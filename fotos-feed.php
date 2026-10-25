<?php
/* ================================================================
 *  RV HARD – FOTOS-FEED FÜR DIE STARTSEITE
 * ================================================================
 *  Liefert eine kleine, aufbereitete Liste der neuesten (nicht
 *  ausgeblendeten) Foto-Events – gedacht für die Startseite.
 *
 *  Warum PHP und nicht direkt fetch() im Browser?
 *    - Die Galerie liegt auf einer eigenen Subdomain (fotos.rv-hard.at).
 *      Ein Browser-fetch() von rv-hard.at bräuchte CORS.
 *    - Server-zu-Server ist unabhängig von CORS und Pfaden.
 *    - Das Ergebnis wird 10 Minuten gecacht (schont den Server).
 *
 *  Antwort (JSON):
 *    { "ok": true, "events": [ { id, name, shortName, date, location,
 *                                image, url, photos } ] }
 *  Bei Fehlern: { "ok": false, "events": [] }
 *  → Die Startseiten-Sektion bleibt dann einfach versteckt.
 * ================================================================ */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

error_reporting(E_ALL);
ini_set('display_errors', 0);

/* ---- Konfiguration ---- */
define('FF_GALLERY_BASE', 'https://fotos.rv-hard.at'); /* Galerie-Basis, OHNE / am Ende */
define('FF_LIMIT', 6);      /* max. Kacheln auf der Startseite */
define('FF_CACHE_TTL', 600); /* Cache-Dauer in Sekunden */

$ffCacheFile = rtrim(sys_get_temp_dir(), '/\\') . '/rvhard-fotos-feed.json';

/* ---- Hilfsfunktionen ---- */

/* Relative Bildpfade der Galerie zu absoluten URLs machen
 * ("/data/…" → "https://fotos.rv-hard.at/data/…") */
function ff_abs_url($u, $base) {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    if ($u[0] === '/') return $base . $u;
    return $base . '/' . ltrim($u, '/');
}

/* Server-seitiger HTTP-Abruf (cURL, sonst file_get_contents) */
function ff_http_get($url, $timeout = 8) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'RVHard-Startseite',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? $body : null;
    }
    if (@ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'timeout' => $timeout,
            'header'  => "Accept: application/json\r\nUser-Agent: RVHard-Startseite\r\n",
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        return ($body !== false) ? $body : null;
    }
    return null;
}

/* Events.json einlesen – mit Kommentar-Cleanup wie im Upload-Skript */
function ff_decode_events($raw) {
    if (!is_string($raw) || $raw === '') return [];
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $cleaned = preg_replace('!/\*.*?\*/!s', '', $raw);
        $cleaned = preg_replace('/^\s*\/\/.*$/m', '', $cleaned);
        $cleaned = preg_replace('/,\s*([\]}])/m', '$1', $cleaned);
        $decoded = json_decode(trim($cleaned), true);
    }
    return is_array($decoded) ? $decoded : [];
}

/* ---- 1) Cache prüfen ---- */
if (is_file($ffCacheFile) && (time() - (int)@filemtime($ffCacheFile)) < FF_CACHE_TTL) {
    $cached = @file_get_contents($ffCacheFile);
    if (is_string($cached) && $cached !== '') { echo $cached; exit; }
}

/* ---- 2) Galerie-Daten holen ---- */
$raw = ff_http_get(FF_GALLERY_BASE . '/data/events.json');
$events = [];

if (is_string($raw) && $raw !== '') {
    foreach (ff_decode_events($raw) as $ev) {
        if (!is_array($ev) || isset($ev['_schemaVersion'])) continue;
        if (!empty($ev['hidden'])) continue;          /* ausgeblendete Events überspringen */

        $id = (string)($ev['id'] ?? '');
        if ($id === '') continue;

        $photos = is_array($ev['photos'] ?? null) ? $ev['photos'] : [];

        /* Bestes Bild für die Kachel: Thumbnail → Original → coverPhoto */
        $img = '';
        if (!empty($photos[0]) && is_array($photos[0])) {
            $img = ff_abs_url((string)($photos[0]['thumbnail'] ?? ''), FF_GALLERY_BASE);
            if ($img === '') $img = ff_abs_url((string)($photos[0]['src'] ?? ''), FF_GALLERY_BASE);
        }
        if ($img === '') {
            $cover = (string)($ev['coverPhoto'] ?? '');
            if ($cover !== ''
                && stripos($cover, 'coresg-normal.trae.ai') === false
                && stripos($cover, 'via.placeholder') === false) {
                $img = ff_abs_url($cover, FF_GALLERY_BASE);
            }
        }
        if ($img === '') continue;                     /* ohne Bild keine Kachel */

        $events[] = [
            'id'        => $id,
            'name'      => (string)($ev['name'] ?? ''),
            'shortName' => (string)($ev['shortName'] ?? ($ev['name'] ?? '')),
            'date'      => (string)($ev['date'] ?? ''),
            'location'  => (string)($ev['location'] ?? ''),
            'image'     => $img,
            'url'       => FF_GALLERY_BASE . '/event.php?id=' . rawurlencode($id),
            'photos'    => count($photos),
        ];
    }
}

/* ---- 3) Neueste zuerst, auf FF_LIMIT begrenzen ---- */
usort($events, function ($a, $b) {
    return strcmp((string)$b['date'], (string)$a['date']);
});
$events = array_slice($events, 0, FF_LIMIT);

/* ---- 4) Ausgabe (+ Cache) ---- */
if (!$events) {
    /* Nichts gefunden (Galerie offline?) → alten Cache ausliefern, falls vorhanden */
    $stale = is_file($ffCacheFile) ? @file_get_contents($ffCacheFile) : '';
    if (is_string($stale) && $stale !== '') { echo $stale; exit; }
}

$json = json_encode(
    ['ok' => (bool)$events, 'updated' => date('c'), 'events' => $events],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if ($json === false) $json = '{"ok":false,"events":[]}';

if ($events) @file_put_contents($ffCacheFile, $json, LOCK_EX);

echo $json;
