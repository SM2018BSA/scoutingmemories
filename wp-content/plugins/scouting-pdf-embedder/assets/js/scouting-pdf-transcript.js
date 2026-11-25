/* The "Text of this document" section: a link from the site search (#page=7&find=word)
   opens that page's text and marks the word. */
(function () {
    'use strict';

    function parse(hash) {
        var params = {};
        String(hash || '').replace(/^#/, '').split('&').forEach(function (part) {
            var kv = part.split('=');
            if (kv[0]) {
                try {
                    params[decodeURIComponent(kv[0])] = decodeURIComponent(kv[1] || '');
                } catch (e) { /* a broken link marks nothing */ }
            }
        });
        var target = null;
        Object.keys(params).forEach(function (key) {
            var m = /^(?:pdf(\d+)-)?page$/i.exec(key);
            if (m && !target) {
                target = { viewer: m[1] ? parseInt(m[1], 10) : 1, page: parseInt(params[key], 10), find: params.find || '' };
            }
        });
        return target && target.page ? target : null;
    }

    function unmark(root) {
        Array.prototype.forEach.call(root.querySelectorAll('mark[data-pdf-text-mark]'), function (mark) {
            var parent = mark.parentNode;
            parent.replaceChild(document.createTextNode(mark.textContent), mark);
            parent.normalize();
        });
    }

    function mark(body, word) {
        var needle = word.toLowerCase();
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
                found.setAttribute('data-pdf-text-mark', '');
                found.textContent = text.slice(at, at + needle.length);
                pieces.appendChild(found);
                from = at + needle.length;
                at = lower.indexOf(needle, from);
            }
            pieces.appendChild(document.createTextNode(text.slice(from)));
            node.parentNode.replaceChild(pieces, node);
        });
    }

    function follow() {
        var section = document.querySelector('[data-pdf-text]');
        var target = parse(window.location.hash);
        if (!section || !target || !target.find) return;
        unmark(section);
        var details = section.querySelector('details[data-pdf-text-viewer="' + target.viewer + '"][data-pdf-text-page="' + target.page + '"]');
        var body = details && details.querySelector('[data-pdf-text-body]');
        if (!body) return;
        details.open = true;
        mark(body, target.find);
    }

    window.addEventListener('hashchange', follow);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', follow);
    else follow();
})();
