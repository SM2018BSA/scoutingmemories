(function() {
    'use strict';

    if (typeof window.pdfjsLib === 'undefined') {
        console.warn('Scouting PDF: pdfjsLib is not loaded');
        return;
    }

    if (window.ScoutingPdfConfig && window.ScoutingPdfConfig.workerUrl) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = window.ScoutingPdfConfig.workerUrl;
    }

    function initViewer(container) {
        if (container.dataset.initialized === 'true') return;
        container.dataset.initialized = 'true';

        var pdfUrl = container.getAttribute('data-pdf-url');
        if (!pdfUrl) return;

        var canvas = container.querySelector('.scouting-pdf-canvas');
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
                var baseViewport = page.getViewport({ scale: 1 });

                if (autoFit && viewportEl) {
                    var availableWidth = viewportEl.clientWidth - 40;
                    if (availableWidth > 200) {
                        scale = availableWidth / baseViewport.width;
                        if (scale > 2.0) scale = 2.0;
                        if (scale < 0.6) scale = 0.6;
                    }
                }

                var viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height * dpr;
                canvas.width = viewport.width * dpr;
                canvas.style.width = viewport.width + 'px';
                canvas.style.height = viewport.height + 'px';

                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                var renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };

                var renderTask = page.render(renderContext);
                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                }).catch(function(err) {
                    console.error('Render error:', err);
                    pageRendering = false;
                });
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

        // Keyboard navigation when hovering
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
                }, 200);
            }
        });

        // Load document
        pdfjsLib.getDocument({
            url: pdfUrl,
            cMapUrl: (window.ScoutingPdfConfig && window.ScoutingPdfConfig.cMapUrl) || undefined,
            cMapPacked: true
        }).promise.then(function(loadedDoc) {
            pdfDoc = loadedDoc;
            updateUI();
            renderPage(pageNum);
        }).catch(function(error) {
            console.error('Error loading PDF document:', error);
            if (loadingEl) {
                loadingEl.innerHTML = '<div class="scouting-pdf-error">' +
                    '<p>Unable to display PDF directly.</p>' +
                    '<a href="' + encodeURI(pdfUrl) + '" class="scouting-pdf-btn" download target="_blank">' +
                    'Download PDF to view</a></div>';
            }
        });
    }

    function initAllViewers() {
        // Find custom viewers
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
