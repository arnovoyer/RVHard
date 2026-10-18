<?php
/* ================================================================
 *  RV HARD – FOTO LÖSCHEN (AJAX, OHNE Datenbank!)
 * ================================================================
 *  Entfernt EIN Foto komplett:
 *    1) Eintrag aus data/events.json (flock + tmp + rename)
 *    2) Original-Datei
 *    3) Thumbnail (._rvthumb.webp / .jpg)
 *    4) Display-Version (._rvdisplay.webp / .jpg)
 *
 *  Aufruf (POST, JSON-Body):
 *    { "eventId":"sbs-2026-kriterium-kammgarn-hard",
 *      "filename":"foto_042.jpg",
 *      "csrf":"...." }
 *
 *  Antwort (JSON): { ok: true|false, message: "...", deleted: [...] }
 * ================================================================ */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once(__DIR__ . '/auth2.php');

/* JSON-Antwort + Ende */
function dp_out($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok' => (bool)$ok, 'message' => $msg], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* Öffentlichen Pfad (/fotos/data/… oder /data/…) → absoluter Server-Pfad.
 * Gibt null zurück, wenn der Pfad nicht eindeutig in /data/ liegt. */
function dp_abs_from_public($public, $dataDir) {
    $p = str_replace('\\', '/', trim((string)$public));
    if ($p === '') return null;
    /* Query/Fragment abschneiden */
    $cut = strcspn($p, '?#');
    if ($cut < strlen($p)) $p = substr($p, 0, $cut);
    /* Absolute URL → nur den Pfad-Teil nehmen */
    if (preg_match('#^https?://#i', $p)) {
        $pu = @parse_url($p);
        $p = is_array($pu) ? (string)($pu['path'] ?? '') : '';
    }
    if ($p === '') return null;
    /* Alles bis einschließlich "/data/" abschneiden (Alt /fotos/data/ UND Neu /data/) */
    $pos = strpos($p, '/data/');
    if ($pos !== false) {
        $rel = substr($p, $pos + 6);
    } elseif (strpos($p, 'data/') === 0) {
        $rel = substr($p, 5);
    } else {
        return null;
    }
    $rel = ltrim($rel, '/');
    if ($rel === '' || strpos($rel, '..') !== false) return null;

    $abs = rtrim($dataDir, '/') . '/' . $rel;
    /* Sicherheit: Ziel muss wirklich innerhalb von /data/ liegen */
    $dirReal  = realpath(dirname($abs));
    $rootReal = realpath($dataDir);
    if ($dirReal === false || $rootReal === false) return null;
    if (strpos($dirReal, $rootReal) !== 0) return null;
    return $abs;
}

/* ---- 1) Login prüfen ---- */
if (!fg2_logged()) {
    http_response_code(401);
    dp_out(false, 'Nicht eingeloggt – bitte Seite neu laden und anmelden.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    dp_out(false, 'Nur POST erlaubt.');
}

/* ---- 2) Eingabe lesen (JSON-Body bevorzugt, sonst Form-POST) ---- */
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;

/* ---- 3) CSRF prüfen ---- */
$csrfToken = '';
if (isset($in['csrf']) && is_string($in['csrf']))          $csrfToken = $in['csrf'];
elseif (isset($_POST['csrf']) && is_string($_POST['csrf'])) $csrfToken = $_POST['csrf'];
elseif (isset($_GET['csrf'])  && is_string($_GET['csrf']))  $csrfToken = $_GET['csrf'];

if (empty($_SESSION['fg2_csrf']) || $csrfToken === '' || !hash_equals($_SESSION['fg2_csrf'], $csrfToken)) {
    http_response_code(400);
    dp_out(false, 'Ungültiges CSRF-Token. Bitte Seite neu laden und erneut anmelden.');
}

/* ---- 4) Parameter validieren ---- */
$eventId  = isset($in['eventId'])  ? trim((string)$in['eventId'])  : '';
$filename = isset($in['filename']) ? trim((string)$in['filename']) : '';

if ($eventId === '')  dp_out(false, 'Kein eventId übergeben.');
if ($filename === '') dp_out(false, 'Kein Dateiname übergeben.');

/* Nur den reinen Dateinamen verwenden (keine Pfad-Traversal!) */
$filename = basename(str_replace('\\', '/', $filename));

/* ---- 5) events.json finden ---- */
$dataDir = realpath(__DIR__ . '/../data');
if ($dataDir === false || !is_dir($dataDir)) {
    dp_out(false, 'Daten-Ordner /fotos/data/ nicht gefunden.');
}
$eventsFile = rtrim($dataDir, '/') . '/events.json';
if (!is_file($eventsFile)) {
    dp_out(false, 'events.json nicht gefunden.');
}

/* ---- 6) Sperren, Eintrag entfernen, zurückschreiben ---- */
$fp = @fopen($eventsFile, 'c+');
if (!$fp) dp_out(false, 'events.json konnte nicht geöffnet werden.');
if (!flock($fp, LOCK_EX)) { fclose($fp); dp_out(false, 'events.json ist gerade gesperrt – bitte erneut versuchen.'); }

$content = stream_get_contents($fp);
$all = json_decode($content, true);
if (!is_array($all)) {
    flock($fp, LOCK_UN); fclose($fp);
    dp_out(false, 'events.json konnte nicht gelesen werden.');
}

$removed = null;
foreach ($all as $ei => $ev) {
    if (!is_array($ev) || ($ev['id'] ?? '') !== $eventId) continue;
    if (empty($ev['photos']) || !is_array($ev['photos'])) break;
    foreach ($ev['photos'] as $pi => $ph) {
        if (!is_array($ph) || empty($ph['src'])) continue;
        $bn = basename(str_replace('\\', '/', (string)$ph['src']));
        if ($bn === $filename) {
            $removed = $ph;
            array_splice($all[$ei]['photos'], $pi, 1);
            break 2;
        }
    }
    break;
}
if ($removed === null) {
    flock($fp, LOCK_UN); fclose($fp);
    dp_out(false, 'Foto nicht gefunden (evtl. schon gelöscht).');
}

$jsonOut = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $eventsFile . '.tmp.' . getmypid();
if ($jsonOut === false || @file_put_contents($tmp, $jsonOut) === false) {
    flock($fp, LOCK_UN); fclose($fp); @unlink($tmp);
    dp_out(false, 'events.json konnte nicht geschrieben werden.');
}
@chmod($tmp, 0644);
if (!@rename($tmp, $eventsFile)) {
    flock($fp, LOCK_UN); fclose($fp); @unlink($tmp);
    dp_out(false, 'events.json konnte nicht gespeichert werden.');
}
flock($fp, LOCK_UN);
fclose($fp);

/* ---- 7) Dateien löschen: Original + Thumbnail + Display ----
 * Jede Auflösung kann als .webp oder .jpg vorliegen → beide probieren. */
$srcPublic = isset($removed['src']) ? (string)$removed['src'] : '';

$candidates = [];
if ($srcPublic !== '') $candidates[] = $srcPublic;
foreach (['thumbnail' => '._rvthumb', 'display' => '._rvdisplay'] as $field => $suffix) {
    if (!empty($removed[$field]) && is_string($removed[$field])) {
        $candidates[] = $removed[$field];
    } elseif ($srcPublic !== '') {
        /* Feld leer → trotzdem beide Varianten versuchen (Datei kann existieren) */
        $candidates[] = $srcPublic . $suffix . '.webp';
        $candidates[] = $srcPublic . $suffix . '.jpg';
    }
}

$deleted = [];
$failed  = [];
foreach (array_unique($candidates) as $pub) {
    $abs = dp_abs_from_public($pub, $dataDir);
    if ($abs === null) continue;
    if (!file_exists($abs)) continue;
    if (@unlink($abs)) $deleted[] = basename($abs);
    else               $failed[]  = basename($abs);
}

$msg = 'Foto gelöscht.';
if ($failed) $msg = 'Eintrag entfernt, aber ' . count($failed) . ' Datei(en) konnten nicht gelöscht werden (Rechte?).';

dp_out(true, $msg, ['deleted' => $deleted, 'failed' => $failed]);
