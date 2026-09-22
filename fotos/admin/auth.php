<?php
/* =============================================================
 *  SBS GALERIE ADMIN – SHARED SESSION AUTH (SEITE-ÜBERGREIFEND)
 *  Einbinden in upload.php, login.php etc. mit require_once(__DIR__ . '/auth.php');
 *  Danach überall fg_is_admin_logged() prüfen!
 * ============================================================= */

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (headers_sent($file, $line)) {
        error_log('[Galerie Auth] Session konnte nicht gestartet werden: Headers already sent at ' . $file . ':' . $line);
    } else {
        /* Sichere Cookie-Einstellungen (DSGVO konform, keine Drittanbindung) */
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        session_set_cookie_params([
            'lifetime' => 0,           /* bis Browser geschlossen wird, oder fg_remember() 30 Tage */
            'path'     => '/',         /* überall auf Domain verfügbar (Subdomain übergreifend!) */
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,        /* KANN NICHT VON JAVASCRIPT GELESEN WERDEN! */
            'samesite' => 'Strict'
        ]);
        @session_name('RVHARD_FOTO_ADMIN');
        @session_start();
    }
}

/* ---- Pfad zur config.php mit Benutzerdaten ---- */
define('FG_AUTH_CONFIG_FILE', __DIR__ . '/config.php');

/* ---- Standard-Benutzer, falls config.php fehlt (NUR als letztes Fallback!) ---- */
$FG_AUTH_DEFAULT_USERS = [
    'RVHardAdmin' => [
        /* ↓ Wenn leer → MUSS config.php existieren! ↓ */
        'hash' => '',
        'displayName' => 'Foto-Galerie Administrator'
    ]
];

/* Benutzer aus config.php holen, fallback auf Defaults */
function fg_auth_get_users() {
    global $FG_AUTH_DEFAULT_USERS;
    $users = [];
    if (file_exists(FG_AUTH_CONFIG_FILE)) {
        $cfg = include FG_AUTH_CONFIG_FILE;
        if (is_array($cfg) && !empty($cfg['users']) && is_array($cfg['users'])) {
            foreach ($cfg['users'] as $u => $data) {
                if (is_string($u) && is_array($data) && !empty($data['hash'])) {
                    $users[$u] = [
                        'hash'        => (string)$data['hash'],
                        'displayName' => isset($data['displayName']) ? (string)$data['displayName'] : $u
                    ];
                }
            }
        }
    }
    if (!$users) {
        /* Fallback auf Defaults – aber nur wenn hash gesetzt! */
        foreach ($FG_AUTH_DEFAULT_USERS as $u => $data) {
            if (!empty($data['hash'])) $users[$u] = $data;
        }
    }
    return $users;
}

function fg_auth_username_exists($u) {
    $all = fg_auth_get_users();
    return isset($all[$u]);
}

function fg_is_admin_logged() {
    if (empty($_SESSION['fg_admin_user']) || !is_string($_SESSION['fg_admin_user'])) return false;
    /* Doppelt check: User existiert in Config? */
    return fg_auth_username_exists($_SESSION['fg_admin_user']);
}

function fg_admin_current_user() {
    return fg_is_admin_logged() ? $_SESSION['fg_admin_user'] : null;
}

function fg_admin_require_login($redirectToLogin = true) {
    if (!fg_is_admin_logged()) {
        if ($redirectToLogin && !headers_sent()) {
            /* Weiterleiten zu login.php (selbes Verzeichnis wie diese auth.php!) */
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $here = rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin/');
            header('Location: ' . $protocol . '://' . $host . dirname(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) . '/login.php?redirect=' . $here);
            exit;
        }
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Nicht eingeloggt – bitte anmelden.',
            'login_url' => dirname(parse_url($_SERVER['REQUEST_URI'] ?? '/admin/', PHP_URL_PATH)) . '/login.php'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    return true;
}

/* ============= LOGIN / LOGOUT HILFSFUNKTIONEN ============= */
function fg_admin_try_login($username, $password, &$errMsg = '') {
    if (!is_string($username) || !is_string($password)) { $errMsg = 'Ungültige Eingabe'; return false; }
    $users = fg_auth_get_users();
    if (!$users) { $errMsg = 'Keine Benutzer konfiguriert – erstelle /admin/config.php!'; return false; }
    if (!isset($users[$username])) { $errMsg = 'Benutzer/Passwort ungültig.'; return false; }
    $u = $users[$username];
    if (empty($u['hash'])) { $errMsg = 'Benutzer hat kein Passwort in config.php!'; return false; }
    if (!function_exists('password_verify')) { $errMsg = 'PHP Version zu alt (kein password_verify).'; return false; }
    if (!password_verify($password, $u['hash'])) { $errMsg = 'Benutzer/Passwort ungültig.'; return false; }

    /* Erfolg! Session regenerieren (Sicherheit) */
    if (!headers_sent()) {
        @session_regenerate_id(true);
    }
    $_SESSION['fg_admin_user'] = $username;
    $_SESSION['fg_admin_at']   = time();
    if (!empty($u['displayName'])) $_SESSION['fg_admin_name'] = $u['displayName'];
    /* Anti-CSRF Token */
    if (empty($_SESSION['fg_csrf_token'])) {
        $_SESSION['fg_csrf_token'] = bin2hex(random_bytes(24));
    }
    return true;
}

function fg_admin_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
}

/* CSRF Token Check (für POST Requests) */
function fg_csrf_token() {
    if (empty($_SESSION['fg_csrf_token'])) {
        $_SESSION['fg_csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['fg_csrf_token'];
}
function fg_csrf_check($tokenOrPostKey = 'csrf') {
    $t = is_string($tokenOrPostKey) && (strpos($tokenOrPostKey, '=') !== false || strlen($tokenOrPostKey) > 20)
        ? $tokenOrPostKey
        : (isset($_POST[$tokenOrPostKey]) ? $_POST[$tokenOrPostKey] : '');
    if (empty($_SESSION['fg_csrf_token']) || !is_string($t) || !hash_equals($_SESSION['fg_csrf_token'], $t)) {
        return false;
    }
    return true;
}
