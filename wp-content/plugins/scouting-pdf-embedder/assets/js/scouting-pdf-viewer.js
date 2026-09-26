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
    var SNAPSHOT_DPI = 300;
    var THUMB_WIDTH = 132;
    var RESUME_DAYS = 180;

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var MLA_MONTHS = ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May', 'June', 'July', 'Aug.', 'Sept.', 'Oct.', 'Nov.', 'Dec.'];

    var nativeFullscreen = !!(document.fullscreenEnabled || document.webkitFullscreenEnabled);

    function getFullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || null;
    }

    // The reader's rotation of a page is added to the page's own rotation
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

    // Icon and label for a button, the same larger icon (.fs-5) as the toolbar buttons
    function iconLabel(icon, label) {
        return '<i class="bi ' + icon + ' fs-5 lh-1" aria-hidden="true"></i> ' + escHtml(label);
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

    function canvasToBlob(canvas, type, quality) {
        return new Promise(function(resolve, reject) {
            canvas.toBlob(function(blob) {
                if (blob) resolve(blob); else reject(new Error('Could not create image'));
            }, type || 'image/png', quality);
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

    // Archive dates are stored as YYYY-MM-DD. Volunteers record a year alone as YYYY-01-01 and
    // a month (a monthly newsletter) as YYYY-MM-01, so cite those as "1955" and "December 1955"
    // rather than inventing a day. Anything not in that form is cited as entered.
    function parseDocDate(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim());
        if (!m) return null;
        var month = parseInt(m[2], 10);
        var day = parseInt(m[3], 10);
        if (month < 1 || month > 12) return null;
        if (month === 1 && day === 1) return { year: m[1] };
        return { year: m[1], month: month - 1, day: day === 1 ? 0 : day };
    }

    // Chicago "September 25, 2026", MLA "25 Sept. 2026", APA "2026, September 25"
    function formatDocDate(value, style) {
        var d = parseDocDate(value);
        if (!d) return value;
        if (d.month === undefined) return d.year;
        if (style === 'mla') return (d.day ? d.day + ' ' : '') + MLA_MONTHS[d.month] + ' ' + d.year;
        if (style === 'apa') return d.year + ', ' + MONTHS[d.month] + (d.day ? ' ' + d.day : '');
        return MONTHS[d.month] + ' ' + (d.day ? d.day + ', ' : '') + d.year;
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
        parts = ['“' + info.title + ',” ' + (info.date ? formatDocDate(info.date, 'chicago') : 'n.d.') + (pageText ? ', ' + pageText : '') + '. '];
        if (info.container) parts.push('In ', { i: info.container }, '. ');
        if (info.identifier) parts.push(endSentence(info.identifier) + ' ');
        if (info.publisher) parts.push('Digitized by ' + endSentence(info.publisher) + ' ');
        parts.push(endSentence(info.site) + ' ', info.url + ' (accessed ' + formatLongDate(now) + ').');
        var chicago = both(parts);

        // MLA 9
        parts = ['“' + endSentence(info.title) + '” '];
        if (info.container) parts.push({ i: info.container }, ', ');
        parts.push({ i: info.site }, ', ');
        if (info.date) parts.push(formatDocDate(info.date, 'mla') + ', ');
        if (pageText) parts.push(pageText + ', ');
        parts.push(info.url + '. Accessed ' + formatMlaDate(now) + '.');
        var mla = both(parts);

        // APA 7
        var author = info.publisher || info.site;
        var apaDate = parseDocDate(info.date) ? formatDocDate(info.date, 'apa') : year;
        parts = [endSentence(author) + ' (' + apaDate + '). ', { i: info.title }, ' [Archival document]'];
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
    // When sharing all settings: #page=12&zoom=width&rot=90&b=120&c=110&inv=1&spread=1

    function buildPageHash(index, num, settings) {
        var prefix = (index > 1 ? 'pdf' + index + '-' : '');
        var parts = [prefix + 'page=' + num];
        if (settings) {
            if (settings.zoom) {
                if (settings.zoom === 'width' || settings.zoom === 'page') {
                    parts.push('zoom=' + settings.zoom);
                } else if (typeof settings.zoom === 'number') {
                    parts.push('zoom=' + settings.zoom.toFixed(2));
                } else if (typeof settings.zoom === 'string') {
                    parts.push('zoom=' + settings.zoom);
                }
            }
            if (settings.rot && (settings.rot % 360 !== 0)) {
                parts.push('rot=' + settings.rot);
            }
            if (settings.brightness !== undefined && settings.brightness !== '100' && settings.brightness !== 100) {
                parts.push('b=' + settings.brightness);
            }
            if (settings.contrast !== undefined && settings.contrast !== '100' && settings.contrast !== 100) {
                parts.push('c=' + settings.contrast);
            }
            if (settings.grayscale) parts.push('g=1');
            if (settings.invert) parts.push('inv=1');
            if (settings.spread) parts.push('spread=1');
        }
        return parts.join('&');
    }

    function pageHash(index, num) {
        return buildPageHash(index, num, null);
    }

    function parseHash(hash) {
        if (!hash || hash.length < 2) return null;
        var str = hash.replace(/^#/, '');
        var params = {};
        str.split('&').forEach(function(part) {
            var kv = part.split('=');
            if (kv[0]) {
                params[decodeURIComponent(kv[0])] = decodeURIComponent(kv[1] || '');
            }
        });

        var index = 1;
        var page = null;
        for (var k in params) {
            var m = /^(?:pdf(\d+)-)?page$/i.exec(k);
            if (m) {
                if (m[1]) index = parseInt(m[1], 10);
                page = parseInt(params[k], 10);
                break;
            }
        }
        if (!page || isNaN(page)) {
            var m2 = /^(?:pdf(\d+)-)?page-(\d+)$/i.exec(str) || /^(?:pdf(\d+)-)?p(\d+)$/i.exec(str);
            if (m2) {
                if (m2[1]) index = parseInt(m2[1], 10);
                page = parseInt(m2[2], 10);
            } else {
                return null;
            }
        }

        var settings = {};
        if (params.zoom) settings.zoom = params.zoom;
        if (params.rot) settings.rot = parseInt(params.rot, 10);
        if (params.b) settings.brightness = params.b;
        if (params.c) settings.contrast = params.c;
        if (params.g !== undefined) settings.grayscale = (params.g === '1' || params.g === 'true');
        if (params.inv !== undefined) settings.invert = (params.inv === '1' || params.inv === 'true');
        if (params.spread !== undefined) settings.spread = (params.spread === '1' || params.spread === 'true');

        return { index: index, page: page, settings: Object.keys(settings).length ? settings : null };
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

    function initViewer(container, initialPage, initialSettings) {
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
        var currentHashTarget = parseHash(window.location.hash);
        var urlSettings = initialSettings || (currentHashTarget && currentHashTarget.index === viewerIndex ? currentHashTarget.settings : null);

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
        var spreadBtn = control('spread');
        var findbar = q('[data-pdf-role="findbar"]');
        var findInput = control('find-input');
        var findStatus = q('[data-pdf-role="find-status"]');
        var adjustbar = q('[data-pdf-role="adjustbar"]');
        var sidebar = q('[data-pdf-role="sidebar"]');
        var dialog = q('[data-pdf-role="dialog"]');
        var toasts = q('[data-pdf-role="toasts"]');

        if (!viewportEl) return null;

        initTooltips(container);

        // Page HTML cached before v1.2 has a single canvas instead of the page column
        if (!pagesEl) {
            pagesEl = el('div', 'd-flex flex-column align-items-center flex-shrink-0 gap-3 mx-auto');
            pagesEl.setAttribute('data-pdf-role', 'pages');
            viewportEl.appendChild(pagesEl);
        }
        var legacyWrapper = q('[data-pdf-role="canvas-wrapper"], .scouting-pdf-canvas-wrapper');
        if (legacyWrapper) legacyWrapper.style.display = 'none';
        if (downloadBtn && !allowDownload) downloadBtn.hidden = true;

        var pdfLib = null;
        var pdfDoc = null;
        var pages = [];          // { num, page, el, canvas, rotation, baseW, baseH, renderedKey, text, textLayer }
        var labels = null;       // printed page numbers, when the PDF defines them
        var outline = null;
        var currentPage = 1;
        var scale = 1.0;
        var fitMode = 'page';    // 'width' | 'page' | null (manual zoom)
        var spread = false;
        var textSelect = false;
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
            currentPage: function() { return currentPage; },
            // num: the page the link points at, which gets the link's rotation
            applySettings: function(s, num) {
                whenReady(function() {
                    applyUrlSettings(s, num || currentPage);
                    pages.forEach(releasePage);
                    measureBase();
                    layout();
                    if (fitMode) applyFit();
                    else if (s && s.zoom && !fitMode && scale) setScale(scale);
                    else applyFit();
                    goToPage(currentPage, true);
                    resetThumbs();
                });
            },
            currentSettings: function() { return currentViewSettings(currentPage); }
        };
        container.scoutingPdf = api;

        function whenReady(fn) {
            if (api.ready) fn(); else readyCallbacks.push(fn);
        }

        function setState(state) {
            container.setAttribute('data-pdf-state', state);
            updateCursor();
        }

        // Drag to move around the pages; drag to draw a box while clipping
        function updateCursor() {
            viewportEl.style.cursor = snap ? 'crosshair' : pan ? 'grabbing' :
                container.getAttribute('data-pdf-state') === 'ready' ? 'grab' : '';
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

        // View settings for a link to page num; its rotation is that page's own
        function currentViewSettings(num) {
            var b = control('brightness');
            var c = control('contrast');
            var g = control('grayscale');
            var inv = control('invert');
            var s = {};
            if (fitMode) {
                s.zoom = fitMode;
            } else if (scale && Math.abs(scale - 1.0) > 0.01) {
                s.zoom = Math.round(scale * 100) / 100;
            }
            var p = pages[num - 1];
            if (p && p.rotation) {
                s.rot = p.rotation;
            }
            if (b && b.value && b.value !== '100' && b.value !== 100) {
                s.brightness = b.value;
            }
            if (c && c.value && c.value !== '100' && c.value !== 100) {
                s.contrast = c.value;
            }
            if (g && g.checked) s.grayscale = true;
            if (inv && inv.checked) s.invert = true;
            if (spread) s.spread = true;
            return s;
        }

        function pageLink(num, includeSettings) {
            var base = cite.permalink || window.location.href.split('#')[0];
            var settings = includeSettings ? currentViewSettings(num) : null;
            return base.split('#')[0] + '#' + buildPageHash(viewerIndex, num, settings);
        }

        function applyUrlSettings(s, num) {
            if (!s) return;
            if (s.brightness !== undefined || s.contrast !== undefined || s.grayscale !== undefined || s.invert !== undefined) {
                var b = control('brightness');
                var c = control('contrast');
                var g = control('grayscale');
                var inv = control('invert');
                if (b && s.brightness !== undefined) b.value = s.brightness;
                if (c && s.contrast !== undefined) c.value = s.contrast;
                if (g && s.grayscale !== undefined) g.checked = !!s.grayscale;
                if (inv && s.invert !== undefined) inv.checked = !!s.invert;
                applyAdjustments();
                if (adjustbar && adjustbar.hidden && (s.brightness !== undefined || s.contrast !== undefined || s.grayscale || s.invert)) {
                    toggleAdjust(true);
                }
            }
            var linked = pages[(num || currentPage) - 1];
            if (linked && s.rot !== undefined && !isNaN(s.rot)) {
                linked.rotation = (Math.round(s.rot / 90) * 90 % 360 + 360) % 360;
            }
            if (s.spread !== undefined && s.spread !== spread) {
                spread = !!s.spread;
                setPressed(spreadBtn, spread);
            }
            if (s.zoom) {
                if (s.zoom === 'width' || s.zoom === 'page') {
                    fitMode = s.zoom;
                } else {
                    var z = parseFloat(s.zoom);
                    if (!isNaN(z) && z >= MIN_SCALE && z <= MAX_SCALE) {
                        fitMode = null;
                        scale = z;
                    }
                }
            }
        }

        function updateUI() {
            var total = pdfDoc ? pdfDoc.numPages : 0;
            if (pageNumEl) {
                if (document.activeElement !== pageNumEl) pageNumEl.value = currentPage;
                if (total) pageNumEl.max = total;
            }
            if (totalPagesEl && total) totalPagesEl.textContent = total;
            if (pageLabelEl) {
                pageLabelEl.hidden = !hasDistinctLabel(currentPage);
                pageLabelEl.textContent = 'p. ' + labelFor(currentPage);
            }
            if (zoomLevelEl) zoomLevelEl.textContent = Math.round(scale * 100) + '%';
            if (prevBtn) prevBtn.disabled = (currentPage <= 1);
            if (nextBtn) nextBtn.disabled = (!total || currentPage >= total);
            if (zoomInBtn) zoomInBtn.disabled = (scale >= MAX_SCALE - 0.001);
            if (zoomOutBtn) zoomOutBtn.disabled = (scale <= MIN_SCALE + 0.001);
            setPressed(zoomFitBtn, fitMode === 'width');
            setPressed(zoomPageBtn, fitMode === 'page');
            updateThumbSelection();
            refitToolbars();
        }

        // The page count and a printed page number change the width of the page navigation,
        // which can call for a different toolbar layout (and so a different reading height)
        var toolbarText = null;
        function refitToolbars() {
            var text = (totalPagesEl ? totalPagesEl.textContent : '') + '|' +
                (pageLabelEl && !pageLabelEl.hidden ? pageLabelEl.textContent.length : 0);
            if (text === toolbarText) return;
            toolbarText = text;
            var before = container.getAttribute('data-pdf-size');
            sizeViewer(container);
            if (container.getAttribute('data-pdf-size') !== before && fitMode && pages.length) applyFit();
        }

        // Bootstrap's .active shows the selected state in the site green
        function setPressed(btn, on) {
            if (!btn) return;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        }

        // ---- Layout -----------------------------------------------------------

        // Inner size of the reading area, excluding padding. The viewer may grow to its max
        // height (the screen) and the reading area gets what the toolbars leave, so measure
        // against that: "fit page" is then right even while the column is still short.
        function getAvailableSize() {
            var style = window.getComputedStyle(viewportEl);
            var padX = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
            var padY = (parseFloat(style.paddingTop) || 0) + (parseFloat(style.paddingBottom) || 0);
            var height = viewportEl.clientHeight;
            var maxH = parseFloat(window.getComputedStyle(container).maxHeight);
            if (!isExpanded() && isFinite(maxH) && maxH > 0) {
                height = maxH - (container.offsetHeight - viewportEl.clientHeight);
            }
            return { width: Math.max(0, viewportEl.clientWidth - padX), height: Math.max(0, height - padY) };
        }

        function measurePage(p) {
            var vp = getPageViewport(p.page, 1, p.rotation);
            p.baseW = vp.width;
            p.baseH = vp.height;
        }

        function measureBase() {
            pages.forEach(measurePage);
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
                var row = el('div', 'd-flex align-items-start justify-content-center gap-3');
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
                fit = Math.min((avail.width - 4) / rowW, (avail.height - 4) / rowH);
            } else {
                fit = (avail.width - 4) / width;
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
            // Settle against the real vertical scrollbar, which changes the available width
            for (var i = 0; i < 3; i++) {
                var fit = computeFitScale(fitMode);
                if (Math.abs(fit - scale) < 0.002) break;
                setScale(fit);
            }
            if (fitMode === 'page') {
                viewportEl.scrollLeft = 0;
                goToPage(currentPage, true);
            }
            updateUI();
        }

        // ---- Navigation -------------------------------------------------------

        function goToPage(num, force, offsetY) {
            if (!pages.length) return;
            num = clamp(num, 1, pages.length);
            if (num === currentPage && !force) return;
            currentPage = num;
            if (fitMode === 'page') {
                var fit = computeFitScale('page');
                if (Math.abs(fit - scale) >= 0.002) {
                    scale = fit;
                    layout();
                }
                viewportEl.scrollLeft = 0;
            } else if (fitMode === 'width') {
                viewportEl.scrollLeft = 0;
            }
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

        function renderKey(p) {
            return scale.toFixed(4) + '|' + p.rotation;
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
            var wanted = [];

            pages.forEach(function(p) {
                var pTop = p.el.offsetTop;
                var pBottom = pTop + p.el.offsetHeight;
                if (pBottom >= top - h && pTop <= top + 2 * h) {
                    if (p.renderedKey !== renderKey(p)) wanted.push(p);
                    else if (p.textLayerKey !== p.rotation) buildTextLayer(p);
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
            var key = renderKey(p);
            if (p.renderedKey === key) {
                renderNext();
                return;
            }
            rendering = true;

            var viewport = getPageViewport(p.page, scale, p.rotation);
            var dpr = window.devicePixelRatio || 1;
            var pixels = viewport.width * viewport.height * dpr * dpr;
            if (pixels > MAX_CANVAS_PIXELS) {
                dpr = Math.max(0.5, Math.sqrt(MAX_CANVAS_PIXELS / (viewport.width * viewport.height)));
            }

            // Draw off-screen and swap in when done, so the previous (stretched) image stays
            // visible during zoom instead of flashing blank
            var canvas = el('canvas', 'position-absolute top-0 start-0 w-100 h-100');
            canvas.width = Math.floor(viewport.width * dpr);
            canvas.height = Math.floor(viewport.height * dpr);

            function done() {
                rendering = false;
                if (!firstRenderDone) {
                    firstRenderDone = true;
                    if (loadingEl) {
                        loadingEl.classList.add('d-none');
                    }
                    pagesEl.style.visibility = '';
                    setState('ready');
                    if (sidebar && !sidebar.hidden) showTab(sidebarTab);
                    // The column is final now; settle the fit against the real scrollbar,
                    // then open at the requested page (or the top of page 1)
                    applyFit();
                    viewportEl.scrollTop = 0;
                    viewportEl.scrollLeft = 0;
                    lastViewportWidth = viewportEl.clientWidth;
                    if (startPage > 1) goToPage(startPage, true);
                    api.ready = true;
                    readyCallbacks.splice(0).forEach(function(fn) { fn(); });
                    document.dispatchEvent(new CustomEvent('scouting-pdf:ready', { detail: { index: viewerIndex } }));
                }
                if (p.renderedKey !== renderKey(p)) scheduleRender();
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
                if (p.textLayerKey !== p.rotation) buildTextLayer(p);
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

        // Only needed while text selection is on; skipping it spares a text extraction per page
        function buildTextLayer(p) {
            if (!textSelect || !pdfLib || !pdfLib.TextLayer || p.textLayerKey === p.rotation || p.textLayerPending === p.rotation) return;
            var rot = p.rotation;
            p.textLayerPending = rot;
            getText(p).then(function(tc) {
                p.textLayerPending = null;
                if (p.rotation !== rot || !p.canvas || !tc.items.length) return;
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
            if (on) pages.forEach(function(p) { if (p.canvas) buildTextLayer(p); });
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

        // On a narrow viewer the sidebar slides over the pages (see sizeViewer) rather than
        // sitting beside them
        function sidebarCovers() {
            return container.clientWidth < SIDEBAR_BESIDE_MIN;
        }

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
                var box = el('span', 'd-block overflow-hidden bg-white border border-2');
                box.style.width = THUMB_WIDTH + 'px';
                box.style.height = Math.round(THUMB_WIDTH * p.baseH / p.baseW) + 'px';
                btn.appendChild(box);
                btn.appendChild(el('span', 'small text-muted', labelFor(p.num)));
                btn.addEventListener('click', function() {
                    goToPage(p.num, true);
                    if (sidebarCovers()) closeSidebar();
                });
                list.appendChild(btn);
                thumbButtons[p.num - 1] = btn;
            });
            host.appendChild(list);

            if ('IntersectionObserver' in window) {
                thumbObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            thumbObserver.unobserve(entry.target);
                            drawThumb(entry.target);
                        }
                    });
                }, { root: sidebar, rootMargin: '300px 0px' });
                thumbButtons.forEach(function(b) { thumbObserver.observe(b); });
            } else {
                thumbButtons.forEach(drawThumb);
            }
            updateThumbSelection();
        }

        function drawThumb(btn) {
            var p = pages[parseInt(btn.getAttribute('data-pdf-thumb'), 10) - 1];
            var box = btn.firstChild;
            if (box.firstChild) return;
            var dpr = window.devicePixelRatio || 1;
            var rot = p.rotation;
            var vp = getPageViewport(p.page, THUMB_WIDTH / p.baseW, rot);
            var canvas = el('canvas', 'd-block w-100 h-100');
            canvas.width = Math.floor(vp.width * dpr);
            canvas.height = Math.floor(vp.height * dpr);
            p.page.render({ canvas: canvas, viewport: vp, transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null }).promise.then(function() {
                // A quick second rotation may have started a newer drawing
                if (p.rotation !== rot) return;
                box.textContent = '';
                box.appendChild(canvas);
            }).catch(function() {});
        }

        // After a page is rotated, redraw just its thumbnail
        function redrawThumb(p) {
            var btn = thumbButtons[p.num - 1];
            if (!btn) return;
            var box = btn.firstChild;
            box.textContent = '';
            box.style.height = Math.round(THUMB_WIDTH * p.baseH / p.baseW) + 'px';
            drawThumb(btn);
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
                b.firstChild.classList.toggle('border-primary', on);
                b.lastChild.classList.toggle('text-primary', on);
                b.lastChild.classList.toggle('fw-bold', on);
                b.lastChild.classList.toggle('text-muted', !on);
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
                        var vp = getPageViewport(p.page, scale, p.rotation);
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
                var dl = el('dl', 'p-3 mb-0');
                rows.forEach(function(row, i) {
                    if (i === archiveCount && archiveCount) dl.appendChild(el('hr'));
                    dl.appendChild(el('dt', '', row[0]));
                    dl.appendChild(el('dd', '', row[1]));
                });
                host.appendChild(dl);
            });
        }

        if (sidebar) {
            // Open beside the pages when there's room; where it would cover the page, start closed
            sidebar.hidden = sidebarCovers();
            setPressed(sidebarBtn, !sidebar.hidden);
            var tabButtons = sidebar.querySelectorAll('[data-pdf-tab]');
            for (var t = 0; t < tabButtons.length; t++) {
                (function(btn) {
                    btn.addEventListener('click', function() { showTab(btn.getAttribute('data-pdf-tab')); });
                })(tabButtons[t]);
            }
            var sidebarCloseBtn = control('sidebar-close');
            if (sidebarCloseBtn) {
                sidebarCloseBtn.classList.remove('btn-link', 'text-muted');
                if (!sidebarCloseBtn.classList.contains('btn-close')) sidebarCloseBtn.classList.add('btn-close');
                sidebarCloseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                    if (sidebarBtn) sidebarBtn.focus();
                });
            }
        }

        // ---- Dialogs and messages -------------------------------------------------

        // The Cite and clipping windows are a Bootstrap modal (the theme's bootstrap.bundle
        // opens and closes it, traps focus and closes it on Esc), placed over the document
        // area with a light wash rather than a dark backdrop over the whole web page
        var dialogOpen = false;
        var dialogOpener = null;
        var modal = (dialog && window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal.getOrCreateInstance(dialog) : null;

        function dialogClosed() {
            dialogOpen = false;
            if (dialogOpener && dialogOpener.focus && container.contains(dialogOpener)) dialogOpener.focus();
            else container.focus({ preventScroll: true });
        }

        // size: 'lg' (the default) or 'xl' for a wide picture
        function openDialog(title, body, size) {
            if (!dialog) return;
            dialogOpener = document.activeElement;
            q('[data-pdf-role="dialog-title"]').textContent = title;
            var host = q('[data-pdf-role="dialog-body"]');
            host.textContent = '';
            host.appendChild(body);
            var box = dialog.querySelector('.modal-dialog');
            box.classList.toggle('modal-xl', size === 'xl');
            box.classList.toggle('modal-lg', size !== 'xl');
            initTooltips(host);
            dialogOpen = true;
            if (modal) {
                modal.show();
            } else {
                dialog.style.display = 'block';
                dialog.classList.add('show');
            }
        }

        function closeDialog() {
            if (!dialogOpen) return false;
            if (modal) {
                modal.hide();
            } else {
                dialog.classList.remove('show');
                dialog.style.display = 'none';
                dialogClosed();
            }
            return true;
        }

        if (dialog) {
            dialog.addEventListener('hidden.bs.modal', dialogClosed);
            // Clicking the light wash around the window closes it (Bootstrap only does that for
            // its own dark backdrop, which the viewer doesn't use)
            dialog.addEventListener('click', function(e) {
                if (e.target === dialog || e.target === dialog.firstElementChild) closeDialog();
            });
            if (!modal) {
                var closeBtn = control('dialog-close');
                if (closeBtn) closeBtn.addEventListener('click', closeDialog);
            }
        }

        function toast(message, actionLabel, action, timeout) {
            if (!toasts) return;
            var existing = toasts.querySelectorAll('.toast');
            if (existing.length > 2) {
                existing[0].remove();
            }
            // Bootstrap's toast with a close button, as in its documentation
            var t = el('div', 'toast show align-items-center');
            t.setAttribute('role', 'status');
            var row = el('div', 'd-flex');
            row.appendChild(el('div', 'toast-body', message));
            if (actionLabel) {
                var btn = el('button', 'btn btn-primary btn-sm my-auto text-nowrap', actionLabel);
                btn.type = 'button';
                btn.addEventListener('click', function() { action(); t.remove(); });
                row.appendChild(btn);
            }
            var x = el('button', 'btn-close me-2 m-auto');
            x.type = 'button';
            x.setAttribute('aria-label', 'Close');
            x.addEventListener('click', function() { t.remove(); });
            row.appendChild(x);
            t.appendChild(row);
            toasts.appendChild(t);

            var ms = (timeout !== undefined) ? timeout : (actionLabel ? 12000 : 3500);
            if (ms > 0) {
                setTimeout(function() {
                    if (t.parentNode) {
                        t.style.opacity = '0';
                        t.style.transition = 'opacity 0.3s ease';
                        setTimeout(function() { t.remove(); }, 300);
                    }
                }, ms);
            }
            return t;
        }

        function copyText(text, html) {
            var fallback = function() {
                return new Promise(function(resolve, reject) {
                    var ta = el('textarea', 'position-fixed top-0 start-0 opacity-0');
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
                var isLink = (what && String(what).toLowerCase().indexOf('link') !== -1);
                var msg = isLink ? 'Link copied!' : what + ' copied!';
                toast(msg, null, null, 3500);
            }, function() {
                toast('Couldn’t copy automatically. Select the text and copy it instead.', null, null, 5000);
            });
        }

        // ---- Cite, link, download, print ------------------------------------------

        function citationInfo(num, includePage, includeSettings) {
            var docTitle = cite.title || cite.postTitle || container.getAttribute('data-pdf-title') || fileNameFromUrl(pdfUrl);
            return {
                title: docTitle,
                container: (cite.postTitle && cite.postTitle !== docTitle) ? cite.postTitle : '',
                date: cite.dateOriginal || '',
                identifier: cite.identifier || '',
                publisher: cite.publisher || '',
                location: cite.location || '',
                site: cite.site || config.siteName || window.location.hostname,
                url: includePage ? pageLink(num, includeSettings) : (cite.permalink || window.location.href.split('#')[0]),
                page: includePage ? labelFor(num) : null
            };
        }

        var currentCiteStyle = storageGet('scouting-pdf-cite-style') || 'chicago';

        function openCite() {
            var num = currentPage;
            var body = el('div');

            // Which page of which document, then one line on what this window is for
            var docTitle = cite.title || cite.postTitle || container.getAttribute('data-pdf-title') || fileNameFromUrl(pdfUrl);
            var context = el('p', 'd-flex align-items-center gap-2 mb-2');
            context.appendChild(el('span', 'badge text-bg-primary flex-shrink-0', describePage(num)));
            context.appendChild(el('span', 'text-muted text-truncate', docTitle));
            body.appendChild(context);
            body.appendChild(el('p', '', 'Copy a ready-made citation for this page, copy a link that opens right at it for email or social media, save it to Zotero or EndNote, or clip part of the page as a picture.'));

            // Citation
            var citeCard = el('div', 'card mb-3');
            var citeBody = el('div', 'card-body');
            citeBody.appendChild(el('h6', 'card-title', 'Citation'));
            var citeHead = el('div', 'd-flex flex-wrap align-items-center gap-2 mb-3');
            var styleLabel = el('label', 'form-label mb-0', 'Style');
            styleLabel.htmlFor = container.id + '-cite-style';
            var styleSelect = el('select', 'form-select w-auto mw-100');
            styleSelect.id = container.id + '-cite-style';
            styleSelect.setAttribute('data-pdf-control', 'cite-style-select');

            // [key, menu label, name used in the "copied" message]
            var styles = [
                ['chicago', 'Chicago — used by most historians', 'Chicago'],
                ['mla', 'MLA — common in schools', 'MLA'],
                ['apa', 'APA — used in the social sciences', 'APA']
            ];
            styles.forEach(function(s) {
                var opt = el('option', '', s[1]);
                opt.value = s[0];
                if (s[0] === currentCiteStyle) opt.selected = true;
                styleSelect.appendChild(opt);
            });

            var copyCiteBtn = el('button', 'btn btn-primary ms-auto px-3 py-2');
            copyCiteBtn.type = 'button';
            copyCiteBtn.innerHTML = iconLabel('bi-clipboard-check', 'Copy citation');
            citeHead.appendChild(styleLabel);
            citeHead.appendChild(styleSelect);
            citeHead.appendChild(copyCiteBtn);
            citeBody.appendChild(citeHead);

            var list = el('div', 'mb-2');
            citeBody.appendChild(list);
            citeBody.appendChild(el('div', 'form-text', 'Check the details against the original before you publish. Dates on older records are sometimes estimates.'));
            citeCard.appendChild(citeBody);
            body.appendChild(citeCard);

            // Link to this page
            var linkCard = el('div', 'card mb-3');
            var linkBody = el('div', 'card-body');
            linkBody.appendChild(el('h6', 'card-title', 'Link to this page'));

            var checksWrap = el('div', 'mb-3');

            var pageSwitch = el('div', 'd-flex align-items-center gap-2 mb-2');
            var cb = el('input', 'form-check-input fs-3 rounded-0 m-0');
            cb.type = 'checkbox';
            cb.checked = true;
            cb.id = container.id + '-cite-page';
            var lab = el('label', 'form-check-label', 'Point to ' + describePage(num) + ' (uncheck to cite the whole document)');
            lab.htmlFor = cb.id;
            pageSwitch.appendChild(cb);
            pageSwitch.appendChild(lab);
            checksWrap.appendChild(pageSwitch);

            var settingsSwitch = el('div', 'd-flex align-items-center gap-2');
            var cbSettings = el('input', 'form-check-input fs-3 rounded-0 m-0');
            cbSettings.type = 'checkbox';
            cbSettings.checked = false;
            cbSettings.id = container.id + '-cite-settings';
            var labSettings = el('label', 'form-check-label', 'Show it the way I see it (same zoom, rotation and image adjustments)');
            labSettings.htmlFor = cbSettings.id;
            settingsSwitch.appendChild(cbSettings);
            settingsSwitch.appendChild(labSettings);
            checksWrap.appendChild(settingsSwitch);
            linkBody.appendChild(checksWrap);

            var urlGroup = el('div', 'input-group');
            var urlIcon = el('span', 'input-group-text');
            urlIcon.innerHTML = '<i class="bi bi-link-45deg fs-5 lh-1" aria-hidden="true"></i>';
            var urlInput = el('input', 'form-control');
            urlInput.type = 'text';
            urlInput.readOnly = true;
            urlInput.setAttribute('aria-label', 'Link to this page');

            var copyLinkBtn = el('button', 'btn btn-primary px-3 py-2');
            copyLinkBtn.type = 'button';
            copyLinkBtn.innerHTML = iconLabel('bi-link-45deg', 'Copy link');

            urlGroup.appendChild(urlIcon);
            urlGroup.appendChild(urlInput);
            urlGroup.appendChild(copyLinkBtn);
            linkBody.appendChild(urlGroup);
            linkCard.appendChild(linkBody);
            body.appendChild(linkCard);

            // More ways to save and share
            var toolsCard = el('div', 'card');
            var toolsBody = el('div', 'card-body');
            toolsBody.appendChild(el('h6', 'card-title', 'More ways to save and share'));
            var toolsBtns = el('div', 'd-flex flex-wrap gap-2');
            var ris = el('button', 'btn btn-outline-secondary px-3 py-2');
            ris.type = 'button';
            ris.setAttribute('data-pdf-control', 'cite-ris');
            ris.innerHTML = iconLabel('bi-download', 'Download for Zotero / EndNote');
            ris.setAttribute('data-bs-toggle', 'tooltip');
            ris.setAttribute('data-bs-title', 'Saves a small .ris file. Open it and Zotero, EndNote or Mendeley adds this document to your library.');

            var areaBtn = el('button', 'btn btn-outline-secondary px-3 py-2');
            areaBtn.type = 'button';
            areaBtn.innerHTML = iconLabel('bi-camera', 'Clip part of the page');
            areaBtn.setAttribute('data-bs-toggle', 'tooltip');
            areaBtn.setAttribute('data-bs-title', 'Draw a box around part of this page and save it as a picture, with the title, page and link printed underneath');

            toolsBtns.appendChild(ris);
            toolsBtns.appendChild(areaBtn);
            toolsBody.appendChild(toolsBtns);
            toolsCard.appendChild(toolsBody);
            body.appendChild(toolsCard);

            var draw = function() {
                var withSettings = cb.checked && cbSettings.checked;
                var currentUrl = pageLink(num, withSettings);
                urlInput.value = currentUrl;

                var info = citationInfo(num, cb.checked, withSettings);
                var cites = buildCitations(info, new Date());
                list.textContent = '';

                styles.forEach(function(s) {
                    var styleKey = s[0];
                    var c = cites[styleKey];
                    var box = el('div');
                    box.setAttribute('data-pdf-citation', styleKey);
                    if (styleKey !== currentCiteStyle) {
                        box.hidden = true;
                    }
                    var text = el('p', 'border rounded p-3 mb-0 user-select-all');
                    text.innerHTML = c.html;
                    box.appendChild(text);
                    list.appendChild(box);
                });
            };

            copyCiteBtn.addEventListener('click', function() {
                var withSettings = cb.checked && cbSettings.checked;
                var info = citationInfo(num, cb.checked, withSettings);
                var cites = buildCitations(info, new Date());
                var c = cites[currentCiteStyle] || cites.chicago;
                var style = styles.filter(function(s) { return s[0] === currentCiteStyle; })[0];
                copyWithFeedback(c.text, c.html, (style ? style[2] : 'Chicago') + ' citation');
            });

            copyLinkBtn.addEventListener('click', function() {
                var withSettings = cb.checked && cbSettings.checked;
                copyWithFeedback(pageLink(num, withSettings), null, 'Link');
            });

            ris.addEventListener('click', function() {
                var withSettings = cb.checked && cbSettings.checked;
                var info = citationInfo(num, cb.checked, withSettings);
                var name = fileNameFromUrl(pdfUrl).replace(/\.pdf$/i, '') + (info.page ? '-p' + info.page : '') + '.ris';
                saveBlob(new Blob([buildRis(info, pdfUrl, new Date())], { type: 'application/x-research-info-systems' }), name);
            });

            areaBtn.addEventListener('click', function() {
                closeDialog();
                startSnapshot();
            });

            styleSelect.addEventListener('change', function() {
                currentCiteStyle = styleSelect.value;
                storageSet('scouting-pdf-cite-style', currentCiteStyle);
                var boxes = list.querySelectorAll('[data-pdf-citation]');
                Array.prototype.forEach.call(boxes, function(box) {
                    box.hidden = (box.getAttribute('data-pdf-citation') !== currentCiteStyle);
                });
            });

            cb.addEventListener('change', function() {
                cbSettings.disabled = !cb.checked;
                if (!cb.checked) cbSettings.checked = false;
                draw();
            });
            cbSettings.addEventListener('change', draw);

            draw();
            openDialog('Cite or share this page', body);
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
                if (input) {
                    input.addEventListener('input', function() {
                        applyAdjustments();
                    });
                    input.addEventListener('change', function() {
                        applyAdjustments();
                    });
                }
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
            viewportEl.classList.add('user-select-none');
            updateCursor();
            snap.hint = toast('Drag a box around the part of the page you want to keep. Press Esc to cancel.', null, null, 6000);
        }

        function cancelSnapshot() {
            if (!snap) return false;
            if (snap.box) snap.box.remove();
            // The drag instruction has done its job once the box is drawn (or cancelled)
            if (snap.hint && snap.hint.parentNode) snap.hint.remove();
            snap = null;
            container.classList.remove('is-snapshot');
            viewportEl.classList.remove('user-select-none');
            updateCursor();
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
            snap.box = el('div', 'position-absolute border border-2 border-primary bg-primary bg-opacity-10 pe-none');
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
                toast('That box was too small. Open Cite, choose “Clip part of the page” and drag a bigger box.');
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
            var vp = getPageViewport(p.page, renderScale, p.rotation);
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

                return canvasToBlob(out, 'image/jpeg', 0.88).then(function(blob) {
                    var name = fileNameFromUrl(pdfUrl).replace(/\.pdf$/i, '') + '-p' + labelFor(num) + '.jpg';
                    openSnapshotDialog(blob, out, num, rect, info, name);
                });
            });
        }

        function openSnapshotDialog(blob, canvas, num, rect, info, name) {
            var body = el('div');

            // The picture as large as the window allows. Clicking it opens the full-size
            // picture in a new tab, which matters when it had to be shrunk to fit.
            var blobUrl = URL.createObjectURL(blob);
            var previewLink = el('a', 'd-block text-center mb-2');
            previewLink.href = blobUrl;
            previewLink.target = '_blank';
            previewLink.rel = 'noopener';
            previewLink.title = 'Open the picture full size in a new tab';
            var previewImg = el('img', 'img-fluid border');
            // Room left in the viewer's document area once the window's text and buttons are in
            previewImg.style.maxHeight = 'max(12rem, calc(100vh - 28rem))';
            previewImg.src = blobUrl;
            previewImg.alt = info.title + ' — ' + describePage(num);
            previewLink.appendChild(previewImg);
            body.appendChild(previewLink);

            var note = el('p', 'text-muted', 'Clipped from ' + describePage(num) + '. The title, page and link are printed underneath, so the source travels with the picture.');
            var shrunk = el('span', '', ' It’s shown smaller to fit your screen; click it to see it full size.');
            shrunk.hidden = true;
            note.appendChild(shrunk);
            body.appendChild(note);
            // Mention the full-size view only when the picture had to be shrunk
            var checkShrunk = function() {
                if (!previewImg.clientWidth) return;
                shrunk.hidden = previewImg.naturalWidth <= previewImg.clientWidth + 1 && previewImg.naturalHeight <= previewImg.clientHeight + 1;
            };
            previewImg.addEventListener('load', checkShrunk);
            if (dialog) dialog.addEventListener('shown.bs.modal', checkShrunk, { once: true });

            var settingsSwitch = el('div', 'd-flex align-items-center gap-2 mb-3');
            var cbSettings = el('input', 'form-check-input fs-3 rounded-0 m-0');
            cbSettings.type = 'checkbox';
            cbSettings.checked = false;
            cbSettings.id = container.id + '-snap-settings';
            var labSettings = el('label', 'form-check-label', 'Shared link shows the page the way I see it (same zoom, rotation and image adjustments)');
            labSettings.htmlFor = cbSettings.id;
            settingsSwitch.appendChild(cbSettings);
            settingsSwitch.appendChild(labSettings);
            body.appendChild(settingsSwitch);

            function getShareUrl() {
                return pageLink(num, cbSettings.checked);
            }

            var actionsRow = el('div', 'd-flex flex-wrap gap-2');

            if (navigator.share) {
                var shareBtn = el('button', 'btn btn-primary px-3 py-2');
                shareBtn.type = 'button';
                shareBtn.innerHTML = iconLabel('bi-share', 'Share picture…');
                shareBtn.addEventListener('click', function() {
                    var currentUrl = getShareUrl();
                    var file = new File([blob], name, { type: 'image/jpeg' });
                    var shareData = {
                        title: info.title + ' (' + describePage(num) + ')',
                        text: info.title + ', ' + describePage(num) + ' — ' + info.site + ': ' + currentUrl,
                        url: currentUrl
                    };
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        shareData.files = [file];
                    }
                    navigator.share(shareData).catch(function() {});
                });
                actionsRow.appendChild(shareBtn);
            }

            var copyImgBtn = el('button', 'btn btn-outline-secondary px-3 py-2');
            copyImgBtn.type = 'button';
            copyImgBtn.innerHTML = iconLabel('bi-clipboard', 'Copy picture');
            copyImgBtn.addEventListener('click', function() {
                if (navigator.clipboard && window.ClipboardItem) {
                    var item = {};
                    item[blob.type || 'image/jpeg'] = blob;
                    navigator.clipboard.write([new ClipboardItem(item)]).then(function() {
                        toast('Picture copied. Paste it into an email, post or document.');
                    }).catch(function() {
                        canvasToBlob(canvas, 'image/png').then(function(pngBlob) {
                            return navigator.clipboard.write([new ClipboardItem({ 'image/png': pngBlob })]);
                        }).then(function() {
                            toast('Picture copied. Paste it into an email, post or document.');
                        }).catch(function() {
                            toast('Your browser won’t let the picture be copied. Use “Download picture” instead.');
                        });
                    });
                } else {
                    toast('This browser can’t copy pictures. Use “Download picture” instead.');
                }
            });
            actionsRow.appendChild(copyImgBtn);

            var copyLinkBtn = el('button', 'btn btn-outline-secondary px-3 py-2');
            copyLinkBtn.type = 'button';
            copyLinkBtn.innerHTML = iconLabel('bi-link-45deg', 'Copy link');
            copyLinkBtn.addEventListener('click', function() {
                copyWithFeedback(getShareUrl(), null, 'Link');
            });
            actionsRow.appendChild(copyLinkBtn);

            var downloadBtn = el('button', 'btn btn-outline-secondary px-3 py-2');
            downloadBtn.type = 'button';
            downloadBtn.innerHTML = iconLabel('bi-download', 'Download picture');
            downloadBtn.addEventListener('click', function() {
                saveBlob(blob, name);
                toast('Picture saved, with the title, page and link printed underneath.');
            });
            actionsRow.appendChild(downloadBtn);

            body.appendChild(actionsRow);

            openDialog('Share your clipping', body, 'xl');
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

        // Turn only the page being read (a sideways map or chart), not the whole document
        function rotate() {
            if (!pages.length) return;
            var p = pages[currentPage - 1];
            p.rotation = (p.rotation + 90) % 360;
            releasePage(p);
            measurePage(p);
            layout();
            applyFit();
            goToPage(currentPage, true);
            redrawThumb(p);
        }

        function toggleSpread() {
            if (!pages.length) return;
            var keep = currentPage;
            spread = !spread;
            setPressed(spreadBtn, spread);
            arrangePages();
            if (!fitMode) fitMode = 'width';
            layout();
            applyFit();
            goToPage(keep, true);
        }

        // "Expanded" mode for browsers without the Fullscreen API on elements (iPhone): the
        // viewer covers the window, and the page behind it stops scrolling
        var EXPANDED = ['is-expanded', 'position-fixed', 'top-0', 'start-0', 'w-100', 'h-100', 'm-0', 'rounded-0'];
        function setExpanded(on) {
            EXPANDED.forEach(function(c) { container.classList.toggle(c, on); });
            container.style.zIndex = on ? '1050' : '';
            container.style.maxHeight = on ? 'none' : '';
            document.body.classList.toggle('overflow-hidden', on);
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

        var handlers = {
            prev: function() { stepPage(-1); },
            next: function() { stepPage(1); },
            'zoom-in': function() { zoomBy(ZOOM_STEP); },
            'zoom-out': function() { zoomBy(1 / ZOOM_STEP); },
            'zoom-fit': function() { setFitMode('width'); },
            'zoom-page': function() { setFitMode('page'); },
            rotate: rotate,
            fullscreen: toggleFullscreen,
            sidebar: function() { if (sidebar && sidebar.hidden) openSidebar(); else closeSidebar(); },
            'sidebar-close': function() { closeSidebar(); if (sidebarBtn) sidebarBtn.focus(); },
            search: function() { if (findbar && !findbar.hidden) closeFindbar(); else openFindbar(); },
            adjust: function() { toggleAdjust(adjustbar && adjustbar.hidden); },
            'text-select': function() { setTextSelect(!textSelect); },
            cite: openCite,
            download: downloadPdf,
            spread: toggleSpread
        };
        Object.keys(handlers).forEach(function(name) {
            var btn = control(name, name === 'prev' || name === 'next' ? 'scouting-pdf-' + name : null);
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (!pdfDoc && name !== 'fullscreen' && name !== 'sidebar-close') return;
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
            viewportEl.classList.add('is-panning', 'user-select-none');
            updateCursor();
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
            viewportEl.classList.remove('is-panning', 'user-select-none');
            updateCursor();
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
                if (closeDialog() || cancelSnapshot()) {
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
            if (dialogOpen) return;
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
            loadingEl.classList.remove('d-none');
            loadingEl.innerHTML = '<div class="card text-center mx-auto" style="max-width: 28rem" data-pdf-role="error"><div class="card-body">' +
                '<h5 class="card-title">Unable to display this document</h5>' +
                '<p class="card-text">It may still be loading from storage, or it may be unavailable for a moment. Please try again.</p>' +
                '<button type="button" class="btn btn-primary px-3 py-2" data-pdf-control="retry">Try again</button>' +
                '</div></div>';
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
                    var pageEl = el('div', 'position-relative flex-shrink-0 bg-white shadow-sm');
                    pageEl.setAttribute('data-pdf-page', String(num));
                    pageEl.setAttribute('data-page-label', 'Page ' + labelFor(num));
                    pageEl.setAttribute('role', 'img');
                    pageEl.setAttribute('aria-label', 'Page ' + num + ' of ' + doc.numPages + (hasDistinctLabel(num) ? ', printed page ' + labelFor(num) : ''));
                    return { num: num, page: pageProxy, el: pageEl, canvas: null, rotation: 0, baseW: 0, baseH: 0, renderedKey: null };
                });
                if (urlSettings) {
                    applyUrlSettings(urlSettings, startPage);
                }
                arrangePages();
                measureBase();
                layout();
                if (fitMode) applyFit();
                else if (urlSettings && urlSettings.zoom && !fitMode && scale) setScale(scale);
                else applyFit();
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
                loadingEl.classList.remove('d-none');
                loadingEl.innerHTML = '<div class="spinner-border sm_green_color" aria-hidden="true"></div><div>Loading document... <span data-pdf-role="progress"></span></div>';
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
        var api = container.scoutingPdf || initViewer(container, target.page, target.settings);
        if (api) {
            if (target.settings && api.applySettings) api.applySettings(target.settings);
            api.goToPage(target.page);
        }
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

    // Toolbar layout follows the viewer's own width, not the window's: the theme's column can
    // leave the viewer much narrower than the screen. Each toolbar is a Bootstrap row of three
    // groups (start, middle, end) and every button keeps its label. Try the roomiest layout
    // first and keep the first where every group fits and each middle group is truly
    // centered, so it works with any font and label length.
    //   one:         start | middle | end, on one row
    //   splitTop:    start and end on one row, middle centered below them (top toolbar)
    //   splitBottom: middle centered on its own row, start and end below it (bottom toolbar)
    //   stack:       every group centered on its own row, page navigation first (bottom toolbar)
    //   stackTop:    every group centered on its own row, the tools last (top toolbar, small phones)
    var LAYOUT_CLASSES = ['col', 'col-auto', 'col-12', 'order-first', 'order-last', 'justify-content-center', 'justify-content-end'];
    var LAYOUTS = {
        one: { start: ['col'], middle: ['col-auto'], end: ['col', 'justify-content-end'] },
        splitTop: { start: ['col'], middle: ['col-12', 'order-last', 'justify-content-center'], end: ['col', 'justify-content-end'] },
        splitBottom: { start: ['col'], middle: ['col-12', 'order-first', 'justify-content-center'], end: ['col', 'justify-content-end'] },
        stack: { start: ['col-12', 'justify-content-center'], middle: ['col-12', 'order-first', 'justify-content-center'], end: ['col-12', 'justify-content-center'] },
        stackTop: { start: ['col-12', 'justify-content-center'], middle: ['col-12', 'order-last', 'justify-content-center'], end: ['col-12', 'justify-content-center'] }
    };
    var LAYOUT_ROWS = { one: 1, splitTop: 2, splitBottom: 2, stack: 3, stackTop: 3 };
    // [size, top toolbar layout, bottom toolbar layout], roomiest first
    var SIZES = [['lg', 'one', 'one'], ['md', 'one', 'splitBottom'], ['sm', 'splitTop', 'splitBottom'], ['xs', 'splitTop', 'stack'], ['xxs', 'stackTop', 'stack']];
    // Narrower than this, the sidebar slides over the pages instead of sitting beside them
    var SIDEBAR_BESIDE_MIN = 768;
    var SIDEBAR_OVER = ['position-absolute', 'top-0', 'bottom-0', 'start-0', 'z-3', 'shadow'];

    function applyLayout(row, name) {
        var layout = LAYOUTS[name];
        Array.prototype.forEach.call(row.children, function(group) {
            var classes = layout[group.getAttribute('data-pdf-group')];
            if (!classes) return;
            group.classList.remove.apply(group.classList, LAYOUT_CLASSES);
            group.classList.add.apply(group.classList, classes);
        });
        row.pdfLayout = name;
    }

    // Every group on its intended row, nothing sticking out, the middle group centered
    function layoutFits(row) {
        var tops = Array.prototype.map.call(row.children, function(g) { return g.getBoundingClientRect().top; })
            .sort(function(a, b) { return a - b; });
        var rows = 1;
        for (var i = 1; i < tops.length; i++) {
            if (tops[i] - tops[i - 1] > 8) rows++;
        }
        if (rows !== LAYOUT_ROWS[row.pdfLayout] || row.scrollWidth > row.clientWidth + 1) return false;
        var mid = row.querySelector('[data-pdf-group="middle"]');
        if (!mid) return true;
        var b = row.getBoundingClientRect();
        var m = mid.getBoundingClientRect();
        return Math.abs((m.left + m.right) - (b.left + b.right)) < 3;
    }

    function sizeViewer(container) {
        if (!container.clientWidth) return;
        var top = container.querySelector('[data-pdf-role="toolbar"] [data-pdf-role="toolbar-row"]');
        var bottom = container.querySelector('[data-pdf-role="bottom-toolbar"] [data-pdf-role="toolbar-row"]');
        var word = container.querySelector('[data-pdf-role="page-word"]');
        var display = container.querySelector('[data-pdf-role="page-display"]');
        for (var i = 0; i < SIZES.length; i++) {
            var phone = SIZES[i][0] === 'xs' || SIZES[i][0] === 'xxs';
            // On phones "Page" goes, and a printed page number may wrap under the page box
            if (word) word.classList.toggle('d-none', phone);
            if (display) display.classList.toggle('flex-wrap', phone);
            if (top) applyLayout(top, SIZES[i][1]);
            if (bottom) applyLayout(bottom, SIZES[i][2]);
            container.setAttribute('data-pdf-size', SIZES[i][0]);
            if ((!top || layoutFits(top)) && (!bottom || layoutFits(bottom))) break;
        }
        var sidebar = container.querySelector('[data-pdf-role="sidebar"]');
        if (sidebar) {
            var covers = container.clientWidth < SIDEBAR_BESIDE_MIN;
            SIDEBAR_OVER.forEach(function(c) { sidebar.classList.toggle(c, covers); });
        }
        container.pdfSizedWidth = container.clientWidth;
    }

    // On narrow screens the viewer spans the window; a desktop scrollbar isn't part of that
    // width. Phones have overlay scrollbars (width 0), and a zoomed-out phone page makes
    // innerWidth larger than the page for reasons that have nothing to do with a scrollbar,
    // so cap the gap at the width a real scrollbar has on this device.
    var deviceScrollbar = null;
    function setScrollbarWidth() {
        var root = document.documentElement;
        if (deviceScrollbar === null && document.body) {
            var probe = document.createElement('div');
            probe.style.cssText = 'position:absolute;top:-9999px;width:100px;height:100px;overflow:scroll';
            document.body.appendChild(probe);
            deviceScrollbar = probe.offsetWidth - probe.clientWidth;
            document.body.removeChild(probe);
        }
        var gap = Math.max(0, Math.min(window.innerWidth - root.clientWidth, deviceScrollbar || 0));
        root.style.setProperty('--scouting-pdf-scrollbar', gap + 'px');
    }

    function watchViewerSizes() {
        var viewers = allViewers();
        setScrollbarWidth();
        Array.prototype.forEach.call(viewers, sizeViewer);
        if ('ResizeObserver' in window) {
            // Only a change of width matters (the viewer's height changes with its own layout)
            var observer = new ResizeObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.target.clientWidth !== entry.target.pdfSizedWidth) sizeViewer(entry.target);
                });
            });
            Array.prototype.forEach.call(viewers, function(v) { observer.observe(v); });
        }
        window.addEventListener('resize', function() {
            setScrollbarWidth();
            if (!('ResizeObserver' in window)) Array.prototype.forEach.call(viewers, sizeViewer);
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
                initViewer(direct, target.page, target.settings);
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

    // This script loads in the footer, after the viewers' markup, so lay out their toolbars
    // right away rather than showing the wide layout first
    if (document.readyState === 'loading') {
        if (allViewers().length) watchViewerSizes();
        else document.addEventListener('DOMContentLoaded', watchViewerSizes);
        document.addEventListener('DOMContentLoaded', initAllViewers);
    } else {
        watchViewerSizes();
        initAllViewers();
    }
})();
