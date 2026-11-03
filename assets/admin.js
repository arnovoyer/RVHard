/* ================================================================
 *  RV HARD – ADMIN: gemeinsames Verhalten
 * ================================================================
 *  Theme-Umschalter (hell/dunkel) in der Admin-Topbar.
 *  Nutzt denselben localStorage-Key wie die Website ("rv-hard-theme"),
 *  damit Admin und Website pro Origin denselben Modus nutzen.
 * ================================================================ */
(function () {
    'use strict';

    var KEY = 'rv-hard-theme';

    function current() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function paint(t) {
        document.documentElement.setAttribute('data-theme', t);
        var icons = document.querySelectorAll('[data-theme-toggle] i');
        for (var i = 0; i < icons.length; i++) {
            icons[i].className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-theme-toggle]') : null;
        if (!btn) return;
        var next = current() === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(KEY, next); } catch (err) {}
        paint(next);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { paint(current()); });
    } else {
        paint(current());
    }
})();
