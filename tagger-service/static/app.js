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
    const token = this.takeToken();
    if (this.value) {
      const me = await this.api('/v1/me');
      if (me.status === 200) { this.csrf = me.answer.csrf; return me.answer; }
      this.value = '';
    }
    if (token) {
      const started = await this.api('/v1/session', { method: 'POST', json: { token: token } });
      if (started.status === 200) {
        this.value = started.answer.sid;
        this.csrf = started.answer.csrf;
        const me = await this.api('/v1/me');
        if (me.status === 200) return me.answer;
      }
    }
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
    document.getElementById('who-role').textContent = me.role.replace(/_/g, ' ');
    Doc.init(me);
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

document.addEventListener('DOMContentLoaded', () => {
  const page = document.body.dataset.page;
  if (page && typeof Pages[page] === 'function') Pages[page]();
});
