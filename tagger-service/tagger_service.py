#!/usr/bin/env python3
"""
Scouting Memories AI Tagging Assistant.

Suggests metadata for documents historians are about to upload: the archive's TIMELINE
fields (state, council, camp, lodge, start and end year) and its descriptive META fields
(publisher, dates, identifier, subject, location, physical description). A document's text
(OCR of an uploaded PDF, or pasted) is matched against the site's own index of councils,
camps and lodges, and an AI provider (Claude, Gemini or Grok) is asked to fill in the
fields using ONLY those index entries. Historians review every suggestion.

Standard library only. Runs behind a web server on the same machine and listens only on
127.0.0.1. WordPress pushes the index with HMAC-signed calls. People arrive through a
signed one-time login link on the WordPress page this app is embedded in, or with the
admin passphrase on /login.

Settings come from the environment (see README.md).
"""

import base64
import hashlib
import hmac
import http.server
import json
import os
import re
import secrets
import shutil
import sqlite3
import subprocess
import sys
import threading
import time
import traceback


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
    """systemd passes STATE_DIRECTORY / CACHE_DIRECTORY; the TAGGER_* names are for running by hand"""
    for name in names:
        value = os.environ.get(name, '').split(':')[0]
        if value:
            return value
    return None


# Taken out of the environment so the programs this service starts do not inherit them
SECRET = os.environ.pop('TAGGER_SECRET', '').encode()
ADMIN_KEY = os.environ.pop('TAGGER_ADMIN_KEY', '').encode()
try:
    # ...and they cannot read them back from this process either (PR_SET_DUMPABLE off closes
    # /proc/<this process>/environ to other processes of the same user)
    import ctypes
    ctypes.CDLL(None, use_errno=True).prctl(4, 0, 0, 0, 0)
except Exception:
    pass

ALLOWED_HOST = os.environ.get('TAGGER_ALLOWED_HOST', 'storage.scoutingmemories.org').strip().lower()
LISTEN_HOST = os.environ.get('TAGGER_LISTEN_HOST', '127.0.0.1')
PORT = env_int('TAGGER_PORT', 8001, 1, 65535)
FRAME_ANCESTORS = os.environ.get('TAGGER_FRAME_ANCESTORS', '').strip()
MAX_PDF_MB = env_int('TAGGER_MAX_PDF_MB', 60, 1, 500)
MAX_OCR_PAGES = env_int('TAGGER_MAX_OCR_PAGES', 20, 1, 100)
PAGES_PER_HOUR = env_int('TAGGER_PAGES_PER_HOUR', 120, 1, 100000)
AI_CALLS_PER_DAY = env_int('TAGGER_AI_CALLS_PER_DAY', 200, 1, 100000)
QUEUE_MAX = env_int('TAGGER_QUEUE_MAX', 20, 1, 10000)
PAUSED = env_switch('TAGGER_PAUSED')
DPI = env_int('TAGGER_DPI', 300, 72, 600)
CACHE_MB = env_int('TAGGER_CACHE_MB', 1024, 50, 100000)
KEEP_DAYS = env_int('TAGGER_KEEP_DAYS', 7, 1, 365)
SESSION_HOURS = env_int('TAGGER_SESSION_HOURS', 12, 1, 168)
INSECURE_COOKIE = env_switch('TAGGER_INSECURE_COOKIE')  # local testing over plain http only

STATE_DIR = first_dir('STATE_DIRECTORY', 'TAGGER_STATE_DIR') or os.path.abspath('state')
CACHE_DIR = first_dir('CACHE_DIRECTORY', 'TAGGER_CACHE_DIR') or os.path.abspath('cache')
DB_PATH = os.path.join(STATE_DIR, 'tagger.sqlite3')
UPLOAD_DIR = os.path.join(CACHE_DIR, 'uploads')
WORK_DIR = os.path.join(CACHE_DIR, 'work')
OWN_DIR = os.path.dirname(os.path.abspath(__file__))

MAX_PDF_BYTES = MAX_PDF_MB * 1024 * 1024
CACHE_BYTES = CACHE_MB * 1024 * 1024
MAX_BODY = 8192             # most requests are a short piece of JSON
MAX_CHUNK_BODY = 512 * 1024  # one batch of index rows pushed by WordPress
SIGNATURE_WINDOW = 300      # seconds a signed request stays valid
TOKEN_LIFE_MAX = 600        # a login link's expiry may be at most this far in the future
LOGIN_FAILS_PER_HOUR = 20
MAX_HANDLERS = 16           # requests answered at the same time
INSTALLED_UNIT = '/etc/systemd/system/scouting-tagger.service'

# Who may use the app at all; "admin" (the settings dashboard) is a separate flag on the login link
ALLOWED_ROLES = ('administrator', 'manager', 'historian', 'regional', 'index_contributor')

VERSION = '0.2.0'           # step 2: service skeleton (sessions, pages, index status)
OCR_READY = False           # set in main(); on this PC the OCR tools may be missing


# ---- The database --------------------------------------------------------------------------

SCHEMA_VERSION = 1

# These tables hold what people gave the app; a program update must never wipe them.
DURABLE_SCHEMA = """
CREATE TABLE IF NOT EXISTS settings (
    key     TEXT PRIMARY KEY,
    value   TEXT NOT NULL,
    updated INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS entities (
    type       TEXT NOT NULL,            -- state | council | camp | lodge
    id         INTEGER NOT NULL,         -- the index's own entry id
    name       TEXT NOT NULL,
    number     TEXT NOT NULL DEFAULT '', -- councils and lodges; camps have none
    code       TEXT NOT NULL DEFAULT '', -- states only: the 2-letter code
    slug       TEXT NOT NULL DEFAULT '',
    states     TEXT NOT NULL DEFAULT '[]',  -- JSON list of state entry ids
    councils   TEXT NOT NULL DEFAULT '[]',  -- camps/lodges: JSON list of council entry ids
    start_year INTEGER,
    end_year   INTEGER,
    status     TEXT NOT NULL DEFAULT '',
    aliases    TEXT NOT NULL DEFAULT '[]',  -- earlier or other names
    PRIMARY KEY (type, id)
);
CREATE INDEX IF NOT EXISTS entities_slug ON entities (type, slug);
CREATE TABLE IF NOT EXISTS links (
    relation  TEXT NOT NULL,             -- lineage | camp_council | lodge_council
    parent_id INTEGER NOT NULL,          -- lineage: the later council; else the camp/lodge
    child_id  INTEGER NOT NULL,          -- lineage: the earlier council; else the council
    role      TEXT NOT NULL DEFAULT '',
    from_year INTEGER,
    to_year   INTEGER,
    PRIMARY KEY (relation, parent_id, child_id)
);
CREATE TABLE IF NOT EXISTS posts (
    post_id    INTEGER PRIMARY KEY,      -- an already-tagged document, pushed as an example
    title      TEXT NOT NULL DEFAULT '',
    category   TEXT NOT NULL DEFAULT '',
    states     TEXT NOT NULL DEFAULT '[]',  -- JSON list of 2-letter codes
    council    TEXT NOT NULL DEFAULT '',    -- slugs, as tagged on the site
    camp       TEXT NOT NULL DEFAULT '',
    lodge      TEXT NOT NULL DEFAULT '',
    start_date TEXT NOT NULL DEFAULT '',
    end_date   TEXT NOT NULL DEFAULT '',
    publisher  TEXT NOT NULL DEFAULT '',
    subject    TEXT NOT NULL DEFAULT '',
    identifier TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS name_tokens (
    token TEXT NOT NULL,                 -- one word of an entity's name, alias or number
    type  TEXT NOT NULL,
    id    INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS name_tokens_token ON name_tokens (token);
CREATE TABLE IF NOT EXISTS notes (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    title    TEXT NOT NULL,
    body     TEXT NOT NULL,
    added_at INTEGER NOT NULL
);
"""

# These tables the app can rebuild or forget: a new layout starts them fresh.
DISPOSABLE_SCHEMA = """
CREATE TABLE IF NOT EXISTS jobs (
    id         TEXT PRIMARY KEY,
    kind       TEXT NOT NULL,            -- ocr | suggest | ask
    status     TEXT NOT NULL,            -- queued | working | done | failed
    created    INTEGER NOT NULL,
    started    INTEGER,
    finished   INTEGER,
    session    TEXT NOT NULL,            -- sha256 of the owning session: people see their own jobs only
    user_login TEXT NOT NULL DEFAULT '',
    filename   TEXT NOT NULL DEFAULT '',
    pages      INTEGER,
    page_limit INTEGER,
    pages_done INTEGER NOT NULL DEFAULT 0,
    input      TEXT,
    text       TEXT,
    result     TEXT,
    error      TEXT
);
CREATE INDEX IF NOT EXISTS jobs_status ON jobs (kind, status, created);
CREATE TABLE IF NOT EXISTS sessions (
    sid      TEXT PRIMARY KEY,           -- sha256 of the session value (the value itself is never stored)
    user     TEXT NOT NULL,
    role     TEXT NOT NULL,
    is_admin INTEGER NOT NULL DEFAULT 0,
    csrf     TEXT NOT NULL,
    nonce    TEXT NOT NULL UNIQUE,       -- the login link's nonce: each link works once
    created  INTEGER NOT NULL,
    expires  INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS accepted (at INTEGER NOT NULL, kind TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS accepted_kind ON accepted (kind, at);
CREATE TABLE IF NOT EXISTS ai_calls (
    at        INTEGER NOT NULL,
    user      TEXT NOT NULL DEFAULT '',
    provider  TEXT NOT NULL DEFAULT '',
    model     TEXT NOT NULL DEFAULT '',
    ok        INTEGER NOT NULL DEFAULT 0,
    seconds   REAL,
    chars_in  INTEGER,
    chars_out INTEGER
);
CREATE TABLE IF NOT EXISTS import_batches (
    id       TEXT PRIMARY KEY,
    source   TEXT NOT NULL,              -- wp | csv | xlsx
    started  INTEGER NOT NULL,
    expected TEXT,
    status   TEXT NOT NULL               -- open | committed | aborted
);
CREATE TABLE IF NOT EXISTS staging_rows (
    batch_id TEXT NOT NULL,
    type     TEXT NOT NULL,
    seq      INTEGER NOT NULL,
    row      TEXT NOT NULL
);
"""

DISPOSABLE_TABLES = ('jobs', 'sessions', 'accepted', 'ai_calls', 'import_batches', 'staging_rows')


def connect(wait=3):
    con = sqlite3.connect(DB_PATH, timeout=wait, isolation_level=None)
    con.row_factory = sqlite3.Row
    con.execute('PRAGMA journal_mode=WAL')
    return con


def prepare_database():
    con = connect(wait=10)
    exists = con.execute("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'settings'").fetchone()
    if exists and con.execute('PRAGMA user_version').fetchone()[0] != SCHEMA_VERSION:
        # Only the disposable tables start over; the index, notes and settings are kept
        for table in DISPOSABLE_TABLES:
            con.execute('DROP TABLE IF EXISTS %s' % table)
        log('jobs and sessions were reset: this version stores them differently')
    con.executescript(DURABLE_SCHEMA + DISPOSABLE_SCHEMA)
    con.execute('PRAGMA user_version = %d' % SCHEMA_VERSION)
    now = int(time.time())
    con.execute("UPDATE jobs SET status = 'failed', finished = ?, error = 'internal' WHERE status IN ('queued', 'working')", (now,))
    con.execute('DELETE FROM sessions WHERE expires < ?', (now,))
    con.close()


# What the settings mean when nothing was saved yet. API keys have no default on purpose.
SETTING_DEFAULTS = {
    'provider': 'claude',
    'model_claude': 'claude-sonnet-5-5',
    'model_gemini': 'gemini-2.5-flash',
    'model_grok': 'grok-4',
    'system_prompt': '',          # empty = the default prompt from skills/
    'ocr_pages': '5',
    'ai_calls_per_day': str(AI_CALLS_PER_DAY),
}


def read_settings(con):
    saved = {row['key']: row['value'] for row in con.execute('SELECT key, value FROM settings')}
    merged = dict(SETTING_DEFAULTS)
    merged.update(saved)
    return merged


def write_setting(con, key, value):
    con.execute('INSERT INTO settings (key, value, updated) VALUES (?, ?, ?) '
                'ON CONFLICT (key) DO UPDATE SET value = excluded.value, updated = excluded.updated',
                (key, value, int(time.time())))


def index_summary(con):
    """When the index was last received and how many rows of each kind it holds"""
    merged = read_settings(con)
    counts = {}
    try:
        counts = json.loads(merged.get('index_counts', '') or '{}')
    except ValueError:
        pass
    received = merged.get('index_received_at', '')
    return {
        'received_at': int(received) if received.isdigit() else None,
        'source': merged.get('index_source', '') or None,
        'counts': counts if isinstance(counts, dict) else {},
    }


class Refused(Exception):
    def __init__(self, status, code):
        super().__init__(code)
        self.status = status
        self.code = code


# ---- Login links and sessions --------------------------------------------------------------

TOKEN_PREFIX = 'tagger-login\n'
USER_RE = re.compile(r'[A-Za-z0-9 ._@-]{1,60}')
NONCE_RE = re.compile(r'[A-Za-z0-9_-]{8,64}')


def parse_login_token(value):
    """The signed one-time link from WordPress -> its payload, or None"""
    if not isinstance(value, str) or len(value) > 800:
        return None
    found = re.fullmatch(r'([A-Za-z0-9_-]{2,700})\.([0-9a-f]{64})', value)
    if not found:
        return None
    payload_b64, signature = found.groups()
    expected = hmac.new(SECRET, (TOKEN_PREFIX + payload_b64).encode(), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, signature):
        return None
    try:
        raw = base64.urlsafe_b64decode(payload_b64 + '=' * (-len(payload_b64) % 4))
        payload = json.loads(raw.decode('utf-8'))
    except (ValueError, UnicodeDecodeError):
        return None
    if not isinstance(payload, dict):
        return None
    user, role, admin, expiry, nonce = (payload.get('u'), payload.get('r'), payload.get('a'),
                                        payload.get('x'), payload.get('n'))
    if not isinstance(user, str) or not USER_RE.fullmatch(user):
        return None
    if role not in ALLOWED_ROLES:
        return None
    if admin not in (0, 1) or isinstance(admin, bool):
        return None
    if isinstance(expiry, bool) or not isinstance(expiry, int):
        return None
    now = int(time.time())
    if expiry < now or expiry > now + TOKEN_LIFE_MAX:
        return None
    if not isinstance(nonce, str) or not NONCE_RE.fullmatch(nonce):
        return None
    return {'user': user, 'role': role, 'admin': admin, 'nonce': nonce}


class Sessions:
    """Who is logged in. Only a hash of each session value is kept, so the database alone
    cannot impersonate anyone; the value itself lives in the visitor's cookie or page."""

    @staticmethod
    def hash(value):
        return hashlib.sha256(value.encode()).hexdigest()

    @staticmethod
    def create(con, user, role, admin, nonce):
        """A new session; raises Refused when the login link was already used"""
        value = secrets.token_hex(32)
        now = int(time.time())
        try:
            con.execute('INSERT INTO sessions (sid, user, role, is_admin, csrf, nonce, created, expires) '
                        'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        (Sessions.hash(value), user, role, admin, secrets.token_hex(16), nonce,
                         now, now + SESSION_HOURS * 3600))
        except sqlite3.IntegrityError:
            raise Refused(401, 'link_used')
        return value

    @staticmethod
    def find(con, value):
        if not isinstance(value, str) or not re.fullmatch(r'[0-9a-f]{64}', value):
            return None
        row = con.execute('SELECT * FROM sessions WHERE sid = ?', (Sessions.hash(value),)).fetchone()
        if row is None or row['expires'] < time.time():
            return None
        return row

    @staticmethod
    def drop(con, value):
        con.execute('DELETE FROM sessions WHERE sid = ?', (Sessions.hash(value),))

    @staticmethod
    def cookie(value, clear=False):
        parts = ['tagger_session=%s' % ('' if clear else value), 'Path=/',
                 'Max-Age=%d' % (0 if clear else SESSION_HOURS * 3600), 'HttpOnly']
        if INSECURE_COOKIE:
            parts.append('SameSite=Lax')
        else:
            # The app lives in a frame on the WordPress site, a different origin: the cookie
            # must be Secure + SameSite=None, and Partitioned keeps browsers accepting it there
            parts += ['Secure', 'SameSite=None', 'Partitioned']
        return '; '.join(parts)

    @staticmethod
    def answer(row, value):
        """What a fresh login is told"""
        return {'sid': value, 'user': row['user'], 'role': row['role'],
                'admin': bool(row['is_admin']), 'csrf': row['csrf']}


def too_many_login_failures(con):
    recent = con.execute("SELECT COUNT(*) FROM accepted WHERE kind = 'login_fail' AND at > ?",
                         (int(time.time()) - 3600,)).fetchone()[0]
    return recent >= LOGIN_FAILS_PER_HOUR


# ---- The pages and their files -------------------------------------------------------------

STATIC_TYPES = {'.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
                '.css': 'text/css; charset=utf-8'}
PAGES = {'/': 'app.html', '/settings': 'settings.html', '/login': 'login.html'}
STATIC_FILES = {}           # name -> bytes, read once at start


def load_static():
    folder = os.path.join(OWN_DIR, 'static')
    for name in sorted(os.listdir(folder)):
        if os.path.splitext(name)[1] in STATIC_TYPES:
            with open(os.path.join(folder, name), 'rb') as handle:
                STATIC_FILES[name] = handle.read()
    missing = [name for name in PAGES.values() if name not in STATIC_FILES]
    if missing or 'app.js' not in STATIC_FILES:
        sys.exit('static/ is missing %s. Nothing was started.' % ', '.join(missing or ['app.js']))


def content_security_policy():
    ancestors = FRAME_ANCESTORS or "'none'"
    return ("default-src 'self'; "
            "script-src 'self' https://cdn.jsdelivr.net; "
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
            "font-src https://fonts.gstatic.com; "
            "img-src 'self' data:; "
            "connect-src 'self'; "
            "frame-ancestors %s" % ancestors)


# ---- Web requests --------------------------------------------------------------------------

class Handler(http.server.BaseHTTPRequestHandler):
    server_version = 'scouting-tagger'
    sys_version = ''
    timeout = 10

    def log_request(self, code='-', size='-'):
        status = getattr(code, 'value', code)
        if self.command == 'GET' and status == 200:
            return  # successful polls and page loads would bury the lines that matter
        log('%s %s -> %s' % (self.command, self.path.split('?')[0][:120], status))

    def log_message(self, format, *args):
        log(format % args)

    def reply(self, status, answer, cookie=None):
        body = json.dumps(answer, ensure_ascii=False, separators=(',', ':')).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        if cookie is not None:
            self.send_header('Set-Cookie', cookie)
        self.end_headers()
        self.wfile.write(body)

    def send_file(self, name):
        body = STATIC_FILES[name]
        self.send_response(200)
        self.send_header('Content-Type', STATIC_TYPES[os.path.splitext(name)[1]])
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        if name.endswith('.html'):
            self.send_header('Content-Security-Policy', content_security_policy())
            self.send_header('Referrer-Policy', 'no-referrer')
        self.end_headers()
        self.wfile.write(body)

    # -- who is asking --

    def signature_problem(self, method, path, body):
        """None when the request is properly signed by WordPress, otherwise the error to answer"""
        stamp = self.headers.get('X-Scouting-Time', '')
        signature = self.headers.get('X-Scouting-Signature', '').lower()
        if not re.fullmatch(r'[0-9]{1,12}', stamp) or not re.fullmatch(r'[0-9a-f]{64}', signature):
            return 'bad_signature'
        message = '%s\n%s\n%s\n%s' % (stamp, method, path, hashlib.sha256(body).hexdigest())
        expected = hmac.new(SECRET, message.encode(), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, signature):
            return 'bad_signature'
        if abs(time.time() - int(stamp)) > SIGNATURE_WINDOW:
            log('refused: the request time is %d seconds off (the caller\'s clock or this one is wrong)'
                % (time.time() - int(stamp)))
            return 'bad_time'
        return None

    def session_value(self):
        """The session value the browser sent: the header first (works everywhere), else the cookie"""
        header = self.headers.get('X-Tagger-Session', '')
        if header:
            return header
        for part in self.headers.get('Cookie', '').split(';'):
            name, _, value = part.strip().partition('=')
            if name == 'tagger_session':
                return value
        return ''

    def person(self, con):
        """The sessions row of whoever is asking, or None"""
        return Sessions.find(con, self.session_value())

    def csrf_ok(self, row):
        given = self.headers.get('X-Tagger-CSRF', '')
        return isinstance(given, str) and hmac.compare_digest(row['csrf'], given) if given else False

    # -- the routes --

    def handle_request(self, method):
        try:
            self.answer(method)
        except Exception:
            log('request problem:\n' + traceback.format_exc())
            try:
                self.reply(500, {'error': 'internal'})
            except Exception:
                pass

    def read_body(self, method, limit=MAX_BODY):
        if method not in ('POST', 'DELETE'):
            return b''
        length = self.headers.get('Content-Length', '')
        if not re.fullmatch(r'[0-9]{1,9}', length):
            raise Refused(411, 'bad_request')
        if int(length) > limit:
            raise Refused(413, 'body_too_large')
        return self.rfile.read(int(length))

    def body_json(self, body):
        try:
            asked = json.loads(body.decode('utf-8'))
        except (UnicodeDecodeError, ValueError):
            raise Refused(400, 'bad_request')
        if not isinstance(asked, dict):
            raise Refused(400, 'bad_request')
        return asked

    def answer(self, method):
        raw = self.path
        path, _, query = raw.partition('?')
        if len(raw) > 2000 or not re.fullmatch(r'/[A-Za-z0-9/_.-]*', path) or '..' in path:
            return self.reply(400, {'error': 'bad_request'})

        # Pages and their files: no login at this level; every piece of data sits behind /v1/
        if method == 'GET' and path in PAGES:
            return self.send_file(PAGES[path])
        found = re.fullmatch(r'/static/([A-Za-z0-9_.-]{1,60})', path)
        if method == 'GET' and found and found.group(1) in STATIC_FILES:
            return self.send_file(found.group(1))
        if method == 'GET' and path == '/v1/health':
            return self.reply(200, {'ok': True})

        try:
            body = self.read_body(method, MAX_CHUNK_BODY if path == '/v1/index/chunk' else MAX_BODY)
        except Refused as refusal:
            return self.reply(refusal.status, {'error': refusal.code})

        con = connect()
        try:
            try:
                return self.routes(method, path, body, con)
            except Refused as refusal:
                return self.reply(refusal.status, {'error': refusal.code})
        finally:
            con.close()

    def routes(self, method, path, body, con):
        # -- signed-in people --
        if method == 'POST' and path == '/v1/session':
            payload = parse_login_token(self.body_json(body).get('token'))
            if payload is None:
                return self.reply(401, {'error': 'bad_link'})
            value = Sessions.create(con, payload['user'], payload['role'], payload['admin'], payload['nonce'])
            row = Sessions.find(con, value)
            log('signed in from WordPress: %s (%s%s)' % (payload['user'], payload['role'],
                                                         ', admin' if payload['admin'] else ''))
            return self.reply(200, Sessions.answer(row, value), cookie=Sessions.cookie(value))

        if method == 'POST' and path == '/v1/login':
            if not ADMIN_KEY:
                return self.reply(403, {'error': 'login_off'})
            if too_many_login_failures(con):
                return self.reply(429, {'error': 'too_many_tries'})
            given = self.body_json(body).get('key')
            if not isinstance(given, str) or not hmac.compare_digest(ADMIN_KEY, given.encode('utf-8')):
                con.execute("INSERT INTO accepted (at, kind) VALUES (?, 'login_fail')", (int(time.time()),))
                time.sleep(1)
                return self.reply(401, {'error': 'wrong_key'})
            value = Sessions.create(con, 'owner', 'administrator', 1, 'passphrase-' + secrets.token_hex(12))
            row = Sessions.find(con, value)
            log('signed in with the admin passphrase')
            return self.reply(200, Sessions.answer(row, value), cookie=Sessions.cookie(value))

        person = self.person(con)
        if person is not None:
            if method == 'GET' and path == '/v1/me':
                merged = read_settings(con)
                return self.reply(200, {
                    'user': person['user'], 'role': person['role'], 'admin': bool(person['is_admin']),
                    'csrf': person['csrf'],
                    'index': index_summary(con),
                    'limits': {'ocr_pages': int(merged['ocr_pages']),
                               'max_ocr_pages': MAX_OCR_PAGES,
                               'ai_calls_per_day': min(int(merged['ai_calls_per_day']), AI_CALLS_PER_DAY),
                               'max_pdf_mb': MAX_PDF_MB},
                    'ocr_ready': OCR_READY,
                    'version': VERSION,
                })
            if method == 'POST' and path == '/v1/logout':
                if not self.csrf_ok(person):
                    return self.reply(403, {'error': 'bad_csrf'})
                Sessions.drop(con, self.session_value())
                return self.reply(200, {'ok': True}, cookie=Sessions.cookie('', clear=True))

        # -- WordPress (signed server-to-server calls) --
        if path.startswith('/v1/index/'):
            problem = self.signature_problem(method, self.path, body)
            if problem:
                return self.reply(401, {'error': problem})
            if method == 'GET' and path == '/v1/index/status':
                return self.reply(200, index_summary(con))
            # begin / chunk / commit / abort arrive in the next step
            return self.reply(404, {'error': 'not_found'})

        if person is None and path.startswith('/v1/'):
            return self.reply(401, {'error': 'no_session'})
        return self.reply(404, {'error': 'not_found'})

    def do_GET(self):
        self.handle_request('GET')

    def do_POST(self):
        self.handle_request('POST')

    def do_DELETE(self):
        self.handle_request('DELETE')


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


# ---- Start ---------------------------------------------------------------------------------

def check_ocr_tools():
    """True when Poppler and Tesseract are here. On the development PC they may not be:
    the app still runs, and uploading a PDF answers ocr_unavailable until they are."""
    for tool in ('pdfinfo', 'pdftoppm', 'tesseract'):
        if shutil.which(tool) is None:
            log('%s is not installed: PDF reading is off, pasted text still works' % tool)
            return False
    try:
        subprocess.run(['tesseract', '--version'], stdin=subprocess.DEVNULL,
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=20)
    except (OSError, subprocess.TimeoutExpired):
        log('tesseract did not answer: PDF reading is off, pasted text still works')
        return False
    return True


def main():
    global OCR_READY
    if len(SECRET) < 32:
        sys.exit('TAGGER_SECRET is missing or shorter than 32 characters. Nothing was started.')
    if ADMIN_KEY and len(ADMIN_KEY) < 12:
        sys.exit('TAGGER_ADMIN_KEY is shorter than 12 characters. Pick a longer passphrase. Nothing was started.')
    if not ADMIN_KEY:
        log('TAGGER_ADMIN_KEY is not set: the /login page is off; people come in through WordPress')
    if INSECURE_COOKIE:
        log('TAGGER_INSECURE_COOKIE=1: cookies without Secure, for local testing only')

    for folder in (STATE_DIR, UPLOAD_DIR, WORK_DIR):
        os.makedirs(folder, exist_ok=True)
    shutil.rmtree(WORK_DIR, ignore_errors=True)  # leftovers of a page that was being read at a restart
    os.makedirs(WORK_DIR, exist_ok=True)

    load_static()
    OCR_READY = check_ocr_tools()
    prepare_database()

    # The service file is installed by hand; say so when the copy in git has moved on
    try:
        with open(os.path.join(OWN_DIR, 'scouting-tagger.service'), 'rb') as mine, \
                open(INSTALLED_UNIT, 'rb') as installed:
            if mine.read().replace(b'\r\n', b'\n') != installed.read().replace(b'\r\n', b'\n'):
                log('NOTE: scouting-tagger.service in git differs from %s. Copy it there and run: systemctl daemon-reload' % INSTALLED_UNIT)
    except OSError:
        pass

    server = Server((LISTEN_HOST, PORT), Handler)
    log('scouting-tagger %s listening on %s:%d (%s; frame ancestors %s)' % (
        VERSION, LISTEN_HOST, PORT,
        'PDF reading ready' if OCR_READY else 'PDF reading OFF',
        FRAME_ANCESTORS or 'none'))
    log('limits: %d OCR pages an hour, %d AI calls a day, PDFs up to %d MB, sessions %d hours' % (
        PAGES_PER_HOUR, AI_CALLS_PER_DAY, MAX_PDF_MB, SESSION_HOURS))
    server.serve_forever()


if __name__ == '__main__':
    main()
