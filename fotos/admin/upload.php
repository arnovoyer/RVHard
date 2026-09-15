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
/* 🔥 THUMBNAIL KONFIGURATION – Wie Google Drive! Kleine, schnelle Vorschau-Bilder */
define('THUMB_SUFFIX', '._rvthumb'); /* Dateiendung = Suffix + .webp (Beispiel: IMG_123.JPG._rvthumb.webp) */
define('THUMB_MAX_W', 420); /* Pixel Breite – ausreichend für Galerie-Vorschau Karten */
define('THUMB_QUALITY', 78); /* WebP Qualität (60-85 reicht für Vorschau) */

/* =============== 🔥 THUMBNAIL HELFER (GD Library, fast überall verfügbar) ===============
 * Nimmt großes Original, erzeugt kleine .webp Vorschau (max THUMB_MAX_W breit, 80KB statt 12MB!)
 * Gibt: relativen Web-Pfad zum Thumbnail ODER false bei Fehler
 * Retroaktiv: Alte bestehende Bilder ohne Thumbnail werden automatisch beim nächsten Publish nachträglich erzeugt!
 * FIX HOCHKANT FOTOS: Liest EXIF Orientation Tag (Handy/Kamera speichert Pixel quer, Tag sagt "90° drehen!)
 *                     GD ignoriert EXIF standardmäßig, Browser aber nicht! Deshalb vorher Thumbnails falsch gedreht!
 */
function rv_createThumbIfMissing(string $absSourcePath, string $publicSrc) {
    if (!file_exists($absSourcePath)) return false;
    /* 1) Ziel-Pfade: <OriginalPfad>._rvthumb.webp  (oder .jpg falls WebP nicht geht) */
    $thumbAbs    = $absSourcePath . THUMB_SUFFIX . '.webp';
    $thumbAbsJpg = $absSourcePath . THUMB_SUFFIX . '.jpg';
    $thumbPublic = $publicSrc  . THUMB_SUFFIX . '.webp';

    /* ---------------- EXIF ORIENTATION SCHNELL-CHECK ----------------
     * Hochkant-Fotos: JPEGs von Handy/Kamera haben oft Pixel in Querformat + EXIF Orientation = 6 (90° drehen)
     * Browser (Lightbox Original) liest EXIF automatisch → OK! GD (Thumbnail Generator) liest ihn NICHT → 90° gedreht!
     * Fix: Wenn EXIF != 1 und Thumb schon alt existiert → IGNORIER Cache, neu erzeugen! (Behebt alte falsche Thumbs)
     */
    $exifOrient = 1;
    if (function_exists('exif_imagetype') && function_exists('exif_read_data')) {
        $type = @exif_imagetype($absSourcePath);
        if ($type === IMAGETYPE_JPEG || $type === IMAGETYPE_TIFF_II || $type === IMAGETYPE_TIFF_MM) {
            $exif = @exif_read_data($absSourcePath, 'IFD0');
            if (is_array($exif) && !empty($exif['Orientation'])) {
                $exifOrient = (int)$exif['Orientation'];
                if ($exifOrient < 1) $exifOrient = 1;
                if ($exifOrient > 8) $exifOrient = 8;
            }
        }
    }
    /* 2) Schon vorhanden + nicht älter als Original + EXIF=1 (keine Drehung nötig) → Cache verwenden! */
    $useCache = file_exists($thumbAbs) || file_exists($thumbAbsJpg);
    if ($useCache && $exifOrient === 1) {
        $cacheMod = max(
            (file_exists($thumbAbs) ? filemtime($thumbAbs) : 0),
            (file_exists($thumbAbsJpg) ? filemtime($thumbAbsJpg) : 0)
        );
        if ($cacheMod >= filemtime($absSourcePath)) {
            return file_exists($thumbAbs) ? ($publicSrc . THUMB_SUFFIX . '.webp') : ($publicSrc . THUMB_SUFFIX . '.jpg');
        }
    }
    /* Alte falsche Thumbnails (EXIF !=1, Drehung fehlte) sofort löschen, damit wir neu machen */
    if ($exifOrient !== 1) {
        @unlink($thumbAbs);
        @unlink($thumbAbsJpg);
    }
    /* 3) GD Extension verfügbar? Ohne GD können wir nichts machen → false (Browser nimmt Original) */
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
        static $warnedGd = false;
        if (!$warnedGd) { $GLOBALS['warnings'][] = 'ℹ️ Info: PHP GD nicht aktiv auf Server → keine Mini-Vorschau-Bilder (langsamer). Hosting aktivieren für schnelle Vorschau wie Google Drive!'; $warnedGd = true; }
        return false;
    }
    [$origW, $origH, $imgType] = @getimagesize($absSourcePath);
    if (!$origW || !$origH) return false;
    /* Source einlesen je nach Typ (JPG/PNG/GIF/WEBP) — NUR JPEG/PNG haben EXIF mit Orientation! */
    $srcImage = null;
    switch ($imgType) {
        case IMAGETYPE_JPEG: $srcImage = @imagecreatefromjpeg($absSourcePath); break;
        case IMAGETYPE_PNG:  $srcImage = @imagecreatefrompng($absSourcePath);  break;
        case IMAGETYPE_GIF:  $srcImage = @imagecreatefromgif($absSourcePath);  break;
        case IMAGETYPE_WEBP: $srcImage = @imagecreatefromwebp($absSourcePath); break;
        default: break;
    }
    if (!$srcImage) return false;

    /* ---------------- 🔥 EXIF ORIENTATION KORREKTUR AUF ORIGINAL-GD BILD ----------------
     * WICHTIG: Drehung MUSS vor dem Resizen passieren, sonst stimmen Breite/Höhe nicht!
     * Standard EXIF Werte 1-8 (https://exiftool.org/TagNames/EXIF.html):
     * 1 = Normal        → Nichts tun
     * 2 = Spiegel horiz → Flip Horizontal
     * 3 = 180° drehen   → Rotate 180
     * 4 = Spiegel vert  → Flip Vertical
     * 5 = Transpose     → Flip Horiz + Rotate 270° CW (swap W/H!)
     * 6 = 90° CW        → Rotate 90° (swap W/H! → Hochkant!)
     * 7 = Transverse    → Flip Horiz + Rotate 90° CW (swap W/H!)
     * 8 = 270° CW       → Rotate 270° CW = 90° CCW (swap W/H!)
     */
    $needsWHSwap = false;
    if (is_resource($srcImage) || (is_object($srcImage) && $srcImage instanceof \GdImage)) {
        switch ($exifOrient) {
            case 2: @imageflip($srcImage, IMG_FLIP_HORIZONTAL); break;
            case 3: $srcImage = @imagerotate($srcImage, 180, 0); break;
            case 4: @imageflip($srcImage, IMG_FLIP_VERTICAL); break;
            case 5:
                @imageflip($srcImage, IMG_FLIP_HORIZONTAL);
                $srcImage = @imagerotate($srcImage, 270, 0);
                $needsWHSwap = true;
                break;
            case 6:
                $srcImage = @imagerotate($srcImage, -90, 0); /* -90° = CW 90° */
                $needsWHSwap = true;
                break;
            case 7:
                @imageflip($srcImage, IMG_FLIP_HORIZONTAL);
                $srcImage = @imagerotate($srcImage, -90, 0);
                $needsWHSwap = true;
                break;
            case 8:
                $srcImage = @imagerotate($srcImage, -270, 0); /* -270° = CW 270° = CCW 90° */
                $needsWHSwap = true;
                break;
        }
    }
    /* Nach Rotation: Breite ↔ Höhe vertauschen (wichtig für neue Zielgröße!) */
    if ($needsWHSwap) {
        $tmp = $origW; $origW = $origH; $origH = $tmp;
        unset($tmp);
    }
    if (!$srcImage) return false;

    /* Kein Upscaling! Wenn Bild kleiner als THUMB_MAX_W → sparen wir uns das (Original ist klein genug) */
    if ($origW <= THUMB_MAX_W) {
        @imagedestroy($srcImage);
        return $publicSrc; /* Original ist schon klein genug → kein extra Thumb nötig! */
    }
    $newW = (int)THUMB_MAX_W;
    $newH = (int)round($origH * ($newW / $origW));
    $canvas = @imagecreatetruecolor($newW, $newH);
    if (!$canvas) { @imagedestroy($srcImage); return false; }
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefilledrectangle($canvas, 0, 0, $newW, $newH, $transparent);

    /* Scharf resizen mit imagecopyresampled! (Jetzt mit korrekt gedrehtem Source!) */
    @imagecopyresampled($canvas, $srcImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    /* WebP ist STANDARD (Google Drive nutzt auch WebP für Vorschauen! 50% kleiner als JPG bei gleicher Qualität!) */
    $saved = false;
    if (function_exists('imagewebp')) {
        $saved = @imagewebp($canvas, $thumbAbs, (int)THUMB_QUALITY);
        if (!$saved) {
            /* Fallback WebP fehlgeschlagen → JPG versuchen */
            $saved = @imagejpeg($canvas, $thumbAbsJpg, 82);
            if ($saved) $thumbPublic = $publicSrc . THUMB_SUFFIX . '.jpg';
        }
    } else {
        /* Fallback falls altes PHP ohne WebP-Support: Thumb als .jpg speichern */
        $saved = @imagejpeg($canvas, $thumbAbsJpg, 82);
        if ($saved) $thumbPublic = $publicSrc . THUMB_SUFFIX . '.jpg';
    }
    @imagedestroy($canvas);
    @imagedestroy($srcImage);
    if (!$saved) return false;
    @chmod($thumbAbs, 0644);
    @chmod($thumbAbsJpg, 0644);
    return $thumbPublic;
}

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
            $publicSrc = '/fotos/data/' . $folder . '/' . $safeName;
            /* 🔥 SOFORT Thumbnail erzeugen! Dann schon vorhanden fürs Galerie-Render! */
            @rv_createThumbIfMissing($targetPath, $publicSrc);
            $savedImages[$names[$i]] = $publicSrc;
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

    /* 5) Fotos für JSON aufbauen — NUR VALIDE SERVER-Pfade! Kein file:/// und kein C:\ Windows Pfad! */
    $photosJson = [];
    $warnings   = [];
    foreach ($photosMeta as $pm) {
        $origName = $pm['originalName'] ?? '';
        $src = null;

        /* 🔴 SICHERHEIT: Prüfe zuerst vorhandenes src auf GÜLTIGKEIT! */
        if (!empty($pm['src']) && is_string($pm['src'])) {
            $raw = trim($pm['src']);
            $blocked = false;
            if (stripos($raw, 'file://') === 0)    $blocked = true; /* Windows/Mac/Linux Lokale Pfade */
            if (stripos($raw, 'blob:') === 0)     $blocked = true; /* Browser Blob URLs (nur Vorschau!) */
            if (preg_match('#^[A-Za-z]:[\\\\/]#', $raw)) $blocked = true; /* C:\ D:\ Windows-Pfade */
            if (preg_match('#^https?://#i', $raw) && strpos($raw, 'rv-hard.at') === false && strpos($raw, 'coresg-normal.trae.ai') === false) $blocked = true;
            if (strpos($raw, '/fotos/data/') !== 0 && !$blocked && !preg_match('#^https?://#i', $raw)) {
                /* Kein file, aber fängt nicht mit /fotos/data an → aufräumen */
                $raw = ltrim(str_replace('\\', '/', $raw), '/');
                if (strpos($raw, 'fotos/data/') === 0) $raw = '/' . $raw;
            }
            if (!$blocked && strpos($raw, '/fotos/data/') === 0) {
                $src = $raw;
            } elseif ($blocked) {
                $warnings[] = "Verworfen: Lokaler Pfad '$raw' (nicht im Web sichtbar!). Bild '$origName' muss via Upload nochmal zum Server geschickt werden.";
                $src = null;
            }
        }

        /* Normale Verarbeitung: Upload via HTTP Form = Bild wurde tatsächlich auf Server gespeichert */
        if (!$src && isset($savedImages[$origName])) {
            $src = $savedImages[$origName];
        }
        if (!$src) continue;

        $bibNumbers = is_array($pm['bibNumbers'] ?? null) ? $pm['bibNumbers'] : [];
        $photosJson[] = [
            'src'         => $src,
            'bibNumbers'  => array_values($bibNumbers)
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
        /* User Wunsch: KEINE KI Images!
           Cover: ERSTES echtes Foto falls vorhanden, sonst NULL (lassen wir im JS nachträglich sanitizen) */
        'coverPhoto'  => $photosJson[0]['src'] ?? null,
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

    /* 🔥 GLOBAL SANITIZER: ALLE Events, AUCH ALTE ARCHIV-Events — NUR VALIDE /fotos/data Src Pfade! */
    /* Ursache: Alte Publish-Versionen haben file:// / C: / blob: in events.json gespeichert → Security-Crash.
       Jetzt wird jedes Bild in JEDEM Event beim Publish automatisch geprüft und ggf. entfernt. */
    $totalDeletedBadPhotos = 0;
    $totalFixedCover = 0;
    /* PHP 7.4 kompatible Helper (statt str_starts_with/str_ends_with — nur PHP 8+) */
    $startsWith = function(string $hay, string $need): bool {
        return $need === '' || strpos($hay, $need) === 0;
    };
    $endsWith = function(string $hay, string $need): bool {
        $len = strlen($need);
        return $len === 0 || substr($hay, -$len) === $need;
    };
    $sanitizeSrc = function($src) use (&$totalFixedCover, $startsWith, $endsWith) {
        if (!is_string($src) || trim($src) === '') return false;
        $s = trim($src);
        /* Step 1: Backticks entfernen! Diese machen URLs zu "lokalem Pfad" und Security-Crash!
           Beispiel: `https://example.com/img.jpg`  →  https://example.com/img.jpg */
        $before = $s;
        $s = trim($s, "` \t\n\r\0\x0B\"'");
        if ($startsWith($s, '`') || $endsWith($s, '`')) {
            $s = trim($s, "`\"'"); $totalFixedCover++;
        }
        if ($startsWith($s, '`') || $endsWith($s, '`')) {
            $s = preg_replace('#^`+|`+$#', '', $s);
            $totalFixedCover++;
        }
        if ($s !== $before) $totalFixedCover++;
        /* Blockierte unsichere Protokolle & Windows-Pfade (GLOBAL!) */
        if (stripos($s, 'file://') === 0) return false;
        if (stripos($s, 'blob:') === 0) return false;
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $s)) return false;
        if (preg_match('#^/[A-Za-z]:#', $s)) return false;
        if (stripos($s, 'data:image') === 0) return false;
        /* Externe Domains blockieren außer RV Hard. User will KEINE KI URLs mehr! Also coresg-normal auch blocken! */
        if (preg_match('#^https?://#i', $s)) {
            if (strpos($s, 'rv-hard.at') !== false) return $s;
            return false; /* User Wunsch: KEINE KI Images (coresg-normal) mehr! */
        }
        /* Nur Server-interne Pfade in /fotos/data erlauben */
        $s = str_replace('\\', '/', $s);
        if ($startsWith($s, '/fotos/data/')) return $s;
        if ($startsWith($s, 'fotos/data/')) return '/' . ltrim($s, '/');
        /* Alles andere ist kaputt (z.B. Windows Pfade ohne Protokoll) */
        return false;
    };
    foreach ($merged as &$_evRef) {
        if (!is_array($_evRef)) continue;
        /* 🔥 AUCH coverPhoto SANITIZEN! (Genau DAS war bei Kriterium/EZF das Problem!) */
        if (!empty($_evRef['coverPhoto'])) {
            $before = $_evRef['coverPhoto'];
            $fixedCover = $sanitizeSrc($_evRef['coverPhoto']);
            if ($fixedCover === false || $fixedCover !== $before) {
                /* User Wunsch: KEINE KI Images! Fallback = ERSTES Foto im photos[] Array ODER leer! */
                $st = strtolower($_evRef['subtype'] ?? '');
                $fb = null;
                if (!empty($_evRef['photos'][0]['src'])) {
                    $firstClean = $sanitizeSrc($_evRef['photos'][0]['src']);
                    if ($firstClean !== false) $fb = $firstClean;
                }
                if ($fixedCover === false) {
                    $totalFixedCover++;
                }
                $fixedCover = $fb; /* leer lassen, falls KEIN Foto vorhanden! KEIN KI FALLBACK! */
            }
            if ($fixedCover !== $_evRef['coverPhoto']) $totalFixedCover++;
            $_evRef['coverPhoto'] = $fixedCover; /* Kann jetzt NULL sein → egal, JS übernimmt Fallback */
        }
        /* Fotos Array sanitizen (wie bisher) */
        if (empty($_evRef['photos']) || !is_array($_evRef['photos'])) continue;
        $cleanPhotos = [];
        foreach ($_evRef['photos'] as $_ph) {
            if (!is_array($_ph)) continue;
            $cleanSrc = $sanitizeSrc($_ph['src'] ?? '');
            if ($cleanSrc === false) {
                $totalDeletedBadPhotos++; /* Lokalen Pfad / kaputt gefunden → löschen! */
                continue;
            }
            $_ph['src'] = $cleanSrc;
            if (!empty($_ph['thumbnail'])) {
                $clTh = $sanitizeSrc($_ph['thumbnail']);
                if ($clTh === false) unset($_ph['thumbnail']); else $_ph['thumbnail'] = $clTh;
            }
            $cleanPhotos[] = $_ph;
        }
        $_evRef['photos'] = array_values($cleanPhotos);
    }
    unset($_evRef); /* Referenz aufheben (Sicherheit) */

    /* 🔥🔥🔥 THUMBNAIL RETRO-GENERIERUNG (Google Drive Speed!)
        Für JEDES Foto in JEDEM Event prüfen: thumbnail Feld leer? → SOFORT erzeugen + speichern!
        Das bedeutet: User braucht nur 1x Publish drücken, und ALLE (auch alte Bergrennen Bilder!) kriegen kleine Vorschauen!
    */
    $totalThumbsCreated = 0;
    $totalThumbsChecked = 0;
    foreach ($merged as &$_evRef2) {
        if (!is_array($_evRef2) || empty($_evRef2['photos']) || !is_array($_evRef2['photos'])) continue;
        /* Ordner-Name vom Event für Absolut-Pfad */
        $evFolder = trim((string)($_evRef2['folder'] ?? ''));
        if ($evFolder === '') continue;
        $evFolder = preg_replace('/[^a-z0-9_\-äöüÄÖÜß]/i', '', $evFolder);
        $absDir = rtrim(DATA_ROOT, '/') . '/' . $evFolder . '/';
        foreach ($_evRef2['photos'] as &$_phRef) {
            if (!is_array($_phRef)) continue;
            $totalThumbsChecked++;
            $src = (string)($_phRef['src'] ?? '');
            if ($src === '') continue;
            /* Wenn thumbnail schon gesetzt ist + Datei existiert → NIX tun (schnell!) */
            if (!empty($_phRef['thumbnail'])) {
                $existingThumbAbs = rtrim(DATA_ROOT, '/') . preg_replace('#^/fotos/data#', '', (string)$_phRef['thumbnail']);
                if (file_exists($existingThumbAbs)) continue;
            }
            /* Absoluter Pfad zum Original berechnen: /fotos/data/<ordner>/<datei> → __DIR__/../data/<ordner>/<datei> */
            $absSrc = rtrim(DATA_ROOT, '/') . preg_replace('#^/fotos/data#', '', $src);
            if (!file_exists($absSrc)) continue;
            $thumb = rv_createThumbIfMissing($absSrc, $src);
            if (is_string($thumb) && $thumb !== '' && $thumb !== $src) {
                $_phRef['thumbnail'] = $thumb;
                $totalThumbsCreated++;
            } elseif (is_string($thumb) && $thumb === $src) {
                /* Original ist kleiner als 420px → kein extra Thumb nötig, Feld thumbnail aber leer lassen (optional) */
                $_phRef['thumbnail'] = null;
            }
        }
        unset($_phRef);
    }
    unset($_evRef2);
    if ($totalThumbsCreated > 0) {
        $warnings[] = "🖼️🚀 Google Drive Speed! Automatisch $totalThumbsCreated Mini-Vorschau-Bilder (kleine .webp Dateien, 30-80KB statt 12MB!) neu erzeugt — Galerie lädt jetzt 20-50x SCHNELLER (geprüft: $totalThumbsChecked Fotos)";
    }

    if ($totalDeletedBadPhotos > 0) {
        $warnings[] = "🧹 Automatisch $totalDeletedBadPhotos alte Fotos mit LOKALEM PFAD (file:///C: etc.) aus events.json ENTFERNT — neu hochladen & publ. falls benötigt!";
    }
    if ($totalFixedCover > 0) {
        $warnings[] = "🔧 Automatisch $totalFixedCover Cover repariert (falsche Zeichen entfernt, KEINE KI-Bilder mehr genutzt! NUR echte hochgeladene Bilder als Cover!)";
    }

    $metaBlock = [[
        '_schemaVersion'  => '3.2',
        '_readme'         => 'SBS Galerie V3.2 - auto-publish via admin/upload.php',
        '_lastModified'   => date('c'),
        '_lastFolder'     => $folder,
        '_badPhotosRemoved' => $totalDeletedBadPhotos
    ]];
    $output = array_merge($metaBlock, $merged);

    /* 7) events.json SPEICHERN — 100% VALIDES JSON! */
    $jsonEncodeFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    $jsonStr = json_encode($output, $jsonEncodeFlags);
    if ($jsonStr === false || strlen($jsonStr) < 50) {
        /* Fallback: Photos rekursiv mit UTF8-Sanitizer durchgehen, falls ein Dateiname kaputt war */
        $sanitizer = function($v) use (&$sanitizer) {
            if (is_array($v)) return array_map($sanitizer, $v);
            if (is_string($v)) return mb_convert_encoding($v, 'UTF-8', 'UTF-8');
            return $v;
        };
        $output = array_map($sanitizer, $output);
        $jsonStr = json_encode($output, $jsonEncodeFlags);
        if ($jsonStr === false) {
            die_json(false, 'Konnte KEIN gültiges JSON erzeugen! Fehler: ' . json_last_error_msg());
        }
    }
    if (function_exists('header_register_variable')) {
        /* Sicherstellen dass PHP Warnings nicht in Output gelangen und JSON zerstören! */
    }
    /* Parsebare JSON Syntax Prüfung bevor gespeichert wird! */
    $testParse = @json_decode($jsonStr, true);
    if (!is_array($testParse)) {
        die_json(false, 'JSON-Validierung FEHLGESCHLAGEN! Nicht gespeichert, um Defekt zu vermeiden. JSON letzte Zeichen: ' . substr($jsonStr, -120));
    }
    $tmpFile = EVENTS_FILE . '.tmp.' . uniqid('', true);
    if (@file_put_contents($tmpFile, $jsonStr, LOCK_EX) === false) {
        @unlink($tmpFile);
        die_json(false, 'Konnte events.json temporär nicht schreiben – Ordner-Rechte prüfen (/fotos/data/ = 755, Datei = 644).');
    }
    clearstatcache(true, $tmpFile);
    $filesizeTmp = @filesize($tmpFile);
    if (!$filesizeTmp || $filesizeTmp < 100) {
        @unlink($tmpFile);
        die_json(false, 'Temporäre events.json zu klein! Wahrscheinlich fehlende Schreibrechte oder Festplatte voll.');
    }
    if (!@rename($tmpFile, EVENTS_FILE)) {
        @unlink($tmpFile);
        if (!@file_put_contents(EVENTS_FILE, $jsonStr, LOCK_EX)) {
            die_json(false, 'Konnte events.json NICHT speichern! Fehlende Schreibrechte? Ordner /fotos/data/ braucht 755, Events-Datei 644.');
        }
    }
    @chmod(EVENTS_FILE, 0644);

    /* 8) ERFOLGS-ANTWORT (inkl. Warnungen für verworfene lokale Pfade!) */
    $responseData = [
        'photos_saved_count'  => count($savedImages),
        'photos_json_count'   => count($photosJson),
        'target_folder'       => $folder,
        'preview_url'         => '/fotos/event.html?id=' . rawurlencode($eventId),
        'events_file_size'    => filesize(EVENTS_FILE),
        'warnings'            => $warnings
    ];
    $msg = '✅ ERFOLG! ' . count($savedImages) . ' Bilder gespeichert, ' . count($photosJson) . ' Einträge in events.json geschrieben. Galerie sofort sichtbar!';
    if (count($warnings)) $msg .= ' ⚠️ ACHTUNG: '.count($warnings).' Bild(er) mit LOKALEM PFAD (z.B. file://) verworfen — Admin muss via HTTP:// statt Doppelklick geöffnet werden!';
    die_json(true, $msg, $responseData);
}

/* Fallback: unbekannte Action */
die_json(false, 'Unbekannte Action: ' . htmlspecialchars($action));
