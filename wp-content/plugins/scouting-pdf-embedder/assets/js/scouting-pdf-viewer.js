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

    // Configure worker options - disable external worker to eliminate CSP, mixed content, and worker blocking
    if (pdfLib.PDFJS) {
        pdfLib.PDFJS.disableWorker = true;
    }
    if (window.PDFJS) {
        window.PDFJS.disableWorker = true;
    }

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

    // Safe getViewport helper compatible with both PDF.js v2.0 (numeric) and v2.1+ (options object)
    function getPageViewport(page, scaleVal) {
        try {
            var vp = page.getViewport(scaleVal);
            if (vp && !isNaN(vp.width) && vp.width > 0) return vp;
        } catch (e) {}
        try {
            var vp2 = page.getViewport({ scale: scaleVal });
            if (vp2 && !isNaN(vp2.width) && vp2.width > 0) return vp2;
        } catch (e) {}
        return page.getViewport(scaleVal);
    }

    function initViewer(container) {
        if (container.dataset.initialized === 'true') return;
        container.dataset.initialized = 'true';

        var pdfUrl = container.getAttribute('data-pdf-url');
        if (!pdfUrl) return;

        if (isHttps && pdfUrl.indexOf('http://') === 0) {
            pdfUrl = pdfUrl.replace(/^http:/i, 'https:');
        }

        var canvas = container.querySelector('.scouting-pdf-canvas');
        var canvasWrapper = container.querySelector('.scouting-pdf-canvas-wrapper');
        var ctx = canvas ? canvas.getContext('2d') : null;
        var loadingEl = container.querySelector('.scouting-pdf-loading');
        var viewportEl = container.querySelector('.scouting-pdf-viewport');
        var pageNumEl = container.querySelector('.scouting-pdf-page-input');
        var totalPagesEl = container.querySelector('.scouting-pdf-total-pages');
        var zoomLevelEl = container.querySelector('.scouting-pdf-zoom-level');
        var prevBtn = container.querySelector('.scouting-pdf-prev');
        var nextBtn = container.querySelector('.scouting-pdf-next');
        var zoomInBtn = container.querySelector('.scouting-pdf-zoom-in');
        var zoomOutBtn = container.querySelector('.scouting-pdf-zoom-out');
        var zoomFitBtn = container.querySelector('.scouting-pdf-zoom-fit');
        var fullscreenBtn = container.querySelector('.scouting-pdf-fullscreen');

        // Ensure canvas wrapper is hidden until first render completes
        if (canvasWrapper) {
            canvasWrapper.style.display = 'none';
        }

        var pdfDoc = null;
        var pageNum = 1;
        var pageRendering = false;
        var pageNumPending = null;
        var scale = 1.0;
        var autoFit = true;

        function updateUI() {
            if (pageNumEl) pageNumEl.value = pageNum;
            if (totalPagesEl && pdfDoc) totalPagesEl.textContent = pdfDoc.numPages;
            if (zoomLevelEl) zoomLevelEl.textContent = Math.round(scale * 100) + '%';
            if (prevBtn) prevBtn.disabled = (pageNum <= 1);
            if (nextBtn) nextBtn.disabled = (pdfDoc && pageNum >= pdfDoc.numPages);
        }

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function(page) {
                var dpr = window.devicePixelRatio || 1;
                var baseViewport = getPageViewport(page, 1.0);

                if (autoFit && viewportEl) {
                    var availableWidth = viewportEl.clientWidth - 48;
                    if (availableWidth > 200 && baseViewport && baseViewport.width > 0) {
                        scale = availableWidth / baseViewport.width;
                        if (scale > 2.5) scale = 2.5;
                        if (scale < 0.5) scale = 0.5;
                    }
                }

                var viewport = getPageViewport(page, scale);
                canvas.height = Math.floor(viewport.height * dpr);
                canvas.width = Math.floor(viewport.width * dpr);
                canvas.style.width = Math.floor(viewport.width) + 'px';
                canvas.style.height = Math.floor(viewport.height) + 'px';

                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                var renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };

                var renderTask = page.render(renderContext);
                var renderPromise = renderTask.promise ? renderTask.promise : renderTask;
                renderPromise.then(function() {
                    pageRendering = false;
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (canvasWrapper) {
                        canvasWrapper.style.display = 'inline-block';
                        canvasWrapper.classList.add('scouting-pdf-loaded');
                    }
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                }).catch(function(err) {
                    console.error('Render error:', err);
                    pageRendering = false;
                });
            }).catch(function(pageErr) {
                console.error('GetPage error:', pageErr);
                pageRendering = false;
            });

            updateUI();
        }

        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        function onPrevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }

        function onNextPage() {
            if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }

        function onZoomIn() {
            autoFit = false;
            scale = Math.min(3.0, scale + 0.25);
            queueRenderPage(pageNum);
        }

        function onZoomOut() {
            autoFit = false;
            scale = Math.max(0.5, scale - 0.25);
            queueRenderPage(pageNum);
        }

        function onZoomFit() {
            autoFit = true;
            queueRenderPage(pageNum);
        }

        function onPageInput(e) {
            var val = parseInt(e.target.value, 10);
            if (!isNaN(val) && val >= 1 && pdfDoc && val <= pdfDoc.numPages) {
                pageNum = val;
                queueRenderPage(pageNum);
            } else {
                e.target.value = pageNum;
            }
        }

        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                if (container.requestFullscreen) {
                    container.requestFullscreen();
                } else if (container.webkitRequestFullscreen) {
                    container.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        // Attach listeners
        if (prevBtn) prevBtn.addEventListener('click', onPrevPage);
        if (nextBtn) nextBtn.addEventListener('click', onNextPage);
        if (zoomInBtn) zoomInBtn.addEventListener('click', onZoomIn);
        if (zoomOutBtn) zoomOutBtn.addEventListener('click', onZoomOut);
        if (zoomFitBtn) zoomFitBtn.addEventListener('click', onZoomFit);
        if (fullscreenBtn) fullscreenBtn.addEventListener('click', toggleFullscreen);

        if (pageNumEl) {
            pageNumEl.addEventListener('change', onPageInput);
            pageNumEl.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') onPageInput(e);
            });
        }

        // Keyboard navigation when focused or hovering
        container.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowRight' || e.key === 'PageDown') {
                onNextPage();
                e.preventDefault();
            } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                onPrevPage();
                e.preventDefault();
            }
        });

        // Window resize re-fit
        var resizeTimeout;
        window.addEventListener('resize', function() {
            if (autoFit && pdfDoc) {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    queueRenderPage(pageNum);
                }, 150);
            }
        });

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

        // Load document with transparent proxy retry fallback
        function loadDoc(urlToLoad, isRetry) {
            if (loadingEl) {
                loadingEl.style.display = 'flex';
                loadingEl.innerHTML = '<div class="scouting-pdf-spinner"></div><div class="scouting-pdf-loading-text">Loading document...</div>';
            }
            if (canvasWrapper) {
                canvasWrapper.style.display = 'none';
            }

            var docInit = {
                url: urlToLoad,
                cMapUrl: (window.ScoutingPdfConfig && window.ScoutingPdfConfig.cMapUrl) || undefined,
                cMapPacked: true,
                disableRange: isRetry
            };

            try {
                var loadingTask = pdfLib.getDocument(docInit);
                var docPromise = loadingTask.promise ? loadingTask.promise : loadingTask;

                docPromise.then(function(loadedDoc) {
                    pdfDoc = loadedDoc;
                    updateUI();
                    renderPage(pageNum);
                }).catch(function(error) {
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

                    if (loadingEl) {
                        loadingEl.innerHTML = '<div class="scouting-pdf-error">' +
                            '<div class="scouting-pdf-error-title">Unable to preview document</div>' +
                            '<p class="scouting-pdf-error-desc">This document can still be downloaded and viewed directly on your device.</p>' +
                            '<div class="scouting-pdf-error-actions">' +
                            '<a href="' + encodeURI(pdfUrl) + '" class="scouting-pdf-btn scouting-pdf-download-btn" download target="_blank">' +
                            '<svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>' +
                            'Download PDF</a>' +
                            '<a href="' + encodeURI(pdfUrl) + '" class="scouting-pdf-btn" target="_blank">Open in New Tab</a>' +
                            '</div></div>';
                    }
                });
            } catch (err) {
                console.error('Scouting PDF: Exception calling getDocument:', err);
            }
        }

        loadDoc(initialUrl, false);
    }

    function initAllViewers() {
        var viewers = document.querySelectorAll('.scouting-pdf-container');
        for (var i = 0; i < viewers.length; i++) {
            initViewer(viewers[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllViewers);
    } else {
        initAllViewers();
    }
})();
