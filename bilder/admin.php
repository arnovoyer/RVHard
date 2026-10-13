<?php
/* ============================================================
 *  RV HARD – BILDER-UPLOAD ADMIN INBOX (UI)
 *  Zeigt alle eingegangenen Uploads, ermöglicht Download (ZIP)
 *  und Löschen.
 * ============================================================ */
require_once(__DIR__ . '/../fotos/admin/auth2.php');
if (!fg2_logged()) {
    /* Weiterleitung zum Login */
    header('Location: /fotos/admin/login.php');
    exit;
}
$currentUser = fg2_user();
$currentName = fg2_displayname();
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

    /* Lightbox */
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
      <a href="/fotos/admin/?logout=1" class="logout" onclick="if(!confirm('Wirklich abmelden?'))return false"><i class="fa-solid fa-right-from-bracket"></i> Abmelden</a>
    </div>
  </div>

  <main>
    <h1><i class="fa-solid fa-inbox" style="color:#ffc400;"></i> Bild-Eingang</h1>
    <p class="subtitle">Eingegangene Uploads von externen Wettkämpfen. Gruppiert nach Veranstaltung.</p>
    <button class="btn btn-refresh" onclick="loadUploads()"><i class="fa-solid fa-rotate"></i> Aktualisieren</button>
    <div id="uploads"></div>
  </main>

  <div class="lightbox" id="lightbox" onclick="if(event.target===this)closeLB()">
    <button class="close" onclick="closeLB()"><i class="fa-solid fa-xmark"></i></button>
    <img id="lbImg" src="" alt="">
    <video id="lbVid" controls style="display:none;"></video>
  </div>

  <script>
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
        wrap.innerHTML = `<p style="color:#e2001a;padding:1rem;">Fehler: ${escapeHtml(e.message)}</p>`;
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
