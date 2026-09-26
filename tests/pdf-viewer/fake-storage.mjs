// Stand-in for storage.scoutingmemories.org, so the stream endpoint can be tested end to end
// (TLS verification, pinned IP, ranges, redirects) without the internet. Test environment only.
//
//   node fake-storage.mjs <tls-dir> <fixtures-dir> <ip> <port>
import { createServer } from 'node:https';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const [tlsDir, fixtures, ip = '1.2.3.4', port = '443'] = process.argv.slice(2);
const guide = readFileSync(join(fixtures, 'leaders-guide.pdf'));

function sendPdf(req, res, body) {
    const range = /^bytes=(\d*)-(\d*)$/.exec(req.headers.range || '');
    const headers = { 'Content-Type': 'application/pdf', 'Accept-Ranges': 'bytes', ETag: '"fixture-1"', 'X-Upstream-Secret': 'must-not-leak' };
    if (range) {
        const start = range[1] === '' ? body.length - Number(range[2]) : Number(range[1]);
        const end = range[1] !== '' && range[2] !== '' ? Math.min(Number(range[2]), body.length - 1) : body.length - 1;
        if (start >= body.length || start > end) {
            res.writeHead(416, { 'Content-Range': `bytes */${body.length}` });
            return res.end();
        }
        res.writeHead(206, { ...headers, 'Content-Range': `bytes ${start}-${end}/${body.length}`, 'Content-Length': end - start + 1 });
        return res.end(req.method === 'HEAD' ? undefined : body.subarray(start, end + 1));
    }
    res.writeHead(200, { ...headers, 'Content-Length': body.length });
    res.end(req.method === 'HEAD' ? undefined : body);
}

const routes = {
    '/fixtures/leaders-guide.pdf': (req, res) => sendPdf(req, res, guide),
    '/redirect-good.pdf': (req, res) => { res.writeHead(302, { Location: '/fixtures/leaders-guide.pdf' }); res.end(); },
    '/redirect-loop.pdf': (req, res) => { res.writeHead(302, { Location: '/redirect-loop.pdf' }); res.end(); },
    '/redirect-internal.pdf': (req, res) => { res.writeHead(302, { Location: 'http://127.0.0.1:8888/wp-content/uploads/fixtures/leaders-guide.pdf' }); res.end(); },
    '/redirect-metadata.pdf': (req, res) => { res.writeHead(302, { Location: 'http://169.254.169.254/latest/meta-data/x.pdf' }); res.end(); },
    '/html.pdf': (req, res) => { res.writeHead(200, { 'Content-Type': 'text/html' }); res.end('<script>document.title="pwned"</script>'); },
    '/missing.pdf': (req, res) => { res.writeHead(404, { 'Content-Type': 'text/plain' }); res.end('upstream-error-body'); },
};

createServer({
    key: readFileSync(join(tlsDir, 'server.key')),
    cert: readFileSync(join(tlsDir, 'server.crt')),
}, (req, res) => {
    const route = routes[new URL(req.url, 'https://x').pathname];
    if (route) return route(req, res);
    res.writeHead(404);
    res.end();
}).listen(Number(port), ip, () => console.log(`fake storage on https://${ip}:${port}`));
