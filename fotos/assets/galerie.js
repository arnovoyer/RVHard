/* ================= RV HARD – FOTO-GALERIE LOGIK ================= */

const FG_DATA_URL = '/fotos/data/events.json';
let _fgEventsCachePromise = null;   /* Promise Cache → fetch läuft NUR 1x! */
let _fgEventsCacheArray = null;     /* Sync Cache → zweiter Aufruf instant! */
const FG_LOAD_TIMEOUT_MS = 12000;   /* 12 Sekunden Timeout, dann Fehlermeldung */

/* --------------- HILFSFUNKTIONEN --------------- */

/* Robustes Laden mit TIMEOUT + CACHE + COMMENT CLEANUP (1 Durchgang) */
async function fgLoadEvents() {
    if (_fgEventsCacheArray) return _fgEventsCacheArray;
    if (_fgEventsCachePromise) return _fgEventsCachePromise;

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

function fgEscapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
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

/* --------------- SEITEN-NAVIGATION & FOOTER --------------- */

async function fgLoadNavigation() {
    const container = document.getElementById('navigation-container');
    if (!container) return;
    try {
        const r = await fetch('/assets/navigationneu.html');
        if (!r.ok) throw new Error('Status ' + r.status);
        container.innerHTML = await r.text();
        if (typeof initNavigationMenu === 'function') initNavigationMenu();
    } catch (e) {
        console.error('Navigation fehlgeschlagen:', e);
    }
}

async function fgLoadFooter() {
    const footer = document.getElementById('site-footer');
    if (!footer) return;
    try {
        const r = await fetch('/assets/footer.html');
        if (!r.ok) throw new Error('Status ' + r.status);
        footer.innerHTML = await r.text();
    } catch (e) {
        console.error('Footer fehlgeschlagen:', e);
    }
}

/* ================================================
   SEITE 1: EVENTS-ÜBERSICHT (/fotos/index.html)
================================================= */

function fgRenderEventsOverview(events, containerId = 'fg-events') {
    const container = document.getElementById(containerId);
    if (!container) return;

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
        if (subtype === 'bergrennen') return 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=road%20cycling%20uphill%20race%20austrian%20mountains&image_size=landscape_16_9';
        if (subtype === 'kriterium') return 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=road%20cycling%20criterium%20city%20race%20blurred%20motion&image_size=landscape_16_9';
        return 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=time%20trial%20cyclist%20lake%20shore%20sunset&image_size=landscape_16_9';
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
            const link = `/fotos/event.html?id=${encodeURIComponent(ev.id)}`;
            const rawCover = (ev.coverPhoto || (ev.photos && ev.photos[0] && ev.photos[0].src) || '');
            /* 🔥 WICHTIG: Cover zuerst SANITIZEN (Backticks entfernen!) — sonst Security-Fehler "file:// nicht erlaubt" */
            const safeCover = fgSanitizePhotoSrc(rawCover, fallbackCover(ev.subtype));
            const date = fgFormatDate(ev.date);
            const count = (ev.photos && Array.isArray(ev.photos)) ? ev.photos.length : 0;
            const uniqueBibs = new Set();
            (ev.photos || []).forEach(p => (p.bibNumbers || []).forEach(b => uniqueBibs.add(String(b))));
            const badge = ev.subtype ? disciplineBadge[ev.subtype] : { label:'SBS', bg:'#ffc107' };
            const icon = disciplineIcon[ev.subtype || ''] || 'fa-bicycle';
            html.push(`
                <a class="fg-event-card" href="${link}" data-aos="fade-up">
                    <div class="fg-event-card__cover" style="background-image:url('${fgEscapeHtml(safeCover)}')">
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
    const container = document.getElementById('fg-events');
    if (!container) return;
    const info = document.getElementById('fg-info');

    const searchInput = document.getElementById('fg-search');
    const filterYear = document.getElementById('fg-filter-year');
    const filterDiscipline = document.getElementById('fg-filter-discipline');

    try {
        if (info) info.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin" style="color:#f5b301;"></i> Lade SBS Events…`;
        const events = await fgLoadEvents();
        const sbsEvents = events.filter(e => !e._schemaVersion && (!e.category || e.category === 'SBS'));

        /* Fallback: Cover Photo auf gültiges Bild prüfen (404 = Platzhalter) */
        const fallbackCover = (subtype) => {
            if (subtype === 'bergrennen') return 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=road%20cycling%20uphill%20race%20austrian%20mountains&image_size=landscape_16_9';
            if (subtype === 'kriterium') return 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=road%20cycling%20criterium%20city%20race%20blurred%20motion&image_size=landscape_16_9';
            return 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=time%20trial%20cyclist%20lake%20shore%20sunset&image_size=landscape_16_9';
        };
        sbsEvents.forEach(e => {
            if (!e.coverPhoto || /\/cover\.jpg(\?|$)/.test(String(e.coverPhoto)) || String(e.coverPhoto).startsWith('https://via.placeholder')) {
                e.coverPhoto = fallbackCover(e.subtype);
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

    /* Externe HTTP(S) URLs: USER WILL KEINE KI IMAGES! → NUR rv-hard.at erlaubt!
       ↳ coresg-normal.trae.ai = KI Placeholder → GESPERRT! (User Wunsch!) */
    if (/^https?:\/\//i.test(s)) {
        if (s.includes('rv-hard.at') || s.includes('rv-hard.arnovoyer.com')) return s;
        return fallback; /* Fremde Domains + KI URLs = blockieren! */
    }

    /* Relativer Server-Pfad → NUR erlaubt wenn er in /fotos/data/ anfängt! */
    if (s.startsWith('/fotos/data/') || s.startsWith('fotos/data/')) return s;

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
            <br><a href="/fotos/" class="fg-btn" style="margin-top:1rem;">← Zurück zur Galerie-Übersicht</a></div>`;
        return;
    }

    const date = fgFormatDate(event.date);
    document.title = `${event.name} | Foto-Galerie | RV Hard`;

    /* 🔥 SECURITY og:image Meta Tag SETZEN! Genau DIESER erzeugt den "file:// nicht erlaubt" Fehler!
       Das eventuelle OG:IMAGE Meta Tag im HTML HEAD wird jetzt SANITIZED & neu gesetzt! */
    (function setSafeMetaTags() {
        const rawCover = event.coverPhoto || (event.photos && event.photos[0] && event.photos[0].src) || '';
        const safeCover = fgSanitizePhotoSrc(rawCover, null);
        const setMeta = (property, content) => {
            if (!content) return;
            const escapedContent = String(content).replace(/"/g, '&quot;');
            let el = document.querySelector(`meta[property="${property}"]`);
            if (!el) {
                el = document.createElement('meta');
                el.setAttribute('property', property);
                document.head.appendChild(el);
            }
            el.setAttribute('content', escapedContent);
        };
        if (safeCover) setMeta('og:image', location.origin + safeCover);
        setMeta('og:title', event.name || 'Event Galerie');
        setMeta('og:description', (event.location ? event.location + ' · ' : '') + (event.date ? event.date : ''));
    })();

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
            let safeSrc = fgSanitizePhotoSrc(rawSrc, null);
            const broken = safeSrc === null;
            if (broken) {
                /* Placeholder 404 statt file:// Security-Crash */
                safeSrc = 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=broken%20image%20placeholder%20gray%20white%20error%20icon%20minimal&image_size=square';
                console.warn('[Galerie] Unsichere oder lokale Foto-URL verworfen:', rawSrc);
            }
            const src = fgEscapeHtml(safeSrc);
            const thumb = p.thumbnail ? (fgEscapeHtml(fgSanitizePhotoSrc(p.thumbnail, safeSrc))) : src;
            const bibs = (p.bibNumbers || []).map(b => `<span class="fg-tag fg-tag--bib">#${fgEscapeHtml(b)}</span>`).join('');
            const brokenBadge = broken
                ? `<span class="fg-tag" style="background:#dc3545;color:#fff;margin-right:4px;"><i class="fa-solid fa-triangle-exclamation"></i> Lokaler Pfad! Neu publ.</span>`
                : '';

            return `
                <figure class="fg-photo ${broken ? 'is-broken' : ''}"
                        tabindex="0"
                        data-idx="${idx}"
                        data-photos-src="${fgEscapeHtml(JSON.stringify(filtered.map(pp => pp)))}"
                        role="button"
                        aria-label="Bild vergrößern">
                    <img src="${thumb}" alt="Foto" loading="lazy" ${broken ? 'style="filter:grayscale(1);opacity:.65;"' : ''}>
                    <figcaption class="fg-photo__overlay">
                        ${brokenBadge}${bibs}
                    </figcaption>
                </figure>
            `;
        }).join('');

        /* Click-Handler für Lightbox (delegiert) */
        gallery.querySelectorAll('.fg-photo').forEach(fig => {
            const go = () => {
                const photosArr = JSON.parse(fig.getAttribute('data-photos-src'));
                const start = Number(fig.getAttribute('data-idx') || 0);
                fgOpenLightbox(photosArr, start);
            };
            fig.addEventListener('click', go);
            fig.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
            });
        });
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
    const content = document.getElementById('fg-event-content');
    if (!content) return;
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

        const events = await fgLoadEvents();
        const id = fgQueryParam('id') || fgQueryParam('event');
        if (!id) throw new Error(`Keine Event-ID in der URL! Öffne die Galerie über die Startseite → Event anklicken.`);

        const event = fgFindEventById(events, id);
        if (!event) {
            const allIds = events.filter(e => !e._schemaVersion).map(e => `${e.id} (${e.shortName||'SBS'})`).slice(0,10).join(', ');
            throw new Error(
                `Event mit ID "${id}" nicht in events.json gefunden! ` +
                `Verfügbare IDs (Auszug): ${allIds || '(keine Events vorhanden)'}. ` +
                `Tipp: Gehe zurück auf /fotos/ und klicke das Event dort neu an!`
            );
        }
        fgRenderEventPage(event);

    } catch (err) {
        console.error('Fehler Event-Seite Init:', err);
        /* AUCH die Überschriften mit Fehler überschreiben — sonst steht EWIG "wird geladen..." da! */
        [$evName1, $breadcrumb].forEach(el => {
            if (el) el.innerHTML = `<span style="color:#dc3545;"><i class="fa-solid fa-triangle-exclamation"></i> Galerie-Fehler</span>`;
        });
        if ($evInfo) $evInfo.innerHTML = `<span style="color:#dc3545;">⚠️ Fehler beim Laden: ${fgEscapeHtml(err.message)}</span>`;
        content.innerHTML = `<div class="fg-error" style="padding:1rem;border-radius:8px;background:#fef0f0;border:1px solid #f5c2c7;color:#842029;">
            <h3 style="margin:0 0 0.4rem 0;color:#842029;"><i class="fa-solid fa-circle-exclamation"></i> Galerie konnte nicht geladen werden</h3>
            <p style="margin:0 0 0.8rem 0;white-space:pre-wrap;">${fgEscapeHtml(err.message)}</p>
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.8rem;">
                <a class="fg-btn" href="/fotos/">← Zurück zur Galerie-Startseite</a>
                <button class="fg-btn" style="background:#198754;border-color:#198754;color:#fff;"
                    onclick="location.reload(true);">🔄 Seite NEU laden (Strg+F5)</button>
            </div>
            <p style="margin:0.9rem 0 0 0;font-size:0.88rem;color:#666;">
                ⚡ Häufigste Gründe: 1) Admin Publish Button wurde <b>nicht</b> gedrückt → <a href="/fotos/admin/" target="_blank" style="color:#842029;">/fotos/admin/</a> öffnen & veröffentlichen!
                2) events.json noch auf dem alten Stand → <b>STRG+F5 / ⌘+⇧+R</b> für hartes Reload!
                3) Fehlende Schreibrechte auf /fotos/data/ Ordner (CHMOD 755 setzen).
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
}

function _fgRenderLightboxItem() {
    const lb = document.getElementById('fg-lightbox');
    if (!lb) return;
    const { photos, index } = _fgLightboxState;
    const p = photos[index];
    if (!p) return;

    const img = lb.querySelector('.fg-lightbox__img-wrap img');
    let safeSrc = fgSanitizePhotoSrc(p.src, null);
    const broken = safeSrc === null;
    if (broken) {
        safeSrc = 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=photo%20placeholder%20image%20unavailable%20reupload%20notice&image_size=landscape_16_9';
        console.warn('[Lightbox] Unsichere Foto-URL verworfen:', p.src);
    }
    img.src = safeSrc;
    img.alt = `Bild ${index + 1}`;

    const info = lb.querySelector('.fg-lightbox__info');
    const bibs = (p.bibNumbers || []).map(b => `<span class="fg-tag fg-tag--bib">#${fgEscapeHtml(b)}</span>`).join('');
    const warnBadge = broken
        ? `<div class="fg-lightbox__row" style="background:#f8d7da;color:#842029;border-radius:6px;padding:0.5rem 0.75rem;margin-bottom:0.6rem;"><label><i class="fa-solid fa-triangle-exclamation"></i> Fehler</label><span style="font-weight:500;">Bild kann nicht angezeigt werden: Lokaler Pfad (<code>file://</code>)! Admin nochmal per HTTPS veröffentlichen!</span></div>`
        : '';

    info.innerHTML = `
        ${warnBadge}
        ${bibs ? `<div class="fg-lightbox__row"><label>🏁 Startnummer(n)</label><div class="fg-lightbox__tags">${bibs}</div></div>` : ''}
        <div class="fg-lightbox__actions">
            <a class="fg-btn" href="${fgEscapeHtml(safeSrc)}" target="_blank" rel="noopener" download>
                <i class="fa-solid fa-download"></i> Original herunterladen
            </a>
            <div style="font-size:0.78rem;color:#777;text-align:center;">
                ${index + 1} / ${photos.length}
            </div>
        </div>
    `;
}

/* ================================================
   INIT
================================================= */

document.addEventListener('DOMContentLoaded', () => {
    /* Nav & Footer HINTERGRUND laden — darf GALERIE NICHT blockieren! */
    Promise.all([fgLoadNavigation(), fgLoadFooter()]).then(() => {
        if (typeof initNavigationMenu === 'function') try { initNavigationMenu(); } catch(e){}
    }).catch(e => console.warn('Nav/Foot Load fehlgeschlagen (Galerie läuft trotzdem):', e));

    /* GALERIE SOFORT INITIALISIEREN — KEIN WARTEN auf Nav/Footer! */
    (async () => {
        try {
            if (document.getElementById('fg-events')) await fgInitEventsOverview();
            if (document.getElementById('fg-event-content')) await fgInitEventPage();
        } catch (e) {
            console.error('GALERIE FATALER INIT FEHLER:', e);
            const info = document.getElementById('fg-info');
            const evInfo = document.getElementById('fg-gallery-info');
            const msg = `<span style="color:#dc3545;"><i class="fa-solid fa-triangle-exclamation"></i> Galerie-Fehler: ${fgEscapeHtml(e.message)}</span>`;
            if (info) info.innerHTML = msg;
            if (evInfo) evInfo.innerHTML = msg;
        }
    })();
});
