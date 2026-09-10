/* ================= RV HARD – FOTO-GALERIE LOGIK ================= */

const FG_DATA_URL = '/fotos/data/events.json';

/* --------------- HILFSFUNKTIONEN --------------- */

async function fgLoadEvents() {
    try {
        const res = await fetch(FG_DATA_URL + '?v=' + Date.now());
        if (!res.ok) throw new Error(`Events-JSON konnte nicht geladen werden (Status ${res.status}).`);
        const txt = await res.text();
        let json;
        try {
            json = JSON.parse(txt);
        } catch (parseErr) {
            /* Fallback: Kommentare rausfiltern (manche Editoren fügen /* Kommentare hinzu) */
            try {
                const cleaned = txt
                    .replace(/\/\*[\s\S]*?\*\//g, '')
                    .replace(/^\s*\/\/.*$/gm, '')
                    .replace(/,\s*([\]}])/g, '$1');
                json = JSON.parse(cleaned);
            } catch (retryErr) {
                throw new Error(`events.json ist UNGÜLTIG! Überprüfe die Datei auf Kommentare, fehlende Kommata oder Tippfehler. Original-Fehler: ${parseErr.message}`);
            }
        }
        return Array.isArray(json) ? json : (json.events || []);
    } catch (e) {
        console.error('[fgLoadEvents] FATAL:', e);
        throw e;
    }
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
            const cover = (ev.coverPhoto || (ev.photos && ev.photos[0] && ev.photos[0].src) || '').trim();
            const date = fgFormatDate(ev.date);
            const count = (ev.photos && Array.isArray(ev.photos)) ? ev.photos.length : 0;
            const uniqueBibs = new Set();
            (ev.photos || []).forEach(p => (p.bibNumbers || []).forEach(b => uniqueBibs.add(String(b))));
            const badge = ev.subtype ? disciplineBadge[ev.subtype] : { label:'SBS', bg:'#ffc107' };
            const icon = disciplineIcon[ev.subtype || ''] || 'fa-bicycle';
            html.push(`
                <a class="fg-event-card" href="${link}" data-aos="fade-up">
                    <div class="fg-event-card__cover" ${cover ? `style="background-image:url('${fgEscapeHtml(cover)}')"` : ''}>
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

function fgPhotoMatchesBib(photo, bibQuery) {
    if (!bibQuery) return true;
    const q = String(bibQuery).toLowerCase().trim();
    if (!q) return true;
    /* Suche in Startnummern (exakt oder Teilstring) */
    const bibs = (photo.bibNumbers || []).map(b => String(b).toLowerCase());
    const hitsBib = bibs.some(b => b === q || b.includes(q));
    /* Suche in Namen */
    const athletes = (photo.athletes || []).join('|').toLowerCase();
    /* Suche in Tags/Kommentar */
    const other = [photo.title, photo.comment, ...(photo.tags || [])].join('|').toLowerCase();
    return hitsBib || athletes.includes(q) || other.includes(q);
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
    const nameInput = document.getElementById('fg-name');
    const searchInput = document.getElementById('fg-search-photo');

    const photos = Array.isArray(event.photos) ? event.photos : [];
    const allBibs = new Set();
    photos.forEach(p => (p.bibNumbers || []).forEach(b => allBibs.add(String(b))));

    /* Query-Parameter vorbelegen */
    const qBib = fgQueryParam('bib');
    const qName = fgQueryParam('name');
    if (qBib && bibInput) bibInput.value = qBib;
    if (qName && nameInput) nameInput.value = qName;

    function renderGallery() {
        const bib = (bibInput ? bibInput.value : '').trim();
        const name = (nameInput ? nameInput.value : '').toLowerCase().trim();
        const general = (searchInput ? searchInput.value : '').toLowerCase().trim();

        const filtered = photos.filter(p => {
            if (bib) {
                const q = bib.toLowerCase();
                const bibs = (p.bibNumbers || []).map(b => String(b).toLowerCase());
                const hitBib = bibs.some(b => b === q || b.includes(q));
                if (!hitBib) return false;
            }
            if (name) {
                const athletes = (p.athletes || []).join('|').toLowerCase();
                if (!athletes.includes(name)) return false;
            }
            if (general) {
                const hay = [
                    p.title, p.comment,
                    ...(p.tags || []), ...(p.athletes || []),
                    ...(p.bibNumbers || []).map(String)
                ].join('|').toLowerCase();
                if (!hay.includes(general)) return false;
            }
            return true;
        });

        document.getElementById('fg-gallery-info').innerHTML =
            `Galerie enthält <strong>${photos.length}</strong> Bilder – Filter anzeigen: <strong>${filtered.length}</strong>`;

        const gallery = document.getElementById('fg-gallery');
        if (!filtered.length) {
            gallery.innerHTML = `<div class="fg-empty">
                <strong>Keine Treffer.</strong>
                <br>Tipp: Starte mit <em>Teil der Startnummer</em> (z.B. 42 oder "R") oder Namensteil.
            </div>`;
            return;
        }

        gallery.innerHTML = filtered.map((p, idx) => {
            const src = fgEscapeHtml(p.src);
            const thumb = p.thumbnail ? fgEscapeHtml(p.thumbnail) : src;
            const bibs = (p.bibNumbers || []).map(b => `<span class="fg-tag fg-tag--bib">#${fgEscapeHtml(b)}</span>`).join('');
            const titleEl = p.title ? `<span style="flex:1 1 auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${fgEscapeHtml(p.title)}</span>` : '';

            return `
                <figure class="fg-photo"
                        tabindex="0"
                        data-idx="${idx}"
                        data-photos-src="${fgEscapeHtml(JSON.stringify(filtered.map(pp => pp)))}"
                        role="button"
                        aria-label="Bild vergrößern">
                    <img src="${thumb}" alt="${fgEscapeHtml(p.title || p.comment || 'Foto')}" loading="lazy">
                    <figcaption class="fg-photo__overlay">
                        ${bibs}${titleEl}
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

    [bibInput, nameInput, searchInput].forEach(el => {
        if (el) el.addEventListener('input', () => {
            renderGallery();
            const params = new URLSearchParams(window.location.search);
            if (bibInput) { if (bibInput.value.trim()) params.set('bib', bibInput.value.trim()); else params.delete('bib'); }
            if (nameInput) { if (nameInput.value.trim()) params.set('name', nameInput.value.trim()); else params.delete('name'); }
            const s = params.toString();
            history.replaceState(null, '', `${location.pathname}${s ? '?' + s : ''}${location.hash}`);
        });
    });

    renderGallery();
}

async function fgInitEventPage() {
    const content = document.getElementById('fg-event-content');
    if (!content) return;

    try {
        const events = await fgLoadEvents();
        const id = fgQueryParam('id') || fgQueryParam('event');
        fgRenderEventPage(fgFindEventById(events, id));
    } catch (err) {
        content.innerHTML = `<div class="fg-error"><strong>Fehler:</strong> ${fgEscapeHtml(err.message)}</div>`;
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
    img.src = p.src;
    img.alt = p.title || '';

    const info = lb.querySelector('.fg-lightbox__info');
    const bibs = (p.bibNumbers || []).map(b => `<span class="fg-tag fg-tag--bib">#${fgEscapeHtml(b)}</span>`).join('');
    const tags = (p.tags || []).map(t => `<span class="fg-tag">${fgEscapeHtml(t)}</span>`).join('');
    const athletes = (p.athletes && p.athletes.length) ? p.athletes.join(', ') : '';
    const date = fgFormatDate(p.date);
    const photographer = p.photographer || p.copyright || '';

    info.innerHTML = `
        <h3>${fgEscapeHtml(p.title || `Bild ${index + 1} / ${photos.length}`)}</h3>
        ${p.comment ? `<div class="fg-lightbox__row"><label>Kommentar</label><span>${fgEscapeHtml(p.comment)}</span></div>` : ''}
        ${athletes ? `<div class="fg-lightbox__row"><label>Teilnehmer*in</label><span>${fgEscapeHtml(athletes)}</span></div>` : ''}
        ${date ? `<div class="fg-lightbox__row"><label>Aufgenommen</label><span>${fgEscapeHtml(date)}</span></div>` : ''}
        ${photographer ? `<div class="fg-lightbox__row"><label>Fotograf*in / Quelle</label><span>${fgEscapeHtml(photographer)}</span></div>` : ''}
        ${(bibs || tags) ? `<div class="fg-lightbox__row"><label>Tags / Startnummern</label>
            <div class="fg-lightbox__tags">${bibs}${tags}</div></div>` : ''}
        <div class="fg-lightbox__actions">
            <a class="fg-btn" href="${fgEscapeHtml(p.src)}" target="_blank" rel="noopener" download>
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

document.addEventListener('DOMContentLoaded', async () => {
    try { await Promise.all([fgLoadNavigation(), fgLoadFooter()]); }
    catch(e) { console.warn('Nav/Foot Load fehlgeschlagen (Galerie läuft trotzdem):', e); }

    try {
        if (document.getElementById('fg-events')) await fgInitEventsOverview();
        if (document.getElementById('fg-event-content')) await fgInitEventPage();
    } catch (e) {
        console.error('GALERIE FATALER INIT FEHLER:', e);
        const info = document.getElementById('fg-info');
        if (info) info.innerHTML = `<span style="color:#dc3545;"><i class="fa-solid fa-triangle-exclamation"></i> Galerie-Fehler: ${fgEscapeHtml(e.message)}</span>`;
    }
});
