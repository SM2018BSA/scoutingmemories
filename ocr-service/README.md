# Scouting Memories page OCR

Reads the text on **one page** of a scanned PDF at a time, when a reader asks for it in the PDF
viewer. Nothing is read in bulk, and the original PDFs are never changed.

```
"Text" button in the viewer → WordPress → this service → WordPress saves the text → the viewer shows it
```

- WordPress sends only the PDF's link and a page number. The service downloads the PDF from the
  archive's public storage itself, the way a visitor's browser does. It needs no storage key.
- WordPress always calls the service, never the other way round.
- One page is read at a time.

It runs on the development server, not on WP Engine. This folder is never published to the
live site: the control panel's live release only copies ticked plugin and theme folders.

## What it is made of

| Part | What it does |
|---|---|
| `ocr_service.py` | The whole service: one Python file, standard library only |
| `scouting-ocr.service` | Runs it as a normal Ubuntu service (systemd) |
| Poppler (`pdfinfo`, `pdftoppm`) | Counts the pages and turns one page into a picture |
| Tesseract | Reads the picture |
| SQLite | The list of jobs, one file |

No Docker, no pip, no outside package sources.

## Requests

Every request except `/v1/health` must be signed (see below). Answers are JSON.

| Request | Answer |
|---|---|
| `POST /v1/jobs` with `{"url": "https://…/file.pdf", "page": 12}` | `202` and `{"id": "…", "status": "queued", "page": 12, "position": 1}` |
| The same `POST` again | `200` and the same job as it is now. A page that failed is put back in line (`202`, same `id`). |
| `GET /v1/jobs/<id>` | `200`; `status` is `queued`, `working`, `done` or `failed` |
| `GET /v1/health` | `200` and `{"ok": true}`; needs no signature |

The link is stored in one fixed form: `https`, the storage host, the path percent-encoded. A
query string (`?…`) is dropped, so one stored file always has one link.

A finished job:

```json
{
  "id": "…", "status": "done", "page": 12,
  "text": "the page's text",
  "words": [[1, 1, 1, 213, 180, 96, 31, 93, "CAMP"]],
  "pages": 48, "width": 2550, "height": 3300, "dpi": 300,
  "seconds": 6.4, "engine": "tesseract 5.3.4 eng"
}
```

Each entry in `words` is `[block, paragraph, line, left, top, width, height, confidence, text]`.
Positions are pixels in a picture `width` × `height` (the part of the page a PDF viewer shows);
confidence is 0 to 100. At most 20,000 words and 200,000 characters are kept for a page.

A failed job has `"status": "failed"` and an `error`:

| `error` | Meaning |
|---|---|
| `download_failed` | The PDF could not be downloaded, or the download was cut short |
| `too_large` | The PDF is bigger than `OCR_MAX_PDF_MB` |
| `not_pdf` | The file is not a PDF, or cannot be opened |
| `no_such_page` | The document has fewer pages |
| `page_too_large` | The page is so big it cannot be rendered within `OCR_MAX_PIXELS` |
| `render_failed` | The page could not be turned into a picture |
| `ocr_failed` | Tesseract could not read the picture |
| `timeout` | A step took too long (download 60 s, page details 20 s, picture 60 s, reading 120 s) |
| `internal` | Anything else; the reason is in the service's log |

Refusals:

| Status | `error` | Meaning |
|---|---|---|
| 400 | `bad_request` | The address has characters it should not, the body is not valid JSON, or `url` is missing |
| 400 | `bad_page` | `page` is not a whole number from 1 to 9999 |
| 401 | `bad_signature` | The signature is missing or wrong |
| 401 | `bad_time` | The signature is right but the request's time is more than 5 minutes off: a clock is wrong |
| 403 | `host_not_allowed` | The link is not an `https://` link to a `.pdf` on the archive's storage |
| 404 | `not_found` | No such job (finished jobs are deleted after `OCR_KEEP_DAYS`), or no such address |
| 411 | `bad_request` | A `POST` without a `Content-Length` |
| 413 | `body_too_large` | The request body is over 8 KB |
| 429 | `queue_full` | Too many pages are waiting |
| 429 | `hourly_limit` | The hourly limit is reached (retries of failed pages count too) |
| 500 | `internal` | Something went wrong inside; the reason is in the log |
| 503 | `paused` | The service is paused |

When the service is stopped or restarting, the web server in front answers instead: `502`,
`503` or `504` with an HTML page, not JSON. Treat any answer without a JSON `error` as "try
again shortly".

## Signature

Two headers:

```
X-Scouting-Time: <the time, seconds since 1970>
X-Scouting-Signature: <HMAC-SHA256, 64 hex characters>
```

The signature is made with the shared secret over these four lines joined by newlines:

```
<time>
<method>            GET or POST
<path>              for example /v1/jobs
<sha256 of the body, hex>   (of the empty string for GET)
```

A request more than 5 minutes old (or from a clock more than 5 minutes off) is refused.

## Settings

Read from the environment. On the server they live in `/etc/scouting/ocr.env`, which only root
can read and which is never in git. One `NAME=value` per line; a comment must be on its own
line (starting with `#`), never after a value. A value that is not a number, or is outside its
range, is replaced and the log says so.

| Name | Default | Range | Meaning |
|---|---|---|---|
| `OCR_SECRET` | none, required | at least 32 characters | The shared secret |
| `OCR_ALLOWED_HOST` | `storage.scoutingmemories.org` | | The only host PDFs are downloaded from |
| `OCR_LISTEN_HOST` | `127.0.0.1` | | Address it listens on; leave it |
| `OCR_PORT` | `8000` | 1–65535 | Port it listens on |
| `OCR_MAX_PDF_MB` | `60` | 1–500 | Largest PDF it will download |
| `OCR_PAGES_PER_HOUR` | `120` | 1–100000 | Pages accepted per hour, retries included |
| `OCR_QUEUE_MAX` | `50` | 1–10000 | Pages allowed to be waiting or being read |
| `OCR_PAUSED` | `0` | `0` or `1` | `1` refuses new pages and reads none |
| `OCR_DPI` | `300` | 72–600 | Resolution a page is rendered at |
| `OCR_MAX_PIXELS` | `40000000` | 1000000–200000000 | An oversized page is rendered at a lower resolution to stay under this; below 72 dpi it is refused |
| `OCR_CACHE_MB` | `2048` | 50–100000 | Disk space for downloaded PDFs; the oldest are deleted first |
| `OCR_KEEP_DAYS` | `7` | 1–365 | Finished jobs and downloaded PDFs are deleted after this many days (WordPress keeps the text) |

When systemd does not provide `STATE_DIRECTORY` and `CACHE_DIRECTORY` (running by hand),
`OCR_STATE_DIR` and `OCR_CACHE_DIR` say where the job list and the PDFs go.

## How one page is read

1. Download the PDF: `https` only, the storage host only, no redirects followed, a size limit and a
   60-second limit; it must be a PDF and arrive complete. The copy is kept so the next page of the
   same document is quick.
2. `pdfinfo` gives the page count and the page's size.
3. `pdftoppm -f N -l N -r 300 -cropbox -gray -png -singlefile` makes one grey picture of the page.
4. `tesseract page.png out -l eng --psm 1 --dpi 300 txt tsv` reads it (`--psm 1` also works out
   which way up the page is).
5. The text and the word positions are saved; the picture is deleted.

If the service restarts while a page is being read, that page goes back in line once. If the
same page is interrupted a second time it is marked failed, so one bad page cannot stop the rest.

## On the server

- Code: `/opt/scouting-ocr` (a git checkout of this repository, only this folder).
- It changes only when **UPDATE OCR APP** is pressed in the control panel. That button fetches the
  chosen branch from GitHub, moves forward to it and restarts the service. Nothing updates by itself.
- `scouting-ocr.service` is installed by hand: copy it to `/etc/systemd/system/` and run
  `systemctl daemon-reload`. When the copy in git has changed, the service says so in its log at start.
- Log: `journalctl -u scouting-ocr`. One line per page read (`done: …` or `failed: …`), one per
  request that was not a plain successful read, and the limits in force at start.
- Job list: `/var/lib/scouting-ocr/jobs.sqlite3`. PDF cache: `/var/cache/scouting-ocr/`. Both may be
  deleted while the service is stopped; it starts fresh.
- The web server passes `/v1/…` on the OCR address to the service and refuses request bodies over 8 KB:
  `ProxyPass /v1/ http://127.0.0.1:8000/v1/ retry=0 timeout=20`
