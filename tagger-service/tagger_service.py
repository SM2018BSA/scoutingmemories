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
import email
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
import unicodedata
import urllib.error
import urllib.parse
import urllib.request


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

MAX_PIXELS = env_int('TAGGER_MAX_PIXELS', 40000000, 1000000, 200000000)

STATE_DIR = first_dir('STATE_DIRECTORY', 'TAGGER_STATE_DIR') or os.path.abspath('state')
CACHE_DIR = first_dir('CACHE_DIRECTORY', 'TAGGER_CACHE_DIR') or os.path.abspath('cache')
DB_PATH = os.path.join(STATE_DIR, 'tagger.sqlite3')
UPLOAD_DIR = os.path.join(CACHE_DIR, 'uploads')   # PDFs people upload, named <job>.pdf
PDF_DIR = os.path.join(CACHE_DIR, 'pdf')          # PDFs fetched from the storage host
WORK_DIR = os.path.join(CACHE_DIR, 'work')
OWN_DIR = os.path.dirname(os.path.abspath(__file__))

MAX_PDF_BYTES = MAX_PDF_MB * 1024 * 1024
CACHE_BYTES = CACHE_MB * 1024 * 1024
MAX_BODY = 8192             # most requests are a short piece of JSON
MAX_CHUNK_BODY = 512 * 1024  # one batch of index rows pushed by WordPress
MAX_UPLOAD_BODY = MAX_PDF_BYTES + 2 * 1024 * 1024  # the PDF plus the form wrapping
MAX_URL = 1024
MIN_DPI = 72                # a page that would need less than this to fit MAX_PIXELS is refused
SIGNATURE_WINDOW = 300      # seconds a signed request stays valid
TOKEN_LIFE_MAX = 600        # a login link's expiry may be at most this far in the future
LOGIN_FAILS_PER_HOUR = 20
MAX_HANDLERS = 16           # requests answered at the same time
DOWNLOAD_SECONDS = 60
PDFINFO_SECONDS = 20
RENDER_SECONDS = 60
OCR_SECONDS = 120           # per page
MAX_TEXT_TOTAL = 150000     # characters of text kept for one document
INSTALLED_UNIT = '/etc/systemd/system/scouting-tagger.service'
WAKE = threading.Event()    # set when a job is added, so the worker does not wait

# Who may use the app at all; "admin" (the settings dashboard) is a separate flag on the login link
ALLOWED_ROLES = ('administrator', 'manager', 'historian', 'regional', 'index_contributor')

VERSION = '0.4.0'           # step 4: uploaded PDFs are read with Poppler + Tesseract
OCR_READY = False           # set in main(); on this PC the OCR tools may be missing


# ---- The database --------------------------------------------------------------------------

SCHEMA_VERSION = 2

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
-- which states an entity belongs to and which councils a camp/lodge belongs to,
-- unpacked from the JSON columns at import time so the dropdowns are plain joins
CREATE TABLE IF NOT EXISTS entity_states (
    type     TEXT NOT NULL,
    id       INTEGER NOT NULL,
    state_id INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS entity_states_state ON entity_states (state_id, type);
CREATE TABLE IF NOT EXISTS entity_councils (
    type       TEXT NOT NULL,
    id         INTEGER NOT NULL,
    council_id INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS entity_councils_council ON entity_councils (council_id, type);
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
    pages      INTEGER,                   -- pages in the whole document
    pages_total INTEGER,                  -- pages being read (the smaller of pages and page_limit)
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


# ---- The index (the RAG memory's backbone) --------------------------------------------------

def normalize_text(value):
    """Lower-case ASCII words only: what name matching and the token index work on"""
    folded = unicodedata.normalize('NFKD', value).encode('ascii', 'ignore').decode('ascii').lower()
    return re.sub(r'[^a-z0-9]+', ' ', folded).strip()


def tokens_of(value):
    return [piece for piece in normalize_text(value).split() if len(piece) >= 2 or piece.isdigit()]


IMPORT_SOURCES = ('wp', 'csv', 'xlsx')
ENTITY_KINDS = ('state', 'council', 'camp', 'lodge')
LINK_RELATIONS = ('lineage', 'camp_council', 'lodge_council')
CHUNK_ROWS_MAX = 500


def clean_str(value, most):
    return value.strip()[:most] if isinstance(value, str) else ''


def clean_year(value):
    if isinstance(value, str) and value.strip().isdigit():
        value = int(value.strip())
    if isinstance(value, bool) or not isinstance(value, int) or not 1800 <= value <= 2100:
        return None
    return value


def clean_entry_id(value):
    if isinstance(value, str) and value.isdigit():
        value = int(value)
    if isinstance(value, bool) or not isinstance(value, int) or not 1 <= value <= 10 ** 9:
        return None
    return value


def clean_id_list(value):
    if not isinstance(value, list):
        return []
    out = []
    for item in value[:50]:
        entry = clean_entry_id(item)
        if entry is not None and entry not in out:
            out.append(entry)
    return out


def clean_text_list(value, most):
    if not isinstance(value, list):
        return []
    return [clean_str(item, most) for item in value[:20] if clean_str(item, most)]


def clean_row(kind, row):
    """One pushed row in the shape the tables take, or None when it makes no sense"""
    if not isinstance(row, dict):
        return None
    if kind in ENTITY_KINDS:
        entry = clean_entry_id(row.get('id'))
        name = clean_str(row.get('name'), 160)
        if entry is None or not name:
            return None
        status = clean_str(row.get('status'), 20).lower()
        return {
            'id': entry, 'name': name,
            'number': clean_str(row.get('number'), 12),
            'code': clean_str(row.get('code'), 8).upper(),
            'slug': clean_str(row.get('slug'), 160).lower(),
            'states': clean_id_list(row.get('states')),
            'councils': clean_id_list(row.get('councils')),
            'start_year': clean_year(row.get('start')),
            'end_year': clean_year(row.get('end')),
            'status': status,
            'aliases': clean_text_list(row.get('aliases'), 160),
        }
    if kind == 'link':
        parent, child = clean_entry_id(row.get('parent_id')), clean_entry_id(row.get('child_id'))
        if row.get('relation') not in LINK_RELATIONS or parent is None or child is None:
            return None
        return {'relation': row['relation'], 'parent_id': parent, 'child_id': child,
                'role': clean_str(row.get('role'), 20),
                'from_year': clean_year(row.get('from_year')), 'to_year': clean_year(row.get('to_year'))}
    if kind == 'post':
        post = clean_entry_id(row.get('post_id'))
        if post is None:
            return None
        return {'post_id': post,
                'title': clean_str(row.get('title'), 300),
                'category': clean_str(row.get('category'), 40),
                'states': clean_text_list(row.get('states'), 8),
                'council': clean_str(row.get('council'), 160).lower(),
                'camp': clean_str(row.get('camp'), 160).lower(),
                'lodge': clean_str(row.get('lodge'), 160).lower(),
                'start_date': clean_str(row.get('start_date'), 10),
                'end_date': clean_str(row.get('end_date'), 10),
                'publisher': clean_str(row.get('publisher'), 300),
                'subject': clean_str(row.get('subject'), 300),
                'identifier': clean_str(row.get('identifier'), 100)}
    return None


class Importer:
    """The index arrives in signed chunks and replaces the old one in a single step,
    so a push that breaks off half-way changes nothing."""

    @staticmethod
    def begin(con, source, expected):
        con.execute('BEGIN IMMEDIATE')
        try:
            con.execute("UPDATE import_batches SET status = 'aborted' WHERE status = 'open'")
            con.execute("DELETE FROM staging_rows WHERE batch_id NOT IN (SELECT id FROM import_batches WHERE status = 'open')")
            batch = secrets.token_hex(8)
            con.execute("INSERT INTO import_batches (id, source, started, expected, status) VALUES (?, ?, ?, ?, 'open')",
                        (batch, source, int(time.time()), json.dumps(expected)[:2000], ))
            con.execute('COMMIT')
            return batch
        except BaseException:
            try:
                con.execute('ROLLBACK')
            except sqlite3.Error:
                pass
            raise

    @staticmethod
    def open_batch(con, batch):
        if not isinstance(batch, str) or not re.fullmatch(r'[0-9a-f]{16}', batch):
            raise Refused(400, 'unknown_batch')
        row = con.execute("SELECT * FROM import_batches WHERE id = ? AND status = 'open'", (batch,)).fetchone()
        if row is None:
            raise Refused(409, 'unknown_batch')
        return row

    @staticmethod
    def chunk(con, batch, kind, rows):
        Importer.open_batch(con, batch)
        if kind not in ENTITY_KINDS + ('link', 'post'):
            raise Refused(400, 'bad_type')
        if not isinstance(rows, list) or len(rows) > CHUNK_ROWS_MAX:
            raise Refused(400, 'too_many_rows')
        cleaned = []
        for position, row in enumerate(rows):
            good = clean_row(kind, row)
            if good is None:
                log('import: a %s row made no sense (row %d of this chunk)' % (kind, position))
                raise Refused(400, 'bad_row')
            cleaned.append(good)
        start = con.execute('SELECT COALESCE(MAX(seq), 0) FROM staging_rows WHERE batch_id = ?', (batch,)).fetchone()[0]
        con.executemany('INSERT INTO staging_rows (batch_id, type, seq, row) VALUES (?, ?, ?, ?)',
                        [(batch, kind, start + 1 + at, json.dumps(row, ensure_ascii=False, separators=(',', ':')))
                         for at, row in enumerate(cleaned)])
        return len(cleaned)

    @staticmethod
    def commit(con, batch):
        Importer.open_batch(con, batch)
        con.execute('BEGIN IMMEDIATE')
        try:
            staged = {kind: [] for kind in ENTITY_KINDS + ('link', 'post')}
            for row in con.execute('SELECT type, row FROM staging_rows WHERE batch_id = ? ORDER BY seq', (batch,)):
                staged[row['type']].append(json.loads(row['row']))
            if not any(staged[kind] for kind in ENTITY_KINDS):
                raise Refused(400, 'empty_import')

            for table in ('entities', 'links', 'posts', 'name_tokens', 'entity_states', 'entity_councils'):
                con.execute('DELETE FROM %s' % table)
            for kind in ENTITY_KINDS:
                con.executemany(
                    'INSERT OR REPLACE INTO entities (type, id, name, number, code, slug, states, councils, '
                    'start_year, end_year, status, aliases) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(kind, e['id'], e['name'], e['number'], e['code'], e['slug'],
                      json.dumps(e['states']), json.dumps(e['councils']),
                      e['start_year'], e['end_year'], e['status'], json.dumps(e['aliases'], ensure_ascii=False))
                     for e in staged[kind]])
            con.executemany(
                'INSERT OR REPLACE INTO links (relation, parent_id, child_id, role, from_year, to_year) '
                'VALUES (?, ?, ?, ?, ?, ?)',
                [(l['relation'], l['parent_id'], l['child_id'], l['role'], l['from_year'], l['to_year'])
                 for l in staged['link']])
            con.executemany(
                'INSERT OR REPLACE INTO posts (post_id, title, category, states, council, camp, lodge, '
                'start_date, end_date, publisher, subject, identifier) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [(p['post_id'], p['title'], p['category'], json.dumps(p['states']), p['council'], p['camp'],
                  p['lodge'], p['start_date'], p['end_date'], p['publisher'], p['subject'], p['identifier'])
                 for p in staged['post']])

            memberships_s, memberships_c, words = [], [], []
            for kind in ENTITY_KINDS:
                for e in staged[kind]:
                    for state_id in e['states']:
                        memberships_s.append((kind, e['id'], state_id))
                    for council_id in e['councils']:
                        memberships_c.append((kind, e['id'], council_id))
                    seen = set(tokens_of(e['name'])) | {e['number']} - {''}
                    for alias in e['aliases']:
                        seen |= set(tokens_of(alias))
                    words += [(token, kind, e['id']) for token in seen]
            for l in staged['link']:
                if l['relation'] == 'camp_council':
                    memberships_c.append(('camp', l['parent_id'], l['child_id']))
                elif l['relation'] == 'lodge_council':
                    memberships_c.append(('lodge', l['parent_id'], l['child_id']))
            con.executemany('INSERT INTO entity_states (type, id, state_id) VALUES (?, ?, ?)', set(memberships_s))
            con.executemany('INSERT INTO entity_councils (type, id, council_id) VALUES (?, ?, ?)', set(memberships_c))
            con.executemany('INSERT INTO name_tokens (token, type, id) VALUES (?, ?, ?)', set(words))

            counts = {'states': len(staged['state']), 'councils': len(staged['council']),
                      'camps': len(staged['camp']), 'lodges': len(staged['lodge']),
                      'links': len(staged['link']), 'posts': len(staged['post'])}
            source = con.execute('SELECT source FROM import_batches WHERE id = ?', (batch,)).fetchone()['source']
            write_setting(con, 'index_received_at', str(int(time.time())))
            write_setting(con, 'index_counts', json.dumps(counts))
            write_setting(con, 'index_source', source)
            con.execute("UPDATE import_batches SET status = 'committed' WHERE id = ?", (batch,))
            con.execute('DELETE FROM staging_rows WHERE batch_id = ?', (batch,))
            con.execute('COMMIT')
            log('index received (%s): %d councils, %d camps, %d lodges, %d states, %d links, %d posts' % (
                source, counts['councils'], counts['camps'], counts['lodges'], counts['states'],
                counts['links'], counts['posts']))
            return counts
        except BaseException:
            try:
                con.execute('ROLLBACK')
            except sqlite3.Error:
                pass
            raise

    @staticmethod
    def abort(con, batch):
        Importer.open_batch(con, batch)
        con.execute("UPDATE import_batches SET status = 'aborted' WHERE id = ?", (batch,))
        con.execute('DELETE FROM staging_rows WHERE batch_id = ?', (batch,))


TAXONOMY_ROW = 'SELECT id, name, number, code, slug, start_year, end_year, status FROM entities'


def taxonomy_rows(con, kind, state_id=None, council_id=None):
    """What a dropdown shows: the entities of one kind, narrowed by state or council"""
    sql, values = TAXONOMY_ROW + ' WHERE type = ?', [kind]
    if state_id is not None:
        sql += ' AND id IN (SELECT id FROM entity_states WHERE type = ? AND state_id = ?)'
        values += [kind, state_id]
    if council_id is not None:
        sql += ' AND id IN (SELECT id FROM entity_councils WHERE type = ? AND council_id = ?)'
        values += [kind, council_id]
    sql += ' ORDER BY name, number LIMIT 4000'
    return [{'id': row['id'], 'name': row['name'], 'number': row['number'], 'code': row['code'],
             'slug': row['slug'], 'start_year': row['start_year'], 'end_year': row['end_year'],
             'status': row['status']} for row in con.execute(sql, values)]


# ---- Reading PDFs (the same tools and rules as the OCR app) ----------------------------------

class OcrError(Exception):
    """A document could not be read; the code is what the caller is told"""


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None  # a redirect becomes an error: the download never leaves the storage host


OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect)
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


def cache_path(url):
    return os.path.join(PDF_DIR, hashlib.sha256(url.encode()).hexdigest() + '.pdf')


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

    request = urllib.request.Request(url, headers={'User-Agent': 'ScoutingMemoriesTagger/1.0', 'Accept': 'application/pdf'})
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
    trim_pdf_folders(keep=path)
    return path


def trim_pdf_folders(keep=None):
    """Delete PDFs kept longer than TAGGER_KEEP_DAYS, then the oldest while over the size limit"""
    too_old = time.time() - KEEP_DAYS * 86400
    files = []
    for folder in (PDF_DIR, UPLOAD_DIR):
        for name in os.listdir(folder):
            full = os.path.join(folder, name)
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


def pdf_pages(pdf):
    """How many pages the document has"""
    info = run(['pdfinfo', pdf], PDFINFO_SECONDS, 'not_pdf')
    counts = re.findall(r'^Pages:\s+(\d+)\s*$', info, re.M)  # the last match: document details print first
    if not counts or int(counts[-1]) < 1:
        raise OcrError('not_pdf')
    return int(counts[-1])


def page_dpi(pdf, page):
    """The resolution to render this page at (an oversized page gets a lower one)"""
    info = run(['pdfinfo', '-f', str(page), '-l', str(page), pdf], PDFINFO_SECONDS, 'not_pdf')
    sizes = PAGE_SIZE.findall(info)
    if not sizes:
        raise OcrError('render_failed')
    square_inches = (float(sizes[-1][0]) / 72.0) * (float(sizes[-1][1]) / 72.0)
    if not square_inches > 0:
        raise OcrError('render_failed')
    dpi = DPI
    if square_inches * dpi * dpi > MAX_PIXELS:
        dpi = int(math.sqrt(MAX_PIXELS / square_inches))
    if dpi < MIN_DPI:
        raise OcrError('page_too_large')
    return dpi


def read_one_page(work, pdf, page):
    """One page's text, through Poppler and Tesseract exactly like the OCR app"""
    dpi = page_dpi(pdf, page)
    # -cropbox: the same part of the page that pdfinfo measured and that PDF viewers show
    run(['pdftoppm', '-f', str(page), '-l', str(page), '-r', str(dpi), '-cropbox', '-gray', '-png', '-singlefile',
         pdf, os.path.join(work, 'page')], RENDER_SECONDS, 'render_failed')
    picture = os.path.join(work, 'page.png')
    if not os.path.isfile(picture):
        raise OcrError('render_failed')
    environment = dict(os.environ, OMP_THREAD_LIMIT='1')  # one page at a time, one processor thread
    # --psm 1: find the layout and which way up the page is
    run(['tesseract', picture, os.path.join(work, 'out'), '-l', 'eng', '--psm', '1', '--dpi', str(dpi), 'txt'],
        OCR_SECONDS, 'ocr_failed', env=environment)
    try:
        with open(os.path.join(work, 'out.txt'), 'r', encoding='utf-8', errors='replace') as handle:
            return handle.read().replace('\f', '').strip()
    except FileNotFoundError:
        raise OcrError('ocr_failed')


# ---- Jobs (OCR now; the AI jobs join in the next step) ---------------------------------------

def queue_job(con, person, kind, filename, page_limit, input_values, pdf_bytes=None):
    """A new job for this person, or a refusal. An uploaded PDF is written before the job
    becomes visible, so the worker never sees a job whose file is still arriving."""
    now = int(time.time())
    con.execute('BEGIN IMMEDIATE')
    try:
        if PAUSED:
            raise Refused(503, 'paused')
        waiting = con.execute("SELECT COUNT(*) FROM jobs WHERE status IN ('queued', 'working')").fetchone()[0]
        if waiting >= QUEUE_MAX:
            raise Refused(429, 'queue_full')
        if kind == 'ocr':
            recent = con.execute("SELECT COUNT(*) FROM accepted WHERE kind = 'page' AND at > ?", (now - 3600,)).fetchone()[0]
            if recent + page_limit > PAGES_PER_HOUR:
                raise Refused(429, 'hourly_limit')
            con.executemany("INSERT INTO accepted (at, kind) VALUES (?, 'page')", [(now,)] * page_limit)
        job_id = secrets.token_hex(16)
        if pdf_bytes is not None:
            with open(os.path.join(UPLOAD_DIR, job_id + '.pdf'), 'wb') as out:
                out.write(pdf_bytes)
        con.execute('INSERT INTO jobs (id, kind, status, created, session, user_login, filename, page_limit, input) '
                    "VALUES (?, ?, 'queued', ?, ?, ?, ?, ?, ?)",
                    (job_id, kind, now, person['sid'], person['user'], filename, page_limit,
                     json.dumps(input_values, ensure_ascii=False)))
        con.execute('COMMIT')
    except BaseException:
        try:
            con.execute('ROLLBACK')
        except sqlite3.Error:
            pass
        raise
    WAKE.set()
    return job_id


def job_answer(con, row):
    """What the owner of a job is told about it"""
    answer = {'id': row['id'], 'kind': row['kind'], 'status': row['status']}
    if row['status'] == 'queued':
        ahead = con.execute(
            "SELECT COUNT(*) FROM jobs WHERE status IN ('queued', 'working') AND (created < ? OR (created = ? AND id < ?))",
            (row['created'], row['created'], row['id'])).fetchone()[0]
        answer['position'] = ahead + 1
    elif row['status'] == 'working':
        answer.update({'pages_done': row['pages_done'], 'pages_total': row['pages_total']})
    elif row['status'] == 'done':
        if row['kind'] == 'ocr':
            answer.update({'text': row['text'] or '', 'pages_done': row['pages_done'],
                           'pages_total': row['pages_total'], 'filename': row['filename']})
        else:
            answer['result'] = json.loads(row['result'] or '{}')
    elif row['status'] == 'failed':
        answer['error'] = row['error'] or 'internal'
    return answer


def do_ocr_job(con, row):
    """Read the first pages of the job's PDF; the text lands on the job row"""
    values = json.loads(row['input'] or '{}')
    if values.get('url'):
        pdf = fetch_pdf(values['url'])
    else:
        pdf = os.path.join(UPLOAD_DIR, row['id'] + '.pdf')
        if not os.path.isfile(pdf):
            raise OcrError('internal')
    pages = pdf_pages(pdf)
    reading = min(pages, row['page_limit'] or 1)
    con.execute('UPDATE jobs SET pages_total = ?, pages = ? WHERE id = ?', (reading, pages, row['id']))
    work = os.path.join(WORK_DIR, row['id'])
    pieces = []
    try:
        for page in range(1, reading + 1):
            shutil.rmtree(work, ignore_errors=True)
            os.makedirs(work)
            text = read_one_page(work, pdf, page)
            pieces.append('--- page %d ---\n%s' % (page, text))
            con.execute('UPDATE jobs SET pages_done = ? WHERE id = ?', (page, row['id']))
            if sum(len(piece) for piece in pieces) > MAX_TEXT_TOTAL:
                break
    finally:
        shutil.rmtree(work, ignore_errors=True)
    return '\n\n'.join(pieces)[:MAX_TEXT_TOTAL]


def worker(kinds, handler):
    """Reads the waiting jobs of these kinds one at a time, oldest first"""
    con = connect(wait=10)
    marks = ','.join('?' * len(kinds))
    tidied = 0.0
    while True:
        try:
            now = int(time.time())
            if time.time() - tidied > 3600:
                for old in con.execute("SELECT id FROM jobs WHERE status IN ('done', 'failed') AND finished < ?",
                                       (now - KEEP_DAYS * 86400,)):
                    try:
                        os.remove(os.path.join(UPLOAD_DIR, old['id'] + '.pdf'))
                    except OSError:
                        pass
                con.execute("DELETE FROM jobs WHERE status IN ('done', 'failed') AND finished < ?", (now - KEEP_DAYS * 86400,))
                con.execute('DELETE FROM accepted WHERE at < ?', (now - 7200,))
                con.execute('DELETE FROM sessions WHERE expires < ?', (now,))
                trim_pdf_folders()
                tidied = time.time()
            row = None if PAUSED else con.execute(
                'SELECT * FROM jobs WHERE status = %r AND kind IN (%s) ORDER BY created, id LIMIT 1'
                % ('queued', marks), kinds).fetchone()
            if row is None:
                WAKE.wait(5)
                WAKE.clear()
                continue
            con.execute("UPDATE jobs SET status = 'working', started = ? WHERE id = ?", (now, row['id']))
            began = time.monotonic()
            try:
                outcome = handler(con, row)
                column = 'text' if row['kind'] == 'ocr' else 'result'
                con.execute('UPDATE jobs SET status = %r, finished = ?, %s = ?, error = NULL WHERE id = ?'
                            % ('done', column), (int(time.time()), outcome, row['id']))
                log('done: %s %s (%s) in %.1f s' % (row['kind'], row['id'][:8], row['filename'] or 'text',
                                                    time.monotonic() - began))
            except (OcrError, Refused) as problem:
                code = problem.code if isinstance(problem, Refused) else str(problem)
                con.execute("UPDATE jobs SET status = 'failed', finished = ?, error = ? WHERE id = ?",
                            (int(time.time()), code, row['id']))
                log('failed: %s %s: %s' % (row['kind'], row['id'][:8], code))
            except Exception:
                log('failed: %s %s: internal\n%s' % (row['kind'], row['id'][:8], traceback.format_exc()))
                con.execute("UPDATE jobs SET status = 'failed', finished = ?, error = 'internal' WHERE id = ?",
                            (int(time.time()), row['id']))
        except Exception:
            log('worker problem:\n' + traceback.format_exc())
            time.sleep(5)


def page_limit_value(value, fallback):
    """How many pages to read: what was asked, within 1..TAGGER_MAX_OCR_PAGES, else the setting"""
    if isinstance(value, str) and value.isdigit():
        value = int(value)
    if isinstance(value, bool) or not isinstance(value, int) or not 1 <= value <= MAX_OCR_PAGES:
        return fallback
    return value


def parse_upload(content_type, body):
    """The uploaded file and the small text fields of a multipart form"""
    if not isinstance(content_type, str) or not content_type.lower().startswith('multipart/form-data'):
        raise Refused(400, 'bad_request')
    try:
        message = email.message_from_bytes(
            b'Content-Type: ' + content_type.encode('latin-1') + b'\r\nMIME-Version: 1.0\r\n\r\n' + body)
    except (UnicodeError, ValueError):
        raise Refused(400, 'bad_request')
    if not message.is_multipart():
        raise Refused(400, 'bad_request')
    fields, file_name, file_bytes = {}, '', None
    for part in message.walk():
        if part.is_multipart():
            continue
        name = part.get_param('name', header='content-disposition')
        if not isinstance(name, str):
            continue
        payload = part.get_payload(decode=True) or b''
        if part.get_filename():
            if name == 'file' and file_bytes is None:
                file_name, file_bytes = str(part.get_filename())[:80], payload
        elif len(payload) <= 1000:
            fields[name] = payload.decode('utf-8', 'replace').strip()
    return fields, file_name, file_bytes


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

        limit = MAX_BODY
        if path == '/v1/index/chunk':
            limit = MAX_CHUNK_BODY
        elif path == '/v1/upload':
            limit = MAX_UPLOAD_BODY
        try:
            body = self.read_body(method, limit)
        except Refused as refusal:
            return self.reply(refusal.status, {'error': refusal.code})

        con = connect()
        try:
            try:
                return self.routes(method, path, query, body, con)
            except Refused as refusal:
                return self.reply(refusal.status, {'error': refusal.code})
        finally:
            con.close()

    @staticmethod
    def query_value(query, name, most=200):
        for part in query.split('&'):
            key, _, value = part.partition('=')
            if key == name:
                return urllib.parse.unquote_plus(value)[:most]
        return None

    def routes(self, method, path, query, body, con):
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

            if method == 'POST' and path == '/v1/upload':
                if not self.csrf_ok(person):
                    return self.reply(403, {'error': 'bad_csrf'})
                if not OCR_READY:
                    return self.reply(503, {'error': 'ocr_unavailable'})
                fallback = min(int(read_settings(con)['ocr_pages']), MAX_OCR_PAGES)
                content_type = self.headers.get('Content-Type', '')
                if content_type.lower().startswith('multipart/'):
                    fields, name, data = parse_upload(content_type, body)
                    if not data:
                        return self.reply(400, {'error': 'bad_request'})
                    if len(data) > MAX_PDF_BYTES:
                        return self.reply(413, {'error': 'too_large'})
                    if b'%PDF-' not in data[:1024]:
                        return self.reply(400, {'error': 'not_pdf'})
                    pages = page_limit_value(fields.get('page_limit'), fallback)
                    job = queue_job(con, person, 'ocr', name, pages, {}, pdf_bytes=data)
                else:
                    asked = self.body_json(body)
                    url = clean_url(asked.get('url'))
                    if url is None:
                        return self.reply(403, {'error': 'host_not_allowed'})
                    pages = page_limit_value(asked.get('page_limit'), fallback)
                    name = urllib.parse.unquote(url.rsplit('/', 1)[-1])[:80]
                    job = queue_job(con, person, 'ocr', name, pages, {'url': url})
                row = con.execute('SELECT * FROM jobs WHERE id = ?', (job,)).fetchone()
                return self.reply(202, job_answer(con, row))

            found = re.fullmatch(r'/v1/jobs/([0-9a-f]{32})', path)
            if method == 'GET' and found:
                row = con.execute('SELECT * FROM jobs WHERE id = ?', (found.group(1),)).fetchone()
                if row is None or (row['session'] != person['sid'] and not person['is_admin']):
                    return self.reply(404, {'error': 'not_found'})
                return self.reply(200, job_answer(con, row))

        if person is not None and method == 'GET' and path == '/v1/taxonomy':
            kind = self.query_value(query, 'type')
            if kind not in ENTITY_KINDS:
                return self.reply(400, {'error': 'bad_type'})
            state = clean_entry_id(self.query_value(query, 'state'))
            council = clean_entry_id(self.query_value(query, 'council'))
            return self.reply(200, {'entities': taxonomy_rows(con, kind, state, council)})

        # -- WordPress (signed server-to-server calls) --
        if path.startswith('/v1/index/'):
            problem = self.signature_problem(method, self.path, body)
            if problem:
                return self.reply(401, {'error': problem})
            if method == 'GET' and path == '/v1/index/status':
                return self.reply(200, index_summary(con))
            if method == 'POST' and path == '/v1/index/begin':
                asked = self.body_json(body)
                if asked.get('source') not in IMPORT_SOURCES:
                    return self.reply(400, {'error': 'bad_request'})
                expected = asked.get('counts') if isinstance(asked.get('counts'), dict) else {}
                return self.reply(200, {'batch': Importer.begin(con, asked['source'], expected)})
            if method == 'POST' and path == '/v1/index/chunk':
                asked = self.body_json(body)
                received = Importer.chunk(con, asked.get('batch'), asked.get('type'), asked.get('rows'))
                return self.reply(200, {'received': received})
            if method == 'POST' and path == '/v1/index/commit':
                return self.reply(200, {'counts': Importer.commit(con, self.body_json(body).get('batch'))})
            if method == 'POST' and path == '/v1/index/abort':
                Importer.abort(con, self.body_json(body).get('batch'))
                return self.reply(200, {'ok': True})
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

    for folder in (STATE_DIR, UPLOAD_DIR, PDF_DIR, WORK_DIR):
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

    threading.Thread(target=worker, args=(('ocr',), do_ocr_job), name='ocr-worker', daemon=True).start()
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
