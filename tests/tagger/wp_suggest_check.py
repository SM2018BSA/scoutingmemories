"""Checks of the signed WordPress routes of the AI Tagging Assistant (/v1/wp/suggest and
/v1/wp/jobs/<id>): WordPress hands over text it already holds, the assistant reads no PDF,
and WordPress fetches the answer.

    python tests/tagger/wp_suggest_check.py <empty folder for the app's state>
    python tests/tagger/wp_suggest_check.py <folder> --serve     keep the app running on
        127.0.0.1:8017 afterwards, for tests/tagger/post-suggestions-check.php

Test environment only. The app runs in this process with a stand-in for the AI: no provider
is ever called and no real key exists. Prints ALL WP SUGGEST CHECKS PASSED, or the failures.
"""
import hashlib
import hmac
import importlib.util
import json
import os
import sys
import threading
import time
import urllib.error
import urllib.request

PORT = 8017
SECRET = 'wp-suggest-check-secret-0123456789abcdef'
STAND_IN_ANSWER = {
    'fields': {
        'state': {'value': ['CA'], 'confidence': 0.9, 'evidence': 'Camp Tahquitz, California'},
        'camp': {'value': 'zephyrcrest_lookout__end_date_uncertain__9001', 'confidence': 0.8,
                 'evidence': 'Troop 12 hiked to Zephyrcrest Lookout in 1938.'},
        'start_date': {'value': '1938', 'confidence': 0.7, 'evidence': 'hiked to Zephyrcrest Lookout in 1938'},
        'end_date': {'value': 'not a year', 'confidence': 0.2, 'evidence': ''},
    },
    'summary': 'A troop hike in 1938.',
    'notes_for_reviewer': '',
}

if len(sys.argv) < 2:
    sys.exit(__doc__)
state = os.path.abspath(sys.argv[1])
os.makedirs(state, exist_ok=True)
os.environ.update({
    'TAGGER_SECRET': SECRET, 'TAGGER_PORT': str(PORT), 'TAGGER_LISTEN_HOST': '127.0.0.1',
    'TAGGER_STATE_DIR': os.path.join(state, 'state'), 'TAGGER_CACHE_DIR': os.path.join(state, 'cache'),
    'TAGGER_AI_CALLS_PER_DAY': '3',
})
here = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location('tagger_service', os.path.join(here, '..', '..', 'tagger-service', 'tagger_service.py'))
app = importlib.util.module_from_spec(spec)
spec.loader.exec_module(app)

seen_by_ai = []


def stand_in(provider, model, key, system, user_text, want_json):
    """What the AI would be asked, kept for the checks; the answer is fixed"""
    seen_by_ai.append(user_text)
    return json.dumps(STAND_IN_ANSWER)


app.ai_complete = stand_in
threading.Thread(target=app.main, daemon=True).start()


def call(method, path, body=None, sign=True, stamp=None):
    raw = b'' if body is None else json.dumps(body).encode()
    stamp = str(int(time.time())) if stamp is None else stamp
    message = '%s\n%s\n%s\n%s' % (stamp, method, path, hashlib.sha256(raw).hexdigest())
    headers = {'Content-Type': 'application/json'}
    if sign:
        headers.update({'X-Scouting-Time': stamp,
                        'X-Scouting-Signature': hmac.new(SECRET.encode(), message.encode(), hashlib.sha256).hexdigest()})
    request = urllib.request.Request('http://127.0.0.1:%d%s' % (PORT, path), data=raw or None, headers=headers, method=method)
    try:
        with urllib.request.urlopen(request, timeout=10) as answer:
            return answer.status, json.loads(answer.read() or b'{}')
    except urllib.error.HTTPError as problem:
        return problem.code, json.loads(problem.read() or b'{}')


for _ in range(100):
    try:
        if call('GET', '/v1/health', sign=False)[0] == 200:
            break
    except OSError:
        time.sleep(0.1)
else:
    sys.exit('The app did not start.')

failures = []


def check(label, actual, expected):
    if actual != expected:
        failures.append('FAIL: %s\n  expected: %r\n  actual:   %r' % (label, expected, actual))


def finished(job):
    for _ in range(100):
        status, answer = call('GET', '/v1/wp/jobs/' + job)
        if status != 200 or answer.get('status') in ('done', 'failed'):
            return status, answer
        time.sleep(0.1)
    return status, answer


TEXT = '--- page 1 ---\nTroop 12 hiked to Zephyrcrest Lookout in 1938.'
ask = {'post_id': 42, 'text': TEXT}

check('unsigned: refused', call('POST', '/v1/wp/suggest', ask, sign=False), (401, {'error': 'bad_signature'}))
check('old clock: refused', call('POST', '/v1/wp/suggest', ask, stamp=str(int(time.time()) - 3600)), (401, {'error': 'bad_time'}))
check('no AI key saved: refused before anything is queued', call('POST', '/v1/wp/suggest', ask), (409, {'error': 'no_api_key'}))

con = app.connect()
app.write_setting(con, 'api_key_claude', 'stand-in-key-not-real')
con.commit()

check('no text: refused', call('POST', '/v1/wp/suggest', {'post_id': 42, 'text': '   '}), (400, {'error': 'no_text'}))
check('no post: refused', call('POST', '/v1/wp/suggest', {'post_id': 0, 'text': TEXT}), (400, {'error': 'bad_request'}))
check('post as text: refused', call('POST', '/v1/wp/suggest', {'post_id': '42', 'text': TEXT}), (400, {'error': 'bad_request'}))
check('unknown route: not found', call('GET', '/v1/wp/nothing')[0], 404)

status, queued = call('POST', '/v1/wp/suggest', ask)
check('asked: accepted', (status, queued.get('kind'), queued.get('status') in ('queued', 'working', 'done')), (202, 'suggest', True))
job = queued.get('id', '')
status, answer = finished(job)
check('the answer is ready', (status, answer.get('status')), (200, 'done'))
fields = (answer.get('result') or {}).get('fields') or {}
check('a year is kept', fields.get('start_date', {}).get('value'), '1938')
check('its evidence comes along', fields.get('start_date', {}).get('evidence'), 'hiked to Zephyrcrest Lookout in 1938')
check('what is not a year is dropped', fields.get('end_date', {}).get('value'), None)
check('a camp the index does not hold is not validated', fields.get('camp', {}).get('validated'), False)
check('the AI was given the text WordPress sent', len(seen_by_ai) == 1 and TEXT in seen_by_ai[0], True)
jobs = con.execute("SELECT kind, session, user_login FROM jobs").fetchall()
check('one job, a suggest job, and no PDF was read', [(row['kind'], row['session'], row['user_login']) for row in jobs],
      [('suggest', 'wordpress', 'wordpress')])
check('nothing was fetched or uploaded', (os.listdir(app.UPLOAD_DIR), os.listdir(app.PDF_DIR)), ([], []))

check('unsigned fetch: refused', call('GET', '/v1/wp/jobs/' + job, sign=False), (401, {'error': 'bad_signature'}))
check('a job that does not exist', call('GET', '/v1/wp/jobs/' + '0' * 32), (404, {'error': 'not_found'}))
# A person's own job is not WordPress's to fetch
con.execute("INSERT INTO jobs (id, kind, status, created, session, user_login, filename, page_limit, input) "
            "VALUES (?, 'suggest', 'done', ?, 'someone', 'historian', '', 0, '{}')", ('a' * 32, int(time.time())))
con.commit()
check("a person's job is not handed to WordPress", call('GET', '/v1/wp/jobs/' + 'a' * 32), (404, {'error': 'not_found'}))

# The daily limit counts these calls too (3 a day here; one is used)
for _ in range(2):
    finished(call('POST', '/v1/wp/suggest', ask)[1].get('id', ''))
check('the daily limit holds for WordPress too', call('POST', '/v1/wp/suggest', ask), (429, {'error': 'daily_limit'}))

if failures:
    print('\n'.join(failures))
    print('%d WP SUGGEST CHECK(S) FAILED' % len(failures))
    sys.exit(1)
print('ALL WP SUGGEST CHECKS PASSED')

if '--serve' in sys.argv:
    con.execute('DELETE FROM ai_calls')
    con.commit()
    print('Serving on 127.0.0.1:%d with the stand-in AI. Stop with Ctrl+C.' % PORT, flush=True)
    while True:
        time.sleep(3600)
