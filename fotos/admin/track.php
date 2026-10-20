<?php
/* ================================================================
 *  RV HARD – REICHWEITEN-MESSUNG DER FOTO-GALERIE
 * ================================================================
 *  Öffentlicher Zähl-Endpunkt (KEIN Login – Besucher sind ja nicht
 *  eingeloggt). Wird per navigator.sendBeacon() aus galerie.js
 *  aufgerufen und schreibt aggregierte Zähler in data/stats.json.
 *
 *  Datenschutz:
 *    - KEINE Cookies, KEINE Wiedererkennung über Tage hinweg.
 *    - Die IP-Adresse wird NICHT gespeichert. Für die Besucherzahl
 *      wird nur ein tagesaktueller, gesalzener Hash gebildet, der
 *      nach 60 Tagen automatisch gelöscht wird.
 *
 *  Aufruf (POST, JSON):
 *    { "t":"view" }                        → Galerie-Startseite aufgerufen
 *    { "t":"event", "e":"<eventId>" }      → Event geöffnet
 *    { "t":"photo", "e":"<eventId>", "f":"<datei>" }  → Foto geöffnet
 *
 *  Antwort: { "ok": true }
 * ================================================================ */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

error_reporting(E_ALL);
ini_set('display_errors', 0);

$rv_data = realpath(__DIR__ . '/../data');
if ($rv_data === false) { echo '{"ok":false}'; exit; }
$rv_data = rtrim(str_replace('\\', '/', $rv_data), '/');

define('RV_STATS_FILE', $rv_data . '/stats.json');
define('RV_SALT_FILE',  $rv_data . '/stats.salt');

/* ---------- Hilfsfunktionen ---------- */

function rv_out($ok, $extra = []) {
    echo json_encode(array_merge(['ok' => (bool)$ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/* Atomar schreiben: temp-Datei + rename */
function rv_save_json($file, array $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $file . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}

/* Lesen (mit Shared-Lock) */
function rv_load_stats() {
    if (!is_file(RV_STATS_FILE) || filesize(RV_STATS_FILE) === 0) return [];
    $fp = @fopen(RV_STATS_FILE, 'rb');
    if (!$fp) return [];
    @flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

/* ---------- 1) Nur POST, Body lesen ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    rv_out(false, ['error' => 'Nur POST erlaubt.']);
}

$rv_raw = file_get_contents('php://input');
if (!is_string($rv_raw) || $rv_raw === '') rv_out(false);
if (strlen($rv_raw) > 4096) rv_out(false);

/* ---------- 2) Bots aussortieren ---------- */
$rv_ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$rv_uaLow = strtolower($rv_ua);
if ($rv_uaLow === '') rv_out(true, ['skipped' => 'no-ua']); /* kein UA = fast immer Bot */
foreach (['bot', 'crawler', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit',
          'whatsapp', 'telegram', 'curl/', 'wget', 'python', 'headless', 'lighthouse',
          'uptimerobot', 'pingdom', 'monitoring', 'preview'] as $rv_n) {
    if (strpos($rv_uaLow, $rv_n) !== false) rv_out(true, ['skipped' => 'bot']);
}

/* ---------- 3) Eingabe validieren ---------- */
$rv_in    = json_decode($rv_raw, true);
$rv_type  = is_array($rv_in) ? (string)($rv_in['t'] ?? '') : '';
$rv_evid  = is_array($rv_in) ? (string)($rv_in['e'] ?? '') : '';
$rv_file  = is_array($rv_in) ? (string)($rv_in['f'] ?? '') : '';

if (!in_array($rv_type, ['view', 'event', 'photo'], true)) rv_out(false);

if (strlen($rv_evid) > 120) $rv_evid = substr($rv_evid, 0, 120);
if (strlen($rv_file) > 160) $rv_file = substr($rv_file, 0, 160);
if ($rv_evid !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $rv_evid)) $rv_evid = '';
if ($rv_file !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $rv_file)) $rv_file = '';

if ($rv_type === 'event' && $rv_evid === '') rv_out(false);
if ($rv_type === 'photo' && ($rv_evid === '' || $rv_file === '')) rv_out(false);

/* ---------- 4) Tagesaktueller Besucher-Hash (IP wird NICHT gespeichert) ---------- */
$rv_salt = is_file(RV_SALT_FILE) ? (string)@file_get_contents(RV_SALT_FILE) : '';
if (strlen($rv_salt) < 16) {
    try { $rv_salt = bin2hex(random_bytes(32)); }
    catch (Exception $e) { $rv_salt = hash('sha256', __FILE__ . php_uname()); }
    @file_put_contents(RV_SALT_FILE, $rv_salt, LOCK_EX);
    @chmod(RV_SALT_FILE, 0600);
}

$rv_day = date('Y-m-d');
$rv_ip  = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$rv_vid = substr(hash('sha256', $rv_day . '|' . $rv_ip . '|' . $rv_ua . '|' . $rv_salt), 0, 24);

/* ---------- 5) Statistik laden und aktualisieren ---------- */
$rv_st = rv_load_stats();
if (!isset($rv_st['version'])) {
    $rv_st = [
        'version'  => 1,
        'since'    => $rv_day,
        'totals'   => ['views' => 0, 'events' => 0, 'photos' => 0],
        'days'     => [],
        'visitors' => [],
        'events'   => [],
        'photos'   => [],
        'refs'     => [],
        'devices'  => ['mobile' => 0, 'desktop' => 0],
        'rl'       => [],
    ];
}
foreach (['totals', 'days', 'visitors', 'events', 'photos', 'refs', 'devices', 'rl'] as $k) {
    if (!isset($rv_st[$k]) || !is_array($rv_st[$k])) $rv_st[$k] = [];
}

/* --- Einfache Mengenbremse: max. 60 Aktionen pro Besucher und Minute --- */
$rv_min = (int)floor(time() / 60);
foreach ($rv_st['rl'] as $k => $v) {
    if (!is_array($v) || ($rv_min - (int)($v[0] ?? 0)) > 2) unset($rv_st['rl'][$k]);
}
$rv_cur = $rv_st['rl'][$rv_vid] ?? [$rv_min, 0];
if ((int)($rv_cur[0] ?? 0) !== $rv_min) $rv_cur = [$rv_min, 0];
$rv_cur[1] = (int)($rv_cur[1] ?? 0) + 1;
if ($rv_cur[1] > 60) rv_out(true, ['skipped' => 'rate']);
$rv_st['rl'][$rv_vid] = $rv_cur;

/* --- Aufräumen (einmal pro Tag) --- */
if (($rv_st['lastDay'] ?? '') !== $rv_day) {
    $rv_cutD = date('Y-m-d', time() - 400 * 86400);
    foreach ($rv_st['days'] as $d => $_) { if ($d < $rv_cutD) unset($rv_st['days'][$d]); }
    $rv_cutV = date('Y-m-d', time() - 60 * 86400);
    foreach ($rv_st['visitors'] as $d => $_) { if ($d < $rv_cutV) unset($rv_st['visitors'][$d]); }
    $rv_st['lastDay'] = $rv_day;
}

/* --- Tages-Werte holen --- */
if (!isset($rv_st['days'][$rv_day]) || !is_array($rv_st['days'][$rv_day])) {
    $rv_st['days'][$rv_day] = ['views' => 0, 'events' => 0, 'photos' => 0];
}
foreach (['views', 'events', 'photos'] as $k) {
    if (!isset($rv_st['days'][$rv_day][$k])) $rv_st['days'][$rv_day][$k] = 0;
}
$rv_st['visitors'][$rv_day][$rv_vid] = 1;

/* --- Zähler erhöhen --- */
$rv_now = date('c');
if ($rv_type === 'view') {
    $rv_st['totals']['views'] = (int)($rv_st['totals']['views'] ?? 0) + 1;
    $rv_st['days'][$rv_day]['views'] = (int)$rv_st['days'][$rv_day]['views'] + 1;
} elseif ($rv_type === 'event') {
    $rv_st['totals']['events'] = (int)($rv_st['totals']['events'] ?? 0) + 1;
    $rv_st['days'][$rv_day]['events'] = (int)$rv_st['days'][$rv_day]['events'] + 1;
    if (!isset($rv_st['events'][$rv_evid]) || !is_array($rv_st['events'][$rv_evid])) {
        $rv_st['events'][$rv_evid] = ['n' => 0, 'last' => ''];
    }
    $rv_st['events'][$rv_evid]['n'] = (int)$rv_st['events'][$rv_evid]['n'] + 1;
    $rv_st['events'][$rv_evid]['last'] = $rv_now;

    /* Notbremse: Event-Tabelle nicht unbegrenzt wachsen lassen */
    if (count($rv_st['events']) > 2000) {
        $rv_sorted = $rv_st['events'];
        uasort($rv_sorted, function ($a, $b) { return (int)$b['n'] <=> (int)$a['n']; });
        $rv_st['events'] = array_slice($rv_sorted, 0, 1000, true);
    }
} else { /* photo */
    $rv_st['totals']['photos'] = (int)($rv_st['totals']['photos'] ?? 0) + 1;
    $rv_st['days'][$rv_day]['photos'] = (int)$rv_st['days'][$rv_day]['photos'] + 1;
    $rv_key = $rv_evid . '|' . $rv_file;
    if (!isset($rv_st['photos'][$rv_key]) || !is_array($rv_st['photos'][$rv_key])) {
        $rv_st['photos'][$rv_key] = ['n' => 0, 'last' => '', 'e' => $rv_evid, 'f' => $rv_file];
    }
    $rv_st['photos'][$rv_key]['n'] = (int)$rv_st['photos'][$rv_key]['n'] + 1;
    $rv_st['photos'][$rv_key]['last'] = $rv_now;

    /* Notbremse: Fotos-Tabelle nicht unbegrenzt wachsen lassen */
    if (count($rv_st['photos']) > 20000) {
        $rv_sorted = $rv_st['photos'];
        uasort($rv_sorted, function ($a, $b) { return (int)$b['n'] <=> (int)$a['n']; });
        $rv_st['photos'] = array_slice($rv_sorted, 0, 10000, true);
    }
}

/* --- Gerät + Verweis (nur bei Galerie-Aufruf) --- */
if ($rv_type === 'view') {
    $rv_dev = preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone/i', $rv_ua) ? 'mobile' : 'desktop';
    $rv_st['devices'][$rv_dev] = (int)($rv_st['devices'][$rv_dev] ?? 0) + 1;

    $rv_ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $rv_host = '';
    if ($rv_ref !== '') {
        $rv_h = @parse_url($rv_ref, PHP_URL_HOST);
        if (is_string($rv_h)) $rv_host = strtolower($rv_h);
    }
    if ($rv_host === '') $rv_host = 'direkt';
    /* Interne Galerie-Navigation nicht als Verweis zählen */
    if (strpos($rv_host, 'fotos.rv-hard.at') === false) {
        if (count($rv_st['refs']) > 200 && !isset($rv_st['refs'][$rv_host])) {
            /* genug gesammelt → neue Verweise ignorieren */
        } else {
            $rv_st['refs'][$rv_host] = (int)($rv_st['refs'][$rv_host] ?? 0) + 1;
        }
    }
}

/* ---------- 6) Speichern ---------- */
rv_save_json(RV_STATS_FILE, $rv_st);
rv_out(true);
