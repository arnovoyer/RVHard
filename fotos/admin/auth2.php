<?php
/* ===================================================================
 *  RV HARD GALERIE – STANDALONE ADMIN AUTH (KEINE REDIRECTS!)
 *  100% OHNE loop!
 * ===================================================================
 *  SPEZIAL-ANPASSUNG FÜR ALL-INKL REVERSE PROXY:
 *  - HTTPS läuft über CDN → zum Apache kommt HTTP:80 an (HTTPS leer!)
 *  - Deshalb: Cookie 'secure' Flag auf FALSE setzen (sonst geht das
 *    Cookie bei manchen Proxy-Konstellationen verloren!)
 *  - Wir schützen es stattdessen mit: httponly=true + samesite=Lax
 * =================================================================== */

/* AM ALLERANFANG: Output Buffering AN!
 * → Verhindert "headers already sent" Fehler wenn UTF-8 BOM/Leerzeichen in Dateien schlafen! */
if (!ob_get_level()) @ob_start();

/* --- Session starten (MAX ROBUST!) --- */
$sessStarted = false;
if (session_status() === PHP_SESSION_ACTIVE) {
    $sessStarted = true;
} else {
    /* 1) Save Path sicherstellen (All-Inkl CGI-FPM benötigt gültigen Pfad!) */
    $sp = ini_get('session.save_path');
    if (!$sp || !is_dir($sp) || !is_writable($sp)) {
        /* Fallback: Server-tmp oder eigenes data/tmp Verzeichnis! */
        $alt1 = '/tmp';
        $alt2 = __DIR__ . '/../data/tmp';
        if (@is_dir($alt1) && @is_writable($alt1)) {
            @ini_set('session.save_path', $alt1);
        } elseif (@is_dir(__DIR__ . '/../data')) {
            if (!@is_dir($alt2)) @mkdir($alt2, 0700, true);
            if (@is_dir($alt2)) @ini_set('session.save_path', $alt2);
        }
    }
    /* 2) Cookie Parameter – WICHTIG: secure = FALSE bei Reverse Proxy! */
    if (!headers_sent()) {
        /* Use SameSite via options array for PHP 7.3+, and fallback via ini_set */
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',  /* LEER lassen! Browser nutzt dann aktuelle Domain automatisch! (Subdomain sicher!) */
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        if (PHP_VERSION_ID >= 70300) {
            @session_set_cookie_params($cookieParams);
        } else {
            @session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'] . '; SameSite=' . $cookieParams['samesite'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
        }
    } else {
        /* Fallback, falls schon Output → via header() manuell SameSite (PHP Alt) */
        @ini_set('session.cookie_httponly','1');
        @ini_set('session.use_only_cookies','1');
        @ini_set('session.use_trans_sid','0');
        @ini_set('session.cookie_samesite','Lax');
        @ini_set('session.cookie_secure','0');
    }
    /* 3) Session Name setzen und starten! */
    @session_name('RVH2');
    if (!headers_sent() || (function_exists('headers_list') && count(headers_list()) < 5)) {
        $sessStarted = @session_start();
    } else {
        /* Letztes Fallback: Trotzdem starten, falls Headers schon unterwegs! */
        $sessStarted = @session_start();
    }
    /* 4) Falls FALSE: TMP Ordner im Galerie data/ anlegen! */
    if (!$sessStarted) {
        $altDir = __DIR__ . '/../data/sessions';
        if (!@is_dir($altDir)) {
            @mkdir($altDir, 0700, true);
            @file_put_contents($altDir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        if (@is_dir($altDir)) {
            @session_save_path($altDir);
            $sessStarted = @session_start();
        }
    }
}
/* Session-ID für Debug loggen (falls nötig) */
if ($sessStarted && !defined('FG2_SESSID')) define('FG2_SESSID', session_id());

/* --- Benutzer aus config.php holen (selber Ordner!) --- */
if (!defined('FG2_CONFIG')) {
    define('FG2_CONFIG', __DIR__ . '/config.php');
}

function fg2_get_users() {
    $users = [];
    if (file_exists(FG2_CONFIG)) {
        $c = include FG2_CONFIG;
        if (is_array($c) && !empty($c['users']) && is_array($c['users'])) {
            foreach ($c['users'] as $u => $d) {
                if (is_string($u) && is_array($d) && !empty($d['hash'])) $users[$u] = $d;
            }
        }
    }
    /* Fallback: falls config.php leer/fehlt, aber Default Hash gesetzt sein sollte: */
    if (!$users) {
        $users = [];
    }
    return $users;
}

function fg2_logged() {
    if (empty($_SESSION['fg2_user']) || !is_string($_SESSION['fg2_user'])) return false;
    $u = $_SESSION['fg2_user'];
    $all = fg2_get_users();
    return isset($all[$u]);
}

function fg2_user() {
    return fg2_logged() ? $_SESSION['fg2_user'] : null;
}

function fg2_csrf_token() {
    if (empty($_SESSION['fg2_csrf'])) $_SESSION['fg2_csrf'] = bin2hex(random_bytes(20));
    return $_SESSION['fg2_csrf'];
}
function fg2_csrf_check($k = 'csrf') {
    $t = isset($_POST[$k]) ? $_POST[$k] : '';
    return (!empty($_SESSION['fg2_csrf']) && is_string($t) && hash_equals($_SESSION['fg2_csrf'], $t));
}

/* --- Fehlermeldung / Login Formular – MINIMAL! (Nur das Nötigste) --- */
function fg2_render_login_page($msg = '', $msgType = 'err') {
    $logoUrl = '../assets/img/logo/RV_Hard_Logo.webp';
    $csrfH = '<input type="hidden" name="csrf" value="' . htmlspecialchars(fg2_csrf_token()) . '">';
    $configExists = file_exists(FG2_CONFIG);
    $users = fg2_get_users();
    $year = date('Y');
    ?>
<!doctype html>
<html lang="de" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Anmelden · RV Hard</title>
<script>
(function () {
  try {
    var t = localStorage.getItem('rv-hard-theme');
    document.documentElement.setAttribute('data-theme', (t === 'dark' || t === 'light') ? t : 'light');
  } catch (e) {}
  var h = location.hostname;
  var isMain = (h === 'rv-hard.at' || h === 'www.rv-hard.at');
  var base = isMain ? '/assets/' : 'https://rv-hard.at/assets/';
  document.write('<link rel="stylesheet" href="' + base + 'admin.css?v=20261103">');
  /* Lokale Poppins-Fonts (ersetzt Google Fonts) */
  var fBase = isMain ? '/fotos/assets/' : '/assets/';
  document.write('<link rel="stylesheet" href="' + fBase + 'fonts.css?v=20261111">');
})();
</script>
<link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css?v=20261111">
</head>
<body class="a-login-page">

<main class="a-login">
  <form method="post" novalidate autocomplete="on">
    <?= $csrfH ?>

    <div class="a-login__logo">
      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="RV Hard" onerror="this.style.display='none';this.parentNode.innerHTML='<i class=\'fa-solid fa-lock\' style=\'color:#121417;font-size:26px\'></i>'">
    </div>
    <h1>Anmelden</h1>

    <?php if (!$configExists): ?>
      <div class="a-msg a-msg--warn" style="margin-bottom:16px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong>Fehlt: config.php</strong><br>Kopiere <code>config.example.php</code> nach <code>config.php</code>.</div>
      </div>
    <?php elseif (!$users): ?>
      <div class="a-msg a-msg--warn" style="margin-bottom:16px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong>Kein Benutzer</strong><br>Füge <code>RVHardAdmin</code> + bcrypt Hash in <code>config.php</code> ein.</div>
      </div>
    <?php endif ?>

    <?php if ($msg && $msgType == 'err'): ?>
      <div class="a-msg a-msg--err" style="margin-bottom:16px;"><i class="fa-solid fa-circle-exclamation"></i><div><?= htmlspecialchars($msg) ?></div></div>
    <?php endif ?>
    <?php if ($msg && $msgType == 'ok'): ?>
      <div class="a-msg a-msg--ok" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i><div><?= htmlspecialchars($msg) ?></div></div>
    <?php endif ?>

    <div class="a-field">
      <label for="un">Benutzername</label>
      <input type="text" id="un" name="username" autocomplete="username" spellcheck="false" inputmode="text" required aria-required="true">
    </div>

    <div class="a-field">
      <label for="pw">Passwort</label>
      <input type="password" id="pw" name="password" autocomplete="current-password" required aria-required="true">
    </div>

    <button type="submit" class="a-btn a-btn--primary" style="width:100%;">
      <i class="fa-solid fa-right-to-bracket"></i> Anmelden
    </button>

    <div class="a-foot">
      <a href="../"><i class="fa-solid fa-arrow-left"></i> Zurück</a>
      <div>© <?= $year ?> <span style="color:var(--accent-yellow);font-weight:700;">RV Hard</span></div>
      <div>
        <a href="../impressum.html">Impressum</a>
        <span aria-hidden="true"> · </span>
        <a href="../datenschutz.html">DSGVO</a>
      </div>
    </div>
  </form>
</main>

<script>
document.getElementById('un').focus();
document.getElementById('un').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('pw').focus(); }
});
</script>

</body>
</html>
<?php
}

/* === Try Login (POST) immer, unabhängig von Seite === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $msg = ''; $ok = false;
    if (!fg2_csrf_check()) {
        $msg = 'Sitzung abgelaufen – bitte neu anmelden.';
    } else {
        $u = trim((string)$_POST['username']);
        $p = (string)$_POST['password'];
        $all = fg2_get_users();
        if (isset($all[$u]) && !empty($all[$u]['hash']) && function_exists('password_verify') && password_verify($p, $all[$u]['hash'])) {
            if (!headers_sent()) @session_regenerate_id(true);
            $_SESSION['fg2_user'] = $u;
            if (!empty($all[$u]['displayName'])) $_SESSION['fg2_name'] = $all[$u]['displayName'];
            unset($_SESSION['fg2_msg'], $_SESSION['fg2_mtype']);
            /* Erfolgreiches Login: NUR EINMALIGER redirect auf SELBSTE SEITE (ohne POST) um Doppel-Submit zu vermeiden */
            $uri = isset($_SERVER['REQUEST_URI']) ? explode('?', (string)$_SERVER['REQUEST_URI'], 2)[0] : '';
            if ($uri && strpos(basename($uri), 'login') === false) {
                /* Kein Problem, bleibt gleiche Seite */
            }
            header('Location: ' . ($uri ?: './index.php'));
            exit;
        } else {
            $msg = 'Benutzername/Passwort falsch.';
        }
    }
    /* Fehler → im Session-Memory speichern für Anzeige auf login.php oder inline */
    $_SESSION['fg2_msg'] = $msg;
    $_SESSION['fg2_mtype'] = 'err';
    /* KEIN redirect bei Fehler → direkt Formular ausgeben */
}

/* Logout via ?logout */
if (isset($_GET['logout']) || (isset($_POST['action']) && $_POST['action'] === 'logout')) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
    /* Zurück zur gleichen Seite (ohne logout parameter) */
    $uri = isset($_SERVER['REQUEST_URI']) ? explode('?', (string)$_SERVER['REQUEST_URI'], 2)[0] : '';
    header('Location: ' . ($uri ?: './'));
    exit;
}

/* Helper: Gibt eingeloggten Namen für Topbar zurück */
function fg2_displayname() {
    if (!fg2_logged()) return '';
    if (!empty($_SESSION['fg2_name'])) return $_SESSION['fg2_name'];
    return fg2_user();
}
