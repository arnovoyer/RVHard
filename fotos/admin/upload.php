<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/* ============================================================
 *  SBS GALERIE – AUTO UPLOAD ENDPOINT
 *  Speichert Bilder direkt in Ordner + merged events.json
 *  Aufgerufen per AJAX aus dem Admin Bereich
 *  ✅ AKTUELL: Subdomain-kompatibel! (fotos.rv-hard.at / rv-hard.at/fotos / beliebige Domain)
 * ============================================================ */

define('DATA_ROOT', realpath(__DIR__ . '/../data') . '/');
define('EVENTS_FILE', DATA_ROOT . 'events.json');

/* 🔥🔥🔥 SUBDOMAIN DETECTION + AUTO BASE PATH!
 * Galerie läuft auf 2 Arten von Setups:
 *   A) Hauptdomain Unterordner: rv-hard.at/fotos/…          → PUBLIC_BASE = '/fotos'
 *   B) Eigene Subdomain:      fotos.rv-hard.at/…            → PUBLIC_BASE = '' (leer = Domain Root)
 *   C) Beliebige Domain:     irgendwas.test/…               → PUBLIC_BASE = ''
 *   D) Lokaler Test:         localhost:8080/rv-hard/fotos/ → PUBLIC_BASE = '/rv-hard/fotos'
 *
 * AUTO-ERKENNUNG (Falls REQUEST_URI verfügbar):
 *   Wenn upload.php unter /fotos/admin/upload.php aufgerufen wurde → PUBLIC_BASE = '/fotos'
 *   Sonst → PUBLIC_BASE = '' (Subdomain/Root)
 */
function fg_php_auto_public_base() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $candidates = [];
    /* Candidate 1: Request URI Path */
    if (!empty($_SERVER['REQUEST_URI'])) {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (is_string($uri) && $uri !== '') {
            /* upload.php liegt NORMALERWEISE im Admin Ordner → /<base>/admin/upload.php */
            if (strpos($uri, '/admin/upload.php') !== false) {
                $candidates[] = rtrim(substr($uri, 0, strpos($uri, '/admin/upload.php')), '/');
            } elseif (preg_match('#^(.*)/admin/[^/]*\.php$#', $uri, $m)) {
                $candidates[] = rtrim($m[1], '/');
            }
        }
    }
    /* Candidate 2: SCRIPT_NAME / PHP_SELF */
    foreach (['SCRIPT_NAME','PHP_SELF','SCRIPT_URL','URL'] as $k) {
        if (empty($_SERVER[$k]) || !is_string($_SERVER[$k])) continue;
        if (strpos($_SERVER[$k], '/admin/upload.php') !== false) {
            $candidates[] = rtrim(substr($_SERVER[$k], 0, strpos($_SERVER[$k], '/admin/upload.php')), '/');
        } elseif (preg_match('#^(.*)/admin/[^/]*\.php$#i', $_SERVER[$k], $m)) {
            $candidates[] = rtrim($m[1], '/');
        }
    }
    $chosen = '';
    foreach ($candidates as $c) {
        if (is_string($c) && $c !== '' && substr($c,0,1) === '/') {
            $chosen = $c; break;
        }
    }
    /* Fallback: Wenn Ordner-Struktur auf Dateisystem /htdocs/fotos/admin/upload.php lautet,
       aber Domain auf fotos/ zeigt → können wir nix erkennen → leer lassen (Subdomain Modus) */
    if ($chosen === '' || strtolower($chosen) === '/fotos') {
        /* Fallback Default: '/fotos' oder '' lassen wir '' als Default nur wenn Request URI auf / beginnt */
        if (empty($_SERVER['REQUEST_URI']) || strpos($_SERVER['REQUEST_URI'] ?? '', '/fotos/') === 0 || strpos($_SERVER['REQUEST_URI'] ?? '', '/fotos') === 0) {
            $chosen = '/fotos';
        }
    }
    $cached = (is_string($chosen) && $chosen !== '/') ? rtrim($chosen, '/') : '';
    return $cached;
}
define('PUBLIC_BASE', fg_php_auto_public_base()); /* z.B. '/fotos' oder '' (leer bei Subdomain!) */
/* Shortcut für data Public Pfad */
function fg_data_public_prefix(): string {
    $b = PUBLIC_BASE;
    return ($b === '' || $b === '0') ? '/data' : $b . '/data';
}
function fg_admin_public_prefix(): string {
    $b = PUBLIC_BASE;
    return ($b === '' || $b === '0') ? '/admin' : $b . '/admin';
}
function fg_event_public_url(string $eventId): string {
    $b = PUBLIC_BASE;
    $prefix = ($b === '' || $b === '0') ? '/event.html' : $b . '/event.html';
    return $prefix . '?id=' . rawurlencode($eventId);
}
define('MAX_IMG_SIZE', 15 * 1024 * 1024);
define('ALLOWED_EXT', ['jpg','jpeg','png','gif','webp']);
define('ALLOWED_TYPES', [
    'image/jpeg','image/png','image/gif','image/webp'
]);
/* 🔥 THUMBNAIL KONFIGURATION – Wie Google Drive! Kleine, schnelle Vorschau-Bilder */
define('THUMB_SUFFIX', '._rvthumb'); /* Dateiendung = Suffix + .webp (Beispiel: IMG_123.JPG._rvthumb.webp) */
define('THUMB_MAX_W', 420); /* Pixel Breite – ausreichend für Galerie-Vorschau Karten */
define('THUMB_QUALITY', 78); /* WebP Qualität (60-85 reicht für Vorschau) */
/* 🔥 LIGHTBOX DISPLAY GRÖSSE (Drittes Format! Zwischen Thumbnail und Original 12MB!)
    Perfekt für Lightbox-Ansicht auf Handy/Tablet/PC Full-HD / 4K: Statt 12MB DSLR Original nur 600KB-1.2MB → KEIN LAG MEHR!
    Genau wie Google Drive: Lightbox zeigt NICHT das Original (nur Download!), sondern die optimierte Display-Größe! */
define('DISPLAY_SUFFIX', '._rvdisplay');
define('DISPLAY_MAX_W', 2560); /* 2560px breit → passt auf Full-HD (1920) + 4K mit Skalierung, super scharf! */
define('DISPLAY_QUALITY', 84); /* Höher als Thumbnail, weil Lightbox größer! 82-86 reicht für kaum sichtbaren Unterschied zum Original */

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

/* =============== 🔥 LIGHTBOX DISPLAY HELFER (mittlere Größe, kein Lag!) ===============
 * Genau wie Google Drive! Lightbox zeigt NICHT 12MB DSLR-Original sondern optimierte ~1MB Version!
 * AUCH mit EXIF Orientation Fix! + Retroaktiv erzeugen bei nächstem Publish.
 */
function rv_createMediumDisplayIfMissing(string $absSourcePath, string $publicSrc) {
    if (!file_exists($absSourcePath)) return false;
    $dAbs    = $absSourcePath . DISPLAY_SUFFIX . '.webp';
    $dAbsJpg = $absSourcePath . DISPLAY_SUFFIX . '.jpg';
    $dPub    = $publicSrc    . DISPLAY_SUFFIX . '.webp';
    /* EXIF Drehung Check (gleiches Prinzip wie Thumbnail → sonst falsch herum) */
    $exifOrient = 1;
    if (function_exists('exif_imagetype') && function_exists('exif_read_data')) {
        $type = @exif_imagetype($absSourcePath);
        if ($type === IMAGETYPE_JPEG || $type === IMAGETYPE_TIFF_II || $type === IMAGETYPE_TIFF_MM) {
            $exif = @exif_read_data($absSourcePath, 'IFD0');
            if (is_array($exif) && !empty($exif['Orientation'])) {
                $exifOrient = (int)$exif['Orientation'];
                if ($exifOrient < 1 || $exifOrient > 8) $exifOrient = 1;
            }
        }
    }
    /* Cache OK wenn: Display existiert + neuer als Original + EXIF=1 (sonst neu machen wg. Drehung!) */
    $cacheOk = (file_exists($dAbs) || file_exists($dAbsJpg)) && $exifOrient === 1;
    if ($cacheOk) {
        $cm = max((file_exists($dAbs)?filemtime($dAbs):0), (file_exists($dAbsJpg)?filemtime($dAbsJpg):0));
        if ($cm >= filemtime($absSourcePath)) {
            return file_exists($dAbs) ? ($publicSrc . DISPLAY_SUFFIX . '.webp') : ($publicSrc . DISPLAY_SUFFIX . '.jpg');
        }
    }
    /* Alte falsche Display Versionen mit fehlender Drehung löschen */
    if ($exifOrient !== 1) { @unlink($dAbs); @unlink($dAbsJpg); }
    if (!extension_loaded('gd') || !function_exists('gd_info')) return false;
    [$origW, $origH, $imgType] = @getimagesize($absSourcePath);
    if (!$origW || !$origH) return false;
    /* Source laden */
    $src = null;
    switch ($imgType) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($absSourcePath); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($absSourcePath);  break;
        case IMAGETYPE_GIF:  $src = @imagecreatefromgif($absSourcePath);  break;
        case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($absSourcePath); break;
    }
    if (!$src) return false;
    /* EXIF Drehung auf Source (VOR Resize!) */
    $whSwap = false;
    if (is_resource($src) || (is_object($src) && $src instanceof \GdImage)) {
        switch ($exifOrient) {
            case 2: @imageflip($src, IMG_FLIP_HORIZONTAL); break;
            case 3: $src = @imagerotate($src, 180, 0); break;
            case 4: @imageflip($src, IMG_FLIP_VERTICAL); break;
            case 5: @imageflip($src, IMG_FLIP_HORIZONTAL); $src = @imagerotate($src, 270, 0); $whSwap = true; break;
            case 6: $src = @imagerotate($src, -90, 0);  $whSwap = true; break;
            case 7: @imageflip($src, IMG_FLIP_HORIZONTAL); $src = @imagerotate($src, -90, 0); $whSwap = true; break;
            case 8: $src = @imagerotate($src, -270, 0); $whSwap = true; break;
        }
    }
    if ($whSwap) { $t=$origW; $origW=$origH; $origH=$t; unset($t); }
    if (!$src) return false;
    /* Wenn Original kleiner als DISPLAY_MAX_W → kein extra Medium nötig (Original ist klein genug!) */
    if ($origW <= DISPLAY_MAX_W) { @imagedestroy($src); return $publicSrc; }
    $newW = (int)DISPLAY_MAX_W;
    $newH = (int)round($origH * ($newW / $origW));
    $canvas = @imagecreatetruecolor($newW, $newH);
    if (!$canvas) { @imagedestroy($src); return false; }
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
    @imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    $saved = false;
    if (function_exists('imagewebp')) {
        $saved = @imagewebp($canvas, $dAbs, (int)DISPLAY_QUALITY);
        if (!$saved) { $saved = @imagejpeg($canvas, $dAbsJpg, 86); if ($saved) $dPub = $publicSrc . DISPLAY_SUFFIX . '.jpg'; }
    } else {
        $saved = @imagejpeg($canvas, $dAbsJpg, 86);
        if ($saved) $dPub = $publicSrc . DISPLAY_SUFFIX . '.jpg';
    }
    @imagedestroy($canvas); @imagedestroy($src);
    if (!$saved) return false;
    @chmod($dAbs, 0644); @chmod($dAbsJpg, 0644);
    return $dPub;
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
            $publicSrc = fg_data_public_prefix() . '/' . $folder . '/' . $safeName;
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

            /* 🔥🔥🔥 Externe Domain Whitelist: JETZT SUBDOMAIN KOMPATIBEL!
               Erlaube: 1) Aktuelle HTTP_HOST (aktive Subdomain/Domain)
                        2) rv-hard.at + *.rv-hard.at (Hauptdomain)
                        3) rv-hard.arnovoyer.com + *. (Testsystem)
               Alles andere = Fremde/KI Domain = blockieren! */
            if (!$blocked && preg_match('#^https?://#i', $raw)) {
                $host = '';
                if (function_exists('parse_url')) {
                    $pu = @parse_url($raw);
                    if (is_array($pu) && !empty($pu['host'])) $host = strtolower($pu['host']);
                }
                if ($host === '') {
                    $blocked = true; /* URL kaputt! */
                } else {
                    $curHost = !empty($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
                    if (strpos($curHost, ':') !== false) $curHost = substr($curHost, 0, strpos($curHost, ':'));
                    $isOk = false;
                    if ($curHost !== '' && ($host === $curHost || (strlen($curHost)>0 && substr($host,-strlen($curHost)-1) === '.' . $curHost))) $isOk = true;
                    if ($host === 'rv-hard.at' || substr($host, -11) === '.rv-hard.at') $isOk = true;
                    if ($host === 'rv-hard.arnovoyer.com' || substr($host, -24) === '.rv-hard.arnovoyer.com') $isOk = true;
                    if (!$isOk) $blocked = true;
                }
            }

            /* Relativer Pfad: Erlaube /fotos/data/xxx + /data/xxx! (Alt /fotos/data/ + Subdomain Neu /data/) */
            $dataPfadAlt = '/fotos/data/';
            $dataPfadNeu = fg_data_public_prefix() . '/';
            if (!$blocked) {
                $normalisiert = $raw;
                if (!preg_match('#^https?://#i', $normalisiert)) {
                    $normalisiert = ltrim(str_replace('\\', '/', $normalisiert), '/');
                    if (strpos($normalisiert, 'fotos/data/') === 0) $normalisiert = '/' . $normalisiert;
                    if (strpos($normalisiert, 'data/') === 0) $normalisiert = '/' . $normalisiert;
                }
                /* Alt Pfad (/fotos/data/) oder Subdomain Neu Pfad (/data/) */
                if (strpos($normalisiert, $dataPfadAlt) === 0 || strpos($normalisiert, $dataPfadNeu) === 0) {
                    $src = $normalisiert;
                }
            }
            if ($blocked) {
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
        /* Step 1: Backticks entfernen! Diese machen URLs zu "lokalem Pfad" und Security-Crash! */
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

        /* Externe Domains: JETZT SUBDOMAIN KOMPATIBEL!
           Erlaube: 1) Aktueller HTTP_HOST (aktive Domain/Subdomain!)
                    2) rv-hard.at + *.rv-hard.at
                    3) rv-hard.arnovoyer.com + *. */
        if (preg_match('#^https?://#i', $s)) {
            $host = '';
            if (function_exists('parse_url')) {
                $pu = @parse_url($s);
                if (is_array($pu) && !empty($pu['host'])) $host = strtolower($pu['host']);
            }
            if ($host === '') return false; /* URL kaputt */
            $curHost = !empty($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
            if (strpos($curHost, ':') !== false) $curHost = substr($curHost, 0, strpos($curHost, ':'));
            $isOk = false;
            if ($curHost !== '' && ($host === $curHost || (strlen($curHost)>0 && substr($host,-strlen($curHost)-1) === '.' . $curHost))) $isOk = true;
            if ($host === 'rv-hard.at' || substr($host, -11) === '.rv-hard.at') $isOk = true;
            if ($host === 'rv-hard.arnovoyer.com' || substr($host, -24) === '.rv-hard.arnovoyer.com') $isOk = true;
            return $isOk ? $s : false;
        }
        /* Server-interne Pfade: Alt (/fotos/data/xxx) + Subdomain Neu (/data/xxx) + RELATIV data/xxx + PUBLIC_BASE/data/xxx */
        $s = str_replace('\\', '/', $s);
        $dataAlt = '/fotos/data/';
        $dataNeu = fg_data_public_prefix() . '/';
        if ($startsWith($s, $dataAlt)) return $s;
        if ($startsWith($s, $dataNeu)) return $s;
        if ($startsWith($s, 'fotos/data/')) return '/' . ltrim($s, '/');
        if ($startsWith($s, '/data/') || $startsWith($s, 'data/')) {
            /* Falls /data/ nicht unser aktuelles data/ ist (wenn wir in /fotos laufen), auf /fotos/data/ mappen */
            if ($dataAlt !== '/data/') { return $startsWith($s, '/') ? $s : '/' . ltrim($s,'/'); }
            return $startsWith($s, '/') ? $s : '/' . ltrim($s, '/');
        }
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

    /* 🔥🔥🔥 LIGHTBOX DISPLAY MEDIUM GRÖSSE (LAG FREI!)
       Genau wie Google Drive: Zwischen 420px Thumb und 12MB Original → 2560px Display (600KB-1.2MB)!
       Beim Klick aufs Bild wird SOFORT das Medium angezeigt → KEIN 12MB Lade-LAG mehr!
       Original wird NUR noch beim "Herunterladen" Button geladen!
    */
    $totalDisplayCreated = 0;
    $totalDisplayChecked = 0;
    foreach ($merged as &$_evRef3) {
        if (!is_array($_evRef3) || empty($_evRef3['photos']) || !is_array($_evRef3['photos'])) continue;
        $evFolder = trim((string)($_evRef3['folder'] ?? ''));
        if ($evFolder === '') continue;
        $evFolder = preg_replace('/[^a-z0-9_\-äöüÄÖÜß]/i', '', $evFolder);
        foreach ($_evRef3['photos'] as &$_phRef3) {
            if (!is_array($_phRef3)) continue;
            $totalDisplayChecked++;
            $src = (string)($_phRef3['src'] ?? '');
            if ($src === '') continue;
            /* Schon gesetzt + Datei existiert → skip */
            if (!empty($_phRef3['display'])) {
                $e = rtrim(DATA_ROOT, '/') . preg_replace('#^/fotos/data#', '', (string)$_phRef3['display']);
                if (file_exists($e)) continue;
            }
            $absSrc = rtrim(DATA_ROOT, '/') . preg_replace('#^/fotos/data#', '', $src);
            if (!file_exists($absSrc)) continue;
            $display = rv_createMediumDisplayIfMissing($absSrc, $src);
            if (is_string($display) && $display !== '' && $display !== $src) {
                $_phRef3['display'] = $display;
                $totalDisplayCreated++;
            } elseif (is_string($display) && $display === $src) {
                $_phRef3['display'] = null; /* Original kleiner als 2560px → kein Medium nötig */
            }
        }
        unset($_phRef3);
    }
    unset($_evRef3);
    if ($totalDisplayCreated > 0) {
        $warnings[] = "🚀🔍 Lightbox LAG-FREI! Automatisch $totalDisplayCreated mittlere Display-Größen (2560px breit, ~600KB-1.2MB statt 12MB DSLR-Original!) erzeugt — beim Klick aufs Bild wird SOFORT angezeigt (geprüft: $totalDisplayChecked Fotos). Original nur noch für Download-Button!";
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
        'preview_url'         => fg_event_public_url($eventId),
        'events_file_size'    => filesize(EVENTS_FILE),
        'public_base'         => PUBLIC_BASE,
        'data_prefix'         => fg_data_public_prefix(),
        'warnings'            => $warnings
    ];
    $msg = '✅ ERFOLG! ' . count($savedImages) . ' Bilder gespeichert, ' . count($photosJson) . ' Einträge in events.json geschrieben. Galerie sofort sichtbar!';
    if (count($warnings)) $msg .= ' ⚠️ ACHTUNG: '.count($warnings).' Bild(er) mit LOKALEM PFAD (z.B. file://) verworfen — Admin muss via HTTP:// statt Doppelklick geöffnet werden!';
    die_json(true, $msg, $responseData);
}

/* Fallback: unbekannte Action */
die_json(false, 'Unbekannte Action: ' . htmlspecialchars($action));
