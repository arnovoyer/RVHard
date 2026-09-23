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

/* --- Fehlermeldung / Login Formular OHNE redirect! --- */
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
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>RV Hard · Foto-Galerie Administrator</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto+Slab:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
/* ============ RV HARD DESIGN TOKENS (1:1 aus assets/style.css) ============ */
:root{
  --site-bg: #121417;
  --site-text: #e7ebef;
  --site-heading: #f3f5f7;
  --site-muted: #c2c8cf;
  --site-surface: #1b2027;
  --site-surface-soft: #232a33;
  --site-border: #313b49;
  --accent-yellow: #ffc107;
  --accent-yellow-hover: #ffd24a;
  --accent-red: #e2001a;
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-xl: 20px;
  --shadow-card: 0 20px 50px rgba(0,0,0,.45), 0 1px 0 rgba(255,255,255,.03) inset;
}

*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0;height:100%;width:100%}
body{
  font-family:'Inter',system-ui,-apple-system,Segoe UI,sans-serif;
  color:var(--site-text);
  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(255,193,7,.10), transparent 60%),
    radial-gradient(800px 500px at 110% 110%, rgba(226,0,26,.06), transparent 55%),
    var(--site-bg);
  min-height:100vh;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
  overflow-x:hidden;
}

/* ============ MAIN LAYOUT: Split Desktop / Stacked Mobile ============ */
.rv-login{
  min-height:100vh;
  min-height:100svh;
  display:grid;
  grid-template-columns: 1.05fr .95fr;
  max-width:1400px;
  margin:0 auto;
  padding:24px;
  gap:24px;
  align-items:center;
}
@media (max-width: 980px){
  .rv-login{grid-template-columns:1fr; padding:16px; gap:16px}
}

/* ============ LEFT PANEL (DESKTOP ONLY) – RV HARD BRANDING ============ */
.rv-login__brand{
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  min-height:620px;
  padding:48px 48px 44px;
  border-radius:var(--radius-xl);
  background:
    linear-gradient(160deg, rgba(255,193,7,.12), rgba(27,32,39,.98) 45%, rgba(18,20,23,1) 100%),
    url('../assets/img/hero/Hero-img-medium.webp') center/cover no-repeat,
    var(--site-surface);
  box-shadow:var(--shadow-card);
  border:1px solid rgba(255,193,7,.18);
  overflow:hidden;
  position:relative;
}
@media (max-width: 980px){
  .rv-login__brand{
    min-height:0;
    padding:28px 24px;
    background:linear-gradient(160deg, rgba(255,193,7,.12), rgba(27,32,39,.98) 70%), var(--site-surface);
  }
}
.rv-login__brand::before{
  content:"";
  position:absolute; inset:0;
  background:
    linear-gradient(90deg, rgba(255,193,7,.0) 0%, rgba(255,193,7,.18) 50%, rgba(255,193,7,.0) 100%);
  transform: translateX(-100%);
  animation: rvShine 9s ease-in-out infinite;
}
@keyframes rvShine{
  0%,100%{transform: translateX(-100%)}
  50%{transform: translateX(100%)}
}

/* Brand – Logo + Headline */
.rv-login__brand-top{position:relative; z-index:2}
.rv-login__logo-row{display:flex;align-items:center;gap:16px;margin-bottom:34px}
.rv-login__logo-wrap{
  width:76px;height:76px;border-radius:20px;
  background:#fff;display:flex;align-items:center;justify-content:center;
  box-shadow: 0 8px 22px rgba(0,0,0,.3), 0 0 0 2px rgba(255,193,7,.25);
}
.rv-login__logo-wrap img{width:76%;height:76%;object-fit:contain}
.rv-login__brand-title{
  font-family:'Roboto Slab',serif;
  font-weight:800;
  font-size:30px;
  line-height:1.1;
  color:var(--site-heading);
  margin:0;
}
.rv-login__brand-title span{color:var(--accent-yellow)}
.rv-login__brand-sub{
  font-size:13.5px;
  color:var(--site-muted);
  margin-top:6px;
  font-weight:500;
}

.rv-login__headline{
  position:relative; z-index:2;
  font-family:'Roboto Slab',serif;
  font-size: clamp(28px, 3.4vw, 44px);
  line-height:1.12;
  font-weight:800;
  margin:14px 0 18px;
  color:var(--site-heading);
  max-width:520px;
}
.rv-login__headline em{
  font-style:normal;
  color:var(--accent-yellow);
  position:relative;
  display:inline-block;
}
.rv-login__headline em::after{
  content:"";
  position:absolute;left:-3px;right:-3px;bottom:4px;height:34%;
  background:linear-gradient(180deg, transparent, rgba(255,193,7,.28));
  z-index:-1;border-radius:4px;
  transform: skewX(-8deg);
}
.rv-login__desc{
  position:relative; z-index:2;
  max-width:460px;
  color:var(--site-text);
  opacity:.86;
  font-size:15px;
  line-height:1.65;
  margin:0;
}

/* Brand – Feature Liste */
.rv-login__features{
  position:relative; z-index:2;
  list-style:none;
  padding:0;margin:40px 0 0;
  display:flex;flex-direction:column;gap:12px;
}
.rv-login__features li{
  display:flex;align-items:center;gap:12px;
  font-size:14px;color:var(--site-text);
  background:rgba(35,42,51,.42);
  backdrop-filter: blur(8px);
  padding:12px 14px;
  border:1px solid rgba(49,59,73,.6);
  border-radius:var(--radius-md);
  max-width:440px;
}
.rv-login__features i{
  width:34px;height:34px;flex-shrink:0;
  border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  background:rgba(255,193,7,.12);
  color:var(--accent-yellow);
  font-size:15px;
  border:1px solid rgba(255,193,7,.22);
}

/* Brand – Footer */
.rv-login__brand-foot{
  position:relative; z-index:2;
  display:flex;align-items:flex-end;justify-content:space-between;
  gap:16px;margin-top:30px;
  color:var(--site-muted);
  font-size:12.5px;
}
.rv-login__brand-foot strong{color:var(--site-heading);font-weight:700}
@media (max-width: 980px){
  .rv-login__features{display:none}
  .rv-login__headline{margin-top:20px;font-size:28px}
  .rv-login__desc{font-size:14px}
  .rv-login__brand-foot{margin-top:28px}
}

/* ============ RIGHT PANEL – LOGIN FORM CARD ============ */
.rv-login__form-wrap{
  display:flex;align-items:center;justify-content:center;
  padding:12px 0;
}
.rv-login__card{
  width:100%;
  max-width:460px;
  background:var(--site-surface);
  border:1px solid var(--site-border);
  border-radius:var(--radius-xl);
  box-shadow: var(--shadow-card);
  overflow:hidden;
  animation: rvFadeUp .5s cubic-bezier(.22,.61,.36,1) both;
}
@keyframes rvFadeUp{
  from{opacity:0;transform: translateY(14px) scale(.985)}
  to{opacity:1;transform:none}
}
@media (max-width: 980px){
  .rv-login__card{max-width:100%}
}

/* Card – Header */
.rv-login__card-top{
  padding:30px 32px 20px;
  border-bottom:1px solid var(--site-border);
  background:
    radial-gradient(500px 120px at 50% -40%, rgba(255,193,7,.12), transparent 70%),
    var(--site-surface-soft);
}
.rv-login__card-top .eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
  color:var(--accent-yellow);
  background:rgba(255,193,7,.10);
  padding:6px 12px;border-radius:999px;
  border:1px solid rgba(255,193,7,.22);
  margin-bottom:14px;
}
.rv-login__card-top .eyebrow i{font-size:12px}
.rv-login__card-top h2{
  margin:0 0 4px;
  font-family:'Roboto Slab',serif;
  font-weight:800;
  font-size:26px;
  line-height:1.15;
  color:var(--site-heading);
}
.rv-login__card-top p{
  margin:4px 0 0;
  font-size:13.5px;color:var(--site-muted);line-height:1.5
}

/* Card – Body */
.rv-login__card-body{padding:26px 32px 28px}
@media (max-width: 480px){
  .rv-login__card-body{padding:22px 20px 24px}
  .rv-login__card-top{padding:24px 20px 16px}
}

/* Messages */
.rv-msg{
  border-radius:var(--radius-md);
  padding:12px 14px;
  margin-bottom:18px;
  font-size:13.5px;
  line-height:1.55;
  display:flex;align-items:flex-start;gap:10px;
  animation: rvMsgIn .28s ease both;
}
@keyframes rvMsgIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1}}
.rv-msg i{margin-top:2px;flex-shrink:0;font-size:15px}
.rv-msg--err{
  background:rgba(226,0,26,.10);
  border:1px solid rgba(226,0,26,.3);
  color:#ffcdd2;
}
.rv-msg--err i{color:#ff7a84}
.rv-msg--ok{
  background:rgba(255,193,7,.10);
  border:1px solid rgba(255,193,7,.28);
  color:#ffe9a7;
}
.rv-msg--ok i{color:var(--accent-yellow)}

/* Config Warnung */
.rv-msg--warn{
  background:rgba(255,100,0,.10);
  border:1px solid rgba(255,100,0,.3);
  color:#ffd7b6;
}
.rv-msg--warn i{color:#ffa755}
.rv-msg code{
  font-family:'JetBrains Mono',Menlo,Consolas,monospace;
  background:rgba(0,0,0,.35);
  padding:1px 6px;
  border-radius:4px;
  border:1px solid rgba(255,255,255,.06);
  font-size:12.5px;
}

/* Fields */
.rv-field{margin-bottom:18px}
.rv-field__label{
  display:block;
  margin:0 0 7px;
  font-size:12.5px;font-weight:700;
  color:var(--site-heading);
  letter-spacing:.01em;
  display:flex;align-items:center;justify-content:space-between;
}
.rv-field__label a{
  font-size:12px;color:var(--accent-yellow);text-decoration:none;font-weight:600;
  opacity:.9;transition:opacity .2s
}
.rv-field__label a:hover{opacity:1;text-decoration:underline}

.rv-input{
  position:relative;
}
.rv-input i{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  color:var(--site-muted);
  font-size:15px;
  width:18px;text-align:center;
  pointer-events:none;
  transition:color .2s
}
.rv-input__right{
  position:absolute;right:6px;top:50%;transform:translateY(-50%);
  display:flex;align-items:center;gap:4px;
}
.rv-input__right button{
  background:transparent;
  border:0;
  width:34px;height:34px;border-radius:10px;
  color:var(--site-muted);
  cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition: all .2s;
}
.rv-input__right button:hover{
  background:var(--site-surface-soft);
  color:var(--site-heading);
}

.rv-input input{
  width:100%;
  padding:13px 14px 13px 42px;
  background:var(--site-surface-soft);
  border:1.5px solid var(--site-border);
  color:var(--site-heading);
  font-family:inherit;font-size:14.5px;font-weight:500;
  border-radius:var(--radius-md);
  outline:none;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
.rv-input input::placeholder{color:#8893a3;font-weight:400}
.rv-input input:hover{border-color:#414c5b}
.rv-input input:focus{
  border-color:var(--accent-yellow);
  background: #1e242d;
  box-shadow: 0 0 0 4px rgba(255,193,7,.15);
}
.rv-input input:focus + i,
.rv-input:focus-within > i{color:var(--accent-yellow)}

/* Submit Button */
.rv-btn{
  position:relative;
  width:100%;
  border:0;
  cursor:pointer;
  padding:14px 18px;
  font-family:inherit;font-weight:800;font-size:15px;
  letter-spacing:.01em;
  border-radius:var(--radius-md);
  transition: transform .12s ease, background .2s, box-shadow .2s, filter .2s;
  display:inline-flex;align-items:center;justify-content:center;gap:10px;
  text-decoration:none;
  user-select:none;
}
.rv-btn:focus-visible{outline:3px solid rgba(255,193,7,.4);outline-offset:2px}
.rv-btn:active{transform: translateY(1px)}

.rv-btn--primary{
  background: linear-gradient(180deg, var(--accent-yellow-hover), var(--accent-yellow));
  color: #1a1600;
  box-shadow:
    0 10px 0 rgba(255,193,7,.06),
    0 8px 22px rgba(255,193,7,.18),
    0 1px 0 rgba(255,255,255,.28) inset;
  border:1px solid rgba(0,0,0,.08);
}
.rv-btn--primary:hover{
  filter:brightness(1.06);
  box-shadow:
    0 12px 0 rgba(255,193,7,.08),
    0 12px 26px rgba(255,193,7,.28);
}
.rv-btn--primary i{font-size:16px}

/* Card – Footer Actions */
.rv-login__card-foot{
  margin-top:20px;
  padding-top:18px;
  border-top:1px dashed var(--site-border);
  display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;
}
.rv-back-link{
  display:inline-flex;align-items:center;gap:8px;
  font-size:13px;font-weight:600;
  color:var(--site-muted);
  text-decoration:none;
  padding:8px 12px;border-radius:10px;
  transition: background .2s, color .2s, transform .15s;
}
.rv-back-link:hover{
  background:var(--site-surface-soft);color:var(--site-text);
  transform:translateX(-2px)
}
.rv-back-link i{font-size:12px;color:var(--accent-yellow)}

.rv-login__mini-links{
  display:flex;gap:10px;flex-wrap:wrap;
  font-size:11.5px;
}
.rv-login__mini-links a{
  color:#8590a0;text-decoration:none;transition:color .2s;padding:4px 2px;
}
.rv-login__mini-links a:hover{color:var(--accent-yellow)}

/* Card – Foot Copyright */
.rv-login__card-copy{
  padding:14px 32px 20px;
  text-align:center;
  font-size:11.5px;
  color:#6e7886;
  border-top:1px solid var(--site-border);
  background:rgba(0,0,0,.18);
}
.rv-login__card-copy span{color:var(--accent-yellow);font-weight:700}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce){
  *,*::before,*::after{animation:none!important;transition:none!important}
}
</style>
</head>
<body>

<main class="rv-login" role="main">

  <!-- LEFT: BRANDING PANEL -->
  <section class="rv-login__brand" aria-hidden="false">
    <div>
      <div class="rv-login__brand-top">
        <div class="rv-login__logo-row">
          <div class="rv-login__logo-wrap">
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="RV Hard Logo" onerror="this.style.display='none';this.parentNode.innerHTML='<i class=\'fa-solid fa-person-biking\' style=\'color:#121417;font-size:38px\'></i>'">
          </div>
          <div>
            <h1 class="rv-login__brand-title">RV <span>Hard</span></h1>
            <div class="rv-login__brand-sub">Radsport · Triathlon · Mountainbike</div>
          </div>
        </div>
      </div>

      <h2 class="rv-login__headline">
        Sommer Bike Series<br>
        <em>Foto-Galerie</em> Administration
      </h2>
      <p class="rv-login__desc">
        Bereich für Upload, Tagging und Veröffentlichung der offiziellen Fotos vom Stadtkriterium Kammgarn Hard, Bergrennen Wolfurt-Buch und EZF Rohrspitz Fußach.
      </p>

      <ul class="rv-login__features">
        <li><i class="fa-solid fa-cloud-arrow-up"></i><span>Drag & Drop Upload direkt im Browser — kein FTP nötig</span></li>
        <li><i class="fa-solid fa-tags"></i><span>Startnummern-Tagging pro Bild — automatische Filterung</span></li>
        <li><i class="fa-solid fa-bolt"></i><span>Ein Klick Publish — Bilder sofort öffentlich sichtbar</span></li>
      </ul>
    </div>

    <div class="rv-login__brand-foot">
      <div>© <?= $year ?> <strong>RV Hard e.V.</strong><br>ZVR 855750678 · Hard, Vorarlberg</div>
      <div style="text-align:right">
        <strong>SBS <?= $year ?></strong><br>
        Foto-Galerie v3.2
      </div>
    </div>
  </section>

  <!-- RIGHT: LOGIN FORM CARD -->
  <section class="rv-login__form-wrap" aria-label="Anmeldeformular">
    <div class="rv-login__card">
      <form method="post" novalidate autocomplete="on">
        <?= $csrfH ?>

        <div class="rv-login__card-top">
          <span class="eyebrow"><i class="fa-solid fa-lock"></i> Administrator Bereich</span>
          <h2>Mit Zugangsdaten anmelden</h2>
          <p>Gib deine Zugangsdaten ein, um den Upload-Bereich zu öffnen.</p>
        </div>

        <div class="rv-login__card-body">

          <?php if (!$configExists): ?>
            <div class="rv-msg rv-msg--warn">
              <i class="fa-solid fa-triangle-exclamation"></i>
              <div><strong>Fehlende Konfiguration!</strong><br>
                Erstelle die Datei <code>admin/config.php</code> aus der Vorlage <code>config.example.php</code> und hinterlege Benutzer <code>RVHardAdmin</code> mit bcrypt-Passwort-Hash.</div>
            </div>
          <?php elseif (!$users): ?>
            <div class="rv-msg rv-msg--warn">
              <i class="fa-solid fa-triangle-exclamation"></i>
              <div><strong>Kein Benutzer hinterlegt!</strong><br>
                Füge mindestens einen Benutzer (<code>RVHardAdmin</code> + bcrypt Hash) in <code>config.php</code> ein.</div>
            </div>
          <?php endif ?>

          <?php if ($msg && $msgType == 'err'): ?>
            <div class="rv-msg rv-msg--err"><i class="fa-solid fa-circle-exclamation"></i><div><?= htmlspecialchars($msg) ?></div></div>
          <?php endif ?>
          <?php if ($msg && $msgType == 'ok'): ?>
            <div class="rv-msg rv-msg--ok"><i class="fa-solid fa-circle-check"></i><div><?= htmlspecialchars($msg) ?></div></div>
          <?php endif ?>

          <div class="rv-field">
            <label class="rv-field__label" for="un">Benutzername</label>
            <div class="rv-input">
              <i class="fa-solid fa-user"></i>
              <input type="text" id="un" name="username" autocomplete="username" inputmode="text" spellcheck="false"
                value="RVHardAdmin" required aria-required="true">
            </div>
          </div>

          <div class="rv-field">
            <label class="rv-field__label" for="pw">Passwort
              <a href="#" onclick="return false" title="Passwort vergessen? → Neuen Hash in config.php setzen!" aria-label="Passwort vergessen">
                <i class="fa-solid fa-circle-question"></i> Vergessen?
              </a>
            </label>
            <div class="rv-input">
              <i class="fa-solid fa-key"></i>
              <input type="password" id="pw" name="password" autocomplete="current-password"
                placeholder="Dein Admin Passwort…" required aria-required="true">
              <div class="rv-input__right">
                <button type="button" id="rv-pw-toggle"
                  aria-label="Passwort ein- oder ausblenden"
                  aria-pressed="false" title="Passwort anzeigen / verstecken">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
              </div>
            </div>
          </div>

          <button type="submit" class="rv-btn rv-btn--primary" style="margin-top:2px">
            <i class="fa-solid fa-right-to-bracket"></i> Anmelden
          </button>

          <div class="rv-login__card-foot">
            <a href="../" class="rv-back-link">
              <i class="fa-solid fa-arrow-left"></i> Zurück zur Galerie
            </a>
            <div class="rv-login__mini-links">
              <a href="../impressum.html" rel="noopener">Impressum</a>
              <span aria-hidden="true">·</span>
              <a href="../datenschutz.html" rel="noopener">Datenschutz</a>
            </div>
          </div>

        </div>

        <div class="rv-login__card-copy">
          Powered by <span>RV Hard Dev Team</span> · Gesicherter Admin-Bereich
        </div>
      </form>
    </div>
  </section>

</main>

<script>
/* Passwort Show/Hide Toggle */
(function(){
  const btn = document.getElementById('rv-pw-toggle');
  const inp = document.getElementById('pw');
  if (!btn || !inp) return;
  const ic  = btn.querySelector('i');
  btn.addEventListener('click', () => {
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    ic.classList.toggle('fa-regular', !show);
    ic.classList.toggle('fa-solid',   show);
    ic.classList.toggle('fa-eye',     !show);
    ic.classList.toggle('fa-eye-slash', show);
    btn.title = show ? 'Passwort verstecken' : 'Passwort anzeigen';
    setTimeout(()=>inp.focus(), 10);
  });
  /* Enter von Username → springt in Passwort Feld */
  document.getElementById('un').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('pw').focus(); }
  });
})();
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
