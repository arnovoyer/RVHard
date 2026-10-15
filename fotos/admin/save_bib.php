<?php
/* ================================================================
 *  RV HARD – STARTNUMMER SPEICHERN (AJAX, OHNE Datenbank!)
 * ================================================================
 *  Aktualisiert NUR die bibNumbers EINES Fotos in data/events.json.
 *  Kein Upload, kein MySQL – die bestehende events.json bleibt die
 *  einzige Datenquelle (identisch zum bisherigen Publish-Flow).
 *
 *  Aufruf (POST, JSON-Body):
 *    { "eventId":"sbs-2026-kriterium-kammgarn-hard",
 *      "filename":"foto_042.jpg",
 *      "bibNumbers":["42","17"],
 *      "csrf":"...." }
 *
 *  Antwort (JSON): { ok: true|false, message: "...", bibNumbers: [...] }
 * ================================================================ */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once(__DIR__ . '/auth2.php');

/* JSON-Antwort + Ende */
function sb_out($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok' => (bool)$ok, 'message' => $msg], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---- 1) Login prüfen ---- */
if (!fg2_logged()) {
    http_response_code(401);
    sb_out(false, 'Nicht eingeloggt – bitte Seite neu laden und anmelden.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sb_out(false, 'Nur POST erlaubt.');
}

/* ---- 2) Eingabe lesen (JSON-Body bevorzugt, sonst Form-POST) ---- */
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;

/* ---- 3) CSRF prüfen (Token kann im JSON-Body ODER als POST/GET kommen) ---- */
$csrfOk = false;
$csrfToken = '';
if (isset($in['csrf']) && is_string($in['csrf']))          $csrfToken = $in['csrf'];
elseif (isset($_POST['csrf']) && is_string($_POST['csrf'])) $csrfToken = $_POST['csrf'];
elseif (isset($_GET['csrf'])  && is_string($_GET['csrf']))  $csrfToken = $_GET['csrf'];

if (!empty($_SESSION['fg2_csrf']) && $csrfToken !== '' && hash_equals($_SESSION['fg2_csrf'], $csrfToken)) {
    $csrfOk = true;
}
if (!$csrfOk) {
    http_response_code(400);
    sb_out(false, 'Ungültiges CSRF-Token. Bitte Seite neu laden und erneut anmelden.');
}

/* ---- 4) Parameter validieren ---- */
$eventId  = isset($in['eventId'])  ? trim((string)$in['eventId'])  : '';
$filename = isset($in['filename']) ? trim((string)$in['filename']) : '';
$bibsRaw  = $in['bibNumbers'] ?? [];

if ($eventId === '')  sb_out(false, 'Kein eventId übergeben.');
if ($filename === '') sb_out(false, 'Kein Dateiname übergeben.');

/* Nur den reinen Dateinamen verwenden (keine Pfad-Traversal!) */
$filename = basename(str_replace('\\', '/', $filename));

/* Startnummern normalisieren (Array ODER Komma-String → saubere Liste) */
if (is_string($bibsRaw)) {
    $bibs = preg_split('/[\s,\/;]+/', $bibsRaw);
} elseif (is_array($bibsRaw)) {
    $bibs = $bibsRaw;
} else {
    $bibs = [];
}
$bibs = array_values(array_unique(array_filter(array_map(function ($b) {
    $b = trim(str_replace('#', '', (string)$b));
    return $b === '' ? null : $b;
}, $bibs), function ($v) { return $v !== null; })));

/* ---- 5) events.json Pfad ----
 * Die Datei liegt IMMER eine Ebene über dem admin-Ordner:  <...>/data/events.json
 * (funktioniert auf Subdomain UND Unterordner-Installation!) */
$dataDir = realpath(__DIR__ . '/../data');
if ($dataDir === false || !is_dir($dataDir)) {
    sb_out(false, 'Daten-Ordner /fotos/data/ nicht gefunden.');
}
$eventsFile = $dataDir . '/events.json';
if (!is_file($eventsFile)) {
    sb_out(false, 'events.json nicht gefunden.');
}

/* ---- 6) Sperre setzen (verhindert Überschreiben bei schnellem Tippen) ---- */
$lock = @fopen($eventsFile . '.lock', 'c');
if ($lock) { @flock($lock, LOCK_EX); }

$all = json_decode((string)@file_get_contents($eventsFile), true);
if (!is_array($all)) {
    if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
    sb_out(false, 'events.json ist beschädigt (kein gültiges JSON).');
}

/* ---- 7) Event + Foto suchen (Foto-Match über basename des src-Pfads!) ---- */
$foundEvent = false;
$foundPhoto = false;
$previous   = [];

foreach ($all as &$ev) {
    if (!is_array($ev) || !isset($ev['id'])) continue;      /* _schemaVersion-Eintrag überspringen */
    if ((string)$ev['id'] !== $eventId) continue;

    $foundEvent = true;
    if (!empty($ev['photos']) && is_array($ev['photos'])) {
        foreach ($ev['photos'] as &$ph) {
            if (!isset($ph['src'])) continue;
            $srcName = basename(str_replace('\\', '/', (string)$ph['src']));
            if ($srcName !== $filename) continue;

            $previous = isset($ph['bibNumbers']) && is_array($ph['bibNumbers']) ? $ph['bibNumbers'] : [];
            $ph['bibNumbers'] = $bibs;
            $foundPhoto = true;
            break;
        }
        unset($ph);
    }
    break;
}
unset($ev);

if (!$foundEvent) {
    if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
    sb_out(false, 'Event nicht gefunden: ' . $eventId);
}
if (!$foundPhoto) {
    if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
    sb_out(false, 'Foto nicht gefunden (noch nicht veröffentlicht?): ' . $filename);
}

/* ---- 8) Sicher schreiben (tmp + rename, wie im Publish-Flow) ---- */
$flags   = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
$jsonStr = json_encode($all, $flags);
if ($jsonStr === false || !is_array(json_decode($jsonStr, true))) {
    if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
    sb_out(false, 'JSON konnte nicht erzeugt werden.');
}

$tmp = $eventsFile . '.tmp.' . uniqid('', true);
if (@file_put_contents($tmp, $jsonStr, LOCK_EX) === false) {
    @unlink($tmp);
    if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
    sb_out(false, 'Keine Schreibrechte auf /fotos/data/ (755?)');
}
if (!@rename($tmp, $eventsFile)) {
    @unlink($tmp);
    if (@file_put_contents($eventsFile, $jsonStr, LOCK_EX) === false) {
        if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
        sb_out(false, 'events.json konnte nicht gespeichert werden.');
    }
}
@chmod($eventsFile, 0644);

if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }

/* ---- 9) Erfolg ---- */
sb_out(true, 'Startnummer gespeichert.', [
    'filename'   => $filename,
    'bibNumbers' => $bibs,
    'previous'   => $previous,
]);
