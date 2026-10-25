<?php
/* ================================================================
 *  RV HARD – FOTO-GALERIE SITEMAP (dynamisch, ohne Datenbank!)
 * ================================================================
 *  Erzeugt https://fotos.rv-hard.at/sitemap.php aus data/events.json.
 *  Es werden NUR öffentlich sichtbare Events aufgenommen
 *  (Events mit hidden = true bleiben außen vor).
 *
 *  Einbinden in robots.txt:
 *    Sitemap: https://fotos.rv-hard.at/sitemap.php
 * ================================================================ */

header('Content-Type: application/xml; charset=utf-8');

$base = 'https://fotos.rv-hard.at';
$eventsFile = __DIR__ . '/data/events.json';

$urls = [];
$urls[] = ['loc' => $base . '/', 'lastmod' => null, 'priority' => '1.0'];

$events = [];
if (is_file($eventsFile)) {
    $json = json_decode((string)@file_get_contents($eventsFile), true);
    if (is_array($json)) $events = $json;
}

foreach ($events as $e) {
    if (!is_array($e) || isset($e['_schemaVersion'])) continue;
    if (!empty($e['hidden'])) continue;                 /* ausgeblendete Events nicht listen */
    $id = isset($e['id']) ? trim((string)$e['id']) : '';
    if ($id === '') continue;

    $lastmod = '';
    foreach (['_lastModified', 'date'] as $k) {
        if (!empty($e[$k]) && preg_match('/\d{4}-\d{2}-\d{2}/', (string)$e[$k], $m)) { $lastmod = $m[0]; break; }
    }

    $urls[] = [
        'loc'      => $base . '/event.php?id=' . rawurlencode($id),
        'lastmod'  => $lastmod !== '' ? $lastmod : null,
        'priority' => '0.7',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?php echo htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></loc>
<?php if (!empty($u['lastmod'])): ?>
    <lastmod><?php echo htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8'); ?></lastmod>
<?php endif; ?>
    <priority><?php echo $u['priority']; ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
