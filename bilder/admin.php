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

/* ---- EIGENES LOGIN-SYSTEM ---- */
$loginError = '';
$loggedIn = !empty($_SESSION['bilder_admin_logged_in']);

/* Logout */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* Login versuchen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $pw = $_POST['password'] ?? '';
    if (!empty($CFG['admin_password']) && hash_equals($CFG['admin_password'], $pw)) {
        $_SESSION['bilder_admin_logged_in'] = true;
        $loggedIn = true;
    } else {
        $loginError = 'Falsches Passwort.';
    }
}

/* Wenn nicht eingeloggt → Login-Seite anzeigen */
if (!$loggedIn) {
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | RV Hard Bild-Eingang</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', system-ui, sans-serif;
      background: #f4f4f4;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; padding: 1rem;
    }
    .login-box {
      background: #fff; border-radius: 16px; padding: 2.5rem 2rem;
      box-shadow: 0 8px 30px rgba(0,0,0,0.1);
      width: 100%; max-width: 380px; text-align: center;
    }
    .login-box .icon {
      font-size: 2.5rem; color: #ffc400; margin-bottom: 0.8rem;
    }
    .login-box h1 { font-size: 1.4rem; margin-bottom: 0.3rem; }
    .login-box .sub { color: #888; font-size: 0.88rem; margin-bottom: 1.5rem; }
    .login-box label { display: block; text-align: left; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.9rem; }
    .login-box input[type="password"] {
      width: 100%; padding: 0.85rem 1rem; border: 2px solid #ddd; border-radius: 10px;
      font-size: 1rem; font-family: inherit; margin-bottom: 1rem;
    }
    .login-box input:focus { outline: none; border-color: #ffc400; }
    .login-box button {
      width: 100%; padding: 0.9rem; background: #ffc400; color: #1a1a1a;
      border: none; border-radius: 10px; font-weight: 700; font-size: 1rem;
      cursor: pointer; font-family: inherit;
    }
    .login-box button:hover { background: #f5b301; }
    .error {
      background: #f8d7da; color: #721c24; padding: 0.7rem 1rem;
      border-radius: 8px; margin-bottom: 1rem; font-size: 0.88rem;
    }
  </style>
</head>
<body>
  <div class="login-box">
    <div class="icon"><i class="fa-solid fa-lock"></i></div>
    <h1>RV Hard Bild-Eingang</h1>
    <p class="sub">Bitte mit dem Admin-Passwort anmelden</p>
    <?php if ($loginError): ?>
      <div class="error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <label for="pw">Passwort</label>
      <input type="password" id="pw" name="password" autofocus required>
      <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> Anmelden</button>
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
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bild-Eingang | RV Hard Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', system-ui, sans-serif;
      background: #f4f4f4;
      color: #222;
      padding-top: 60px;
    }
    .topbar {
      position: fixed; top: 0; left: 0; right: 0; z-index: 10000;
      background: #111; border-bottom: 2px solid #ffcc00;
      padding: 9px 18px; display: flex; align-items: center; gap: 14px;
      font-size: 13px; color: #fff;
    }
    .topbar .name { color: #ffcc00; font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .topbar .right { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
    .topbar a { color: #ddd; text-decoration: none; padding: 5px 9px; border-radius: 6px; }
    .topbar a:hover { background: #222; color: #ffcc00; }
    .topbar a.logout { background: #e2001a; color: #fff; font-weight: 600; }
    .topbar a.logout:hover { background: #c70018; }

    main { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem; }
    h1 { font-size: 1.6rem; margin-bottom: 0.3rem; }
    .subtitle { color: #666; margin-bottom: 1.5rem; font-size: 0.92rem; }

    .tabs { display: flex; gap: 0.5rem; margin-bottom: 1.2rem; flex-wrap: wrap; }
    .tab {
      padding: 0.55rem 1rem; background: #ddd; border: none; border-radius: 8px;
      cursor: pointer; font-family: inherit; font-weight: 600; font-size: 0.9rem;
    }
    .tab.active { background: #ffc400; color: #1a1a1a; }
    .tab:hover:not(.active) { background: #ccc; }

    .panel { display: none; }
    .panel.active { display: block; }

    .upload-card {
      background: #fff; border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,0.06);
      margin-bottom: 1.2rem; overflow: hidden;
    }
    .upload-header {
      display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;
      padding: 0.9rem 1.1rem; background: #fafafa; border-bottom: 1px solid #eee;
    }
    .upload-header h3 { font-size: 1.05rem; flex: 1; min-width: 200px; }
    .upload-meta { font-size: 0.82rem; color: #888; display: flex; gap: 1rem; flex-wrap: wrap; }
    .upload-meta i { color: #ffc400; margin-right: 4px; }
    .btn {
      border: none; border-radius: 8px; padding: 0.5rem 0.9rem; font-size: 0.85rem;
      font-weight: 600; cursor: pointer; font-family: inherit;
      display: inline-flex; align-items: center; gap: 0.35rem;
    }
    .btn-download { background: #ffc400; color: #1a1a1a; }
    .btn-download:hover { background: #f5b301; }
    .btn-delete { background: #e2001a; color: #fff; }
    .btn-delete:hover { background: #c70018; }
    .btn-refresh { background: #333; color: #fff; margin-bottom: 1rem; }
    .btn-refresh:hover { background: #111; }
    .btn-save { background: #28a745; color: #fff; }
    .btn-save:hover { background: #218838; }

    .thumb-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
      gap: 0.5rem; padding: 1rem;
    }
    .thumb {
      position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden;
      background: #eee; cursor: pointer;
    }
    .thumb img, .thumb video {
      width: 100%; height: 100%; object-fit: cover; display: block;
    }
    .thumb .vid-badge {
      position: absolute; bottom: 4px; left: 4px;
      background: rgba(0,0,0,0.7); color: #fff; font-size: 0.65rem;
      padding: 1px 6px; border-radius: 4px;
    }
    .thumb .orig-name {
      position: absolute; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.65); color: #fff; font-size: 0.65rem;
      padding: 3px 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      opacity: 0; transition: opacity 0.2s;
    }
    .thumb:hover .orig-name { opacity: 1; }

    .empty {
      text-align: center; padding: 3rem 1rem; color: #888; background: #fff;
      border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,0.05);
    }
    .empty i { font-size: 3rem; color: #ccc; margin-bottom: 0.8rem; }

    .lightbox {
      display: none; position: fixed; inset: 0; z-index: 20000;
      background: rgba(0,0,0,0.92); align-items: center; justify-content: center;
      padding: 1rem;
    }
    .lightbox.show { display: flex; }
    .lightbox img, .lightbox video {
      max-width: 100%; max-height: 90vh; border-radius: 8px;
    }
    .lightbox .close {
      position: absolute; top: 1rem; right: 1rem; background: #fff; color: #111;
      border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.2rem;
      cursor: pointer;
    }

    .settings-card {
      background: #fff; border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,0.06);
      padding: 1.5rem; max-width: 600px;
    }
    .field { margin-bottom: 1.1rem; }
    .field label { display: block; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.92rem; }
    .field input {
      width: 100%; padding: 0.7rem 0.9rem; border: 2px solid #ddd; border-radius: 8px;
      font-size: 0.95rem; font-family: inherit;
    }
    .field input:focus { outline: none; border-color: #ffc400; }
    .field .hint { font-size: 0.78rem; color: #888; margin-top: 0.3rem; }
    .save-msg {
      padding: 0.7rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;
    }
    .save-msg.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .save-msg.err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    @media (max-width: 520px) {
      body { padding-top: 90px; }
      .thumb-grid { grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="name"><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($currentName) ?> (<?= htmlspecialchars($currentUser) ?>)</div>
    <div class="right">
      <a href="/fotos/admin/"><i class="fa-solid fa-grip"></i> Galerie-Admin</a>
      <a href="/bilder/" target="_blank"><i class="fa-solid fa-upload"></i> Upload-Seite</a>
      <a href="?logout=1" class="logout" onclick="if(!confirm('Wirklich abmelden?'))return false"><i class="fa-solid fa-right-from-bracket"></i> Abmelden</a>
    </div>
  </div>

  <main>
    <h1><i class="fa-solid fa-inbox" style="color:#ffc400;"></i> Bild-Eingang</h1>
    <p class="subtitle">Eingegangene Uploads von externen Wettkämpfen. Gruppiert nach Veranstaltung.</p>

    <div class="tabs">
      <button class="tab active" onclick="showTab('uploads',this)"><i class="fa-solid fa-images"></i> Uploads</button>
      <button class="tab" onclick="showTab('settings',this)"><i class="fa-solid fa-gear"></i> Einstellungen</button>
    </div>

    <div id="tab-uploads" class="panel active">
      <button class="btn btn-refresh" onclick="loadUploads()"><i class="fa-solid fa-rotate"></i> Aktualisieren</button>
      <div id="uploads"></div>
    </div>

    <div id="tab-settings" class="panel">
      <div class="settings-card">
        <?php if ($saveMsg): ?>
          <div class="save-msg <?= $saveOk ? 'ok' : 'err' ?>">
            <i class="fa-solid fa-<?= $saveOk ? 'circle-check' : 'triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($saveMsg) ?>
          </div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="save_settings">
          <div class="field">
            <label for="admin_password">Admin-Passwort ändern</label>
            <input type="password" id="admin_password" name="admin_password" placeholder="• • • • • • • •">
            <div class="hint">Leer lassen, um das aktuelle Passwort beizubehalten.</div>
          </div>
          <div class="field">
            <label for="email_to">E-Mail für Benachrichtigungen</label>
            <input type="email" id="email_to" name="email_to" value="<?= htmlspecialchars($CFG['email_to'] ?? '') ?>" placeholder="deine@email.at">
            <div class="hint">Hier bekommst du eine E-Mail, sobald jemand Bilder hochlädt.</div>
          </div>
          <div class="field">
            <label for="ntfy_topic">ntfy.sh Topic (Push)</label>
            <input type="text" id="ntfy_topic" name="ntfy_topic" value="<?= htmlspecialchars($CFG['ntfy_topic'] ?? '') ?>" placeholder="rvhard-bildereingang">
            <div class="hint">Topic-Name, den du in der ntfy-App abonniert hast. Leer = kein Push.</div>
          </div>
          <button type="submit" class="btn btn-save"><i class="fa-solid fa-floppy-disk"></i> Speichern</button>
        </form>
      </div>
    </div>
  </main>

  <div class="lightbox" id="lightbox" onclick="if(event.target===this)closeLB()">
    <button class="close" onclick="closeLB()"><i class="fa-solid fa-xmark"></i></button>
    <img id="lbImg" src="" alt="">
    <video id="lbVid" controls style="display:none;"></video>
  </div>

  <script>
    function showTab(name, btn) {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + name).classList.add('active');
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
      wrap.innerHTML = '<p style="text-align:center;color:#888;padding:2rem;">Lade…</p>';
      try {
        const r = await fetch('inbox.php?action=list');
        if (!r.ok) throw new Error('Server antwortet mit Status ' + r.status + ' (inbox.php)');
        const data = await r.json();
        if (!data.ok) throw new Error(data.msg || 'Fehler');
        const ups = data.uploads || [];
        if (ups.length === 0) {
          wrap.innerHTML = '<div class="empty"><i class="fa-solid fa-inbox"></i><p>Keine Uploads vorhanden.</p></div>';
          return;
        }
        wrap.innerHTML = ups.map(u => {
          const dt = new Date(u.datetime);
          const dateStr = dt.toLocaleString('de-AT', { dateStyle: 'medium', timeStyle: 'short' });
          return `
            <div class="upload-card" id="up-${u.id}">
              <div class="upload-header">
                <h3><i class="fa-solid fa-flag" style="color:#ffc400;"></i> ${escapeHtml(u.event)}</h3>
                <div class="upload-meta">
                  <span><i class="fa-regular fa-calendar"></i> ${dateStr}</span>
                  <span><i class="fa-solid fa-images"></i> ${u.count} Datei(en)</span>
                </div>
                <a class="btn btn-download" href="inbox.php?action=download&id=${encodeURIComponent(u.id)}"><i class="fa-solid fa-download"></i> ZIP</a>
                <button class="btn btn-delete" onclick="delUpload('${u.id}','${escapeHtml(u.event)}')"><i class="fa-solid fa-trash"></i> Löschen</button>
              </div>
              <div class="thumb-grid">
                ${u.files.map(f => {
                  const url = `inbox.php?action=preview&id=${encodeURIComponent(u.id)}&file=${encodeURIComponent(f.file)}`;
                  const isVid = f.type === 'video';
                  return `
                    <div class="thumb" onclick="openLB('${url}','${f.type}')">
                      ${isVid ? `<video muted playsinline><source src="${url}"></video><span class="vid-badge"><i class="fa-solid fa-play"></i></span>` : `<img src="${url}" alt="">`}
                      <span class="orig-name">${escapeHtml(f.original)}</span>
                    </div>`;
                }).join('')}
              </div>
            </div>`;
        }).join('');
      } catch (e) {
        wrap.innerHTML = `<p style="color:#e2001a;padding:1rem;background:#fff;border-radius:8px;">Fehler beim Laden: ${escapeHtml(e.message)}</p>`;
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
