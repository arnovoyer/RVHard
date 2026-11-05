/* =========================================================
   Teilen-Button auf Event-Seiten
   Fügt neben der Hauptüberschrift (<h1>) einen "Teilen"-Button
   ein. Nutzt die Web Share API, sonst wird der Link kopiert.
   Wird nur auf Seiten mit .nightrace-container aktiv.
   ========================================================= */
(function () {
  'use strict';

  /* Stylesheet einmalig nachladen, damit die Event-Seiten
     nicht einzeln angepasst werden müssen. */
  function ensureStylesheet() {
    var already = Array.prototype.some.call(
      document.querySelectorAll('link[rel="stylesheet"]'),
      function (link) {
        return (link.getAttribute('href') || '').indexOf('/assets/share.css') !== -1;
      }
    );
    if (already) {
      return;
    }
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/assets/share.css';
    document.head.appendChild(link);
  }

  /* Erste Überschrift des Events finden. Überschriften in einer
     Hinweis-Box (.danke-section) werden übersprungen, damit bei
     abgesagten Events der eigentliche Titel verwendet wird. */
  function findHeading(container) {
    var headings = container.querySelectorAll('h1, h2');
    for (var i = 0; i < headings.length; i++) {
      if (!headings[i].closest('.danke-section')) {
        return headings[i];
      }
    }
    return headings[0] || null;
  }

  function createButton() {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'share-btn';
    btn.setAttribute('aria-label', 'Diese Seite teilen');
    btn.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<circle cx="18" cy="5" r="3"></circle>' +
      '<circle cx="6" cy="12" r="3"></circle>' +
      '<circle cx="18" cy="19" r="3"></circle>' +
      '<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>' +
      '<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>' +
      '</svg><span class="share-btn-label">Teilen</span>';
    return btn;
  }

  function showFeedback(btn, text) {
    var label = btn.querySelector('.share-btn-label');
    btn.classList.add('share-btn--done');
    if (label) {
      label.textContent = text;
    }
    window.setTimeout(function () {
      btn.classList.remove('share-btn--done');
      if (label) {
        label.textContent = 'Teilen';
      }
    }, 2000);
  }

  function fallbackCopy(btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(window.location.href)
        .then(function () { showFeedback(btn, 'Link kopiert!'); })
        .catch(function () { window.prompt('Link kopieren:', window.location.href); });
      return;
    }
    window.prompt('Link kopieren:', window.location.href);
  }

  function initShare() {
    var container = document.querySelector('.nightrace-container');
    if (!container) {
      return;
    }

    var heading = findHeading(container);
    if (!heading) {
      return;
    }

    /* Überschrift in einen Flex-Wrapper setzen und Button daneben. */
    var row = document.createElement('div');
    row.className = 'share-title-row';
    heading.parentNode.insertBefore(row, heading);
    row.appendChild(heading);

    var btn = createButton();
    row.appendChild(btn);

    btn.addEventListener('click', function () {
      var url = window.location.href;
      if (navigator.share) {
        navigator.share({ title: document.title, url: url }).catch(function () {
          /* Vom Nutzer abgebrochen – nichts weiter tun. */
        });
        return;
      }
      fallbackCopy(btn);
    });
  }

  ensureStylesheet();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initShare);
  } else {
    initShare();
  }
})();
