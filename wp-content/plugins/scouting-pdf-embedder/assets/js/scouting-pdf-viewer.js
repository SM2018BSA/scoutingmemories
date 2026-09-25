(function() {
    'use strict';

    var config = window.ScoutingPdfConfig || {};

    // Page HTML cached before v2.0 carries the old settings, which point at the removed
    // PDF.js 2.0 files. Work out the bundled PDF.js from where this script was loaded.
    if (!config.libUrl && document.currentScript && document.currentScript.src) {
        var assets = document.currentScript.src.replace(/js\/scouting-pdf-viewer\.js(\?.*)?$/, '');
        var pdfjs = assets + 'vendor/pdfjs/';
        config.libUrl = pdfjs + 'pdf.min.js';
        config.workerUrl = pdfjs + 'pdf.worker.min.js';
        config.cMapUrl = pdfjs + 'cmaps/';
        config.standardFontDataUrl = pdfjs + 'standard_fonts/';
        config.wasmUrl = pdfjs + 'wasm/';
        config.iccUrl = pdfjs + 'iccs/';
    }
    var isHttps = (window.location.protocol === 'https:');
    if (isHttps) {
        ['libUrl', 'workerUrl', 'cMapUrl', 'standardFontDataUrl', 'wasmUrl', 'iccUrl', 'restUrl'].forEach(function(key) {
            if (config[key]) config[key] = config[key].replace(/^http:/i, 'https:');
        });
    }

    // PDF.js is an ES module; load it once, only when a viewer is about to open a document.
    // It parses and renders in a Web Worker, off the main thread.
    var libPromise = null;
    function loadLib() {
        if (!libPromise) {
            if (!config.libUrl) return Promise.reject(new Error('Scouting PDF: no PDF.js URL configured'));
            libPromise = import(config.libUrl).then(function(lib) {
                if (config.workerUrl) lib.GlobalWorkerOptions.workerSrc = config.workerUrl;
                return lib;
            });
            libPromise.catch(function() { libPromise = null; });
        }
        return libPromise;
    }

    // Largest canvas browsers reliably paint; bigger canvases silently render blank
    var MAX_CANVAS_PIXELS = 16777216;
    var MIN_SCALE = 0.1;
    // High enough to study fine detail on small artifact scans (patches, neckerchiefs, photos)
    var MAX_SCALE = 5.0;
    var ZOOM_STEP = 1.25;
    var PAGE_GAP = 16; // matches the 1rem gap between pages in the CSS
    var PRINT_DPI = 150;
    var SNAPSHOT_DPI = 300;
    var THUMB_WIDTH = 132;
    var RESUME_DAYS = 180;

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var MLA_MONTHS = ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May', 'June', 'July', 'Aug.', 'Sept.', 'Oct.', 'Nov.', 'Dec.'];

    var nativeFullscreen = !!(document.fullscreenEnabled || document.webkitFullscreenEnabled);

    function getFullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || null;
    }

    // User rotation is added to the page's own rotation
    function getPageViewport(page, scaleVal, rotation) {
        return page.getViewport({ scale: scaleVal, rotation: ((page.rotate || 0) + (rotation || 0)) % 360 });
    }

    function clamp(val, min, max) {
        return Math.max(min, Math.min(max, val));
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function escHtml(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function isTypingTarget(t) {
        return !!t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
    }

    function storageGet(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }

    function storageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) {}
    }

    function saveBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.rel = 'noopener';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function() { URL.revokeObjectURL(url); }, 30000);
    }

    function fileNameFromUrl(url) {
        var name = 'document.pdf';
        try {
            name = decodeURIComponent(new URL(url, window.location.href).pathname.split('/').pop()) || name;
        } catch (e) {}
        return name.replace(/[\\/:*?"<>|\x00-\x1f]+/g, '-');
    }

    function canvasToBlob(canvas, type) {
        return new Promise(function(resolve, reject) {
            canvas.toBlob(function(blob) {
                if (blob) resolve(blob); else reject(new Error('Could not create image'));
            }, type || 'image/png');
        });
    }

    // ---- Text normalization for search ---------------------------------------
    // Case-, accent- and ligature-insensitive ("ﬁ" finds "fi", "é" finds "e"), with runs
    // of whitespace and line breaks treated as one space.

    function normChar(c) {
        return c.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function normalizeQuery(q) {
        var out = '';
        var lastSpace = true;
        for (var i = 0; i < q.length; i++) {
            var c = q[i];
            if (/\s/.test(c)) {
                if (!lastSpace) out += ' ';
                lastSpace = true;
                continue;
            }
            out += normChar(c);
            lastSpace = false;
        }
        return out.trim();
    }

    // norm: searchable string. map[i]: [itemIndex, charOffset] of norm[i] (null for line
    // breaks). disp/dispPos: readable text for result snippets.
    function buildTextIndex(items) {
        var norm = '', map = [], disp = '', dispPos = [];
        var lastSpace = true;
        items.forEach(function(item, i) {
            var s = item.str || '';
            for (var k = 0; k < s.length; k++) {
                var c = s[k];
                if (/\s/.test(c)) {
                    if (!lastSpace) {
                        norm += ' ';
                        map.push([i, k]);
                        dispPos.push(disp.length);
                        disp += ' ';
                    }
                    lastSpace = true;
                    continue;
                }
                var n = normChar(c);
                for (var j = 0; j < n.length; j++) {
                    norm += n[j];
                    map.push([i, k]);
                    dispPos.push(disp.length);
                }
                disp += c;
                lastSpace = false;
            }
            if (item.hasEOL && !lastSpace) {
                norm += ' ';
                map.push(null);
                dispPos.push(disp.length);
                disp += ' ';
                lastSpace = true;
            }
        });
        return { norm: norm, map: map, disp: disp, dispPos: dispPos };
    }

    // ---- Citations ------------------------------------------------------------

    function formatLongDate(d) {
        return MONTHS[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    function formatMlaDate(d) {
        return d.getDate() + ' ' + MLA_MONTHS[d.getMonth()] + ' ' + d.getFullYear();
    }

    function endSentence(s) {
        return /[.?!]$/.test(s) ? s : s + '.';
    }

    function tidy(s) {
        return s.replace(/\s+/g, ' ').replace(/\s+([,.])/g, '$1').replace(/\.\./g, '.').trim();
    }

    /**
     * Citations for one page. info: { title, container, date, identifier, publisher, site,
     * url, page } where page is the printed page label (or null to leave it out).
     * Returns { chicago, mla, apa } each as { text, html } (html has the italics).
     */
    function buildCitations(info, now) {
        var pageText = info.page ? 'p. ' + info.page : '';
        var yearMatch = info.date ? String(info.date).match(/\b(1[5-9]\d\d|20\d\d)\b/) : null;
        var year = yearMatch ? yearMatch[1] : 'n.d.';
        var parts;

        function both(pieces) {
            return {
                text: tidy(pieces.map(function(p) { return typeof p === 'string' ? p : p.i; }).join('')),
                html: tidy(pieces.map(function(p) { return typeof p === 'string' ? escHtml(p) : '<i>' + escHtml(p.i) + '</i>'; }).join(''))
            };
        }

        // Chicago notes-bibliography style for an unpublished archival document
        parts = ['“' + info.title + ',” ' + (info.date || 'n.d.') + (pageText ? ', ' + pageText : '') + '. '];
        if (info.container) parts.push('In ', { i: info.container }, '. ');
        if (info.identifier) parts.push(endSentence(info.identifier) + ' ');
        if (info.publisher) parts.push('Digitized by ' + endSentence(info.publisher) + ' ');
        parts.push(endSentence(info.site) + ' ', info.url + ' (accessed ' + formatLongDate(now) + ').');
        var chicago = both(parts);

        // MLA 9
        parts = ['“' + endSentence(info.title) + '” '];
        if (info.container) parts.push({ i: info.container }, ', ');
        parts.push({ i: info.site }, ', ');
        if (info.date) parts.push(info.date + ', ');
        if (pageText) parts.push(pageText + ', ');
        parts.push(info.url + '. Accessed ' + formatMlaDate(now) + '.');
        var mla = both(parts);

        // APA 7
        var author = info.publisher || info.site;
        parts = [endSentence(author) + ' (' + year + '). ', { i: info.title }, ' [Archival document]'];
        if (pageText) parts.push(' (' + pageText + ')');
        parts.push('. ');
        if (info.publisher) parts.push(endSentence(info.site) + ' ');
        parts.push(info.url);
        var apa = both(parts);

        return { chicago: chicago, mla: mla, apa: apa };
    }

    // RIS works with Zotero, EndNote, Mendeley and most other reference managers
    function buildRis(info, pdfUrl, now) {
        var yearMatch = info.date ? String(info.date).match(/\b(1[5-9]\d\d|20\d\d)\b/) : null;
        var pad = function(n) { return (n < 10 ? '0' : '') + n; };
        var lines = [['TY', 'GEN'], ['TI', info.title]];
        if (info.container) lines.push(['T2', info.container]);
        if (yearMatch) lines.push(['PY', yearMatch[1]]);
        if (info.date) lines.push(['DA', info.date]);
        if (info.publisher) lines.push(['PB', info.publisher]);
        lines.push(['DB', info.site]);
        if (info.identifier) lines.push(['AN', info.identifier]);
        if (info.location) lines.push(['CY', info.location]);
        if (info.page) lines.push(['SP', info.page]);
        lines.push(['UR', info.url]);
        if (pdfUrl) lines.push(['L1', pdfUrl]);
        lines.push(['Y2', now.getFullYear() + '/' + pad(now.getMonth() + 1) + '/' + pad(now.getDate())]);
        lines.push(['ER', '']);
        return lines.map(function(l) {
            return l[0] + '  - ' + String(l[1]).replace(/[\r\n]+/g, ' ');
        }).join('\r\n') + '\r\n';
    }

    // ---- Deep links -------------------------------------------------------------
    // #page=12 opens the first document on the page at PDF page 12; #pdf2-page=12 the second.

    function pageHash(index, num) {
        return (index > 1 ? 'pdf' + index + '-' : '') + 'page=' + num;
    }

    function parseHash(hash) {
        var m = /^#(?:pdf(\d+)-)?page=(\d+)$/.exec(hash || '');
        if (!m) return null;
        return { index: m[1] ? parseInt(m[1], 10) : 1, page: parseInt(m[2], 10) };
    }

    // Bootstrap tooltips (from the theme's bootstrap.bundle) explain what each control does.
    // They're placed inside the viewer so they still show in fullscreen.
    function initTooltips(container) {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;
        var els = container.querySelectorAll('[data-bs-toggle="tooltip"]');
        for (var i = 0; i < els.length; i++) {
            (function(node) {
                var tip = window.bootstrap.Tooltip.getOrCreateInstance(node, {
                    container: container,
                    placement: 'bottom',
                    fallbackPlacements: ['bottom'],
                    trigger: 'hover focus',
                    delay: { show: 250, hide: 0 }
                });
                // Mouse and touch users have seen the hint by the time they press; don't let it
                // come back (via focus) and cover the page or the next row of buttons
                node.addEventListener('pointerdown', function() {
                    tip.hide();
                    tip.disable();
                });
                node.addEventListener('pointerleave', function() { tip.enable(); });
                node.addEventListener('blur', function() { tip.enable(); });
            })(els[i]);
        }
    }

    function initViewer(container, initialPage) {
        if (container.dataset.initialized === 'true') return container.scoutingPdf;
        container.dataset.initialized = 'true';

        var pdfUrl = container.getAttribute('data-pdf-url');
        if (!pdfUrl) return null;

        if (isHttps && pdfUrl.indexOf('http://') === 0) {
            pdfUrl = pdfUrl.replace(/^http:/i, 'https:');
        }

        var viewerIndex = parseInt(container.getAttribute('data-pdf-index'), 10) ||
            (Array.prototype.indexOf.call(document.querySelectorAll('[data-pdf-viewer], .scouting-pdf-container'), container) + 1) || 1;
        var allowDownload = container.getAttribute('data-pdf-download') !== '0';
        var cite = {};
        try { cite = JSON.parse(container.getAttribute('data-pdf-cite') || '{}') || {}; } catch (e) {}
        var startPage = initialPage || parseInt(container.getAttribute('data-pdf-start-page'), 10) || 1;

        function q(selector) {
            return container.querySelector(selector);
        }

        function control(name, legacyClass) {
            return q('[data-pdf-control="' + name + '"]' + (legacyClass ? ', .' + legacyClass : ''));
        }

        var loadingEl = q('[data-pdf-role="loading"], .scouting-pdf-loading');
        var viewportEl = q('[data-pdf-role="viewport"], .scouting-pdf-viewport');
        var pagesEl = q('[data-pdf-role="pages"]');
        var pageNumEl = control('page-input', 'scouting-pdf-page-input');
        var totalPagesEl = control('total-pages', 'scouting-pdf-total-pages');
        var pageLabelEl = control('page-label');
        var zoomLevelEl = control('zoom-level', 'scouting-pdf-zoom-level');
        var prevBtn = control('prev', 'scouting-pdf-prev');
        var nextBtn = control('next', 'scouting-pdf-next');
        var zoomInBtn = control('zoom-in', 'scouting-pdf-zoom-in');
        var zoomOutBtn = control('zoom-out', 'scouting-pdf-zoom-out');
        var zoomFitBtn = control('zoom-fit', 'scouting-pdf-zoom-fit');
        var zoomPageBtn = control('zoom-page');
        var rotateBtn = control('rotate');
        var fullscreenBtn = control('fullscreen', 'scouting-pdf-fullscreen');
        var sidebarBtn = control('sidebar');
        var searchBtn = control('search');
        var adjustBtn = control('adjust');
        var textSelectBtn = control('text-select');
        var citeBtn = control('cite');
        var downloadBtn = control('download');
        var moreBtn = control('more');
        var menuEl = q('[data-pdf-role="menu"]');
        var findbar = q('[data-pdf-role="findbar"]');
        var findInput = control('find-input');
        var findStatus = q('[data-pdf-role="find-status"]');
        var adjustbar = q('[data-pdf-role="adjustbar"]');
        var sidebar = q('[data-pdf-role="sidebar"]');
        var dialog = q('[data-pdf-role="dialog"]');
        var toasts = q('[data-pdf-role="toasts"]');

        var bottomPageNumEl = control('bottom-page-input');
        var bottomTotalPagesEl = control('bottom-total-pages');
        var bottomPageLabelEl = control('bottom-page-label');
        var bottomZoomLevelEl = control('bottom-zoom-level');
        var bottomPrevBtn = control('bottom-prev');
        var bottomNextBtn = control('bottom-next');
        var bottomZoomInBtn = control('bottom-zoom-in');
        var bottomZoomOutBtn = control('bottom-zoom-out');
        var bottomZoomFitBtn = control('bottom-zoom-fit');
        var bottomZoomPageBtn = control('bottom-zoom-page');
        var bottomScrollTopBtn = control('bottom-scroll-top');
        var bottomCiteBtn = control('bottom-cite');

        if (!viewportEl) return null;

        initTooltips(container);

        // Page HTML cached before v1.2 has a single canvas instead of the page column
        if (!pagesEl) {
            pagesEl = document.createElement('div');
            pagesEl.setAttribute('data-pdf-role', 'pages');
            viewportEl.appendChild(pagesEl);
        }
        var legacyWrapper = q('[data-pdf-role="canvas-wrapper"], .scouting-pdf-canvas-wrapper');
        if (legacyWrapper) legacyWrapper.style.display = 'none';
        if (downloadBtn && !allowDownload) downloadBtn.hidden = true;

        var pdfLib = null;
        var pdfDoc = null;
        var pages = [];          // { num, page, el, canvas, baseW, baseH, renderedKey, text, textLayer }
        var labels = null;       // printed page numbers, when the PDF defines them
        var outline = null;
        var currentPage = 1;
        var scale = 1.0;
        var rotation = 0;
        var fitMode = 'width';   // 'width' | 'page' | null (manual zoom)
        var spread = false;
        var textSelect = storageGet('scouting-pdf:text-select') === '1';
        var renderQueue = [];
        var rendering = false;
        var renderTimer = null;
        var firstRenderDone = false;
        var lastViewportWidth = 0;
        var readyCallbacks = [];
        var api = {
            index: viewerIndex,
            ready: false,
            goToPage: function(num) { whenReady(function() { goToPage(num, true); }); },
            goToLabel: function(label) { whenReady(function() { goToPage(pageForLabel(label), true); }); },
            labelFor: function(num) { return labelFor(num); },
            currentPage: function() { return currentPage; }
        };
        container.scoutingPdf = api;

        function whenReady(fn) {
            if (api.ready) fn(); else readyCallbacks.push(fn);
        }

        function setState(state) {
            container.setAttribute('data-pdf-state', state);
        }

        function isExpanded() {
            return getFullscreenElement() === container || container.classList.contains('is-expanded');
        }

        // ---- Page numbers -------------------------------------------------------

        function labelFor(num) {
            return (labels && labels[num - 1]) ? labels[num - 1] : String(num);
        }

        function hasDistinctLabel(num) {
            return !!(labels && labels[num - 1] && labels[num - 1] !== String(num));
        }

        function pageForLabel(label) {
            label = String(label).trim();
            if (labels) {
                var i = labels.indexOf(label);
                if (i === -1) i = labels.map(function(l) { return String(l).toLowerCase(); }).indexOf(label.toLowerCase());
                if (i !== -1) return i + 1;
            }
            var n = parseInt(label, 10);
            return isNaN(n) ? 1 : n;
        }

        // "p. iv (PDF page 5)" when the printed number differs, otherwise "p. 5"
        function describePage(num) {
            return 'p. ' + labelFor(num) + (hasDistinctLabel(num) ? ' (PDF page ' + num + ')' : '');
        }

        function pageLink(num) {
            var base = cite.permalink || window.location.href.split('#')[0];
            return base.split('#')[0] + '#' + pageHash(viewerIndex, num);
        }

        var resumeKey = 'scouting-pdf:last-page:' + pdfUrl;
        var resumeTimer = null;
        function rememberPage() {
            clearTimeout(resumeTimer);
            resumeTimer = setTimeout(function() {
                storageSet(resumeKey, JSON.stringify({ page: currentPage, t: Date.now() }));
            }, 800);
        }

        function updateUI() {
            var total = pdfDoc ? pdfDoc.numPages : 0;
            if (pageNumEl) {
                if (document.activeElement !== pageNumEl) pageNumEl.value = currentPage;
                if (total) pageNumEl.max = total;
            }
            if (bottomPageNumEl) {
                if (document.activeElement !== bottomPageNumEl) bottomPageNumEl.value = currentPage;
                if (total) bottomPageNumEl.max = total;
            }
            if (totalPagesEl && total) totalPagesEl.textContent = total;
            if (bottomTotalPagesEl && total) bottomTotalPagesEl.textContent = total;
            if (pageLabelEl) {
                pageLabelEl.hidden = !hasDistinctLabel(currentPage);
                pageLabelEl.textContent = 'p. ' + labelFor(currentPage);
            }
            if (bottomPageLabelEl) {
                bottomPageLabelEl.hidden = !hasDistinctLabel(currentPage);
                bottomPageLabelEl.textContent = 'p. ' + labelFor(currentPage);
            }
            if (zoomLevelEl) zoomLevelEl.textContent = Math.round(scale * 100) + '%';
            if (bottomZoomLevelEl) bottomZoomLevelEl.textContent = Math.round(scale * 100) + '%';
            if (prevBtn) prevBtn.disabled = (currentPage <= 1);
            if (bottomPrevBtn) bottomPrevBtn.disabled = (currentPage <= 1);
            if (nextBtn) nextBtn.disabled = (!total || currentPage >= total);
            if (bottomNextBtn) bottomNextBtn.disabled = (!total || currentPage >= total);
            if (zoomInBtn) zoomInBtn.disabled = (scale >= MAX_SCALE - 0.001);
            if (bottomZoomInBtn) bottomZoomInBtn.disabled = (scale >= MAX_SCALE - 0.001);
            if (zoomOutBtn) zoomOutBtn.disabled = (scale <= MIN_SCALE + 0.001);
            if (bottomZoomOutBtn) bottomZoomOutBtn.disabled = (scale <= MIN_SCALE + 0.001);
            setPressed(zoomFitBtn, fitMode === 'width');
            setPressed(bottomZoomFitBtn, fitMode === 'width');
            setPressed(zoomPageBtn, fitMode === 'page');
            setPressed(bottomZoomPageBtn, fitMode === 'page');
            updateThumbSelection();
            // Only once the reader is moving around, so opening a document doesn't
            // overwrite where they were last time
            if (total && api.ready) rememberPage();
        }

        // Bootstrap's .active shows the selected state in the site green
        function setPressed(btn, on) {
            if (!btn) return;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        }

        // ---- Layout -----------------------------------------------------------

        // Inner size of the reading area, excluding padding. Uses the max height (85vh) so
        // "fit page" is right even while the column is still short.
        function getAvailableSize() {
            var style = window.getComputedStyle(viewportEl);
            var padX = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
            var padY = (parseFloat(style.paddingTop) || 0) + (parseFloat(style.paddingBottom) || 0);
            var height = viewportEl.clientHeight;
            var maxH = parseFloat(style.maxHeight);
            if (!isExpanded() && isFinite(maxH) && maxH > 0) height = maxH;
            return { width: viewportEl.clientWidth - padX, height: height - padY };
        }

        function measureBase() {
            pages.forEach(function(p) {
                var vp = getPageViewport(p.page, 1, rotation);
                p.baseW = vp.width;
                p.baseH = vp.height;
            });
        }

        function layout() {
            pages.forEach(function(p) {
                p.el.style.width = Math.floor(p.baseW * scale) + 'px';
                p.el.style.height = Math.floor(p.baseH * scale) + 'px';
                p.el.style.setProperty('--total-scale-factor', scale);
            });
            if (!pages.length) return;
            // The column is as wide as the reading area or the usual page (or spread), so
            // ordinary pages stay centered. A wider fold-out starts at the left edge and
            // scrolls sideways, instead of pushing every other page off-center.
            var usual = Math.floor(typicalPageWidth() * scale) * (spread ? 2 : 1) + (spread ? PAGE_GAP : 0);
            var colW = Math.max(getAvailableSize().width, usual);
            pagesEl.style.width = colW + 'px';
            var items = spread ? Array.prototype.slice.call(pagesEl.children) : pages.map(function(p) { return p.el; });
            items.forEach(function(node) {
                var w = spread
                    ? Array.prototype.reduce.call(node.children, function(sum, c) { return sum + parseFloat(c.style.width); }, 0) + (node.children.length - 1) * PAGE_GAP
                    : parseFloat(node.style.width);
                node.style.alignSelf = w > colW ? 'flex-start' : '';
            });
        }

        // Pages sharing a row in two-page view: the first page alone (the cover), then pairs
        function rowOf(num) {
            if (!spread || num === 1) return [num];
            var left = num % 2 === 0 ? num : num - 1;
            return left + 1 <= pages.length ? [left, left + 1] : [left];
        }

        function arrangePages() {
            pagesEl.textContent = '';
            pagesEl.classList.toggle('is-spread', spread);
            if (!spread) {
                pages.forEach(function(p) { pagesEl.appendChild(p.el); });
                return;
            }
            var num = 1;
            while (num <= pages.length) {
                var row = el('div');
                row.setAttribute('data-pdf-role', 'spread-row');
                rowOf(num).forEach(function(n) { row.appendChild(pages[n - 1].el); });
                pagesEl.appendChild(row);
                num = rowOf(num).slice(-1)[0] + 1;
            }
        }

        function computeFitScale(mode) {
            var avail = getAvailableSize();
            if (!pages.length || avail.width <= 0) return scale;
            var fit;
            var width = typicalPageWidth() * (spread ? 2 : 1) + (spread ? PAGE_GAP : 0);
            if (mode === 'page') {
                var row = rowOf(currentPage).map(function(n) { return pages[n - 1]; });
                var rowW = row.reduce(function(sum, p) { return sum + p.baseW; }, 0) + (row.length - 1) * PAGE_GAP;
                var rowH = Math.max.apply(null, row.map(function(p) { return p.baseH; }));
                fit = Math.min(avail.width / rowW, avail.height / rowH);
            } else {
                fit = avail.width / width;
            }
            return clamp(fit, MIN_SCALE, MAX_SCALE);
        }

        // Most common page width, so one fold-out or landscape page doesn't shrink every
        // other page (the odd wide page scrolls sideways instead)
        function typicalPageWidth() {
            var counts = {};
            var best = pages[0].baseW;
            var bestCount = 0;
            pages.forEach(function(pg) {
                var w = Math.round(pg.baseW);
                counts[w] = (counts[w] || 0) + 1;
                if (counts[w] > bestCount) {
                    bestCount = counts[w];
                    best = pg.baseW;
                }
            });
            return best;
        }

        function pageIndexAtY(y) {
            for (var i = 0; i < pages.length; i++) {
                if (pages[i].el.offsetTop + pages[i].el.offsetHeight + PAGE_GAP / 2 > y) return i;
            }
            return pages.length - 1;
        }

        // Change zoom while keeping the point under (anchorX, anchorY) — or the center of the
        // reading area — in place, so the reader doesn't lose their spot
        function setScale(newScale, anchorX, anchorY) {
            newScale = clamp(newScale, MIN_SCALE, MAX_SCALE);
            if (!pages.length || Math.abs(newScale - scale) < 0.001) {
                scale = newScale;
                updateUI();
                return;
            }
            var rect = viewportEl.getBoundingClientRect();
            var ax = (anchorX === undefined) ? viewportEl.clientWidth / 2 : anchorX - rect.left - viewportEl.clientLeft;
            var ay = (anchorY === undefined) ? viewportEl.clientHeight / 2 : anchorY - rect.top - viewportEl.clientTop;

            var contentY = viewportEl.scrollTop + ay;
            var p = pages[pageIndexAtY(contentY)];
            var fracY = (contentY - p.el.offsetTop) / p.el.offsetHeight;
            var fracX = (viewportEl.scrollLeft + ax - p.el.offsetLeft) / p.el.offsetWidth;

            scale = newScale;
            layout();

            viewportEl.scrollTop = p.el.offsetTop + fracY * p.el.offsetHeight - ay;
            viewportEl.scrollLeft = p.el.offsetLeft + fracX * p.el.offsetWidth - ax;

            updateUI();
            scheduleRender();
        }

        function applyFit() {
            if (!fitMode || !pages.length) return;
            // Twice: the first pass can add or remove the scrollbar, which changes the width
            for (var i = 0; i < 2; i++) {
                var fit = computeFitScale(fitMode);
                if (Math.abs(fit - scale) < 0.002) break;
                setScale(fit);
            }
            if (fitMode === 'page') goToPage(currentPage, true);
            updateUI();
        }

        // ---- Navigation -------------------------------------------------------

        function goToPage(num, force, offsetY) {
            if (!pages.length) return;
            num = clamp(num, 1, pages.length);
            if (num === currentPage && !force) return;
            currentPage = num;
            var top = pages[num - 1].el.offsetTop;
            viewportEl.scrollTop = Math.max(0, offsetY !== undefined ? top + offsetY : top - PAGE_GAP / 2);
            updateUI();
            scheduleRender();
        }

        // In two-page view, previous/next move a whole spread
        function stepPage(dir) {
            if (!spread) {
                goToPage(currentPage + dir);
                return;
            }
            var row = rowOf(currentPage);
            goToPage(dir > 0 ? row[row.length - 1] + 1 : row[0] - 1);
        }

        // Current page = the one covering the upper third of the reading area
        function updateCurrentFromScroll() {
            if (!pages.length) return;
            var atBottom = viewportEl.scrollTop + viewportEl.clientHeight >= viewportEl.scrollHeight - 2;
            var num = atBottom && viewportEl.scrollTop > 0
                ? pages.length
                : pageIndexAtY(viewportEl.scrollTop + viewportEl.clientHeight / 3) + 1;
            if (num !== currentPage) {
                currentPage = num;
                updateUI();
            }
        }

        // ---- Rendering --------------------------------------------------------

        function renderKey() {
            return scale.toFixed(4) + '|' + rotation;
        }

        function scheduleRender() {
            if (renderTimer) return;
            renderTimer = setTimeout(function() {
                renderTimer = null;
                refreshVisiblePages();
            }, 80);
        }

        function releasePage(p) {
            if (p.canvas && p.canvas.parentNode) p.canvas.parentNode.removeChild(p.canvas);
            p.canvas = null;
            p.renderedKey = null;
            removeTextLayer(p);
        }

        // Render pages in and around view (nearest first); free canvases far away so long
        // documents (70+ pages of scans) don't exhaust memory
        function refreshVisiblePages() {
            if (!pages.length) return;
            var top = viewportEl.scrollTop;
            var h = viewportEl.clientHeight || 600;
            var key = renderKey();
            var wanted = [];

            pages.forEach(function(p) {
                var pTop = p.el.offsetTop;
                var pBottom = pTop + p.el.offsetHeight;
                if (pBottom >= top - h && pTop <= top + 2 * h) {
                    if (p.renderedKey !== key) wanted.push(p);
                    else if (p.textLayerKey !== rotation) buildTextLayer(p);
                } else if ((pBottom < top - 3 * h || pTop > top + 4 * h) && p.canvas) {
                    releasePage(p);
                }
            });

            var center = top + h / 2;
            wanted.sort(function(a, b) {
                return Math.abs(a.el.offsetTop + a.el.offsetHeight / 2 - center) -
                       Math.abs(b.el.offsetTop + b.el.offsetHeight / 2 - center);
            });
            renderQueue = wanted;
            renderNext();
        }

        function renderNext() {
            if (rendering || !renderQueue.length) return;
            var p = renderQueue.shift();
            var key = renderKey();
            if (p.renderedKey === key) {
                renderNext();
                return;
            }
            rendering = true;

            var viewport = getPageViewport(p.page, scale, rotation);
            var dpr = window.devicePixelRatio || 1;
            var pixels = viewport.width * viewport.height * dpr * dpr;
            if (pixels > MAX_CANVAS_PIXELS) {
                dpr = Math.max(0.5, Math.sqrt(MAX_CANVAS_PIXELS / (viewport.width * viewport.height)));
            }

            // Draw off-screen and swap in when done, so the previous (stretched) image stays
            // visible during zoom instead of flashing blank
            var canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width * dpr);
            canvas.height = Math.floor(viewport.height * dpr);

            function done() {
                rendering = false;
                if (!firstRenderDone) {
                    firstRenderDone = true;
                    if (loadingEl) {
                        loadingEl.style.display = 'none';
                        loadingEl.classList.add('hidden');
                    }
                    pagesEl.style.visibility = '';
                    setState('ready');
                    // The column is final now; settle the fit against the real scrollbar,
                    // then open at the requested page (or the top of page 1)
                    applyFit();
                    viewportEl.scrollTop = 0;
                    viewportEl.scrollLeft = 0;
                    lastViewportWidth = viewportEl.clientWidth;
                    if (startPage > 1) goToPage(startPage, true);
                    else offerResume();
                    api.ready = true;
                    readyCallbacks.splice(0).forEach(function(fn) { fn(); });
                    document.dispatchEvent(new CustomEvent('scouting-pdf:ready', { detail: { index: viewerIndex } }));
                }
                if (p.renderedKey !== renderKey()) scheduleRender();
                renderNext();
            }

            var task = p.page.render({
                canvas: canvas,
                viewport: viewport,
                transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
            });
            task.promise.then(function() {
                if (p.canvas && p.canvas.parentNode) p.canvas.parentNode.removeChild(p.canvas);
                p.el.insertBefore(canvas, p.el.firstChild);
                p.canvas = canvas;
                p.renderedKey = key;
                if (p.textLayerKey !== rotation) buildTextLayer(p);
                done();
            }).catch(function(err) {
                console.error('Scouting PDF: render error on page ' + p.num, err);
                done();
            });
        }

        // ---- Text layer: invisible, selectable text over each page ------------

        function getText(p) {
            if (!p.text) {
                p.text = p.page.getTextContent().then(function(tc) {
                    p.index = buildTextIndex(tc.items);
                    return tc;
                });
                p.text.catch(function() { p.text = null; });
            }
            return p.text;
        }

        function removeTextLayer(p) {
            if (p.textLayer) p.textLayer.cancel();
            if (p.textLayerDiv && p.textLayerDiv.parentNode) p.textLayerDiv.parentNode.removeChild(p.textLayerDiv);
            p.textLayer = null;
            p.textLayerDiv = null;
            p.textLayerKey = null;
            p.highlighted = [];
        }

        function buildTextLayer(p) {
            if (!pdfLib || !pdfLib.TextLayer || p.textLayerKey === rotation || p.textLayerPending === rotation) return;
            var rot = rotation;
            p.textLayerPending = rot;
            getText(p).then(function(tc) {
                p.textLayerPending = null;
                if (rotation !== rot || !p.canvas || !tc.items.length) return;
                removeTextLayer(p);
                var div = el('div', 'textLayer');
                // Scale 1: the layer follows zoom through --total-scale-factor on the page
                var vp = getPageViewport(p.page, 1, rot);
                var layer = new pdfLib.TextLayer({ textContentSource: tc, container: div, viewport: vp });
                var dims = vp.rawDims;
                div.style.width = 'calc(var(--total-scale-factor) * ' + dims.pageWidth + 'px)';
                div.style.height = 'calc(var(--total-scale-factor) * ' + dims.pageHeight + 'px)';
                p.el.appendChild(div);
                p.textLayer = layer;
                p.textLayerDiv = div;
                p.textLayerKey = rot;
                return layer.render().then(function() {
                    if (p.textLayer === layer) highlightPage(p);
                });
            }).catch(function(err) {
                p.textLayerPending = null;
                if (err && err.name !== 'AbortException') console.warn('Scouting PDF: text layer failed on page ' + p.num, err);
            });
        }

        function setTextSelect(on) {
            textSelect = on;
            container.classList.toggle('is-text-select', on);
            setPressed(textSelectBtn, on);
            storageSet('scouting-pdf:text-select', on ? '1' : '0');
        }

        // ---- Search -------------------------------------------------------------

        var search = { query: '', matches: [], current: -1, token: 0, pendingScroll: false, timer: null };

        function indexAllPages(onProgress) {
            var done = 0;
            // One page at a time keeps the worker responsive for rendering
            return pages.reduce(function(chain, p) {
                return chain.then(function() {
                    return getText(p).then(function() {
                        done++;
                        if (onProgress) onProgress(done);
                    }, function() { done++; });
                });
            }, Promise.resolve());
        }

        function findMatches(p, needle) {
            var idx = p.index;
            var found = [];
            if (!idx || !needle) return found;
            var from = 0;
            var at;
            while ((at = idx.norm.indexOf(needle, from)) !== -1) {
                var end = at + needle.length;
                var ranges = [];
                for (var i = at; i < end; i++) {
                    var m = idx.map[i];
                    if (!m) continue;
                    var last = ranges[ranges.length - 1];
                    if (last && last.item === m[0]) last.end = m[1] + 1;
                    else ranges.push({ item: m[0], begin: m[1], end: m[1] + 1 });
                }
                var ds = idx.dispPos[at];
                var de = idx.dispPos[end - 1] + 1;
                found.push({
                    page: p.num,
                    ranges: ranges,
                    before: (ds > 60 ? '…' : '') + idx.disp.slice(Math.max(0, ds - 60), ds),
                    text: idx.disp.slice(ds, de),
                    after: idx.disp.slice(de, de + 60) + (de + 60 < idx.disp.length ? '…' : '')
                });
                from = end;
            }
            return found;
        }

        function setFindStatus(text) {
            if (findStatus) findStatus.textContent = text;
        }

        function runSearch(query) {
            var needle = normalizeQuery(query);
            var token = ++search.token;
            search.query = query;
            search.matches = [];
            search.current = -1;
            refreshHighlights();
            if (!needle) {
                setFindStatus('');
                renderResults();
                return Promise.resolve();
            }
            setFindStatus('Searching…');
            return indexAllPages(function(n) {
                if (token === search.token && pages.length > 5) setFindStatus('Searching… ' + n + ' of ' + pages.length + ' pages');
            }).then(function() {
                if (token !== search.token) return;
                var hasText = pages.some(function(p) { return p.index && p.index.norm.trim().length; });
                pages.forEach(function(p) {
                    search.matches = search.matches.concat(findMatches(p, needle));
                });
                renderResults();
                if (!hasText) {
                    setFindStatus('This document has no searchable text. It is a scanned image that hasn’t been converted to text (OCR).');
                    return;
                }
                if (!search.matches.length) {
                    setFindStatus('No matches');
                    return;
                }
                // Start at the first match on or after the page being read
                var first = 0;
                for (var i = 0; i < search.matches.length; i++) {
                    if (search.matches[i].page >= currentPage) { first = i; break; }
                }
                selectMatch(first);
            });
        }

        function selectMatch(i) {
            if (!search.matches.length) return;
            search.current = (i + search.matches.length) % search.matches.length;
            var m = search.matches[search.current];
            setFindStatus((search.current + 1) + ' of ' + search.matches.length + ' matches · ' + describePage(m.page));
            search.pendingScroll = true;
            if (currentPage !== m.page || !pages[m.page - 1].textLayer) goToPage(m.page, true);
            refreshHighlights();
            updateResultSelection();
        }

        function refreshHighlights() {
            pages.forEach(function(p) { if (p.textLayer) highlightPage(p); });
        }

        // Wrap each match in the text layer in a highlight span; the current one is "selected"
        function highlightPage(p) {
            var layer = p.textLayer;
            if (!layer) return;
            var divs = layer.textDivs;
            var strs = layer.textContentItemsStr;
            (p.highlighted || []).forEach(function(i) {
                if (divs[i]) divs[i].textContent = strs[i];
            });
            p.highlighted = [];
            var byDiv = {};
            search.matches.forEach(function(m, mi) {
                if (m.page !== p.num) return;
                m.ranges.forEach(function(r) {
                    (byDiv[r.item] = byDiv[r.item] || []).push({ b: r.begin, e: r.end, selected: mi === search.current });
                });
            });
            var selectedSpan = null;
            Object.keys(byDiv).forEach(function(key) {
                var i = parseInt(key, 10);
                var div = divs[i];
                var s = strs[i];
                if (!div || s === undefined) return;
                var parts = byDiv[key].sort(function(a, b) { return a.b - b.b; });
                div.textContent = '';
                var pos = 0;
                parts.forEach(function(r) {
                    if (r.b < pos) return;
                    if (r.b > pos) div.appendChild(document.createTextNode(s.slice(pos, r.b)));
                    var span = el('span', 'highlight' + (r.selected ? ' selected' : ''), s.slice(r.b, r.e));
                    div.appendChild(span);
                    if (r.selected && !selectedSpan) selectedSpan = span;
                    pos = r.e;
                });
                if (pos < s.length) div.appendChild(document.createTextNode(s.slice(pos)));
                p.highlighted.push(i);
            });
            if (selectedSpan && search.pendingScroll) {
                search.pendingScroll = false;
                scrollIntoViewport(selectedSpan);
            }
        }

        // Bring an element inside the pages into view without scrolling the whole web page
        function scrollIntoViewport(node) {
            var vr = viewportEl.getBoundingClientRect();
            var r = node.getBoundingClientRect();
            if (r.top < vr.top + 40 || r.bottom > vr.bottom - 40) {
                viewportEl.scrollTop += r.top - vr.top - viewportEl.clientHeight / 3;
            }
            if (r.left < vr.left || r.right > vr.right) {
                viewportEl.scrollLeft += r.left - vr.left - viewportEl.clientWidth / 3;
            }
        }

        function openFindbar() {
            if (!findbar) return;
            closeMenu();
            findbar.hidden = false;
            setPressed(searchBtn, true);
            findInput.focus();
            findInput.select();
            onViewerResized();
        }

        function closeFindbar() {
            if (!findbar || findbar.hidden) return;
            findbar.hidden = true;
            setPressed(searchBtn, false);
            search.token++;
            search.query = '';
            search.matches = [];
            search.current = -1;
            refreshHighlights();
            renderResults();
            if (findInput) findInput.value = '';
            setFindStatus('');
            container.focus({ preventScroll: true });
            onViewerResized();
        }

        if (findbar && findInput) {
            findbar.addEventListener('submit', function(e) {
                e.preventDefault();
                if (normalizeQuery(findInput.value) !== normalizeQuery(search.query)) {
                    clearTimeout(search.timer);
                    runSearch(findInput.value);
                } else {
                    selectMatch(search.current + 1);
                }
            });
            findInput.addEventListener('input', function() {
                clearTimeout(search.timer);
                search.timer = setTimeout(function() { runSearch(findInput.value); }, 300);
            });
            findInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.shiftKey) {
                    e.preventDefault();
                    selectMatch(search.current - 1);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    closeFindbar();
                }
            });
            var findPrev = control('find-prev');
            var findClose = control('find-close');
            var findList = control('find-list');
            if (findPrev) findPrev.addEventListener('click', function() { selectMatch(search.current - 1); });
            if (findClose) findClose.addEventListener('click', closeFindbar);
            if (findList) findList.addEventListener('click', function() { openSidebar('results'); });
        }

        // ---- Sidebar: thumbnails, contents, matches, details --------------------

        var sidebarTab = 'thumbs';
        var thumbsBuilt = false;
        var thumbObserver = null;
        var thumbButtons = [];

        function openSidebar(tab) {
            if (!sidebar) return;
            if (sidebar.hidden) {
                sidebar.hidden = false;
                setPressed(sidebarBtn, true);
                onViewerResized();
            }
            showTab(tab || sidebarTab);
        }

        function closeSidebar() {
            if (!sidebar || sidebar.hidden) return;
            sidebar.hidden = true;
            setPressed(sidebarBtn, false);
            onViewerResized();
        }

        function showTab(tab) {
            if (!sidebar) return;
            sidebarTab = tab;
            var tabs = sidebar.querySelectorAll('[data-pdf-tab]');
            for (var i = 0; i < tabs.length; i++) {
                var on = tabs[i].getAttribute('data-pdf-tab') === tab;
                tabs[i].classList.toggle('active', on);
                tabs[i].setAttribute('aria-selected', on ? 'true' : 'false');
            }
            var panels = sidebar.querySelectorAll('[data-pdf-panel]');
            for (var j = 0; j < panels.length; j++) {
                panels[j].hidden = panels[j].getAttribute('data-pdf-panel') !== tab;
            }
            if (tab === 'thumbs') buildThumbs();
            if (tab === 'info') buildInfo();
            if (tab === 'results') updateResultSelection();
        }

        function panel(name) {
            return sidebar ? sidebar.querySelector('[data-pdf-panel="' + name + '"]') : null;
        }

        function buildThumbs() {
            var host = panel('thumbs');
            if (thumbsBuilt || !host || !pages.length) {
                updateThumbSelection();
                return;
            }
            thumbsBuilt = true;
            host.textContent = '';
            var list = el('div', 'd-flex flex-column align-items-center gap-3 p-3');
            pages.forEach(function(p) {
                var btn = el('button', 'btn btn-link p-0 text-decoration-none d-flex flex-column align-items-center gap-1');
                btn.type = 'button';
                btn.setAttribute('data-pdf-thumb', String(p.num));
                btn.setAttribute('aria-label', 'Go to ' + describePage(p.num));
                var box = el('span', 'scouting-pdf-thumb');
                box.style.width = THUMB_WIDTH + 'px';
                box.style.height = Math.round(THUMB_WIDTH * p.baseH / p.baseW) + 'px';
                btn.appendChild(box);
                btn.appendChild(el('span', 'small text-muted', labelFor(p.num)));
                btn.addEventListener('click', function() {
                    goToPage(p.num, true);
                    if (window.matchMedia('(max-width: 767.98px)').matches) closeSidebar();
                });
                list.appendChild(btn);
                thumbButtons[p.num - 1] = btn;
            });
            host.appendChild(list);

            var draw = function(btn) {
                var p = pages[parseInt(btn.getAttribute('data-pdf-thumb'), 10) - 1];
                var box = btn.firstChild;
                if (box.firstChild) return;
                var dpr = window.devicePixelRatio || 1;
                var vp = getPageViewport(p.page, THUMB_WIDTH / p.baseW, rotation);
                var canvas = document.createElement('canvas');
                canvas.width = Math.floor(vp.width * dpr);
                canvas.height = Math.floor(vp.height * dpr);
                p.page.render({ canvas: canvas, viewport: vp, transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null }).promise.then(function() {
                    box.appendChild(canvas);
                }).catch(function() {});
            };
            if ('IntersectionObserver' in window) {
                thumbObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            thumbObserver.unobserve(entry.target);
                            draw(entry.target);
                        }
                    });
                }, { root: sidebar, rootMargin: '300px 0px' });
                thumbButtons.forEach(function(b) { thumbObserver.observe(b); });
            } else {
                thumbButtons.forEach(draw);
            }
            updateThumbSelection();
        }

        function resetThumbs() {
            if (thumbObserver) thumbObserver.disconnect();
            thumbsBuilt = false;
            thumbButtons = [];
            if (sidebar && !sidebar.hidden && sidebarTab === 'thumbs') buildThumbs();
        }

        function updateThumbSelection() {
            if (!thumbButtons.length) return;
            thumbButtons.forEach(function(b, i) {
                var on = i + 1 === currentPage;
                b.classList.toggle('is-current', on);
                if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
            });
            var btn = thumbButtons[currentPage - 1];
            if (btn && sidebar && !sidebar.hidden && sidebarTab === 'thumbs') {
                // Scroll the sidebar only, never the web page
                var sr = sidebar.getBoundingClientRect();
                var br = btn.getBoundingClientRect();
                if (br.top < sr.top + 40 || br.bottom > sr.bottom) {
                    sidebar.scrollTop += br.top - sr.top - 60;
                }
            }
        }

        function buildOutline() {
            var host = panel('outline');
            var tab = sidebar ? sidebar.querySelector('[data-pdf-tab="outline"]') : null;
            if (!host || !outline || !outline.length) return;
            if (tab) tab.hidden = false;
            host.textContent = '';
            var render = function(items, depth) {
                var ul = el('ul', 'list-unstyled mb-0' + (depth ? ' ps-3' : ' p-2'));
                items.forEach(function(item) {
                    var li = el('li');
                    var node;
                    if (item.url && /^https?:\/\//i.test(item.url)) {
                        node = el('a', 'd-block py-1 px-2 small', item.title);
                        node.href = item.url;
                        node.target = '_blank';
                        node.rel = 'noopener noreferrer nofollow';
                    } else {
                        node = el('button', 'btn btn-link btn-sm text-start d-block w-100 py-1 px-2 text-decoration-none', item.title);
                        node.type = 'button';
                        node.addEventListener('click', function() { goToDest(item.dest); });
                    }
                    li.appendChild(node);
                    if (item.items && item.items.length) li.appendChild(render(item.items, depth + 1));
                    ul.appendChild(li);
                });
                return ul;
            };
            host.appendChild(render(outline, 0));
        }

        // Jump to an outline destination, to the exact spot on the page when it has one
        function goToDest(dest) {
            var destPromise = typeof dest === 'string' ? pdfDoc.getDestination(dest) : Promise.resolve(dest);
            destPromise.then(function(explicit) {
                if (!Array.isArray(explicit)) return;
                var ref = explicit[0];
                var indexPromise = (typeof ref === 'object' && ref !== null) ? pdfDoc.getPageIndex(ref) : Promise.resolve(ref);
                return indexPromise.then(function(index) {
                    var num = index + 1;
                    var p = pages[num - 1];
                    if (!p) return;
                    var offset;
                    if (explicit[1] && explicit[1].name === 'XYZ' && typeof explicit[3] === 'number') {
                        var vp = getPageViewport(p.page, scale, rotation);
                        var pt = vp.convertToViewportPoint(explicit[2] || 0, explicit[3]);
                        offset = Math.max(0, pt[1] - 8);
                    }
                    goToPage(num, true, offset);
                });
            }).catch(function(err) {
                console.warn('Scouting PDF: could not follow outline link', err);
            });
        }

        function renderResults() {
            var host = panel('results');
            if (!host) return;
            host.textContent = '';
            if (!search.matches.length) {
                host.appendChild(el('p', 'small text-muted p-3 mb-0', search.query ? (findStatus ? findStatus.textContent : 'No matches') : 'Search the document to list every match here.'));
                return;
            }
            host.appendChild(el('p', 'small text-muted px-3 pt-3 mb-2', search.matches.length + ' matches for “' + search.query.trim() + '”'));
            var list = el('div', 'list-group list-group-flush small');
            search.matches.forEach(function(m, i) {
                var btn = el('button', 'list-group-item list-group-item-action');
                btn.type = 'button';
                btn.setAttribute('data-pdf-result', String(i));
                btn.appendChild(el('div', 'fw-semibold', describePage(m.page)));
                var snip = el('div', 'text-muted');
                snip.appendChild(document.createTextNode(m.before));
                snip.appendChild(el('mark', '', m.text));
                snip.appendChild(document.createTextNode(m.after));
                btn.appendChild(snip);
                btn.addEventListener('click', function() { selectMatch(i); });
                list.appendChild(btn);
            });
            host.appendChild(list);
            updateResultSelection();
        }

        function updateResultSelection() {
            var host = panel('results');
            if (!host) return;
            var items = host.querySelectorAll('[data-pdf-result]');
            for (var i = 0; i < items.length; i++) {
                items[i].classList.toggle('active', i === search.current);
            }
            var cur = items[search.current];
            if (cur && sidebar && !sidebar.hidden && sidebarTab === 'results') {
                var sr = sidebar.getBoundingClientRect();
                var br = cur.getBoundingClientRect();
                if (br.top < sr.top + 40 || br.bottom > sr.bottom) sidebar.scrollTop += br.top - sr.top - 80;
            }
        }

        var infoBuilt = false;
        function buildInfo() {
            var host = panel('info');
            if (infoBuilt || !host || !pdfDoc) return;
            infoBuilt = true;
            host.textContent = '';
            host.appendChild(el('p', 'small text-muted p-3 mb-0', 'Loading details…'));

            Promise.all([
                pdfDoc.getMetadata().catch(function() { return null; }),
                pdfDoc.getDownloadInfo().catch(function() { return null; })
            ]).then(function(res) {
                var meta = res[0] || {};
                var info = meta.info || {};
                var size = res[1] && res[1].length;
                var rows = [];
                var add = function(label, value) {
                    if (value !== undefined && value !== null && String(value).trim() !== '') rows.push([label, String(value).trim()]);
                };
                var date = function(v) {
                    var d = v && pdfLib.PDFDateString ? pdfLib.PDFDateString.toDateObject(v) : null;
                    return d ? formatLongDate(d) : v;
                };

                // What the archive recorded about the original
                add('Identifier', cite.identifier);
                add('Date of original', cite.dateOriginal);
                add('Location', cite.location);
                add('Physical description', cite.physicalDescription);
                add('Digitized by', cite.publisher);
                add('Date digitized', cite.dateDigital);
                var archiveCount = rows.length;

                // What the PDF file says about itself
                add('Title in file', info.Title);
                add('Author in file', info.Author);
                add('Subject', info.Subject);
                add('Keywords', info.Keywords);
                add('File created', date(info.CreationDate));
                add('File modified', date(info.ModDate));
                add('Made with', [info.Creator, info.Producer].filter(Boolean).join(' / '));
                add('PDF version', info.PDFFormatVersion);
                add('Pages', pdfDoc.numPages + (labels ? ' (printed numbers ' + labels[0] + '–' + labels[labels.length - 1] + ')' : ''));
                if (pages[0]) {
                    var vp = getPageViewport(pages[0].page, 1, 0);
                    var w = vp.width / 72;
                    var h = vp.height / 72;
                    add('Page size', w.toFixed(2) + ' × ' + h.toFixed(2) + ' in (' + (w * 2.54).toFixed(1) + ' × ' + (h * 2.54).toFixed(1) + ' cm)');
                }
                if (size) add('File size', size > 1048576 ? (size / 1048576).toFixed(1) + ' MB' : Math.round(size / 1024) + ' KB');
                add('File name', fileNameFromUrl(pdfUrl));

                host.textContent = '';
                var dl = el('dl', 'small p-3 mb-0 scouting-pdf-info');
                rows.forEach(function(row, i) {
                    if (i === archiveCount && archiveCount) dl.appendChild(el('hr'));
                    dl.appendChild(el('dt', '', row[0]));
                    dl.appendChild(el('dd', '', row[1]));
                });
                host.appendChild(dl);
            });
        }

        if (sidebar) {
            var tabButtons = sidebar.querySelectorAll('[data-pdf-tab]');
            for (var t = 0; t < tabButtons.length; t++) {
                (function(btn) {
                    btn.addEventListener('click', function() { showTab(btn.getAttribute('data-pdf-tab')); });
                })(tabButtons[t]);
            }
        }

        // ---- Menu, dialogs and messages -------------------------------------------

        function openMenu() {
            if (!menuEl) return;
            menuEl.hidden = false;
            if (moreBtn) moreBtn.setAttribute('aria-expanded', 'true');
            var first = menuEl.querySelector('[role^="menuitem"]:not([hidden])');
            if (first) first.focus();
        }

        function closeMenu() {
            if (!menuEl || menuEl.hidden) return false;
            menuEl.hidden = true;
            if (moreBtn) moreBtn.setAttribute('aria-expanded', 'false');
            return true;
        }

        if (moreBtn && menuEl) {
            moreBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (menuEl.hidden) openMenu(); else closeMenu();
            });
            menuEl.addEventListener('keydown', function(e) {
                var items = Array.prototype.filter.call(menuEl.querySelectorAll('[role^="menuitem"]'), function(i) { return !i.hidden; });
                var at = items.indexOf(document.activeElement);
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    items[(at + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    closeMenu();
                    moreBtn.focus();
                }
            });
            menuEl.addEventListener('click', function(e) {
                if (e.target.closest('[role^="menuitem"]')) closeMenu();
            });
            document.addEventListener('click', function(e) {
                if (!menuEl.hidden && !menuEl.contains(e.target) && e.target !== moreBtn) closeMenu();
            });
        }

        var dialogOpener = null;
        function openDialog(title, body) {
            if (!dialog) return;
            closeMenu();
            dialogOpener = document.activeElement;
            q('[data-pdf-role="dialog-title"]').textContent = title;
            var host = q('[data-pdf-role="dialog-body"]');
            host.textContent = '';
            host.appendChild(body);
            dialog.hidden = false;
            var focusable = dialog.querySelector('input, button:not([data-pdf-control="dialog-close"]), textarea');
            (focusable || dialog.querySelector('button')).focus();
        }

        function closeDialog() {
            if (!dialog || dialog.hidden) return false;
            dialog.hidden = true;
            if (dialogOpener && dialogOpener.focus && container.contains(dialogOpener)) dialogOpener.focus();
            else container.focus({ preventScroll: true });
            return true;
        }

        if (dialog) {
            var closeBtn = control('dialog-close');
            if (closeBtn) closeBtn.addEventListener('click', closeDialog);
        }

        function toast(message, actionLabel, action, timeout) {
            if (!toasts) return;
            var t = el('div', 'toast show align-items-center');
            t.setAttribute('role', 'status');
            var row = el('div', 'd-flex align-items-center gap-2 p-2');
            row.appendChild(el('div', 'toast-body p-1 small', message));
            if (actionLabel) {
                var btn = el('button', 'btn btn-sm btn-sm-green', actionLabel);
                btn.type = 'button';
                btn.addEventListener('click', function() { action(); t.remove(); });
                row.appendChild(btn);
            }
            var x = el('button', 'btn-close ms-auto');
            x.type = 'button';
            x.setAttribute('aria-label', 'Dismiss');
            x.addEventListener('click', function() { t.remove(); });
            row.appendChild(x);
            t.appendChild(row);
            toasts.appendChild(t);
            setTimeout(function() { t.remove(); }, timeout || 5000);
        }

        function copyText(text, html) {
            var fallback = function() {
                return new Promise(function(resolve, reject) {
                    var ta = el('textarea', 'scouting-pdf-copy-buffer');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    container.appendChild(ta);
                    ta.select();
                    var ok = false;
                    try { ok = document.execCommand('copy'); } catch (e) {}
                    ta.remove();
                    if (ok) resolve(); else reject(new Error('copy failed'));
                });
            };
            if (!navigator.clipboard || !window.isSecureContext) return fallback();
            var plain = function() { return navigator.clipboard.writeText(text).catch(fallback); };
            if (html && window.ClipboardItem) {
                try {
                    return navigator.clipboard.write([new ClipboardItem({
                        'text/plain': new Blob([text], { type: 'text/plain' }),
                        'text/html': new Blob([html], { type: 'text/html' })
                    })]).catch(plain);
                } catch (e) {}
            }
            return plain();
        }

        function copyWithFeedback(text, html, what) {
            copyText(text, html).then(function() {
                toast(what + ' copied');
            }, function() {
                toast('Couldn’t copy automatically. Select the text and copy it instead.');
            });
        }

        // ---- Cite, link, download, print ------------------------------------------

        function citationInfo(num, includePage) {
            var docTitle = cite.title || cite.postTitle || container.getAttribute('data-pdf-title') || fileNameFromUrl(pdfUrl);
            return {
                title: docTitle,
                container: (cite.postTitle && cite.postTitle !== docTitle) ? cite.postTitle : '',
                date: cite.dateOriginal || '',
                identifier: cite.identifier || '',
                publisher: cite.publisher || '',
                location: cite.location || '',
                site: cite.site || config.siteName || window.location.hostname,
                url: includePage ? pageLink(num) : (cite.permalink || window.location.href.split('#')[0]),
                page: includePage ? labelFor(num) : null
            };
        }

        var currentCiteStyle = storageGet('scouting-pdf-cite-style') || 'chicago';

        function openCite() {
            var num = currentPage;
            var body = el('div');
            var intro = el('p', 'mb-2');
            intro.appendChild(document.createTextNode('Citing '));
            intro.appendChild(el('strong', '', describePage(num)));
            intro.appendChild(document.createTextNode('. Check the details against your style guide before you publish.'));
            body.appendChild(intro);

            var pageSwitch = el('div', 'form-check mb-3');
            var cb = el('input', 'form-check-input');
            cb.type = 'checkbox';
            cb.checked = true;
            cb.id = container.id + '-cite-page';
            var lab = el('label', 'form-check-label', 'Include the page number and a link straight to this page');
            lab.htmlFor = cb.id;
            pageSwitch.appendChild(cb);
            pageSwitch.appendChild(lab);
            body.appendChild(pageSwitch);

            var styleWrap = el('div', 'mb-3');
            var selectLabel = el('label', 'form-label small fw-bold text-muted mb-1', 'Citation style');
            selectLabel.htmlFor = container.id + '-cite-style';
            var styleSelect = el('select', 'form-select form-select-sm');
            styleSelect.id = container.id + '-cite-style';
            styleSelect.setAttribute('data-pdf-control', 'cite-style-select');

            var styles = [
                ['chicago', 'Chicago (Notes & Bibliography) — Most Popular'],
                ['mla', 'MLA (Modern Language Association)'],
                ['apa', 'APA (American Psychological Association)']
            ];

            styles.forEach(function(s) {
                var opt = el('option', '', s[1]);
                opt.value = s[0];
                if (s[0] === currentCiteStyle) opt.selected = true;
                styleSelect.appendChild(opt);
            });
            styleWrap.appendChild(selectLabel);
            styleWrap.appendChild(styleSelect);
            body.appendChild(styleWrap);

            var list = el('div', 'd-flex flex-column gap-3');
            body.appendChild(list);

            var draw = function() {
                var info = citationInfo(num, cb.checked);
                var cites = buildCitations(info, new Date());
                list.textContent = '';
                styles.forEach(function(s) {
                    var styleKey = s[0];
                    var styleLabel = s[1].split(' —')[0];
                    var c = cites[styleKey];
                    var box = el('div', 'scouting-pdf-citation');
                    box.setAttribute('data-pdf-citation', styleKey);
                    if (styleKey !== currentCiteStyle) {
                        box.hidden = true;
                    }
                    var head = el('div', 'd-flex align-items-center justify-content-between mb-1');
                    head.appendChild(el('strong', '', styleLabel));
                    var copy = el('button', 'btn btn-sm btn-outline-secondary', 'Copy');
                    copy.type = 'button';
                    copy.setAttribute('aria-label', 'Copy ' + styleLabel + ' citation');
                    copy.addEventListener('click', function() { copyWithFeedback(c.text, c.html, styleLabel + ' citation'); });
                    head.appendChild(copy);
                    box.appendChild(head);
                    var text = el('div', 'border rounded p-2 bg-body-tertiary user-select-all');
                    // Built from escaped pieces in buildCitations; only <i> tags are markup
                    text.innerHTML = c.html;
                    box.appendChild(text);
                    list.appendChild(box);
                });

                var actions = el('div', 'd-flex flex-wrap gap-2 pt-2 border-top');
                var ris = el('button', 'btn btn-sm btn-outline-secondary', 'Download for Zotero / EndNote (.ris)');
                ris.type = 'button';
                ris.setAttribute('data-pdf-control', 'cite-ris');
                ris.addEventListener('click', function() {
                    var name = fileNameFromUrl(pdfUrl).replace(/\.pdf$/i, '') + (info.page ? '-p' + info.page : '') + '.ris';
                    saveBlob(new Blob([buildRis(info, pdfUrl, new Date())], { type: 'application/x-research-info-systems' }), name);
                });
                var link = el('button', 'btn btn-sm btn-outline-secondary', 'Copy link to this page');
                link.type = 'button';
                link.addEventListener('click', function() { copyWithFeedback(pageLink(num), null, 'Link'); });

                var areaBtn = el('button', 'btn btn-sm btn-outline-secondary d-flex align-items-center gap-1');
                areaBtn.type = 'button';
                areaBtn.innerHTML = '<i class="bi bi-camera" aria-hidden="true"></i> Select area to cite & share';
                areaBtn.setAttribute('data-bs-toggle', 'tooltip');
                areaBtn.setAttribute('data-bs-title', 'Select an area of this page to share as an image with embedded link and citation');
                areaBtn.addEventListener('click', function() {
                    closeDialog();
                    startSnapshot();
                });

                actions.appendChild(ris);
                actions.appendChild(link);
                actions.appendChild(areaBtn);
                list.appendChild(actions);
            };

            styleSelect.addEventListener('change', function() {
                currentCiteStyle = styleSelect.value;
                storageSet('scouting-pdf-cite-style', currentCiteStyle);
                var boxes = list.querySelectorAll('[data-pdf-citation]');
                Array.prototype.forEach.call(boxes, function(box) {
                    box.hidden = (box.getAttribute('data-pdf-citation') !== currentCiteStyle);
                });
            });

            cb.addEventListener('change', draw);
            draw();
            openDialog('Cite this document', body);
        }

        function copyPageLink() {
            copyWithFeedback(pageLink(currentPage), null, 'Link to ' + describePage(currentPage));
        }

        function downloadPdf() {
            if (!pdfDoc || !allowDownload) return;
            // The bytes already loaded, so it works even when storage blocks direct downloads
            pdfDoc.getData().then(function(data) {
                saveBlob(new Blob([data], { type: 'application/pdf' }), fileNameFromUrl(pdfUrl));
            }).catch(function(err) {
                console.error('Scouting PDF: download failed', err);
                toast('The download didn’t work. Please try again.');
            });
        }

        // "1-3, 7" -> [1, 2, 3, 7]
        function parseRanges(text, total) {
            var out = [];
            String(text).split(',').forEach(function(part) {
                var m = /^\s*(\d+)\s*(?:-\s*(\d+)\s*)?$/.exec(part);
                if (!m) return;
                var a = parseInt(m[1], 10);
                var b = m[2] ? parseInt(m[2], 10) : a;
                if (a > b) { var tmp = a; a = b; b = tmp; }
                for (var n = Math.max(1, a); n <= Math.min(total, b); n++) {
                    if (out.indexOf(n) === -1) out.push(n);
                }
            });
            return out;
        }

        function openPrint() {
            if (!allowDownload) return;
            var body = el('form', 'd-flex flex-column gap-2');
            var choices = [['current', 'This page (' + describePage(currentPage) + ')'], ['all', 'All ' + pages.length + ' pages'], ['range', 'Pages:']];
            var rangeInput = el('input', 'form-control form-control-sm d-inline-block w-auto ms-2');
            rangeInput.type = 'text';
            rangeInput.placeholder = 'e.g. 1-3, 7';
            rangeInput.setAttribute('aria-label', 'PDF pages to print');
            choices.forEach(function(c, i) {
                var wrap = el('div', 'form-check d-flex align-items-center');
                var r = el('input', 'form-check-input me-2');
                r.type = 'radio';
                r.name = container.id + '-print-range';
                r.value = c[0];
                r.id = container.id + '-print-' + c[0];
                r.checked = i === 0;
                var l = el('label', 'form-check-label', c[1]);
                l.htmlFor = r.id;
                wrap.appendChild(r);
                wrap.appendChild(l);
                if (c[0] === 'range') {
                    wrap.appendChild(rangeInput);
                    rangeInput.addEventListener('focus', function() { r.checked = true; });
                }
                body.appendChild(wrap);
            });
            if (filterValue() !== 'none') body.appendChild(el('p', 'text-muted mb-0', 'Your brightness and contrast settings will be used for the printout.'));
            var status = el('p', 'text-muted mb-0');
            status.setAttribute('role', 'status');
            var go = el('button', 'btn btn-sm btn-sm-green align-self-start', 'Print');
            go.type = 'submit';
            body.appendChild(status);
            body.appendChild(go);
            body.addEventListener('submit', function(e) {
                e.preventDefault();
                var mode = body.querySelector('input[type="radio"]:checked').value;
                var list = mode === 'all' ? pages.map(function(p) { return p.num; })
                    : mode === 'range' ? parseRanges(rangeInput.value, pages.length) : [currentPage];
                if (!list.length) {
                    status.textContent = 'Enter the pages to print, like 1-3, 7';
                    rangeInput.focus();
                    return;
                }
                go.disabled = true;
                printPages(list, function(n) {
                    status.textContent = 'Preparing page ' + n + ' of ' + list.length + '…';
                }).then(function() {
                    closeDialog();
                }, function(err) {
                    console.error('Scouting PDF: print failed', err);
                    status.textContent = 'Printing didn’t work. Please try again.';
                    go.disabled = false;
                });
            });
            openDialog('Print', body);
        }

        // Draw each page into an image at print resolution, then print only those images
        function printPages(list, onProgress) {
            var urls = [];
            var holder = el('div');
            holder.id = 'scouting-pdf-print';
            var filter = filterValue();
            return list.reduce(function(chain, num, i) {
                return chain.then(function() {
                    if (onProgress) onProgress(i + 1);
                    var p = pages[num - 1];
                    var vp = getPageViewport(p.page, PRINT_DPI / 72, rotation);
                    var canvas = document.createElement('canvas');
                    canvas.width = Math.floor(vp.width);
                    canvas.height = Math.floor(vp.height);
                    return p.page.render({ canvas: canvas, viewport: vp, intent: 'print' }).promise.then(function() {
                        return canvasToBlob(applyFilterToCanvas(canvas, filter));
                    }).then(function(blob) {
                        var url = URL.createObjectURL(blob);
                        urls.push(url);
                        var img = el('img');
                        img.src = url;
                        img.alt = describePage(num);
                        img.setAttribute('data-pdf-print-page', String(num));
                        holder.appendChild(img);
                    });
                });
            }, Promise.resolve()).then(function() {
                var old = document.getElementById('scouting-pdf-print');
                if (old) old.remove();
                document.body.appendChild(holder);
                document.body.classList.add('scouting-pdf-printing');
                var cleaned = false;
                var cleanup = function() {
                    if (cleaned) return;
                    cleaned = true;
                    window.removeEventListener('afterprint', cleanup);
                    document.body.classList.remove('scouting-pdf-printing');
                    holder.remove();
                    urls.forEach(function(u) { URL.revokeObjectURL(u); });
                };
                window.addEventListener('afterprint', cleanup);
                var imgs = holder.querySelectorAll('img');
                return Promise.all(Array.prototype.map.call(imgs, function(img) {
                    return img.decode ? img.decode().catch(function() {}) : Promise.resolve();
                })).then(function() {
                    window.print();
                });
            });
        }

        // ---- Image adjustments for faded scans --------------------------------------

        function filterValue() {
            if (!adjustbar) return 'none';
            var b = control('brightness');
            var c = control('contrast');
            var g = control('grayscale');
            var inv = control('invert');
            var parts = [];
            if (b && b.value !== '100') parts.push('brightness(' + (b.value / 100) + ')');
            if (c && c.value !== '100') parts.push('contrast(' + (c.value / 100) + ')');
            if (g && g.checked) parts.push('grayscale(1)');
            if (inv && inv.checked) parts.push('invert(1)');
            return parts.length ? parts.join(' ') : 'none';
        }

        function applyAdjustments() {
            var f = filterValue();
            container.style.setProperty('--pdf-page-filter', f);
            setPressed(adjustBtn, !adjustbar.hidden || f !== 'none');
        }

        // Copy a canvas with the reader's adjustments baked in (for prints and pictures)
        function applyFilterToCanvas(canvas, filter) {
            if (!filter || filter === 'none') return canvas;
            var out = document.createElement('canvas');
            out.width = canvas.width;
            out.height = canvas.height;
            var ctx = out.getContext('2d');
            if (!('filter' in ctx)) return canvas;
            ctx.filter = filter;
            ctx.drawImage(canvas, 0, 0);
            return out;
        }

        if (adjustbar) {
            ['brightness', 'contrast', 'grayscale', 'invert'].forEach(function(name) {
                var input = control(name);
                if (input) input.addEventListener('input', applyAdjustments);
                if (input) input.addEventListener('change', applyAdjustments);
            });
            var resetBtn = control('adjust-reset');
            if (resetBtn) resetBtn.addEventListener('click', function() {
                control('brightness').value = 100;
                control('contrast').value = 100;
                control('grayscale').checked = false;
                control('invert').checked = false;
                applyAdjustments();
            });
            var adjustClose = control('adjust-close');
            if (adjustClose) adjustClose.addEventListener('click', function() { toggleAdjust(false); });
        }

        function toggleAdjust(on) {
            if (!adjustbar) return;
            adjustbar.hidden = !on;
            applyAdjustments();
            if (on) {
                var first = control('brightness');
                if (first) first.focus();
            }
            onViewerResized();
        }

        // ---- Save an area as a picture ------------------------------------------------

        var snap = null;

        function startSnapshot() {
            if (!pages.length) return;
            cancelSnapshot();
            snap = { active: true };
            container.classList.add('is-snapshot');
            toast('Drag across a page to choose the area to save. Press Esc to cancel.', null, null, 6000);
        }

        function cancelSnapshot() {
            if (!snap) return false;
            if (snap.box) snap.box.remove();
            snap = null;
            container.classList.remove('is-snapshot');
            return true;
        }

        function snapshotPointerDown(e) {
            var pageEl = e.target.closest('[data-pdf-page]');
            if (!pageEl || !pagesEl.contains(pageEl)) return;
            e.preventDefault();
            var r = pageEl.getBoundingClientRect();
            snap.pageEl = pageEl;
            snap.x0 = e.clientX - r.left;
            snap.y0 = e.clientY - r.top;
            snap.box = el('div', 'scouting-pdf-snap-box');
            pageEl.appendChild(snap.box);
            snap.pointerId = e.pointerId;
            if (viewportEl.setPointerCapture) viewportEl.setPointerCapture(e.pointerId);
            snapshotPointerMove(e);
        }

        function snapRect(e) {
            var r = snap.pageEl.getBoundingClientRect();
            var x1 = clamp(e.clientX - r.left, 0, r.width);
            var y1 = clamp(e.clientY - r.top, 0, r.height);
            var x0 = clamp(snap.x0, 0, r.width);
            var y0 = clamp(snap.y0, 0, r.height);
            return { x: Math.min(x0, x1), y: Math.min(y0, y1), w: Math.abs(x1 - x0), h: Math.abs(y1 - y0) };
        }

        function snapshotPointerMove(e) {
            if (!snap || !snap.box) return;
            var rect = snapRect(e);
            snap.rect = rect;
            snap.box.style.left = rect.x + 'px';
            snap.box.style.top = rect.y + 'px';
            snap.box.style.width = rect.w + 'px';
            snap.box.style.height = rect.h + 'px';
        }

        function snapshotPointerUp(e) {
            if (!snap || !snap.box) return;
            snapshotPointerMove(e);
            var rect = snap.rect;
            var num = parseInt(snap.pageEl.getAttribute('data-pdf-page'), 10);
            if (viewportEl.releasePointerCapture) {
                try { viewportEl.releasePointerCapture(snap.pointerId); } catch (err) {}
            }
            cancelSnapshot();
            if (!rect || rect.w < 8 || rect.h < 8) {
                toast('That area is too small. Choose "Save an area as a picture" and drag across the part you want.');
                return;
            }
            saveSnapshot(num, rect).catch(function(err) {
                console.error('Scouting PDF: snapshot failed', err);
                toast('The picture couldn’t be made. Please try again.');
            });
        }

        // Render just the chosen area at high resolution with the citation and embedded link underneath
        function saveSnapshot(num, rect) {
            var p = pages[num - 1];
            var target = SNAPSHOT_DPI / 72;
            var factor = target / scale;
            var w = rect.w * factor;
            var h = rect.h * factor;
            if (w * h > MAX_CANVAS_PIXELS / 2) {
                factor *= Math.sqrt((MAX_CANVAS_PIXELS / 2) / (w * h));
                w = rect.w * factor;
                h = rect.h * factor;
            }
            var renderScale = scale * factor;
            var vp = getPageViewport(p.page, renderScale, rotation);
            var area = document.createElement('canvas');
            area.width = Math.max(1, Math.round(w));
            area.height = Math.max(1, Math.round(h));
            return p.page.render({
                canvas: area,
                viewport: vp,
                transform: [1, 0, 0, 1, -rect.x * factor, -rect.y * factor]
            }).promise.then(function() {
                var img = applyFilterToCanvas(area, filterValue());
                var info = citationInfo(num, true);
                var fontSize = Math.max(14, Math.round(img.width / 52));
                var pad = Math.round(fontSize * 0.9);
                var measure = document.createElement('canvas').getContext('2d');
                measure.font = fontSize + 'px sans-serif';

                var titleText = info.title + ' — ' + describePage(num) + ' (' + info.site + ')';
                var linkText = '🔗 ' + info.url;

                // Wrap title text if wider than image
                var lines = [];
                var line = '';
                titleText.split(' ').forEach(function(word) {
                    var test = line ? line + ' ' + word : word;
                    if (line && measure.measureText(test).width > img.width - pad * 2) {
                        lines.push(line);
                        line = word;
                    } else {
                        line = test;
                    }
                });
                if (line) lines.push(line);

                var capH = (lines.length + 1) * Math.round(fontSize * 1.4) + pad * 2.2;
                var out = document.createElement('canvas');
                out.width = img.width;
                out.height = img.height + capH;
                var ctx = out.getContext('2d');

                // White footer background
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, out.width, out.height);

                // Draw clipped page region
                ctx.drawImage(img, 0, 0);

                // Divider line in Scouting Green
                ctx.fillStyle = '#025600';
                ctx.fillRect(0, img.height, out.width, Math.max(3, Math.round(fontSize * 0.2)));

                // Document attribution lines
                ctx.fillStyle = '#1c1e21';
                ctx.font = 'bold ' + fontSize + 'px sans-serif';
                ctx.textBaseline = 'top';
                var curY = img.height + pad;
                lines.forEach(function(l) {
                    ctx.fillText(l, pad, curY);
                    curY += Math.round(fontSize * 1.4);
                });

                // Embedded link visibly on the image
                ctx.fillStyle = '#025600';
                ctx.font = '500 ' + Math.max(12, Math.round(fontSize * 0.88)) + 'px monospace, sans-serif';
                ctx.fillText(linkText, pad, curY);

                return canvasToBlob(out).then(function(blob) {
                    var name = fileNameFromUrl(pdfUrl).replace(/\.pdf$/i, '') + '-p' + labelFor(num) + '.png';
                    saveBlob(blob, name);
                    openSnapshotDialog(blob, out, num, rect, info, name);
                });
            });
        }

        function openSnapshotDialog(blob, canvas, num, rect, info, name) {
            var body = el('div', 'scouting-pdf-snapshot-dialog');
            
            var previewWrap = el('div', 'text-center p-2 mb-3 bg-light border rounded overflow-hidden');
            var previewImg = el('img', 'img-fluid rounded shadow-sm');
            previewImg.style.maxHeight = '240px';
            previewImg.style.objectFit = 'contain';
            var blobUrl = URL.createObjectURL(blob);
            previewImg.src = blobUrl;
            previewImg.alt = info.title + ' — ' + describePage(num);
            previewWrap.appendChild(previewImg);
            body.appendChild(previewWrap);

            var note = el('p', 'small text-muted mb-3');
            note.innerHTML = 'Image captured from <strong>' + escHtml(describePage(num)) + '</strong> with the citation and link embedded directly on the image.';
            body.appendChild(note);

            var actionsRow = el('div', 'd-flex flex-wrap gap-2 mb-3');

            if (navigator.share) {
                var shareBtn = el('button', 'btn btn-sm btn-sm-green d-flex align-items-center gap-1');
                shareBtn.type = 'button';
                shareBtn.innerHTML = '<i class="bi bi-share" aria-hidden="true"></i> Share image…';
                shareBtn.addEventListener('click', function() {
                    var file = new File([blob], name, { type: 'image/png' });
                    var shareData = {
                        title: info.title + ' (' + describePage(num) + ')',
                        text: info.title + ', ' + describePage(num) + ' — Scouting Memories: ' + info.url,
                        url: info.url
                    };
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        shareData.files = [file];
                    }
                    navigator.share(shareData).catch(function() {});
                });
                actionsRow.appendChild(shareBtn);
            }

            var copyImgBtn = el('button', 'btn btn-sm btn-outline-secondary d-flex align-items-center gap-1');
            copyImgBtn.type = 'button';
            copyImgBtn.innerHTML = '<i class="bi bi-clipboard" aria-hidden="true"></i> Copy image';
            copyImgBtn.addEventListener('click', function() {
                if (navigator.clipboard && window.ClipboardItem) {
                    navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]).then(function() {
                        toast('Image copied to clipboard! (Link: ' + info.url + ')');
                    }).catch(function() {
                        toast('Direct clipboard image copying not permitted by browser. Use Download instead.');
                    });
                } else {
                    toast('Clipboard image copying is not supported in this browser. Use Download instead.');
                }
            });
            actionsRow.appendChild(copyImgBtn);

            var copyLinkBtn = el('button', 'btn btn-sm btn-outline-secondary d-flex align-items-center gap-1');
            copyLinkBtn.type = 'button';
            copyLinkBtn.innerHTML = '<i class="bi bi-link-45deg" aria-hidden="true"></i> Copy link';
            copyLinkBtn.addEventListener('click', function() {
                copyWithFeedback(info.url, null, 'Link');
            });
            actionsRow.appendChild(copyLinkBtn);

            var downloadBtn = el('button', 'btn btn-sm btn-outline-secondary d-flex align-items-center gap-1');
            downloadBtn.type = 'button';
            downloadBtn.innerHTML = '<i class="bi bi-download" aria-hidden="true"></i> Download PNG';
            downloadBtn.addEventListener('click', function() {
                saveBlob(blob, name);
                toast('Picture saved with embedded citation and link');
            });
            actionsRow.appendChild(downloadBtn);

            body.appendChild(actionsRow);

            var embedBox = el('div', 'card bg-body-tertiary border mb-2');
            var embedHeader = el('div', 'card-header py-1 px-3 d-flex align-items-center justify-content-between');
            embedHeader.appendChild(el('span', 'small fw-bold text-muted', 'Embed code (HTML link with image)'));
            
            var copyEmbedBtn = el('button', 'btn btn-sm btn-link p-0 text-decoration-none small', 'Copy HTML');
            embedHeader.appendChild(copyEmbedBtn);
            embedBox.appendChild(embedHeader);

            var embedBody = el('div', 'card-body p-2');
            var htmlCode = '<a href="' + escHtml(info.url) + '" target="_blank" rel="noopener">\n  <img src="' + escHtml(name) + '" alt="' + escHtml(info.title + ' - ' + describePage(num)) + '">\n</a>';
            var codePre = el('pre', 'bg-body p-2 border rounded small user-select-all mb-0', htmlCode);
            codePre.style.whiteSpace = 'pre-wrap';
            codePre.style.wordBreak = 'break-all';
            codePre.style.fontSize = '0.8rem';
            embedBody.appendChild(codePre);
            embedBox.appendChild(embedBody);

            copyEmbedBtn.addEventListener('click', function() {
                copyWithFeedback(htmlCode, null, 'Embed HTML code');
            });

            body.appendChild(embedBox);

            openDialog('Share & Cite Clipped Image', body);
        }

        // ---- Other controls ---------------------------------------------------------

        function zoomBy(factor, anchorX, anchorY) {
            fitMode = null;
            setScale(scale * factor, anchorX, anchorY);
        }

        function setFitMode(mode) {
            fitMode = mode;
            applyFit();
        }

        function rotate() {
            if (!pages.length) return;
            rotation = (rotation + 90) % 360;
            pages.forEach(releasePage);
            measureBase();
            layout();
            applyFit();
            goToPage(currentPage, true);
            resetThumbs();
        }

        function toggleSpread() {
            if (!pages.length) return;
            var keep = currentPage;
            spread = !spread;
            var item = control('spread');
            if (item) item.setAttribute('aria-checked', spread ? 'true' : 'false');
            if (item) item.classList.toggle('active', spread);
            arrangePages();
            if (!fitMode) fitMode = 'width';
            layout();
            applyFit();
            goToPage(keep, true);
        }

        // CSS-only "expanded" mode for browsers without the Fullscreen API on elements (iPhone)
        function setExpanded(on) {
            container.classList.toggle('is-expanded', on);
            document.body.classList.toggle('scouting-pdf-lock', on);
            onViewerResized();
        }

        function toggleFullscreen() {
            if (!nativeFullscreen) {
                setExpanded(!container.classList.contains('is-expanded'));
                return;
            }
            if (!getFullscreenElement()) {
                if (container.requestFullscreen) {
                    container.requestFullscreen();
                } else if (container.webkitRequestFullscreen) {
                    container.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
            }
        }

        function onViewerResized() {
            if (!pages.length) return;
            layout();
            if (fitMode) applyFit();
            lastViewportWidth = viewportEl.clientWidth;
            scheduleRender();
        }

        function onPageInput(e) {
            var val = parseInt(e.target.value, 10);
            if (!isNaN(val) && pdfDoc && val >= 1 && val <= pdfDoc.numPages) {
                goToPage(val, true);
            } else {
                e.target.value = currentPage;
            }
        }

        // The theme's comment form: start a comment that points at this page
        var commentBox = document.querySelector('#commentform textarea[name="comment"], #commentform #comment');
        var commentItem = control('comment-page');
        if (commentItem && commentBox && viewerIndex === 1) commentItem.hidden = false;
        function commentOnPage() {
            if (!commentBox) return;
            var ref = '[p. ' + labelFor(currentPage) + '] ';
            commentBox.value = commentBox.value ? commentBox.value.replace(/\s*$/, '\n') + ref : ref;
            if (isExpanded()) {
                if (getFullscreenElement()) toggleFullscreen(); else setExpanded(false);
            }
            commentBox.scrollIntoView({ block: 'center' });
            commentBox.focus();
            commentBox.setSelectionRange(commentBox.value.length, commentBox.value.length);
        }

        function offerResume() {
            var saved = null;
            try { saved = JSON.parse(storageGet(resumeKey) || 'null'); } catch (e) {}
            if (!saved || !saved.page || saved.page <= 1 || saved.page > pages.length || pages.length < 3) return;
            if (Date.now() - (saved.t || 0) > RESUME_DAYS * 86400000) return;
            var page = saved.page;
            toast('You were reading ' + describePage(page) + '.', 'Continue there', function() { goToPage(page, true); }, 10000);
        }

        var handlers = {
            prev: function() { stepPage(-1); },
            next: function() { stepPage(1); },
            'bottom-prev': function() { stepPage(-1); },
            'bottom-next': function() { stepPage(1); },
            'zoom-in': function() { zoomBy(ZOOM_STEP); },
            'zoom-out': function() { zoomBy(1 / ZOOM_STEP); },
            'bottom-zoom-in': function() { zoomBy(ZOOM_STEP); },
            'bottom-zoom-out': function() { zoomBy(1 / ZOOM_STEP); },
            'zoom-fit': function() { setFitMode('width'); },
            'zoom-page': function() { setFitMode('page'); },
            'bottom-zoom-fit': function() { setFitMode('width'); },
            'bottom-zoom-page': function() { setFitMode('page'); },
            rotate: rotate,
            fullscreen: toggleFullscreen,
            sidebar: function() { if (sidebar && sidebar.hidden) openSidebar(); else closeSidebar(); },
            search: function() { if (findbar && !findbar.hidden) closeFindbar(); else openFindbar(); },
            adjust: function() { toggleAdjust(adjustbar && adjustbar.hidden); },
            'text-select': function() { setTextSelect(!textSelect); },
            cite: openCite,
            'bottom-cite': openCite,
            'bottom-scroll-top': function() { viewportEl.scrollTo({ top: 0, behavior: 'smooth' }); },
            download: downloadPdf,
            'copy-link': copyPageLink,
            'comment-page': commentOnPage,
            print: openPrint,
            snapshot: startSnapshot,
            spread: toggleSpread,
            info: function() { openSidebar('info'); }
        };
        Object.keys(handlers).forEach(function(name) {
            var btn = control(name, name === 'prev' || name === 'next' ? 'scouting-pdf-' + name : null);
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (!pdfDoc && name !== 'fullscreen') return;
                handlers[name]();
            });
        });
        document.addEventListener('fullscreenchange', onViewerResized);
        document.addEventListener('webkitfullscreenchange', onViewerResized);

        if (pageNumEl) {
            pageNumEl.addEventListener('change', onPageInput);
            pageNumEl.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') onPageInput(e);
            });
        }
        if (bottomPageNumEl) {
            bottomPageNumEl.addEventListener('change', onPageInput);
            bottomPageNumEl.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') onPageInput(e);
            });
        }

        viewportEl.addEventListener('scroll', function() {
            updateCurrentFromScroll();
            scheduleRender();
        }, { passive: true });

        // Ctrl + scroll wheel (and trackpad pinch, which browsers report the same way)
        // zooms at the pointer instead of zooming the whole web page
        viewportEl.addEventListener('wheel', function(e) {
            if (!e.ctrlKey || !pages.length) return;
            e.preventDefault();
            var dy = e.deltaMode === 1 ? e.deltaY * 33 : e.deltaY;
            dy = clamp(dy, -100, 100);
            zoomBy(Math.exp(-dy * 0.002), e.clientX, e.clientY);
        }, { passive: false });

        // Click and drag to move around a page (mouse only; touch scrolls natively).
        // In text mode dragging selects text instead; in picture mode it chooses an area.
        var pan = null;
        viewportEl.addEventListener('pointerdown', function(e) {
            if (e.pointerType !== 'mouse' || e.button !== 0 || !pages.length) return;
            if (snap) {
                snapshotPointerDown(e);
                return;
            }
            if (textSelect) return;
            var rect = viewportEl.getBoundingClientRect();
            // Leave the scrollbars alone
            if (e.clientX >= rect.left + viewportEl.clientLeft + viewportEl.clientWidth ||
                e.clientY >= rect.top + viewportEl.clientTop + viewportEl.clientHeight) return;
            e.preventDefault();
            container.focus({ preventScroll: true });
            pan = { x: e.clientX, y: e.clientY, left: viewportEl.scrollLeft, top: viewportEl.scrollTop, id: e.pointerId };
            if (viewportEl.setPointerCapture) viewportEl.setPointerCapture(e.pointerId);
            viewportEl.classList.add('is-panning');
        });
        viewportEl.addEventListener('pointermove', function(e) {
            if (snap && snap.box) {
                snapshotPointerMove(e);
                return;
            }
            if (!pan || e.pointerId !== pan.id) return;
            viewportEl.scrollLeft = pan.left - (e.clientX - pan.x);
            viewportEl.scrollTop = pan.top - (e.clientY - pan.y);
        });
        function endPan(e) {
            if (snap && snap.box) {
                snapshotPointerUp(e);
                return;
            }
            if (!pan || (e && e.pointerId !== pan.id)) return;
            if (viewportEl.releasePointerCapture) {
                try { viewportEl.releasePointerCapture(pan.id); } catch (err) {}
            }
            pan = null;
            viewportEl.classList.remove('is-panning');
        }
        viewportEl.addEventListener('pointerup', endPan);
        viewportEl.addEventListener('pointercancel', endPan);

        // Keyboard shortcuts while the viewer has focus
        container.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'f' || e.key === 'F') && findbar && pdfDoc) {
                e.preventDefault();
                openFindbar();
                return;
            }
            if (e.key === 'Escape') {
                if (closeMenu() || closeDialog() || cancelSnapshot()) {
                    e.preventDefault();
                    return;
                }
                if (findbar && !findbar.hidden) {
                    e.preventDefault();
                    closeFindbar();
                    return;
                }
                if (container.classList.contains('is-expanded')) {
                    e.preventDefault();
                    setExpanded(false);
                }
                return;
            }
            if (isTypingTarget(e.target) || e.ctrlKey || e.metaKey || e.altKey) return;
            if (dialog && !dialog.hidden) return;
            if (menuEl && !menuEl.hidden) return;
            var handled = true;
            switch (e.key) {
                case 'ArrowRight':
                case 'PageDown':
                    stepPage(1);
                    break;
                case 'ArrowLeft':
                case 'PageUp':
                    stepPage(-1);
                    break;
                case 'ArrowDown':
                    viewportEl.scrollTop += 60;
                    break;
                case 'ArrowUp':
                    viewportEl.scrollTop -= 60;
                    break;
                case 'Home':
                    goToPage(1, true);
                    break;
                case 'End':
                    goToPage(pages.length, true);
                    break;
                case '+':
                case '=':
                    zoomBy(ZOOM_STEP);
                    break;
                case '-':
                case '_':
                    zoomBy(1 / ZOOM_STEP);
                    break;
                case '0':
                    setFitMode('width');
                    break;
                case 'r':
                case 'R':
                    rotate();
                    break;
                case 't':
                case 'T':
                    setTextSelect(!textSelect);
                    break;
                default:
                    handled = false;
            }
            if (handled) e.preventDefault();
        });

        // Re-fit when the reading area's width changes (window resize, sidebar, fullscreen).
        // Height-only changes (e.g. a browser toolbar appearing) don't affect fit-to-width.
        var resizeTimeout;
        var onResize = function() {
            if (!pdfDoc) return;
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (viewportEl.clientWidth !== lastViewportWidth || isExpanded()) {
                    onViewerResized();
                }
            }, 150);
        };
        if ('ResizeObserver' in window) new ResizeObserver(onResize).observe(viewportEl);
        else window.addEventListener('resize', onResize);

        setTextSelect(textSelect);

        // ---- Loading ----------------------------------------------------------

        var proxyUrl = '';
        if (config.restUrl) {
            proxyUrl = config.restUrl + (config.restUrl.indexOf('?') === -1 ? '?' : '&') + 'pdf_url=' + encodeURIComponent(pdfUrl);
        }

        // Detect localhost where Google Cloud Storage rejects direct CORS
        var isLocal = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
        var isStorageUrl = (pdfUrl.indexOf('storage.scoutingmemories.org') !== -1);
        var initialUrl = (isLocal && isStorageUrl && proxyUrl) ? proxyUrl : pdfUrl;

        function showProgress(loaded, total) {
            if (!loadingEl) return;
            var progressEl = loadingEl.querySelector('[data-pdf-role="progress"]');
            if (!progressEl) return;
            if (total > 0) {
                progressEl.textContent = Math.min(100, Math.round((loaded / total) * 100)) + '%';
            } else if (loaded > 0) {
                progressEl.textContent = (loaded / 1048576).toFixed(1) + ' MB';
            }
        }

        function showError() {
            setState('error');
            if (!loadingEl) return;
            loadingEl.style.display = 'flex';
            loadingEl.innerHTML = '<div class="card shadow-sm text-center p-4 mx-auto" style="max-width: 28rem;" data-pdf-role="error">' +
                '<div class="h6 mb-2">Unable to display this document</div>' +
                '<p class="text-muted small mb-3">It may still be loading from storage or be temporarily unavailable. Please try again.</p>' +
                '<div><button type="button" class="btn btn-sm btn-sm-green" data-pdf-control="retry">Try again</button></div>' +
                '</div>';
            var retryBtn = loadingEl.querySelector('[data-pdf-control="retry"]');
            if (retryBtn) retryBtn.addEventListener('click', function() { start(); });
        }

        function setupDocument(doc) {
            pdfDoc = doc;
            var requests = [];
            for (var i = 1; i <= doc.numPages; i++) requests.push(doc.getPage(i));

            return Promise.all([
                Promise.all(requests),
                doc.getPageLabels().catch(function() { return null; }),
                doc.getOutline().catch(function() { return null; })
            ]).then(function(res) {
                var pageProxies = res[0];
                labels = (res[1] && res[1].length === doc.numPages) ? res[1] : null;
                outline = res[2];
                pagesEl.innerHTML = '';
                pagesEl.style.visibility = 'hidden';
                pages = pageProxies.map(function(pageProxy, idx) {
                    var num = idx + 1;
                    var pageEl = document.createElement('div');
                    pageEl.setAttribute('data-pdf-page', String(num));
                    pageEl.setAttribute('data-page-label', 'Page ' + labelFor(num));
                    pageEl.setAttribute('role', 'img');
                    pageEl.setAttribute('aria-label', 'Page ' + num + ' of ' + doc.numPages + (hasDistinctLabel(num) ? ', printed page ' + labelFor(num) : ''));
                    return { num: num, page: pageProxy, el: pageEl, canvas: null, baseW: 0, baseH: 0, renderedKey: null };
                });
                arrangePages();
                measureBase();
                layout();
                applyFit();
                viewportEl.scrollTop = 0;
                updateUI();
                buildOutline();
                if (sidebar && !sidebar.hidden) showTab(sidebarTab);
                refreshVisiblePages();
            });
        }

        // Load document with transparent proxy retry fallback
        function loadDoc(urlToLoad, isRetry) {
            setState('loading');
            firstRenderDone = false;
            if (loadingEl) {
                loadingEl.style.display = 'flex';
                loadingEl.classList.remove('hidden');
                loadingEl.innerHTML = '<div class="spinner-border sm_green_color" aria-hidden="true"></div><div class="small">Loading document... <span data-pdf-role="progress"></span></div>';
            }

            var loadingTask = pdfLib.getDocument({
                url: urlToLoad,
                cMapUrl: config.cMapUrl || undefined,
                cMapPacked: true,
                standardFontDataUrl: config.standardFontDataUrl || undefined,
                wasmUrl: config.wasmUrl || undefined,
                iccUrl: config.iccUrl || undefined,
                disableRange: isRetry,
                // Belt and braces: never compile fonts with eval, never run PDF scripts
                isEvalSupported: false,
                enableXfa: false
            });
            loadingTask.onProgress = function(progress) {
                if (progress) showProgress(progress.loaded || 0, progress.total || 0);
            };

            loadingTask.promise.then(setupDocument).catch(function(error) {
                console.warn('Scouting PDF: Failed to load ' + urlToLoad + ':', error);
                if (!isRetry && proxyUrl && urlToLoad !== proxyUrl) {
                    console.info('Scouting PDF: Retrying through stream proxy...');
                    loadDoc(proxyUrl, true);
                    return;
                }
                if (!isRetry && urlToLoad === proxyUrl) {
                    console.info('Scouting PDF: Retrying with disableRange: true...');
                    loadDoc(proxyUrl, true);
                    return;
                }
                showError();
            });
        }

        function start() {
            setState('loading');
            loadLib().then(function(lib) {
                pdfLib = lib;
                loadDoc(initialUrl, false);
            }).catch(function(err) {
                console.error('Scouting PDF: PDF.js could not be loaded', err);
                showError();
            });
        }

        start();
        return api;
    }

    // ---- Page-wide wiring -------------------------------------------------------

    function allViewers() {
        return document.querySelectorAll('[data-pdf-viewer], .scouting-pdf-container');
    }

    function viewerByIndex(index) {
        var viewers = allViewers();
        for (var i = 0; i < viewers.length; i++) {
            var n = parseInt(viewers[i].getAttribute('data-pdf-index'), 10) || i + 1;
            if (n === index) return viewers[i];
        }
        return null;
    }

    // Open the document a #page=N link points at, loading it first if needed
    function followHash() {
        var target = parseHash(window.location.hash);
        if (!target) return false;
        var container = viewerByIndex(target.index);
        if (!container) return false;
        var api = container.scoutingPdf || initViewer(container, target.page);
        if (api) api.goToPage(target.page);
        container.scrollIntoView({ block: 'start' });
        return true;
    }

    // "p. 12" / "page iv" in comments become links that open the first document at that page
    function linkPageReferences() {
        var viewer = viewerByIndex(1);
        if (!viewer) return;
        var bodies = document.querySelectorAll('#comments .comment p, .comment-content, .wp-block-comment-content');
        // Numbers, or well-formed roman numerals (so "page did" isn't a page reference)
        var pattern = /\b(p\.|pp\.|pg\.|page)\s?([0-9]{1,4}|(?=[ivxlcdm])m{0,3}(?:cm|cd|d?c{0,3})(?:xc|xl|l?x{0,3})(?:ix|iv|v?i{0,3}))(?![\w])/gi;
        Array.prototype.forEach.call(bodies, function(body) {
            var walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT, null);
            var nodes = [];
            while (walker.nextNode()) {
                if (!walker.currentNode.parentNode.closest('a')) nodes.push(walker.currentNode);
            }
            nodes.forEach(function(node) {
                var text = node.nodeValue;
                pattern.lastIndex = 0;
                if (!pattern.test(text)) return;
                pattern.lastIndex = 0;
                var frag = document.createDocumentFragment();
                var last = 0;
                var m;
                while ((m = pattern.exec(text))) {
                    frag.appendChild(document.createTextNode(text.slice(last, m.index)));
                    var a = document.createElement('a');
                    a.href = '#' + viewer.id;
                    a.className = 'scouting-pdf-page-ref';
                    a.setAttribute('data-pdf-page-ref', m[2]);
                    a.title = 'Show this page in the document';
                    a.textContent = m[0];
                    frag.appendChild(a);
                    last = m.index + m[0].length;
                }
                frag.appendChild(document.createTextNode(text.slice(last)));
                node.parentNode.replaceChild(frag, node);
            });
        });
        document.addEventListener('click', function(e) {
            var link = e.target.closest && e.target.closest('a[data-pdf-page-ref]');
            if (!link) return;
            e.preventDefault();
            var api = viewer.scoutingPdf || initViewer(viewer);
            if (api) api.goToLabel(link.getAttribute('data-pdf-page-ref'));
            viewer.scrollIntoView({ block: 'start', behavior: 'smooth' });
        });
    }

    // Only download a PDF once its viewer is near the screen, so posts with several
    // documents don't pull every file on page load
    function initAllViewers() {
        var viewers = allViewers();
        var target = parseHash(window.location.hash);

        linkPageReferences();
        window.addEventListener('hashchange', followHash);

        if (target) {
            var direct = viewerByIndex(target.index);
            if (direct) {
                initViewer(direct, target.page);
                direct.scrollIntoView({ block: 'start' });
            }
        }

        if (!('IntersectionObserver' in window)) {
            for (var i = 0; i < viewers.length; i++) {
                initViewer(viewers[i]);
            }
            return;
        }

        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    initViewer(entry.target);
                }
            });
        }, { rootMargin: '400px 0px' });

        for (var j = 0; j < viewers.length; j++) {
            observer.observe(viewers[j]);
        }
    }

    // Exposed for the automated tests
    window.ScoutingPdfViewer = {
        buildCitations: buildCitations,
        buildRis: buildRis,
        buildTextIndex: buildTextIndex,
        normalizeQuery: normalizeQuery,
        parseHash: parseHash
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllViewers);
    } else {
        initAllViewers();
    }
})();
