const THEME_KEY = 'rv-hard-theme';

applyStoredTheme();
ensureHomeFontsStylesheet();
injectThemeOverrides();

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

function injectThemeOverrides() {
  if (document.getElementById('rv-theme-overrides')) {
    return;
  }

  const style = document.createElement('style');
  style.id = 'rv-theme-overrides';
  style.textContent = `
html[data-theme="dark"] .gemeinschaft-item {
  background: var(--site-bg) !important;
  border-color: transparent !important;
  box-shadow: none !important;
}

html[data-theme="dark"] body {
  background: var(--site-bg) !important;
  color: var(--site-text) !important;
}

html[data-theme="dark"] {
  --card-bg: var(--site-surface-soft);
  --bg-main: var(--site-bg);
  --text-main: var(--site-heading);
  --text-muted: var(--site-text);
}

html[data-theme="dark"] .card,
html[data-theme="dark"] .news-card,
html[data-theme="dark"] .event-card,
html[data-theme="dark"] .news-item,
html[data-theme="dark"] .related-news-item,
html[data-theme="dark"] .fakt,
html[data-theme="dark"] .verein-vorstellung,
html[data-theme="dark"] .info-section,
html[data-theme="dark"] .strecken-section,
html[data-theme="dark"] .zeitplan-section,
html[data-theme="dark"] .anmeldung-section,
html[data-theme="dark"] .partner-sponsoren-section,
html[data-theme="dark"] .pdf-section,
html[data-theme="dark"] .danke-section,
html[data-theme="dark"] .vorstand-intro,
html[data-theme="dark"] .vorstandsmitglied,
html[data-theme="dark"] .verein-container,
html[data-theme="dark"] .geschichte-abschnitt,
html[data-theme="dark"] .zeitplan-tabelle,
html[data-theme="dark"] .zeitplan-tabelle tr,
html[data-theme="dark"] .rr-mobile-box,
html[data-theme="dark"] .sponsoren-grid,
html[data-theme="dark"] .training-seite section,
html[data-theme="dark"] .modern-event-card,
html[data-theme="dark"] .kontakt-container,
html[data-theme="dark"] .kontakt-container section,
html[data-theme="dark"] .verein-container section,
html[data-theme="dark"] .vorstand-kurz .mitglied,
html[data-theme="dark"] .vorstand-kurz .mitglied img,
html[data-theme="dark"] .membership-section {
  background: var(--site-surface-soft) !important;
  border-color: var(--site-border) !important;
}

html[data-theme="dark"] .timeline-content,
html[data-theme="dark"] .membership-card {
  background: var(--site-surface) !important;
  border: 1px solid rgba(255, 255, 255, 0.10) !important;
  box-shadow: 0 14px 34px rgba(0, 0, 0, 0.34) !important;
}

html[data-theme="dark"] .sponsoren-seite,
html[data-theme="dark"] .nightrace-container,
html[data-theme="dark"] .news-page,
html[data-theme="dark"] .verein-container,
html[data-theme="dark"] .training-seite,
html[data-theme="dark"] .kontakt-container,
html[data-theme="dark"] .termine-seite {
  background-color: var(--site-bg) !important;
}

html[data-theme="dark"] .timeline-item:first-child .timeline-content {
  background: #262f3a !important;
  border-color: rgba(255, 193, 7, 0.4) !important;
}

html[data-theme="dark"] .timeline::after {
  background: linear-gradient(to bottom, transparent, var(--accent-yellow), var(--accent-yellow), transparent) !important;
}

html[data-theme="dark"] .timeline-point {
  background: var(--accent-yellow) !important;
  box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.25) !important;
}

html[data-theme="dark"] .zeitplan-tabelle tbody tr:nth-child(even),
html[data-theme="dark"] .zeitplan-tabelle tbody tr:hover {
  background: var(--site-surface) !important;
}

html[data-theme="dark"] .related-news-tags .tag,
html[data-theme="dark"] .mitglied-mail,
html[data-theme="dark"] .year-filter {
  background: var(--site-surface) !important;
  color: var(--site-text) !important;
  border-color: var(--site-border) !important;
}

html[data-theme="dark"] .related-news-text p,
html[data-theme="dark"] .mitglied-funktion,
html[data-theme="dark"] .news-meta,
html[data-theme="dark"] .event-date,
html[data-theme="dark"] .jahr,
html[data-theme="dark"] .training-kontakt,
html[data-theme="dark"] .sparte-beschreibung,
html[data-theme="dark"] .sparte-verein,
html[data-theme="dark"] .training-label,
html[data-theme="dark"] .modern-event-card p,
html[data-theme="dark"] .modern-event-card .event-date-time,
html[data-theme="dark"] .kontakt-hinweis,
html[data-theme="dark"] .kontakt-summary,
html[data-theme="dark"] .dokumente-bereich h3,
html[data-theme="dark"] .kontakt-container p {
  color: var(--site-text) !important;
}

html[data-theme="dark"] .timeline-content p,
html[data-theme="dark"] .geschichte-abschnitt > p,
html[data-theme="dark"] .membership-section p,
html[data-theme="dark"] .membership-card p,
html[data-theme="dark"] .membership-card li,
html[data-theme="dark"] cite {
  color: var(--site-text) !important;
}

html[data-theme="dark"] .vorstand-intro h2,
html[data-theme="dark"] .vorstand-intro p,
html[data-theme="dark"] .mitglied-name,
html[data-theme="dark"] .related-news-text h3,
html[data-theme="dark"] .sponsoren-seite h1,
html[data-theme="dark"] .nightrace-container h1,
html[data-theme="dark"] .nightrace-container h2,
html[data-theme="dark"] .training-seite h2,
html[data-theme="dark"] .modern-event-card .event-title,
html[data-theme="dark"] .kontakt-container h1,
html[data-theme="dark"] .kontakt-container h2,
html[data-theme="dark"] .kontakt-container h3,
html[data-theme="dark"] .verein-container h1,
html[data-theme="dark"] .verein-container h2,
html[data-theme="dark"] .verein-container h3 {
  color: var(--site-heading) !important;
}

html[data-theme="dark"] .timeline-content h3,
html[data-theme="dark"] .membership-card h2 {
  color: var(--site-heading) !important;
}

html[data-theme="dark"] .training-seite section,
html[data-theme="dark"] .modern-event-card,
html[data-theme="dark"] .kontakt-container,
html[data-theme="dark"] .kontakt-container section,
html[data-theme="dark"] .verein-container section {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28) !important;
}

html[data-theme="dark"] .training-seite li i,
html[data-theme="dark"] .sparte-vorteile i,
html[data-theme="dark"] .modern-event-card .event-location i {
  color: var(--accent-yellow) !important;
}

html[data-theme="dark"] .dokument-link {
  color: var(--accent-yellow) !important;
  border-color: var(--accent-yellow) !important;
}

html[data-theme="dark"] .dokument-link:hover {
  background-color: var(--accent-yellow-hover) !important;
  color: #fff !important;
}

html[data-theme="dark"] #cookie-banner {
  background: #1f242c !important;
  color: var(--site-text) !important;
  border: 1px solid var(--site-border) !important;
}

html[data-theme="dark"] #cookie-banner h3,
html[data-theme="dark"] #cookie-banner p,
html[data-theme="dark"] #cookie-banner strong,
html[data-theme="dark"] #cookie-banner div {
  color: var(--site-text) !important;
}

html[data-theme="dark"] #cookie-banner #decline-all,
html[data-theme="dark"] #cookie-banner #open-preferences,
html[data-theme="dark"] #cookie-banner .save-pref {
  background: #2c3440 !important;
  color: var(--site-text) !important;
}

html[data-theme="dark"] #cookie-banner .cookie-checkbox {
  background: #232a33 !important;
  color: var(--site-text) !important;
}
`;

  document.head.appendChild(style);
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
