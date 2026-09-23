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
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="color-scheme" content="dark">
<title>Anmelden · RV Hard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
:root{
  --bg: #121417;
  --card: #1b2027;
  --soft: #232a33;
  --border: #313b49;
  --text: #e7ebef;
  --muted: #98a2b3;
  --yellow: #ffc107;
  --yellow-hover: #ffd24a;
  --red: #e2001a;
  --radius: 12px;
}
*{box-sizing:border-box}
html,body{margin:0;padding:0;min-height:100%;width:100%}
body{
  font-family:'Inter',system-ui,-apple-system,Segoe UI,sans-serif;
  background:var(--bg);
  color:var(--text);
  display:flex;align-items:center;justify-content:center;
  padding:20px;
  min-height:100vh;min-height:100svh;
  -webkit-font-smoothing:antialiased;
}
.card{
  width:100%;max-width:400px;
  background:var(--card);
  border:1px solid var(--border);
  border-radius:16px;
  padding:28px 26px 24px;
  box-shadow: 0 20px 40px rgba(0,0,0,.4);
}
.logo{
  width:56px;height:56px;border-radius:14px;background:#fff;
  display:flex;align-items:center;justify-content:center;margin:0 auto 18px;
  box-shadow: 0 0 0 2px rgba(255,193,7,.25);
}
.logo img{width:70%;height:70%;object-fit:contain}
h1{
  margin:0 0 22px;font-size:22px;font-weight:700;text-align:center;
  letter-spacing:-.01em;color:var(--text);
}
.msg{
  border-radius:var(--radius);
  padding:11px 13px;
  margin-bottom:18px;
  font-size:13.5px;line-height:1.5;
  display:flex;align-items:flex-start;gap:9px;
}
.msg i{margin-top:2px;flex-shrink:0;font-size:15px}
.msg.err{background:rgba(226,0,26,.1);border:1px solid rgba(226,0,26,.3);color:#ffcdd2}
.msg.err i{color:#ff7a84}
.msg.ok{background:rgba(255,193,7,.1);border:1px solid rgba(255,193,7,.28);color:#ffe9a7}
.msg.ok i{color:var(--yellow)}
.msg.warn{background:rgba(255,100,0,.1);border:1px solid rgba(255,100,0,.3);color:#ffd7b6}
.msg.warn i{color:#ffa755}
.msg code{font-family:Menlo,Consolas,monospace;background:rgba(0,0,0,.35);padding:1px 5px;border-radius:4px;font-size:12px}
.field{margin-bottom:14px}
.field label{
  display:block;margin:0 0 6px;font-size:12.5px;font-weight:700;color:var(--text);
  letter-spacing:.01em;
}
.input{position:relative}
.input i{
  position:absolute;left:13px;top:50%;transform:translateY(-50%);
  color:var(--muted);font-size:15px;width:17px;text-align:center;pointer-events:none;
  transition:color .2s;
}
.input input{
  width:100%;
  padding:12.5px 14px 12.5px 40px;
  background:var(--soft);
  border:1.5px solid var(--border);
  color:var(--text);
  font-family:inherit;font-size:14.5px;font-weight:500;
  border-radius:var(--radius);
  outline:none;
  transition:border-color .2s, box-shadow .2s, background .2s;
}
.input input:hover{border-color:#414c5b}
.input input:focus{
  border-color:var(--yellow);
  background:#1e242d;
  box-shadow:0 0 0 4px rgba(255,193,7,.15);
}
.input:focus-within > i{color:var(--yellow)}
.btn{
  width:100%;
  margin-top:4px;
  padding:13px 18px;
  background:linear-gradient(180deg, var(--yellow-hover), var(--yellow));
  color:#1a1600;
  border:1px solid rgba(0,0,0,.08);
  border-radius:var(--radius);
  font-family:inherit;font-weight:700;font-size:15px;letter-spacing:.01em;
  cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;gap:9px;
  transition:filter .2s, transform .1s;
  box-shadow:0 8px 20px rgba(255,193,7,.18);
}
.btn:hover{filter:brightness(1.06)}
.btn:active{transform:translateY(1px)}
.foot{
  margin-top:22px;padding-top:14px;
  border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:10px;
  font-size:11.5px;color:var(--muted);
}
.foot a{color:var(--muted);text-decoration:none;padding:3px 0;transition:color .2s}
.foot a:hover{color:var(--yellow)}
.foot .copy span{color:var(--yellow);font-weight:700}
@media (prefers-reduced-motion: reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
</style>
</head>
<body>

<main class="card">
  <form method="post" novalidate autocomplete="on">
    <?= $csrfH ?>

    <div class="logo">
      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="RV Hard" onerror="this.style.display='none';this.parentNode.innerHTML='<i class=\'fa-solid fa-lock\' style=\'color:#121417;font-size:26px\'></i>'">
    </div>
    <h1>Anmelden</h1>

    <?php if (!$configExists): ?>
      <div class="msg warn">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong>Fehlt: config.php</strong><br>Kopiere <code>config.example.php</code> nach <code>config.php</code>.</div>
      </div>
    <?php elseif (!$users): ?>
      <div class="msg warn">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong>Kein Benutzer</strong><br>Füge <code>RVHardAdmin</code> + bcrypt Hash in <code>config.php</code> ein.</div>
      </div>
    <?php endif ?>

    <?php if ($msg && $msgType == 'err'): ?>
      <div class="msg err"><i class="fa-solid fa-circle-exclamation"></i><div><?= htmlspecialchars($msg) ?></div></div>
    <?php endif ?>
    <?php if ($msg && $msgType == 'ok'): ?>
      <div class="msg ok"><i class="fa-solid fa-circle-check"></i><div><?= htmlspecialchars($msg) ?></div></div>
    <?php endif ?>

    <div class="field">
      <label for="un">Benutzername</label>
      <div class="input">
        <i class="fa-solid fa-user"></i>
        <input type="text" id="un" name="username" autocomplete="username"
          spellcheck="false" inputmode="text" required aria-required="true">
      </div>
    </div>

    <div class="field">
      <label for="pw">Passwort</label>
      <div class="input">
        <i class="fa-solid fa-key"></i>
        <input type="password" id="pw" name="password" autocomplete="current-password"
          required aria-required="true">
      </div>
    </div>

    <button type="submit" class="btn">
      <i class="fa-solid fa-right-to-bracket"></i> Anmelden
    </button>

    <div class="foot">
      <a href="../"><i class="fa-solid fa-arrow-left"></i> Zurück</a>
      <div class="copy">© <?= $year ?> <span>RV Hard</span></div>
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
