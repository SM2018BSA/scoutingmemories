// AI Tagging Assistant front end. Talks JSON to the service; Bootstrap does the widgets.
// One session value identifies the visitor: it arrives as a cookie AND is kept in
// sessionStorage, because some browsers drop cookies inside a cross-site iframe.
'use strict';

const Session = {
  csrf: '',
  get value() { try { return sessionStorage.getItem('tagger_sid') || ''; } catch (e) { return ''; } },
  set value(v) { try { v ? sessionStorage.setItem('tagger_sid', v) : sessionStorage.removeItem('tagger_sid'); } catch (e) {} },

  async api(path, options) {
    const given = Object.assign({ method: 'GET', headers: {} }, options || {});
    if (this.value) given.headers['X-Tagger-Session'] = this.value;
    if (this.csrf && given.method !== 'GET') given.headers['X-Tagger-CSRF'] = this.csrf;
    if (given.json !== undefined) {
      given.body = JSON.stringify(given.json);
      given.headers['Content-Type'] = 'application/json';
      delete given.json;
    }
    const response = await fetch(path, given);
    let answer = null;
    try { answer = await response.json(); } catch (e) { /* the proxy answered, not the app */ }
    return { status: response.status, answer: answer || {} };
  },

  // The one-time ?token=... link from WordPress, removed from the address bar once read
  takeToken() {
    const search = new URLSearchParams(location.search);
    const token = search.get('token');
    if (token !== null) {
      search.delete('token');
      const clean = location.pathname + (search.toString() ? '?' + search : '');
      history.replaceState(null, '', clean);
    }
    return token;
  },

  async signIn() {
    // A fresh link in the address outranks whatever session this tab already had
    // (someone may have been handed an administrator link mid-visit).
    const token = this.takeToken();
    if (token) {
      const started = await this.api('/v1/session', { method: 'POST', json: { token: token } });
      if (started.status === 200) {
        this.value = started.answer.sid;
        this.csrf = started.answer.csrf;
      }
    }
    // Without a stored session the server may still know who this is: the session cookie,
    // or the development server's password gate, which hands out a session here.
    const me = await this.api('/v1/me');
    if (me.status === 200) {
      if (me.answer.sid) this.value = me.answer.sid;
      this.csrf = me.answer.csrf;
      return me.answer;
    }
    this.value = '';
    return null;
  },
};

// The State -> Council -> Camp/Lodge dropdowns, fed by the index WordPress pushed.
const Taxonomy = {
  pick(name) { return document.getElementById('pick-' + name); },

  label(kind, entity) {
    if (kind === 'state') return entity.name + (entity.code ? ' (' + entity.code + ')' : '');
    let text = entity.name + (entity.number ? ' (#' + entity.number + ')' : '');
    if (entity.start_year) {
      text += ', ' + entity.start_year + '–' + (entity.status === 'active' ? 'present' : (entity.end_year || '?'));
    }
    return text;
  },

  fill(kind, entities, emptyText) {
    const select = this.pick(kind === 'state' ? 'state' : kind);
    select.textContent = '';
    const first = document.createElement('option');
    first.value = '';
    first.textContent = entities.length ? '— choose —' : (emptyText || '— none —');
    select.appendChild(first);
    for (const entity of entities) {
      const option = document.createElement('option');
      option.value = entity.id;
      option.dataset.slug = entity.slug || '';
      option.textContent = this.label(kind, entity);
      select.appendChild(option);
    }
    select.disabled = entities.length === 0;
  },

  async load(kind, params) {
    const search = new URLSearchParams(Object.assign({ type: kind }, params || {}));
    const result = await Session.api('/v1/taxonomy?' + search);
    return result.status === 200 ? result.answer.entities : [];
  },

  async start() {
    this.fill('state', await this.load('state'));
    this.fill('council', [], '— pick a state first —');
    this.fill('camp', [], '— pick a council first —');
    this.fill('lodge', [], '— pick a council first —');
    this.pick('state').addEventListener('change', () => this.stateChanged());
    this.pick('council').addEventListener('change', () => this.councilChanged());
  },

  async stateChanged() {
    const state = this.pick('state').value;
    this.fill('council', state ? await this.load('council', { state: state }) : [],
      '— pick a state first —');
    this.fill('camp', [], '— pick a council first —');
    this.fill('lodge', [], '— pick a council first —');
  },

  async councilChanged() {
    const council = this.pick('council').value;
    const [camps, lodges] = council
      ? await Promise.all([this.load('camp', { council: council }), this.load('lodge', { council: council })])
      : [[], []];
    this.fill('camp', camps, council ? '— none recorded for this council —' : '— pick a council first —');
    this.fill('lodge', lodges, council ? '— none recorded for this council —' : '— pick a council first —');
  },

  // What the historian picked, for the suggestion request (used from step 5 on)
  picked() {
    const chosen = {};
    for (const kind of ['state', 'council', 'camp', 'lodge']) {
      const select = this.pick(kind);
      const option = select.selectedOptions[0];
      if (select.value && option) chosen[kind] = { id: select.value, slug: option.dataset.slug || '' };
    }
    return chosen;
  },
};

// The Document card: a PDF goes to the service and comes back as text; pasting works too.
const Doc = {
  node(id) { return document.getElementById(id); },

  REASONS: {
    ocr_unavailable: 'PDF reading is switched off on this server.',
    too_large: 'That PDF is bigger than the app accepts.',
    not_pdf: 'That file does not look like a PDF.',
    host_not_allowed: 'Only links on storage.scoutingmemories.org work here.',
    hourly_limit: 'The hourly page limit is used up. Try again in an hour.',
    queue_full: 'Too many documents are waiting. Try again in a few minutes.',
    paused: 'The app is paused.',
    download_failed: 'The PDF could not be fetched from the storage site.',
    render_failed: 'That page could not be turned into a picture.',
    ocr_failed: 'The reading tool failed on that page.',
    page_too_large: 'That page is too large to read.',
    timeout: 'Reading took too long and was stopped.',
    internal: 'Something went wrong on the server.',
  },

  init(me) {
    const pages = this.node('doc-pages');
    pages.textContent = '';
    for (let n = 1; n <= me.limits.max_ocr_pages; n += 1) {
      const option = document.createElement('option');
      option.value = n;
      option.textContent = n === 1 ? 'first page' : 'first ' + n + ' pages';
      if (n === me.limits.ocr_pages) option.selected = true;
      pages.appendChild(option);
    }
    if (!me.ocr_ready) {
      for (const id of ['doc-file', 'doc-pages', 'doc-read', 'doc-url']) this.node(id).disabled = true;
      this.node('document-note').textContent =
        'PDF reading is off on this server (the OCR tools are not installed here). Paste the document’s text below instead.';
    }
    this.node('doc-read').addEventListener('click', () => this.read());
  },

  error(code) {
    const box = this.node('doc-error');
    box.textContent = this.REASONS[code] || ('Reading failed (' + code + ').');
    box.hidden = false;
  },

  progress(done, total) {
    const wrap = this.node('doc-progress-wrap');
    const bar = this.node('doc-progress');
    wrap.hidden = false;
    const percent = total ? Math.round((100 * done) / total) : 5;
    bar.style.width = Math.max(5, percent) + '%';
    bar.textContent = total ? done + ' of ' + total : '';
  },

  async read() {
    this.node('doc-error').hidden = true;
    this.node('doc-status').textContent = '';
    const file = this.node('doc-file').files[0];
    const url = this.node('doc-url').value.trim();
    if (!file && !url) {
      this.error('not_pdf');
      this.node('doc-error').textContent = 'Choose a PDF file or give a storage link first.';
      return;
    }
    this.node('doc-read').disabled = true;
    let started;
    if (file) {
      const form = new FormData();
      form.append('file', file);
      form.append('page_limit', this.node('doc-pages').value);
      started = await Session.api('/v1/upload', { method: 'POST', body: form });
    } else {
      started = await Session.api('/v1/upload', {
        method: 'POST',
        json: { url: url, page_limit: Number(this.node('doc-pages').value) },
      });
    }
    if (started.status !== 202) {
      this.error(started.answer.error || started.status);
      this.node('doc-read').disabled = false;
      return;
    }
    this.progress(0, 0);
    this.node('doc-status').textContent = 'Waiting in line…';
    await this.poll(started.answer.id);
    this.node('doc-read').disabled = false;
  },

  async poll(job) {
    for (;;) {
      await new Promise((resolve) => setTimeout(resolve, 1500));
      const result = await Session.api('/v1/jobs/' + job);
      if (result.status !== 200) {
        this.node('doc-progress-wrap').hidden = true;
        this.error(result.answer.error || result.status);
        return;
      }
      const answer = result.answer;
      if (answer.status === 'queued') {
        this.node('doc-status').textContent = 'Waiting in line (number ' + answer.position + ')…';
      } else if (answer.status === 'working') {
        this.progress(answer.pages_done, answer.pages_total);
        this.node('doc-status').textContent = answer.pages_total
          ? 'Reading page ' + Math.min(answer.pages_done + 1, answer.pages_total) + ' of ' + answer.pages_total + '…'
          : 'Opening the PDF…';
      } else if (answer.status === 'done') {
        this.node('doc-progress-wrap').hidden = true;
        this.node('doc-text').value = answer.text;
        this.node('doc-status').textContent = 'Read ' + answer.pages_done + ' of ' +
          (answer.pages_total || answer.pages_done) + ' pages' +
          (answer.filename ? ' of ' + answer.filename : '') + '. Look the text over, then ask for suggestions.';
        return;
      } else {
        this.node('doc-progress-wrap').hidden = true;
        this.error(answer.error || 'internal');
        return;
      }
    }
  },
};

// Asking the assistant for tags, and showing what it answered field by field.
const Suggest = {
  node(id) { return document.getElementById(id); },
  lastResult: null,

  LABELS: {
    category: 'Category', state: 'State', council: 'Council', camp: 'Camp', lodge: 'Lodge',
    start_date: 'Start date', end_date: 'End date', publisher_of_digital: 'Publisher of digital',
    date_of_original: 'Date of original', date_of_digital: 'Date of digital', identifier: 'Identifier',
    meta_subject: 'Subject', meta_location: 'Location', meta_physical_description: 'Physical description',
    author: 'Author', photographer: 'Photographer', contributors: 'Contributors',
  },

  REASONS: {
    no_api_key: 'No API key is saved yet. An administrator adds one on the Settings page.',
    daily_limit: 'Today’s AI calls are used up. The counter resets at midnight.',
    no_text: 'There is no document text yet — read a PDF or paste text first.',
    no_question: 'Type a question first.',
    queue_full: 'Too many requests are waiting. Try again in a few minutes.',
    paused: 'The app is paused.',
  },

  reason(answer, status) {
    const code = (answer && answer.error) || '';
    if (this.REASONS[code]) return this.REASONS[code];
    if (code.startsWith('ai_') || code.startsWith('bad_answer')) {
      return 'The AI provider did not answer properly: ' + code.replace(/_/g, ' ');
    }
    return 'That did not work (' + (code || status) + ').';
  },

  init() {
    this.node('suggest-go').addEventListener('click', () => this.go());
    this.node('ask-go').addEventListener('click', () => this.ask());
    this.node('ask-question').addEventListener('keydown', (event) => {
      if (event.key === 'Enter') { event.preventDefault(); this.ask(); }
    });
    this.node('suggest-download').addEventListener('click', () => this.download());
  },

  async waitFor(jobId, onTick) {
    for (;;) {
      await new Promise((resolve) => setTimeout(resolve, 1500));
      const result = await Session.api('/v1/jobs/' + jobId);
      if (result.status !== 200) return { error: result.answer.error || result.status };
      if (result.answer.status === 'done') return result.answer.result;
      if (result.answer.status === 'failed') return { error: result.answer.error };
      if (onTick) onTick(result.answer);
    }
  },

  async go() {
    const text = document.getElementById('doc-text').value.trim();
    this.node('suggest-error').hidden = true;
    this.node('suggest-result').hidden = true;
    this.node('suggest-empty').hidden = true;
    if (!text) {
      this.node('suggest-error').textContent = this.REASONS.no_text;
      this.node('suggest-error').hidden = false;
      return;
    }
    this.node('suggest-go').disabled = true;
    this.node('suggest-wait').hidden = false;
    const hints = {};
    for (const [kind, pick] of Object.entries(Taxonomy.picked())) hints[kind] = pick.slug || String(pick.id);
    const started = await Session.api('/v1/suggest', { method: 'POST', json: { text: text, hints: hints } });
    let outcome;
    if (started.status !== 202) {
      outcome = { error: (started.answer && started.answer.error) || started.status };
    } else {
      outcome = await this.waitFor(started.answer.id, (answer) => {
        this.node('suggest-wait-text').textContent = answer.status === 'queued'
          ? 'Waiting in line (number ' + answer.position + ')…' : 'Asking the assistant…';
      });
    }
    this.node('suggest-wait').hidden = true;
    this.node('suggest-go').disabled = false;
    if (outcome && outcome.fields) this.render(outcome);
    else {
      this.node('suggest-error').textContent = this.reason(outcome, started.status);
      this.node('suggest-error').hidden = false;
    }
  },

  copyText(field) {
    const value = field.value;
    if (Array.isArray(value)) return value.join('; ');
    return value === null || value === undefined ? '' : String(value);
  },

  render(result) {
    this.lastResult = result;
    this.node('suggest-summary').textContent = result.summary || '';
    const reviewer = this.node('suggest-reviewer');
    reviewer.hidden = !result.notes_for_reviewer;
    reviewer.textContent = result.notes_for_reviewer ? 'For the reviewer: ' + result.notes_for_reviewer : '';
    const rows = this.node('suggest-rows');
    rows.textContent = '';
    for (const [name, label] of Object.entries(this.LABELS)) {
      const field = result.fields[name] || { value: null, confidence: 0, evidence: '' };
      const row = document.createElement('tr');

      const labelCell = document.createElement('th');
      labelCell.scope = 'row';
      labelCell.textContent = label;

      const valueCell = document.createElement('td');
      if (field.name) {
        valueCell.textContent = field.name + (field.number ? ' (#' + field.number + ')' : '');
        const small = document.createElement('div');
        small.className = 'text-body-secondary small font-monospace';
        small.textContent = field.value;
        valueCell.appendChild(small);
      } else if (field.validated === false && field.model_value) {
        valueCell.innerHTML = '';
        const badge = document.createElement('span');
        badge.className = 'badge text-bg-warning';
        badge.textContent = 'not in the index';
        valueCell.appendChild(badge);
        valueCell.appendChild(document.createTextNode(' ' + field.model_value));
      } else {
        valueCell.textContent = this.copyText(field) || '—';
      }

      const sureCell = document.createElement('td');
      if (field.value !== null && field.value !== undefined && String(field.value) !== '') {
        const badge = document.createElement('span');
        const sure = Math.round((field.confidence || 0) * 100);
        badge.className = 'badge text-bg-' + (sure >= 75 ? 'success' : sure >= 40 ? 'warning' : 'secondary');
        badge.textContent = sure + '%';
        sureCell.appendChild(badge);
      }

      const evidenceCell = document.createElement('td');
      evidenceCell.className = 'small text-body-secondary';
      evidenceCell.textContent = field.evidence ? '“' + field.evidence + '”' : '';

      const copyCell = document.createElement('td');
      const text = this.copyText(field);
      if (text) {
        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'btn btn-outline-secondary btn-sm';
        copy.textContent = 'Copy';
        copy.addEventListener('click', async () => {
          try { await navigator.clipboard.writeText(text); copy.textContent = 'Copied'; } catch (e) {}
          setTimeout(() => { copy.textContent = 'Copy'; }, 1500);
        });
        copyCell.appendChild(copy);
      }

      row.append(labelCell, valueCell, sureCell, evidenceCell, copyCell);
      rows.appendChild(row);
    }
    this.node('suggest-provider').textContent = 'Answered by ' + result.provider + ' (' + result.model + ').';
    this.node('suggest-result').hidden = false;
  },

  download() {
    if (!this.lastResult) return;
    const blob = new Blob([JSON.stringify(this.lastResult, null, 2)], { type: 'application/json' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'tag-suggestions.json';
    link.click();
    URL.revokeObjectURL(link.href);
  },

  async ask() {
    const question = this.node('ask-question').value.trim();
    this.node('ask-error').hidden = true;
    this.node('ask-answer').hidden = true;
    if (!question) {
      this.node('ask-error').textContent = this.REASONS.no_question;
      this.node('ask-error').hidden = false;
      return;
    }
    this.node('ask-go').disabled = true;
    const text = document.getElementById('doc-text').value.trim();
    const started = await Session.api('/v1/ask', {
      method: 'POST', json: text ? { question: question, text: text } : { question: question },
    });
    let outcome;
    if (started.status !== 202) outcome = { error: (started.answer && started.answer.error) || started.status };
    else outcome = await this.waitFor(started.answer.id);
    this.node('ask-go').disabled = false;
    if (outcome && outcome.answer) {
      this.node('ask-answer').textContent = outcome.answer;
      this.node('ask-answer').hidden = false;
    } else {
      this.node('ask-error').textContent = this.reason(outcome, started.status);
      this.node('ask-error').hidden = false;
    }
  },
};

const Pages = {
  show(id, visible) { const node = document.getElementById(id); if (node) node.hidden = !visible; },
  status(kind, text) {
    const node = document.getElementById('status');
    if (!node) return;
    node.className = 'alert alert-' + kind;
    node.textContent = text;
    node.hidden = !text;
  },

  indexLine(me) {
    const index = me.index || {};
    if (!index.received_at) return '';
    const counts = index.counts || {};
    const when = new Date(index.received_at * 1000).toLocaleString();
    const parts = ['councils', 'camps', 'lodges', 'states', 'posts']
      .filter((kind) => counts[kind])
      .map((kind) => counts[kind].toLocaleString() + ' ' + kind);
    return 'Index received ' + when + ': ' + parts.join(', ') + '.';
  },

  async app() {
    const me = await Session.signIn();
    if (!me) {
      this.status('warning', 'Please open this page from the Scouting Memories website. ' +
        'If you are the owner, you can sign in with the admin passphrase instead.');
      return;
    }
    this.status('secondary', '');
    this.show('app-body', true);
    this.show('who', true);
    document.getElementById('who-name').textContent = me.user;
    this.show('settings-link', me.admin);
    document.getElementById('who-role').textContent = me.role.replace(/_/g, ' ');
    Doc.init(me);
    Suggest.init();
    const line = this.indexLine(me);
    if (line) {
      document.getElementById('index-note').textContent = line;
      await Taxonomy.start();
    }
  },

  async settings() {
    const me = await Session.signIn();
    if (!me) {
      this.status('warning', 'Please sign in first.');
      return;
    }
    if (!me.admin) {
      this.status('warning', 'The settings dashboard is for administrators. The app itself is on the front page.');
      return;
    }
    this.status('secondary', '');
    this.show('settings-body', true);
    const line = this.indexLine(me);
    document.getElementById('index-status').textContent = line ||
      'Nothing received yet. In WordPress, open Settings › AI Tagging Assistant and press “Send index to the tagging assistant”.';
    await Settings.load();
  },

  login() {
    const form = document.getElementById('login-form');
    const error = document.getElementById('login-error');
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      error.hidden = true;
      const key = document.getElementById('login-key').value;
      const result = await Session.api('/v1/login', { method: 'POST', json: { key: key } });
      if (result.status === 200) {
        Session.value = result.answer.sid;
        location.href = '/';
        return;
      }
      const reasons = {
        login_off: 'The admin passphrase is not set up on this server.',
        too_many_tries: 'Too many wrong tries. Wait an hour and try again.',
        wrong_key: 'That passphrase is not right.',
      };
      error.textContent = reasons[result.answer.error] || 'Signing in did not work (' + result.status + ').';
      error.hidden = false;
    });
  },
};

// The settings dashboard: provider, keys, prompt, limits and the reference material.
const Settings = {
  node(id) { return document.getElementById(id); },

  async load() {
    const result = await Session.api('/v1/admin/settings');
    if (result.status !== 200) return;
    this.fill(result.answer);
    this.node('set-save').addEventListener('click', () => this.save());
    this.node('note-add').addEventListener('click', () => this.addNote());
    this.node('note-upload').addEventListener('click', () => this.uploadFile());
    for (const provider of ['claude', 'gemini', 'grok']) {
      this.node('test-' + provider).addEventListener('click', () => this.test(provider));
    }
  },

  fill(settings) {
    this.node('set-provider').value = settings.provider;
    for (const provider of ['claude', 'gemini', 'grok']) {
      this.node('set-model-' + provider).value = settings['model_' + provider];
      const keyBox = this.node('set-key-' + provider);
      keyBox.value = '';
      keyBox.placeholder = settings['key_' + provider] || 'no key saved yet';
    }
    this.node('set-ocr-pages').value = settings.ocr_pages;
    this.node('set-ocr-pages').max = settings.max_ocr_pages;
    this.node('set-daily-cap').value = settings.ai_calls_per_day;
    this.node('set-daily-cap').max = settings.max_ai_calls_per_day;
    this.node('set-prompt').value = settings.system_prompt;
    this.node('set-default-prompt').textContent = settings.default_prompt;
    this.node('set-usage').textContent = 'Calls today: ' + settings.calls_today + ' of ' + settings.ai_calls_per_day + '.';
    const list = this.node('notes-list');
    list.textContent = '';
    if (!settings.notes.length) {
      const item = document.createElement('li');
      item.className = 'list-group-item text-body-secondary';
      item.textContent = 'No reference material yet.';
      list.appendChild(item);
    }
    for (const note of settings.notes) {
      const item = document.createElement('li');
      item.className = 'list-group-item d-flex justify-content-between align-items-center';
      const text = document.createElement('span');
      text.textContent = note.title + ' ';
      const small = document.createElement('span');
      small.className = 'text-body-secondary small';
      small.textContent = '(' + note.chars.toLocaleString() + ' characters)';
      text.appendChild(small);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn btn-outline-secondary btn-sm';
      remove.textContent = 'Remove';
      remove.addEventListener('click', async () => {
        const result = await Session.api('/v1/admin/notes/' + note.id, { method: 'DELETE' });
        if (result.status === 200) item.remove();
      });
      item.append(text, remove);
      list.appendChild(item);
    }
  },

  problem(id, answer, status) {
    const reasons = {
      no_api_key: 'No key is saved for that provider yet.',
      bad_file: 'Only .txt, .md, .csv and .xlsx files work here.',
      empty_file: 'That file holds no text.',
      body_too_large: 'That is too big.',
    };
    const code = (answer && answer.error) || '';
    let said = reasons[code] || 'That did not work (' + (code || status) + ').';
    if (answer && answer.detail) said += ' The provider said: ' + answer.detail;
    this.node(id).textContent = said;
    this.node(id).hidden = false;
  },

  async save() {
    this.node('set-error').hidden = true;
    this.node('set-test-ok').hidden = true;
    this.node('set-saved').hidden = true;
    const body = {
      provider: this.node('set-provider').value,
      system_prompt: this.node('set-prompt').value,
      ocr_pages: Number(this.node('set-ocr-pages').value),
      ai_calls_per_day: Number(this.node('set-daily-cap').value),
    };
    for (const provider of ['claude', 'gemini', 'grok']) {
      body['model_' + provider] = this.node('set-model-' + provider).value;
      const key = this.node('set-key-' + provider).value.trim();
      if (key) body['api_key_' + provider] = key;
    }
    const result = await Session.api('/v1/admin/settings', { method: 'POST', json: body });
    if (result.status === 200) {
      this.fill(result.answer);
      this.node('set-saved').hidden = false;
      setTimeout(() => { this.node('set-saved').hidden = true; }, 3000);
    } else {
      this.problem('set-error', result.answer, result.status);
    }
  },

  async test(provider) {
    this.node('set-error').hidden = true;
    this.node('set-test-ok').hidden = true;
    this.node('test-' + provider).disabled = true;
    const result = await Session.api('/v1/admin/test', { method: 'POST', json: { provider: provider } });
    this.node('test-' + provider).disabled = false;
    if (result.status === 200) {
      this.node('set-test-ok').textContent = provider + ' answered with model ' + result.answer.model +
        ': “' + result.answer.answer + '”';
      this.node('set-test-ok').hidden = false;
    } else {
      this.problem('set-error', result.answer, result.status);
    }
  },

  async addNote() {
    this.node('note-error').hidden = true;
    const title = this.node('note-title').value.trim();
    const body = this.node('note-body').value.trim();
    if (!title || !body) {
      this.problem('note-error', { error: 'empty_file' }, 400);
      return;
    }
    const result = await Session.api('/v1/admin/notes', { method: 'POST', json: { title: title, body: body } });
    if (result.status === 200) {
      this.node('note-title').value = '';
      this.node('note-body').value = '';
      await this.reload();
    } else {
      this.problem('note-error', result.answer, result.status);
    }
  },

  async uploadFile() {
    this.node('note-error').hidden = true;
    const file = this.node('note-file').files[0];
    if (!file) {
      this.problem('note-error', { error: 'bad_file' }, 400);
      return;
    }
    const form = new FormData();
    form.append('file', file);
    const result = await Session.api('/v1/admin/files', { method: 'POST', body: form });
    if (result.status === 200) {
      this.node('note-file').value = '';
      await this.reload();
    } else {
      this.problem('note-error', result.answer, result.status);
    }
  },

  async reload() {
    const result = await Session.api('/v1/admin/settings');
    if (result.status === 200) this.fill(result.answer);
  },
};

document.addEventListener('DOMContentLoaded', () => {
  const page = document.body.dataset.page;
  if (page && typeof Pages[page] === 'function') Pages[page]();
});
