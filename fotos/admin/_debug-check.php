<?php
/* =============================================================
 *  RV HARD: Debug Login Redirect Loop QUICK CHECK
 *  Diese Seite kurz hochladen, aufrufen, Output kopieren → LÖSCHEN!
 * ============================================================= */

header('Content-Type: text/plain; charset=utf-8');

$tests = [];
$tests['REQUEST_URI']    = $_SERVER['REQUEST_URI']     ?? '<unset>';
$tests['SCRIPT_NAME']    = $_SERVER['SCRIPT_NAME']     ?? '<unset>';
$tests['PHP_SELF']       = $_SERVER['PHP_SELF']        ?? '<unset>';
$tests['HTTP_HOST']      = $_SERVER['HTTP_HOST']       ?? '<unset>';
$tests['HTTPS']          = $_SERVER['HTTPS']           ?? '<unset>';
$tests['X_FORWARDED_PROTO'] = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '<unset>';
$tests['SERVER_PORT']    = $_SERVER['SERVER_PORT']     ?? '<unset>';
$tests['SERVER_NAME']    = $_SERVER['SERVER_NAME']     ?? '<unset>';
$tests['DOCUMENT_ROOT']  = $_SERVER['DOCUMENT_ROOT']   ?? '<unset>';
$tests['DIRNAME_FILE']   = realpath(__DIR__);
$tests['CONFIG_FILE_EXISTS (admin/config.php)'] = file_exists(__DIR__ . '/config.php') ? 'JA ✅' : 'NEIN ❌ (erstelle config.php!!)';

echo "=== RV HARD GALERIE ADMIN DEBUG\n\n";
foreach ($tests as $k => $v) {
    echo str_pad($k, 40) . ' => ' . $v . "\n";
}
echo "\n=== Test require auth.php:\n";
require_once(__DIR__ . '/auth.php');
echo "auth.php loaded OK\n";
echo "fg_is_admin_logged(): " . (fg_is_admin_logged() ? 'JA' : 'NEIN (erwartet!)') . "\n";
$users = fg_auth_get_users();
echo "Users in config: " . count($users) . "\n";
if (count($users) === 0) {
    echo "\n\n❌ ❌ ❌ KEINE BENUTZER GEFUNDEN!\n";
    echo "Lösung: Kopiere admin/config.example.php → admin/config.php und trage Passwort-Hash ein!\n";
} else {
    foreach ($users as $u => $d) {
        echo "  - User: " . $u . "  Hash vorhanden: " . (!empty($d['hash']) ? "JA (len=".strlen($d['hash']).")" : "NEIN ❌") . "\n";
    }
}
echo "\n=== ENDE → LÖSCHE DIESE DATEI JETZT SOFORT VOM SERVER! ===\n";
