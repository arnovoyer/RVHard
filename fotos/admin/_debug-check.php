<?php
/* =================================================================
 *  RV HARD GALERIE – DEBUG CHECK (100% STANDALONE! KEINE INCLUDES!)
 *  - Keine auth/auth2 benötigt!
 *  - Kann auch aufgerufen werden wenn Rewrites falsch sind.
 *  - NACH DEM DEBUGGEN SOFORT VOM SERVER LÖSCHEN!
 * ================================================================= */

header('Content-Type: text/plain; charset=utf-8');
echo "=== RV HARD GALERIE DEBUG ===\n\n";

$keys = [
    'REQUEST_URI','SCRIPT_NAME','SCRIPT_FILENAME','PHP_SELF','QUERY_STRING',
    'HTTP_HOST','HTTP_X_FORWARDED_PROTO','HTTP_X_FORWARDED_SSL',
    'HTTPS','SERVER_PORT','SERVER_NAME','DOCUMENT_ROOT','SERVER_SOFTWARE'
];
foreach ($keys as $k) {
    $v = isset($_SERVER[$k]) ? $_SERVER[$k] : '<nicht gesetzt>';
    if (is_string($v)) $v = trim($v);
    echo str_pad($k, 30) . ' => ' . (is_scalar($v) ? $v : print_r($v, true)) . "\n";
}
echo "\n--- Pfade ---\n";
echo 'DIRNAME (__DIR__)                => ' . __DIR__ . "\n";
echo 'realpath(__DIR__)                => ' . realpath(__DIR__) . "\n";

echo "\n--- Dateien im Admin ---\n";
$files = ['auth.php','auth2.php','index.php','index.html','login.php','logout.php','upload.php','config.php','config.example.php','.htaccess','generate-password-hash.php'];
foreach ($files as $f) {
    $p = __DIR__ . '/' . $f;
    $ex = file_exists($p);
    $sz = $ex ? filesize($p) : 0;
    echo '  ' . str_pad($f, 28) . ' => ' . ($ex ? "DA ($sz B)" : '❌ FEHLT') . "\n";
}

echo "\n--- INI / Session ---\n";
echo 'session.save_path                => ' . (ini_get('session.save_path') ?: '<default>') . "\n";
echo 'session.gc_maxlifetime           => ' . (ini_get('session.gc_maxlifetime') ?: '?') . "\n";
echo 'display_errors                   => ' . (ini_get('display_errors') ? 'AN' : 'AUS') . "\n";
echo 'password_verify verfügbar        => ' . (function_exists('password_verify') ? 'JA ✅' : 'NEIN ❌') . "\n";

echo "\n--- config.php PRÜFUNG ---\n";
$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) {
    echo "❌ FEHLT admin/config.php! Kopiere config.example.php -> config.php!\n";
} else {
    $users = [];
    $inc = @include $cfg;
    if (is_array($inc) && !empty($inc['users'])) $users = $inc['users'];
    echo "Anzahl Benutzer in config: " . count($users) . "\n";
    foreach ($users as $u => $d) {
        $hashOk = !empty($d['hash']) && str_starts_with((string)$d['hash'], '$2y$');
        echo "  - User: $u   Hash(bcrypt): " . ($hashOk ? 'JA ✅' : 'NEIN/UNGÜLTIG ❌  [erwarte $2y$...]') . "\n";
        echo "    Hash Länge: " . strlen((string)($d['hash'] ?? '')) . "\n";
    }
    if (!$users) {
        echo "   ❌ KEIN BENUTZER GEFUNDEN! Benötige mindestens 'RVHardAdmin' mit bcrypt Hash!\n";
    }
}

echo "\n--- SESSION TEST ---\n";
$sessionOk = false;
try {
    if (session_status() === PHP_SESSION_NONE) {
        $sn = 'RVH_TEST_' . bin2hex(random_bytes(4));
        session_name($sn);
        $started = session_start();
    } else {
        $started = true;
    }
    $sessionOk = $started;
    $_SESSION['_fg2_debug_test'] = 42;
    echo "Session gestartet: " . ($started ? "JA ✅ (id:".session_id().")" : "NEIN ❌") . "\n";
    echo "Session Cookie Name: " . session_name() . "\n";
    $p = session_get_cookie_params();
    echo "Cookie Pfad: {$p['path']} | Domain: " . ($p['domain'] ?: '<auto>') . " | Secure: " . ($p['secure'] ? 'JA' : 'NEIN') . " | SameSite: " . ($p['samesite'] ?? '') . "\n";
} catch (\Throwable $e) {
    echo "Session ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- HTTP Status Test ---\n";
echo "Diese Datei ist direkt erreichbar: ✅ (sonst hättest du diesen Text NICHT gesehen!)\n";
echo "Wenn DU DIESEN TEXT SIEHST → funktioniert /admin/_debug-check.php JETZT!\n";

echo "\n=== ENDE (bitte LÖSCHE diese Datei jetzt sofort vom Server!) ===\n";
