<?php
/* =========================================================
   RV HARD – BESTELLFORMULAR VEREINSBEKLEIDUNG
   =========================================================
   ⚠️  HIER DIE RICHTIGE EMPFÄNGER-ADRESSE EINTRAGEN!
   ========================================================= */
define('EMPFAENGER_EMAIL', 'vorstand@rv-hard.at'); // <-- HIER DIE EMAIL DES BEKLEIDUNGSBEAUFTRAGTEN!
define('ABSENDER_EMAIL',    'info@rv-hard.at');    // <-- MUSS AUF RV-HARD.AT ENDEN! (sonst Spam)
define('ABSENDER_NAME',     'RV Hard Bestellformular');
define('WEBSITE_NAME',      'RV Hard Bekleidung');

/* Erlaubte Artikel & Größen – schützt vor manipulierten Formulardaten */
$ARTIKEL_LISTE = [
    'Trikot',
    'Hose (Trägerhose)',
    'MTB-Hose',
    'Tri-Anzug Kurz',
    'Tri-Anzug Lang',
    'Laufshirt / T-Shirt',
    'Weste',
    'Jacke',
];
$GROESSEN_LISTE = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

/* =========================================================
   🧪 TEST-MODUS: https://rv-hard.at/verein/bekleidung-send.php?test=rvhard
   ========================================================= */
if (isset($_GET['test']) && $_GET['test'] === 'rvhard') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1 style='color:#28a745;'>✅ Debug: /verein/bekleidung-send.php AKTUELL geladen!</h1>";
    echo "<p><strong>Pfad dieser Datei:</strong> " . __FILE__ . "</p>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    echo "<p><strong>mail() Funktion verfügbar:</strong> " . (function_exists('mail') ? "✅ JA" : "❌ NEIN") . "</p>";
    echo "<p><strong>EMPFAENGER (wo hingeht):</strong> " . EMPFAENGER_EMAIL . "</p>";
    echo "<p><strong>ABSENDER:</strong> " . ABSENDER_NAME . " &lt;" . ABSENDER_EMAIL . "&gt;</p>";
    $testBetreff   = "TEST: /verein/bekleidung-send.php funktioniert!";
    $testNachricht = "Test-Mail vom BESTELLFORMULAR.\n\nGeneriert am: " . date('d.m.Y H:i:s');
    $testHeader    = "From: " . ABSENDER_NAME . " <" . ABSENDER_EMAIL . ">\r\n";
    $testHeader   .= "Reply-To: " . ABSENDER_EMAIL . "\r\n";
    $testHeader   .= "MIME-Version: 1.0\r\n";
    $testHeader   .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail(EMPFAENGER_EMAIL, '=?UTF-8?B?' . base64_encode($testBetreff) . '?=', $testNachricht, $testHeader, "-f " . ABSENDER_EMAIL);
    echo $ok
        ? "<p style='color:green;font-size:1.2rem;'>✅ Test-Mail wurde AKZEPTIERT! Prüfe das Postfach: <strong>" . EMPFAENGER_EMAIL . "</strong></p>"
        : "<p style='color:red;'>❌ mail() hat FALSE zurückgegeben! Prüfe im KAS, ob die Absenderadresse <strong>" . ABSENDER_EMAIL . "</strong> angelegt ist.</p>";
    exit;
}

/* ---------- NORMALER FORMULAR-ABLAUF ---------- */
header('Content-Type: text/html; charset=utf-8');

$formular_ist_gesendet = ($_SERVER['REQUEST_METHOD'] === 'POST');
$ist_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
         || (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!$formular_ist_gesendet) {
    if ($ist_ajax) { echo json_encode(['status' => 'error', 'ok' => false, 'msg' => 'Keine POST-Daten.']); exit; }
    header('Location: /verein/bekleidung');
    exit;
}

/* ---------- HILFSFUNKTIONEN ---------- */
function clean_input($daten) {
    $daten = trim($daten ?? '');
    $daten = stripslashes($daten);
    return htmlspecialchars($daten, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pruefe_email($email) {
    return (filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
}

/* ---------- EINGABEN EINLESEN ---------- */
$name            = clean_input($_POST['name']      ?? '');
$email           = clean_input($_POST['email']     ?? '');
$telefon         = clean_input($_POST['telefon']   ?? '');
$bemerkung       = clean_input($_POST['bemerkung'] ?? '');
$datenschutz_ack = isset($_POST['datenschutz']) && $_POST['datenschutz'] === '1';

$artikel_arr = (isset($_POST['artikel']) && is_array($_POST['artikel'])) ? $_POST['artikel'] : [];
$groesse_arr = (isset($_POST['groesse']) && is_array($_POST['groesse'])) ? $_POST['groesse'] : [];
$menge_arr   = (isset($_POST['menge'])   && is_array($_POST['menge']))   ? $_POST['menge']   : [];

/* ---------- VALIDIERUNG ---------- */
$fehler = [];
if ($name === '' || mb_strlen($name) < 2) $fehler[] = 'Bitte gib deinen vollständigen Namen ein (mind. 2 Zeichen).';
if ($email === '' || !pruefe_email($email)) $fehler[] = 'Bitte gib eine gültige E-Mail Adresse ein, damit wir dir antworten können.';
if ($telefon !== '' && mb_strlen(preg_replace('/[^0-9]/', '', $telefon)) < 6) $fehler[] = 'Die eingegebene Telefonnummer ist zu kurz (wenn du sie angibst, min. 6 Ziffern).';
if (mb_strlen($bemerkung) > 2000) $fehler[] = 'Die Bemerkung ist zu lang (max. 2000 Zeichen).';
if (!$datenschutz_ack) $fehler[] = 'Bitte klicke die Datenschutz-Checkbox an, um die Einwilligung zur Datenverarbeitung zu geben.';

/* Bestellpositionen prüfen und aufbereiten */
$positionen = [];
$anzahl = count($artikel_arr);
for ($i = 0; $i < $anzahl; $i++) {
    $art = clean_input($artikel_arr[$i] ?? '');
    $gro = clean_input($groesse_arr[$i] ?? '');
    $men = clean_input($menge_arr[$i] ?? '');

    if ($art === '' && $gro === '' && $men === '') continue; // leere Zeile ignorieren

    if (!in_array($art, $ARTIKEL_LISTE, true)) {
        $fehler[] = 'Ungültiger Artikel in Zeile ' . ($i + 1) . '.';
        continue;
    }
    if (!in_array($gro, $GROESSEN_LISTE, true)) {
        $fehler[] = 'Ungültige Größe in Zeile ' . ($i + 1) . '.';
        continue;
    }
    $menge_int = (int)$men;
    if ($menge_int < 1 || $menge_int > 50) {
        $fehler[] = 'Ungültige Menge in Zeile ' . ($i + 1) . ' (1–50).';
        continue;
    }
    $positionen[] = ['artikel' => $art, 'groesse' => $gro, 'menge' => $menge_int];
}
if (count($positionen) === 0) $fehler[] = 'Bitte wähle mindestens einen Artikel mit Größe und Menge aus.';

/* ---------- SCHLAGWORT-SPAM PRÜFUNG ---------- */
$spam_woerter = ['http://', 'https://', 'www.', '[url=', 'casino', 'viagra', 'kryptowährung', 'sex', 'porn'];
foreach ([$name, $email, $telefon, $bemerkung] as $feld) {
    foreach ($spam_woerter as $sw) {
        if (stripos($feld, $sw) !== false) { $fehler[] = 'Verdächtiger Inhalt erkannt – bitte keine Links einfügen.'; break 2; }
    }
}

/* ---------- ANTWORT SENDEN ---------- */
function antwort_senden($status, $html, $msg, $ist_ajax) {
    if ($ist_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'     => ($status === 'success'),
            'msg'    => $msg,
            'status' => $status,
            'html'   => $html
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Antwort – ' . WEBSITE_NAME . '</title><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<link rel="stylesheet" href="/assets/style.css"></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1.5rem;background:#f8f9fa;">';
    echo '<div style="max-width:620px;width:100%;padding:2rem;background:white;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,0.08);font-family:Poppins,sans-serif;">';
    echo $html;
    echo '<p style="margin-top:1.75rem;"><a href="/verein/bekleidung" style="color:#ffc107;font-weight:600;text-decoration:none;">← Zurück zur Bekleidungs-Seite</a></p>';
    echo '</div></body></html>';
    exit;
}

if (count($fehler) > 0) {
    $html  = '<div style="color:#dc3545;">';
    $html .= '<h3 style="color:#dc3545;margin-top:0;">⚠️ Fehler beim Senden</h3><ul style="padding-left:1.1rem;">';
    foreach ($fehler as $f) $html .= '<li style="margin-bottom:0.25rem;">' . $f . '</li>';
    $html .= '</ul></div>';
    antwort_senden('error', $html, 'Fehler: ' . implode(' ', $fehler), $ist_ajax);
}

/* ---------- E-MAIL ZUSAMMENBAUEN ---------- */
$positions_text = '';
$gesamt = 0;
foreach ($positionen as $p) {
    $positions_text .= '- ' . $p['artikel'] . ' | Größe: ' . $p['groesse'] . ' | Menge: ' . $p['menge'] . "\n";
    $gesamt += $p['menge'];
}

$mail_betreff = 'Neue Bekleidungsbestellung (' . count($positionen) . ' Position' . (count($positionen) === 1 ? '' : 'en') . ') von ' . $name;
$betreff_mail = '=?UTF-8?B?' . base64_encode($mail_betreff) . '?=';

$nachricht_mail  = "Neue Bestellung über die Website!\n";
$nachricht_mail .= "Formular: /verein/bekleidung (Vereinsbekleidung)\n\n";
$nachricht_mail .= "Name:            " . $name . "\n";
$nachricht_mail .= "E-Mail:          " . $email . "\n";
$nachricht_mail .= "Telefon:         " . ($telefon !== '' ? $telefon : 'Nicht angegeben') . "\n";
$nachricht_mail .= "\n------------------ BESTELLUNG ------------------\n";
$nachricht_mail .= $positions_text;
$nachricht_mail .= "------------------------------------------------\n";
$nachricht_mail .= "Gesamt (Stück):  " . $gesamt . "\n\n";
$nachricht_mail .= "Bemerkung:\n" . ($bemerkung !== '' ? $bemerkung : '(keine)') . "\n\n";
$nachricht_mail .= "IP-Adresse:      " . ($_SERVER['REMOTE_ADDR'] ?? 'unbekannt') . "\n";
$nachricht_mail .= "Zeitpunkt:       " . date('d.m.Y, H:i:s') . "\n";
$nachricht_mail .= "User-Agent:      " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unbekannt') . "\n";

$header  = "From: " . ABSENDER_NAME . " <" . ABSENDER_EMAIL . ">\r\n";
$header .= "Reply-To: " . $name . " <" . $email . ">\r\n";
$header .= "MIME-Version: 1.0\r\n";
$header .= "Content-Type: text/plain; charset=UTF-8\r\n";
$header .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$header .= "X-RVHARD-Form: Bekleidung-Bestellung\r\n";

$versand_ok = @mail(EMPFAENGER_EMAIL, $betreff_mail, $nachricht_mail, $header, "-f " . ABSENDER_EMAIL);

if (!$versand_ok) {
    $html  = '<div style="color:#dc3545;">';
    $html .= '<h3 style="color:#dc3545;margin-top:0;">❌ Versand-Fehler</h3>';
    $html .= '<p>Leider konnte die E-Mail technisch nicht zugestellt werden. Bitte schreibe uns direkt an <a href="mailto:' . htmlspecialchars(EMPFAENGER_EMAIL) . '">' . htmlspecialchars(EMPFAENGER_EMAIL) . '</a>.</p>';
    $html .= '</div>';
    antwort_senden('error', $html, 'Versand-Fehler! Bitte direkt an ' . EMPFAENGER_EMAIL . ' schreiben.', $ist_ajax);
}

/* ---------- ERFOLG ---------- */
$html  = '<div>';
$html .= '<h3 style="color:#198754;margin-top:0;">✅ Danke für deine Bestellung, ' . htmlspecialchars($name) . '!</h3>';
$html .= '<p>Wir haben deine Bestellung mit ' . count($positionen) . ' Position(en) erhalten:</p>';
$html .= '<ul style="padding-left:1.1rem;">';
foreach ($positionen as $p) {
    $html .= '<li>' . htmlspecialchars($p['artikel']) . ' – Größe ' . htmlspecialchars($p['groesse']) . ' × ' . $p['menge'] . '</li>';
}
$html .= '</ul>';
$html .= '<p style="padding:0.9rem 1.1rem;background:#fff8db;border-left:4px solid #ffc107;border-radius:8px;">';
$html .= '✉️ Wir melden uns per E-Mail an <strong>' . htmlspecialchars($email) . '</strong> mit Verfügbarkeit, Preis und Abholung.';
$html .= '</p></div>';
antwort_senden('success', $html, 'Danke ' . $name . '! Deine Bestellung wurde versendet. Wir melden uns per E-Mail an ' . $email . '.', $ist_ajax);
