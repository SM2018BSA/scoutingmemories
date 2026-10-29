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

    // ================= The PDF text box on the post edit screen =================

    var box = document.getElementById('scouting-pdf-text-box');
    if (box) {
        var boxPostId = parseInt(box.getAttribute('data-post-id'), 10);
        Array.prototype.forEach.call(box.querySelectorAll('.sm-ocr-box-doc'), function(section) {
            initBoxDoc(section, boxPostId);
        });
    }

    function initBoxDoc(section, postId) {
        var key = section.getAttribute('data-doc-key');
        var viewUrl = section.getAttribute('data-view-url');
        var body = section.querySelector('.sm-ocr-box-body');
        var rowPage = 1;
        var watch = null;    // refreshes the box while the document is being read
        var notice = '';

        function load() {
            return api('GET', 'doc-text?doc_key=' + key + '&page=' + rowPage).then(function(r) {
                if (!r.ok) {
                    body.textContent = errorText(r, 'This could not be loaded.');
                    return;
                }
                render(r.data);
            });
        }

        function watchWhileReading(on) {
            if (on && !watch) {
                watch = setInterval(function() {
                    if (!document.hidden) {
                        load();
                    }
                }, POLL_MS);
            } else if (!on && watch) {
                clearInterval(watch);
                watch = null;
            }
        }

        function smallButton(label, primary) {
            var button = el('button', label);
            button.type = 'button';
            button.className = primary ? 'button button-primary' : 'button';
            return button;
        }

        function say(message) {
            notice = message;
        }

        function editorFor(page, alreadyApproved) {
            var holder = el('div');
            var area = el('textarea');
            area.value = page.text;
            area.rows = 8;
            area.style.width = '100%';
            holder.appendChild(area);
            var buttons = el('p');
            if (alreadyApproved) {
                var save = smallButton('Save changes', false);
                save.addEventListener('click', function() {
                    withButton(save, function() {
                        return api('POST', 'approve/' + page.id, { text: area.value }).then(function(r) {
                            say(r.ok ? 'Saved.' : errorText(r, 'That did not work.'));
                            return load();
                        });
                    });
                });
                buttons.appendChild(save);
            } else {
                var approve = smallButton('Approve', true);
                approve.addEventListener('click', function() {
                    withButton(approve, function() {
                        return api('POST', 'approve/' + page.id, {}).then(function(r) {
                            say(r.ok ? 'Approved.' : errorText(r, 'That did not work.'));
                            return load();
                        });
                    });
                });
                var saveApprove = smallButton('Save changes and approve', false);
                saveApprove.hidden = true;
                saveApprove.addEventListener('click', function() {
                    withButton(saveApprove, function() {
                        return api('POST', 'approve/' + page.id, { text: area.value }).then(function(r) {
                            say(r.ok ? 'Approved.' : errorText(r, 'That did not work.'));
                            return load();
                        });
                    });
                });
                area.addEventListener('input', function() {
                    saveApprove.hidden = false;
                });
                buttons.appendChild(approve);
                buttons.appendChild(document.createTextNode(' '));
                buttons.appendChild(saveApprove);
            }
            holder.appendChild(buttons);
            return holder;
        }

        function pageBlock(page) {
            var wrap = el('div');
            wrap.className = 'sm-ocr-box-page';
            wrap.style.marginBottom = '12px';
            if (page.status === 'empty') {
                wrap.appendChild(el('p', 'Page ' + page.page + ' — nothing readable on this page.'));
            } else if (page.status === 'failed') {
                wrap.appendChild(el('p', 'Page ' + page.page + ' — could not be read.'));
            } else if (page.status === 'queued') {
                wrap.appendChild(el('p', 'Page ' + page.page + ' — being read…'));
            } else if (page.review_status === 'approved') {
                var line = el('p', 'Page ' + page.page + ' — checked by ' + (page.reviewed_by_name || 'a reviewer') +
                    (page.reviewed_at ? ' on ' + page.reviewed_at.slice(0, 10) : '') +
                    (page.edited ? ' (corrected)' : '') + '. ');
                var show = el('a', 'Show text');
                show.href = '#';
                line.appendChild(show);
                wrap.appendChild(line);
                var editor = editorFor(page, true);
                editor.hidden = true;
                show.addEventListener('click', function(event) {
                    event.preventDefault();
                    editor.hidden = !editor.hidden;
                });
                wrap.appendChild(editor);
            } else {
                var head = el('p');
                head.appendChild(el('strong', 'Page ' + page.page));
                if (viewUrl) {
                    head.appendChild(document.createTextNode(' — '));
                    var open = el('a', 'Open this page');
                    open.href = viewUrl + '#page=' + page.page;
                    open.target = '_blank';
                    head.appendChild(open);
                }
                wrap.appendChild(head);
                wrap.appendChild(editorFor(page, false));
            }
            return wrap;
        }

        function render(data) {
            body.textContent = '';
            if (notice) {
                var flash = el('div');
                flash.className = 'notice notice-success inline';
                flash.appendChild(el('p', notice));
                body.appendChild(flash);
                notice = '';
            }
            var doc = data.doc;
            if (!doc) {
                watchWhileReading(false);
                body.appendChild(el('p', 'This document has not been read yet.'));
                var read = smallButton('Read this document', true);
                read.addEventListener('click', function() {
                    withButton(read, function() {
                        return api('POST', 'enqueue-post', { post_id: postId }).then(function(r) {
                            say(r.ok ? '' : errorText(r, 'That did not work.'));
                            return load();
                        });
                    });
                });
                var line = el('p');
                line.appendChild(read);
                line.appendChild(document.createTextNode(' '));
                var note = el('span', 'It goes to the front of the queue.');
                note.className = 'description';
                line.appendChild(note);
                body.appendChild(line);
                return;
            }
            if (doc.status === 'queued' || doc.status === 'working') {
                body.appendChild(el('p', 'Being read: ' + doc.pages_done + ' of ' +
                    (doc.total_pages || '?') + ' pages so far.'));
                watchWhileReading(true);
            } else {
                watchWhileReading(false);
            }
            if (doc.status === 'failed') {
                body.appendChild(el('p', 'This document could not be read. You can try again from the PDF Text page.'));
            }
            data.pages.forEach(function(page) {
                body.appendChild(pageBlock(page));
            });
            if (doc.pages_unchecked > 0) {
                var all = smallButton('Approve all ' + doc.pages_unchecked + ' unchecked pages of this document', false);
                all.addEventListener('click', function() {
                    withButton(all, function() {
                        return api('POST', 'approve-doc', { doc_key: key }).then(function(r) {
                            say(r.ok ? 'Approved.' : errorText(r, 'That did not work.'));
                            return load();
                        });
                    });
                });
                var allLine = el('p');
                allLine.appendChild(all);
                body.appendChild(allLine);
            }
            if (data.total_rows > data.per_page) {
                var nav = el('p');
                if (rowPage > 1) {
                    var earlier = el('a', 'Earlier pages');
                    earlier.href = '#';
                    earlier.addEventListener('click', function(event) {
                        event.preventDefault();
                        rowPage--;
                        load();
                    });
                    nav.appendChild(earlier);
                }
                if (rowPage * data.per_page < data.total_rows) {
                    if (nav.firstChild) {
                        nav.appendChild(document.createTextNode(' · '));
                    }
                    var later = el('a', 'Later pages');
                    later.href = '#';
                    later.addEventListener('click', function(event) {
                        event.preventDefault();
                        rowPage++;
                        load();
                    });
                    nav.appendChild(later);
                }
                body.appendChild(nav);
            }
        }

        load();
    }
})();
