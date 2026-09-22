<?php
/* ===================================================================
 *  RV HARD GALERIE – STANDALONE ADMIN AUTH (KEINE REDIRECTS!)
 *  100% OHNE loop!
 *  - Statt Weiterleitungen: Direkt HTML Formular / Meldung ausgeben + die()
 *  - Funktionen: fg2_logged(), fg2_user(), fg2_require(), fg2_logout()
 * =================================================================== */

/* --- Session starten ---(immer, vor allem Output!)--- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        @session_name('RVH2');
        @session_start();
    }
}

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

/* --- Fehlermeldung / Login Formular OHNE redirect! --- */
function fg2_render_login_page($msg = '', $msgType = 'err') {
    $logoUrl = '../assets/img/logo/RV_Hard_Logo.webp';
    $csrfH = '<input type="hidden" name="csrf" value="' . htmlspecialchars(fg2_csrf_token()) . '">';
    $configExists = file_exists(FG2_CONFIG);
    $users = fg2_get_users();
    ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>RV Hard Foto-Galerie · Anmeldung</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
 :root{--rv-schwarz:#111;--rv-rot:#e2001a;--rv-gelb:#ffcc00;--rv-weiss:#fff;}
 *{box-sizing:border-box}
 html,body{margin:0;padding:0;background:linear-gradient(135deg,#111,#2a2a2a);color:#fff;font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
 .card{width:100%;max-width:460px;background:#1a1a1a;border:2px solid var(--rv-gelb);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.5);overflow:hidden}
 .head{padding:24px;text-align:center;background:linear-gradient(180deg,rgba(255,204,0,.08),transparent);border-bottom:1px solid #2a2a2a}
 .logo{width:84px;height:84px;margin:0 auto 12px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center}
 .logo img{max-width:72%;max-height:72%}
 h1{margin:0 0 4px;font-family:'Roboto Slab',serif;font-size:22px}
 .sub{margin:0;color:#bbb;font-size:13px}
 .body{padding:24px}
 label{display:block;margin:0 0 5px;font-size:13px;font-weight:600;color:#ddd}
 .field{margin-bottom:16px;position:relative}
 .field i{position:absolute;top:50%;left:14px;transform:translateY(-50%);color:#888}
 input[type=text],input[type=password]{width:100%;padding:12px 14px 12px 40px;border-radius:10px;border:1px solid #333;background:#101010;color:#fff;font-size:15px;outline:none}
 input:focus{border-color:var(--rv-gelb);box-shadow:0 0 0 3px rgba(255,204,0,.15)}
 button{width:100%;padding:13px 16px;border-radius:10px;border:none;background:var(--rv-gelb);color:#111;font-weight:700;font-size:15px;cursor:pointer}
 button:active{transform:translateY(1px)}
 .msg{border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:14px}
 .msg.err{background:rgba(226,0,26,.12);border:1px solid var(--rv-rot);color:#ffcdd2}
 .msg.ok{background:rgba(255,204,0,.12);border:1px solid var(--rv-gelb);color:#f8e8a1}
 .foot{text-align:center;padding:12px 24px;font-size:12px;color:#888;border-top:1px solid #2a2a2a}
 .foot a{color:#bbb;text-decoration:none;margin:0 5px}
</style>
</head>
<body>
<main class="card">
  <div class="head">
    <div class="logo"><img src="<?= htmlspecialchars($logoUrl) ?>" alt="RV Hard" onerror="this.style.display='none';this.parentNode.innerHTML='<i class=\'fa-solid fa-camera-retro\' style=\'color:#111;font-size:34px\'></i>'"></div>
    <h1>Foto-Galerie Anmeldung</h1>
    <p class="sub">Benötigt: Admin-Zugangsdaten</p>
  </div>
  <form class="body" method="post">
    <?= $csrfH ?>

    <?php if (!$configExists): ?>
      <div class="msg err"><strong><i class="fa-solid fa-triangle-exclamation"></i> FEHLT config.php!</strong><br>
        Kopiere <code>config.example.php</code> nach <code>config.php</code> und setze Hash für <code>RVHardAdmin</code>!</div>
    <?php elseif (!$users): ?>
      <div class="msg err"><strong><i class="fa-solid fa-triangle-exclamation"></i> KEINE BENUTZER in config.php!</strong><br>
        Füge Benutzer <code>RVHardAdmin</code> mit bcrypt-Hash in <code>config.php</code> ein.</div>
    <?php endif ?>

    <?php if ($msg && $msgType == 'err'): ?><div class="msg err"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($msg) ?></div><?php endif ?>
    <?php if ($msg && $msgType == 'ok'):  ?><div class="msg ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif ?>

    <div class="field">
      <label for="un">Benutzername</label>
      <i class="fa-solid fa-user"></i>
      <input type="text" id="un" name="username" autocomplete="username" value="RVHardAdmin" required>
    </div>
    <div class="field">
      <label for="pw">Passwort</label>
      <i class="fa-solid fa-lock"></i>
      <input type="password" id="pw" name="password" autocomplete="current-password" required>
    </div>

    <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> &nbsp;Anmelden</button>
    <div style="margin-top:14px;text-align:center;color:#999;font-size:12px">
      Passwort vergessen? → Neuen bcrypt Hash in <code>admin/config.php</code> setzen.<br>
      <a href="../" style="color:var(--rv-gelb);text-decoration:none"><i class="fa-solid fa-arrow-left"></i> Zurück zur Galerie</a>
    </div>
  </form>
  <div class="foot">© <?= date('Y') ?> RV Hard e.V.</div>
</main>
<script>document.getElementById('pw').focus();</script>
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
