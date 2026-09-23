<?php
/* =============================================================
 *  RV HARD – SESSION PRÜFUNG MIT NEUEM auth2.php
 *  NUR ZUM TESTEN! Danach SOFORT LÖSCHEN!
 * ============================================================= */
header('Content-Type: text/plain; charset=utf-8');
echo "=== RV HARD – auth2.php SESSION PRÜFUNG (All-Inkl Fix!) ===\n\n";

/* A. Ohne auth2 */
echo "--- Schritt 1: Aktueller Zustand ---\n";
echo "session_status()                => " . session_status() . " (";
switch(session_status()){
    case PHP_SESSION_DISABLED: echo "DISABLED"; break;
    case PHP_SESSION_NONE: echo "NONE (nicht gestartet)"; break;
    case PHP_SESSION_ACTIVE: echo "ACTIVE"; break;
}
echo ")\n";
echo "Server Port                     => " . ($_SERVER['SERVER_PORT'] ?? '?') . "\n";
echo "HTTPS Flag                      => " . (empty($_SERVER['HTTPS']) ? 'LEER (Proxy erwartet!)' : $_SERVER['HTTPS']) . "\n";

/* B. Auth2 laden */
echo "\n--- Schritt 2: Lade auth2.php (robust!) ---\n";
require_once(__DIR__ . '/auth2.php');

echo "Nach auth2 require: session_status => " . session_status() . "\n";
echo "FG2_SESSID (Konstante)          => " . (defined('FG2_SESSID') ? FG2_SESSID : '<nicht definiert! ❌>') . "\n";
echo "session_id()                    => " . session_id() . "\n";
echo "Session Name                    => " . session_name() . "\n";
if (!session_id()) echo "❌ SESSION LEER – Start fehlgeschlagen!\n";
else echo "✅ Session ID vorhanden!\n";

/* C. Setzen + Auslesen Test */
echo "\n--- Schritt 3: Session Wert Test ---\n";
$key = 'fg2_selftest_'.date('Ymd');
$_SESSION[$key] = bin2hex(random_bytes(8));
echo "Schreibe $key => $_SESSION[$key]  \n";
echo "Ausgelesen: " . (isset($_SESSION[$key]) ? $_SESSION[$key] : '<FEHLT!>') . "\n";

/* D. Cookie Parameter prüfen */
echo "\n--- Schritt 4: Cookie Parameter (session_get_cookie_params) ---\n";
$cp = session_get_cookie_params();
foreach ($cp as $k => $v) {
    if (is_bool($v)) $v = $v ? 'JA' : 'NEIN';
    echo "  " . str_pad($k,10) . " => " . $v . "\n";
}
echo "INFO: Secure=NEIN ist GEWOLLT bei All-Inkl Reverse Proxy! (HTTPS wird CDN gemacht!)";

/* E. Check User */
echo "\n\n--- Schritt 5: User aus config.php ---\n";
$all = fg2_get_users();
echo "Anzahl User: ".count($all)."\n";
foreach ($all as $u => $d) echo "  - $u  (Hash vorhanden: ".($d['hash'] ? 'JA' : 'NEIN').")\n";

echo "\n--- Schritt 6: Eingeloggt? (Erst nach Login JA!) ---\n";
echo "fg2_logged() => " . (fg2_logged() ? 'JA (User: '.fg2_user().')' : 'NEIN (erwartet, wenn nicht eingeloggt!)') . "\n";

echo "\n\n=== Nächste Schritte ===\n";
echo "1. Wenn Session ID vorhanden: ✅ Gut! Lösche _session-check-auth2.php + _debug-check.php vom Server!\n";
echo "2. Öffne /admin/ im Browser → Login Formular erscheint! → RVHardAdmin + Passwort eingeben!\n";
echo "3. Falls wieder 'Umleitungsfehler': BROWSER CACHE + SSL STATE LÖSCHEN (Anleitung in trae AI Nachricht!)\n";
echo "4. _debug-check.php und _session-check-auth2.php SOFORT LÖSCHEN vom Server nach Tests!\n";
