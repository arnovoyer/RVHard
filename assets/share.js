/* =========================================================
   Teilen-Buttons auf Event-Seiten
   Fügt unter der Hauptüberschrift (<h1>) eine eigene Zeile mit
   WhatsApp-, E-Mail- und "Link kopieren"-Button ein. Der Titel
   bleibt exakt mittig.
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

  var ICONS = {
    whatsapp:
      '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
      '<path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.76-1.66-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.09 3.2 5.07 4.49.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35z"></path>' +
      '<path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.13h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.22 8.22 0 0 1-1.26-4.36c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24z"></path>' +
      '</svg>',
    mail:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<rect x="3" y="5" width="18" height="14" rx="2"></rect>' +
      '<path d="M3 7l9 6 9-6"></path>' +
      '</svg>',
    link:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>' +
      '<path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>' +
      '</svg>'
  };

  /* Erzeugt einen Share-Button (als <a> oder <button>). */
  function createButton(tag, className, label, iconSvg, href) {
    var el = document.createElement(tag);
    if (tag === 'a') {
      el.href = href;
      el.target = '_blank';
      el.rel = 'noopener';
    } else {
      el.type = 'button';
    }
    el.className = 'share-btn ' + className;
    el.setAttribute('aria-label', label);
    el.innerHTML = iconSvg + '<span class="share-btn-label">' + label + '</span>';
    return el;
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

    var url = window.location.href;
    var title = document.title;

    /* Eigene, zentrierte Aktionszeile direkt unter dem Titel. */
    var actions = document.createElement('div');
    actions.className = 'share-actions';

    var whatsapp = createButton(
      'a',
      'share-btn--whatsapp',
      'WhatsApp',
      ICONS.whatsapp,
      'https://wa.me/?text=' + encodeURIComponent(title + '\n' + url)
    );

    var mail = createButton(
      'a',
      'share-btn--mail',
      'E-Mail',
      ICONS.mail,
      'mailto:?subject=' + encodeURIComponent(title) +
        '&body=' + encodeURIComponent('Schau dir das an: ' + url)
    );

    var copy = createButton(
      'button',
      'share-btn--copy',
      'Link kopieren',
      ICONS.link
    );

    copy.addEventListener('click', function () {
      function done() {
        var label = copy.querySelector('.share-btn-label');
        copy.classList.add('share-btn--done');
        if (label) {
          label.textContent = 'Kopiert!';
        }
        window.setTimeout(function () {
          copy.classList.remove('share-btn--done');
          if (label) {
            label.textContent = 'Link kopieren';
          }
        }, 2000);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(function () {
          window.prompt('Link kopieren:', url);
        });
        return;
      }
      window.prompt('Link kopieren:', url);
    });

    actions.appendChild(whatsapp);
    actions.appendChild(mail);
    actions.appendChild(copy);

    /* Zeile direkt nach der Überschrift einfügen. */
    heading.parentNode.insertBefore(actions, heading.nextSibling);
  }

  ensureStylesheet();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initShare);
  } else {
    initShare();
  }
})();
