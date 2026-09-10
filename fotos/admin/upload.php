<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/* ============================================================
 *  SBS GALERIE – AUTO UPLOAD ENDPOINT
 *  Speichert Bilder direkt in Ordner + merged events.json
 *  Aufgerufen per AJAX aus /fotos/admin/
 * ============================================================ */

define('DATA_ROOT', realpath(__DIR__ . '/../data') . '/');
define('EVENTS_FILE', DATA_ROOT . 'events.json');
define('MAX_IMG_SIZE', 15 * 1024 * 1024);
define('ALLOWED_EXT', ['jpg','jpeg','png','gif','webp']);
define('ALLOWED_TYPES', [
    'image/jpeg','image/png','image/gif','image/webp'
]);

$ALLOWED_FOLDERS = [
    'sbs-2024-bergrennen-wolfurt-buch','sbs-2024-kriterium-kammgarn-hard','sbs-2024-ezf-rohrspitz-fussach',
    'sbs-2025-bergrennen-wolfurt-buch','sbs-2025-kriterium-kammgarn-hard','sbs-2025-ezf-rohrspitz-fussach',
    'sbs-2026-bergrennen-wolfurt-buch','sbs-2026-kriterium-kammgarn-hard','sbs-2026-ezf-rohrspitz-fussach'
];

/* ---------- HELPER: Sicherer Ordnername (kein Path Traversal) ---------- */
function sanitizeFolder($f) {
    global $ALLOWED_FOLDERS;
    if (!in_array($f, $ALLOWED_FOLDERS, true)) die_json(false, 'Ungültiger Ordner: ' . htmlspecialchars($f));
    return $f;
}
/* ---------- HELPER: Sicherer Dateiname ---------- */
function sanitizeFilename($name, $folder) {
    $name = preg_replace('/[^\w\.\-äöüÄÖÜß]/u', '_', $name);
    if (empty($name)) $name = 'image_' . time() . '.jpg';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) die_json(false, 'Dateityp nicht erlaubt: ' . htmlspecialchars($ext));
    $targetDir = DATA_ROOT . $folder;
    if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
    if (!is_dir($targetDir)) die_json(false, 'Ordner konnte nicht erstellt werden: ' . htmlspecialchars($folder));
    $base = $name;
    $i = 1;
    while (file_exists($targetDir . '/' . $name)) {
        $name = preg_replace('/\.[^\.]+$/', '', $base) . '_' . $i . '.' . $ext;
        $i++;
    }
    return $name;
}
/* ---------- HELPER: JSON Antwort ---------- */
function die_json($ok, $msg, $extra = []) {
    echo json_encode(array_merge([
        'ok' => $ok,
        'msg' => $msg
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* ---------- ACTION ROUTING ---------- */
$action = $_POST['action'] ?? $_GET['action'] ?? 'ping';

/* ============ ACTION: PING / TEST (Server erreichbar?) ============ */
if ($action === 'ping') {
    $writable = is_writable(DATA_ROOT);
    $hasEvents = file_exists(EVENTS_FILE);
    die_json(true, 'Server erreichbar – bereit für Upload!', [
        'data_writable' => $writable,
        'events_exists' => $hasEvents,
        'max_upload' => ini_get('upload_max_filesize')
    ]);
}

/* ============ ACTION: UPLOAD + SAVE ALL ============ */
if ($action === 'publish') {

    /* 1) METADATEN (JSON encoded) */
    $metaRaw = $_POST['metadata'] ?? '';
    $meta = json_decode($metaRaw, true);
    if (!is_array($meta) || !isset($meta['eventId']) || !isset($meta['folder'])) {
        die_json(false, 'Fehlende Metadaten (eventId/folder)');
    }
    $eventId = $meta['eventId'];
    $folder  = sanitizeFolder($meta['folder']);
    $year    = $meta['year'] ?? '2026';
    $discKey = $meta['discKey'] ?? '';
    $photosMeta = is_array($meta['photos'] ?? null) ? $meta['photos'] : [];

    if (count($photosMeta) === 0 && !isset($_FILES['images'])) {
        die_json(false, 'Keine Bilder oder Metadaten gesendet.');
    }

    /* 2) GESENDETE DATEIEN SPEICHERN (zu den Metadaten dazugehörig) */
    $savedImages = []; // $savedImages['origName'] = 'final-src-path';
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $names  = $_FILES['images']['name'];
        $tmp    = $_FILES['images']['tmp_name'];
        $errs   = $_FILES['images']['error'];
        $sizes  = $_FILES['images']['size'];

        for ($i = 0; $i < count($names); $i++) {
            if ($errs[$i] !== UPLOAD_ERR_OK) {
                if ($errs[$i] === UPLOAD_ERR_NO_FILE) continue;
                die_json(false, 'Upload Fehler bei ' . htmlspecialchars($names[$i]) . ' (Code ' . $errs[$i] . ')');
            }
            if ($sizes[$i] > MAX_IMG_SIZE) {
                die_json(false, 'Bild zu groß (>15 MB): ' . htmlspecialchars($names[$i]));
            }
            if (!in_array(mime_content_type($tmp[$i]), ALLOWED_TYPES, true)) {
                die_json(false, 'Kein gültiges Bildformat: ' . htmlspecialchars($names[$i]));
            }
            $safeName = sanitizeFilename($names[$i], $folder);
            $targetPath = DATA_ROOT . $folder . '/' . $safeName;
            if (!@move_uploaded_file($tmp[$i], $targetPath)) {
                die_json(false, 'Konnte Datei nicht speichern (Fehlende Rechte? Ordner 755!): ' . htmlspecialchars($folder . '/' . $safeName));
            }
            $savedImages[$names[$i]] = '/fotos/data/' . $folder . '/' . $safeName;
        }
    }

    /* 3) Alte events.json laden (MIT FALLBACK falls Kommentare drin sind!) */
    $allEvents = [];
    if (file_exists(EVENTS_FILE) && filesize(EVENTS_FILE) > 0) {
        $raw = file_get_contents(EVENTS_FILE);
        $decoded = json_decode($raw, true);
        /* Fallback: Kommentare entfernen falls JSON ungueltig war! */
        if (!is_array($decoded) && $raw) {
            $cleaned = preg_replace('!/\*.*?\*/!s', '', $raw);
            $cleaned = preg_replace('/^\s*\/\/.*$/m', '', $cleaned);
            $cleaned = preg_replace('/,\s*([\]}])/m', '$1', $cleaned);
            $decoded = json_decode(trim($cleaned), true);
        }
        if (is_array($decoded)) {
            $allEvents = array_filter($decoded, function($e) {
                return is_array($e) && !isset($e['_schemaVersion']);
            });
            $allEvents = array_values($allEvents);
        }
    }

    /* 4) Neues Event Objekt bauen */
    $discDefs = [
        'bergrennen-wolfurt-buch' => [
            'short' => 'Bergrennen Wolfurt-Buch',
            'name'  => 'Bergrennen Wolfurt → Buch',
            'location' => 'Wolfurt → Buch (Bregenzerwald)',
            'distance' => '12,8 km · 690 Hm',
            'desc'  => 'Bergrennen Wolfurt nach Buch. Suche nach deiner Startnummer!',
            'subtype' => 'bergrennen',
            'coverDefault' => 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=road%20cycling%20uphill%20race%20austrian%20alps%20forest%20road&image_size=landscape_16_9'
        ],
        'kriterium-kammgarn-hard' => [
            'short' => 'Kriterium Kammgarn Hard',
            'name'  => 'Kriterium Kammgarn Hard',
            'location' => 'Kammgarn Areal, Hard',
            'distance' => '50 Runden · 75 km',
            'desc'  => 'Stadtkurs Kriterium in Hard am Kammgarn Gelände. Suche nach deiner Startnummer!',
            'subtype' => 'kriterium',
            'coverDefault' => 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=road%20cycling%20criterium%20city%20race%20blurred%20motion&image_size=landscape_16_9'
        ],
        'ezf-rohrspitz-fussach' => [
            'short' => 'EZF Rohrspitz Fußach',
            'name'  => 'Einzelzeitfahren Rohrspitz, Fußach',
            'location' => 'Rohrspitz, Fußach (Bodensee)',
            'distance' => '9,2 km · 45 Hm',
            'desc'  => 'Einzelzeitfahren (EZF) entlang des Bodensees in Fußach am Rohrspitz. Suche nach deiner Startnummer!',
            'subtype' => 'ezf',
            'coverDefault' => 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=time%20trial%20cyclist%20lake%20shore%20bodensee%20sunset%20aerodynamic&image_size=landscape_16_9'
        ]
    ];
    $disc = $discDefs[$discKey] ?? $discDefs['bergrennen-wolfurt-buch'];

    /* 5) Fotos für JSON aufbauen */
    $photosJson = [];
    foreach ($photosMeta as $pm) {
        $origName = $pm['originalName'] ?? '';
        $src = null;
        if (!empty($pm['src']) && strpos($pm['src'], '/fotos/data/') === 0) {
            $src = $pm['src'];
        } elseif (isset($savedImages[$origName])) {
            $src = $savedImages[$origName];
        }
        if (!$src) continue;

        $bibNumbers = is_array($pm['bibNumbers'] ?? null) ? $pm['bibNumbers'] : [];
        $athletes   = is_array($pm['athletes']   ?? null) ? $pm['athletes']   : [];
        $photosJson[] = [
            'src'         => $src,
            'thumbnail'   => $src,
            'title'       => $pm['title'] ?? basename($src),
            'date'        => $pm['date'] ?? $year . '-09-06',
            'photographer'=> $pm['photographer'] ?? 'RV Hard',
            'copyright'   => '© RV Hard ' . $year,
            'bibNumbers'  => array_values($bibNumbers),
            'athletes'    => array_values($athletes),
            'tags'        => ['SBS', (string)$year, $disc['short']]
        ];
    }

    $thisEvent = [
        'id'          => $eventId,
        'parentId'    => 'sbs-' . $year,
        'slug'        => $eventId,
        'name'        => 'SBS ' . $year . ' – ' . $disc['name'],
        'shortName'   => $disc['short'],
        'description' => $disc['desc'],
        'category'    => 'SBS',
        'subtype'     => $disc['subtype'],
        'date'        => $year . '-09-06',
        'location'    => $disc['location'],
        'organizer'   => 'RV Hard',
        'distance'    => $disc['distance'],
        'folder'      => $folder,
        'coverPhoto'  => $photosJson[0]['src'] ?? $disc['coverDefault'],
        'tags'        => ['SBS', (string)$year, $disc['short'], explode(',', $disc['location'])[0]],
        'photos'      => $photosJson
    ];

    /* 6) Merge: altes Event entfernen + neues + sortieren */
    $others = array_values(array_filter($allEvents, function($e) use ($eventId) {
        return ($e['id'] ?? '') !== $eventId;
    }));
    $merged = array_merge([$thisEvent], $others);
    usort($merged, function($a, $b) {
        $ay = str_replace('sbs-', '', (string)($a['parentId'] ?? ''));
        $by = str_replace('sbs-', '', (string)($b['parentId'] ?? ''));
        return strnatcmp($by, $ay);
    });

    $metaBlock = [[
        '_schemaVersion'  => '3.2',
        '_readme'         => 'SBS Galerie V3.2 - auto-publish via admin/upload.php',
        '_lastModified'   => date('c'),
        '_lastFolder'     => $folder
    ]];
    $output = array_merge($metaBlock, $merged);

    /* 7) events.json SPEICHERN */
    $jsonStr = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $tmpFile = EVENTS_FILE . '.tmp.' . uniqid();
    if (@file_put_contents($tmpFile, $jsonStr) === false) {
        @unlink($tmpFile);
        die_json(false, 'Konnte events.json temporär nicht schreiben – Ordner-Rechte prüfen (/fotos/data/ = 755, Datei = 644).');
    }
    if (!@rename($tmpFile, EVENTS_FILE)) {
        @unlink($tmpFile);
        if (!@file_put_contents(EVENTS_FILE, $jsonStr)) {
            die_json(false, 'Konnte events.json NICHT speichern! Fehlende Schreibrechte? Ordner /fotos/data/ braucht 755, Events-Datei 644.');
        }
    }
    @chmod(EVENTS_FILE, 0644);

    /* 8) ERFOLGS-ANTWORT */
    die_json(true, '✅ ERFOLG! ' . count($savedImages) . ' Bilder gespeichert, ' . count($photosJson) . ' Einträge in events.json geschrieben. Galerie sofort sichtbar!', [
        'photos_saved_count'  => count($savedImages),
        'photos_json_count'   => count($photosJson),
        'target_folder'       => $folder,
        'preview_url'         => '/fotos/event.html?id=' . rawurlencode($eventId),
        'events_file_size'    => filesize(EVENTS_FILE)
    ]);
}

/* Fallback: unbekannte Action */
die_json(false, 'Unbekannte Action: ' . htmlspecialchars($action));
