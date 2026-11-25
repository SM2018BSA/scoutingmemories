/* Buttons on the PDF Text screens that go through the whole site a batch at a time
   (data-sm-batch): the server handles one batch per request and says how far along it is. */
(function () {
    'use strict';

    function number(n) {
        return Number(n).toLocaleString('en-US');
    }

    function wire(button) {
        var status = document.getElementById(button.getAttribute('data-status'));
        var bar = document.getElementById(button.getAttribute('data-bar'));
        if (!status || !bar) {
            return;
        }

        function stop(message) {
            button.disabled = false;
            bar.classList.remove('sm-ocr-bar-moving');
            status.textContent = message;
        }

        function batch(page, count) {
            var body = new URLSearchParams();
            body.set('action', button.getAttribute('data-action'));
            body.set('_ajax_nonce', button.getAttribute('data-nonce'));
            body.set('page', String(page));
            body.set('count', String(count));
            fetch(button.getAttribute('data-url'), { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (response) { return response.json(); })
                .then(function (answer) {
                    if (!answer || !answer.success) {
                        throw new Error('refused');
                    }
                    var data = answer.data;
                    var percent = data.total > 0 ? Math.round(data.checked / data.total * 100) : 100;
                    bar.style.display = '';
                    bar.setAttribute('aria-valuenow', String(percent));
                    bar.firstElementChild.style.width = percent + '%';
                    if (data.finished) {
                        stop(data.message);
                    } else {
                        status.textContent = number(data.checked) + ' of ' + number(data.total) + ' posts checked';
                        batch(page + 1, data.count);
                    }
                })
                .catch(function () {
                    stop('This stopped before it finished. Press the button to start again; nothing is lost.');
                });
        }

        button.addEventListener('click', function () {
            button.disabled = true;
            bar.classList.add('sm-ocr-bar-moving');
            status.textContent = 'Starting…';
            batch(1, 0);
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('button[data-sm-batch]'), wire);
})();
