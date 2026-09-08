<?php
/**
 * SBS Galerie – Admin Upload Endpoint
 * NUR ERREICHBAR WENN ADMIN PASSWORT RICHTIG!
 */

/* ==== PASSWORT KONFIGURATION (KANN AUCH IN EIGENE config.php AUSGELAGERT WERDEN!) ==== */
/* WICHTIG: Ändere das Passwort sofort! */
$ADMIN_PASSWORD = 'rvhard-sbs-passwort-2026!'; /* ← HIER DEIN ADMIN-PASSWORT EINTRAGEN */

/* ==== SICHERHEITSTHEADER ==== */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/* ==== AKTION ERKENNEN ==== */
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ==== 1) PASSWORT PRÜFEN / LOGIN ==== */
if ($action === 'login') {
    $pw = $_POST['password'] ?? '';
    if (hash_equals($ADMIN_PASSWORD, $pw)) {
        session_start();
        $_SESSION['sbs_admin_auth'] = true;
        $_SESSION['sbs_admin_time'] = time();
        session_write_close();
        echo json_encode(['ok' => true, 'msg' => 'Login erfolgreich!']);
    } else {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Falsches Passwort!']);
    }
    exit;
}

/* ==== 2) AUTH PRÜFEN FÜR ALLE FOLGENDEN AKTIONEN ==== */
session_start();
if (empty($_SESSION['sbs_admin_auth']) || (time() - ($_SESSION['sbs_admin_time'] ?? 0) > 3600 * 6)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Nicht eingeloggt. Bitte neu anmelden!']);
    exit;
}
session_write_close();

/* ==== BASIS-PFADE ==== */
define('BASE_DIR', realpath(__DIR__ . '/..')); /* /fotos/ (über admin/) */
define('DATA_DIR', BASE_DIR . '/data');

/* ==== 3) EVENTS BZW. EVENTS.JSON AUSGEBEN ==== */
if ($action === 'get_events') {
    $events = [];
    $jsonPath = DATA_DIR . '/events.json';
    if (file_exists($jsonPath)) {
        $j = json_decode(file_get_contents($jsonPath), true);
        if (is_array($j)) $events = array_values(array_filter($j, function($e) { return empty($e['_schemaVersion']); }));
    }
    echo json_encode(['ok' => true, 'events' => $events]);
    exit;
}

/* ==== 4) EINZELNES BILD HOCHLADEN ==== */
if ($action === 'upload_image') {
    $eventId = $_POST['eventId'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $eventId)) {
        http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Ungültige Event-ID!']); exit;
    }
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Datei-Upload fehlgeschlagen.']); exit;
    }
    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
        http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Nur Bild-Dateien erlaubt!']); exit;
    }
    $targetDir = DATA_DIR . '/' . $eventId;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $fn = preg_replace('/[^a-z0-9_-]/', '-', strtolower($_POST['filename'] ?? $file['name']));
    if (empty($fn) || strlen($fn) < 3) $fn = 'img-' . date('His') . '.' . $ext;
    if (!str_ends_with($fn, '.' . $ext)) $fn .= '.' . $ext;
    $target = $targetDir . '/' . $fn;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        http_response_code(500); echo json_encode(['ok' => false, 'error' => 'Speichern auf Server fehlgeschlagen.']); exit;
    }
    chmod($target, 0644);
    echo json_encode(['ok' => true, 'url' => '/fotos/data/' . $eventId . '/' . $fn]);
    exit;
}

/* ==== 5) EVENTS (NEU) SPEICHERN ==== */
if ($action === 'save_events') {
    $input = json_decode(file_get_contents('php://input'), true);
    $events = $input['events'] ?? [];
    if (!is_array($events)) {
        http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Ungültige Events!']); exit;
    }
    /* Meta (Schemaversion) wieder vorne anhängen */
    $meta = [
        '_schemaVersion' => '3.0',
        '_readme' => '=== SBS GALERIE - AUTOMATISCH GESPEICHERT VOM ADMIN TOOL ==='
    ];
    $output = [$meta, ...$events];
    $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents(DATA_DIR . '/events.json', $json);
    echo json_encode(['ok' => true, 'msg' => 'Events gespeichert!']);
    exit;
}

/* ==== FALLBACK: UNBEKANNTE AKTION ==== */
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion: ' . htmlspecialchars($action)]);
