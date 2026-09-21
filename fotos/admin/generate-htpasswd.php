<?php
/* ============================================================
 * SBS GALERIE – ADMIN PASSWORT-GENERATOR (.htpasswd Creator)
 * Einmal ausführen für Passwort Hash!
 *
 * BENUTZUNG (1x):
 * 1. Setze unten $username und $password auf Deine Wünsche
 * 2. Lade diese Datei kurz auf deinen Webserver in /admin/
 * 3. Rufe sie im Browser auf: https://fotos.rv-hard.at/admin/generate-htpasswd.php
 * 4. Kopiere die angezeigte Zeile vollständig
 * 5. Erstelle damit die Datei admin/.htpasswd (genau diese Zeile!)
 * 6. LÖSCHE DIE DATEI generate-htpasswd.php SOFORT WIEDER VOM SERVER!
 *    (Ansonsten kann sie jeder ausführen!)
 * ============================================================
 */

/* ==== DEINE WERTE HIER EINTRAGEN ==== */
$username = 'rvhard';                 /* ← Dein Wunsch-Benutzername */
$password = 'ErsetzeMichSicher123!'; /* ← Dein sicheres Passwort! */
/* ==================================== */

header('Content-Type: text/plain; charset=utf-8');
echo "# KOPIERE FOLGENDE ZEILE IN DIE DATEI /admin/.htpasswd (genau 1 Zeile!):\n";
echo "\n";
echo $username . ':' . password_hash($password, PASSWORD_BCRYPT) . "\n";
echo "\n";
echo "# Prüfsumme / Info: Benutzer=" . $username . " / Länge Passwort=" . strlen($password) . "\n";
