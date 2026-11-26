/* "Text of this document": out of sight until the reader presses Read text in the viewer,
   then one page of text at a time. htmx (open source) fetches a page and swaps it in; this
   file is the glue: the Read text button, and links from the site search (#page=7&find=word)
   that open that page's text with the word marked. */
(function () {
    'use strict';

    var htmx = window.htmx;
    if (!htmx) {
        return;
    }
    // Only the page pieces from this site are swapped in; nothing in them is run as script
    htmx.config.allowEval = false;
    htmx.config.allowScriptTags = false;
    htmx.config.includeIndicatorStyles = false;
    htmx.config.historyEnabled = false;

    function sectionFor(key, value) {
        return document.querySelector('[data-pdf-text][' + key + '="' + String(value).replace(/[^a-z0-9]/gi, '') + '"]');
    }

    function buttonFor(section) {
        return document.querySelector('[data-pdf-control="read-text"][data-pdf-text-doc="' + section.getAttribute('data-pdf-text-doc') + '"]');
    }

    function shownPage(section) {
        var piece = section.querySelector('[data-pdf-text-page]');
        return piece ? parseInt(piece.getAttribute('data-pdf-text-page'), 10) : 0;
    }

    function setOpen(section, open) {
        section.hidden = !open;
        var button = buttonFor(section);
        if (button) {
            button.setAttribute('aria-pressed', open ? 'true' : 'false');
            button.classList.toggle('active', open);
        }
    }

    // Fetch one page of text into the section; answers when it is in place
    function load(section, page) {
        if (!page || shownPage(section) === page) {
            return Promise.resolve();
        }
        return htmx.ajax('GET', section.getAttribute('data-pdf-text-url') + '&page=' + page, {
            target: section.querySelector('[data-pdf-text-area]'),
            swap: 'innerHTML'
        });
    }

    function mark(section, word) {
        var body = section.querySelector('[data-pdf-text-body]');
        var needle = String(word || '').toLowerCase();
        if (!body || !needle) {
            return;
        }
        var walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT, null);
        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(function (node) {
            var text = node.nodeValue;
            var lower = text.toLowerCase();
            var at = lower.indexOf(needle);
            if (at < 0) return;
            var pieces = document.createDocumentFragment();
            var from = 0;
            while (at >= 0) {
                pieces.appendChild(document.createTextNode(text.slice(from, at)));
                var found = document.createElement('mark');
                found.textContent = text.slice(at, at + needle.length);
                pieces.appendChild(found);
                from = at + needle.length;
                at = lower.indexOf(needle, from);
            }
            pieces.appendChild(document.createTextNode(text.slice(from)));
            node.parentNode.replaceChild(pieces, node);
        });
    }

    // The Read text button in a viewer: show the text of the page the reader is on, or put
    // the section away again
    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('[data-pdf-control="read-text"]');
        if (!button) {
            return;
        }
        var section = sectionFor('data-pdf-text-doc', button.getAttribute('data-pdf-text-doc'));
        if (!section) {
            return;
        }
        if (!section.hidden) {
            setOpen(section, false);
            return;
        }
        var viewer = button.closest('[data-pdf-viewer]');
        var page = viewer && viewer.scoutingPdf && viewer.scoutingPdf.ready ? viewer.scoutingPdf.currentPage() : 0;
        setOpen(section, true);
        load(section, page).then(function () {
            section.scrollIntoView({ block: 'start', behavior: 'smooth' });
        });
    });

    // #page=7&find=word (a search result) or #pdf2-page=7&find=word
    function followLink() {
        var params = {};
        String(window.location.hash || '').replace(/^#/, '').split('&').forEach(function (part) {
            var kv = part.split('=');
            if (kv[0]) {
                try {
                    params[decodeURIComponent(kv[0])] = decodeURIComponent(kv[1] || '');
                } catch (e) { /* a broken link opens nothing */ }
            }
        });
        if (!params.find) {
            return;
        }
        Object.keys(params).some(function (key) {
            var found = /^(?:pdf(\d+)-)?page$/i.exec(key);
            var page = found ? parseInt(params[key], 10) : 0;
            var section = page ? sectionFor('data-pdf-text-viewer', found[1] || 1) : null;
            if (!section) {
                return false;
            }
            setOpen(section, true);
            load(section, page).then(function () {
                mark(section, params.find);
            });
            return true;
        });
    }

    // A section the post arrived with open (a link to a page of text): its button shows it
    Array.prototype.forEach.call(document.querySelectorAll('[data-pdf-text]:not([hidden])'), function (section) {
        setOpen(section, true);
    });
    window.addEventListener('hashchange', followLink);
    followLink();
})();
