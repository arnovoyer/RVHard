import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const cmsPath = path.join(root, 'data', 'news', 'news-cms.json');
const legacyPath = path.join(root, 'data', 'news', 'legacy-slugs.json');
const newsDir = path.join(root, 'news');
const force = process.argv.includes('--force');
const marker = '<!-- generated: simple-cms -->';

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function escapeHtml(value = '') {
  return String(value).replace(/[&<>"']/g, (m) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[m]));
}

function normalizeNewsPayload(payload) {
  if (Array.isArray(payload)) return payload;
  if (payload && Array.isArray(payload.items)) return payload.items;
  return [];
}

function normalizeImageList(article) {
  const images = [];
  if (typeof article.image === 'string' && article.image.trim()) {
    images.push(article.image.trim());
  }
  if (Array.isArray(article.images)) {
    for (const item of article.images) {
      if (typeof item === 'string' && item.trim()) {
        images.push(item.trim());
      } else if (item && typeof item.image === 'string' && item.image.trim()) {
        images.push(item.image.trim());
      }
    }
  }
  return [...new Set(images)];
}

function markdownToHtml(markdown = '') {
  const text = String(markdown || '').replace(/\r\n/g, '\n').trim();
  if (!text) return '';

  const lines = text.split('\n');
  const html = [];
  let inList = false;

  const flushList = () => {
    if (inList) {
      html.push('</ul>');
      inList = false;
    }
  };

  for (const rawLine of lines) {
    const line = rawLine.trim();

    if (!line) {
      flushList();
      continue;
    }

    if (line.startsWith('### ')) {
      flushList();
      html.push(`<h3>${escapeHtml(line.slice(4))}</h3>`);
      continue;
    }

    if (line.startsWith('## ')) {
      flushList();
      html.push(`<h2>${escapeHtml(line.slice(3))}</h2>`);
      continue;
    }

    if (line.startsWith('# ')) {
      flushList();
      html.push(`<h1>${escapeHtml(line.slice(2))}</h1>`);
      continue;
    }

    if (line.startsWith('- ') || line.startsWith('* ')) {
      if (!inList) {
        html.push('<ul>');
        inList = true;
      }
      html.push(`<li>${escapeHtml(line.slice(2))}</li>`);
      continue;
    }

    flushList();
    const paragraph = escapeHtml(line)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>');
    html.push(`<p>${paragraph}</p>`);
  }

  flushList();
  return html.join('\n');
}

function renderGallery(article) {
  const images = normalizeImageList(article);
  if (!images.length) return '';

  const slides = images
    .map((src) => `<div class="gallery-slide" style="background-image: url('${src}');"></div>`)
    .join('\n');

  return `
    <div class="news-detail-gallery-wrapper" data-aos="fade-up">
      <div class="news-detail-gallery">
        ${slides}
      </div>
      <button class="gallery-control prev">&lt;</button>
      <button class="gallery-control next">&gt;</button>
    </div>
  `;
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  if (Number.isNaN(d.getTime())) return escapeHtml(dateStr || '');
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: 'long', year: 'numeric' });
}

function buildArticleHtml(article) {
  const title = escapeHtml(article.title || 'News');
  const date = formatDate(article.date);
  const bodyHtml = article.body ? markdownToHtml(article.body) : `<p>${escapeHtml(article.content || '')}</p>`;
  const galleryHtml = renderGallery(article);

  return `<!DOCTYPE html>
${marker}
<html lang="de">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>${title} | RV Hard</title>
  <meta name="description" content="News und Rennberichte des RV Hard." />
  <meta name="robots" content="index, follow" />
  <link rel="stylesheet" href="/assets/style.css" />
  <link rel="stylesheet" href="/assets/news/news.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@3.0.0-beta.6/dist/aos.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'" />
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" />
  </noscript>
</head>

<body>
  <header>
    <nav id="navigation-container"></nav>
  </header>

  <main class="news-detail-page">
    <section class="container">
      <div class="news-layout">
        <div id="news-detail-container" data-aos="fade-up">
          <h1 id="news-title" data-aos="fade-up">${title}</h1>
          <p id="news-date" class="date" data-aos="fade-up">${date}</p>
          <div id="news-content" class="news-content-detail" data-aos="fade-up">${galleryHtml}${bodyHtml}</div>
        </div>
        <div class="news-sidebar" data-aos="fade-left">
          <aside id="related-news" class="more-news">
            <h2>Weitere Neuigkeiten</h2>
            <div class="related-news-list"></div>
          </aside>
        </div>
      </div>
    </section>
  </main>

  <footer id="site-footer"></footer>

  <script src="/assets/navigation-loader.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      try {
        const navRes = await fetch('/assets/navigationneu.html');
        if (navRes.ok) {
          document.getElementById('navigation-container').innerHTML = await navRes.text();
          if (typeof initNavigationMenu === 'function') initNavigationMenu();
        }

        const footerRes = await fetch('/assets/footer.html');
        if (footerRes.ok) {
          document.getElementById('site-footer').innerHTML = await footerRes.text();
        }

        if (typeof window.initNewsGalleries === 'function') {
          window.initNewsGalleries(document);
        }
      } catch (err) {
        console.error('Fehler beim Laden von Navigation/Footer:', err);
      }
    });
  </script>
  <script src="/assets/news/news.js" defer></script>
  <script src="/assets/news/related-news-loader.js" defer></script>
  <script src="/assets/nav.js" defer></script>
  <script src="/assets/header-scroll.js" defer></script>
  <script src="/assets/cookie-banner.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@3.0.0-beta.6/dist/aos.js"></script>
  <script>
    window.addEventListener('load', () => {
      if (window.AOS && typeof window.AOS.init === 'function') {
        window.AOS.init({ duration: 1000, once: true, mirror: false });
      }
    });
  </script>
</body>

</html>
`;
}

const payload = readJson(cmsPath);
const items = normalizeNewsPayload(payload);

if (!items.length) {
  console.log('Keine News gefunden.');
  process.exit(0);
}

if (!fs.existsSync(newsDir)) {
  fs.mkdirSync(newsDir, { recursive: true });
}

const generated = [];

for (const article of items) {
  const slug = String(article.slug || '').trim();
  if (!slug) continue;

  const targetPath = path.join(newsDir, `${slug}.html`);
  if (fs.existsSync(targetPath) && !force) {
    const existing = fs.readFileSync(targetPath, 'utf8');
    if (!existing.includes(marker)) continue;
  }

  const html = buildArticleHtml(article);
  fs.writeFileSync(targetPath, html, 'utf8');
  generated.push(slug);
}

if (fs.existsSync(legacyPath)) {
  const legacy = readJson(legacyPath);
  const merged = Array.from(new Set([...(Array.isArray(legacy) ? legacy : []), ...generated])).sort();
  fs.writeFileSync(legacyPath, JSON.stringify(merged, null, 2), 'utf8');
}

console.log(`Erstellt/aktualisiert: ${generated.length}`);
if (generated.length) {
  console.log(generated.join('\n'));
}
