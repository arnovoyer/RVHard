<?php
/* ================================================================
 *  RV HARD – FOTO-GALERIE: ÜBERSICHT (Galerie-Startseite)
 *  Die Event-Karten werden SERVERSEITIG aus data/events.json erzeugt.
 *  Grund: Google & Co. führen KEIN JavaScript aus. Vorher stand hier
 *  nur "Foto-Galerie wird geladen…" → die Übersicht wurde in der
 *  Search Console als "Gecrawlt – zurzeit nicht indexiert" geführt.
 *  galerie.js rendert anschließend identisch neu (Suche, Filter, AOS).
 * ================================================================ */

/* Galerie-Wurzel: '' (Subdomain) oder '/fotos' (Unterordner der Hauptdomain) */
$fgRoot = str_replace('\\', '/', (string)dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$fgRoot = ($fgRoot === '/' || $fgRoot === '.' || $fgRoot === '') ? '' : rtrim($fgRoot, '/');

$fgEventUrl = $fgRoot . '/event.php';
$fgE = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

/* Events laden (Schema-Objekte und ausgeblendete Events überspringen) */
$fgEvents = [];
$fgFile = __DIR__ . '/data/events.json';
if (is_file($fgFile)) {
    $fgJson = json_decode((string)@file_get_contents($fgFile), true);
    if (is_array($fgJson)) {
        foreach ($fgJson as $fgItem) {
            if (!is_array($fgItem) || isset($fgItem['_schemaVersion']) || !empty($fgItem['hidden'])) continue;
            $fgEvents[] = $fgItem;
        }
    }
}

/* Nach Jahr gruppieren (Jahr aus parentId/id, sonst "Archiv") */
$fgByYear = [];
foreach ($fgEvents as $fgEv) {
    $fgYear = '';
    if (preg_match('/(20\d{2})/', (string)($fgEv['parentId'] ?? $fgEv['id'] ?? ''), $fgM)) $fgYear = $fgM[1];
    if ($fgYear === '') $fgYear = 'Archiv';
    $fgByYear[$fgYear][] = $fgEv;
}
krsort($fgByYear);

/* Datum JJJJ-MM-TT -> TT.MM.JJJJ */
$fgFmtDate = function ($d) { return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$d, $m) ? $m[3].'.'.$m[2].'.'.$m[1] : ''; };

$fgIcon  = ['bergrennen' => 'fa-mountain-sun', 'ezf' => 'fa-stopwatch', 'kriterium' => 'fa-flag-checkered'];
$fgBadge = ['bergrennen' => 'Bergrennen', 'ezf' => 'EZF', 'kriterium' => 'Stadtkriterium'];

/* Cover wie galerie.js: erstes Foto-Thumbnail > coverPhoto > erstes Foto.
   KI-Platzhalter (coresg-normal.trae.ai) und /cover.jpg werden ignoriert. */
$fgCover = function ($ev) use ($fgRoot) {
    $p0 = (isset($ev['photos'][0]) && is_array($ev['photos'][0])) ? $ev['photos'][0] : null;
    $c = $p0 ? (string)($p0['thumbnail'] ?? '') : '';
    if ($c === '') $c = (string)($ev['coverPhoto'] ?? '');
    if ($c === '' && $p0) $c = (string)($p0['src'] ?? '');
    if ($c === '' || stripos($c, 'coresg-normal.trae.ai') !== false || preg_match('#/cover\.jpg(\?|$)#', $c)) return '';
    if (!preg_match('#^https?://#i', $c)) {
        if ($c[0] !== '/') $c = '/' . $c;
        if ($fgRoot !== '' && strpos($c, $fgRoot . '/') !== 0) $c = $fgRoot . $c;
    }
    return $c;
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Foto-Galerie | RV Hard</title>
    <meta name="description" content="Foto-Galerie des RV Hard – Bilder unserer Veranstaltungen: Rennen, Trainings und Vereinsleben in Hard, Vorarlberg.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://fotos.rv-hard.at/">
    <meta property="og:locale" content="de_AT">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="RV Hard">
    <meta property="og:title" content="Foto-Galerie | RV Hard">
    <meta property="og:description" content="Bilder unserer Veranstaltungen – Rennen, Trainings und Vereinsleben des RV Hard in Vorarlberg.">
    <meta property="og:url" content="https://fotos.rv-hard.at/">
    <meta property="og:image" content="https://rv-hard.at/assets/img/social/og-galerie-1200x630.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Foto-Galerie | RV Hard">
    <meta name="twitter:description" content="Bilder unserer Veranstaltungen – Rennen, Trainings und Vereinsleben des RV Hard in Vorarlberg.">
    <meta name="twitter:image" content="https://rv-hard.at/assets/img/social/og-galerie-1200x630.png">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "CollectionPage",
      "name": "Foto-Galerie RV Hard",
      "url": "https://fotos.rv-hard.at/",
      "description": "Bilder unserer Veranstaltungen – Rennen, Trainings und Vereinsleben des RV Hard.",
      "inLanguage": "de-AT",
      "isPartOf": { "@type": "WebSite", "name": "RV Hard", "url": "https://rv-hard.at/" }
    }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://unpkg.com">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" as="style">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"></noscript>
    <script>
        (function() {
            /* Automatisch den BASE-Pfad erkennen! (Gleiches Prinzip wie upload.php + galerie.js) */
            const here = String(window.location.pathname || '/');
            const inferBase = (here.indexOf('/fotos/') === 0 || here.endsWith('/fotos') || here === '/fotos')
                ? '/fotos' : '';
            const b = inferBase; /* /fotos  ODER  '' (Root) */
            const prefixCss = (b === '' ? './' : b + '/') + 'assets/';
            const assetsBase = (b === '' ? './' : '../') + 'assets/'; /* style.css liegt im übergeordneten */
            const logo = assetsBase + 'img/logo/favicon.png';

            /* Fav Icon */
            const link1 = document.createElement('link');
            link1.rel = 'icon'; link1.type = 'image/png'; link1.href = logo + '?v=20260918';
            document.write(link1.outerHTML);

            /* style.css (Haupt-Site) */
            const link2 = document.createElement('link');
            link2.rel = 'stylesheet'; link2.href = assetsBase + 'style.css?v=20260918';
            document.write(link2.outerHTML);

            /* galerie.css */
            const link3 = document.createElement('link');
            link3.rel = 'stylesheet'; link3.href = prefixCss + 'galerie.css?v=20261103';
            document.write(link3.outerHTML);
        })();
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="https://cdn.jsdelivr.net/npm/aos@3.0.0-beta.6/dist/aos.css" rel="stylesheet">
</head>
<body>
    <header>
        <nav id="navigation-container"></nav>
    </header>

    <main>
        <section class="fg-container">

            <div class="fg-section" data-aos="fade-up">
                <h1>Foto-Galerie</h1>

                <p class="lead" style="margin-top: 0.75rem;">
                    Bilder unserer Veranstaltungen auf einen Blick.
                </p>

                <div class="fg-search-row" style="margin-top: 1.5rem;">
                    <input type="search" id="fg-search" placeholder="🔍 Suche nach Ort, Jahr…" aria-label="Events durchsuchen" style="min-width: 260px;">
                    <select id="fg-filter-year" aria-label="Jahr Filter">
                        <option value="all">Alle Jahre</option>
<?php foreach (array_keys($fgByYear) as $fgY): if ($fgY === 'Archiv') continue; ?>
                        <option value="<?= $fgE($fgY) ?>"><?= $fgE($fgY) ?></option>
<?php endforeach; ?>
                    </select>
                </div>

                <!-- EVENT-KARTEN (SERVERSEITIG vorbelegt; galerie.js übernimmt danach Suche/Filter) -->
                <div id="fg-events" class="fg-events-grid" style="margin-top:10px;">
<?php if (!$fgEvents): ?>
                    <div class="fg-empty"><strong>Zurzeit sind keine Events veröffentlicht.</strong></div>
<?php else: ?>
<?php foreach ($fgByYear as $fgYearGroup => $fgList): ?>
                    <div style="grid-column: 1/-1; margin: 1.5rem 0 0.5rem;">
                        <h2 style="margin:0; font-size:1.5rem; display:flex; align-items:center; gap:0.75rem;">
                            <span style="background:#111; color:#ffc107; padding:0.25rem 0.85rem; border-radius: 8px; font-weight: 800;">Events <?= $fgE($fgYearGroup) ?></span>
                            <span style="font-size:0.9rem; color:#777; font-weight: 500;"><?= count($fgList) ?> Einträge</span>
                        </h2>
                        <hr style="border:none; border-top: 2px dashed #eee; margin:0.5rem 0 0;">
                    </div>
<?php foreach ($fgList as $fgEv):
    $fgId = (string)($fgEv['id'] ?? ''); if ($fgId === '') continue;
    $fgCoverUrl = $fgCover($fgEv);
    $fgCount = (isset($fgEv['photos']) && is_array($fgEv['photos'])) ? count($fgEv['photos']) : 0;
    $fgSub = (string)($fgEv['subtype'] ?? '');
?>
                    <a class="fg-event-card" href="<?= $fgE($fgEventUrl . '?id=' . rawurlencode($fgId)) ?>">
                        <div class="fg-event-card__cover"<?= $fgCoverUrl !== '' ? ' style="background-image:url(&quot;' . $fgE($fgCoverUrl) . '&quot;)"' : '' ?>>
<?php if (isset($fgBadge[$fgSub])): ?>
                            <span class="fg-event-card__badge"><?= $fgE($fgBadge[$fgSub]) ?></span>
<?php endif; ?>
                            <div class="fg-event-card__count"><i class="fa-regular fa-image"></i> <?= $fgCount ?></div>
                        </div>
                        <div class="fg-event-card__body">
                            <h3><i class="fa-solid <?= $fgE($fgIcon[$fgSub] ?? 'fa-bicycle') ?>" style="color:#f5b301;"></i> <?= $fgE((string)($fgEv['shortName'] ?? $fgEv['name'] ?? '')) ?></h3>
                            <div class="fg-event-card__meta">
<?php $fgD = $fgFmtDate($fgEv['date'] ?? ''); if ($fgD !== ''): ?>
                                <span><i class="fa-regular fa-calendar"></i> <?= $fgE($fgD) ?></span>
<?php endif; ?>
<?php if (trim((string)($fgEv['location'] ?? '')) !== ''): ?>
                                <span><i class="fa-solid fa-location-dot"></i> <?= $fgE($fgEv['location']) ?></span>
<?php endif; ?>
<?php if (trim((string)($fgEv['distance'] ?? '')) !== ''): ?>
                                <span><i class="fa-solid fa-route"></i> <?= $fgE($fgEv['distance']) ?></span>
<?php endif; ?>
                            </div>
<?php if (trim((string)($fgEv['description'] ?? '')) !== ''): ?>
                            <p class="fg-event-card__desc"><?= $fgE($fgEv['description']) ?></p>
<?php endif; ?>
                        </div>
                    </a>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer id="site-footer"></footer>

    <!-- 🔥🔥 SUBDOMAIN KOMPATIBLE JS-Links! RELATIVE PFAD-ANGABEN! -->
    <script>
        (function() {
            const here = String(window.location.pathname || '/');
            const b = (here.indexOf('/fotos/') === 0 || here.endsWith('/fotos') || here === '/fotos') ? '/fotos' : '';
            const assetBase = (b === '' ? './' : '../') + 'assets/';
            const galerieBase = (b ? b + '/' : './') + 'assets/';
            document.write(`<script src="${assetBase}navigation-loader.js?v=20260918" defer><\/script>`);
            document.write(`<script src="${assetBase}nav.js?v=20260918" defer><\/script>`);
            document.write(`<script src="${galerieBase}galerie.js?v=20261109" defer><\/script>`);
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/aos@3.0.0-beta.6/dist/aos.js" defer></script>
    <script>
        window.addEventListener('load', () => {
            if (typeof AOS !== 'undefined') {
                AOS.init({ duration: 800, once: true, mirror: false });
            }
        });
    </script>
</body>
</html>
