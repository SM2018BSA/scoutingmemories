"""Checks of the taxonomy ideas: the ideas part of a document's suggestions, and the ideas for
the whole archive (/v1/advise).

    python tests/tagger/taxonomy_ideas_check.py <empty folder for the app's state>

Test environment only. The app runs in this process on a small made-up index, with a
stand-in for the AI: no provider is ever called and no real key exists. The stand-in answers
with some junk on purpose, to check that the app cleans it up. Prints ALL TAXONOMY IDEAS
CHECKS PASSED, or the failures.
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

PORT = 8018
SECRET = 'taxonomy-ideas-check-secret-0123456789abcdef'
PASSPHRASE = 'taxonomy-ideas-passphrase'

DOCUMENT_ANSWER = {
    'fields': {'camp': {'value': 'camp_russell_385', 'confidence': 0.9, 'evidence': 'Camp Russell'}},
    'taxonomy_ideas': {
        'subject_tags': ['Camping', 'camping', 'Youth organizations', '', 7, 'Waterfront'],
        'people': ['Chuck Champe (camp director)'],
        'events': ['1952 summer camp season'],
        'not_in_index': [
            {'type': 'camp', 'name': 'Camp Woodland', 'evidence': 'a visit to Camp Woodland'},
            {'type': 'camp', 'name': 'camp russell', 'evidence': 'Camp Russell'},
            {'type': 'troop', 'name': 'Troop 12', 'evidence': 'Troop 12'},
            {'type': 'lodge', 'name': ''},
        ],
        'extra_tags': [
            {'name': 'Volume and issue', 'value': 'Vol. 3, No. 2', 'why': 'on the masthead'},
            {'name': 'Language', 'value': ''},
        ],
        'filing_notes': 'History: a camp newsletter.',
    },
    'summary': 'A 1952 camp newsletter.',
}
ARCHIVE_ANSWER = {
    'summary': 'Subjects are spelled many ways.',
    'subject_vocabulary': [
        {'term': 'Youth organizations', 'covers': 'Scouting itself', 'replaces': ['Youth organizatinons', 'Youth Organization']},
        {'term': '', 'covers': 'nothing'},
    ],
    'fields': [
        {'field': 'meta_subject', 'advice': 'change', 'why': '3 spellings', 'how': 'merge'},
        {'field': 'identifier', 'advice': 'remove', 'why': 'not a known advice word'},
    ],
    'recommendations': [
        {'title': 'Merge the spellings', 'why': 'the numbers', 'steps': ['List them', 'Merge them'], 'needs_decision': True},
        {'why': 'no title'},
    ],
}

if len(sys.argv) < 2:
    sys.exit(__doc__)
state = os.path.abspath(sys.argv[1])
os.makedirs(state, exist_ok=True)
os.environ.update({
    'TAGGER_SECRET': SECRET, 'TAGGER_ADMIN_KEY': PASSPHRASE, 'TAGGER_PORT': str(PORT),
    'TAGGER_LISTEN_HOST': '127.0.0.1', 'TAGGER_INSECURE_COOKIE': '1',
    'TAGGER_STATE_DIR': os.path.join(state, 'state'), 'TAGGER_CACHE_DIR': os.path.join(state, 'cache'),
})
here = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location('tagger_service', os.path.join(here, '..', '..', 'tagger-service', 'tagger_service.py'))
app = importlib.util.module_from_spec(spec)
spec.loader.exec_module(app)

seen_by_ai = []


def stand_in(provider, model, key, system, user_text, want_json):
    """What the AI would be asked, kept for the checks; the answer depends on the question"""
    seen_by_ai.append(user_text)
    return json.dumps(ARCHIVE_ANSWER if '## The archive today' in user_text else DOCUMENT_ANSWER)


app.ai_complete = stand_in
threading.Thread(target=app.main, daemon=True).start()


def call(method, path, body=None, headers=None, sign=False):
    raw = b'' if body is None else json.dumps(body).encode()
    send = {'Content-Type': 'application/json'}
    send.update(headers or {})
    if sign:
        stamp = str(int(time.time()))
        message = '%s\n%s\n%s\n%s' % (stamp, method, path, hashlib.sha256(raw).hexdigest())
        send.update({'X-Scouting-Time': stamp,
                     'X-Scouting-Signature': hmac.new(SECRET.encode(), message.encode(), hashlib.sha256).hexdigest()})
    request = urllib.request.Request('http://127.0.0.1:%d%s' % (PORT, path), data=raw or None, headers=send, method=method)
    try:
        with urllib.request.urlopen(request, timeout=10) as answer:
            return answer.status, json.loads(answer.read() or b'{}')
    except urllib.error.HTTPError as problem:
        return problem.code, json.loads(problem.read() or b'{}')


for _ in range(100):
    try:
        if call('GET', '/v1/health')[0] == 200:
            break
    except OSError:
        time.sleep(0.1)
else:
    sys.exit('The app did not start.')

failures = []


def check(label, actual, expected):
    if actual != expected:
        failures.append('FAIL: %s\n  expected: %r\n  actual:   %r' % (label, expected, actual))


def finished(job, auth):
    for _ in range(100):
        status, answer = call('GET', '/v1/jobs/' + job, headers=auth)
        if status != 200 or answer.get('status') in ('done', 'failed'):
            return status, answer
        time.sleep(0.1)
    return status, answer


status, login = call('POST', '/v1/login', {'key': PASSPHRASE})
auth = {'X-Tagger-Session': login['sid'], 'X-Tagger-CSRF': login['csrf']}

check('advise without a key: refused', call('POST', '/v1/advise', {}, auth), (409, {'error': 'no_api_key'}))
call('POST', '/v1/admin/settings', {'api_key_claude': 'stand-in-key-not-real'}, auth)
check('advise before the index arrived: refused', call('POST', '/v1/advise', {}, auth), (409, {'error': 'no_index'}))
check('advise without the CSRF token: refused',
      call('POST', '/v1/advise', {}, {'X-Tagger-Session': login['sid']}), (403, {'error': 'bad_csrf'}))

# A small index, sent the way WordPress sends it
status, begun = call('POST', '/v1/index/begin', {'source': 'wp', 'counts': {}}, sign=True)
batch = begun['batch']
rows = {
    'state': [{'id': 1, 'name': 'New York', 'code': 'NY'}],
    'council': [{'id': 378, 'name': 'General Herkimer Council', 'number': '400', 'states': [1], 'slug': 'general_herkimer_400'}],
    'camp': [{'id': 385, 'name': 'Camp Russell', 'states': [1], 'councils': [378], 'slug': 'camp_russell_385'}],
    'post': [
        {'post_id': 1, 'title': 'Camp Russell 1952', 'category': 'History', 'states': ['NY'],
         'camp': 'camp_russell_385', 'subject': 'Camping; Youth organizations; Leadership'},
        {'post_id': 2, 'title': 'Patches', 'category': 'Memorabilia', 'subject': 'Camping; Uniforms'},
        {'post_id': 3, 'title': 'Roster', 'category': 'History', 'subject': 'Youth organizatinons'},
    ],
}
for kind, chunk in rows.items():
    check('index chunk %s' % kind, call('POST', '/v1/index/chunk', {'batch': batch, 'type': kind, 'rows': chunk}, sign=True)[0], 200)
check('index commit', call('POST', '/v1/index/commit', {'batch': batch}, sign=True)[0], 200)

# The document: its ideas come back cleaned
status, started = call('POST', '/v1/suggest', {'text': 'The Camp Russell Courier, 1952. A visit to Camp Woodland.'}, auth)
check('suggest queued', status, 202)
status, done = finished(started['id'], auth)
ideas = done.get('result', {}).get('taxonomy_ideas', {})
check('subject headings: duplicates and junk dropped', ideas.get('subject_tags'), ['Camping', 'Youth organizations', 'Waterfront'])
check('people kept', ideas.get('people'), ['Chuck Champe (camp director)'])
check('events kept', ideas.get('events'), ['1952 summer camp season'])
check('not in the index: wrong type and empty name dropped', [item['name'] for item in ideas.get('not_in_index', [])],
      ['Camp Woodland', 'camp russell'])
check('a truly new camp has no index match', 'in_index' in ideas.get('not_in_index', [{}])[0], False)
check('a camp the index does have is pointed out',
      ideas.get('not_in_index', [{}, {}])[1].get('in_index'),
      {'name': 'Camp Russell', 'number': '', 'slug': 'camp_russell_385', 'count': 1})
check('extra tags: one without a value dropped', ideas.get('extra_tags'),
      [{'name': 'Volume and issue', 'value': 'Vol. 3, No. 2', 'why': 'on the masthead'}])
check('filing notes kept', ideas.get('filing_notes'), 'History: a camp newsletter.')
document_prompt = next((text for text in seen_by_ai if '## Document text' in text), '')
check('the AI saw the site\'s headings, counted one by one',
      '- Camping (2)' in document_prompt and '- Youth organizations (1)' in document_prompt, True)

# The whole archive: the AI gets the numbers, the answer comes back cleaned
status, started = call('POST', '/v1/advise', {'focus': 'subject spellings'}, auth)
check('advise queued', status, 202)
status, done = finished(started['id'], auth)
result = done.get('result', {})
archive_prompt = next((text for text in seen_by_ai if '## The archive today' in text), '')
check('the AI saw how many documents there are', 'Tagged documents: 3' in archive_prompt, True)
check('the AI saw the misspelled heading', '- Youth organizatinons (1)' in archive_prompt, True)
check('the AI saw how full each field is', '- Camp: 1 of 3 (33%)' in archive_prompt, True)
check('the AI saw the focus', 'subject spellings' in archive_prompt, True)
check('vocabulary: the entry without a term dropped', [item['term'] for item in result.get('subject_vocabulary', [])],
      ['Youth organizations'])
check('fields: unknown advice word dropped', [item['field'] for item in result.get('fields', [])], ['meta_subject'])
check('recommendations: the one without a title dropped', [item['title'] for item in result.get('recommendations', [])],
      ['Merge the spellings'])
check('needs-decision flag kept', result.get('recommendations', [{}])[0].get('needs_decision'), True)

if failures:
    print('\n'.join(failures))
    sys.exit(1)
print('ALL TAXONOMY IDEAS CHECKS PASSED')
