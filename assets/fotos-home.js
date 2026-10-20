/* ================================================================
 *  RV HARD – FOTOS-TEASER AUF DER STARTSEITE
 * ================================================================
 *  Holt die neuesten Foto-Events über /fotos-feed.php und rendert
 *  daraus eine kompakte Kachel-Reihe. Ist nichts vorhanden
 *  (Galerie offline / keine Bilder), bleibt die Sektion versteckt.
 * ================================================================ */
(function () {
    'use strict';

    var section = document.getElementById('fotos-teaser');
    var grid = document.getElementById('fotos-teaser-grid');
    if (!section || !grid) return;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(items) {
        var usable = (items || []).filter(function (e) {
            return e && typeof e.image === 'string' && e.image !== '' && typeof e.url === 'string' && e.url !== '';
        });
        if (!usable.length) return;                       /* Sektion bleibt hidden */

        grid.innerHTML = usable.map(function (e) {
            var label = e.shortName || e.name || 'Fotos';
            return '<a class="fotos-tile" href="' + esc(e.url) + '" target="_blank" rel="noopener">' +
                       '<span class="fotos-tile__imgwrap">' +
                           '<img src="' + esc(e.image) + '" alt="' + esc(label) + '" loading="lazy" decoding="async">' +
                       '</span>' +
                       '<span class="fotos-tile__label">' + esc(label) + '</span>' +
                   '</a>';
        }).join('');

        section.hidden = false;
        if (window.AOS && typeof AOS.refresh === 'function') AOS.refresh();
    }

    function load() {
        if (!window.fetch) return;                        /* sehr alter Browser → Sektion bleibt weg */
        fetch('/fotos-feed.php', { credentials: 'omit' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && j.events) render(j.events); })
            .catch(function () { /* still: Sektion bleibt versteckt */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
