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

  function clickLabel(element) {
    const raw = element.getAttribute('data-track')
      || element.getAttribute('aria-label')
      || element.getAttribute('title')
      || element.textContent
      || '';
    const text = raw.replace(/\s+/g, ' ').trim();
    return text.length > 80 ? text.slice(0, 80) : text;
  }

  if (!IGNORED.test(currentPath())) {
    send({ t: 'page', p: currentPath() });
  }

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

    const text = clickLabel(target);
    if (!text) {
      return;
    }

    send({ t: 'click', p: path, a: text });
  }, true);
})();
