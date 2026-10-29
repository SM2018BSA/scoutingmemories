# Scouting Memories AI Tagging Assistant

Suggests metadata for documents historians are about to upload: the archive's TIMELINE
fields (state, council, camp, lodge, start and end year) and its descriptive META fields.
The document's text is matched against the site's own index of councils, camps and
lodges (pushed from WordPress), and an AI provider — Claude, Gemini or Grok — fills in
the fields from those candidates only. Historians review every suggestion.

One standard-library Python file (`tagger_service.py`), no pip anywhere. It runs as the
systemd service `scouting-tagger` on the development droplet, listening on
`127.0.0.1:8001` behind Apache at `https://tagger.68-183-140-47.sslip.io/`. The
WordPress side is the `scouting-ai-tagger` plugin.

## Settings (`/etc/scouting/tagger.env`, read by systemd; root only)

| Name | Default | Meaning |
|---|---|---|
| `TAGGER_SECRET` | *(required)* | At least 32 characters. Signs WordPress's calls and the one-time login links. The same value goes in the WordPress plugin settings. |
| `TAGGER_ADMIN_KEY` | *(off)* | The owner's passphrase for `/login` (standalone use without WordPress). At least 12 characters; leave unset to turn `/login` off. |
| `TAGGER_LISTEN_HOST` | `127.0.0.1` | Never expose the app directly. |
| `TAGGER_PORT` | `8001` | |
| `TAGGER_FRAME_ANCESTORS` | *(empty)* | Which sites may embed the app in a frame, e.g. `https://wp.68-183-140-47.sslip.io`. Empty = no site may. |
| `TAGGER_MAX_PDF_MB` | `60` | Upload cap. |
| `TAGGER_MAX_OCR_PAGES` | `20` | Hard ceiling on pages read per document (the admin setting stays under it). |
| `TAGGER_PAGES_PER_HOUR` | `120` | OCR rate cap. |
| `TAGGER_AI_CALLS_PER_DAY` | `200` | Hard ceiling on AI calls per day (the admin setting stays under it). |
| `TAGGER_QUEUE_MAX` | `20` | Jobs waiting at most. |
| `TAGGER_DPI` | `300` | Render resolution for OCR. |
| `TAGGER_CACHE_MB` / `TAGGER_KEEP_DAYS` | `1024` / `7` | Uploaded files kept this much, this long. |
| `TAGGER_SESSION_HOURS` | `12` | How long a login lasts. |
| `TAGGER_PAUSED` | `0` | `1` = answer but start no new work. |
| `TAGGER_STATE_DIR` / `TAGGER_CACHE_DIR` | | Only for running by hand; systemd passes `STATE_DIRECTORY` / `CACHE_DIRECTORY`. |
| `TAGGER_INSECURE_COOKIE` | `0` | `1` = cookies without `Secure` for local plain-http testing. Never on the server. |

AI provider keys are NOT environment settings: the owner enters them in the settings
dashboard, and they live in the app's database.

## Who may call what

- **People** come in with a one-time signed link from the WordPress page (or `/login`
  with the admin passphrase) and get a session: a cookie plus the same value in
  `X-Tagger-Session`. State-changing calls also need the session's `X-Tagger-CSRF`.
- **WordPress** (server to server) signs each call with the shared secret:
  `X-Scouting-Time` = unix seconds, `X-Scouting-Signature` =
  `hex(hmac_sha256(secret, time + "\n" + method + "\n" + path + "\n" + sha256_hex(body)))`,
  valid for ±300 seconds. Same scheme as the OCR app.

### Login links

`/?token=<payload>.<signature>` where `<payload>` is unpadded url-safe base64 of
`{"u":"<user>","r":"<role>","a":0|1,"x":<unix expiry>,"n":"<nonce>"}` and
`<signature>` is `hex(hmac_sha256(secret, "tagger-login\n" + <payload>))`. Roles:
administrator, manager, historian, regional, index_contributor; `a:1` opens the
settings dashboard. Links expire (WordPress sets 120 s) and each nonce works once.

## Endpoints so far

| Method and path | Who | What |
|---|---|---|
| `GET /v1/health` | anyone | `{"ok":true}` |
| `GET /` `/settings` `/login` `/static/<file>` | anyone | the pages (all data sits behind /v1/) |
| `POST /v1/session` | login link | `{"token":…}` → session |
| `POST /v1/login` | admin passphrase | `{"key":…}` → session (20 wrong tries an hour, then 429) |
| `GET /v1/me` | session | who I am, index status, limits |
| `POST /v1/logout` | session + CSRF | ends the session |
| `GET /v1/index/status` | WordPress (signed) | when the index was last received, row counts |

Errors are always `{"error":"<code>"}`.

## Running it by hand (development PC)

```bash
cd tagger-service
TAGGER_SECRET=dev-secret-dev-secret-dev-secret-00 \
TAGGER_ADMIN_KEY=dev-passphrase \
TAGGER_STATE_DIR=state TAGGER_CACHE_DIR=cache \
TAGGER_INSECURE_COOKIE=1 \
python tagger_service.py
```

`state/` and `cache/` stay out of git. Without Tesseract/Poppler the app still runs;
PDF reading answers `ocr_unavailable` until they are installed.

A login link for testing (prints a URL; replace the secret):

```bash
python -c "import base64,hashlib,hmac,json,secrets,time;p=base64.urlsafe_b64encode(json.dumps({'u':'roger','r':'historian','a':1,'x':int(time.time())+120,'n':secrets.token_hex(8)}).encode()).rstrip(b'=').decode();print('http://127.0.0.1:8001/?token=%s.%s'%(p,hmac.new(b'dev-secret-dev-secret-dev-secret-00',('tagger-login\n'+p).encode(),hashlib.sha256).hexdigest()))"
```

## On the droplet

Installed once (plan step 8) as a sparse checkout of `tagger-service/` in
`/opt/scouting-tagger`, unit `scouting-tagger.service`, settings in
`/etc/scouting/tagger.env`, Apache name `tagger.68-183-140-47.sslip.io` proxying to
`127.0.0.1:8001`. Updated only by the control panel's **UPDATE TAGGER APP** button.
The service logs a NOTE at start when the unit file in git differs from the installed
copy.
