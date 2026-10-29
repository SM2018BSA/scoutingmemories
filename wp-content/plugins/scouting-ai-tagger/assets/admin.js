// Settings > AI Tagging Assistant: the connection test and the step-by-step index send.
// Each step is one REST request, so PHP never runs long enough to time out.
'use strict';

(function () {
  const config = window.scoutingAiTagger || {};
  const progress = document.getElementById('sm-tagger-progress');
  const sendButton = document.getElementById('sm-tagger-send');
  const testButton = document.getElementById('sm-tagger-test');
  if (!progress || !sendButton || !testButton) return;

  function say(text, isError) {
    const line = document.createElement('p');
    line.textContent = (isError ? '✗ ' : '✓ ') + text;
    if (isError) line.style.color = '#b32d2e';
    progress.appendChild(line);
  }

  async function step(name, batch) {
    const response = await fetch(config.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
      body: JSON.stringify({ step: name, batch: batch || '' }),
    });
    let answer = {};
    try { answer = await response.json(); } catch (e) { /* no JSON came back */ }
    if (!response.ok || !answer.ok) {
      throw new Error(answer.message || ('The site answered ' + response.status + '.'));
    }
    return answer;
  }

  testButton.addEventListener('click', async () => {
    progress.textContent = '';
    testButton.disabled = true;
    try {
      say((await step('status')).message, false);
    } catch (problem) {
      say(problem.message, true);
    }
    testButton.disabled = false;
  });

  sendButton.addEventListener('click', async () => {
    progress.textContent = '';
    sendButton.disabled = true;
    testButton.disabled = true;
    try {
      let answer = await step('begin');
      say(answer.message, false);
      while (answer.next) {
        answer = await step(answer.next, answer.batch);
        say(answer.message, false);
      }
    } catch (problem) {
      say(problem.message, true);
      say('Nothing was replaced: the app keeps its previous index until a send finishes.', true);
    }
    sendButton.disabled = false;
    testButton.disabled = false;
  });
})();
