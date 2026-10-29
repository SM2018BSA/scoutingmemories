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
    const line = this.indexLine(me);
    if (line) document.getElementById('index-note').textContent = line;
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
