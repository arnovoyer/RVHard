<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/* ============================================================
 *  RV HARD – PROBETRAINING ANFRAGE E-MAIL HANDLER
 *  PHP 7.4 kompatibel (KEINE str_starts_with/str_ends_with!)
 *  Nimmt Formular POST an, validiert, sendet E-Mail via mail()
 *  Antwortet IMMER als JSON (für AJAX im Frontend)
 * ============================================================ */

/* ============ KONFIGURATION – HIER E-MAIL ADRESSE EINTRAGEN! ============ */
define('EMPFAENGER_EMAIL', 'vorstand@rv-hard.at');   /* HIER EURE E-MAIL ADRESSE REIN! */
define('ABSENDER_EMAIL',  'no-reply@rv-hard.at');    /* Absender (Domain muss auf Server zeigen!) */
define('ABSENDER_NAME',   'RV Hard Webseite');
define('BETREFF_PREFIX',  '[Probetraining] ');

/* Spamschutz – Mindestzeit zwischen Formularaufruf und Submit (in Sekunden) */
define('MIN_FORM_ZEIT', 2);
/* Maximale Anzahl Zeilen im Nachrichtentext (gegen Header Injection) */
define('MAX_HEADER_LINES', 0);

/* ============== HILFSFUNKTIONEN ============== */
function die_json($ok, $msg, $extra = []) {
    echo json_encode(array_merge([
        'ok'  => $ok,
        'msg' => $msg
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* PHP 7.4 Kompatibel: Prüft ob String mit etwas anfängt */
function rv_str_startet_mit($haystack, $needle) {
    $len = strlen($needle);
    if ($len === 0) return true;
    return substr($haystack, 0, $len) === $needle;
}

/* PHP 7.4 Kompatibel: Prüft ob String mit etwas endet */
function rv_str_endet_mit($haystack, $needle) {
    $lenNeedle = strlen($needle);
    $lenHay    = strlen($haystack);
    if ($lenNeedle === 0) return true;
    if ($lenNeedle > $lenHay) return false;
    return substr($haystack, -$lenNeedle) === $needle;
}

/* Bereinigt String von Zeilenumbrüchen (für E-Mail Header Felder!) */
function rv_header_safe($str) {
    return preg_replace('/[\r\n\0]+/', ' ', trim($str));
}

/* Einfache E-Mail Validierung (PHP 7.4 filter_var ist überall verfügbar) */
function rv_ist_email($str) {
    return filter_var(trim($str), FILTER_VALIDATE_EMAIL) !== false;
}

/* Einfache Telefon Validierung: Erlaubt +, Zahlen, Leerzeichen, Bindestriche, Klammern */
function rv_ist_telefon($str) {
    $s = trim($str);
    if ($s === '') return false;
    return (bool)preg_match('/^[0-9+\s\-()\/]{7,30}$/', $s);
}

/* ============== POST REQUEST AUSLESEN ============== */
$istPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

/* ===== ACTION: test / ping (Mail Funktionalität prüfen) ===== */
$action = $istPost ? ($_POST['_action'] ?? '') : ($_GET['_action'] ?? '');
if ($action === 'ping' || $action === 'test') {
    $mailVerfuegbar = function_exists('mail');
    $sendTest = ($action === 'test');
    $testResult = null;
    if ($sendTest && $mailVerfuegbar) {
        $testBetreff = rv_header_safe(BETREFF_PREFIX . ' TEST – E-Mail Server Prüfung');
        $testBody    = "Das ist eine Test-E-Mail von der RV Hard Webseite.\n\nWenn du das siehst, funktioniert der PHP Mail Versand!\nDatum: " . date('d.m.Y H:i:s') . "\nServer: " . php_uname('n');
        $testHeader  = [];
        $testHeader[] = 'From: ' . ABSENDER_NAME . ' <' . ABSENDER_EMAIL . '>';
        $testHeader[] = 'Reply-To: ' . EMPFAENGER_EMAIL;
        $testHeader[] = 'Content-Type: text/plain; charset=utf-8';
        $testHeader[] = 'X-Mailer: PHP/' . phpversion();
        $testResult = @mail(EMPFAENGER_EMAIL, $testBetreff, $testBody, implode("\r\n", $testHeader), '-f ' . ABSENDER_EMAIL);
    }
    die_json(true, 'Probetraining Mail-Handler erreichbar', [
        'php_version'       => PHP_VERSION,
        'mail_available'    => $mailVerfuegbar,
        'empfaenger'        => EMPFAENGER_EMAIL,
        'absender'          => ABSENDER_EMAIL,
        'test_mail_sent'    => $testResult,
        'info'              => 'Testen: ?_action=test schickt eine Test-Mail an ' . EMPFAENGER_EMAIL
    ]);
}

/* ===== NORMALER SUBMIT: NUR POST erlauben! ===== */
if (!$istPost) {
    http_response_code(405);
    die_json(false, 'Ungültige Anfrage. Bitte das Formular auf der Probetraining-Seite verwenden.');
}

/* ===== 1) SPAMSCHUTZ: Honey Pot Feld – wenn ausgefüllt, automatische Spam Ablehnung! ===== */
if (!empty($_POST['_honey'])) {
    http_response_code(400);
    die_json(false, 'Ungültige Anfrage.');
}

/* ===== 2) PFLICHTFELDER AUSLESEN & VALIDIEREN ===== */
$nameKind   = trim($_POST['name_kind']   ?? '');
$nameEltern = trim($_POST['name_eltern'] ?? '');
$alterKind  = trim($_POST['alter_kind']  ?? '');
$telefon    = trim($_POST['telefon']     ?? '');
$sparte     = trim($_POST['sparte']      ?? '');
$email      = trim($_POST['email']       ?? '');
$nachricht  = trim($_POST['nachricht']   ?? '');

/* Pflichtfelder Prüfung */
$fehler = [];
if ($nameKind === '' || strlen($nameKind) < 2)   $fehler[] = 'Name des Kindes fehlt.';
if ($nameEltern === '' || strlen($nameEltern) < 2) $fehler[] = 'Name des Elternteils fehlt.';
if ($alterKind === '' || !ctype_digit($alterKind)) {
    $fehler[] = 'Alter des Kindes fehlt oder ist ungültig.';
} else {
    $alterInt = (int)$alterKind;
    if ($alterInt < 3 || $alterInt > 19) $fehler[] = 'Alter des Kindes muss zwischen 3 und 18 Jahren liegen.';
}
if (!rv_ist_telefon($telefon)) $fehler[] = 'Telefonnummer fehlt oder ist ungültig (erlaubt: Zahlen, +, Leerzeichen, Bindestriche).';

/* Optionale Felder: Länge beschränken + Header-Injection-Schutz */
if (strlen($nameKind) > 100)   $nameKind   = substr($nameKind, 0, 100);
if (strlen($nameEltern) > 100) $nameEltern = substr($nameEltern, 0, 100);
if (strlen($sparte) > 60)      $sparte     = substr($sparte, 0, 60);
if (strlen($email) > 120)      $email      = substr($email, 0, 120);
if (strlen($nachricht) > 1500) $nachricht  = substr($nachricht, 0, 1500);
if ($email !== '' && !rv_ist_email($email)) {
    $fehler[] = 'E-Mail Adresse ist ungültig.';
    $email = '';
}

/* Header Injection Schutz: Entferne Zeilenumbrüche aus allen Header-tauglichen Feldern */
$nameKind   = rv_header_safe($nameKind);
$nameEltern = rv_header_safe($nameEltern);
$sparte     = rv_header_safe($sparte);
$email      = rv_header_safe($email);

/* Fehler werfen falls etwas ungültig */
if (!empty($fehler)) {
    http_response_code(400);
    die_json(false, implode(' ', $fehler));
}

/* ===== 3) E-MAIL INHALT BAUEN ===== */
$betreff = BETREFF_PREFIX . 'Anfrage von ' . $nameKind . ' (' . $alterKind . ' J.)';
if ($sparte !== '') $betreff .= ' – ' . $sparte;
$betreff = rv_header_safe($betreff);

$replyTo = ($email !== '') ? $email : EMPFAENGER_EMAIL;
$replyToName = ($email !== '') ? $nameEltern : ABSENDER_NAME;

$body  = "=== NEUE PROBETRAINING ANFRAGE ===\n";
$body .= "Eingang: " . date('d.m.Y H:i:s') . "\n";
$body .= "---------------------------------\n\n";
$body .= "Name Kind:        " . $nameKind . "\n";
$body .= "Alter Kind:       " . $alterKind . " Jahre\n";
$body .= "Name Elternteil:  " . $nameEltern . "\n";
$body .= "Telefon Rückruf:  " . $telefon . "\n";
if ($email !== '') {
$body .= "E-Mail:           " . $email . "\n";
}
if ($sparte !== '') {
$body .= "Gewünschte Sparte: " . $sparte . "\n";
}
$body .= "\n";
if ($nachricht !== '') {
    $body .= "--- Weitere Infos ---\n";
    $body .= $nachricht . "\n\n";
}
$body .= "---------------------------------\n";
$body .= "Gesendet von RV Hard Webseite (" . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unbekannt') . ")\n";
$body .= "IP: " . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unbekannt') . "\n";

/* ===== 4) E-MAIL HEADER ===== */
$headers   = [];
$headers[] = 'From: ' . ABSENDER_NAME . ' <' . ABSENDER_EMAIL . '>';
$headers[] = 'Reply-To: ' . $replyToName . ' <' . $replyTo . '>';
$headers[] = 'Content-Type: text/plain; charset=utf-8';
$headers[] = 'X-Mailer: RV-Hard-Webseite-Formular';
$headers[] = 'MIME-Version: 1.0';
$headersStr = implode("\r\n", $headers);

/* ===== 5) E-MAIL SENDEN ===== */
if (!function_exists('mail')) {
    http_response_code(500);
    die_json(false, 'PHP mail() Funktion ist auf diesem Server leider nicht verfügbar. Lösung: Im <probetraining.html> die form action auf https://formsubmit.co/' . EMPFAENGER_EMAIL . ' ändern (FormSubmit kostenlos nutzen!).');
}

/* Zusätzliche Parameter: -f erzwingt Envelope-From = weniger Spam Markierung */
$zusatzParams = '-f ' . escapeshellarg(ABSENDER_EMAIL);

/* Versuch Senden */
$erfolg = false;
$letzterFehler = '';
try {
    $erfolg = mail(EMPFAENGER_EMAIL, $betreff, $body, $headersStr, $zusatzParams);
} catch (Throwable $e) {
    $letzterFehler = $e->getMessage();
}

/* Fallback: Ohne zusatzParams nochmal versuchen (manche Provider blockieren -f) */
if (!$erfolg) {
    try {
        $erfolg = mail(EMPFAENGER_EMAIL, $betreff, $body, $headersStr);
    } catch (Throwable $e2) {
        $letzterFehler = $letzterFehler . ' | ' . $e2->getMessage();
    }
}

/* ===== 6) ANTWORTEN ===== */
if ($erfolg) {
    die_json(true, 'Vielen Dank, ' . htmlspecialchars($nameKind) . '! Deine Anfrage wurde erfolgreich an uns versendet. Wir melden uns telefonisch bei ' . htmlspecialchars($nameEltern) . ' unter ' . htmlspecialchars($telefon) . ' zurück.');
} else {
    http_response_code(500);
    $fallbackMsg  = 'Technischer Fehler beim Senden der E-Mail. ';
    $fallbackMsg .= 'Bitte schreibe uns direkt an: ' . EMPFAENGER_EMAIL;
    $fallbackMsg .= ' oder rufe uns an. Betreff: Probetraining ' . $nameKind;
    if (isset($_SERVER['SERVER_ADMIN']) && $_SERVER['SERVER_ADMIN'] !== '') {
        $fallbackMsg .= ' (Server Admin: ' . $_SERVER['SERVER_ADMIN'] . ')';
    }
    die_json(false, $fallbackMsg, [
        'debug' => (WP_DEBUG ?? false) ? $letzterFehler : null,
        'fallback_email' => EMPFAENGER_EMAIL
    ]);
}
