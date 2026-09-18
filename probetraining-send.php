<?php
/* =========================================================
   RV HARD – PROBETRAINING ANFRAGE SENDER
   =========================================================
   ⚠️  HIER UNBEDINGT DIE RICHTIGE EMAIL ADRESSE EINTRAGEN!
   ========================================================= */
define('EMPFAENGER_EMAIL', 'vorstand@rv-hard.at'); // <-- HIER DEINE EMAIL!
define('ABSENDER_EMAIL',    'info@rv-hard.at');    // <-- MUSS AUF RV-HARD.AT ENDEN! (sonst Spam)
define('ABSENDER_NAME',     'RV Hard Website');
define('WEBSITE_NAME',      'RV Hard Probetraining');

/* =========================================================
   🧪 TEST-MODUS – zum Prüfen ob DIESE Datei auf dem Server läuft!
   Ruf im Browser auf: https://rv-hard.at/probetraining-send.php?test=rvhard
   ========================================================= */
if (isset($_GET['test']) && $_GET['test'] === 'rvhard') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>✅ Debug: probetraining-send.php AKTUELL geladen!</h1>";
    echo "<p><strong>Pfad dieser Datei:</strong> " . __FILE__ . "</p>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    echo "<p><strong>mail() Funktion verfügbar:</strong> " . (function_exists('mail') ? "✅ JA" : "❌ NEIN") . "</p>";
    echo "<hr>";
    echo "<h3>📧 Aktuell konfigurierte E-Mails:</h3>";
    echo "<p><strong>EMPFAENGER (wo hingeht):</strong> <span style='color:#ffc107;font-size:1.1rem;font-weight:bold;'>" . EMPFAENGER_EMAIL . "</span></p>";
    echo "<p><strong>ABSENDER:</strong> " . ABSENDER_NAME . " &lt;" . ABSENDER_EMAIL . "&gt;</p>";
    echo "<hr>";
    echo "<h3>📬 Test-Mail senden...</h3>";
    $testBetreff = "TEST: probetraining-send.php funktioniert!";
    $testNachricht = "Dies ist eine Test-Mail von deinem Probetraining-Formular.\n\nWenn du das hier bekommst, ist die aktuelle PHP-Datei aktiv und die E-Mail Konfiguration stimmt!\n\nGeneriert am: " . date('d.m.Y H:i:s');
    $testHeader  = "From: " . ABSENDER_NAME . " <" . ABSENDER_EMAIL . ">\r\n";
    $testHeader .= "Reply-To: " . ABSENDER_EMAIL . "\r\n";
    $testHeader .= "MIME-Version: 1.0\r\n";
    $testHeader .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail(EMPFAENGER_EMAIL, '=?UTF-8?B?'.base64_encode($testBetreff).'?=', $testNachricht, $testHeader, "-f " . ABSENDER_EMAIL);
    if ($ok) {
        echo "<p style='color:green;font-size:1.2rem;'>✅ Test-Mail wurde AKZEPTIERT vom Server! Prüfe dein Postfach: <strong>" . EMPFAENGER_EMAIL . "</strong> (auch Spam-Ordner!)</p>";
    } else {
        echo "<p style='color:red;'>❌ mail() Funktion hat FALSE zurückgegeben! Prüfe bei All-Inkl im KAS: <ul><li>Ist die ABSENDER E-Mail <strong>".ABSENDER_EMAIL."</strong> als Postfach oder Weiterleitung angelegt?</li><li>E-Mail Verwaltung → Postfächer → Neu anlegen, falls nicht!</li><li>All-Inkl erlaubt NUR ABSENDER-Emails die auch EXISTIEREN auf dem Space!</li></ul></p>";
    }
    echo "<hr><p><em>⚠️  Bitte ändere in der Datei die <strong>EMPFAENGER_EMAIL</strong> falls die oben angezeigte Adresse falsch ist, dann lade sie neu hoch!</em></p>";
    exit;
}

/* Normaler Formular Ablauf */
header('Content-Type: text/html; charset=utf-8');

$formular_ist_gesendet = ($_SERVER['REQUEST_METHOD'] === 'POST') ? true : false;

// AJAX-Erkennung: ZUSÄTZLICH zum X-Requested-With Header auch den Accept-Header auswerten!
$ist_xhr = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$accept_json = (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
$ist_ajax = ($ist_xhr || $accept_json);

if (!$formular_ist_gesendet) {
    if ($ist_ajax) { echo json_encode(['status' => 'error', 'ok' => false, 'msg' => 'Keine POST-Daten.']); exit; }
    header('Location: /probetraining.html');
    exit;
}

// ---------- HILFSFUNKTIONEN ----------
function clean_input($daten) {
    $daten = trim($daten ?? '');
    $daten = stripslashes($daten);
    $daten = htmlspecialchars($daten, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return $daten;
}
function pruefe_email($email) {
    return (filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
}

// ---------- EINGABEN EINLESEN ----------
$name_kind       = clean_input($_POST['name_kind']       ?? '');
$name_eltern     = clean_input($_POST['name_eltern']     ?? '');
$alter_kind      = clean_input($_POST['alter_kind']      ?? '');
$telefon         = clean_input($_POST['telefon']         ?? '');
$sparte          = clean_input($_POST['sparte']          ?? 'Keine Angabe');
$email           = clean_input($_POST['email']           ?? '');
$nachricht       = clean_input($_POST['nachricht']       ?? 'Keine weiteren Infos');
$datenschutz_ack = isset($_POST['datenschutz']) && $_POST['datenschutz'] === '1';

// ---------- VALIDIERUNG ----------
$fehler = [];
if ($name_kind === '' || mb_strlen($name_kind) < 2) $fehler[] = 'Bitte gib den Namen des Kindes ein (mind. 2 Zeichen).';
if ($name_eltern === '' || mb_strlen($name_eltern) < 2) $fehler[] = 'Bitte gib deinen Namen als Elternteil ein (mind. 2 Zeichen).';
if ($alter_kind === '' || !is_numeric($alter_kind) || intval($alter_kind) < 3 || intval($alter_kind) > 18) $fehler[] = 'Bitte gib ein gültiges Alter für das Kind ein (Zahl zwischen 3 und 18).';
if ($telefon === '' || mb_strlen(preg_replace('/[^0-9]/', '', $telefon)) < 6) $fehler[] = 'Bitte gib eine gültige Telefonnummer für den Rückruf ein (min. 6 Ziffern).';
if ($email !== '' && !pruefe_email($email)) $fehler[] = 'Die eingegebene E-Mail Adresse ist ungültig (wenn du sie angibst).';
if ($nachricht !== '' && mb_strlen($nachricht) > 5000) $fehler[] = 'Deine Nachricht ist zu lang (max. 5000 Zeichen).';
if (!$datenschutz_ack) $fehler[] = 'Bitte bestätige den Datenschutzhinweis, um die Anfrage zu senden.';

// ---------- ANTWORT SENDEN (AJAX ODER NORMAL) ----------
function antwort_senden($status, $html, $msg, $ist_ajax) {
    // $status = 'success' / 'error'
    // $msg = einfacher Text (für alte Frontend-Version mit data.ok / data.msg!)
    // $html = HTML-formatierter Text (für neue Versionen mit data.html!)
    if ($ist_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        $ok = ($status === 'success') ? true : false;
        echo json_encode([
            // --- Altes Format (wird von /training/probetraining.html verwendet!) ---
            'ok'  => $ok,
            'msg' => $msg,
            // --- Neues Format (für zukünftige Seiten im Root) ---
            'status' => $status,
            'html'   => $html
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Fallback: Normales HTML Template
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Antwort – '.WEBSITE_NAME.'</title><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<link rel="stylesheet" href="/assets/styles.css"></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1.5rem;background:#f8f9fa;">';
    echo '<div style="max-width:560px;width:100%;padding:2rem;background:white;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,0.08);font-family:Poppins,sans-serif;">';
    echo $html;
    echo '<p style="margin-top:1.75rem;"><a href="/training/probetraining.html" style="color:#ffc107;font-weight:600;text-decoration:none;">← Zurück zum Formular</a></p>';
    echo '</div></body></html>';
    exit;
}

// ---------- SCHLAGWORT-SPAM PRÜFUNG ----------
$spam_woerter = ['http://', 'https://', 'www.', '[url=', 'casino', 'viagra', 'kryptowährung', 'kredit', 'sex', 'porn'];
$spam_gefunden = false;
foreach ([$name_kind, $name_eltern, $telefon, $email, $nachricht] as $feld) {
    foreach ($spam_woerter as $sw) {
        if (stripos($feld, $sw) !== false) { $spam_gefunden = true; break 2; }
    }
}
if ($spam_gefunden) $fehler[] = 'Verdächtiger Inhalt erkannt – bitte keine Links oder unpassenden Text einfügen.';

if (count($fehler) > 0) {
    $html = '<div style="color:#dc3545;">';
    $html .= '<h3 style="color:#dc3545;margin-top:0;">⚠️ Fehler beim Senden</h3><ul style="padding-left:1.1rem;">';
    foreach ($fehler as $f) $html .= '<li style="margin-bottom:0.25rem;">' . $f . '</li>';
    $html .= '</ul></div>';
    $msg_plain = 'Fehler: ' . implode(' ', $fehler);
    antwort_senden('error', $html, $msg_plain, $ist_ajax);
}

// ---------- EMAIL VERSAND ----------
$empfaenger = EMPFAENGER_EMAIL;

$betreff = 'Neue Probetraining Anfrage: ' . $name_kind . ' (' . $alter_kind . ' J.)';
$betreff_mail = '=?UTF-8?B?' . base64_encode($betreff) . '?=';

$replyTo = !empty($email) ? $email : EMPFAENGER_EMAIL;
$replyToName = !empty($name_eltern) ? $name_eltern : $name_kind;

$nachricht_mail  = "Es gibt eine neue Probetraining-Anfrage über die Website!\n\n";
$nachricht_mail .= "Name Kind:      " . $name_kind . "\n";
$nachricht_mail .= "Name Eltern:    " . $name_eltern . "\n";
$nachricht_mail .= "Alter Kind:     " . $alter_kind . " Jahre\n";
$nachricht_mail .= "Telefon:        " . $telefon . "\n";
$nachricht_mail .= "E-Mail:         " . ($email !== '' ? $email : 'Nicht angegeben') . "\n";
$nachricht_mail .= "Sparte:         " . $sparte . "\n";
$nachricht_mail .= "Weitere Infos:  " . $nachricht . "\n\n";
$nachricht_mail .= "----------------------------------------------------------\n";
$nachricht_mail .= "IP-Adresse:     " . ($_SERVER['REMOTE_ADDR'] ?? 'unbekannt') . "\n";
$nachricht_mail .= "Zeitpunkt:      " . date('d.m.Y, H:i:s') . "\n";
$nachricht_mail .= "User-Agent:     " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unbekannt') . "\n";

$header  = "From: " . ABSENDER_NAME . " <" . ABSENDER_EMAIL . ">\r\n";
$header .= "Reply-To: " . $replyToName . " <" . $replyTo . ">\r\n";
$header .= "MIME-Version: 1.0\r\n";
$header .= "Content-Type: text/plain; charset=UTF-8\r\n";
$header .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$header .= "X-RVHARD-Form: Probetraining\r\n";

// 🔥 WICHTIG FÜR ALL-INKL.COM: Der "-f" Parameter zwingt den Absender!
$zusatz_parameter = "-f " . ABSENDER_EMAIL;

$versand_ok = @mail($empfaenger, $betreff_mail, $nachricht_mail, $header, $zusatz_parameter);

if (!$versand_ok) {
    $html = '<div style="color:#dc3545;">';
    $html .= '<h3 style="color:#dc3545;margin-top:0;">❌ Versand-Fehler</h3>';
    $html .= '<p>Leider konnte die E-Mail technisch nicht zugestellt werden. Mögliche Ursachen:</p>';
    $html .= '<ul style="padding-left:1.1rem;">';
    $html .= '<li>Die Absender-Adresse <strong>' . htmlspecialchars(ABSENDER_EMAIL) . '</strong> existiert auf deinem All-Inkl Space noch nicht als Postfach oder Weiterleitung.</li>';
    $html .= '<li>Prüfe im KAS unter <em>E-Mail verwalten</em>, ob die Adresse angelegt ist!</li>';
    $html .= '</ul>';
    $html .= '<p><small><strong>All-Inkl Fehler-Hinweis:</strong> Die PHP mail() Funktion gibt nur dann TRUE zurück, wenn der Server die Mail AKZEPTIERT. Das passiert nur bei gültigen, angelegten Absenderadressen auf deinem Hosting!</small></p>';
    $html .= '</div>';
    $msg_plain = 'Versand-Fehler: Bitte prüfe im KAS ob ' . ABSENDER_EMAIL . ' als Postfach existiert! Alternativ direkt an ' . EMPFAENGER_EMAIL . ' schreiben.';
    antwort_senden('error', $html, $msg_plain, $ist_ajax);
}

// ---------- ERFOLG ----------
$html  = '<div>';
$html .= '<h3 style="color:#198754;margin-top:0;">✅ Vielen Dank für deine Anfrage!</h3>';
$html .= '<p>Wir haben deine Probetraining-Anfrage für <strong>' . htmlspecialchars($name_kind) . '</strong> erfolgreich erhalten.</p>';
$html .= '<p style="padding:0.9rem 1.1rem;background:#fff8db;border-left:4px solid #ffc107;border-radius:8px;">';
$html .= '📞 Wir melden uns telefonisch in den nächsten 1–2 Werktagen unter der Nummer <strong>' . htmlspecialchars($telefon) . '</strong> zur Terminvereinbarung.';
$html .= '</p>';
$html .= '<p>Falls du Fragen hast, kannst du uns auch jederzeit per E-Mail an <a href="mailto:' . htmlspecialchars(EMPFAENGER_EMAIL) . '">' . htmlspecialchars(EMPFAENGER_EMAIL) . '</a> erreichen.</p>';
$html .= '</div>';
$msg_plain = 'Vielen Dank ' . $name_kind . '! Deine Anfrage wurde versendet. Wir rufen dich in den nächsten Werktagen unter ' . $telefon . ' an!';
antwort_senden('success', $html, $msg_plain, $ist_ajax);
