<?php
/* ============================================================
 *  RV HARD – STARTNUMMERN IMPORT (import_bibs.php)
 * ============================================================
 *  Liest eine per detect_bibs.py erzeugte startnummern.json
 *  und aktualisiert die bibNumbers der Fotos in events.json.
 *
 *  Aufruf:  fotos/tools/import_bibs.php  (im Browser)
 * ============================================================ */

@ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ---- AUTH (gleiches Login wie Galerie-Admin) ---- */
session_start();

// Fallback 1: Eigenes Session-Login (z.B. vom bilder/admin)
if (!empty($_SESSION['bilder_admin_logged_in'])) {
    $loggedIn = true;
} else {
    // Fallback 2: Galerie-Admin auth2.php (falls auf fotos.rv-hard.at)
    $authCandidates = [
        __DIR__ . '/../admin/auth2.php',
        '/www/htdocs/w007ee49/fotos.rv-hard.at/admin/auth2.php',
    ];
    $authPath = null;
    foreach ($authCandidates as $c) {
        if (file_exists($c)) { $authPath = $c; break; }
    }
    if ($authPath) {
        require_once($authPath);
        $loggedIn = function_exists('fg2_logged') && fg2_logged();
    } else {
        $loggedIn = false;
    }
}

if (!$loggedIn) {
    http_response_code(403);
    echo '<h1>Zugriff verweigert</h1><p>Bitte zuerst im Galerie-Admin anmelden.</p>';
    exit;
}

/* ---- PFAD ZUR EVENTS.JSON ---- */
$eventsJsonPath = __DIR__ . '/../data/events.json';
if (!file_exists($eventsJsonPath)) {
    die('events.json nicht gefunden unter: ' . htmlspecialchars($eventsJsonPath));
}

$events = json_decode(file_get_contents($eventsJsonPath), true);
if (!is_array($events)) {
    die('events.json konnte nicht gelesen werden (JSON-Fehler).');
}

/* ---- VARIABLEN ---- */
$msg = '';
$msgType = '';
$selectedEvent = $_GET['event'] ?? '';
$importedData = null;

/* ---- JSON HOCHLADEN / IMPORTIEREN ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Option A: Datei hochladen */
    if ($action === 'upload_json' && isset($_FILES['jsonfile']) && $_FILES['jsonfile']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['jsonfile']['tmp_name'];
        $content = file_get_contents($tmp);
        $importedData = json_decode($content, true);
        if (!is_array($importedData)) {
            $msg = 'Ungültige JSON-Datei hochgeladen.';
            $msgType = 'error';
        } else {
            $msg = 'JSON geladen: ' . count($importedData) . ' Einträge. Bitte Ziel-Event wählen und importieren.';
            $msgType = 'success';
            $_SESSION['bib_import_data'] = $importedData;
        }
    }

    /* Option B: JSON direkt einfügen (Textarea) */
    elseif ($action === 'paste_json') {
        $raw = $_POST['jsontext'] ?? '';
        $importedData = json_decode($raw, true);
        if (!is_array($importedData)) {
            $msg = 'Eingefügter Text ist kein gültiges JSON.';
            $msgType = 'error';
        } else {
            $msg = 'JSON geparst: ' . count($importedData) . ' Einträge. Bitte Ziel-Event wählen und importieren.';
            $msgType = 'success';
            $_SESSION['bib_import_data'] = $importedData;
        }
    }

    /* Import durchführen */
    elseif ($action === 'do_import') {
        $importedData = $_SESSION['bib_import_data'] ?? null;
        $eventId = $_POST['event_id'] ?? '';

        if (!$importedData || !$eventId) {
            $msg = 'Keine Daten oder kein Event ausgewählt.';
            $msgType = 'error';
        } else {
            /* Event finden */
            $eventIndex = null;
            foreach ($events as $i => $ev) {
                if (($ev['id'] ?? '') === $eventId) { $eventIndex = $i; break; }
            }

            if ($eventIndex === null) {
                $msg = 'Event nicht gefunden.';
                $msgType = 'error';
            } else {
                $photos = $events[$eventIndex]['photos'] ?? [];
                $updated = 0;
                $matched = 0;
                $totalBibs = 0;
                $unmatched = [];

                /* Backup der events.json erstellen */
                $backupPath = $eventsJsonPath . '.bak_' . date('Ymd_His');
                copy($eventsJsonPath, $backupPath);

                /* Import-Logik: Dateiname (basename) match */
                foreach ($photos as $pi => &$photo) {
                    $src = $photo['src'] ?? '';
                    $fname = basename($src);

                    if (isset($importedData[$fname])) {
                        $matched++;
                        $bibs = $importedData[$fname];
                        if (!is_array($bibs)) {
                            $bibs = array_filter(preg_split('/[,\s]+/', (string)$bibs));
                        }
                        $bibs = array_values(array_unique(array_map('strval', $bibs)));

                        if ($bibs !== ($photo['bibNumbers'] ?? [])) {
                            $photo['bibNumbers'] = $bibs;
                            $updated++;
                            $totalBibs += count($bibs);
                        }
                    }
                }
                unset($photo);

                $events[$eventIndex]['photos'] = $photos;

                /* Speichern */
                $json = json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (file_put_contents($eventsJsonPath, $json)) {
                    $msg = "Import erfolgreich!<br>"
                         . "Event: <strong>" . htmlspecialchars($events[$eventIndex]['name']) . "</strong><br>"
                         . "Bilder in Event: " . count($photos) . "<br>"
                         . "Dateinamen aus JSON gefunden: " . count($importedData) . "<br>"
                         . "Davon gematcht: <strong>" . $matched . "</strong><br>"
                         . "Aktualisiert: <strong>" . $updated . "</strong> Bilder<br>"
                         . "Startnummern gesamt: " . $totalBibs . "<br>"
                         . "Backup: " . htmlspecialchars(basename($backupPath));
                    $msgType = 'success';
                    unset($_SESSION['bib_import_data']);
                } else {
                    $msg = 'Fehler beim Speichern von events.json (Schreibrechte?).';
                    $msgType = 'error';
                }
            }
        }
    }
}

/* ---- HTML AUSGABE ---- */
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Startnummern Import | RV Hard Galerie</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Poppins', system-ui, sans-serif; background: #f4f4f4; padding: 1.5rem; max-width: 900px; margin: 0 auto; }
    h1 { font-size: 1.5rem; margin-bottom: 1rem; color: #1a1a1a; }
    h2 { font-size: 1.1rem; margin: 1.5rem 0 0.8rem; color: #1a1a1a; }
    .card { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
    .msg { padding: 0.9rem 1.2rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: 0.92rem; line-height: 1.6; }
    .msg.success { background: #d4edda; color: #155724; }
    .msg.error   { background: #f8d7da; color: #721c24; }
    .form-row { margin-bottom: 1rem; }
    label { display: block; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.9rem; }
    input[type="file"], select, textarea {
      width: 100%; padding: 0.7rem 0.9rem; border: 2px solid #ddd; border-radius: 8px;
      font-family: inherit; font-size: 0.95rem;
    }
    textarea { min-height: 160px; font-family: 'Courier New', monospace; font-size: 0.85rem; }
    button {
      padding: 0.8rem 1.5rem; background: #ffc400; color: #1a1a1a; border: none;
      border-radius: 8px; font-weight: 700; cursor: pointer; font-family: inherit; font-size: 0.95rem;
    }
    button:hover { background: #f5b301; }
    .hint { color: #888; font-size: 0.8rem; margin-top: 0.3rem; }
    .or { text-align: center; color: #aaa; margin: 1rem 0; font-weight: 600; }
    .stats { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .stat { background: #f0f0f0; padding: 0.6rem 1rem; border-radius: 8px; font-size: 0.85rem; }
    .stat strong { color: #ffc400; font-size: 1.1rem; }
  </style>
</head>
<body>

<h1><i class="fa-solid fa-route" style="color:#ffc400"></i> RV Hard – Startnummern Import</h1>

<?php if ($msg): ?>
  <div class="msg <?= $msgType ?>"><?= $msg ?></div>
<?php endif; ?>

<?php
/* Wenn Daten in der Session sind, Import-Formular anzeigen */
$pendingData = $_SESSION['bib_import_data'] ?? null;
if ($pendingData && empty($_POST['action'])):
?>
  <div class="card">
    <h2>JSON-Daten geladen – jetzt Event wählen</h2>
    <div class="stats">
      <div class="stat">Einträge: <strong><?= count($pendingData) ?></strong></div>
      <div class="stat">Davon mit #N: <strong><?= count(array_filter($pendingData, fn($v)=>!empty($v))) ?></strong></div>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="do_import">
      <div class="form-row">
        <label for="event_id">Ziel-Event wählen</label>
        <select id="event_id" name="event_id" required>
          <option value="">-- Bitte Event wählen --</option>
          <?php foreach ($events as $ev):
            if (empty($ev['id']) || empty($ev['photos'])) continue;
            $cnt = count($ev['photos']);
          ?>
            <option value="<?= htmlspecialchars($ev['id']) ?>" <?= $selectedEvent===$ev['id']?'selected':'' ?>>
              <?= htmlspecialchars($ev['name']) ?> (<?= $cnt ?> Bilder)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Nur Events mit vorhandenen Bildern werden angezeigt.</div>
      </div>
      <button type="submit"><i class="fa-solid fa-file-import"></i> Startnummern importieren</button>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h2>1. startnummern.json hochladen</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="upload_json">
    <div class="form-row">
      <label for="jsonfile">JSON-Datei auswählen</label>
      <input type="file" id="jsonfile" name="jsonfile" accept=".json,application/json" required>
      <div class="hint">Die Datei wurde von detect_bibs.py erzeugt (Format: {"bild.jpg": ["123", "456"]})</div>
    </div>
    <button type="submit"><i class="fa-solid fa-upload"></i> JSON laden</button>
  </form>

  <div class="or">— oder —</div>

  <h2>JSON direkt einfügen</h2>
  <form method="post">
    <input type="hidden" name="action" value="paste_json">
    <div class="form-row">
      <label for="jsontext">JSON-Inhalt</label>
      <textarea id="jsontext" name="jsontext" placeholder='{"bild_001.jpg": ["123"], "bild_002.jpg": ["45", "678"]}' required></textarea>
    </div>
    <button type="submit"><i class="fa-solid fa-paste"></i> JSON parsen</button>
  </form>
</div>

<div class="card">
  <h2>So funktioniert's</h2>
  <ol style="margin-left:1.2rem; line-height:1.8; font-size:0.9rem;">
    <li>Lokal auf dem PC Bilder scannen: <code>python detect_bibs.py /pfad/zu/bildern</code></li>
    <li>Es entsteht <code>startnummern.json</code></li>
    <li>Hier die JSON hochladen oder einfügen</li>
    <li>Ziel-Event wählen → Importieren</li>
    <li>Fertig! Die Startnummern sind in der Galerie durchsuchbar.</li>
  </ol>
  <div class="hint" style="margin-top:1rem">
    <i class="fa-solid fa-circle-info"></i> Vor jedem Import wird automatisch ein Backup von events.json erstellt (.bak_DATUM).
  </div>
</div>

</body>
</html>
