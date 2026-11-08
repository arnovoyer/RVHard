const THEME_KEY = 'rv-hard-theme';

applyStoredTheme();
ensureHomeFontsStylesheet();

document.addEventListener('DOMContentLoaded', () => {
  fetch('/assets/navigationneu.html')
    .then(response => response.text())
    .then(html => {
      const navContainer = document.getElementById('navigation-container');
      if (!navContainer) {
        return;
      }

      navContainer.innerHTML = html;
      initNavigationMenu();
      initThemeToggle();
    })
    .catch(error => {
      console.error('Fehler beim Laden der Navigation:', error);
    });
});

function applyStoredTheme() {
  const storedTheme = localStorage.getItem(THEME_KEY);
  const fallbackTheme = 'light';
  const theme = storedTheme === 'dark' || storedTheme === 'light' ? storedTheme : fallbackTheme;
  document.documentElement.setAttribute('data-theme', theme);
}

function ensureHomeFontsStylesheet() {
  const href = '/assets/home-fonts.css';
  const alreadyLoaded = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
    .some(link => (link.getAttribute('href') || '').includes('/assets/home-fonts.css'));

  if (alreadyLoaded) {
    return;
  }

  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = href;
  document.head.appendChild(link);
}

function setTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem(THEME_KEY, theme);
}

function initThemeToggle() {
  const button = document.querySelector('.theme-toggle');
  
  // Falls Button nicht direkt verfügbar (noch nicht im DOM), mit Retry-Logik warten
  if (!button) {
    const checkButton = () => {
      const newButton = document.querySelector('.theme-toggle');
      if (newButton && !newButton.classList.contains('theme-toggle-initialized')) {
        attachThemeToggleListener(newButton);
      }
    };
    
    // Mehrere Versuche mit Verzögerung
    setTimeout(checkButton, 100);
    setTimeout(checkButton, 500);
    setTimeout(checkButton, 1000);
    
    // Zusätzlich: Beobachter für spätes Hinzufügen des Buttons
    const observer = new MutationObserver(() => {
      const newButton = document.querySelector('.theme-toggle');
      if (newButton && !newButton.classList.contains('theme-toggle-initialized')) {
        attachThemeToggleListener(newButton);
        observer.disconnect();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
    
    return;
  }

  attachThemeToggleListener(button);
}

function attachThemeToggleListener(button) {
  // Verhindere mehrfaches Registrieren
  if (button.classList.contains('theme-toggle-initialized')) {
    return;
  }
  
  button.classList.add('theme-toggle-initialized');
  syncThemeButton(button);

  button.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    setTheme(next);
    syncThemeButton(button);
  });
}

function syncThemeButton(button) {
  const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  const icon = button.querySelector('.theme-toggle-icon');
  const label = button.querySelector('.theme-toggle-label');
  const isDark = theme === 'dark';

  button.setAttribute('aria-pressed', String(isDark));
  button.setAttribute('aria-label', isDark ? 'High Contrast Mode deaktivieren' : 'High Contrast Mode aktivieren');

  if (icon) {
    icon.textContent = isDark ? '☀️' : '🌙';
  }

  if (label) {
    label.textContent = 'Kontrastmodus';
  }
}

function initNavigationMenu() {
  const toggleBtn = document.querySelector('.menu-toggle');
  const navMenu = document.querySelector('.nav-main-menu');

  if (!toggleBtn || !navMenu) {
    return;
  }

  toggleBtn.addEventListener('click', () => {
    navMenu.classList.toggle('active');
    toggleBtn.classList.toggle('active');
    document.body.classList.toggle('no-scroll', navMenu.classList.contains('active'));
  });

  document.addEventListener('click', (event) => {
    if (window.innerWidth > 794) {
      return;
    }

    const clickedInsideNav = event.target.closest('.nav');
    if (!clickedInsideNav && navMenu.classList.contains('active')) {
      navMenu.classList.remove('active');
      toggleBtn.classList.remove('active');
      document.body.classList.remove('no-scroll');
    }
  });

  document.querySelectorAll('.nav-main-menu li > a').forEach(link => {
    link.addEventListener('click', function (event) {
      if (window.innerWidth > 794) {
        return;
      }

      const li = this.parentElement;
      const submenu = li.querySelector(':scope > ul');

      if (!submenu) {
        navMenu.classList.remove('active');
        toggleBtn.classList.remove('active');
        document.body.classList.remove('no-scroll');
        return;
      }

      event.preventDefault();
      Array.from(li.parentElement.children).forEach(sibling => {
        if (sibling !== li) {
          sibling.classList.remove('open');
        }
      });
      li.classList.toggle('open');
    });
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 794) {
      navMenu.classList.remove('active');
      toggleBtn.classList.remove('active');
      document.body.classList.remove('no-scroll');
    }
  });
}

/* -----------------------------------------------------------------
 *  Anonyme Reichweiten-Messung der Hauptseite.
 *  Sendet Seitenaufrufe und Klicks an /cms/track.php.
 *  Keine Cookies, keine Zuordnung zu Personen – Details und die
 *  Auswertung stehen im CMS unter "Statistik".
 * ----------------------------------------------------------------- */
(function () {
  const host = window.location.hostname;

  /* Nur die öffentliche Hauptdomain messen. Die Foto-Galerie
     (fotos.rv-hard.at) hat eine eigene Statistik. */
  if (host !== 'rv-hard.at' && host !== 'www.rv-hard.at') {
    return;
  }

  const ENDPOINT = '/cms/track.php';
  const IGNORED = /^\/(fotos|cms|update|bilder)(\/|$)/;
  const NOTRACK_COOKIE = 'rv_notrack';
  const DOWNLOAD_RE = /\.(pdf|zip|docx?|xlsx?|pptx?|csv|ics|gpx|jpe?g|png|webp|mp4|mov|mp3)(\?|#|$)/i;

  function readCookie(name) {
    const match = ('; ' + document.cookie).match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? match[1] : '';
  }

  /* Austritt / Wiedereintritt per Link: ...?notrack=1 bzw. ?notrack=0
     (z. B. vom CMS-Button verlinkt). Der Zustand bleibt 1 Jahr erhalten. */
  try {
    const params = new URLSearchParams(window.location.search);
    if (params.has('notrack')) {
      const on = params.get('notrack') !== '0';
      document.cookie = NOTRACK_COOKIE + '=' + (on ? '1' : '') +
        '; path=/; max-age=' + (on ? 31536000 : 0) + '; samesite=Lax';
    }
  } catch (error) {}

  /* Wer die Messung abbestellt hat, wird nicht gezählt. */
  if (readCookie(NOTRACK_COOKIE) === '1') {
    return;
  }

  function currentPath() {
    return window.location.pathname || '/';
  }

  function send(payload) {
    try {
      const body = JSON.stringify(payload);
      if (navigator.sendBeacon) {
        navigator.sendBeacon(ENDPOINT, new Blob([body], { type: 'application/json' }));
        return;
      }
      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        keepalive: true
      });
    } catch (error) {
      /* Messung darf die Seite nie beeinträchtigen */
    }
  }

  function clip(value, max) {
    const text = String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
    return text.length > max ? text.slice(0, max) : text;
  }

  function clickLabel(element) {
    return clip(
      element.getAttribute('data-track')
        || element.getAttribute('aria-label')
        || element.getAttribute('title')
        || element.textContent
        || '',
      80
    );
  }

  /* Ziel eines Links einordnen: Download, externer Link oder normal. */
  function linkKind(anchor) {
    if (anchor.hasAttribute('download')) {
      return 'dl';
    }
    const href = anchor.getAttribute('href') || '';
    if (href === '' || href.startsWith('#')) {
      return '';
    }
    if (href.startsWith('mailto:') || href.startsWith('tel:')) {
      return '';
    }
    if (DOWNLOAD_RE.test(href)) {
      return 'dl';
    }
    try {
      const url = new URL(anchor.href, window.location.href);
      if (url.hostname && url.hostname !== window.location.hostname) {
        return 'ext';
      }
    } catch (error) {}
    return '';
  }

  /* --- Seitenaufruf (inkl. Kampagnen-Kennung aus UTM-Parametern) --- */
  if (!IGNORED.test(currentPath())) {
    const payload = { t: 'page', p: currentPath() };
    try {
      const params = new URLSearchParams(window.location.search);
      const source = clip(params.get('utm_source') || '', 60);
      const campaign = clip(params.get('utm_campaign') || '', 60);
      if (source || campaign) {
        payload.c = source;
        payload.m = campaign;
      }
    } catch (error) {}
    send(payload);
  }

  /* --- Klicks, Downloads und externe Links --- */
  document.addEventListener('click', (event) => {
    const el = event.target;
    if (!el || typeof el.closest !== 'function') {
      return;
    }

    const target = el.closest('a, button');
    if (!target || target.closest('.theme-toggle, .menu-toggle')) {
      return;
    }

    const path = currentPath();
    if (IGNORED.test(path)) {
      return;
    }

    if (target.tagName === 'A') {
      const kind = linkKind(target);
      if (kind) {
        const label = clickLabel(target) || clip(target.getAttribute('href') || '', 80);
        if (label) {
          send({ t: kind, p: path, a: label, h: clip(target.getAttribute('href') || '', 160) });
          return;
        }
      }
    }

    const text = clickLabel(target);
    if (!text) {
      return;
    }

    send({ t: 'click', p: path, a: text });
  }, true);

  /* --- Verweildauer & Scroll-Tiefe --- */
  const startTime = Date.now();
  let maxScroll = 0;

  function measureScroll() {
    const doc = document.documentElement;
    const body = document.body || {};
    const height = Math.max(
      doc.scrollHeight || 0, body.scrollHeight || 0,
      doc.offsetHeight || 0, body.offsetHeight || 0
    );
    const viewport = window.innerHeight || doc.clientHeight || 0;
    const scrollable = Math.max(1, height - viewport);
    const top = window.scrollY || doc.scrollTop || body.scrollTop || 0;
    const depth = Math.round(Math.min(1, Math.max(0, top / scrollable)) * 100);
    if (depth > maxScroll) {
      maxScroll = depth;
    }
  }

  window.addEventListener('scroll', measureScroll, { passive: true });
  measureScroll();

  let engageSent = false;
  function sendEngagement() {
    if (engageSent || IGNORED.test(currentPath())) {
      return;
    }
    engageSent = true;
    const seconds = Math.round((Date.now() - startTime) / 1000);
    if (seconds < 1 && maxScroll <= 0) {
      return;
    }
    send({ t: 'engage', p: currentPath(), d: seconds, s: maxScroll });
  }

  window.addEventListener('pagehide', sendEngagement);

  /* --- Teilen-Nutzung: Meldung aus /assets/share.js --- */
  window.addEventListener('rv-track', (event) => {
    const detail = event && event.detail ? event.detail : null;
    if (!detail || IGNORED.test(currentPath())) {
      return;
    }
    send(Object.assign({ p: currentPath() }, detail));
  });
})();
