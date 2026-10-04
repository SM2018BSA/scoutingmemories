#!/usr/bin/env python3
"""
Scouting Memories page OCR service.

Reads the text on ONE page of a scanned PDF at a time. WordPress asks for a page, this
service downloads the PDF from the archive's public storage, turns the page into a picture
with Poppler and reads it with Tesseract. Nothing is read in bulk.

Standard library only. Runs behind a web server on the same machine and listens only on
127.0.0.1. Every request except /v1/health must be signed with the shared secret.

  POST /v1/jobs        {"url": "https://storage.../file.pdf", "page": 12}
  GET  /v1/jobs/<id>   -> queued | working | done (text, words) | failed (error)
  GET  /v1/health      -> {"ok": true}

Settings come from the environment (see README.md).
"""

import csv
import hashlib
import hmac
import http.client
import http.server
import ipaddress
import json
import math
import os
import re
import secrets
import shutil
import socket
import sqlite3
import subprocess
import sys
import tempfile
import threading
import time
import traceback
import urllib.error
import urllib.parse
import urllib.request

try:
    import resource
except ImportError:  # not on Windows; only used for a log line
    resource = None


def log(message):
    print(message, flush=True)


# ---- Settings ------------------------------------------------------------------------------

def env_int(name, default, low, high):
    given = os.environ.get(name)
    if given is None or given.strip() == '':
        return default
    try:
        value = int(given.strip())
    except ValueError:
        log('%s=%r is not a whole number; using %d' % (name, given, default))
        return default
    if not low <= value <= high:
        log('%s=%d is outside %d to %d; using %d' % (name, value, low, high, max(low, min(high, value))))
    return max(low, min(high, value))


def env_switch(name):
    given = os.environ.get(name, '').strip().lower()
    if given in ('1', 'yes', 'true', 'on'):
        return True
    if given not in ('', '0', 'no', 'false', 'off'):
        log('%s=%r is not 0 or 1; using 0' % (name, given))
    return False


def first_dir(*names):
    """systemd passes STATE_DIRECTORY / CACHE_DIRECTORY; the OCR_* names are for running by hand"""
    for name in names:
        value = os.environ.get(name, '').split(':')[0]
        if value:
            return value
    return None


# Taken out of the environment so the programs this service starts do not inherit it
SECRET = os.environ.pop('OCR_SECRET', '').encode()
try:
    # ...and they cannot read it back from this process either (PR_SET_DUMPABLE off closes
    # /proc/<this process>/environ to other processes of the same user)
    import ctypes
    ctypes.CDLL(None, use_errno=True).prctl(4, 0, 0, 0, 0)
except Exception:
    pass

ALLOWED_HOST = os.environ.get('OCR_ALLOWED_HOST', 'storage.scoutingmemories.org').strip().lower()
LISTEN_HOST = os.environ.get('OCR_LISTEN_HOST', '127.0.0.1')
PORT = env_int('OCR_PORT', 8000, 1, 65535)
MAX_PDF_MB = env_int('OCR_MAX_PDF_MB', 60, 1, 500)
PAGES_PER_HOUR = env_int('OCR_PAGES_PER_HOUR', 120, 1, 100000)
QUEUE_MAX = env_int('OCR_QUEUE_MAX', 50, 1, 10000)
PAUSED = env_switch('OCR_PAUSED')
DPI = env_int('OCR_DPI', 300, 72, 600)
MAX_PIXELS = env_int('OCR_MAX_PIXELS', 40000000, 1000000, 200000000)
CACHE_MB = env_int('OCR_CACHE_MB', 2048, 50, 100000)
KEEP_DAYS = env_int('OCR_KEEP_DAYS', 7, 1, 365)

STATE_DIR = first_dir('STATE_DIRECTORY', 'OCR_STATE_DIR') or os.path.abspath('state')
CACHE_DIR = first_dir('CACHE_DIRECTORY', 'OCR_CACHE_DIR') or os.path.abspath('cache')
DB_PATH = os.path.join(STATE_DIR, 'jobs.sqlite3')
PDF_DIR = os.path.join(CACHE_DIR, 'pdf')
WORK_DIR = os.path.join(CACHE_DIR, 'work')

MAX_PDF_BYTES = MAX_PDF_MB * 1024 * 1024
CACHE_BYTES = CACHE_MB * 1024 * 1024
MAX_BODY = 8192             # a request is a short piece of JSON
MAX_URL = 1024
MAX_PAGE = 9999
MIN_DPI = 72                # a page that would need less than this to fit OCR_MAX_PIXELS is refused
SIGNATURE_WINDOW = 300      # seconds a signed request stays valid
MAX_HANDLERS = 16           # requests answered at the same time
DOWNLOAD_SECONDS = 60
PDFINFO_SECONDS = 20
RENDER_SECONDS = 60
OCR_SECONDS = 120
MAX_TEXT = 200000           # characters of text kept for one page
MAX_WORDS = 20000
INSTALLED_UNIT = '/etc/systemd/system/scouting-ocr.service'

ENGINE = 'tesseract'
WAKE = threading.Event()    # set when a job is added, so the worker does not wait


# ---- Job list (SQLite) ---------------------------------------------------------------------

SCHEMA_VERSION = 1
SCHEMA = """
CREATE TABLE IF NOT EXISTS jobs (
    id       TEXT PRIMARY KEY,
    url      TEXT NOT NULL,
    page     INTEGER NOT NULL,
    status   TEXT NOT NULL,          -- queued | working | done | failed
    created  INTEGER NOT NULL,
    started  INTEGER,
    finished INTEGER,
    seconds  REAL,
    pages    INTEGER,
    width    INTEGER,
    height   INTEGER,
    dpi      INTEGER,
    engine   TEXT,
    text     TEXT,
    words    TEXT,
    error    TEXT,
    UNIQUE (url, page)
);
CREATE INDEX IF NOT EXISTS jobs_status ON jobs (status, created);
-- one row for every page accepted (first requests and retries), for the hourly limit
CREATE TABLE IF NOT EXISTS accepted (at INTEGER NOT NULL);
"""


def connect(wait=3):
    con = sqlite3.connect(DB_PATH, timeout=wait, isolation_level=None)
    con.row_factory = sqlite3.Row
    con.execute('PRAGMA journal_mode=WAL')
    return con


def job_answer(con, row):
    """What a caller is told about a job"""
    answer = {'id': row['id'], 'status': row['status'], 'page': row['page']}
    if row['status'] == 'queued':
        ahead = con.execute(
            "SELECT COUNT(*) FROM jobs WHERE status IN ('queued', 'working') AND (created < ? OR (created = ? AND id < ?))",
            (row['created'], row['created'], row['id'])).fetchone()[0]
        answer['position'] = ahead + 1
    elif row['status'] == 'done':
        answer.update({
            'text': row['text'] or '',
            'words': json.loads(row['words'] or '[]'),
            'pages': row['pages'], 'width': row['width'], 'height': row['height'],
            'dpi': row['dpi'], 'seconds': row['seconds'], 'engine': row['engine'],
        })
    elif row['status'] == 'failed':
        answer['error'] = row['error'] or 'internal'
    return answer


class Refused(Exception):
    def __init__(self, status, code):
        super().__init__(code)
        self.status = status
        self.code = code


def add_job(con, url, page):
    """Add a page to the list, or give back the job that already exists for it"""
    now = int(time.time())
    con.execute('BEGIN IMMEDIATE')
    try:
        row = con.execute('SELECT * FROM jobs WHERE url = ? AND page = ?', (url, page)).fetchone()
        if row and row['status'] != 'failed':
            con.execute('COMMIT')
            return row, False
        if PAUSED:
            raise Refused(503, 'paused')
        waiting = con.execute("SELECT COUNT(*) FROM jobs WHERE status IN ('queued', 'working')").fetchone()[0]
        if waiting >= QUEUE_MAX:
            raise Refused(429, 'queue_full')
        recent = con.execute('SELECT COUNT(*) FROM accepted WHERE at > ?', (now - 3600,)).fetchone()[0]
        if recent >= PAGES_PER_HOUR:
            raise Refused(429, 'hourly_limit')
        if row:  # a page that failed before is tried again
            con.execute(
                "UPDATE jobs SET status = 'queued', created = ?, started = NULL, finished = NULL, error = NULL WHERE id = ?",
                (now, row['id']))
            job_id = row['id']
        else:
            job_id = secrets.token_hex(16)
            con.execute("INSERT INTO jobs (id, url, page, status, created) VALUES (?, ?, ?, 'queued', ?)",
                        (job_id, url, page, now))
        con.execute('INSERT INTO accepted (at) VALUES (?)', (now,))
        row = con.execute('SELECT * FROM jobs WHERE id = ?', (job_id,)).fetchone()
        con.execute('COMMIT')
        return row, True
    except BaseException:
        try:
            con.execute('ROLLBACK')
        except sqlite3.Error:
            pass
        raise


# ---- Checking what was asked for -----------------------------------------------------------

PATH_SAFE = "/-._~!$&'()*+,;=:@"


def clean_url(value):
    """The link in one fixed form (https, the storage host, the path percent-encoded), or None.
    Only a .pdf on the archive's storage is accepted. A query string is dropped: stored files
    need none, and this way one file always has one link."""
    if not isinstance(value, str) or not value or len(value) > MAX_URL:
        return None
    if re.search(r'[\x00-\x20\x7f\\]', value):
        return None
    try:
        value.encode('utf-8')
        parts = urllib.parse.urlsplit(value)
        port = parts.port
    except (UnicodeError, ValueError):
        return None
    if parts.scheme != 'https' or parts.username is not None or parts.password is not None:
        return None
    if (parts.hostname or '').lower() != ALLOWED_HOST or port not in (None, 443):
        return None
    if parts.fragment:
        return None
    try:
        path = urllib.parse.unquote(parts.path, errors='strict')
    except UnicodeError:
        return None
    if re.search(r'[\x00-\x1f\x7f\\]', path) or not path.startswith('/') or not path.lower().endswith('.pdf'):
        return None
    if any(segment in ('', '.', '..') for segment in path.split('/')[1:]):
        return None
    return 'https://%s%s' % (ALLOWED_HOST, urllib.parse.quote(path, safe=PATH_SAFE))


def file_name(url):
    return urllib.parse.unquote(url.rsplit('/', 1)[-1])[:80]


# ---- Reading one page ----------------------------------------------------------------------

class OcrError(Exception):
    """A page could not be read; the code is what the caller is told"""


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None  # a redirect becomes an error: the download never leaves the storage host


OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect)


def cache_path(url):
    return os.path.join(PDF_DIR, hashlib.sha256(url.encode()).hexdigest() + '.pdf')


def forget_pdf(url):
    try:
        os.remove(cache_path(url))
    except OSError:
        pass


def fetch_pdf(url):
    """Download the PDF (or reuse the copy kept on disk) and return its path"""
    path = cache_path(url)
    if os.path.isfile(path) and os.path.getsize(path) > 0:
        return path

    # The storage host must be a public address (never this machine or a private network)
    try:
        addresses = {info[4][0] for info in socket.getaddrinfo(ALLOWED_HOST, 443, type=socket.SOCK_STREAM)}
    except OSError:
        raise OcrError('download_failed')
    if not addresses or not all(ipaddress.ip_address(a).is_global for a in addresses):
        raise OcrError('download_failed')

    request = urllib.request.Request(url, headers={'User-Agent': 'ScoutingMemoriesOCR/1.0', 'Accept': 'application/pdf'})
    partial = path + '.part'
    deadline = time.monotonic() + DOWNLOAD_SECONDS
    try:
        with OPENER.open(request, timeout=30) as response:
            if response.status != 200:
                raise OcrError('download_failed')
            length = response.headers.get('Content-Length') or ''
            expected = int(length) if re.fullmatch(r'[0-9]{1,12}', length) else None
            if expected is not None and expected > MAX_PDF_BYTES:
                raise OcrError('too_large')
            size = 0
            head = b''
            with open(partial, 'wb') as out:
                while True:
                    chunk = response.read1(65536)
                    if not chunk:
                        break
                    size += len(chunk)
                    if size > MAX_PDF_BYTES:
                        raise OcrError('too_large')
                    if time.monotonic() > deadline:
                        raise OcrError('timeout')
                    if len(head) < 1024:
                        head += chunk[:1024 - len(head)]
                    out.write(chunk)
        if expected is not None and size != expected:
            raise OcrError('download_failed')  # the download was cut short
        if b'%PDF-' not in head:
            raise OcrError('not_pdf')
        os.replace(partial, path)
    except OcrError:
        raise
    except TimeoutError:
        raise OcrError('timeout')
    except urllib.error.URLError as problem:
        raise OcrError('timeout' if isinstance(problem.reason, TimeoutError) else 'download_failed')
    except (http.client.HTTPException, OSError, ValueError):
        raise OcrError('download_failed')
    finally:
        if os.path.exists(partial):
            os.remove(partial)
    trim_cache(keep=path)
    return path


def trim_cache(keep=None):
    """Delete PDFs kept longer than OCR_KEEP_DAYS, then the oldest ones while the folder is over its size limit"""
    too_old = time.time() - KEEP_DAYS * 86400
    files = []
    for name in os.listdir(PDF_DIR):
        full = os.path.join(PDF_DIR, name)
        try:
            info = os.stat(full)
            if full != keep and info.st_mtime < too_old:
                os.remove(full)
                continue
        except OSError:
            continue
        files.append((info.st_mtime, info.st_size, full))
    total = sum(size for _, size, _ in files)
    for _, size, full in sorted(files):
        if total <= CACHE_BYTES:
            break
        if full == keep:
            continue
        try:
            os.remove(full)
            total -= size
        except OSError:
            pass


def run(command, seconds, code, env=None):
    """Run one tool. Its output goes to temporary files, so a tool that prints a lot cannot use up memory."""
    with tempfile.TemporaryFile() as out, tempfile.TemporaryFile() as err:
        try:
            done = subprocess.run(command, stdin=subprocess.DEVNULL, stdout=out, stderr=err, timeout=seconds, env=env)
        except subprocess.TimeoutExpired:
            raise OcrError('timeout')
        except OSError as problem:
            log('%s could not be started: %s' % (command[0], problem))
            raise OcrError('internal')
        if done.returncode != 0:
            end = err.seek(0, os.SEEK_END)
            err.seek(max(0, end - 300))
            log('%s failed (%d): %s' % (command[0], done.returncode, err.read().decode('utf-8', 'replace').strip()))
            raise OcrError(code)
        out.seek(0)
        return out.read(65536).decode('utf-8', 'replace')


NUMBER = r'([0-9.]+(?:e[+-]?[0-9]+)?)'
PAGE_SIZE = re.compile(r'^Page\s+\d+\s+size:\s+' + NUMBER + r'\s+x\s+' + NUMBER + r'\s+pts', re.M | re.I)


def page_dpi(pdf, page):
    """Page count of the document and the resolution to render this page at"""
    info = run(['pdfinfo', pdf], PDFINFO_SECONDS, 'not_pdf')
    counts = re.findall(r'^Pages:\s+(\d+)\s*$', info, re.M)  # the last match: document details print first
    if not counts or int(counts[-1]) < 1:
        raise OcrError('not_pdf')
    pages = int(counts[-1])
    if page > pages:
        raise OcrError('no_such_page')
    info = run(['pdfinfo', '-f', str(page), '-l', str(page), pdf], PDFINFO_SECONDS, 'not_pdf')
    sizes = PAGE_SIZE.findall(info)
    if not sizes:
        raise OcrError('render_failed')
    square_inches = (float(sizes[-1][0]) / 72.0) * (float(sizes[-1][1]) / 72.0)
    if not square_inches > 0:
        raise OcrError('render_failed')
    dpi = DPI
    if square_inches * dpi * dpi > MAX_PIXELS:
        dpi = int(math.sqrt(MAX_PIXELS / square_inches))  # an oversized page gets a lower resolution
    if dpi < MIN_DPI:
        raise OcrError('page_too_large')
    return pages, dpi


def parse_tsv(path):
    """Tesseract's table of what it found: the picture's size and every word with its place"""
    width = height = 0
    words = []
    with open(path, 'r', encoding='utf-8', errors='replace', newline='') as handle:
        for row in csv.DictReader(handle, delimiter='\t', quoting=csv.QUOTE_NONE):
            try:
                level = int(row['level'])
                if level == 1:
                    width, height = int(row['width']), int(row['height'])
                elif level == 5 and (row.get('text') or '').strip() and len(words) < MAX_WORDS:
                    words.append([
                        int(row['block_num']), int(row['par_num']), int(row['line_num']),
                        int(row['left']), int(row['top']), int(row['width']), int(row['height']),
                        int(round(float(row['conf']))), row['text'].strip(),
                    ])
            except (KeyError, TypeError, ValueError):
                continue
    return width, height, words


def read_page(job_id, url, page):
    pdf = fetch_pdf(url)
    work = os.path.join(WORK_DIR, job_id)
    shutil.rmtree(work, ignore_errors=True)
    os.makedirs(work)
    try:
        try:
            pages, dpi = page_dpi(pdf, page)
            # -cropbox: the same part of the page that pdfinfo measured and that PDF viewers show
            run(['pdftoppm', '-f', str(page), '-l', str(page), '-r', str(dpi), '-cropbox', '-gray', '-png', '-singlefile',
                 pdf, os.path.join(work, 'page')], RENDER_SECONDS, 'render_failed')
        except OcrError as problem:
            if str(problem) in ('not_pdf', 'render_failed'):
                forget_pdf(url)  # the copy on disk may be damaged: download it again next time
            raise
        picture = os.path.join(work, 'page.png')
        if not os.path.isfile(picture):
            raise OcrError('render_failed')
        environment = dict(os.environ, OMP_THREAD_LIMIT='1')  # one page at a time, one processor thread
        # --psm 1: find the layout and which way up the page is
        run(['tesseract', picture, os.path.join(work, 'out'), '-l', 'eng', '--psm', '1', '--dpi', str(dpi), 'txt', 'tsv'],
            OCR_SECONDS, 'ocr_failed', env=environment)
        with open(os.path.join(work, 'out.txt'), 'r', encoding='utf-8', errors='replace') as handle:
            text = handle.read().replace('\f', '').strip()[:MAX_TEXT]
        width, height, words = parse_tsv(os.path.join(work, 'out.tsv'))
    except FileNotFoundError:
        raise OcrError('ocr_failed')
    finally:
        shutil.rmtree(work, ignore_errors=True)
    return {'text': text, 'words': words, 'pages': pages, 'width': width, 'height': height, 'dpi': dpi}


def tool_memory():
    if resource is None:
        return ''
    return ', largest tool memory since start %d MB' % (resource.getrusage(resource.RUSAGE_CHILDREN).ru_maxrss // 1024)


def worker():
    """Reads the waiting pages one at a time, oldest first"""
    con = connect(wait=10)
    tidied = 0.0
    while True:
        try:
            now = int(time.time())
            # Only this loop marks a job 'working', so one found here was left by an error: fail it
            con.execute("UPDATE jobs SET status = 'failed', finished = ?, error = 'internal' WHERE status = 'working'", (now,))
            if time.time() - tidied > 3600:
                con.execute("DELETE FROM jobs WHERE status IN ('done', 'failed') AND finished < ?", (now - KEEP_DAYS * 86400,))
                con.execute('DELETE FROM accepted WHERE at < ?', (now - 7200,))
                trim_cache()
                tidied = time.time()
            row = None if PAUSED else con.execute(
                "SELECT * FROM jobs WHERE status = 'queued' ORDER BY created, id LIMIT 1").fetchone()
            if row is None:
                WAKE.wait(5)
                WAKE.clear()
                continue
            name = file_name(row['url'])
            con.execute("UPDATE jobs SET status = 'working', started = ? WHERE id = ?", (now, row['id']))
            began = time.monotonic()
            try:
                result = read_page(row['id'], row['url'], row['page'])
                seconds = round(time.monotonic() - began, 1)
                con.execute(
                    "UPDATE jobs SET status = 'done', finished = ?, seconds = ?, pages = ?, width = ?, height = ?, dpi = ?, "
                    "engine = ?, text = ?, words = ?, error = NULL WHERE id = ?",
                    (int(time.time()), seconds, result['pages'], result['width'], result['height'], result['dpi'],
                     ENGINE, result['text'], json.dumps(result['words'], ensure_ascii=False, separators=(',', ':')), row['id']))
                log('done: %s page %d, %d words, %.1f s at %d dpi%s' % (
                    name, row['page'], len(result['words']), seconds, result['dpi'], tool_memory()))
            except OcrError as problem:
                con.execute("UPDATE jobs SET status = 'failed', finished = ?, error = ? WHERE id = ?",
                            (int(time.time()), str(problem), row['id']))
                log('failed: %s page %d: %s' % (name, row['page'], problem))
            except Exception:
                log('failed: %s page %d: internal\n%s' % (name, row['page'], traceback.format_exc()))
                con.execute("UPDATE jobs SET status = 'failed', finished = ?, error = 'internal' WHERE id = ?",
                            (int(time.time()), row['id']))
        except Exception:
            log('worker problem:\n' + traceback.format_exc())
            time.sleep(5)


# ---- Web requests --------------------------------------------------------------------------

class Handler(http.server.BaseHTTPRequestHandler):
    server_version = 'scouting-ocr'
    sys_version = ''
    timeout = 10

    def log_request(self, code='-', size='-'):
        status = getattr(code, 'value', code)
        if self.command == 'GET' and status == 200:
            return  # successful polls and health checks would bury the lines that matter
        log('%s %s -> %s' % (self.command, self.path[:120], status))

    def log_message(self, format, *args):
        log(format % args)

    def reply(self, status, answer):
        body = json.dumps(answer, ensure_ascii=False, separators=(',', ':')).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.end_headers()
        self.wfile.write(body)

    def signature_problem(self, method, path, body):
        """None when the request is properly signed, otherwise the error to answer with"""
        stamp = self.headers.get('X-Scouting-Time', '')
        signature = self.headers.get('X-Scouting-Signature', '').lower()
        if not re.fullmatch(r'[0-9]{1,12}', stamp) or not re.fullmatch(r'[0-9a-f]{64}', signature):
            return 'bad_signature'
        message = '%s\n%s\n%s\n%s' % (stamp, method, path, hashlib.sha256(body).hexdigest())
        expected = hmac.new(SECRET, message.encode(), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, signature):
            return 'bad_signature'
        if abs(time.time() - int(stamp)) > SIGNATURE_WINDOW:
            log('refused: the request time is %d seconds off (the caller\'s clock or this one is wrong)' % (time.time() - int(stamp)))
            return 'bad_time'
        return None

    def handle_request(self, method):
        try:
            self.answer(method)
        except Exception:
            log('request problem:\n' + traceback.format_exc())
            try:
                self.reply(500, {'error': 'internal'})
            except Exception:
                pass

    def answer(self, method):
        path = self.path
        if len(path) > 200 or not re.fullmatch(r'/[A-Za-z0-9/_-]*', path):
            return self.reply(400, {'error': 'bad_request'})
        body = b''
        if method == 'POST':
            length = self.headers.get('Content-Length', '')
            if not re.fullmatch(r'[0-9]{1,6}', length):
                return self.reply(411, {'error': 'bad_request'})
            if int(length) > MAX_BODY:
                return self.reply(413, {'error': 'body_too_large'})
            body = self.rfile.read(int(length))
        if method == 'GET' and path == '/v1/health':
            return self.reply(200, {'ok': True})
        problem = self.signature_problem(method, path, body)
        if problem:
            return self.reply(401, {'error': problem})

        con = connect()
        try:
            if method == 'POST' and path == '/v1/jobs':
                try:
                    asked = json.loads(body.decode('utf-8'))
                except (UnicodeDecodeError, ValueError):
                    return self.reply(400, {'error': 'bad_request'})
                if not isinstance(asked, dict):
                    return self.reply(400, {'error': 'bad_request'})
                page = asked.get('page')
                if isinstance(page, bool) or not isinstance(page, int) or not 1 <= page <= MAX_PAGE:
                    return self.reply(400, {'error': 'bad_page'})
                if not isinstance(asked.get('url'), str):
                    return self.reply(400, {'error': 'bad_request'})
                url = clean_url(asked['url'])
                if url is None:
                    return self.reply(403, {'error': 'host_not_allowed'})
                try:
                    row, added = add_job(con, url, page)
                except Refused as refusal:
                    return self.reply(refusal.status, {'error': refusal.code})
                if added:
                    WAKE.set()
                return self.reply(202 if added else 200, job_answer(con, row))

            found = re.fullmatch(r'/v1/jobs/([0-9a-f]{32})', path)
            if method == 'GET' and found:
                row = con.execute('SELECT * FROM jobs WHERE id = ?', (found.group(1),)).fetchone()
                if row is None:
                    return self.reply(404, {'error': 'not_found'})
                return self.reply(200, job_answer(con, row))
            return self.reply(404, {'error': 'not_found'})
        finally:
            con.close()

    def do_GET(self):
        self.handle_request('GET')

    def do_POST(self):
        self.handle_request('POST')


class Server(http.server.ThreadingHTTPServer):
    daemon_threads = True
    slots = threading.BoundedSemaphore(MAX_HANDLERS)

    def process_request(self, request, client_address):
        if not self.slots.acquire(blocking=False):
            self.shutdown_request(request)  # too many at once: the web server in front answers for us
            return
        super().process_request(request, client_address)

    def process_request_thread(self, request, client_address):
        try:
            super().process_request_thread(request, client_address)
        finally:
            self.slots.release()


def prepare_job_list():
    con = connect(wait=10)
    exists = con.execute("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'jobs'").fetchone()
    if exists and con.execute('PRAGMA user_version').fetchone()[0] != SCHEMA_VERSION:
        # The list is disposable (WordPress keeps the text), so a new layout starts a new list
        con.executescript('DROP TABLE IF EXISTS jobs; DROP TABLE IF EXISTS accepted;')
        log('the job list was reset: this version stores it differently')
    con.executescript(SCHEMA)
    con.execute('PRAGMA user_version = %d' % SCHEMA_VERSION)
    # A page that was being read when the service stopped goes back in line once. If it was
    # already given that second chance, reading it is what stops the service: fail it.
    now = int(time.time())
    con.execute("UPDATE jobs SET status = 'failed', finished = ?, error = 'internal' WHERE status = 'working' AND error = 'retry'", (now,))
    con.execute("UPDATE jobs SET status = 'queued', started = NULL, error = 'retry' WHERE status = 'working'")
    con.close()


def main():
    global ENGINE
    if len(SECRET) < 32:
        sys.exit('OCR_SECRET is missing or shorter than 32 characters. Nothing was started.')
    for folder in (STATE_DIR, PDF_DIR, WORK_DIR):
        os.makedirs(folder, exist_ok=True)
    shutil.rmtree(WORK_DIR, ignore_errors=True)  # leftovers of a page that was being read at a restart
    os.makedirs(WORK_DIR, exist_ok=True)
    for name in os.listdir(PDF_DIR):
        if name.endswith('.part'):
            os.remove(os.path.join(PDF_DIR, name))
    try:
        version = subprocess.run(['tesseract', '--version'], stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                                 stderr=subprocess.STDOUT, timeout=20)
        ENGINE = (version.stdout.decode('utf-8', 'replace').splitlines() or ['tesseract'])[0].strip()[:40] + ' eng'
    except (OSError, subprocess.TimeoutExpired):
        sys.exit('Tesseract is not installed. Nothing was started.')
    for tool in ('pdfinfo', 'pdftoppm'):
        if shutil.which(tool) is None:
            sys.exit('%s (poppler-utils) is not installed. Nothing was started.' % tool)

    prepare_job_list()

    # The service file is installed by hand; say so when the copy in git has moved on
    try:
        with open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'scouting-ocr.service'), 'rb') as mine, \
                open(INSTALLED_UNIT, 'rb') as installed:
            if mine.read().replace(b'\r\n', b'\n') != installed.read().replace(b'\r\n', b'\n'):
                log('NOTE: scouting-ocr.service in git differs from %s. Copy it there and run: systemctl daemon-reload' % INSTALLED_UNIT)
    except OSError:
        pass

    threading.Thread(target=worker, name='worker', daemon=True).start()
    server = Server((LISTEN_HOST, PORT), Handler)
    log('scouting-ocr listening on %s:%d (%s; storage host %s; %s)' % (
        LISTEN_HOST, PORT, ENGINE, ALLOWED_HOST, 'PAUSED: reading nothing' if PAUSED else 'reading pages'))
    log('limits: %d pages an hour, %d waiting, PDFs up to %d MB, %d dpi, %d pixels a page, %d MB of PDFs kept, %d days' % (
        PAGES_PER_HOUR, QUEUE_MAX, MAX_PDF_MB, DPI, MAX_PIXELS, CACHE_MB, KEEP_DAYS))
    server.serve_forever()


if __name__ == '__main__':
    main()
