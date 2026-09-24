(function() {
    'use strict';

    // Resolve PDF.js across different vendor build export patterns
    var pdfLib = window.pdfjsLib || window.pdfjsDistBuildPdf || window.PDFJS || (typeof window['pdfjs-dist/build/pdf'] !== 'undefined' ? window['pdfjs-dist/build/pdf'] : null);
    if (!pdfLib && typeof PDFJS !== 'undefined') {
        pdfLib = PDFJS;
    }

    if (!pdfLib) {
        console.warn('Scouting PDF: PDF.js library is not loaded');
        return;
    }

    // Alias window.pdfjsLib so standard references resolve
    window.pdfjsLib = pdfLib;

    var isHttps = (window.location.protocol === 'https:');
    if (isHttps && window.ScoutingPdfConfig) {
        if (window.ScoutingPdfConfig.workerUrl) window.ScoutingPdfConfig.workerUrl = window.ScoutingPdfConfig.workerUrl.replace(/^http:/i, 'https:');
        if (window.ScoutingPdfConfig.cMapUrl) window.ScoutingPdfConfig.cMapUrl = window.ScoutingPdfConfig.cMapUrl.replace(/^http:/i, 'https:');
        if (window.ScoutingPdfConfig.restUrl) window.ScoutingPdfConfig.restUrl = window.ScoutingPdfConfig.restUrl.replace(/^http:/i, 'https:');
        if (window.ScoutingPdfConfig.ajaxUrl) window.ScoutingPdfConfig.ajaxUrl = window.ScoutingPdfConfig.ajaxUrl.replace(/^http:/i, 'https:');
    }

    // Parse and render in a real Web Worker (off the main thread). If the browser refuses to
    // start the worker, PDF.js falls back to loading workerSrc in-page by itself.
    if (window.ScoutingPdfConfig && window.ScoutingPdfConfig.workerUrl) {
        if (pdfLib.GlobalWorkerOptions) {
            pdfLib.GlobalWorkerOptions.workerSrc = window.ScoutingPdfConfig.workerUrl;
        }
        if (pdfLib.PDFJS) {
            pdfLib.PDFJS.workerSrc = window.ScoutingPdfConfig.workerUrl;
        }
        if (window.PDFJS) {
            window.PDFJS.workerSrc = window.ScoutingPdfConfig.workerUrl;
        }
    }

    // Largest canvas browsers reliably paint; bigger canvases silently render blank
    var MAX_CANVAS_PIXELS = 16777216;
    var MIN_SCALE = 0.1;
    // High enough to study fine detail on small artifact scans (patches, neckerchiefs, photos)
    var MAX_SCALE = 5.0;
    var ZOOM_STEP = 1.25;
    var PAGE_GAP = 16; // matches the 1rem gap between pages in the CSS

    var nativeFullscreen = !!(document.fullscreenEnabled || document.webkitFullscreenEnabled);

    function getFullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || null;
    }

    // Viewport helper for both PDF.js v2.0 (positional args) and v2.1+ (options object).
    // User rotation is added to the page's own rotation.
    function getPageViewport(page, scaleVal, rotation) {
        var rot = ((page.rotate || 0) + (rotation || 0)) % 360;
        try {
            var vp = page.getViewport(scaleVal, rot);
            if (vp && !isNaN(vp.width) && vp.width > 0) return vp;
        } catch (e) {}
        try {
            var vp2 = page.getViewport({ scale: scaleVal, rotation: rot });
            if (vp2 && !isNaN(vp2.width) && vp2.width > 0) return vp2;
        } catch (e) {}
        return page.getViewport(scaleVal);
    }

    function clamp(val, min, max) {
        return Math.max(min, Math.min(max, val));
    }

    // Bootstrap tooltips (from the theme's bootstrap.bundle) explain what each control does.
    // They're placed inside the viewer so they still show in fullscreen, and always open
    // below the toolbar because the card clips anything above it.
    function initTooltips(container) {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;
        var els = container.querySelectorAll('[data-bs-toggle="tooltip"]');
        for (var i = 0; i < els.length; i++) {
            (function(el) {
                var tip = window.bootstrap.Tooltip.getOrCreateInstance(el, {
                    container: container,
                    placement: 'bottom',
                    fallbackPlacements: ['bottom'],
                    trigger: 'hover focus',
                    delay: { show: 250, hide: 0 }
                });
                // Don't leave the hint covering the page after the button is used
                el.addEventListener('click', function() { tip.hide(); });
            })(els[i]);
        }
    }

    function initViewer(container) {
        if (container.dataset.initialized === 'true') return;
        container.dataset.initialized = 'true';

        var pdfUrl = container.getAttribute('data-pdf-url');
        if (!pdfUrl) return;

        if (isHttps && pdfUrl.indexOf('http://') === 0) {
            pdfUrl = pdfUrl.replace(/^http:/i, 'https:');
        }

        function control(name, legacyClass) {
            return container.querySelector('[data-pdf-control="' + name + '"]' + (legacyClass ? ', .' + legacyClass : ''));
        }

        var loadingEl = container.querySelector('[data-pdf-role="loading"], .scouting-pdf-loading');
        var viewportEl = container.querySelector('[data-pdf-role="viewport"], .scouting-pdf-viewport');
        var pagesEl = container.querySelector('[data-pdf-role="pages"]');
        var pageNumEl = control('page-input', 'scouting-pdf-page-input');
        var totalPagesEl = control('total-pages', 'scouting-pdf-total-pages');
        var zoomLevelEl = control('zoom-level', 'scouting-pdf-zoom-level');
        var prevBtn = control('prev', 'scouting-pdf-prev');
        var nextBtn = control('next', 'scouting-pdf-next');
        var zoomInBtn = control('zoom-in', 'scouting-pdf-zoom-in');
        var zoomOutBtn = control('zoom-out', 'scouting-pdf-zoom-out');
        var zoomFitBtn = control('zoom-fit', 'scouting-pdf-zoom-fit');
        var zoomPageBtn = control('zoom-page');
        var rotateBtn = control('rotate');
        var fullscreenBtn = control('fullscreen', 'scouting-pdf-fullscreen');

        if (!viewportEl) return;

        initTooltips(container);

        // Page HTML cached before v1.2 has a single canvas instead of the page column
        if (!pagesEl) {
            pagesEl = document.createElement('div');
            pagesEl.setAttribute('data-pdf-role', 'pages');
            viewportEl.appendChild(pagesEl);
        }
        var legacyWrapper = container.querySelector('[data-pdf-role="canvas-wrapper"], .scouting-pdf-canvas-wrapper');
        if (legacyWrapper) legacyWrapper.style.display = 'none';

        var pdfDoc = null;
        var pages = [];          // { num, page, el, canvas, baseW, baseH, renderedKey }
        var currentPage = 1;
        var scale = 1.0;
        var rotation = 0;
        var fitMode = 'width';   // 'width' | 'page' | null (manual zoom)
        var renderQueue = [];
        var rendering = false;
        var renderTimer = null;
        var firstRenderDone = false;
        var lastViewportWidth = 0;

        function setState(state) {
            container.setAttribute('data-pdf-state', state);
        }

        function isExpanded() {
            return getFullscreenElement() === container || container.classList.contains('is-expanded');
        }

        function updateUI() {
            var total = pdfDoc ? pdfDoc.numPages : 0;
            if (pageNumEl) {
                pageNumEl.value = currentPage;
                if (total) pageNumEl.max = total;
            }
            if (totalPagesEl && total) totalPagesEl.textContent = total;
            if (zoomLevelEl) zoomLevelEl.textContent = Math.round(scale * 100) + '%';
            if (prevBtn) prevBtn.disabled = (currentPage <= 1);
            if (nextBtn) nextBtn.disabled = (!total || currentPage >= total);
            if (zoomInBtn) zoomInBtn.disabled = (scale >= MAX_SCALE - 0.001);
            if (zoomOutBtn) zoomOutBtn.disabled = (scale <= MIN_SCALE + 0.001);
            setPressed(zoomFitBtn, fitMode === 'width');
            setPressed(zoomPageBtn, fitMode === 'page');
        }

        // Bootstrap's .active shows the selected fit mode in the site green
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
            });
        }

        function computeFitScale(mode) {
            var avail = getAvailableSize();
            if (!pages.length || avail.width <= 0) return scale;
            var fit;
            if (mode === 'page') {
                var p = pages[currentPage - 1];
                fit = Math.min(avail.width / p.baseW, avail.height / p.baseH);
            } else {
                fit = avail.width / typicalPageWidth();
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

        function goToPage(num, force) {
            if (!pages.length) return;
            num = clamp(num, 1, pages.length);
            if (num === currentPage && !force) return;
            currentPage = num;
            viewportEl.scrollTop = Math.max(0, pages[num - 1].el.offsetTop - PAGE_GAP / 2);
            updateUI();
            scheduleRender();
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
            var ctx = canvas.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

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
                    // then start at the top of page 1
                    applyFit();
                    viewportEl.scrollTop = 0;
                    viewportEl.scrollLeft = 0;
                    lastViewportWidth = viewportEl.clientWidth;
                }
                if (p.renderedKey !== renderKey()) scheduleRender();
                renderNext();
            }

            var task = p.page.render({ canvasContext: ctx, viewport: viewport });
            var promise = task.promise ? task.promise : task;
            promise.then(function() {
                if (p.canvas && p.canvas.parentNode) p.canvas.parentNode.removeChild(p.canvas);
                p.el.appendChild(canvas);
                p.canvas = canvas;
                p.renderedKey = key;
                done();
            }).catch(function(err) {
                console.error('Scouting PDF: render error on page ' + p.num, err);
                done();
            });
        }

        function setupDocument(doc) {
            pdfDoc = doc;
            var requests = [];
            for (var i = 1; i <= doc.numPages; i++) requests.push(doc.getPage(i));

            return Promise.all(requests).then(function(pageProxies) {
                pagesEl.innerHTML = '';
                pagesEl.style.visibility = 'hidden';
                pages = pageProxies.map(function(pageProxy, idx) {
                    var el = document.createElement('div');
                    el.setAttribute('data-pdf-page', String(idx + 1));
                    el.setAttribute('data-page-label', 'Page ' + (idx + 1));
                    el.setAttribute('role', 'img');
                    el.setAttribute('aria-label', 'Page ' + (idx + 1) + ' of ' + doc.numPages);
                    pagesEl.appendChild(el);
                    return { num: idx + 1, page: pageProxy, el: el, canvas: null, baseW: 0, baseH: 0, renderedKey: null };
                });
                measureBase();
                layout();
                applyFit();
                viewportEl.scrollTop = 0;
                updateUI();
                refreshVisiblePages();
            });
        }

        // ---- Controls ---------------------------------------------------------

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

        if (prevBtn) prevBtn.addEventListener('click', function() { goToPage(currentPage - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function() { goToPage(currentPage + 1); });
        if (zoomInBtn) zoomInBtn.addEventListener('click', function() { zoomBy(ZOOM_STEP); });
        if (zoomOutBtn) zoomOutBtn.addEventListener('click', function() { zoomBy(1 / ZOOM_STEP); });
        if (zoomFitBtn) zoomFitBtn.addEventListener('click', function() { setFitMode('width'); });
        if (zoomPageBtn) zoomPageBtn.addEventListener('click', function() { setFitMode('page'); });
        if (rotateBtn) rotateBtn.addEventListener('click', rotate);
        if (fullscreenBtn) fullscreenBtn.addEventListener('click', toggleFullscreen);
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

        // Click and drag to move around a page (mouse only; touch scrolls natively)
        var pan = null;
        viewportEl.addEventListener('pointerdown', function(e) {
            if (e.pointerType !== 'mouse' || e.button !== 0 || !pages.length) return;
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
            if (!pan || e.pointerId !== pan.id) return;
            viewportEl.scrollLeft = pan.left - (e.clientX - pan.x);
            viewportEl.scrollTop = pan.top - (e.clientY - pan.y);
        });
        function endPan(e) {
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
            if (e.target === pageNumEl || e.ctrlKey || e.metaKey || e.altKey) return;
            var handled = true;
            switch (e.key) {
                case 'ArrowRight':
                case 'PageDown':
                    goToPage(currentPage + 1);
                    break;
                case 'ArrowLeft':
                case 'PageUp':
                    goToPage(currentPage - 1);
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
                case 'Escape':
                    if (container.classList.contains('is-expanded')) setExpanded(false);
                    else handled = false;
                    break;
                default:
                    handled = false;
            }
            if (handled) e.preventDefault();
        });

        // Re-fit when the viewer's width changes. Height-only changes (e.g. a browser toolbar
        // appearing) don't affect fit-to-width, so they're ignored outside fullscreen.
        var resizeTimeout;
        window.addEventListener('resize', function() {
            if (!pdfDoc) return;
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (viewportEl.clientWidth !== lastViewportWidth || isExpanded()) {
                    onViewerResized();
                }
            }, 150);
        });

        // ---- Loading ----------------------------------------------------------

        // Compute proxy URL
        var proxyUrl = '';
        if (window.ScoutingPdfConfig) {
            if (window.ScoutingPdfConfig.restUrl) {
                proxyUrl = window.ScoutingPdfConfig.restUrl + (window.ScoutingPdfConfig.restUrl.indexOf('?') === -1 ? '?' : '&') + 'pdf_url=' + encodeURIComponent(pdfUrl);
            } else if (window.ScoutingPdfConfig.ajaxUrl) {
                proxyUrl = window.ScoutingPdfConfig.ajaxUrl + '?action=scouting_pdf_proxy&pdf_url=' + encodeURIComponent(pdfUrl);
            }
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
            loadingEl.innerHTML = '<div class="card shadow-sm text-center p-4 mx-auto" style="max-width: 28rem;" data-pdf-role="error">' +
                '<div class="h6 mb-2">Unable to display this document</div>' +
                '<p class="text-muted small mb-3">It may still be loading from storage or be temporarily unavailable. Please try again.</p>' +
                '<div><button type="button" class="btn btn-sm btn-sm-green" data-pdf-control="retry">Try again</button></div>' +
                '</div>';
            var retryBtn = loadingEl.querySelector('[data-pdf-control="retry"]');
            if (retryBtn) retryBtn.addEventListener('click', function() { loadDoc(initialUrl, false); });
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

            var docInit = {
                url: urlToLoad,
                cMapUrl: (window.ScoutingPdfConfig && window.ScoutingPdfConfig.cMapUrl) || undefined,
                cMapPacked: true,
                disableRange: isRetry
            };

            try {
                var loadingTask = pdfLib.getDocument(docInit);
                loadingTask.onProgress = function(progress) {
                    if (progress) showProgress(progress.loaded || 0, progress.total || 0);
                };
                var docPromise = loadingTask.promise ? loadingTask.promise : loadingTask;

                docPromise.then(setupDocument).catch(function(error) {
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
            } catch (err) {
                console.error('Scouting PDF: Exception calling getDocument:', err);
                showError();
            }
        }

        loadDoc(initialUrl, false);
    }

    // Only download a PDF once its viewer is near the screen, so posts with several
    // documents don't pull every file on page load
    function initAllViewers() {
        var viewers = document.querySelectorAll('[data-pdf-viewer], .scouting-pdf-container');

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllViewers);
    } else {
        initAllViewers();
    }
})();
