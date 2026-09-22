<?php
/* =============================================================
 *  SBS GALERIE ADMIN – LOGIN SEITE
 *  Benutzername: RVHardAdmin (siehe admin/config.php)
 * ============================================================= */

require_once(__DIR__ . '/auth.php');

$error = '';
$success = '';

/* =========================================================
 *  SICHERHEIT: redirect Parameter sanitizen (OPEN REDIRECT!)
 *  Erlaube NUR:
 *    - Relative Pfade mit ./ oder / (aber KEINE externen // URLs)
 *    - index.php / index.html (Standard, wenn nichts angegeben)
 * ========================================================= */
$redirectTarget = '';
if (!empty($_GET['redirect'])) {
    $redirectTarget = (string)$_GET['redirect'];
} elseif (!empty($_POST['redirect'])) {
    $redirectTarget = (string)$_POST['redirect'];
}
if (!$redirectTarget) {
    $redirectTarget = './index.php';
}
/* Dekodiere URL-kodierte Redirects (für die Prüfung!) */
$decoded = rawurldecode($redirectTarget);
if (is_string($decoded) && $decoded !== '') {
    $redirectTarget = $decoded;
}
/* Externe URLs mit Protokoll? AUF KEINEN FALL! → Default */
if (preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $redirectTarget) || strpos($redirectTarget, '//') === 0) {
    $redirectTarget = './index.php';
}
/* Falls Start ohne / oder ./ → mit ./ ergänzen (relativ zu Admin!) */
if ($redirectTarget !== '' && $redirectTarget[0] !== '/' && strpos($redirectTarget, './') !== 0 && strpos($redirectTarget, '?') !== 0) {
    $redirectTarget = './' . $redirectTarget;
}
/* Zusätzlicher Schutz: Keine Protokolle, keine Backslashes, keine NULL Bytes */
$redirectTarget = str_replace(["\x00","\\","\r","\n"], '', $redirectTarget);

/* Schon eingeloggt? → Sofort weiter (aber immer relative URL!)
 * AUSSER: redirectTarget ist login.php SELBST! → Dann Endlosschleife vermeiden! */
$decodedRedirectForLoopCheck = $redirectTarget;
if ($decodedRedirectForLoopCheck) {
    $tmp = explode('?', rawurldecode($decodedRedirectForLoopCheck), 2);
    $decodedRedirectForLoopCheck = basename($tmp[0]);
}
$loopTargets = ['login.php','logout.php','generate-password-hash.php'];
if (fg_is_admin_logged() && !in_array(strtolower($decodedRedirectForLoopCheck), array_map('strtolower', $loopTargets), true)) {
    header('Location: ' . $redirectTarget);
    exit;
} elseif (fg_is_admin_logged()) {
    /* Schon eingeloggt, aber man wollte auf login.php → auf index.php schicken */
    header('Location: ./index.php');
    exit;
}

/* Formular abgeschickt? */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!fg_csrf_check()) {
        $error = 'Sitzung abgelaufen – bitte neu laden und nochmal anmelden.';
    } else {
        $u = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        $p = isset($_POST['password']) ? (string)$_POST['password'] : '';
        if (fg_admin_try_login($u, $p, $err)) {
            $success = 'Anmeldung erfolgreich! Weiterleiten …';
            header('Refresh: 0.5; url=' . $redirectTarget);
        } else {
            $error = $err;
        }
    }
}

/* Logout? */
if (isset($_GET['logout'])) {
    fg_admin_logout();
    header('Location: ./login.php?bye');
    exit;
}

/* ======= HTML Ausgabe ======= */
/* Asset-Base: Ebenen relativ hoch (admin/ liegt unter Galerie root) */
$assetBase = '../assets/';
$logoUrl   = $assetBase . 'img/logo/RV_Hard_Logo.webp';
$csrfHtml  = '<input type="hidden" name="csrf" value="' . htmlspecialchars(fg_csrf_token()) . '">';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>RV Hard Foto-Galerie · Admin Anmeldung</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  :root{
    --rv-schwarz:#111;
    --rv-rot:#e2001a;
    --rv-gelb:#ffcc00;
    --rv-weiss:#ffffff;
    --rv-grau:#f6f6f6;
    --rv-dunkelgrau:#303030;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;background:linear-gradient(135deg,#111 0%, #2a2a2a 100%);color:var(--rv-weiss);font-family:'Inter',system-ui,sans-serif;min-height:100vh}
  body{display:flex;align-items:center;justify-content:center;padding:24px}
  .card{width:100%;max-width:460px;background:#1a1a1a;border:2px solid var(--rv-gelb);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.5);overflow:hidden}
  .head{padding:28px 24px 18px;text-align:center;background:linear-gradient(180deg,rgba(255,204,0,.08),transparent);border-bottom:1px solid #2a2a2a}
  .logo{width:90px;height:90px;margin:0 auto 14px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(255,204,0,.25)}
  .logo img{max-width:72%;max-height:72%}
  h1{margin:0 0 6px;font-family:'Roboto Slab',serif;font-size:24px;letter-spacing:.3px;color:#fff}
  .sub{margin:0;color:#bbb;font-size:13px}
  .body{padding:24px}
  label{display:block;margin:0 0 6px;font-size:13px;font-weight:600;color:#ddd}
  .field{margin-bottom:16px;position:relative}
  .field i{position:absolute;top:50%;left:14px;transform:translateY(-50%);color:#888}
  input[type=text],input[type=password]{
    width:100%;padding:13px 14px 13px 40px;border-radius:10px;border:1px solid #333;background:#101010;color:#fff;
    font-size:15px;outline:none;transition:border-color .15s, box-shadow .15s;
  }
  input[type=text]:focus,input[type=password]:focus{border-color:var(--rv-gelb);box-shadow:0 0 0 3px rgba(255,204,0,.15)}
  .row{display:flex;justify-content:space-between;align-items:center;margin:4px 0 20px}
  .row a{color:#bbb;font-size:13px;text-decoration:none}
  .row a:hover{color:var(--rv-gelb)}
  button{
    width:100%;padding:13px 16px;border-radius:10px;border:none;background:var(--rv-gelb);color:#111;
    font-weight:700;font-size:15px;cursor:pointer;transition:transform .08s, filter .15s;
  }
  button:hover{filter:brightness(1.05)}button:active{transform:translateY(1px)}
  .msg{border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:14px}
  .msg.err{background:rgba(226,0,26,.12);border:1px solid var(--rv-rot);color:#ffcdd2}
  .msg.ok{background:rgba(255,204,0,.12);border:1px solid var(--rv-gelb);color:#f8e8a1}
  .foot{text-align:center;padding:14px 24px;font-size:12px;color:#888;border-top:1px solid #2a2a2a}
  .foot a{color:#bbb;text-decoration:none}
  .foot a:hover{color:var(--rv-gelb)}
  .hint{margin-top:14px;font-size:12px;color:#999;text-align:center}
</style>
</head>
<body>
<main class="card" role="main" aria-label="Anmeldung RV Hard Foto-Galerie">
  <div class="head">
    <div class="logo" aria-hidden="true">
      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="RV Hard Logo" onerror="this.style.display='none';this.parentNode.innerHTML='<i class=\'fa-solid fa-camera-retro\' style=\'color:#111;font-size:36px\'></i>'">
    </div>
    <h1>Foto-Galerie Anmeldung</h1>
    <p class="sub">Bitte mit Admin-Zugangsdaten anmelden</p>
  </div>

  <form class="body" method="post" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" autocomplete="on" novalidate>
    <?= $csrfHtml ?>
    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget) ?>">

    <?php if (!empty($error)): ?>
      <div class="msg err" role="alert"><i class="fa-solid fa-circle-exclamation" style="margin-right:6px"></i><?= htmlspecialchars($error) ?></div>
    <?php endif ?>
    <?php if (!empty($success)): ?>
      <div class="msg ok" role="status"><i class="fa-solid fa-circle-check" style="margin-right:6px"></i><?= htmlspecialchars($success) ?></div>
    <?php endif ?>
    <?php if (isset($_GET['bye'])): ?>
      <div class="msg ok" role="status"><i class="fa-solid fa-right-from-bracket" style="margin-right:6px"></i>Abmeldung erfolgreich.</div>
    <?php endif ?>
    <?php if (!file_exists(__DIR__ . '/config.php')): ?>
      <div class="msg err" role="alert">
        <strong><i class="fa-solid fa-triangle-exclamation"></i> WICHTIG:</strong><br>
        Keine <code style="background:#000;padding:1px 5px;border-radius:4px">config.php</code> gefunden. Kopiere <code>config.example.php</code> zu <code>config.php</code> und setze den Passwort-Hash für den Benutzer <code>RVHardAdmin</code>!
      </div>
    <?php endif ?>

    <div class="field">
      <label for="username">Benutzername</label>
      <i class="fa-solid fa-user" aria-hidden="true"></i>
      <input type="text" id="username" name="username" autocomplete="username" placeholder="Benutzername" value="RVHardAdmin" required>
    </div>
    <div class="field">
      <label for="password">Passwort</label>
      <i class="fa-solid fa-lock" aria-hidden="true"></i>
      <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Dein Passwort" required>
    </div>

    <div class="row">
      <label style="display:flex;align-items:center;gap:6px;margin:0;font-weight:500;color:#aaa">
        <input type="checkbox" name="remember" value="1" style="accent-color:var(--rv-gelb)"> Angemeldet bleiben
      </label>
      <a href="../" title="Zurück zur Galerie"><i class="fa-solid fa-arrow-left"></i> Galerie</a>
    </div>

    <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> &nbsp;Anmelden</button>

    <p class="hint">
      Passwort vergessen? → Trage neuen Hash in <code>admin/config.php</code> ein.<br>
      Hash generieren: <a href="generate-password-hash.php" style="color:var(--rv-gelb);text-decoration:none">Passwort-Generator öffnen</a>
    </p>
  </form>

  <div class="foot">
    © <?= date('Y') ?> RV Hard e.V. · <a href="../impressum.html">Impressum</a> · <a href="../datenschutz.html">Datenschutz</a>
  </div>
</main>
<script>
  // Autofokus auf Passwort (Username ist vorausgefüllt)
  try { document.getElementById('password').focus(); } catch(e){}
  // Wenn ?redirect=... + Anmeldung erfolgt, wird oben via Refresh weitergeleitet
</script>
</body>
</html>
