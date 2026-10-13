<?php
/* ============================================================
 *  RV HARD – BILDER & VIDEOS HOCHLADEN (ÖFFENTLICH)
 *  Endpunkt für die /bilder Upload-Seite
 *  Speichert Dateien gruppiert nach Veranstaltung in
 *  fotos/data/uploads/<event-slug>/
 *  Schickt Benachrichtigung per ntfy.sh + E-Mail
 * ============================================================ */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/* ---------- KONFIGURATION AUS config.php ---------- */
$CFG = file_exists(__DIR__ . '/config.php') ? include(__DIR__ . '/config.php') : [];
$UPLOAD_ROOT = realpath(__DIR__ . '/../fotos/data/uploads') . '/';
$INDEX_FILE  = $UPLOAD_ROOT . 'index.json';
$MAX_FILES   = $CFG['max_files']  ?? 20;
$MAX_IMG_SIZE = ($CFG['max_img_mb'] ?? 15) * 1024 * 1024;
$MAX_VID_SIZE = ($CFG['max_vid_mb'] ?? 50) * 1024 * 1024;

$ALLOWED_IMG = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
$ALLOWED_VID = ['video/mp4','video/quicktime','video/webm','video/x-matroska'];
$ALLOWED_EXT = ['jpg','jpeg','png','webp','gif','mp4','mov','webm','mkv'];

/* Benachrichtigungen */
$NOTIFY = [
    'ntfy_topic' => $CFG['ntfy_topic']  ?? '',
    'ntfy_server'=> $CFG['ntfy_server'] ?? 'https://ntfy.sh',
    'email_to'   => $CFG['email_to']    ?? '',
    'email_from' => $CFG['email_from']  ?? 'noreply@rv-hard.at',
];

/* ---------- HILFSFUNKTIONEN ---------- */
function die_json($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function slugify($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower(trim($text)));
    return trim($text, '-');
}

function safe_filename($name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return substr(md5(uniqid(rand(), true)), 0, 12) . '.' . $ext;
}

/* ---------- UPLOAD-ORDNER ANLEGEN ---------- */
if (!is_dir($UPLOAD_ROOT)) {
    @mkdir($UPLOAD_ROOT, 0755, true);
    /* Schütze den Ordner vor öffentlichem Zugriff */
    @file_put_contents($UPLOAD_ROOT . '.htaccess', "Require all denied\nDeny from all\n");
}
if (!is_dir($UPLOAD_ROOT)) die_json(false, 'Upload-Ordner konnte nicht erstellt werden.');

/* ---------- INPUT PRÜFEN ---------- */
$eventName = trim($_POST['event'] ?? '');
if ($eventName === '') die_json(false, 'Bitte gib den Namen der Veranstaltung an.');
if (mb_strlen($eventName) > 80) $eventName = mb_substr($eventName, 0, 80);

$eventSlug = slugify($eventName);
if ($eventSlug === '') $eventSlug = 'veranstaltung-' . date('Ymd-His');

if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'])) {
    die_json(false, 'Keine Dateien empfangen.');
}

$names = $_FILES['files']['name'];
$tmps  = $_FILES['files']['tmp_name'];
$errs  = $_FILES['files']['error'];
$sizes = $_FILES['files']['size'];
$types = $_FILES['files']['type'];

$total = count($names);
if ($total > $MAX_FILES) die_json(false, "Maximal $MAX_FILES Dateien pro Upload erlaubt.");
if ($total === 0) die_json(false, 'Keine Dateien ausgewählt.');

/* ---------- DATEIEN SPEICHERN ---------- */
$eventDir = $UPLOAD_ROOT . $eventSlug . '/';
if (!is_dir($eventDir)) @mkdir($eventDir, 0755, true);

$saved = [];
$uploadId = date('Ymd-His') . '-' . substr(md5(uniqid()), 0, 6);

for ($i = 0; $i < $total; $i++) {
    if ($errs[$i] !== UPLOAD_ERR_OK) {
        if ($errs[$i] === UPLOAD_ERR_NO_FILE) continue;
        die_json(false, 'Upload-Fehler bei ' . htmlspecialchars($names[$i]) . ' (Code ' . $errs[$i] . ')');
    }

    $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXT, true)) {
        die_json(false, 'Dateityp nicht erlaubt: ' . htmlspecialchars($names[$i]));
    }

    $isVideo = in_array($types[$i], $ALLOWED_VID, true) ||
               in_array($ext, ['mp4','mov','webm','mkv'], true);
    $maxSize = $isVideo ? $MAX_VID_SIZE : $MAX_IMG_SIZE;
    if ($sizes[$i] > $maxSize) {
        $mb = round($maxSize / 1024 / 1024);
        die_json(false, 'Datei zu groß (' . htmlspecialchars($names[$i]) . ', max. ' . $mb . ' MB)');
    }

    /* MIME-Check per finfo */
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmps[$i]);
    finfo_close($finfo);
    $allowedMimes = array_merge($ALLOWED_IMG, $ALLOWED_VID);
    if (!in_array($mime, $allowedMimes, true)) {
        die_json(false, 'Kein gültiges Bild/Video: ' . htmlspecialchars($names[$i]));
    }

    $safeName = safe_filename($names[$i]);
    $target = $eventDir . $safeName;
    if (!@move_uploaded_file($tmps[$i], $target)) {
        die_json(false, 'Speichern fehlgeschlagen: ' . htmlspecialchars($names[$i]));
    }

    $saved[] = [
        'file' => $safeName,
        'original' => $names[$i],
        'type' => $isVideo ? 'video' : 'image',
        'mime' => $mime,
        'size' => $sizes[$i],
    ];
}

if (count($saved) === 0) die_json(false, 'Keine Dateien konnten gespeichert werden.');

/* ---------- INDEX.JSON UPDATEN ---------- */
$index = [];
if (file_exists($INDEX_FILE) && filesize($INDEX_FILE) > 0) {
    $decoded = json_decode(file_get_contents($INDEX_FILE), true);
    if (is_array($decoded)) $index = $decoded;
}

$entry = [
    'id' => $uploadId,
    'event' => $eventName,
    'eventSlug' => $eventSlug,
    'datetime' => date('c'),
    'count' => count($saved),
    'files' => $saved,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
];
array_unshift($index, $entry);
@file_put_contents($INDEX_FILE, json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

/* ---------- BENACHRICHTIGUNG ---------- */
$msgText = "📸 Neuer Upload!\n" .
    "Veranstaltung: $eventName\n" .
    "Anzahl: " . count($saved) . " Datei(en)\n" .
    "Zeit: " . date('d.m.Y H:i') . "\n" .
    "Ordner: $eventSlug";

/* ntfy.sh Push */
if (!empty($NOTIFY['ntfy_topic'])) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/plain\r\n" .
                        "Title: RV Hard – neuer Upload\r\n" .
                        "Priority: default\r\n" .
                        "Tags: camera,envelope\r\n",
            'content' => $msgText,
            'timeout' => 8,
            'ignore_errors' => true,
        ]
    ]);
    @file_get_contents($NOTIFY['ntfy_server'] . '/' . $NOTIFY['ntfy_topic'], false, $ctx);
}

/* E-Mail */
if (!empty($NOTIFY['email_to'])) {
    $headers = "From: " . $NOTIFY['email_from'] . "\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($NOTIFY['email_to'], 'RV Hard – neuer Upload (' . $eventName . ')', $msgText, $headers);
}

/* ---------- ERFOLG ---------- */
die_json(true, 'Danke! Dateien erfolgreich gesendet.', [
    'count' => count($saved),
    'event' => $eventName,
]);
