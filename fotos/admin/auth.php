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
    /* ============================================================
     *  LOOP-SCHUTZ (AM ANFANG VON ALLEM!)
     *  Wenn die aufrufende Seite selbst login.php / logout.php etc. ist,
     *  dann AUF KEINEN FALL weiterleiten!
     *  (auth.php wird ja VON login.php included! Wenn es dann auf
     *   login.php umleitet → Endlos-Loop! 🚨)
     * ============================================================ */
    $NO_REDIRECT_FILES = ['login.php','logout.php','generate-password-hash.php','_debug-check.php'];
    foreach (['REQUEST_URI','SCRIPT_NAME','PHP_SELF','SCRIPT_FILENAME','PATH_TRANSLATED','ORIG_PATH_INFO'] as $k) {
        if (empty($_SERVER[$k]) || !is_string($_SERVER[$k])) continue;
        $tmp = explode('?', $_SERVER[$k], 2)[0];
        $b = basename($tmp);
        if (in_array(strtolower($b), array_map('strtolower', $NO_REDIRECT_FILES), true)) {
            /* Schon auf einer Auth-Seite → kein redirect! */
            return fg_is_admin_logged();
        }
    }
    /* Letztes Fallback: Debug-Backtrace prüfen, inkludierende Datei holen */
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (!empty($bt[0]['file'])) {
        $incFile = basename($bt[0]['file']);
        if (in_array(strtolower($incFile), array_map('strtolower', $NO_REDIRECT_FILES), true)) {
            return fg_is_admin_logged();
        }
    }
    if (!empty($bt[1]['file'])) {
        $callerFile = basename($bt[1]['file']);
        if (in_array(strtolower($callerFile), array_map('strtolower', $NO_REDIRECT_FILES), true)) {
            return fg_is_admin_logged();
        }
    }

    if (!fg_is_admin_logged()) {
        if ($redirectToLogin && !headers_sent()) {
            $requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
            $qsPos = is_string($requestUri) ? strpos($requestUri, '?') : false;
            $herePath = ($qsPos !== false) ? substr($requestUri, 0, $qsPos) : $requestUri;
            if ($herePath === '' || $herePath === false) $herePath = '/admin/';
            $hereEncoded = rawurlencode($requestUri !== '' ? $requestUri : $herePath);

            /* Sicherheitshalber hier nochmal: SELBST AUF login.php? Dann STOP */
            $selfFile = basename(parse_url($herePath, PHP_URL_PATH) ?: '');
            if (in_array(strtolower($selfFile), array_map('strtolower', $NO_REDIRECT_FILES), true)) {
                return false;
            }
            foreach (['SCRIPT_NAME','PHP_SELF'] as $k) {
                if (!empty($_SERVER[$k]) && is_string($_SERVER[$k])) {
                    $b = basename(explode('?', $_SERVER[$k], 2)[0]);
                    if (in_array(strtolower($b), array_map('strtolower', $NO_REDIRECT_FILES), true)) {
                        return false;
                    }
                }
            }

            $sep = strpos('./login.php', '?') === false ? '?' : '&';
            header('Location: ./login.php' . $sep . 'redirect=' . $hereEncoded);
            exit;
        }
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Nicht eingeloggt – bitte anmelden.',
            'login_url' => './login.php'
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
