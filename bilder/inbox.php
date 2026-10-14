<?php
/* ============================================================
 *  RV HARD – BILDER-UPLOAD ADMIN INBOX
 *  Nur für eingeloggte Admins!
 *  Actions:
 *    ?action=list                 → JSON Liste aller Uploads
 *    ?action=preview&id=..&file=.. → Bild/Video servieren (Preview)
 *    ?action=download&id=..        → ZIP Download eines Uploads
 *    ?action=delete&id=..  (POST)  → Upload löschen
 * ============================================================ */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/* ZUERST CONFIG LADEN für auth_path */
$configFile = __DIR__ . '/config.php';
$CFG = file_exists($configFile) ? (include $configFile) : [];
if (!is_array($CFG)) $CFG = [];

/* ADMIN AUTH (gleiche Session wie fotos/admin) – mit Fallback-Pfaden */
$authCandidates = [];
if (!empty($CFG['auth_path']) && file_exists($CFG['auth_path'])) {
    $authCandidates[] = $CFG['auth_path'];
}
$authCandidates = array_merge($authCandidates, [
    __DIR__ . '/../fotos/admin/auth2.php',
    __DIR__ . '/../../fotos/admin/auth2.php',
    '/www/htdocs/w007ee49/fotos.rv-hard.at/admin/auth2.php',
    '/www/htdocs/w007ee49/rv-hard.at/fotos/admin/auth2.php',
    '/www/htdocs/w007ee49/neu.rv-hard.at/fotos/admin/auth2.php',
]);
$authPath = null;
foreach ($authCandidates as $c) { if (file_exists($c)) { $authPath = $c; break; } }
if (!$authPath) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'auth2.php nicht gefunden. Bitte Pfad in bilder/config.php eintragen.']);
    exit;
}
require_once($authPath);
if (!function_exists('fg2_logged') || !fg2_logged()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Nicht eingeloggt.']);
    exit;
}

/* UPLOAD_ROOT – Uploads liegen im bilder/data/uploads/ Ordner (selbe Subdomain) */
$UPLOAD_ROOT = __DIR__ . '/data/uploads/';
if (!is_dir($UPLOAD_ROOT)) { @mkdir($UPLOAD_ROOT, 0755, true); }
$UPLOAD_ROOT = realpath($UPLOAD_ROOT) . '/';
$INDEX_FILE  = $UPLOAD_ROOT . 'index.json';

function load_index() {
    global $INDEX_FILE;
    if (!file_exists($INDEX_FILE) || filesize($INDEX_FILE) === 0) return [];
    $decoded = json_decode(file_get_contents($INDEX_FILE), true);
    return is_array($decoded) ? $decoded : [];
}
function save_index($idx) {
    global $INDEX_FILE;
    @file_put_contents($INDEX_FILE, json_encode($idx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function find_entry($id, $idx) {
    foreach ($idx as $i => $e) { if (($e['id'] ?? '') === $id) return $i; }
    return null;
}

$action = $_GET['action'] ?? 'list';

/* ---------- LISTE ---------- */
if ($action === 'list') {
    header('Content-Type: application/json; charset=utf-8');
    $idx = load_index();
    /* Nur Metadaten, keine Dateiinhalte */
    $out = [];
    foreach ($idx as $e) {
        $out[] = [
            'id' => $e['id'],
            'event' => $e['event'],
            'eventSlug' => $e['eventSlug'],
            'datetime' => $e['datetime'],
            'count' => $e['count'],
            'files' => array_map(function($f) {
                return ['file' => $f['file'], 'original' => $f['original'], 'type' => $f['type']];
            }, $e['files'] ?? []),
        ];
    }
    echo json_encode(['ok' => true, 'uploads' => $out]);
    exit;
}

/* ---------- PREVIEW (Datei servieren) ---------- */
if ($action === 'preview') {
    $id = $_GET['id'] ?? '';
    $file = basename($_GET['file'] ?? '');
    $idx = load_index();
    $i = find_entry($id, $idx);
    if ($i === null) { http_response_code(404); exit('Not found'); }
    $entry = $idx[$i];
    $found = null;
    foreach (($entry['files'] ?? []) as $f) {
        if ($f['file'] === $file) { $found = $f; break; }
    }
    if (!$found) { http_response_code(404); exit('Not found'); }
    $path = $UPLOAD_ROOT . $entry['eventSlug'] . '/' . $file;
    if (!file_exists($path)) { http_response_code(404); exit('Not found'); }
    $mime = $found['mime'] ?? mime_content_type($path);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=3600');
    readfile($path);
    exit;
}

/* ---------- ZIP DOWNLOAD ---------- */
if ($action === 'download') {
    $id = $_GET['id'] ?? '';
    $idx = load_index();
    $i = find_entry($id, $idx);
    if ($i === null) { http_response_code(404); exit('Upload nicht gefunden.'); }
    $entry = $idx[$i];
    $eventDir = $UPLOAD_ROOT . $entry['eventSlug'] . '/';

    /* Fallback: falls ZipArchive nicht verfügbar, einzelne Datei ausliefern */
    if (!class_exists('ZipArchive')) {
        /* Einfach erste Datei ausliefern (besser als 500) */
        $first = $entry['files'][0] ?? null;
        if ($first) {
            $fp = $eventDir . $first['file'];
            if (file_exists($fp)) {
                header('Content-Type: ' . ($first['mime'] ?? 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="' . ($first['original'] ?? $first['file']) . '"');
                readfile($fp);
                exit;
            }
        }
        http_response_code(500);
        exit('ZipArchive auf diesem Server nicht verfügbar.');
    }

    $zipName = $entry['eventSlug'] . '_' . $entry['id'] . '.zip';
    $tmpZip = tempnam(sys_get_temp_dir(), 'rvh_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500); exit('ZIP konnte nicht erstellt werden.');
    }
    foreach (($entry['files'] ?? []) as $f) {
        $fp = $eventDir . $f['file'];
        if (file_exists($fp)) {
            $zip->addFile($fp, $entry['eventSlug'] . '/' . ($f['original'] ?? $f['file']));
        }
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($tmpZip));
    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
}

/* ---------- LÖSCHEN (nur POST) ---------- */
if ($action === 'delete' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $id = $_POST['id'] ?? '';
    $idx = load_index();
    $i = find_entry($id, $idx);
    if ($i === null) {
        echo json_encode(['ok' => false, 'msg' => 'Upload nicht gefunden.']);
        exit;
    }
    $entry = $idx[$i];
    $eventDir = $UPLOAD_ROOT . $entry['eventSlug'] . '/';
    /* Dateien dieses Uploads löschen */
    foreach (($entry['files'] ?? []) as $f) {
        @unlink($eventDir . $f['file']);
    }
    /* Wenn der Ordner leer ist, auch löschen */
    if (is_dir($eventDir) && count(glob($eventDir . '*')) === 0) {
        @rmdir($eventDir);
    }
    /* Eintrag aus index.json entfernen */
    array_splice($idx, $i, 1);
    save_index($idx);
    echo json_encode(['ok' => true, 'msg' => 'Upload gelöscht.']);
    exit;
}

/* ---------- FALLBACK ---------- */
http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'msg' => 'Unbekannte Aktion.']);
