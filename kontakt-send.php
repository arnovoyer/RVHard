<?php
/* =========================================================
   RV HARD – KONTAKT FORMULAR SENDER
   =========================================================
   ⚠️  HIER UNBEDINGT DIE RICHTIGE EMAIL ADRESSE EINTRAGEN!
   ========================================================= */
define('EMPFAENGER_EMAIL', 'vorstand@rv-hard.at'); // <-- HIER DEINE EMAIL!
define('ABSENDER_EMAIL',    'info@rv-hard.at');    // <-- MUSS AUF RV-HARD.AT ENDEN! (sonst Spam)
define('ABSENDER_NAME',     'RV Hard Kontaktformular');
define('WEBSITE_NAME',      'RV Hard Kontakt');

/* =========================================================
   🧪 TEST-MODUS – zum Prüfen ob DIESE Datei auf dem Server läuft!
   Ruf im Browser auf: https://rv-hard.at/kontakt-send.php?test=rvhard
   ========================================================= */
if (isset($_GET['test']) && $_GET['test'] === 'rvhard') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1 style='color:#28a745;'>✅ Debug: /kontakt-send.php AKTUELL geladen!</h1>";
    echo "<p><strong>Pfad dieser Datei:</strong> " . __FILE__ . "</p>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    echo "<p><strong>mail() Funktion verfügbar:</strong> " . (function_exists('mail') ? "✅ JA" : "❌ NEIN") . "</p>";
    echo "<hr>";
    echo "<h3>📧 Aktuell konfigurierte E-Mails (in DIESER Kontakt-Datei!):</h3>";
    echo "<p><strong>EMPFAENGER (wo hingeht):</strong> <span style='color:#ffc107;font-size:1.1rem;font-weight:bold;background:#333;padding:0.2rem 0.4rem;border-radius:4px;'>" . EMPFAENGER_EMAIL . "</span></p>";
    echo "<p><strong>ABSENDER:</strong> " . ABSENDER_NAME . " &lt;" . ABSENDER_EMAIL . "&gt;</p>";
    echo "<p style='background:#fff3cd;padding:0.5rem;border-left:4px solid #ffc107;border-radius:4px;'><strong>⚠️  WICHTIG:</strong> Wenn oben die <em>FALSCHE</em> EMAIL steht, hast du diese Datei auf dem Server <strong>NOCH NICHT aktualisiert!</strong> Lade sie via KAS WebFTP hoch und prüfe sie erneut mit Bearbeiten!</p>";
    echo "<hr>";
    echo "<h3>📬 Test-Mail senden...</h3>";
    $testBetreff = "TEST: /kontakt-send.php funktioniert!";
    $testNachricht = "Dies ist eine Test-Mail von deinem KONTAKT-FORMULAR.\n\nWenn du das hier bekommst, ist die AKTUELLE PHP-Datei aktiv und die E-Mail Konfiguration stimmt!\n\nGeneriert am: " . date('d.m.Y H:i:s');
    $testHeader  = "From: " . ABSENDER_NAME . " <" . ABSENDER_EMAIL . ">\r\n";
    $testHeader .= "Reply-To: " . ABSENDER_EMAIL . "\r\n";
    $testHeader .= "MIME-Version: 1.0\r\n";
    $testHeader .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail(EMPFAENGER_EMAIL, '=?UTF-8?B?'.base64_encode($testBetreff).'?=', $testNachricht, $testHeader, "-f " . ABSENDER_EMAIL);
    if ($ok) {
        echo "<p style='color:green;font-size:1.2rem;'>✅ Test-Mail wurde AKZEPTIERT vom Server! Prüfe JETZT dein Postfach: <strong>" . EMPFAENGER_EMAIL . "</strong> (auch Spam-Ordner!)</p>";
        echo "<p><strong>Aktion:</strong> Wenn die Mail hier ankommt, aber die Formular-Mails immer noch falsch sind → <em>Cache im Browser löschen!</em> oder im Inkognito testen!</p>";
    } else {
        echo "<p style='color:red;'>❌ mail() Funktion hat FALSE zurückgegeben! Prüfe bei All-Inkl im KAS: <ul><li>Ist die ABSENDER E-Mail <strong>".ABSENDER_EMAIL."</strong> als Postfach oder Weiterleitung angelegt?</li><li>E-Mail Verwaltung → Postfächer → Neu anlegen, falls nicht!</li><li>All-Inkl erlaubt NUR ABSENDER-Emails die auch EXISTIEREN auf dem Space!</li></ul></p>";
    }
    echo "<hr><p><em>⚠️  Bitte ändere in der Datei die <strong>EMPFAENGER_EMAIL</strong> falls die oben angezeigte Adresse falsch ist, dann lade sie neu hoch via KAS WebFTP!</em></p>";
    exit;
}

/* Normaler Formular Ablauf */
header('Content-Type: text/html; charset=utf-8');

$formular_ist_gesendet = ($_SERVER['REQUEST_METHOD'] === 'POST') ? true : false;

// AJAX-Erkennung: doppelt robust!
$ist_xhr = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$accept_json = (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
$ist_ajax = ($ist_xhr || $accept_json);

if (!$formular_ist_gesendet) {
    if ($ist_ajax) { echo json_encode(['status' => 'error', 'ok' => false, 'msg' => 'Keine POST-Daten.']); exit; }
    header('Location: /kontakt.html');
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

// ---------- EINGABEN EINLESEN (andere Felder als Probetraining!) ----------
$name            = clean_input($_POST['name']            ?? '');
$email           = clean_input($_POST['email']           ?? '');
$telefon         = clean_input($_POST['telefon']         ?? '');
$betreff         = clean_input($_POST['betreff']         ?? 'Allgemeine Anfrage');
$nachricht       = clean_input($_POST['nachricht']       ?? '');
$datenschutz_ack = isset($_POST['datenschutz']) && $_POST['datenschutz'] === '1';

// ---------- VALIDIERUNG ----------
$fehler = [];
if ($name === '' || mb_strlen($name) < 2) $fehler[] = 'Bitte gib deinen vollständigen Namen ein (mind. 2 Zeichen).';
if ($email === '' || !pruefe_email($email)) $fehler[] = 'Bitte gib eine gültige E-Mail Adresse ein, damit wir dir antworten können.';
if ($betreff === '' || mb_strlen($betreff) < 3) $fehler[] = 'Bitte wähle einen Betreff oder gib ein Anliegen ein (mind. 3 Zeichen).';
if ($nachricht === '' || mb_strlen($nachricht) < 10) $fehler[] = 'Bitte gib eine Nachricht ein (mind. 10 Zeichen).';
if ($nachricht !== '' && mb_strlen($nachricht) > 8000) $fehler[] = 'Deine Nachricht ist zu lang (max. 8000 Zeichen).';
if ($telefon !== '' && mb_strlen(preg_replace('/[^0-9]/', '', $telefon)) < 6) $fehler[] = 'Die eingegebene Telefonnummer ist zu kurz (wenn du sie angibst, min. 6 Ziffern).';
if (!$datenschutz_ack) $fehler[] = 'Bitte klicke die Datenschutz-Checkbox an, um die Einwilligung zur Datenverarbeitung zu geben.';

// ---------- ANTWORT SENDEN (beide Formate kompatibel) ----------
function antwort_senden($status, $html, $msg, $ist_ajax) {
    if ($ist_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        $ok = ($status === 'success') ? true : false;
        echo json_encode([
            'ok'  => $ok,
            'msg' => $msg,
            'status' => $status,
            'html'   => $html
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Antwort – '.WEBSITE_NAME.'</title><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<link rel="stylesheet" href="/assets/style.css"></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1.5rem;background:#f8f9fa;">';
    echo '<div style="max-width:620px;width:100%;padding:2rem;background:white;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,0.08);font-family:Poppins,sans-serif;">';
    echo $html;
    echo '<p style="margin-top:1.75rem;"><a href="/kontakt.html" style="color:#ffc107;font-weight:600;text-decoration:none;">← Zurück zur Kontakt-Seite</a></p>';
    echo '</div></body></html>';
    exit;
}

// ---------- SCHLAGWORT-SPAM PRÜFUNG ----------
$spam_woerter = ['http://', 'https://', 'www.', '[url=', 'casino', 'viagra', 'kryptowährung', 'kredit', 'sex', 'porn'];
$spam_gefunden = false;
foreach ([$name, $email, $telefon, $betreff, $nachricht] as $feld) {
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

$mail_betreff = 'Neue Kontaktanfrage: ' . $betreff . ' (von: ' . $name . ')';
$betreff_mail = '=?UTF-8?B?' . base64_encode($mail_betreff) . '?=';

$replyTo = $email;
$replyToName = $name;

$nachricht_mail  = "Neue Kontaktanfrage über die Website!\n";
$nachricht_mail .= "Formular: /kontakt.html (allgemeines Kontaktformular)\n\n";
$nachricht_mail .= "Name:           " . $name . "\n";
$nachricht_mail .= "E-Mail:         " . $email . "\n";
$nachricht_mail .= "Telefon:        " . ($telefon !== '' ? $telefon : 'Nicht angegeben') . "\n";
$nachricht_mail .= "Betreff:        " . $betreff . "\n";
$nachricht_mail .= "\n------------------ NACHRICHT ------------------\n";
$nachricht_mail .= $nachricht . "\n";
$nachricht_mail .= "------------------------------------------------\n\n";
$nachricht_mail .= "IP-Adresse:     " . ($_SERVER['REMOTE_ADDR'] ?? 'unbekannt') . "\n";
$nachricht_mail .= "Zeitpunkt:      " . date('d.m.Y, H:i:s') . "\n";
$nachricht_mail .= "User-Agent:     " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unbekannt') . "\n";

$header  = "From: " . ABSENDER_NAME . " <" . ABSENDER_EMAIL . ">\r\n";
$header .= "Reply-To: " . $replyToName . " <" . $replyTo . ">\r\n";
$header .= "MIME-Version: 1.0\r\n";
$header .= "Content-Type: text/plain; charset=UTF-8\r\n";
$header .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$header .= "X-RVHARD-Form: Kontakt-Allgemein\r\n";

// All-Inkl Fix: -f Parameter
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
    $html .= '<p>Alternativ: Schreibe uns direkt an <a href="mailto:'.htmlspecialchars(EMPFAENGER_EMAIL).'">'.htmlspecialchars(EMPFAENGER_EMAIL).'</a>.</p>';
    $html .= '</div>';
    $msg_plain = 'Versand-Fehler! Bitte direkt an ' . EMPFAENGER_EMAIL . ' schreiben.';
    antwort_senden('error', $html, $msg_plain, $ist_ajax);
}

// ---------- ERFOLG ----------
$html  = '<div>';
$html .= '<h3 style="color:#198754;margin-top:0;">✅ Vielen Dank für deine Nachricht, ' . htmlspecialchars($name) . '!</h3>';
$html .= '<p>Wir haben deine Kontaktanfrage zum Thema <strong>' . htmlspecialchars($betreff) . '</strong> erfolgreich erhalten.</p>';
$html .= '<p style="padding:0.9rem 1.1rem;background:#fff8db;border-left:4px solid #ffc107;border-radius:8px;">';
$html .= '✉️ Wir melden uns per E-Mail zurück an <strong>' . htmlspecialchars($email) . '</strong> – meist innerhalb von 1–3 Werktagen.';
if ($telefon !== '') {
    $html .= '<br>Falls schneller Kontakt nötig ist, rufen wir dich auch gern unter ' . htmlspecialchars($telefon) . ' an.';
}
$html .= '</p>';
$html .= '</div>';
$msg_plain = 'Vielen Dank ' . $name . '! Deine Kontaktanfrage wurde versendet. Wir melden uns per E-Mail an ' . $email . ' zurück!';
antwort_senden('success', $html, $msg_plain, $ist_ajax);
