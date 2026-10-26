// Stand-in for the OCR app on the development server (ocr-service/ocr_service.py), so the
// Text button can be tested in every state without the droplet: the test documents are
// local files the real app can't download, and "down", "slow" or "failed" can't be made to
// happen there on demand. Test environment only.
//
//   node fake-ocr.mjs <port> <secret>
//
// It checks signatures exactly as the real app does (HMAC-SHA256 over time, method, path and
// the body's SHA-256; 5 minutes either way) and answers in the same shapes. The tests switch
// its behaviour with POST /__test/mode {"mode": ...}; GET /__test/requests lists what it was
// sent, with the signature verdict; POST /__test/reset forgets jobs and requests.
//
// Modes:
//   fast     new page: queued, done on the first status question
//   instant  done straight away (a page the service had already read)
//   slow     stays queued for ever
//   fail     queued, then failed (no_such_page)
//   empty    queued, then done with no text (a photo or a blank page)
//   busy     refuses new pages with 429 queue_full
//   down     drops every connection, like a stopped service
import { createServer } from 'node:http';
import { createHash, createHmac, randomBytes, timingSafeEqual } from 'node:crypto';

const [port = '8899', secret = 'fake-ocr-test-secret'] = process.argv.slice(2);
const WINDOW = 300;
const STORAGE = 'https://storage.scoutingmemories.org/';
const FAKE_TEXT ='Camp Tahquitz opened in 1952\non the shore of the lake.\n\nWaterfront rules: every Scout swims with a buddy.';

let mode = 'fast';
let jobs = new Map();
let requests = [];

const sha256 = (data) => createHash('sha256').update(data).digest('hex');

function signatureProblem(req, path, body) {
    const stamp = String(req.headers['x-scouting-time'] || '');
    const signature = String(req.headers['x-scouting-signature'] || '').toLowerCase();
    if (!/^[0-9]{1,12}$/.test(stamp) || !/^[0-9a-f]{64}$/.test(signature)) return 'bad_signature';
    const expected = createHmac('sha256', secret).update(`${stamp}\n${req.method}\n${path}\n${sha256(body)}`).digest('hex');
    if (!timingSafeEqual(Buffer.from(expected), Buffer.from(signature))) return 'bad_signature';
    if (Math.abs(Date.now() / 1000 - Number(stamp)) > WINDOW) return 'bad_time';
    return null;
}

function reply(res, status, answer) {
    const body = JSON.stringify(answer);
    res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': Buffer.byteLength(body), 'Cache-Control': 'no-store' });
    res.end(body);
}

function answerFor(job) {
    const out = { id: job.id, status: job.status, page: job.page };
    if (job.status === 'queued') out.position = 1;
    if (job.status === 'done') Object.assign(out, { text: job.text, words: [], pages: 12, width: 2550, height: 3300, dpi: 300, seconds: 1.5, engine: 'fake-ocr test' });
    if (job.status === 'failed') out.error = 'no_such_page';
    return out;
}

// What a waiting job turns into when its status is asked for, by the mode at that moment
// (so a test can let a slow page finish)
function advance(job) {
    if (job.status !== 'queued') return;
    if (mode === 'fast' || mode === 'instant') Object.assign(job, { status: 'done', text: FAKE_TEXT });
    if (mode === 'empty') Object.assign(job, { status: 'done', text: '' });
    if (mode === 'fail') job.status = 'failed';
}

function control(req, res, path, body) {
    if (req.method === 'POST' && path === '/__test/mode') {
        mode = JSON.parse(body.toString() || '{}').mode || 'fast';
        return reply(res, 200, { mode });
    }
    if (req.method === 'POST' && path === '/__test/reset') {
        jobs = new Map();
        requests = [];
        mode = 'fast';
        return reply(res, 200, { ok: true });
    }
    if (req.method === 'GET' && path === '/__test/requests') return reply(res, 200, requests);
    return reply(res, 404, { error: 'not_found' });
}

function service(req, res, path, body) {
    if (req.method === 'GET' && path === '/v1/health') return reply(res, 200, { ok: true });
    const problem = signatureProblem(req, path, body);
    requests.push({ method: req.method, path, body: body.toString(), signature: problem || 'ok' });
    if (problem) return reply(res, 401, { error: problem });

    if (req.method === 'POST' && path === '/v1/jobs') {
        let asked;
        try { asked = JSON.parse(body.toString()); } catch { return reply(res, 400, { error: 'bad_request' }); }
        if (!asked || typeof asked !== 'object') return reply(res, 400, { error: 'bad_request' });
        if (!Number.isInteger(asked.page) || asked.page < 1 || asked.page > 9999) return reply(res, 400, { error: 'bad_page' });
        if (typeof asked.url !== 'string' || !asked.url.startsWith(STORAGE) || asked.url.includes('?')) return reply(res, 403, { error: 'host_not_allowed' });
        const key = asked.url + '#' + asked.page;
        const old = jobs.get(key);
        if (old && old.status !== 'failed') return reply(res, 200, answerFor(old));
        if (mode === 'busy') return reply(res, 429, { error: 'queue_full' });
        const job = { id: randomBytes(16).toString('hex'), page: asked.page, status: 'queued', text: '' };
        if (mode === 'instant') Object.assign(job, { status: 'done', text: FAKE_TEXT });
        jobs.set(key, job);
        return reply(res, 202, answerFor(job));
    }
    const found = /^\/v1\/jobs\/([0-9a-f]{32})$/.exec(path);
    if (req.method === 'GET' && found) {
        const job = [...jobs.values()].find((j) => j.id === found[1]);
        if (!job) return reply(res, 404, { error: 'not_found' });
        advance(job);
        return reply(res, 200, answerFor(job));
    }
    return reply(res, 404, { error: 'not_found' });
}

createServer((req, res) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => {
        const body = Buffer.concat(chunks);
        const path = new URL(req.url, 'http://x').pathname;
        if (path.startsWith('/__test/')) return control(req, res, path, body);
        if (mode === 'down') return req.socket.destroy();
        if (path.length > 200 || !/^\/[A-Za-z0-9/_-]*$/.test(path)) return reply(res, 400, { error: 'bad_request' });
        service(req, res, path, body);
    });
}).listen(Number(port), '127.0.0.1', () => console.log(`fake OCR service on http://127.0.0.1:${port}`));
