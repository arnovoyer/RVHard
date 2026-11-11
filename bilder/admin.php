<?php
/* ============================================================
 *  RV HARD – BILDER-UPLOAD ADMIN INBOX (UI)
 *  Eigenes Login-System (Session auf dieser Subdomain)
 *  Passwort steht in config.php unter 'admin_password'
 * ============================================================ */

@ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

/* ---- CONFIG LADEN ---- */
$configFile = __DIR__ . '/config.php';
$CFG = file_exists($configFile) ? (include $configFile) : [];
if (!is_array($CFG)) $CFG = [];

/* ---- LOGIN-SYSTEM (SSO für rv-hard.at) ----
 * Der Bild-Eingang hält das gemeinsame Admin-Passwort (config.php).
 * Das CMS (/cms/) akzeptiert dasselbe Passwort. Beide teilen sich denselben
 * Session-Flag 'rv_admin_logged_in' → einmal anmelden reicht für beide. */
$loginError = '';
$loggedIn = !empty($_SESSION['bilder_admin_logged_in']) || !empty($_SESSION['rv_admin_logged_in']);

/* Logout (SSO: gemeinsame Session → gilt auch für das CMS) */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* Login versuchen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $pw = $_POST['password'] ?? '';
    if (!empty($CFG['admin_password']) && hash_equals($CFG['admin_password'], $pw)) {
        session_regenerate_id(true);
        $_SESSION['bilder_admin_logged_in'] = true;
        $_SESSION['rv_admin_logged_in'] = true;
        $loggedIn = true;
    } else {
        $loginError = 'Falsches Passwort.';
    }
}

/* Wenn nicht eingeloggt → Login-Seite anzeigen */
if (!$loggedIn) {
    ?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Anmelden | RV Hard Bild-Eingang</title>
  <script>
    (function () {
      try {
        var t = localStorage.getItem('rv-hard-theme');
        document.documentElement.setAttribute('data-theme', (t === 'dark' || t === 'light') ? t : 'light');
      } catch (e) {}
    })();
  </script>
  <link rel="stylesheet" href="/assets/fonts.css?v=20261111">
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css?v=20261111">
  <link rel="stylesheet" href="/assets/admin.css?v=20261103">
</head>
<body class="a-login-page">
  <div class="a-login">
    <div class="a-login__logo"><i class="fa-solid fa-lock" style="color:#111;font-size:24px;"></i></div>
    <h1>RV Hard Bild-Eingang</h1>
    <p class="a-page-sub" style="text-align:center;margin-bottom:20px;">Bitte mit dem Admin-Passwort anmelden</p>
    <?php if ($loginError): ?>
      <div class="a-msg a-msg--err" style="margin-bottom:16px;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <div class="a-field">
        <label for="pw">Passwort</label>
        <input type="password" id="pw" name="password" autofocus required>
      </div>
      <button type="submit" class="a-btn a-btn--primary" style="width:100%;"><i class="fa-solid fa-right-to-bracket"></i> Anmelden</button>
    </form>
  </div>
</body>
</html>
    <?php
    exit;
}

/* Ab hier: eingeloggt */
$currentName = 'Admin';
$currentUser = 'Admin';

/* ---------- EINSTELLUNGEN SPEICHERN ---------- */
$saveMsg = '';
$saveOk  = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $newEmail = trim($_POST['email_to'] ?? '');
    $newTopic = trim($_POST['ntfy_topic'] ?? '');
    $newAuthPath = trim($_POST['auth_path'] ?? '');
    $newPw = trim($_POST['admin_password'] ?? '');
    $newCfg = [
        'admin_password' => $newPw !== '' ? $newPw : ($CFG['admin_password'] ?? ''),
        'auth_path'   => $newAuthPath,
        'email_to'    => $newEmail,
        'email_from'  => $CFG['email_from'] ?? 'noreply@rv-hard.at',
        'ntfy_topic'  => $newTopic,
        'ntfy_server' => $CFG['ntfy_server'] ?? 'https://ntfy.sh',
        'max_files'   => isset($CFG['max_files'])  ? (int)$CFG['max_files']  : 20,
        'max_img_mb'  => isset($CFG['max_img_mb']) ? (int)$CFG['max_img_mb'] : 15,
        'max_vid_mb'  => isset($CFG['max_vid_mb']) ? (int)$CFG['max_vid_mb'] : 50,
    ];
    $content = "<?php\nreturn " . var_export($newCfg, true) . ";\n";
    if (@file_put_contents($configFile, $content)) {
        $CFG = $newCfg;
        $saveMsg = 'Einstellungen gespeichert!';
        $saveOk  = true;
    } else {
        $saveMsg = 'Konnte config.php nicht speichern (Schreibrechte fehlen?).';
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Bild-Eingang | RV Hard Admin</title>
  <script>
    (function () {
      try {
        var t = localStorage.getItem('rv-hard-theme');
        document.documentElement.setAttribute('data-theme', (t === 'dark' || t === 'light') ? t : 'light');
      } catch (e) {}
    })();
  </script>
  <link rel="stylesheet" href="/assets/fonts.css?v=20261111">
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css?v=20261111">
  <link rel="stylesheet" href="/assets/admin.css?v=20261103">
  <script src="/assets/admin.js?v=20261103" defer></script>
</head>
<body class="a-admin">
  <div class="a-topbar">
    <div class="a-topbar__brand"><i class="fa-solid fa-shield-halved"></i> <span>RV Hard <small>· Admin</small></span></div>
    <div class="a-topbar__spacer"></div>
    <a class="a-topbar__link" href="/fotos/admin/"><i class="fa-solid fa-upload"></i> <span>Hochladen</span></a>
    <a class="a-topbar__link" href="/fotos/admin/verwalten.php"><i class="fa-solid fa-list-check"></i> <span>Bilder</span></a>
    <a class="a-topbar__link" href="/fotos/admin/stats.php"><i class="fa-solid fa-chart-simple"></i> <span>Statistik</span></a>
    <a class="a-topbar__link is-active" href="/bilder/admin.php"><i class="fa-solid fa-inbox"></i> <span>Bild-Eingang</span></a>
    <a class="a-topbar__link" href="/cms/"><i class="fa-solid fa-newspaper"></i> <span>Admin</span></a>
    <a class="a-topbar__link" href="https://fotos.rv-hard.at/" target="_blank" rel="noopener"><i class="fa-solid fa-image"></i> <span>Galerie</span></a>
    <button type="button" class="a-topbar__btn" data-theme-toggle title="Hell/Dunkel umschalten" aria-label="Theme umschalten"><i class="fa-solid fa-moon"></i></button>
    <a class="a-topbar__link a-topbar__link--danger" href="?logout=1" onclick="if(!confirm('Wirklich abmelden?'))return false"><i class="fa-solid fa-right-from-bracket"></i> <span>Abmelden</span></a>
  </div>

  <div class="a-wrap">
    <h1 class="a-page-title"><i class="fa-solid fa-inbox"></i> Bild-Eingang</h1>
    <p class="a-page-sub">Eingegangene Uploads von externen Wettkämpfen. Gruppiert nach Veranstaltung.</p>

    <div class="a-tabs">
      <button class="a-tab is-active" onclick="showTab('uploads',this)"><i class="fa-solid fa-images"></i> Uploads</button>
      <button class="a-tab" onclick="showTab('settings',this)"><i class="fa-solid fa-gear"></i> Einstellungen</button>
    </div>

    <div id="tab-uploads" class="a-panel is-active">
      <button class="a-btn" onclick="loadUploads()"><i class="fa-solid fa-rotate"></i> Aktualisieren</button>
      <div id="uploads" style="margin-top:16px;"></div>
    </div>

    <div id="tab-settings" class="a-panel">
      <div class="a-card" style="max-width:640px;">
        <?php if ($saveMsg): ?>
          <div class="a-msg a-msg--<?= $saveOk ? 'ok' : 'err' ?>" style="margin-bottom:16px;">
            <i class="fa-solid fa-<?= $saveOk ? 'circle-check' : 'triangle-exclamation' ?>"></i>
            <span><?= htmlspecialchars($saveMsg) ?></span>
          </div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="save_settings">
          <div class="a-field">
            <label for="admin_password">Admin-Passwort ändern</label>
            <input type="password" id="admin_password" name="admin_password" placeholder="• • • • • • • •">
            <div class="a-hint">Leer lassen, um das aktuelle Passwort beizubehalten.</div>
          </div>
          <div class="a-field">
            <label for="email_to">E-Mail für Benachrichtigungen</label>
            <input type="email" id="email_to" name="email_to" value="<?= htmlspecialchars($CFG['email_to'] ?? '') ?>" placeholder="deine@email.at">
            <div class="a-hint">Hier bekommst du eine E-Mail, sobald jemand Bilder hochlädt.</div>
          </div>
          <div class="a-field">
            <label for="ntfy_topic">ntfy.sh Topic (Push)</label>
            <input type="text" id="ntfy_topic" name="ntfy_topic" value="<?= htmlspecialchars($CFG['ntfy_topic'] ?? '') ?>" placeholder="rvhard-bildereingang">
            <div class="a-hint">Topic-Name, den du in der ntfy-App abonniert hast. Leer = kein Push.</div>
          </div>
          <button type="submit" class="a-btn a-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Speichern</button>
        </form>
      </div>
    </div>
  </div>

  <div class="a-lightbox" id="lightbox" onclick="if(event.target===this)closeLB()">
    <button class="a-lightbox__close" onclick="closeLB()"><i class="fa-solid fa-xmark"></i></button>
    <img id="lbImg" src="" alt="">
    <video id="lbVid" controls style="display:none;"></video>
  </div>

  <script>
    function showTab(name, btn) {
      document.querySelectorAll('.a-tab').forEach(t => t.classList.remove('is-active'));
      document.querySelectorAll('.a-panel').forEach(p => p.classList.remove('is-active'));
      btn.classList.add('is-active');
      document.getElementById('tab-' + name).classList.add('is-active');
    }

    const LB = document.getElementById('lightbox');
    const lbImg = document.getElementById('lbImg');
    const lbVid = document.getElementById('lbVid');

    function closeLB() {
      LB.classList.remove('show');
      lbImg.style.display = 'none'; lbVid.style.display = 'none';
      lbVid.pause(); lbImg.src = ''; lbVid.src = '';
    }
    function openLB(url, type) {
      if (type === 'video') {
        lbVid.src = url; lbVid.style.display = 'block'; lbImg.style.display = 'none';
      } else {
        lbImg.src = url; lbImg.style.display = 'block'; lbVid.style.display = 'none';
      }
      LB.classList.add('show');
    }

    async function loadUploads() {
      const wrap = document.getElementById('uploads');
      wrap.innerHTML = '<p class="a-empty">Lade…</p>';
      try {
        const r = await fetch('inbox.php?action=list');
        if (!r.ok) throw new Error('Server antwortet mit Status ' + r.status + ' (inbox.php)');
        const data = await r.json();
        if (!data.ok) throw new Error(data.msg || 'Fehler');
        const ups = data.uploads || [];
        if (ups.length === 0) {
          wrap.innerHTML = '<div class="a-empty"><i class="fa-solid fa-inbox"></i>Keine Uploads vorhanden.</div>';
          return;
        }
        wrap.innerHTML = ups.map(u => {
          const dt = new Date(u.datetime);
          const dateStr = dt.toLocaleString('de-AT', { dateStyle: 'medium', timeStyle: 'short' });
          return `
            <div class="a-card" id="up-${u.id}">
              <div class="a-card__head">
                <h3 class="a-grow"><i class="fa-solid fa-flag" style="color:var(--accent-yellow);"></i> ${escapeHtml(u.event)}</h3>
                <div class="a-meta">
                  <span><i class="fa-regular fa-calendar"></i> ${dateStr}</span>
                  <span><i class="fa-solid fa-images"></i> ${u.count} Datei(en)</span>
                </div>
                <a class="a-btn a-btn--primary a-btn--sm" href="inbox.php?action=download&id=${encodeURIComponent(u.id)}"><i class="fa-solid fa-download"></i> ZIP</a>
                <button class="a-btn a-btn--danger a-btn--sm" onclick="delUpload('${u.id}','${escapeHtml(u.event)}')"><i class="fa-solid fa-trash"></i> Löschen</button>
              </div>
              <div class="a-thumbgrid" style="padding:0;">
                ${u.files.map(f => {
                  const url = `inbox.php?action=preview&id=${encodeURIComponent(u.id)}&file=${encodeURIComponent(f.file)}`;
                  const isVid = f.type === 'video';
                  return `
                    <div class="a-thumb" onclick="openLB('${url}','${f.type}')">
                      ${isVid ? `<video muted playsinline><source src="${url}"></video><span class="a-thumb__badge"><i class="fa-solid fa-play"></i></span>` : `<img src="${url}" alt="">`}
                      <span class="a-thumb__name">${escapeHtml(f.original)}</span>
                    </div>`;
                }).join('')}
              </div>
            </div>`;
        }).join('');
      } catch (e) {
        wrap.innerHTML = `<div class="a-msg a-msg--err"><i class="fa-solid fa-triangle-exclamation"></i><span>Fehler beim Laden: ${escapeHtml(e.message)}</span></div>`;
      }
    }

    async function delUpload(id, event) {
      if (!confirm(`Upload von "${event}" wirklich löschen?`)) return;
      const fd = new FormData();
      fd.append('id', id);
      try {
        const r = await fetch('inbox.php?action=delete', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.ok) {
          document.getElementById('up-' + id)?.remove();
        } else {
          alert(data.msg || 'Löschen fehlgeschlagen.');
        }
      } catch (e) { alert('Fehler: ' + e.message); }
    }

    function escapeHtml(s) {
      const d = document.createElement('div');
      d.textContent = s;
      return d.innerHTML;
    }

    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeLB(); });
    loadUploads();
  </script>
</body>
</html>
