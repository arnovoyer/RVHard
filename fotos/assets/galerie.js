/* ================= RV HARD – FOTO-GALERIE LOGIK ================= */

/* 🔥 SUBDOMAIN / ANY-DOMAIN KOMPATIBEL!
   Galerie kann jetzt auf:
     - rv-hard.at/fotos/     (Hauptdomain Unterordner)
     - fotos.rv-hard.at/       (Subdomain Galerie Root)
     - fotos.meinedomain.test/   (beliebige andere Domain/Subdomain)
   Pfade werden AUTOMATISCH an die aktuelle Origin + aktueller Pfad angepasst!
*/
(function () {
    const scriptTag = (function () {
        try { if (document.currentScript) return document.currentScript; } catch(e){}
        const q = document.querySelectorAll && document.querySelectorAll('script[src]');
        if (q && q.length) for (let i=q.length-1; i>=0; i--) {
            const s = q[i].src || '';
            if (s.indexOf('galerie.js') > -1) return q[i];
        }
        return null;
    })();
    let inferredRoot = '';
    if (scriptTag) {
        let src = scriptTag.getAttribute ? (scriptTag.getAttribute('src') || '') : '';
        if (!src && scriptTag.src) { try { src = new URL(scriptTag.src, window.location.href).pathname; } catch(e){} }
        if (typeof src === 'string' && src.indexOf('assets/galerie.js') > -1) {
            inferredRoot = src.split('assets/galerie.js')[0].trim().replace(/\/*$/, '');
        }
    }
    const path = String(window.location.pathname || '/').trim();
    let autoBase = '';
    if (path.indexOf('/fotos/') === 0 || path === '/fotos' ||
        (inferredRoot && (inferredRoot.indexOf('/fotos') === 0 || inferredRoot.indexOf('fotos/') === 0))) {
        autoBase = '/fotos';
    } else if (path.indexOf('/fotos') === 0) {
        autoBase = '/fotos';
    }
    if (scriptTag && !autoBase && inferredRoot !== '') autoBase = inferredRoot;
    window.__FGBASE = (autoBase || '').replace(/\/+$/,'');
})();
const FG_BASE = window.__FGBASE || ''; /* z.B. '' (Subdomain root) ODER '/fotos' (Hauptdomain Unterordner) */
const FG_DATA_URL = (FG_BASE? FG_BASE : '') + '/data/events.json';
const FG_EVENT_URL = (FG_BASE? FG_BASE : '') + '/event.html';
const FG_ADMIN_URL = (FG_BASE? FG_BASE : '') + '/admin/';
const FG_HOME_URL = (FG_BASE? FG_BASE : '') + '/';
let _fgEventsCachePromise = null;   /* Promise Cache → fetch läuft NUR 1x! */
let _fgEventsCacheArray = null;     /* Sync Cache → zweiter Aufruf instant! */
const FG_LOAD_TIMEOUT_MS = 12000;   /* 12 Sekunden Timeout, dann Fehlermeldung */

/* ================ 🔥 ULTIMATIVES DEBUG-PANEL (SICHTBAR!) ================ */
/* Zeigt ALLES an! Phasen, Fehler, böse URLs! User soll mir Screenshot schicken können! */
(function fgDebugBootstrap() {
    let log = [];
    const MAX_LOG = 30;
    const MAX_NASTY = 50;

    const FGBG = {
        phase: 'Start',
        badUrls: [],
        log: function(lvl, msg) {
            const t = new Date().toLocaleTimeString('de-DE', { hour12:false }) + '.' + String(new Date().getMilliseconds()).padStart(3,'0');
            log.unshift(`[${t}] ${lvl.toUpperCase()} ${String(msg||'')}`);
            log = log.slice(0, MAX_LOG);
        },
        setPhase: function(p) {
            FGBG.phase = String(p || '');
            FGBG.log('info', '→ ' + FGBG.phase);
        },
        addNasty: function(selector, attr, value) {
            FGBG.badUrls.push({s:selector,a:String(attr||''),v:String(value||'').slice(0,150)});
            FGBG.log('err', `BÖSE URL! ${attr||''} = ${String(value||'').slice(0,80)}`);
        },
        renderNasty: function() {},
        scanned: 0,
        scanDom: function(label) {
            FGBG.setPhase(`Scan DOM (${label||''})`);
            let total = 0;
            const isNasty = (val) => {
                if (typeof val !== 'string') return false;
                const v = val.trim();
                if (!v) return false;
                if (v.indexOf('file:') === 0) return true;
                if (v.indexOf('FILE:') === 0) return true;
                if (/^[A-Za-z]:[\\/]/.test(v)) return true;
                if (/^\/[A-Za-z]:/.test(v)) return true;
                if (v.startsWith('\`') || v.endsWith('\`')) return true;
                if (/^`+https?:\/\//i.test(v)) return true;
                if (/`/.test(v) && /https?:\/\//.test(v)) return true;
                return false;
            };
            const checkEl = (el, attrName) => {
                total++;
                const raw = el.getAttribute && el.getAttribute(attrName);
                if (isNasty(raw)) {
                    let sel = (el.tagName||'').toLowerCase();
                    if (el.id) sel += `#${el.id}`;
                    if (el.className && typeof el.className === 'string') {
                        const c = el.className.trim().split(/\s+/).filter(Boolean).slice(0,3).join('.');
                        if (c) sel += '.' + c;
                    }
                    FGBG.addNasty(sel, attrName, raw);
                }
                if (attrName === 'style' && typeof raw === 'string' && (raw.indexOf('file:')>=0 || raw.indexOf('C:\\')>=0 || /`/.test(raw))) {
                    let sel = (el.tagName||'').toLowerCase();
                    if (el.id) sel += `#${el.id}`;
                    if (el.className && typeof el.className === 'string') {
                        const c = el.className.trim().split(/\s+/).filter(Boolean).slice(0,3).join('.');
                        if (c) sel += '.' + c;
                    }
                    FGBG.addNasty(sel,'style', raw);
                }
            };
            document.querySelectorAll('base[href], a[href], area[href], img[src], img[srcset], img[poster], link[href], script[src], iframe[src], iframe[srcdoc], video[src], audio[src], source[src], track[src], video[poster], embed[src], object[data], form[action], input[formaction], button[formaction], blockquote[cite], q[cite], del[cite], ins[cite], *[background], [style]').forEach(el=>{
                ['href','src','srcset','poster','srcdoc','data','codebase','archive','action','formaction','cite','manifest','background','style','content'].forEach(a=>{ if(el.hasAttribute && el.hasAttribute(a)) checkEl(el,a); });
            });
            document.querySelectorAll('meta[content]').forEach(e=>{
                const prop = String(e.getAttribute('property')||'').toLowerCase() + ' ' + String(e.getAttribute('name')||'').toLowerCase();
                if (prop.indexOf('og:image') >= 0 || prop.indexOf('twitter:image') >= 0 || prop.indexOf('msapplication-') >= 0) checkEl(e,'content');
            });
            FGBG.scanned = (FGBG.scanned||0) + total;
            FGBG.log('info', `🔍 Scan ${label||''}: ${total} gecheckt, ${FGBG.badUrls.length} böse.`);
        }
    };
    window.__FGBG = FGBG;
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(()=>FGBG.scanDom('Initial'), 200);
    });
})();

/* 🔥 HELFER: fgEscapeHtml MUSS VOR Globalem onerror kommen! (sonst ReferenceError!) */
function fgEscapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

/* 🔥 GLOBALER JS FEHLER HANDLER! Alle Fehler werden im Browser (statt stiller console.error)
   direkt im Galerie-Info Feld angezeigt! So sehen wir SOFORT was los ist! */
window.addEventListener('error', function(errEvt) {
    try {
        const msg = `⚠️ JS-Fehler: ${fgEscapeHtml(errEvt.message || String(errEvt.error || ''))} (Zeile ${errEvt.lineno||'?'})`;
        console.error('GLOBAL JS ERROR CATCHER:', errEvt);
        const info = document.getElementById('fg-info') || document.getElementById('fg-gallery-info');
        if (info) {
            info.innerHTML = `<span style="color:#dc3545;font-weight:600;">${msg}</span>`;
        }
        const evName = document.getElementById('fg-event-name');
        if (evName && evName.textContent.includes('wird geladen')) {
            evName.textContent = 'Fehler beim Laden';
        }
        if (window.__FGBG) window.__FGBG.log('err', msg);
    } catch(e) {}
});

/* Promise Uncaught ebenfalls! */
window.addEventListener('unhandledrejection', function(ev) {
    try {
        console.error('UNHANDLED PROMISE:', ev);
        const msg = `⚠️ Netzwerk/Promise Fehler: ${fgEscapeHtml((ev.reason && (ev.reason.message||String(ev.reason))) || String(ev||''))}`;
        const info = document.getElementById('fg-info') || document.getElementById('fg-gallery-info');
        if (info) info.innerHTML = `<span style="color:#dc3545;font-weight:600;">${msg}</span>`;
        if (window.__FGBG) window.__FGBG.log('err', msg);
    } catch(e) {}
});

/* 🔥 FINALE SICHERHEITSKANONE: ALLE URLs gehen durch diese Funktion!
   GARANTIERT dass NIE file:// / Backtick / Windows C: URLs ins DOM gelangen!
   → Wenn irgendwo (Nav, Footer, Meta, Galerie) eine schlechte URL durchschleicht,
      wird sie hier ABGEFANGEN und durch NULL ersetzt! */
function fgSafeUrl(raw, fallback = null, label = 'url') {
    if (raw === null || raw === undefined) return fallback;
    if (typeof raw !== 'string') return fallback;
    let s = raw;

    /* Step 1 + 2 + 3: DREIFACHE Backtick + Smart Quotes + Anführungszeichen Entfernung! */
    for (let i = 0; i < 3; i++) {
        s = s.replace(/^[\s`"'“”‘’]+|[\s`"'“”‘’]+$/g, '');
        s = s.trim();
    }

    /* Step 4: 100% Blockliste! Alles was nicht erlaubt ist → FALLBACK */
    const BAD_PREFIXES = ['file:', 'blob:', 'data:image', 'C:', 'D:', 'E:', 'F:', 'G:', 'c:', 'd:', 'e:', 'f:', 'g:', '/C:', '/c:'];
    for (const bp of BAD_PREFIXES) {
        if (s.startsWith(bp)) {
            console.error(`[fgSafeUrl:${label}] BLOCKED unsafe url →`, bp, raw);
            return fallback;
        }
    }

    /* Step 5: Externe URLs nur auf RV-Hard Domains + aktueller Host! (User will KEINE fremden/KI Bilder!) */
    if (/^https?:\/\//i.test(s)) {
        try {
            const u = new URL(s, window.location.href);
            const curHost = window.location.hostname.toLowerCase();
            const tgtHost = u.hostname.toLowerCase();
            const isRvHard    = tgtHost === 'rv-hard.at' || tgtHost.endsWith('.rv-hard.at');
            const isArnovoyer = tgtHost === 'rv-hard.arnovoyer.com' || tgtHost.endsWith('.rv-hard.arnovoyer.com');
            const isCDN = tgtHost.endsWith('cdnjs.cloudflare.com') || tgtHost.endsWith('fonts.googleapis.com') ||
                          tgtHost.endsWith('fonts.gstatic.com')   || tgtHost.endsWith('cdn.jsdelivr.net') ||
                          tgtHost.endsWith('unpkg.com')           || tgtHost.endsWith('kit.fontawesome.com') ||
                          tgtHost.endsWith('use.fontawesome.com') || tgtHost.endsWith('ka-f.fontawesome.com');
            if (tgtHost === curHost || isRvHard || isArnovoyer || isCDN) return s;
            console.error(`[fgSafeUrl:${label}] BLOCKED external url (nicht RV Hard / aktive Domain!) →`, raw);
            return fallback;
        } catch(e) { return fallback; }
    }

    /* Step 6: Interne Pfade! JETZT SUBDOMAIN KOMPATIBEL!
       Führt mit / (z.B. /fotos/, /data/) → OK!
       Relativ (./assets/, assets/) → OK!
       Wenn FG_BASE gesetzt, auch FG_BASE/data/ etc. → OK! */
    if (s.startsWith('/')) return s;
    if (s.startsWith('./')) return s;
    if (/^[^/]{1,40}\//.test(s)) return s; /* z.B. assets/style.css (relativ zu aktueller Seite) */

    /* Step 7: Alles andere → Fallback */
    if (s === '') return fallback;
    console.error(`[fgSafeUrl:${label}] BLOCKED unknown format →`, raw);
    return fallback;
}

/* --------------- HILFSFUNKTIONEN --------------- */

/* Robustes Laden mit TIMEOUT + CACHE + COMMENT CLEANUP (1 Durchgang) */
async function fgLoadEvents() {
    if (_fgEventsCacheArray) return _fgEventsCacheArray;
    if (_fgEventsCachePromise) return _fgEventsCachePromise;
    if (window.__FGBG) window.__FGBG.setPhase('Lade events.json vom Server…');

    _fgEventsCachePromise = (async () => {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), FG_LOAD_TIMEOUT_MS);

        try {
            const cacheBust = '?v=' + Math.floor(Date.now() / 30000); /* Nur alle 30 Sekunden neu holen! */
            const res = await fetch(FG_DATA_URL + cacheBust, {
                signal: controller.signal,
                cache: 'no-cache',
                priority: 'high',
                mode: 'cors'
            });
            clearTimeout(timeoutId);

            if (!res.ok) throw new Error(`Server antwortete mit Status ${res.status} – events.json nicht gefunden?`);
            const txt = await res.text();
            if (!txt || txt.trim().length < 10) throw new Error(`events.json ist LEER! Datei wurde vermutlich nicht korrekt auf den Server geladen.`);

            let json;
            try {
                json = JSON.parse(txt);
            } catch (parseErr) {
                /* Letzter Ausweg: Kommentare + Trailing Commas entfernen (User hat manuell editiert) */
                try {
                    const cleaned = txt
                        .replace(/\/\*[\s\S]*?\*\//g, '')
                        .replace(/^\s*\/\/.*$/gm, '')
                        .replace(/,\s*([\]}])/g, '$1')
                        .replace(/^\uFEFF/, '');
                    json = JSON.parse(cleaned);
                } catch (retryErr) {
                    const pos = (parseErr.message && parseErr.message.match(/position (\d+)/i))?.[1] || '?';
                    const snippet = txt.substring(Math.max(0, Number(pos) - 80), Math.min(txt.length, Number(pos) + 80));
                    throw new Error(
                        `events.json ist UNGÜLTIG (JSON Parse fehlgeschlagen bei Position ${pos}).\n` +
                        `TIPPS:\n  1. Hast du die Datei manuell bearbeitet? Keine Kommentare /* */ oder // erlaubt!\n` +
                        `  2. Im Admin-Tool auf "Veröffentlichen" klicken, damit neu erzeugt wird.\n` +
                        `  3. STRG+F5 drücken für neue Version.\n\n` +
                        `Original Fehler: ${parseErr.message}\n\nStelle im JSON bei Position ${pos}: ...${snippet}...`
                    );
                }
            }

            const arr = Array.isArray(json) ? json : (json.events || []);
            if (!arr.length) throw new Error(`events.json wurde geladen, enthält aber KEINE Events! Admin Publish erneut durchführen.`);
            _fgEventsCacheArray = arr;
            if (window.__FGBG) window.__FGBG.log('info', `✅ events.json OK! ${arr.length} Events geladen.`);
            return _fgEventsCacheArray;

        } catch (e) {
            clearTimeout(timeoutId);
            /* Cache zurücksetzen, damit Retry nach Fehler wieder möglich ist */
            _fgEventsCachePromise = null;
            _fgEventsCacheArray = null;
            if (e && e.name === 'AbortError') {
                throw new Error(`Zeitüberschreitung! events.json konnte nach ${FG_LOAD_TIMEOUT_MS/1000}s nicht geladen werden. Langsame Internetverbindung oder Datei zu groß? STRG+F5 versuchen!`);
            }
            throw e;
        }
    })();

    return _fgEventsCachePromise;
}

function fgFormatDate(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        if (isNaN(d)) return String(dateStr);
        return d.toLocaleDateString('de-AT', {
            day: '2-digit', month: 'long', year: 'numeric'
        });
    } catch { return String(dateStr); }
}

function fgQueryParam(name) {
    const params = new URLSearchParams(window.location.search);
    const v = params.get(name);
    return (v == null) ? '' : v.trim();
}

/* --------------- SEITEN-NAVIGATION & FOOTER (SUBDOMAIN KOMPATIBEL!) ---------------
   Navigation + Footer HTML sind NICHT in der Galerie enthalten! Sie liegen auf der
   Haupt-Domain RV Hard. Problem bei eigener Subdomain (fotos.rv-hard.at):
   CORS (Cross Origin) verbietet Fetch von fremder Domain!
   Lösung: 1. Versuche relative Pfade /assets/navigationneu.html (altes Verhalten)
           2. Fallback: Navigation/Footer NUR anzeigen, wenn wir in /fotos Unterordner sind!
              Auf eigener Subdomain (FG_BASE === '') → Navigation/Footer werden GAR NICHT geladen!
              → Galerie hat dann KEINE Hauptnavigation (aber ist ja auf fotos.example.com separat!)
*/
async function fgLoadNavigation() {
    const container = document.getElementById('navigation-container');
    if (!container) return;
    const path = window.location.pathname || '';
    const istUnterordnerRVHard = FG_BASE === '/fotos' || path.indexOf('/fotos/') === 0;
    if (istUnterordnerRVHard) {
        try {
            const r = await fetch('/assets/navigationneu.html');
            if (!r.ok) throw new Error('Status ' + r.status);
            container.innerHTML = await r.text();
            if (typeof initNavigationMenu === 'function') try { initNavigationMenu(); } catch(e){}
        } catch (e) {
            console.warn('Navigation konnte nicht geladen werden:', e);
            container.innerHTML = '';
        }
        return;
    }
    const mainDomain = 'https://rv-hard.at';
    const homeUrl = FG_HOME_URL || './';
    container.innerHTML = `
<nav class="fg-nav">
  <a href="${mainDomain}" class="fg-nav__back" title="Zurück zu rv-hard.at" aria-label="Zurück zur Hauptseite">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M19 12H5"></path>
      <polyline points="12 19 5 12 12 5"></polyline>
    </svg>
    <span>rv-hard.at</span>
  </a>
  <a href="${homeUrl}" class="fg-nav__logo" title="Foto-Galerie Start">
    <img src="${FG_BASE ? FG_BASE : ''}/assets/logo.svg" alt="RV Hard Logo" onerror="this.onerror=null;this.src='${mainDomain}/assets/img/logo/RV_Hard_Logo.webp';">
  </a>
  <div class="fg-nav__spacer" aria-hidden="true"></div>
</nav>`;
}

async function fgLoadFooter() {
    const footer = document.getElementById('site-footer');
    if (!footer) return;
    const path = window.location.pathname || '';
    const istUnterordnerRVHard = FG_BASE === '/fotos' || path.indexOf('/fotos/') === 0;
    if (istUnterordnerRVHard) {
        try {
            const r = await fetch('/assets/footer.html');
            if (!r.ok) throw new Error('Status ' + r.status);
            footer.innerHTML = await r.text();
        } catch (e) {
            console.warn('Footer konnte nicht geladen werden:', e);
            footer.innerHTML = '';
        }
        return;
    }
    const mainDomain = 'https://rv-hard.at';
    footer.innerHTML = `
<footer class="fg-footer">
  <div class="fg-footer__social">
    <a href="https://www.instagram.com/rv_hard/" target="_blank" rel="noopener" aria-label="Instagram RV Hard">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.8.1 1.2.1 1.8.3 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1 .4 2.2.1 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 1.2-.3 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .4-2.2.4-1.2.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2-.1-1.8-.3-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.4-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8c.1-1.2.3-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.4 2.2-.4C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.2 0-3.5 0-4.7.1-1.1.1-1.7.3-2.1.4-.5.2-.9.4-1.2.8-.3.3-.6.7-.8 1.2-.1.4-.3 1-.4 2.1C2.7 9.3 2.7 9.6 2.7 12s0 2.7.1 3.9c.1 1.1.3 1.7.4 2.1.2.5.4.9.8 1.2.3.3.7.6 1.2.8.4.1 1 .3 2.1.4 1.2.1 1.5.1 4.7.1s3.5 0 4.7-.1c1.1-.1 1.7-.3 2.1-.4.5-.2.9-.4 1.2-.8.3-.3.6-.7.8-1.2.1-.4.3-1 .4-2.1.1-1.2.1-1.5.1-3.9s0-2.7-.1-3.9c-.1-1.1-.3-1.7-.4-2.1-.2-.5-.4-.9-.8-1.2-.3-.3-.7-.6-1.2-.8-.4-.1-1-.3-2.1-.4C15.5 4 15.2 4 12 4zm0 3.1a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8zm0 1.8a3.1 3.1 0 1 0 0 6.2 3.1 3.1 0 0 0 0-6.2zm5.1-2.2a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3z"/></svg>
    </a>
    <a href="https://www.facebook.com/radvereinhard/" target="_blank" rel="noopener" aria-label="Facebook RV Hard">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.5l.4-3h-2.9V8.6c0-.9.2-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.9v3h2.6V21h3z"/></svg>
    </a>
  </div>
  <p class="fg-footer__copy">&copy; 2026 RV Hard</p>
  <p class="fg-footer__links">
    <a href="${mainDomain}/kontakt.html">Kontakt</a>
    <span class="fg-footer__sep">|</span>
    <a href="${mainDomain}/impressum.html">Impressum</a>
    <span class="fg-footer__sep">|</span>
    <a href="${mainDomain}/datenschutz.html">Datenschutz</a>
  </p>
</footer>`;
}

/* ================================================
   SEITE 1: EVENTS-ÜBERSICHT (/fotos/index.html)
================================================= */

function fgRenderEventsOverview(events, containerId = 'fg-events') {
    const container = document.getElementById(containerId);
    if (!container) return;
    if (window.__FGBG) window.__FGBG.setPhase('Rendere Event-Karten (Übersicht)…');

    /* Filter Zustand */
    const searchInput = document.getElementById('fg-search');
    const filterYear = document.getElementById('fg-filter-year');
    const filterDiscipline = document.getElementById('fg-filter-discipline');

    const state = {
        search: (searchInput && searchInput.value) ? searchInput.value.toLowerCase() : '',
        year: (filterYear && filterYear.value) || 'all',
        discipline: (filterDiscipline && filterDiscipline.value) || 'all'
    };

    /* Filter anwenden: nur SBS Events, keine _readme Objekte */
    const sbsEvents = events.filter(ev => !ev._schemaVersion && (!ev.category || ev.category === 'SBS'));
    const filtered = sbsEvents.filter(ev => {
        if (state.year !== 'all') {
            const evYear = String(ev.parentId || '').replace('sbs-', '');
            if (evYear !== state.year) return false;
        }
        if (state.discipline !== 'all' && String(ev.subtype || '') !== state.discipline) return false;
        if (!state.search) return true;
        const hay = [
            ev.name, ev.shortName, ev.location, ev.description, ev.distance,
            (ev.organizer || ''),
            ...(ev.tags || []),
            String(ev.date || '')
        ].join(' ').toLowerCase();
        return hay.includes(state.search);
    });

    /* Info */
    const info = document.getElementById('fg-info');
    if (info) {
        const total = sbsEvents.length;
        const bibCount = () => {
            const s = new Set();
            filtered.forEach(e => (e.photos || []).forEach(p => (p.bibNumbers || []).forEach(b => s.add(String(b)))));
            return s.size;
        };
        const photoCount = filtered.reduce((n, e) => n + ((e.photos && e.photos.length) || 0), 0);
        info.innerHTML = `Insgesamt <strong>${total}</strong> SBS-Events – angezeigt: <strong>${filtered.length}</strong> · <i class="fa-regular fa-image"></i> ${photoCount} Bilder · <i class="fa-solid fa-person-running"></i> ${bibCount()} TN-Nummern`;
    }

    if (!filtered.length) {
        container.innerHTML = `<div class="fg-empty"><strong>Keine SBS-Events gefunden.</strong><br>Versuche es mit einem anderen Jahr, Disziplin oder Suchbegriff.</div>`;
        return;
    }

    /* Gruppieren nach Jahr */
    const byYear = {};
    filtered.forEach(ev => {
        const yr = String(ev.parentId || ev.id || '').match(/20\d{2}/)?.[0] || 'Archiv';
        if (!byYear[yr]) byYear[yr] = [];
        byYear[yr].push(ev);
    });

    const disciplineIcon = {
        bergrennen: 'fa-mountain-sun',
        ezf: 'fa-stopwatch',
        kriterium: 'fa-flag-checkered'
    };
    const disciplineBadge = {
        bergrennen: { label: 'Bergrennen', bg: '#198754' },
        ezf: { label: 'EZF', bg: '#0d6efd' },
        kriterium: { label: 'Kriterium', bg: '#dc3545' }
    };

    const fallbackCover = (subtype) => {
        /* User Wunsch: KEINE KI generierten Placeholder Bilder! 
           → return null: Karte zeigt nur Farbverlauf + Disziplin-Icon (CSS sorgt für Hintergrund) */
        return null;
    };

    const html = [];
    Object.keys(byYear).sort((a,b) => (b > a ? 1 : -1)).forEach(year => {
        html.push(`<div style="grid-column: 1/-1; margin: 1.5rem 0 0.5rem;">
            <h2 style="margin:0; font-size:1.5rem; display:flex; align-items:center; gap:0.75rem;">
                <span style="background:#111; color:#ffc107; padding:0.25rem 0.85rem; border-radius: 8px; font-weight: 800;">SBS ${year}</span>
                <span style="font-size:0.9rem; color:#777; font-weight: 500;">${byYear[year].length} Disziplinen</span>
            </h2>
            <hr style="border:none; border-top: 2px dashed #eee; margin:0.5rem 0 0;">
        </div>`);

        byYear[year].forEach(ev => {
            const link = `${FG_EVENT_URL}?id=${encodeURIComponent(ev.id)}`;
            /* 🔥 GOOGLE DRIVE SPEED: Bevorzugt THUMBNAIL (klein, 40KB) statt Original (12MB)!
               1. Priorität: erstes Foto thumbnail   2. coverPhoto   3. erstes Foto src   4. null */
            const firstPhoto = (ev.photos && ev.photos[0]) ? ev.photos[0] : null;
            const rawCover =
                (firstPhoto && firstPhoto.thumbnail ? firstPhoto.thumbnail : '') ||
                (ev.coverPhoto || '') ||
                (firstPhoto && firstPhoto.src ? firstPhoto.src : '') ||
                '';
            /* 🔥 WICHTIG: Dreifache Sicherheit! Backticks entfernen!
               1) fgSanitizePhotoSrc + 2) fgSafeUrl + 3) Wenn NULL dann KEIN background-image! */
            const sanitizedCover = fgSanitizePhotoSrc(rawCover, null);
            const safeCover = fgSafeUrl(sanitizedCover, null, `card-cover-${ev.id}`);
            const date = fgFormatDate(ev.date);
            const count = (ev.photos && Array.isArray(ev.photos)) ? ev.photos.length : 0;
            const uniqueBibs = new Set();
            (ev.photos || []).forEach(p => (p.bibNumbers || []).forEach(b => uniqueBibs.add(String(b))));
            const badge = ev.subtype ? disciplineBadge[ev.subtype] : { label:'SBS', bg:'#ffc107' };
            const icon = disciplineIcon[ev.subtype || ''] || 'fa-bicycle';
            /* Wenn KEIN echtes Bild da → style leer lassen (CSS zeigt automatisch Gradient Background!) */
            const coverBgStyle = safeCover ? `style="background-image:url('${fgEscapeHtml(safeCover)}')"` : '';
            html.push(`
                <a class="fg-event-card" href="${link}" data-aos="fade-up">
                    <div class="fg-event-card__cover" ${coverBgStyle}>
                        <span class="fg-event-card__badge" style="background:${badge.bg}; color:#fff;">${badge.label}</span>
                        <div class="fg-event-card__count">
                            <i class="fa-regular fa-image"></i> ${count}
                            ${uniqueBibs.size ? ` · <i class="fa-solid fa-person-running"></i> ${uniqueBibs.size}` : ''}
                        </div>
                    </div>
                    <div class="fg-event-card__body">
                        <h3><i class="fa-solid ${icon}" style="color:#f5b301;"></i> ${fgEscapeHtml(ev.shortName || ev.name)}</h3>
                        <div class="fg-event-card__meta">
                            ${date ? `<span><i class="fa-regular fa-calendar"></i> ${fgEscapeHtml(date)}</span>` : ''}
                            ${ev.location ? `<span><i class="fa-solid fa-location-dot"></i> ${fgEscapeHtml(ev.location)}</span>` : ''}
                            ${ev.distance ? `<span><i class="fa-solid fa-route"></i> ${fgEscapeHtml(ev.distance)}</span>` : ''}
                        </div>
                        ${ev.description ? `<p class="fg-event-card__desc">${fgEscapeHtml(ev.description)}</p>` : ''}
                        <div class="fg-event-card__footer">
                            <span class="fg-btn fg-btn--outline">
                                <i class="fa-solid fa-magnifying-glass"></i> Startnummer suchen →
                            </span>
                        </div>
                    </div>
                </a>
            `);
        });
    });

    container.innerHTML = html.join('');
    if (typeof AOS !== 'undefined') AOS.refreshHard();
}

async function fgInitEventsOverview() {
    if (window.__FGBG) window.__FGBG.setPhase('fgInitEventsOverview() startet…');
    const container = document.getElementById('fg-events');
    if (!container) {
        if (window.__FGBG) window.__FGBG.log('err', '❌ FATAL: #fg-events Div NICHT gefunden!');
        return;
    }
    if (window.__FGBG) window.__FGBG.log('info', '✅ #fg-events Container vorhanden.');

    const info = document.getElementById('fg-info');
    const searchInput = document.getElementById('fg-search');
    const filterYear = document.getElementById('fg-filter-year');
    const filterDiscipline = document.getElementById('fg-filter-discipline');

    try {
        if (info) info.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin" style="color:#f5b301;"></i> Lade SBS Events…`;
        if (window.__FGBG) window.__FGBG.setPhase('Lade events.json für Overview…');

        const events = await fgLoadEvents();
        if (window.__FGBG) window.__FGBG.log('info', `✅ events.json geladen (${events.length}). Filtere SBS Events…`);

        const sbsEvents = events.filter(e => !e._schemaVersion && (!e.category || e.category === 'SBS'));
        if (window.__FGBG) window.__FGBG.setPhase(`${sbsEvents.length} SBS Events geladen. Sanitize CoverPhotos…`);

        /* Fallback: Cover Photo auf gültiges Bild prüfen (404 = Platzhalter) — NUR SANITIZE + KEINE KI PLATZHALTER! */
        const fallbackCoverLocal = () => null; /* User Wunsch: NUR echte Bilder! Keine KI Placeholder! */
        sbsEvents.forEach(e => {
            /* Backticks entfernen! (Sonst file:// Security Fehler!) */
            if (typeof e.coverPhoto === 'string') {
                e.coverPhoto = e.coverPhoto.replace(/^[`\s"'“”‘’]+|[`\s"'“”‘’]+$/g, '');
            }
            if (!e.coverPhoto || /\/cover\.jpg(\?|$)/.test(String(e.coverPhoto)) || String(e.coverPhoto).startsWith('https://via.placeholder') || String(e.coverPhoto).includes('coresg-normal.trae.ai')) {
                /* User Wunsch: KEINE KI Bilder! Fallback = ERSTES ECHTES FOTO! sonst null */
                e.coverPhoto = (e.photos && e.photos[0] && e.photos[0].src && fgSanitizePhotoSrc(e.photos[0].src, null)) || fallbackCoverLocal();
            }
        });

        /* Jahres-Filter befüllen */
        if (filterYear && filterYear.children.length <= 1) {
            const years = [...new Set(sbsEvents.map(e => (String(e.parentId || e.id || '').match(/20\d{2}/) || [])[0]).filter(Boolean))]
                .sort((a,b) => String(b).localeCompare(String(a)));
            years.forEach(y => {
                const o = document.createElement('option');
                o.value = y; o.textContent = `SBS ${y}`;
                filterYear.appendChild(o);
            });
        }

        const render = () => fgRenderEventsOverview(events, 'fg-events');
        if (searchInput) searchInput.addEventListener('input', render);
        if (filterYear) filterYear.addEventListener('change', render);
        if (filterDiscipline) filterDiscipline.addEventListener('change', render);
        render();
    } catch (err) {
        console.error('Fehler Galerie Init:', err);
        if (info) info.innerHTML = `<span style="color:#dc3545;"><i class="fa-solid fa-triangle-exclamation"></i> Fehler: ${fgEscapeHtml(err.message)}</span>`;
        container.innerHTML = `<div class="fg-error" style="padding:1rem;border-radius:8px;background:#fef0f0;border:1px solid #f5c2c7;color:#842029;">
            <h3 style="margin:0 0 0.4rem 0;color:#842029;"><i class="fa-solid fa-circle-exclamation"></i> Galerie konnte nicht geladen werden</h3>
            <p style="margin:0 0 0.8rem 0;">${fgEscapeHtml(err.message)}</p>
            <p style="margin:0;font-size:0.9rem;color:#666;">
                ⚠️ Meistens Ursachen: a) <code>events.json</code> enthält Kommentare (ungültig!) — b) Datei fehlt oder hat falsche Rechte (644!) —
                c) Browser-Cache mit alter ungültiger Version: STRG+F5 / ⌘+⇧+R drücken!
            </p>
        </div>`;
    }
}

/* ================================================
   SEITE 2: SINGLE EVENT-ANSICHT (/fotos/event.html)
================================================= */

function fgFindEventById(events, id) {
    if (!id) return null;
    return events.find(ev => String(ev.id) === id || String(ev.slug || '') === id) || null;
}

/* Foto-URL auf GÜLTIGKEIT & SICHERHEIT prüfen: Keine lokalen Pfade erlauben! */
function fgSanitizePhotoSrc(src, fallback = null) {
    if (!src || typeof src !== 'string') return fallback;
    let s = src.trim();
    if (!s) return fallback;

    /* Step 1: Leading/Trailing BACKTICKS entfernen! (`` ` `` Zeichen)
       Die machen URLs zu lokalem Pfad und erzeugen Security-Fehler "file:// nicht erlaubt"! */
    s = s.replace(/^[`\s"'“”‘’]+|[`\s"'“”‘’]+$/g, '');

    /* 🔴 Blockierte gefährliche Protokolle / Windows-Pfade */
    if (/^file:/i.test(s))      return fallback;
    if (/^blob:/i.test(s))     return fallback;
    if (/^[A-Za-z]:[\\/]/.test(s)) return fallback;   /* C:\ D:\ Windows */
    if (/^\/[A-Za-z]:/.test(s))    return fallback;   /* /C:/ (Unix Form) */
    if (/^data:image/i.test(s))    return fallback;   /* Kein Base64 Chaos */

    /* Externe HTTP(S) URLs: USER WILL KEINE KI IMAGES! */
    if (/^https?:\/\//i.test(s)) {
        /* 🔥🔥🔥 JETZT SUBDOMAIN KOMPATIBEL!
         *   Erlaube IMMER: Aktuelle Host Domain + rv-hard.at (Hauptdomain) + CDNs für Fonts!
         *   Alles Andere (KI-Plattformen etc.) = blockieren! */
        try {
            const u = new URL(s, window.location.href);
            const curHost = window.location.hostname.toLowerCase();
            const tgtHost = u.hostname.toLowerCase();
            if (tgtHost === curHost) return s; /* Gleiche Domain / Subdomain → OK */
            if (tgtHost === 'rv-hard.at' || tgtHost.endsWith('.rv-hard.at')) return s; /* Hauptdomain + alle Subdomains von RV Hard! */
            if (tgtHost === 'rv-hard.arnovoyer.com' || tgtHost.endsWith('.rv-hard.arnovoyer.com')) return s;
            /* CDNs nur für Fonts/Skripte (keine Bilder hier erlaubt außer explizit) */
            if (tgtHost.endsWith('cdnjs.cloudflare.com') ||
                tgtHost.endsWith('fonts.googleapis.com') ||
                tgtHost.endsWith('fonts.gstatic.com') ||
                tgtHost.endsWith('cdn.jsdelivr.net') ||
                tgtHost.endsWith('unpkg.com')) {
                return s;
            }
            return fallback; /* Fremde Domains / KI URLs blockieren! */
        } catch(e) { /* URL parse failed → block! */ return fallback; }
    }

    /* Relativer Server-Pfad — JETZT SUBDOMAIN KOMPATIBEL!
       Hauptdomain alte URLs /fotos/data/xxx sind OK!
       Subdomain neue  /data/xxx ODER ./data/xxx OK! */
    if (s.startsWith('/fotos/data/') || s.startsWith('fotos/data/')) return s;
    if (s.startsWith('/data/')      || s.startsWith('data/'))      return s;
    if (s.startsWith('./data/'))                                 return s;
    if (FG_BASE && s.startsWith(FG_BASE + '/data/'))             return s;

    /* Sonst alles was nicht passt → Fallback */
    return fallback;
}

function fgPhotoMatchesBib(photo, bibQuery) {
    if (!bibQuery) return true;
    const q = String(bibQuery).toLowerCase().trim();
    if (!q) return true;
    const bibs = (photo.bibNumbers || []).map(b => String(b).toLowerCase());
    return bibs.some(b => b === q || b.includes(q));
}

function fgRenderEventPage(event) {
    if (!event) {
        document.getElementById('fg-event-content').innerHTML =
            `<div class="fg-error"><strong>Event nicht gefunden.</strong>
            <br><a href="${FG_HOME_URL}" class="fg-btn" style="margin-top:1rem;">← Zurück zur Galerie-Übersicht</a></div>`;
        if (window.__FGBG) window.__FGBG.log('err', 'Event NICHT in events.json gefunden!');
        return;
    }
    if (window.__FGBG) window.__FGBG.setPhase(`Event laden: "${event.name || 'unbekannt'}" (${event.photos && event.photos.length} Bilder)…`);

    const date = fgFormatDate(event.date);
    document.title = `${event.name} | Foto-Galerie | RV Hard`;

    document.getElementById('fg-event-name').textContent = event.name;
    document.getElementById('fg-event-date').textContent = date;
    if (event.location) document.getElementById('fg-event-location').textContent = ' · ' + event.location;

    const headerMeta = document.getElementById('fg-event-head-meta');
    if (headerMeta) {
        headerMeta.innerHTML = [
            date ? `<span><i class="fa-regular fa-calendar"></i> ${fgEscapeHtml(date)}</span>` : '',
            event.location ? `<span><i class="fa-solid fa-location-dot"></i> ${fgEscapeHtml(event.location)}</span>` : '',
            event.organizer ? `<span><i class="fa-regular fa-building"></i> ${fgEscapeHtml(event.organizer)}</span>` : ''
        ].filter(Boolean).join('&nbsp;&nbsp;');
    }

    /* Eventuell externer Link zur Foto-Quelle */
    const externalLink = document.getElementById('fg-external-link');
    if (externalLink && event.externalGalleryUrl) {
        externalLink.innerHTML = `<a class="fg-btn fg-btn--outline" href="${fgEscapeHtml(event.externalGalleryUrl)}" target="_blank" rel="noopener">
            <i class="fa-solid fa-up-right-from-square"></i> Original-Fotografen-Galerie
        </a>`;
    }

    /* Filter-Controls */
    const bibInput = document.getElementById('fg-bib');

    const photos = Array.isArray(event.photos) ? event.photos : [];
    const allBibs = new Set();
    photos.forEach(p => (p.bibNumbers || []).forEach(b => allBibs.add(String(b))));

    /* Query-Parameter vorbelegen */
    const qBib = fgQueryParam('bib');
    if (qBib && bibInput) bibInput.value = qBib;

    function renderGallery() {
        const bib = (bibInput ? bibInput.value : '').trim();

        const filtered = photos.filter(p => fgPhotoMatchesBib(p, bib));
        /* 🔥 Memory Speicher statt data-photos-src JSON im HTML! (Riesige JSON im HTML Attribut löste Security Parser Error file:// aus!) */
        if (!window._fgPhotoBuckets) window._fgPhotoBuckets = {};
        window._fgPhotoBuckets.current = filtered;

        document.getElementById('fg-gallery-info').innerHTML =
            `Galerie enthält <strong>${photos.length}</strong> Bilder – angezeigt: <strong>${filtered.length}</strong>`;

        const gallery = document.getElementById('fg-gallery');
        if (!filtered.length) {
            gallery.innerHTML = `<div class="fg-empty">
                <strong>Keine Treffer.</strong>
                <br>Tipp: Gib einen Teil der Startnummer ein, z.B. <code>42</code> oder auch nur <code>4</code>.
            </div>`;
            return;
        }

        gallery.innerHTML = filtered.map((p, idx) => {
            const rawSrc = p.src;
            /* DOUBLE SAFE! 1x Sanitize + 1x fgSafeUrl! */
            const sanitizedSrc = fgSanitizePhotoSrc(rawSrc, null);
            let safeSrc = fgSafeUrl(sanitizedSrc, null, `gallery-img-${idx}`);
            const broken = safeSrc === null;
            /* 100% SICHERER Fallback: Inline SVG (minimal) → KEIN externer Request! KEIN KI! KEIN file:// Risiko! */
            const FALLBACK_IMG = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect width="100%" height="100%" fill="#f5f5f5"/><path fill="#ccc" d="M100 100h200v200H100z" stroke="#aaa" stroke-width="4"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#888" font-family="Arial" font-size="48">404</text></svg>');
            if (broken) {
                safeSrc = FALLBACK_IMG;
                console.warn('[Galerie] Unsichere oder lokale Foto-URL verworfen:', rawSrc);
            }
            /* Thumbnail → ebenfalls double-check! */
            const rawThumb = p.thumbnail;
            const sanitizedThumb = rawThumb ? fgSanitizePhotoSrc(rawThumb, null) : safeSrc;
            let thumb = fgSafeUrl(sanitizedThumb, safeSrc, `gallery-thumb-${idx}`);
            if (!thumb) thumb = safeSrc;
            const bibs = (p.bibNumbers || []).map(b => `<span class="fg-tag fg-tag--bib">#${fgEscapeHtml(b)}</span>`).join('');
            const brokenBadge = broken
                ? `<span class="fg-tag" style="background:#dc3545;color:#fff;margin-right:4px;"><i class="fa-solid fa-triangle-exclamation"></i> Lokaler Pfad! Neu publ.</span>`
                : '';

            return `
                <figure class="fg-photo ${broken ? 'is-broken' : ''}"
                        tabindex="0"
                        data-idx="${idx}"
                        role="button"
                        aria-label="Bild vergrößern">
                    <img src="${fgEscapeHtml(thumb)}" alt="Foto" loading="lazy" decoding="async" fetchpriority="low" ${broken ? 'style="filter:grayscale(1);opacity:.65;"' : ''}>
                    <figcaption class="fg-photo__overlay">
                        ${brokenBadge}${bibs}
                    </figcaption>
                </figure>
            `;
        }).join('');

        /* Click-Handler für Lightbox (delegiert) — NUR data-idx! Photos Array holen aus window._fgPhotoBuckets.current! */
        gallery.querySelectorAll('.fg-photo').forEach(fig => {
            const go = () => {
                const photosArr = (window._fgPhotoBuckets && window._fgPhotoBuckets.current) || [];
                const start = Number(fig.getAttribute('data-idx') || 0);
                fgOpenLightbox(photosArr, start);
            };
            fig.addEventListener('click', go);
            fig.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
            });
        });
        if (window.__FGBG) {
            window.__FGBG.log('info', `✅ Galerie gerendert: ${filtered.length} Bilder angezeigt.`);
            window.__FGBG.scanDom('Nach Galerie-Render');
            window.__FGBG.setPhase('Fertig! Galerie geladen.');
        }
    }

    if (bibInput) {
        bibInput.addEventListener('input', () => {
            renderGallery();
            const params = new URLSearchParams(window.location.search);
            if (bibInput.value.trim()) params.set('bib', bibInput.value.trim()); else params.delete('bib');
            const s = params.toString();
            history.replaceState(null, '', `${location.pathname}${s ? '?' + s : ''}${location.hash}`);
        });
    }

    renderGallery();
}

async function fgInitEventPage() {
    if (window.__FGBG) window.__FGBG.setPhase('fgInitEventPage() startet…');
    const content = document.getElementById('fg-event-content');
    if (!content) {
        const msg = '❌ FATAL: <div id="fg-event-content"> NICHT im HTML gefunden! Bitte event.html Template prüfen.';
        if (window.__FGBG) window.__FGBG.log('err', msg);
        const name = document.getElementById('fg-event-name');
        if (name) name.innerHTML = `<span style="color:#dc3545;">${fgEscapeHtml(msg)}</span>`;
        return;
    }
    if (window.__FGBG) window.__FGBG.log('info', '✅ #fg-event-content vorhanden. Setze Loading-Texte.');

    const $evName1 = document.getElementById('fg-event-name');
    const $evInfo = document.getElementById('fg-gallery-info');
    const $gallery = document.getElementById('fg-gallery');
    const $breadcrumb = document.getElementById('fg-bc-event');

    try {
        /* Loading Text setzen — falls es vorher stand, bleibt es sonst ewig! */
        [$evName1, $breadcrumb].forEach(el => {
            if (el) el.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin" style="color:#f5b301;"></i> Galerie lädt…`;
        });
        if ($evInfo) $evInfo.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin" style="color:#f5b301;"></i> Bilder werden geladen — bitte kurz warten!`;
        if ($gallery) $gallery.innerHTML = `<div class="fg-empty"><strong>Galerie wird geladen…</strong></div>`;
        if (window.__FGBG) window.__FGBG.setPhase('Warte auf fgLoadEvents() Fetch…');

        const events = await fgLoadEvents();
        if (window.__FGBG) window.__FGBG.log('info', `✅ fgLoadEvents() OK! ${events.length} Events. Suche Event-ID in URL…`);

        const id = fgQueryParam('id') || fgQueryParam('event');
        if (!id) throw new Error(`Keine Event-ID in der URL! Öffne die Galerie über die Startseite → Event anklicken.`);
        if (window.__FGBG) window.__FGBG.setPhase(`Suche Event mit ID="${id}" in ${events.length} Events…`);

        const event = fgFindEventById(events, id);
        if (!event) {
            const allIds = events.filter(e => !e._schemaVersion).map(e => `${e.id} (${e.shortName||'SBS'})`).slice(0,10).join(', ');
            throw new Error(
                `Event mit ID "${id}" nicht in events.json gefunden! ` +
                `Verfügbare IDs (Auszug): ${allIds || '(keine Events vorhanden)'}. ` +
                `Tipp: Gehe zurück auf ${FG_HOME_URL || '/'} und klicke das Event dort neu an!`
            );
        }
        if (window.__FGBG) window.__FGBG.log('info', `✅ Event gefunden: ${event.name} (${event.photos && event.photos.length} Bilder). Rendere Seite…`);
        fgRenderEventPage(event);
        if (window.__FGBG) window.__FGBG.setPhase(`✅ ${event.shortName} Galerie geladen!`);

    } catch (err) {
        console.error('Fehler Event-Seite Init:', err);
        if (window.__FGBG) window.__FGBG.log('err', `fgInitEventPage CRASH: ${err.message}`);
        /* AUCH die Überschriften mit Fehler überschreiben — sonst steht EWIG "wird geladen..." da! */
        [$evName1, $breadcrumb].forEach(el => {
            if (el) el.innerHTML = `<span style="color:#dc3545;"><i class="fa-solid fa-triangle-exclamation"></i> Galerie-Fehler</span>`;
        });
        if ($evInfo) $evInfo.innerHTML = `<span style="color:#dc3545;">⚠️ Fehler beim Laden: ${fgEscapeHtml(err.message)}</span>`;
        content.innerHTML = `<div class="fg-error" style="padding:1rem;border-radius:8px;background:#fef0f0;border:1px solid #f5c2c7;color:#842029;">
            <h3 style="margin:0 0 0.4rem 0;color:#842029;"><i class="fa-solid fa-circle-exclamation"></i> Galerie konnte nicht geladen werden</h3>
            <p style="margin:0 0 0.8rem 0;white-space:pre-wrap;">${fgEscapeHtml(err.message)}</p>
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.8rem;">
                <a class="fg-btn" href="${FG_HOME_URL}">← Zurück zur Galerie-Startseite</a>
                <button class="fg-btn" style="background:#198754;border-color:#198754;color:#fff;"
                    onclick="location.reload(true);">🔄 Seite NEU laden (Strg+F5)</button>
            </div>
            <p style="margin:0.9rem 0 0 0;font-size:0.88rem;color:#666;">
                ⚡ Häufigste Gründe: 1) Admin Publish Button wurde <b>nicht</b> gedrückt → <a href="${FG_ADMIN_URL}" target="_blank" style="color:#842029;">Admin</a> öffnen & veröffentlichen!
                2) events.json noch auf dem alten Stand → <b>STRG+F5 / ⌘+⇧+R</b> für hartes Reload!
                3) Fehlende Schreibrechte auf ${FG_BASE || ''}/data/ Ordner (CHMOD 755 setzen).
            </p>
        </div>`;
        if ($gallery) $gallery.innerHTML = '';
    }
}

/* ================================================
   LIGHTBOX (Bild betrachten, downloaden)
================================================= */

let _fgLightboxState = { photos: [], index: 0 };

function fgOpenLightbox(photos, startIdx = 0) {
    _fgLightboxState = { photos: Array.isArray(photos) ? photos : [], index: startIdx };
    const lb = document.getElementById('fg-lightbox');
    if (!lb) return;
    lb.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    _fgRenderLightboxItem();

    const closeBtn = lb.querySelector('.fg-lightbox__close');
    const prevBtn = lb.querySelector('.fg-lightbox__nav--prev');
    const nextBtn = lb.querySelector('.fg-lightbox__nav--next');

    if (closeBtn) closeBtn.onclick = fgCloseLightbox;
    if (prevBtn) prevBtn.onclick = () => fgLightboxNav(-1);
    if (nextBtn) nextBtn.onclick = () => fgLightboxNav(1);

    lb.onclick = (e) => { if (e.target === lb) fgCloseLightbox(); };
    document.addEventListener('keydown', _fgLightboxKeyHandler, { once: true });
}

function fgCloseLightbox() {
    const lb = document.getElementById('fg-lightbox');
    if (!lb) return;
    lb.classList.remove('is-open');
    document.body.style.overflow = '';
    document.removeEventListener('keydown', _fgLightboxKeyHandler);
}

function _fgLightboxKeyHandler(e) {
    if (e.key === 'Escape') fgCloseLightbox();
    else if (e.key === 'ArrowLeft') fgLightboxNav(-1);
    else if (e.key === 'ArrowRight') fgLightboxNav(1);
    else document.addEventListener('keydown', _fgLightboxKeyHandler, { once: true });
}

function fgLightboxNav(delta) {
    const { photos, index } = _fgLightboxState;
    if (!photos.length) return;
    let next = index + delta;
    if (next < 0) next = photos.length - 1;
    if (next >= photos.length) next = 0;
    _fgLightboxState.index = next;
    _fgRenderLightboxItem();
    /* 🔥 Hintergrund Preload: Nächstes & Vorheriges Bild sofort in den Cache ziehen!
        So hat User beim Nächsten Klick schon das Bild vorbereitet → KEIN LAG! */
    _fgLightboxPreloadAdjacent();
}

/* 🔥🔥 LIGHTBOX LAG FREI – 3-stufige Bild-Ladekette (Genau wie Google Drive!):
 *   Stufe 1: thumbnail (420px / 60KB) → SOFORT sichtbar, Low-Quality Placeholder
 *   Stufe 2: display   (2560px / 800KB) → HOCHWERTIG! Hauptansicht! (15x kleiner als 12MB DSLR-Original!)
 *   Stufe 3: src       (Original 12MB)  → NUR für Download-Button! Wird NICHT automatisch geladen!
 *  + Vorheriges/Nächstes Bild wird sofort im Hintergrund vorgeladen!
 *  + Kein DOM-Neuaufbau, nur src tauschen + Opacity Fade Transition!
 */
function _fgLightboxBestDisplaySrc(p) {
    if (!p) return null;
    if (p.display) { const s = fgSanitizePhotoSrc(p.display, null); if (s) return fgSafeUrl(s, null, 'lb-disp'); }
    if (p.src)     { const s = fgSanitizePhotoSrc(p.src, null);     if (s) return fgSafeUrl(s, null, 'lb-src'); }
    return null;
}
function _fgLightboxBestLQIP(p) { /* Low Quality Immediate Preview – falls Display noch lädt */
    if (!p) return null;
    if (p.thumbnail) { const s = fgSanitizePhotoSrc(p.thumbnail, null); if (s) return fgSafeUrl(s, null, 'lb-lqip'); }
    return null;
}
/* Vorbereitete Preload Bilder im Document versteckt halten (Browser cached automatisch!) */
let _fgLightboxPreloaded = new Set();
function _fgLightboxPreloadOne(url) {
    if (!url || _fgLightboxPreloaded.has(url)) return;
    _fgLightboxPreloaded.add(url);
    try {
        const i = new Image();
        i.decoding = 'async';
        i.fetchpriority = 'low';
        i.src = url;
    } catch(e){}
}
function _fgLightboxPreloadAdjacent() {
    const { photos, index } = _fgLightboxState;
    if (!photos.length) return;
    const p = photos.length;
    const prev = photos[(index - 1 + p) % p];
    const next = photos[(index + 1) % p];
    [prev, next].forEach(ph => {
        const u = _fgLightboxBestDisplaySrc(ph); if (u) _fgLightboxPreloadOne(u);
        const l = _fgLightboxBestLQIP(ph);        if (l) _fgLightboxPreloadOne(l);
    });
}

function _fgRenderLightboxItem() {
    const lb = document.getElementById('fg-lightbox');
    if (!lb) return;
    const { photos, index } = _fgLightboxState;
    const p = photos[index];
    if (!p) return;

    const FALLBACK_LB = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><rect width="100%" height="100%" fill="#f5f5f5"/><path fill="#eee" d="M200 200h800v400H200z" stroke="#ccc" stroke-width="8"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999" font-family="Arial" font-size="80">Bild nicht verfügbar</text><text x="50%" y="62%" text-anchor="middle" dy=".3em" fill="#bbb" font-family="Arial" font-size="44">Im Admin nochmals veröffentlichen!</text></svg>');

    /* WRAP enthält zwei <img> Layer:
     *  layer fg-lqip: thumbnail 420px → SOFORT sichtbar (0.05s!) → User sieht sofort Bild!
     *  layer fg-main: display 2560px hochwertig! → geladen → FADE IN (+ LQIP fade out!)
     */
    const imgWrap = lb.querySelector('.fg-lightbox__img-wrap');
    /* Kein single img mehr! Wir müssen das alte <img> entfernen, falls es noch ohne Klassen existiert! */
    const oldSingle = imgWrap.querySelector('img:not(.fg-lqip):not(.fg-main)');
    if (oldSingle) try { oldSingle.remove(); } catch(e){}
    let lqipImg = imgWrap ? imgWrap.querySelector('img.fg-lqip') : null;
    let mainImg = imgWrap ? imgWrap.querySelector('img.fg-main') : null;
    if (!lqipImg) {
        lqipImg = document.createElement('img');
        lqipImg.className = 'fg-lqip';
        lqipImg.alt = '';
        lqipImg.setAttribute('aria-hidden','true');
        lqipImg.decoding = 'async';
        lqipImg.referrerPolicy = 'no-referrer';
        imgWrap.appendChild(lqipImg);
    }
    if (!mainImg) {
        mainImg = document.createElement('img');
        mainImg.className = 'fg-main';
        mainImg.decoding = 'async';
        mainImg.fetchpriority = 'high';
        mainImg.referrerPolicy = 'no-referrer';
        imgWrap.appendChild(mainImg);
    }

    /* Display Fallback Reihenfolge: 1. display (2560px) 2. src (Original) */
    const getDisplay = () => {
        if (p && p.display) {
            const s = fgSanitizePhotoSrc(p.display, null);
            if (s) return fgSafeUrl(s, null, 'lb-disp');
        }
        if (p && p.src) {
            const s = fgSanitizePhotoSrc(p.src, null);
            if (s) return fgSafeUrl(s, null, 'lb-src');
        }
        return null;
    };
    /* LQIP: thumbnail falls vorhanden, sonst Fallback */
    const getLQIP = () => {
        if (p && p.thumbnail) {
            const s = fgSanitizePhotoSrc(p.thumbnail, null);
            if (s) return fgSafeUrl(s, null, 'lb-lqip');
        }
        return null;
    };

    const displaySrc = getDisplay();
    const lqipSrc    = getLQIP();
    const broken = displaySrc === null;
    const mainSrcFinal = displaySrc || lqipSrc || FALLBACK_LB;
    const lqipFinal    = lqipSrc || mainSrcFinal;

    /* 🔥 Layer zurücksetzen für neuen Klick: LQIP sichtbar, main transparent! */
    lqipImg.style.transition = 'none';
    lqipImg.style.opacity = '1';
    lqipImg.src = lqipFinal;

    mainImg.style.transition = 'none';
    mainImg.style.opacity = '0';
    mainImg.alt = `Bild ${index + 1}`;
    /* Reset Event Handler (nur für dieses Bild!) */
    try { mainImg.onload = null; mainImg.onerror = null; } catch(e){}

    let mainFired = false;
    const showMain = () => {
        if (mainFired) return; mainFired = true;
        requestAnimationFrame(() => {
            mainImg.style.transition = 'opacity 0.35s ease-out';
            mainImg.style.opacity = '1';
            /* Kurz warten bis main drüber ist → dann LQIP rausblenden für volle Schärfe! */
            setTimeout(() => {
                lqipImg.style.transition = 'opacity 0.35s ease-out';
                lqipImg.style.opacity = '0';
            }, 220);
        });
    };
    mainImg.onload = showMain;
    mainImg.onerror = () => {
        /* Wenn main laden fehlschlägt: LQIP bleibt! */
        if (window.__FGBG) window.__FGBG.log('warn', '[Lightbox] Main image failed to load (show LQIP): ' + String(mainSrcFinal).slice(0,80));
    };
    /* 🔥 Sicherheitsnetz: Selbst wenn onload aus irgendeinem Grund nicht feuert, nach max 2.8s sichtbar! */
    setTimeout(() => { if (!mainFired) showMain(); }, 2800);
    mainImg.src = mainSrcFinal;

    /* Download URL = IMMER das ORIGINAL! (Nicht die kleine Display Version!) */
    const origSafe = (() => {
        const s = fgSanitizePhotoSrc(p.src, null);
        return s ? fgSafeUrl(s, FALLBACK_LB, 'lb-download') : FALLBACK_LB;
    })();
    const origBroken = !p.src || (origSafe === FALLBACK_LB);

    const info = lb.querySelector('.fg-lightbox__info');
    const bibs = (p.bibNumbers || []).map(b => `<span class="fg-tag fg-tag--bib">#${fgEscapeHtml(b)}</span>`).join('');
    const warnBadge = broken
        ? `<div class="fg-lightbox__row" style="background:#f8d7da;color:#842029;border-radius:6px;padding:0.5rem 0.75rem;margin-bottom:0.6rem;"><label><i class="fa-solid fa-triangle-exclamation"></i> Fehler</label><span style="font-weight:500;">Bild kann nicht angezeigt werden: Lokaler Pfad (<code>file://</code>)! Admin nochmal per HTTPS veröffentlichen!</span></div>`
        : '';

    info.innerHTML = `
        ${warnBadge}
        ${bibs ? `<div class="fg-lightbox__row"><label>🏁 Startnummer(n)</label><div class="fg-lightbox__tags">${bibs}</div></div>` : ''}
        <div class="fg-lightbox__actions">
            <a class="fg-btn" href="${fgEscapeHtml(origSafe)}" target="_blank" rel="noopener" ${origBroken ? 'style="opacity:0.5;pointer-events:none;" title="Original kann nicht geladen werden – neu publ."' : ''} download>
                <i class="fa-solid fa-download"></i> Original herunterladen
            </a>
            <div style="font-size:0.78rem;color:#777;text-align:center;">
                ${index + 1} / ${photos.length}
            </div>
        </div>
    `;

    /* 🔥 Direkt nach Rendern: Vorheriges + Nächstes Bild im Hintergrund vorladen! (Nicht warten bis User klickt!) */
    setTimeout(_fgLightboxPreloadAdjacent, 40);
}

/* ================================================
   INIT
================================================= */

document.addEventListener('DOMContentLoaded', () => {
    if (window.__FGBG) window.__FGBG.setPhase('DOMContentLoaded: Starte Galerie!');

    /* Nav & Footer HINTERGRUND laden — darf GALERIE NICHT blockieren! */
    Promise.all([fgLoadNavigation(), fgLoadFooter()]).then(() => {
        if (typeof initNavigationMenu === 'function') try { initNavigationMenu(); } catch(e){console.warn(e);}
        if (window.__FGBG) {
            window.__FGBG.log('info', '✅ Nav + Footer geladen. Scanne DOM nach bösen URLs…');
            window.__FGBG.scanDom('Nav+Footer');
        }
    }).catch(e => {
        console.warn('Nav/Foot Load fehlgeschlagen (Galerie läuft trotzdem):', e);
        if (window.__FGBG) window.__FGBG.log('warn', 'Nav/Footer Fehler: '+String(e.message||e));
    });

    /* GALERIE SOFORT INITIALISIEREN — KEIN WARTEN auf Nav/Footer! */
    (async () => {
        try {
            if (document.getElementById('fg-events')) await fgInitEventsOverview();
            if (document.getElementById('fg-event-content')) await fgInitEventPage();
            if (window.__FGBG) {
                window.__FGBG.scanDom('FINAL (alles geladen)');
                window.__FGBG.setPhase('✅ Galerie INIT abgeschlossen!');
            }
        } catch (e) {
            console.error('GALERIE FATALER INIT FEHLER:', e);
            const info = document.getElementById('fg-info');
            const evInfo = document.getElementById('fg-gallery-info');
            const msg = `<span style="color:#dc3545;"><i class="fa-solid fa-triangle-exclamation"></i> Galerie-Fehler: ${fgEscapeHtml(e.message)}</span>`;
            if (info) info.innerHTML = msg;
            if (evInfo) evInfo.innerHTML = msg;
            if (window.__FGBG) window.__FGBG.log('err', 'FATAL INIT: ' + String(e.message||e));
        }
    })();
});
