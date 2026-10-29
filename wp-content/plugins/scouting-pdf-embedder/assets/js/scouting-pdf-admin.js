/**
 * The PDF Text dashboard (wp-admin > PDF Text) and the "PDF text" box on the post edit
 * screen. Plain JavaScript over the plugin's own REST endpoints; WordPress admin markup
 * only. Stored text reaches the page only as textContent or a textarea's value, never as
 * HTML, however strange the OCR output.
 */
(function() {
    'use strict';

    var config = window.ScoutingPdfAdminConfig || {};
    var POLL_MS = 10000;

    function api(method, path, body) {
        var options = {
            method: method,
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': config.nonce || '' }
        };
        if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        return fetch(config.restRoot + path, options).then(function(response) {
            return response.json().catch(function() { return {}; }).then(function(data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        }, function() {
            return { ok: false, status: 0, data: {} };
        });
    }

    function el(tag, text) {
        var node = document.createElement(tag);
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function setText(id, text) {
        var node = document.getElementById(id);
        if (node) {
            node.textContent = text;
            node.hidden = text === '';
        }
    }

    function toggle(id, show) {
        var node = document.getElementById(id);
        if (node) {
            node.hidden = !show;
        }
    }

    // While one request runs its button is dead, so a double click cannot double-act
    function withButton(button, work) {
        if (!button || button.disabled) {
            return;
        }
        button.disabled = true;
        work().then(function() { button.disabled = false; }, function() { button.disabled = false; });
    }

    function clockTime(datetime) {
        return (datetime || '').slice(11, 16);
    }

    function errorText(result, fallback) {
        return (result.data && result.data.message) ? result.data.message : fallback;
    }

    // ================= The dashboard =================

    var dashboard = document.getElementById('sm-ocr-dashboard');
    if (dashboard) {
        initDashboard(dashboard.getAttribute('data-tab'));
    }

    function initDashboard(tab) {
        var docPage = 1;

        function renderStatus(s) {
            var state;
            if (s.queue === 'off') {
                state = 'Background reading is switched off. Switch it on under Settings → PDF OCR.';
            } else if (s.queue === 'paused') {
                state = 'The queue is paused. Nothing is sent to the reading service until you press Resume.';
            } else if (s.queue === 'backoff') {
                state = 'The reading service is busy or full. The queue will try again at ' + clockTime(s.backoff_until) + '.';
            } else {
                state = 'The queue is running.';
            }
            setText('sm-ocr-state', state);
            setText('sm-ocr-docs-line', 'Documents: ' + s.docs.queued + ' waiting · ' + s.docs.working +
                ' being read · ' + s.docs.done + ' done · ' + s.docs.failed + ' failed');
            setText('sm-ocr-today-line', 'Pages read today: ' + s.today.queue_pages + ' by the queue, ' +
                s.today.reader_pages + ' by readers');
            setText('sm-ocr-unchecked-line', 'Pages not yet checked by a reviewer: ' + s.pages_unchecked);
            var current = '';
            if (s.current) {
                current = 'Now reading: ' + s.current.label + ' — ' + (s.current.total_pages
                    ? 'page ' + Math.min(s.current.pages_done + 1, s.current.total_pages) + ' of ' + s.current.total_pages
                    : 'page count not known yet');
            }
            setText('sm-ocr-current', current);
            var lastrun = '';
            if (s.last_tick) {
                if (s.queue === 'running' && s.last_tick_age !== null && s.last_tick_age > 180) {
                    lastrun = 'The queue has not run since ' + s.last_tick + '. Check the timer on the server, or press Run once now.';
                } else {
                    lastrun = 'The queue last ran at ' + s.last_tick + '.';
                }
            } else if (s.queue !== 'off') {
                lastrun = 'The queue has not run yet.';
            }
            setText('sm-ocr-lastrun', lastrun);
            toggle('sm-ocr-pause', s.queue === 'running' || s.queue === 'backoff');
            toggle('sm-ocr-resume', s.queue === 'paused');
            toggle('sm-ocr-tick', s.queue !== 'off');
        }

        function statusLabel(status) {
            return { queued: 'Waiting', working: 'Being read', done: 'Done', failed: 'Failed' }[status] || status;
        }

        function docRow(doc) {
            var tr = el('tr');

            var cell = el('td');
            var name = doc.label || doc.pdf_url;
            if (doc.permalink) {
                var link = el('a', name);
                link.href = doc.permalink;
                cell.appendChild(link);
            } else {
                cell.appendChild(el('strong', name));
            }
            cell.appendChild(el('br'));
            var path = doc.pdf_url.replace(/^https:\/\/[^\/]+/, '');
            cell.appendChild(el('small', path));
            if (doc.edit_link) {
                cell.appendChild(document.createTextNode(' · '));
                var edit = el('a', 'Edit post');
                edit.href = doc.edit_link;
                cell.appendChild(edit);
            }
            tr.appendChild(cell);

            var pages = doc.total_pages ? doc.pages_done + ' of ' + doc.total_pages + ' read' : '–';
            if (doc.pages_failed) {
                pages += ', ' + doc.pages_failed + ' failed';
            }
            tr.appendChild(el('td', pages));
            tr.appendChild(el('td', doc.pages_checked + ' of ' + doc.pages_done));
            tr.appendChild(el('td', statusLabel(doc.status)));

            var who = doc.enqueued_by_name ? 'by ' + doc.enqueued_by_name
                : (doc.source === 'auto' ? 'from a post' : 'by the site scan');
            if (doc.enqueued_at) {
                who += ' on ' + doc.enqueued_at.slice(0, 10);
            }
            tr.appendChild(el('td', who));

            var actions = el('td');
            if (config.isAdmin) {
                if (doc.status === 'failed' || (doc.status === 'done' && doc.pages_failed > 0)) {
                    var retry = el('button', 'Try again');
                    retry.type = 'button';
                    retry.className = 'button button-small';
                    retry.addEventListener('click', function() {
                        withButton(retry, function() {
                            return api('POST', 'docs/' + doc.id + '/retry', {}).then(function(r) {
                                setText('sm-ocr-add-result', r.ok ? '' : errorText(r, 'That could not be tried again.'));
                                return refresh();
                            });
                        });
                    });
                    actions.appendChild(retry);
                }
                if (doc.status === 'queued') {
                    var remove = el('button', 'Remove');
                    remove.type = 'button';
                    remove.className = 'button button-small';
                    remove.addEventListener('click', function() {
                        withButton(remove, function() {
                            return api('POST', 'docs/' + doc.id + '/remove', {}).then(function(r) {
                                setText('sm-ocr-add-result', r.ok ? '' : errorText(r, 'That could not be removed.'));
                                return refresh();
                            });
                        });
                    });
                    if (actions.firstChild) {
                        actions.appendChild(document.createTextNode(' '));
                    }
                    actions.appendChild(remove);
                }
            }
            tr.appendChild(actions);
            return tr;
        }

        function renderDocs(result) {
            var tbody = document.getElementById('sm-ocr-docs');
            if (!tbody) {
                return;
            }
            tbody.textContent = '';
            if (!result.docs.length) {
                var tr = el('tr');
                var td = el('td', 'No documents are in the queue.');
                td.colSpan = 6;
                tr.appendChild(td);
                tbody.appendChild(tr);
            } else {
                result.docs.forEach(function(doc) {
                    tbody.appendChild(docRow(doc));
                });
            }
            var pager = document.getElementById('sm-ocr-pager');
            if (pager) {
                pager.textContent = '';
                var pageCount = Math.max(1, Math.ceil(result.total / result.per_page));
                pager.appendChild(el('span', result.total + ' document(s)'));
                if (pageCount > 1) {
                    var back = el('button', '‹');
                    back.type = 'button';
                    back.className = 'button button-small';
                    back.disabled = docPage <= 1;
                    back.addEventListener('click', function() { docPage--; refresh(); });
                    var forward = el('button', '›');
                    forward.type = 'button';
                    forward.className = 'button button-small';
                    forward.disabled = docPage >= pageCount;
                    forward.addEventListener('click', function() { docPage++; refresh(); });
                    pager.appendChild(document.createTextNode(' '));
                    pager.appendChild(back);
                    pager.appendChild(el('span', ' ' + docPage + ' of ' + pageCount + ' '));
                    pager.appendChild(forward);
                }
            }
        }

        function refresh() {
            // Polling the status is also what keeps the queue moving when cron is late
            var work = [api('GET', 'status').then(function(r) {
                if (r.ok) {
                    renderStatus(r.data);
                }
            })];
            if (tab === 'queue') {
                work.push(api('GET', 'docs?page=' + docPage).then(function(r) {
                    if (r.ok) {
                        renderDocs(r.data);
                    }
                }));
            }
            return Promise.all(work);
        }

        function wireQueueButton(id, action, doneText) {
            var button = document.getElementById(id);
            if (!button) {
                return;
            }
            button.addEventListener('click', function() {
                withButton(button, function() {
                    return api('POST', 'queue', { action: action }).then(function(r) {
                        if (!r.ok) {
                            setText('sm-ocr-action-result', errorText(r, 'That did not work.'));
                        } else {
                            setText('sm-ocr-action-result', r.data.ticked === false
                                ? 'Another run is already under way.' : (doneText || ''));
                        }
                        return refresh();
                    });
                });
            });
        }
        wireQueueButton('sm-ocr-pause', 'pause', 'Paused.');
        wireQueueButton('sm-ocr-resume', 'resume', 'Running.');
        wireQueueButton('sm-ocr-tick', 'tick', '');

        var add = document.getElementById('sm-ocr-add');
        if (add) {
            add.addEventListener('click', function() {
                var input = document.getElementById('sm-ocr-add-url');
                withButton(add, function() {
                    return api('POST', 'docs', { url: input.value }).then(function(r) {
                        var message;
                        if (r.status === 201) {
                            message = 'Added. It will be read next.';
                            input.value = '';
                        } else if (r.data.status === 'already_queued') {
                            message = 'This document is already in the queue.';
                        } else if (r.data.status === 'already_read') {
                            message = 'This document has already been read.';
                        } else {
                            message = errorText(r, 'That link could not be used.');
                        }
                        setText('sm-ocr-add-result', message);
                        return refresh();
                    });
                });
            });
        }

        var scan = document.getElementById('sm-ocr-scan');
        if (scan) {
            scan.addEventListener('click', function() {
                if (scan.disabled) {
                    return;
                }
                scan.disabled = true;
                var progress = document.getElementById('sm-ocr-scan-progress');
                var scanned = 0;
                var added = 0;
                (function step(after) {
                    api('POST', 'scan', { after_id: after }).then(function(r) {
                        if (!r.ok) {
                            progress.textContent = errorText(r, 'The scan stopped early. Press the button to go on.');
                            scan.disabled = false;
                            return;
                        }
                        scanned += r.data.posts_scanned;
                        added += r.data.docs_added;
                        if (r.data.done) {
                            progress.textContent = 'Done: ' + scanned + ' posts looked through, ' + added +
                                ' new document(s) added to the queue.';
                            scan.disabled = false;
                            refresh();
                        } else {
                            progress.textContent = 'Looked through ' + scanned + ' posts, found ' + added +
                                ' new document(s)…';
                            step(r.data.last_id);
                        }
                    });
                })(0);
            });
        }

        refresh();
        var timer = setInterval(function() {
            if (!document.hidden) {
                refresh();
            }
        }, POLL_MS);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refresh();
            }
        });
    }
})();
