<?php
/* ================================================================
 *  RV HARD – EVENT VERWALTEN (AJAX, OHNE Datenbank!)
 * ================================================================
 *  Legt ein neues (eigenes) Event in data/events.json an und
 *  blendet Events ein/aus (hidden-Flag für die öffentliche Galerie).
 *
 *  Aufruf (POST, JSON-Body):
 *    { "action":"create", "csrf":"…", "year":"2026", "name":"…",
 *      "date":"2026-09-06", "location":"…", "distance":"…",
 *      "description":"…", "subtype":"" }
 *    { "action":"set_hidden", "csrf":"…", "eventId":"…", "hidden":1 }
 *
 *  Antwort (JSON): { ok: true|false, message: "…", event: {…} }
 * ================================================================ */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once(__DIR__ . '/auth2.php');

/* JSON-Antwort + Ende */
function ea_out($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok' => (bool)$ok, 'message' => $msg], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* Umlaute → ASCII, alles andere → Bindestrich (für ID/Slug) */
function ea_slug($s) {
    $s = trim((string)$s);
    $s = strtr($s, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue','ß'=>'ss']);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    if ($s === '') $s = 'event';
    return substr($s, 0, 60);
}

/* ---- 1) Login prüfen ---- */
if (!fg2_logged()) {
    http_response_code(401);
    ea_out(false, 'Nicht eingeloggt – bitte Seite neu laden und anmelden.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ea_out(false, 'Nur POST erlaubt.');
}

/* ---- 2) Eingabe lesen ---- */
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
    ea_out(false, 'Ungültiges CSRF-Token. Bitte Seite neu laden und erneut anmelden.');
}

$action = isset($in['action']) ? trim((string)$in['action']) : '';

/* ---- 4) events.json finden ---- */
$dataDir = realpath(__DIR__ . '/../data');
if ($dataDir === false || !is_dir($dataDir)) {
    ea_out(false, 'Daten-Ordner /fotos/data/ nicht gefunden.');
}
$eventsFile = rtrim($dataDir, '/') . '/events.json';
if (!is_file($eventsFile)) {
    ea_out(false, 'events.json nicht gefunden.');
}

/* ---- 5) Sperren, lesen, ändern, atomar zurückschreiben ---- */
$fp = @fopen($eventsFile, 'c+');
if (!$fp) ea_out(false, 'events.json konnte nicht geöffnet werden.');
if (!flock($fp, LOCK_EX)) { fclose($fp); ea_out(false, 'events.json ist gerade gesperrt – bitte erneut versuchen.'); }

$content = stream_get_contents($fp);
$all = json_decode($content, true);
if (!is_array($all)) {
    flock($fp, LOCK_UN); fclose($fp);
    ea_out(false, 'events.json konnte nicht gelesen werden.');
}

/* Helper: atomar speichern */
$ea_save = function($data) use ($fp, $eventsFile) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { flock($fp, LOCK_UN); fclose($fp); ea_out(false, 'events.json konnte nicht erzeugt werden.'); }
    $tmp = $eventsFile . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json) === false) { flock($fp, LOCK_UN); fclose($fp); @unlink($tmp); ea_out(false, 'events.json konnte nicht geschrieben werden.'); }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $eventsFile)) { flock($fp, LOCK_UN); fclose($fp); @unlink($tmp); ea_out(false, 'events.json konnte nicht gespeichert werden.'); }
    flock($fp, LOCK_UN); fclose($fp);
};

/* ================= CREATE ================= */
if ($action === 'create') {
    $name = isset($in['name']) ? trim((string)$in['name']) : '';
    if (mb_strlen($name) < 2) ea_out(false, 'Bitte einen Event-Namen angeben (mind. 2 Zeichen).');

    $year = isset($in['year']) ? trim((string)$in['year']) : '';
    if (!preg_match('/^\d{4}$/', $year)) $year = date('Y');
    $yi = (int)$year;
    if ($yi < 2000 || $yi > 2100) $year = date('Y');

    $date = isset($in['date']) ? trim((string)$in['date']) : '';
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';

    $location    = isset($in['location'])    ? mb_substr(trim((string)$in['location']), 0, 120)    : '';
    $distance    = isset($in['distance'])    ? mb_substr(trim((string)$in['distance']), 0, 80)     : '';
    $description = isset($in['description']) ? mb_substr(trim((string)$in['description']), 0, 300) : '';

    $allowedSubtypes = ['', 'bergrennen', 'ezf', 'kriterium'];
    $subtype = isset($in['subtype']) ? trim((string)$in['subtype']) : '';
    if (!in_array($subtype, $allowedSubtypes, true)) $subtype = '';

    /* Eindeutige ID/Slug bilden – direkt aus dem Event-Namen, ohne technisches Präfix! */
    $takenIds = [];
    foreach ($all as $e) { if (is_array($e) && !empty($e['id'])) $takenIds[(string)$e['id']] = true; }
    $baseId = ea_slug($name);
    $id = $baseId;
    $n = 2;
    while (isset($takenIds[$id])) { $id = $baseId . '-' . $n; $n++; }

    $event = [
        'id'          => $id,
        'parentId'    => 'events-' . $year,
        'slug'        => $id,
        'name'        => $name,
        'shortName'   => $name,
        'description' => $description,
        'category'    => 'EVENT',
        'subtype'     => $subtype,
        'date'        => $date,
        'location'    => $location,
        'organizer'   => 'RV Hard',
        'distance'    => $distance,
        'folder'      => $id,
        'coverPhoto'  => null,
        'tags'        => [(string)$year, $name],
        'custom'      => true,
        'hidden'      => false,
        'photos'      => []
    ];

    /* Nach dem Schema-Block einfügen, Reihenfolge der anderen Events erhalten */
    $out = [];
    $inserted = false;
    foreach ($all as $e) {
        if (is_array($e) && isset($e['_schemaVersion'])) { $e['_lastModified'] = date('c'); $out[] = $e; continue; }
        if (!$inserted) { $out[] = $event; $inserted = true; }
        $out[] = $e;
    }
    if (!$inserted) $out[] = $event;

    $ea_save($out);
    ea_out(true, 'Event "' . $name . '" angelegt.', ['event' => $event]);
}

/* ================= SET_HIDDEN ================= */
if ($action === 'set_hidden') {
    $eventId = isset($in['eventId']) ? trim((string)$in['eventId']) : '';
    if ($eventId === '') ea_out(false, 'Kein eventId übergeben.');
    $hidden = !empty($in['hidden']);

    $found = false;
    foreach ($all as $i => $e) {
        if (!is_array($e)) continue;
        if (isset($e['_schemaVersion'])) continue;
        if ((string)($e['id'] ?? '') === $eventId) {
            $all[$i]['hidden'] = $hidden;
            $found = true;
            break;
        }
    }
    if (!$found) { flock($fp, LOCK_UN); fclose($fp); ea_out(false, 'Event nicht gefunden.'); }

    /* _lastModified aktualisieren */
    foreach ($all as $i => $e) {
        if (is_array($e) && isset($e['_schemaVersion'])) { $all[$i]['_lastModified'] = date('c'); break; }
    }

    $ea_save($all);
    ea_out(true, $hidden ? 'Event ausgeblendet.' : 'Event eingeblendet.', ['hidden' => $hidden]);
}

flock($fp, LOCK_UN); fclose($fp);
ea_out(false, 'Unbekannte Aktion.');
