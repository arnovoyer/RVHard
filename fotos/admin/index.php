<?php
/* ================================================================
 *  SBS GALERIE ADMIN – HAUPTSEITE (KEIN redirect! Loop-sicher!)
 *  Wenn nicht eingeloggt → FORMULAR DIREKT AUF DER SEITE + exit()
 *  Auf KEINEN FALL auf login.php weiterleiten → Loop-Gefahr!
 * ================================================================ */

require_once(__DIR__ . '/auth2.php');

/* Flash Msg holen */
$__msg = ''; $__mType = 'err';
if (!empty($_SESSION['fg2_msg']))  { $__msg = $_SESSION['fg2_msg'];  $__mType = ($_SESSION['fg2_mtype'] ?? 'err'); unset($_SESSION['fg2_msg'], $_SESSION['fg2_mtype']); }

/* WICHTIG! Nur WENN EINGELOGGT: Rest der Seite ausgeben! */
if (!fg2_logged()) {
    fg2_render_login_page($__msg, $__mType);
    exit; /* STOP! Kein Rest der Seite wird geladen wenn nicht eingeloggt! */
}

/* ---- Ab hier: NUR WENN EINGELOGGT! ---- */
$csrfToken   = fg2_csrf_token();
$currentUser = fg2_user();
$currentName = fg2_displayname();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>🔒 Foto Admin – Bilder taggen & events.json erzeugen | RV Hard</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- 🔥🔥 SUBDOMAIN KOMPATIBEL! RELATIVE PFAD-ANGABEN! -->
    <script>
        (function() {
            /* Admin liegt immer in <base>/admin/! → Base ist immer eine Ebene über Admin */
            const scriptHere = String(window.location.pathname || '/');
            /* /fotos/admin/... → base = /fotos
               /admin/... → base = '' (Subdomain Root) */
            let b = '';
            const m = scriptHere.match(/^(.*)\/admin\/?$/i);
            if (m && m[1]) b = m[1].replace(/\/+$/,'');
            /* Falls im Base nichts gefunden /index.html o.ä */
            if (!b && (scriptHere.indexOf('/fotos/admin/') === 0 || scriptHere.endsWith('/fotos/admin/'))) b = '/fotos';
            const assetsBase = (b === '' ? '../assets/' : b.replace(/\/fotos$/,'') + '/assets/');
            const galerieBase = (b === '' ? '../assets/' : b + '/assets/');
            const logo = (b === '' ? '../assets/' : (b === '/fotos' ? '../../assets/' : b.replace(/\/[^/]+$/,'') + '/assets/')) + 'img/logo/RV_Hard_Logo.webp';
            document.write(`<link rel="icon" type="image/png" href="${logo}">`);
            document.write(`<link rel="stylesheet" href="${assetsBase}style.css">`);
            document.write(`<link rel="stylesheet" href="${galerieBase}galerie.css">`);
        })();
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { padding-top: 70px; }

        /* Einfache Anleitung Box */
        .fg-guide {
            padding: 1.1rem 1.3rem; margin-bottom: 1.5rem;
            background: #e7f1ff; color: #052c65;
            border-left: 5px solid #0d6efd;
            border-radius: 10px;
        }
        .fg-guide ol { margin: 0.5rem 0 0 0; padding-left: 1.3rem; line-height: 1.75; }
        .fg-guide ol code { background: #fff; padding: 2px 5px; border-radius: 4px; }

        .fg-step { margin-bottom: 2rem; }
        .fg-step__header {
            display: flex; align-items: center; gap: 0.8rem;
            margin-bottom: 1rem;
        }
        .fg-step__num {
            width: 38px; height: 38px; flex: 0 0 38px;
            background: linear-gradient(135deg, #ffc107, #f5b301);
            color: #111; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
        }
        .fg-step__header h2 { margin: 0; font-size: 1.3rem; }

        .fg-grid-3 { display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        @media (max-width: 800px) { .fg-grid-3 { grid-template-columns: 1fr; } }

        .fg-event-pick {
            padding: 1rem; border: 2px solid #eee; border-radius: 10px; cursor:pointer;
            transition: all .18s ease; background: #fff;
        }
        .fg-event-pick:hover { border-color:#ffc107; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 193, 7, 0.15); }
        .fg-event-pick.active { border-color: #f5b301; background: #fff8e1; box-shadow: inset 0 0 0 2px #f5b301; }
        .fg-event-pick .cat { display:inline-block; padding: 2px 8px; color:#fff; font-size: 0.7rem; border-radius: 999px; font-weight:700; margin-bottom: 0.4rem; letter-spacing: 0.03em; }
        .fg-event-pick h3 { margin: 0.15rem 0; font-size: 1rem; color:#111; }
        .fg-event-pick small { color:#777; font-size:0.8rem; display:block; margin-top:2px;}
        .fg-event-pick .count { margin-top: 0.5rem; font-size: 0.85rem; color:#555; font-weight:600; }
        .fg-event-pick { position: relative; }
        .fg-event-pick.is-hidden { opacity: 0.6; border-style: dashed; }
        .fg-event-pick .hidebtn {
            position: absolute; top: 8px; right: 8px; z-index: 3;
            width: 30px; height: 30px; border-radius: 8px; border: 1px solid #ddd;
            background: #fff; color: #555; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; font-size: 13px;
            transition: all .15s;
        }
        .fg-event-pick .hidebtn:hover { border-color: #dc3545; color: #dc3545; }
        .fg-event-pick .hidebtn.on { background: #dc3545; border-color: #dc3545; color: #fff; }
        .fg-event-pick .hidebtn.on:hover { background:#bb2d3b; color:#fff; }
        .fg-event-pick .hiddenbadge {
            display:inline-block; margin-left:6px; padding: 1px 7px; border-radius: 999px;
            background:#dc3545; color:#fff; font-size:0.68rem; font-weight:700;
        }
        .fg-ne { background:#f7f7f7; border-radius:8px; padding:1rem; margin-bottom:1rem; }
        .fg-ne .row { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; }
        @media (max-width: 800px) { .fg-ne .row { grid-template-columns: 1fr; } }
        .fg-ne .actions { margin-top:0.75rem; display:flex; gap:0.5rem; flex-wrap:wrap; }

        /* Foto-Karte - VERBESSERT */
        .fg-photo-card {
            border: 2px solid #eee;
            border-radius: 10px; overflow: hidden; background: #fff;
            transition: all .2s ease; display: flex; flex-direction: column;
        }
        .fg-photo-card.selected { border-color: #ffc107; box-shadow: 0 4px 12px rgba(255, 193, 7, 0.25); }
        .fg-photo-card__header { position: relative; background: #111; }
        .fg-photo-card__header img { width:100%; height: 180px; object-fit:cover; display:block; }
        .fg-photo-card__select { position: absolute; top: 8px; left: 8px; width: 22px; height: 22px; cursor:pointer; accent-color: #f5b301; }
        .fg-photo-card__del {
            position: absolute; top: 8px; right: 8px; z-index: 3;
            width: 30px; height: 30px; border-radius: 8px; border: 0;
            background: rgba(220,53,69,0.92); color: #fff; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 13px; box-shadow: 0 2px 6px rgba(0,0,0,.35);
            transition: background .15s, transform .1s;
        }
        .fg-photo-card__del:hover { background: #dc3545; transform: scale(1.06); }
        .fg-photo-card__del:active { transform: scale(.96); }
        .fg-photo-card__body { padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .fg-photo-card__body label { display:block; font-size:0.7rem; color:#666; letter-spacing:0.04em; font-weight:600; margin-bottom: 0.15rem; }
        .fg-photo-card__body input {
            width: 100%; padding: 0.4rem 0.55rem; border: 2px solid #e0e0e0; border-radius: 6px;
            font-size: 0.85rem; font-family:'Poppins', sans-serif; box-sizing: border-box;
        }
        .fg-photo-card__body input.bibs {
            border-color: #ffd855; background: #fffce6; font-weight: 600; font-size: 0.9rem;
        }
        .fg-photo-card__title {
            font-size: 0.8rem; color: #555; padding-bottom: 6px; border-bottom: 1px dashed #eee;
            word-break: break-all;
        }

        /* Bulk Bar */
        .fg-bulkbar {
            display: none;
            padding: 1rem 1.2rem;
            background: #fff8e1;
            border: 2px solid #ffc107;
            border-radius: 10px;
            margin: 1.25rem 0;
            flex-wrap: wrap; gap: 14px;
            align-items: flex-end;
        }
        .fg-bulkbar.show { display: flex; }
        .fg-bulkbar__title { flex: 1 1 100%; font-weight:700; color:#111; display:flex; gap:0.6rem; align-items:center; flex-wrap: wrap; }
        .fg-bulkbar__title strong { color:#f5b301; font-size:1.05rem; }
        .fg-bulkbar__title .btns { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
        .fg-bulkbar .field { flex: 1 1 170px; display:flex; flex-direction:column; gap:0.2rem; }
        .fg-bulkbar .field label { font-size: 0.72rem; color:#666; font-weight: 600; }
        .fg-bulkbar .field input { padding: 0.55rem 0.7rem; border: 2px solid #ffd855; border-radius:6px; font-family:'Poppins'; font-size:0.9rem; background:#fffce6; }

        /* Dropzone */
        .fg-dropzone {
            border: 3px dashed #cfcfcf; border-radius: 12px;
            padding: 2.5rem 1.25rem; text-align: center;
            background: #fafafa; transition: all .2s ease; cursor: pointer; color: #666;
        }
        .fg-dropzone.dragover { border-color: #ffc107; background: #fff9e6; color: #111; transform: scale(1.01); }
        .fg-dropzone i { font-size: 2.5rem; color: #ffc107; margin-bottom: 0.75rem; display:block; }
        .fg-dropzone strong { font-size: 1.05rem; color: #111; display: block; margin-bottom: 0.25rem; }
        .fg-dropzone small { color: #888; }

        /* Foto Grid */
        .fg-photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 14px;
            margin-top: 1rem;
        }

        /* Export */
        .fg-export {
            padding: 1.5rem;
            background: linear-gradient(135deg, #0f5132, #157347);
            color: #fff;
            border-radius: 10px;
        }
        .fg-export .fg-step__header h2 { color:#fff; }
        .fg-export .fg-step__num { background:#fff;color:#157347; }
        .fg-export p { color: rgba(255,255,255,0.9); margin: 0 0 1rem 0; }
        .fg-export .fg-btn {
            background: #fff;
            color: #157347;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 700;
        }
        .fg-export small { color: rgba(255,255,255,0.75); display:block; margin-top: 0.75rem; font-size: 0.78rem; line-height:1.6; }

        /* Erfolg */
        .fg-success {
            display: none;
            margin-top: 1rem;
            padding: 1.25rem;
            background: #d1e7dd; color: #0f5132;
            border: 2px solid #157347;
            border-radius: 8px;
        }
        .fg-success.show { display: block; }
        .fg-success h3 { margin: 0 0 0.5rem 0; color: #0f5132; }
        .fg-success ol { margin: 0; padding-left: 1.25rem; line-height: 1.75; }

        /* FTP Info Box */
        .fg-ftp-info {
            padding: 1.25rem; background: #fff3cd;
            border: 2px dashed #ffc107; border-radius: 10px;
            margin-bottom: 1.5rem; color: #7a5d00;
        }
        .fg-ftp-info h3 { margin: 0 0 0.5rem 0; color: #7a5d00; font-size: 1.05rem; }
        .fg-ftp-info ul { margin: 0; padding-left: 1.25rem; line-height: 1.75; }
        .fg-ftp-info code { background:#fff; padding: 2px 5px; border-radius:4px; }

        /* ===== ADMIN LIGHTBOX (große Ansicht + Bearbeiten) ===== */
        .admin-lb {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.92);
            display: none; flex-direction: column; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .admin-lb.show { display: flex; }
        .admin-lb__topbar {
            position: absolute; top: 0; left: 0; right: 0;
            display:flex; justify-content: space-between; align-items: center;
            padding: 0.8rem 1.2rem; color: #fff;
            background: rgba(0,0,0,0.4);
        }
        .admin-lb__topbar .title {
            font-weight: 600; font-size: 0.95rem;
            max-width: 60%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .admin-lb__topbar .title small { color: #aaa; margin-left: 0.5rem;}
        .admin-lb__topbar__actions { display:flex; gap: 8px; }
        .admin-lb__btn {
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            color: #fff; padding: 0.5rem 0.9rem; border-radius: 6px; cursor:pointer;
            font-family:'Poppins',sans-serif; font-size: 0.85rem;
            transition: all .15s ease;
        }
        .admin-lb__btn:hover { background: rgba(255,193,7,0.25); border-color: #ffc107; color:#ffc107;}
        .admin-lb__btn.primary { background: #ffc107; color:#111; border-color:#ffc107; font-weight: 700;}
        .admin-lb__btn.primary:hover { background:#f5b301; color:#000;}
        .admin-lb__btn.danger { background: #dc3545; border-color:#dc3545; color:#fff; font-weight: 700;}
        .admin-lb__btn.danger:hover { background:#bb2d3b; border-color:#bb2d3b; color:#fff;}

        .admin-lb__body {
            display:flex; gap: 1.2rem; max-width: 1600px; width:100%;
            max-height: calc(100vh - 160px); align-items: stretch;
        }
        .admin-lb__imgwrap {
            flex: 1 1 auto; display: flex; align-items: center; justify-content: center;
            background: #000; border-radius: 10px; overflow: hidden;
            min-height: 400px;
        }
        .admin-lb__imgwrap img { max-width: 100%; max-height: calc(100vh - 160px); object-fit: contain; }
        .admin-lb__sidebar {
            flex: 0 0 360px; background: #fff; border-radius: 10px;
            padding: 1.2rem; overflow-y: auto;
            display:flex; flex-direction: column; gap: 14px;
        }
        .admin-lb__sidebar h3 { margin:0 0 0.3rem; font-size: 1.05rem; color: #111;}
        .admin-lb__sidebar .nav {
            display:flex; justify-content: space-between; gap: 8px;
            padding-bottom: 10px; border-bottom: 1px solid #eee;
        }
        .admin-lb__sidebar label { display:block; font-size:0.75rem; color:#666; letter-spacing:0.03em; font-weight:700; margin-bottom: 0.2rem;}
        .admin-lb__sidebar input, .admin-lb__sidebar textarea {
            width:100%; padding:0.5rem 0.7rem; border: 2px solid #e0e0e0; border-radius: 6px;
            font-family:'Poppins',sans-serif; font-size:0.9rem; box-sizing:border-box;
        }
        .admin-lb__sidebar textarea { min-height: 70px; resize: vertical; }
        .admin-lb__sidebar input.bibs {
            border-color: #ffd855; background: #fffce6; font-weight: 600;
        }
        .admin-lb__sidebar .hint { font-size: 0.75rem; color: #888; line-height: 1.5;}

        .admin-lb__navbtn {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(0,0,0,0.55); border: 2px solid rgba(255,255,255,0.3);
            color: #fff; width: 48px; height: 48px;
            border-radius: 50%; font-size: 1.3rem; cursor: pointer;
            display:flex; align-items: center; justify-content: center;
            transition: all .15s ease; z-index: 10000;
        }
        .admin-lb__navbtn:hover { background: #ffc107; color:#111; border-color:#ffc107; }
        .admin-lb__navbtn.prev { left: 1.5rem;}
        .admin-lb__navbtn.next { right: 1.5rem;}

        /* ---- Startnummern-Modus: Fortschritt + Speicher-Status ---- */
        .admin-lb__progress { margin-top: 0.5rem; }
        .admin-lb__progressbar { height: 8px; background: #e9ecef; border-radius: 999px; overflow: hidden; }
        .admin-lb__progressbar span { display: block; height: 100%; width: 0%; background: linear-gradient(90deg,#ffc107,#f5b301); transition: width .25s ease; }
        .admin-lb__progresstext { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.75rem; color: #666; margin-top: 0.35rem; }
        .admin-lb__savestatus { font-weight: 700; }
        .admin-lb__savestatus.ok { color: #198754; }
        .admin-lb__savestatus.warn { color: #b8860b; }

        /* ---- Einstieg in den Startnummern-Modus ---- */
        .fg-bibmode { display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap; margin: 0.9rem 0 0.2rem; }
        .fg-bibmode__hint { font-size: 0.8rem; color: #666; }
        .fg-bibmode__hint kbd, .admin-lb__sidebar .hint kbd {
            background: #f1f1f1; border: 1px solid #ccc; border-bottom-width: 2px;
            border-radius: 5px; padding: 1px 6px; font-size: 0.78rem; font-family: inherit; color: #333;
        }

        /* ===== UPLOAD / PUBLISH STATUS BOX ===== */
        .fg-publish {
            padding: 1.5rem;
            background: linear-gradient(135deg, #0f5132, #157347);
            color: #fff;
            border-radius: 10px;
        }
        .fg-publish .fg-step__header h2 { color:#fff; }
        .fg-publish .fg-step__num { background:#fff;color:#157347; }
        .fg-publish p { color: rgba(255,255,255,0.9); margin: 0 0 1rem 0; }
        .fg-publish .fg-btn {
            background: #ffc107;
            color: #111;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 700;
        }
        .fg-publish .fg-btn[disabled] { opacity: 0.6; cursor: not-allowed; }
        .fg-publish small { color: rgba(255,255,255,0.78); display:block; margin-top: 0.75rem; font-size: 0.78rem; line-height:1.6; }
        .fg-publish .server-status {
            padding: 0.7rem 0.9rem; background: rgba(255,255,255,0.1);
            border-radius: 7px; margin-bottom: 1rem;
            display:flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;
            font-size: 0.85rem;
        }
        .fg-publish .server-status .dot {
            width: 12px; height: 12px; border-radius: 50%;
            flex: 0 0 12px;
        }
        .fg-publish .server-status .dot.ok { background: #198754; box-shadow: 0 0 0 3px rgba(25,135,84,0.4); }
        .fg-publish .server-status .dot.err { background: #dc3545; }
        .fg-publish .server-status .dot.warn { background: #ffc107; }

        .progress-wrap {
            width:100%; background: rgba(255,255,255,0.15);
            border-radius: 999px; overflow: hidden;
            height: 14px; margin-top: 1rem; display: none;
        }
        .progress-wrap.show { display: block; }
        .progress-bar {
            height: 100%; background: linear-gradient(90deg, #ffc107, #f5b301);
            width: 0%; transition: width .3s ease;
        }
        .progress-text { font-size: 0.8rem; margin-top: 0.4rem; color: rgba(255,255,255,0.85);}

        @media (max-width: 900px) {
            .admin-lb__body { flex-direction: column; }
            .admin-lb__sidebar { flex: 0 0 auto; width:100%; max-height: 45vh; }
            .admin-lb__imgwrap { min-height: 220px; }
            .admin-lb__navbtn { width: 40px; height: 40px; font-size: 1rem; top: auto; bottom: 140px;}
            .admin-lb__navbtn.prev { left: 0.6rem;}
            .admin-lb__navbtn.next { right: 0.6rem;}
        }

        @media (max-width: 600px) {
            .fg-photo-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
            .fg-photo-card__header img { height: 140px; }
        }
    </style>
</head>
<body>

<!-- Admin Session Topbar -->

<!-- Admin Session Topbar (auth2.php standalone) -->
<div id="fg-admin-sessionbar" style="position:fixed;top:0;left:0;right:0;z-index:10000;background:#111;border-bottom:2px solid var(--rv-gelb,#ffcc00);padding:9px 18px;display:flex;align-items:center;gap:14px;font-family:'Inter',system-ui,sans-serif;font-size:13px;color:#fff">
  <div style="display:flex;align-items:center;gap:8px;color:#ffcc00;font-weight:600">
    <i class="fa-solid fa-user-shield"></i>
    <span><?= htmlspecialchars($currentName) ?></span>
    <span style="color:#888;font-weight:400">(<?= htmlspecialchars($currentUser) ?>)</span>
  </div>
  <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <a href="../" style="color:#ddd;text-decoration:none;padding:5px 9px;border-radius:6px"><i class="fa-solid fa-image"></i> Galerie</a>
    <a href="/bilder/admin.php" style="color:#ddd;text-decoration:none;padding:5px 9px;border-radius:6px"><i class="fa-solid fa-inbox"></i> Bild-Eingang</a>
    <a href="?logout=1" style="background:#e2001a;color:#fff;text-decoration:none;padding:5px 10px;border-radius:6px;font-weight:600"
       onclick="if(!confirm('Wirklich abmelden?'))return false"><i class="fa-solid fa-right-from-bracket"></i> Abmelden</a>
  </div>
  <input type="hidden" id="fg-admin-csrf" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
</div>
<style>
  body { padding-top: 60px !important; }
  @media(max-width:520px){ body { padding-top: 90px !important; } }
  #fg-admin-sessionbar a[href="../"]:hover { background:#222;color:#ffcc00 }
  #fg-admin-sessionbar a[href="?logout=1"]:hover { background:#c70018 }
</style>


    <header><nav id="navigation-container"></nav></header>

    <main>
    <section class="fg-container">

        <!-- KONNTE NICHT ANDERS: FILE PROTOKOLL STOPPER! -->
        <div id="proto-warn" style="display:none;padding:1rem 1.25rem;background:#f8d7da;border:2px solid #dc3545;border-radius:10px;color:#842029;margin-bottom:1.2rem;line-height:1.55;">
            <h3 style="margin:0 0 0.5rem 0;color:#842029;"><i class="fa-solid fa-triangle-exclamation"></i> ⛔ Admin funktioniert hier NICHT!</h3>
            <p style="margin:0 0 0.6rem 0;">
                Du hast diese Seite per <strong>Doppelklick</strong> als <code>file:///C:/Users/...</code> geöffnet.
                Das <strong>geht NICHT</strong> — per file:// lassen sich <strong>keine Bilder auf den Server laden</strong> und auch <strong>keine PHP-Skripte ausführen</strong>.
                Der Browser blockiert das aus Sicherheitsgründen (genau diese Meldung: <em>"Sicherheitsfehler: darf file:/// nicht laden"</em>).
            </p>
            <p style="margin:0 0 0.8rem 0;">
                <strong>Richtig so öffnen (unbedingt):</strong><br>
                👉 <a href="https://rv-hard.at/fotos/admin/" target="_blank" rel="noopener" style="color:#842029;text-decoration:underline;font-weight:bold;">https://rv-hard.at/fotos/admin/</a>
            </p>
            <p style="margin:0;font-size:0.88rem;color:#7a2430;">
                Lokal auf PC zu Hause zum Testen: <code>http://localhost/rv-hard/fotos/admin/</code> oder XAMPP/WAMP starten.
                Bilder hochladen und taggen funktioniert NUR über <code>http://</code> oder <code>https://</code>.
            </p>
        </div>
        <script>
            if (location.protocol === 'file:') {
                document.getElementById('proto-warn').style.display = 'block';
                document.querySelectorAll('.fg-step, .fg-section, .fg-publish-box').forEach(el => {
                    if (!el.id || el.id !== 'proto-warn') el.style.opacity = '0.35';
                });
            }
        </script>

        <!-- KURZER HINWEIS -->
        <div class="fg-ftp-info" style="padding: 0.8rem 1rem; background: #fff3cd; border: 1px dashed #ffc107; border-radius: 8px; color: #7a5d00; margin-bottom: 1.2rem;">
            <i class="fa-solid fa-circle-info" style="color:#f5b301;"></i>
            <strong>So geht's:</strong> Disziplin wählen → Bilder reinziehen → klicken zum Bearbeiten → veröffentlichen.
        </div>

        <!-- 1. EVENT WÄHLEN -->
        <div class="fg-section fg-step">
            <div class="fg-step__header">
                <div class="fg-step__num">1</div>
                <h2>Welches Event möchtest du jetzt bearbeiten?</h2>
            </div>
            <div style="padding: 0.75rem 1rem 1rem; background: #f7f7f7; border-radius: 8px; margin-bottom: 1rem; display:flex; align-items:flex-end; gap:0.75rem; flex-wrap:wrap;">
                <div>
                    <label class="fg-label" for="year-select">Jahr</label>
                    <select class="fg-input" id="year-select" style="max-width:240px;">
                        <option value="2026" selected>2026</option>
                    </select>
                </div>
                <button class="fg-btn fg-btn--outline" id="new-event-btn" type="button" style="padding:0.6rem 1rem;">
                    <i class="fa-solid fa-plus"></i> Neues Event erstellen
                </button>
            </div>

            <!-- Formular: eigenes Event anlegen -->
            <div class="fg-ne" id="new-event-form" style="display:none;">
                <div class="row">
                    <div>
                        <label class="fg-label" for="ne-name">Name *</label>
                        <input class="fg-input" id="ne-name" type="text" placeholder="z. B. Mountainbike Rennen Bregenzerwald" autocomplete="off">
                    </div>
                    <div>
                        <label class="fg-label" for="ne-date">Datum</label>
                        <input class="fg-input" id="ne-date" type="date">
                    </div>
                    <div>
                        <label class="fg-label" for="ne-location">Ort</label>
                        <input class="fg-input" id="ne-location" type="text" placeholder="z. B. Alberschwende" autocomplete="off">
                    </div>
                    <div>
                        <label class="fg-label" for="ne-distance">Distanz (optional)</label>
                        <input class="fg-input" id="ne-distance" type="text" placeholder="z. B. 45 km · 900 Hm" autocomplete="off">
                    </div>
                    <div>
                        <label class="fg-label" for="ne-subtype">Disziplin/Typ</label>
                        <select class="fg-input" id="ne-subtype">
                            <option value="">Allgemein</option>
                            <option value="bergrennen">Bergrennen</option>
                            <option value="ezf">Einzelzeitfahren</option>
                            <option value="kriterium">Stadtkriterium</option>
                        </select>
                    </div>
                    <div>
                        <label class="fg-label" for="ne-year">Jahr</label>
                        <input class="fg-input" id="ne-year" type="number" min="2000" max="2100">
                    </div>
                </div>
                <div style="margin-top:0.75rem;">
                    <label class="fg-label" for="ne-desc">Beschreibung (optional)</label>
                    <input class="fg-input" id="ne-desc" type="text" placeholder="Kurzer Text für die Event-Karte" autocomplete="off">
                </div>
                <div class="actions">
                    <button class="fg-btn" id="ne-save" type="button"><i class="fa-solid fa-check"></i> Event anlegen</button>
                    <button class="fg-btn fg-btn--outline" id="ne-cancel" type="button">Abbrechen</button>
                </div>
            </div>

            <div id="discipline-grid" class="fg-grid-3"></div>
        </div>

        <!-- 2. BILDER REINZIEHEN -->
        <div class="fg-section fg-step">
            <div class="fg-step__header">
                <div class="fg-step__num">2</div>
                <h2>Bilder per Drag & Drop hier reinziehen (vom FTP / PC-Ordner!)</h2>
            </div>
            <label for="file-input">
                <div id="dropzone" class="fg-dropzone">
                    <i class="fa-solid fa-file-image"></i>
                    <strong>Bilder hier reinziehen oder klicken zum Auswählen</strong>
                    <small>Am besten: GANZEN FTP-Ordner per Explorer auswählen → alle Bilder markieren → reinziehen!</small>
                </div>
            </label>
            <input type="file" id="file-input" multiple accept="image/*" style="display:none;">

            <!-- Bulk Bar -->
            <div id="bulkbar" class="fg-bulkbar">
                <div class="fg-bulkbar__title">
                    <i class="fa-solid fa-layer-group"></i> Massen-Tagging:
                    <strong><span id="sel-count">0</span> Bilder ausgewählt</strong>
                    <div class="btns">
                        <button class="fg-btn fg-btn--outline" id="select-all"><i class="fa-regular fa-square-check"></i> Alle</button>
                        <button class="fg-btn fg-btn--outline" id="select-none"><i class="fa-regular fa-square"></i> Keine</button>
                    </div>
                </div>
                <div class="field">
                    <label>🏁 STARTNUMMERN (Komma getrennt!)</label>
                    <input type="text" id="bulk-bib" placeholder="42, 12, 88, 333" autocomplete="off">
                </div>
                <div class="field" style="flex:0 0 auto;">
                    <label>&nbsp;</label>
                    <button class="fg-btn" id="bulk-apply" style="padding:0.6rem 1.2rem;">
                        <i class="fa-solid fa-tags"></i> Anwenden
                    </button>
                </div>
            </div>

            <!-- 🎯 Startnummern-Modus: reiner Tastatur-Workflow (Bild groß + Nummer tippen) -->
            <div class="fg-bibmode">
                <button class="fg-btn" id="bib-mode-btn" type="button">
                    <i class="fa-solid fa-keyboard"></i> 🎯 Startnummern-Modus starten
                </button>
                <span class="fg-bibmode__hint">
                    Bild groß · Nummer tippen · <kbd>Enter</kbd> speichern &amp; weiter · <kbd>←</kbd> <kbd>→</kbd> blättern
                </span>
            </div>

            <div id="photo-grid" class="fg-photo-grid"></div>
            <div id="empty-msg" class="fg-empty" style="margin-top:1rem;">
                <strong>Keine Bilder geladen.</strong><br>Bilder hier reinziehen!
            </div>
        </div>

        <!-- 3. VERÖFFENTLICHEN -->
        <div class="fg-publish fg-step">
            <div class="fg-step__header">
                <div class="fg-step__num">3</div>
                <h2 style="margin:0;color:#fff;">Veröffentlichen</h2>
            </div>

            <div class="server-status" id="server-status">
                <div class="dot" id="server-dot"></div>
                <div id="server-msg">Prüfe Server…</div>
            </div>

            <button id="publish-btn" class="fg-btn" disabled>
                <i class="fa-solid fa-cloud-arrow-up"></i> 🚀 Auf Server speichern &amp; veröffentlichen
            </button>
            <small>
                Bilder + events.json gehen direkt auf den Server und sind sofort öffentlich sichtbar.
            </small>

            <div class="progress-wrap" id="progress-wrap">
                <div class="progress-bar" id="progress-bar"></div>
            </div>
            <div class="progress-text" id="progress-text" style="display:none;"></div>

            <div id="success-msg" class="fg-success" style="margin-top:1rem; background:#fff3cd !important; color:#7a5d00 !important; border-color:#ffc107 !important; display:none;">
                <h3 style="color:#155724 !important;"><i class="fa-solid fa-circle-check"></i> ✅ Fertig!</h3>
                <ol style="color:#333;">
                    <li>Bilder gespeichert</li>
                    <li>Galerie ist jetzt öffentlich sichtbar: <a id="success-link" style="color:#0f5132;font-weight:700;" href="../index.html" target="_blank">Galerie öffnen →</a></li>
                </ol>
            </div>
        </div>

    </section>
    </main>

    <!-- ===== ADMIN LIGHTBOX (große Bildansicht) ===== -->
    <div id="admin-lightbox" class="admin-lb" role="dialog" aria-modal="true">
        <div class="admin-lb__topbar">
            <div class="title">
                <i class="fa-solid fa-image" style="color:#ffc107;"></i>
                <span id="lb-filename">Bildname</span>
                <small id="lb-position"></small>
            </div>
            <div class="admin-lb__topbar__actions">
                <button class="admin-lb__btn" id="lb-prev-top"><i class="fa-solid fa-arrow-left"></i> Zurück</button>
                <button class="admin-lb__btn primary" id="lb-save-top">
                    <i class="fa-solid fa-floppy-disk"></i> Speichern & Weiter
                </button>
                <button class="admin-lb__btn" id="lb-close" title="Schließen (ESC)">
                    <i class="fa-solid fa-xmark"></i> Schließen
                </button>
            </div>
        </div>

        <button class="admin-lb__navbtn prev" id="lb-prev" title="Vorheriges (←)">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <button class="admin-lb__navbtn next" id="lb-next" title="Nächstes (→)">
            <i class="fa-solid fa-chevron-right"></i>
        </button>

        <div class="admin-lb__body">
            <div class="admin-lb__imgwrap">
                <img id="lb-img" src="" alt="Vorschau">
            </div>
            <div class="admin-lb__sidebar">
                <h3><i class="fa-solid fa-tags" style="color:#f5b301;"></i> Bild bearbeiten</h3>
                <div>
                    <label>🏁 STARTNUMMER ODER KATEGORIE</label>
                    <input type="text" id="lb-bib" class="bibs" placeholder="42, 12 oder U7-U9" autocomplete="off">
                </div>

                <div style="display:flex; gap:8px; padding-top:4px;">
                    <button class="admin-lb__btn" id="lb-copy-bib" style="flex:1;">
                        <i class="fa-solid fa-copy"></i> Kopieren
                    </button>
                    <button class="admin-lb__btn primary" id="lb-save-sidebar" style="flex:2;">
                        <i class="fa-solid fa-floppy-disk"></i> Speichern &amp; Weiter
                    </button>
                </div>
                <div style="padding-top:4px;">
                    <button class="admin-lb__btn danger" id="lb-delete" style="width:100%;">
                        <i class="fa-solid fa-trash"></i> Bild löschen
                    </button>
                </div>
                <div class="admin-lb__progress">
                    <div class="admin-lb__progressbar"><span id="lb-progress-fill"></span></div>
                    <div class="admin-lb__progresstext">
                        <span id="lb-progress-text">0 / 0 getaggt</span>
                        <span id="lb-savestatus" class="admin-lb__savestatus"></span>
                    </div>
                </div>
                <div class="hint" style="margin-top:0;">
                    ⌨️ <kbd>Enter</kbd> speichern &amp; weiter · <kbd>←</kbd> <kbd>→</kbd> blättern · <kbd>Entf</kbd> löschen · <kbd>ESC</kbd> schließen
                </div>
            </div>
        </div>
    </div>

    <footer id="site-footer"></footer>

    <script>
    /* ============================================================
     *  SBS GALERIE ADMIN – V3.2
     *  Upload im Tab → Startnummern → Publish-Button → automatisch!
     *  (Alter V3.1 Code mit FTP / JSON-Download siehe unten ausgeklammert)
     * ============================================================ */

    /* ========== [ARCHIVIERT V3.1] ALT: JSON-DOWNLOAD + FTP UPLOAD
     *          Nicht mehr benötigt – jetzt alles über upload.php!
     * ------------------------------------------------------------------
     *  $exportBtn.addEventListener('click', async () => { ... });
     *  Ablauf alt: Browser → events.json runterladen → FTP uploaden
     *  Ablauf neu: Publish Button → upload.php macht alles automatisch
     * ------------------------------------------------------------------
     *  (Kompletter alter Code in project_memory / Git history)
     * ========== [ENDE ARCHIVIERT V3.1] */

    /* ===== INIT NAV + FOOTER ===== */
    (async () => {
        try {
            const r = await fetch('/assets/navigationneu.html');
            if (r.ok) document.getElementById('navigation-container').innerHTML = await r.text();
            if (typeof initNavigationMenu === 'function') initNavigationMenu();
        } catch(e) {}
        try {
            const r = await fetch('/assets/footer.html');
            if (r.ok) document.getElementById('site-footer').innerHTML = await r.text();
        } catch(e) {}
    })();

    /* ===== DATEN: 3 DISZIPLINEN PRO JAHR ===== */
    const DISCS = [
        { key: 'bergrennen-wolfurt-buch', name: 'Bergrennen Wolfurt → Buch', short: 'Bergrennen Wolfurt-Buch', cat: 'BERGRENNEN', color: '#198754', icon: 'fa-mountain-sun',
          location: 'Wolfurt → Buch', distance: '12,8 km · 690 Hm', desc: 'Bergrennen Wolfurt nach Buch. Suche nach deiner Startnummer!' },
        { key: 'kriterium-kammgarn-hard', name: 'Kriterium Kammgarn Hard', short: 'Kriterium Kammgarn Hard', cat: 'KRITERIUM', color: '#dc3545', icon: 'fa-flag-checkered',
          location: 'Kammgarn Areal, Hard', distance: '50 Runden · 75 km', desc: 'Stadtkurs Kriterium in Hard am Kammgarn-Gelände. Suche nach deiner Startnummer!' },
        { key: 'ezf-rohrspitz-fussach', name: 'Einzelzeitfahren Rohrspitz Fußach', short: 'EZF Rohrspitz Fußach', cat: 'EZF', color: '#0d6efd', icon: 'fa-stopwatch',
          location: 'Rohrspitz, Fußach (Bodensee)', distance: '9,2 km · 45 Hm', desc: 'Einzelzeitfahren entlang des Bodensees in Fußach. Suche nach deiner Startnummer!' }
    ];

    let currentEventId = '';
    let uploadedPhotos = []; /* [{ id, file, previewUrl, filename, bibNumbers, _selected, _wasExisting, src }] */
    let _objectUrls = new Set(); /* Tracking für URL.revokeObjectURL (Memory Leak verhindern!) — NUR Blob: URLs! */
    let idCounter = 0;
    let lbCurrentIndex = -1;
    let lbCurrentList = [];
    let _fgEventsCachePromise = null; /* events.json Cache-Promise → nur 1x laden */

    /* ===== Helper: Server events.json 1x laden (für bestehende Fotos!) ===== */
    function fgLoadEventsJson() {
        if (_fgEventsCachePromise) return _fgEventsCachePromise;
        _fgEventsCachePromise = (async () => {
            try {
                const r = await fetch('../data/events.json?_=' + Date.now(), { cache: 'no-store' });
                if (!r.ok) return [];
                const raw = await r.json();
                if (!Array.isArray(raw)) return [];
                return raw.filter(e => e && !e._schemaVersion);
            } catch (e) { console.warn('[Admin] events.json konnte nicht geladen werden:', e); return []; }
        })();
        return _fgEventsCachePromise;
    }
    function basename(path) { return String(path||'').split(/[\\/]/).filter(Boolean).pop() || String(path||''); }
    /* Bestehende Fotos aus events.json zum aktuellen Event in uploadedPhotos anhängen (OHNE Duplikate!) */
    async function fgAttachExistingPhotos(eventId) {
        if (!eventId) return 0;
        const events = await fgLoadEventsJson();
        const ev = events.find(e => e.id === eventId);
        if (!ev || !Array.isArray(ev.photos) || ev.photos.length === 0) return 0;
        const currentForEvent = uploadedPhotos.filter(p => p._event === eventId);
        let added = 0;
        for (const ph of ev.photos) {
            if (!ph || !ph.src) continue;
            const fname = basename(ph.src);
            const already = currentForEvent.find(p => {
                const pf = basename(p.filename || '');
                const ps = basename(p.src || '');
                return (pf && pf === fname) || (ps && ps === fname);
            });
            if (already) continue; /* Bereits drin (als Upload oder alt!) → nicht doppelt! */
            const preview = ph.thumbnail || ph.src;
            uploadedPhotos.push({
                id: `p${++idCounter}`, _event: eventId,
                file: null, /* KEIN Upload! Schon auf Server! */
                previewUrl: preview,
                filename: fname,
                src: ph.src, /* WICHTIG für photosMeta! */
                thumbnail: ph.thumbnail || null,
                display: ph.display || null,
                bibNumbers: Array.isArray(ph.bibNumbers) ? ph.bibNumbers.slice() : [],
                _selected: true,
                _wasExisting: true
            });
            added++;
        }
        return added;
    }

    /* ============ SAUBERER MEMORY CLEANUP (Page Hide / Unload) ============ */
    const _revokeAll = () => {
        try {
            _objectUrls.forEach(u => { if (String(u).startsWith('blob:')) URL.revokeObjectURL(u); });
            _objectUrls.clear();
            uploadedPhotos.forEach(p => {
                if (p.previewUrl && String(p.previewUrl).startsWith('blob:')) {
                    try { URL.revokeObjectURL(p.previewUrl); } catch(e){}
                }
            });
        } catch(e){}
    };
    window.addEventListener('pagehide', _revokeAll);
    window.addEventListener('beforeunload', _revokeAll);

    /* ===== EVENT WAHL ===== */
    const $yearSel = document.getElementById('year-select');
    const $grid = document.getElementById('discipline-grid');

    function eventIdFor(year, key) { return `sbs-${year}-${key}`; }
    function folderFor(year, key) { return `sbs-${year}-${key}`; }

    const SUBTYPE_ICON = { bergrennen: 'fa-mountain-sun', ezf: 'fa-stopwatch', kriterium: 'fa-flag-checkered' };

    let serverEventsById = {};   /* id → Event-Objekt aus events.json */
    let _pickerItems = [];       /* aktuelle Auswahl-Liste (Standard + eigene Events) */
    let _busyEvent = false;      /* verhindert Doppel-Klicks beim Anlegen/Toggeln */

    function fgEsc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    /* Server-Events (events.json) NEU einlesen */
    async function fgRefreshServerEvents() {
        _fgEventsCachePromise = null;
        const events = await fgLoadEventsJson();
        serverEventsById = {};
        (events || []).forEach(ev => { if (ev && ev.id) serverEventsById[String(ev.id)] = ev; });
    }

    /* Jahres-Dropdown mit allen vorhandenen Jahren füllen */
    function fgSyncYearOptions(prefer) {
        const cur = prefer || $yearSel.value || String(new Date().getFullYear());
        const years = new Set();
        Array.from($yearSel.options).forEach(o => years.add(o.value));
        Object.values(serverEventsById).forEach(ev => {
            const y = String(ev.parentId || ev.id || '').match(/20\d{2}/)?.[0];
            if (y) years.add(y);
        });
        years.add(cur);
        const sorted = Array.from(years).filter(Boolean).sort((a, b) => String(b).localeCompare(String(a)));
        $yearSel.innerHTML = sorted.map(y => `<option value="${y}"${y === cur ? ' selected' : ''}>${y}</option>`).join('');
    }

    /* Auswahlliste bauen: Server-Events des Jahres + noch nicht angelegte Standard-Disziplinen */
    function buildPickerItems() {
        const year = String($yearSel.value);
        const items = [];
        const seen = new Set();
        const discByKey = {};
        DISCS.forEach(d => { discByKey[d.key] = d; });

        Object.values(serverEventsById).forEach(ev => {
            const y = String(ev.parentId || ev.id || '').match(/20\d{2}/)?.[0] || '';
            if (y !== year) return;
            const key = String(ev.slug || ev.id).replace(/^sbs-\d{4}-/, '');
            const d = discByKey[key];
            seen.add(String(ev.id));
            items.push({
                id: String(ev.id), key: key, folder: ev.folder || ev.id,
                custom: !!ev.custom, hidden: !!ev.hidden,
                name: ev.name || ev.shortName || ev.id,
                short: ev.shortName || ev.name || ev.id,
                cat: d ? d.cat : (ev.custom ? 'EVENT' : 'SBS'),
                color: d ? d.color : (ev.custom ? '#6f42c1' : '#343a40'),
                icon: d ? d.icon : (SUBTYPE_ICON[ev.subtype] || 'fa-camera-retro'),
                location: ev.location || '', distance: ev.distance || '',
                desc: ev.description || '',
                serverPhotos: Array.isArray(ev.photos) ? ev.photos.length : 0,
                onServer: true
            });
        });
        DISCS.forEach(d => {
            const id = eventIdFor(year, d.key);
            if (seen.has(id)) return;
            items.push({
                id: id, key: d.key, folder: folderFor(year, d.key),
                custom: false, hidden: false, name: d.name, short: d.short,
                cat: d.cat, color: d.color, icon: d.icon, location: d.location,
                distance: d.distance, desc: d.desc, serverPhotos: 0, onServer: false
            });
        });
        return items;
    }

    function renderEvents() {
        _pickerItems = buildPickerItems();
        $grid.innerHTML = _pickerItems.map(it => {
            const active = (it.id === currentEventId) ? 'active' : '';
            const hidden = it.hidden;
            const toggleBtn = it.onServer
                ? `<button class="hidebtn ${hidden ? 'on' : ''}" data-hide="${fgEsc(it.id)}" data-hidden="${hidden ? 1 : 0}" title="${hidden ? 'Einblenden' : 'Ausblenden'}"><i class="fa-solid ${hidden ? 'fa-eye-slash' : 'fa-eye'}"></i></button>`
                : '';
            const badge = hidden ? '<span class="hiddenbadge">AUSGEBLENDET</span>' : '';
            const loaded = uploadedPhotos.filter(p => p._event === it.id).length;
            const total = Math.max(loaded, it.serverPhotos || 0);
            return `
                <div class="fg-event-pick ${active} ${hidden ? 'is-hidden' : ''}" data-id="${fgEsc(it.id)}">
                    ${toggleBtn}
                    <span class="cat" style="background:${it.color};">${it.cat}</span>${badge}
                    <h3><i class="fa-solid ${it.icon}" style="color:#f5b301;"></i> ${fgEsc(it.short)}</h3>
                    ${it.location ? `<small>${fgEsc(it.location)}</small>` : ''}
                    <small style="color:${it.custom ? '#6f42c1' : '#0d6efd'}; display:block;"><i class="fa-solid fa-folder"></i> Ordner: ${fgEsc(it.folder)}</small>
                    <div class="count"><i class="fa-regular fa-image"></i> ${total} Bilder${it.onServer ? ' im Event' : ' geladen'}</div>
                </div>
            `;
        }).join('');
        $grid.querySelectorAll('.fg-event-pick').forEach(el => {
            el.addEventListener('click', async (ev) => {
                if (ev.target.closest('.hidebtn')) return; /* Toggle nicht als Auswahl werten */
                currentEventId = el.getAttribute('data-id');
                renderEvents();
                /* Bestehende Fotos aus events.json automatisch zu uploadedPhotos dazuladen (Merge!) */
                try {
                    const n = await fgAttachExistingPhotos(currentEventId);
                    if (n > 0) {
                        console.log(`[Admin] ${n} bestehende Fotos aus events.json zu "${currentEventId}" geladen.`);
                    }
                } catch (e) { console.warn('[Admin] Fotos laden fehlgeschlagen:', e); }
                renderPhotos();
                updatePublishBtn();
            });
        });
        $grid.querySelectorAll('.hidebtn').forEach(btn => {
            btn.addEventListener('click', async (ev) => {
                ev.preventDefault(); ev.stopPropagation();
                if (_busyEvent) return;
                _busyEvent = true;
                await fgSetEventHidden(btn.getAttribute('data-hide'), btn.getAttribute('data-hidden') !== '1');
                _busyEvent = false;
            });
        });
        if (!currentEventId || !_pickerItems.some(i => i.id === currentEventId)) {
            const first = $grid.querySelector('.fg-event-pick');
            if (first) first.click();
        }
    }

    /* Event ein-/ausblenden (hidden-Flag in events.json) */
    async function fgSetEventHidden(eventId, hidden) {
        try {
            const res = await fetch('event_admin.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set_hidden', csrf: fgAdminCsrf(), eventId: eventId, hidden: hidden ? 1 : 0 })
            });
            const j = await res.json().catch(() => ({ ok: false }));
            if (!j || !j.ok) { alert('Fehlgeschlagen:\n' + ((j && j.message) || 'Unbekannter Fehler')); return; }
            await fgRefreshServerEvents();
            renderEvents();
        } catch (e) { alert('Netzwerkfehler: ' + (e.message || e)); }
    }

    /* Neues Event anlegen */
    async function fgCreateEvent() {
        const name = (document.getElementById('ne-name').value || '').trim();
        if (name.length < 2) { alert('Bitte einen Namen eintragen.'); return; }
        const btn = document.getElementById('ne-save');
        if (_busyEvent) return;
        _busyEvent = true;
        const old = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Wird angelegt…';
        try {
            const res = await fetch('event_admin.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create', csrf: fgAdminCsrf(), name: name,
                    year: (document.getElementById('ne-year').value || $yearSel.value || '').trim(),
                    date: document.getElementById('ne-date').value || '',
                    location: document.getElementById('ne-location').value || '',
                    distance: document.getElementById('ne-distance').value || '',
                    description: document.getElementById('ne-desc').value || '',
                    subtype: document.getElementById('ne-subtype').value || ''
                })
            });
            const j = await res.json().catch(() => ({ ok: false }));
            if (!j || !j.ok) { alert('Anlegen fehlgeschlagen:\n' + ((j && j.message) || 'Unbekannter Fehler')); return; }
            await fgRefreshServerEvents();
            const newId = String(j.event.id);
            const newYear = String(newId).match(/20\d{2}/)?.[0] || $yearSel.value;
            fgSyncYearOptions(newYear);
            $yearSel.value = newYear;
            currentEventId = newId;
            document.getElementById('new-event-form').style.display = 'none';
            ['ne-name','ne-date','ne-location','ne-distance','ne-desc'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            renderEvents();
            renderPhotos();
            updatePublishBtn();
        } catch (e) {
            alert('Netzwerkfehler: ' + (e.message || e));
        } finally {
            _busyEvent = false;
            btn.disabled = false;
            btn.innerHTML = old;
        }
    }

    /* Formular ein-/ausblenden + Jahr vorbelegen */
    (function initNewEventForm() {
        const form = document.getElementById('new-event-form');
        const btnOpen = document.getElementById('new-event-btn');
        if (!form || !btnOpen) return;
        const toggle = (show) => {
            form.style.display = show ? 'block' : 'none';
            if (show) {
                const y = document.getElementById('ne-year');
                if (y && !y.value) y.value = $yearSel.value || String(new Date().getFullYear());
                const n = document.getElementById('ne-name');
                if (n) n.focus();
            }
        };
        btnOpen.addEventListener('click', () => toggle(form.style.display === 'none'));
        document.getElementById('ne-cancel').addEventListener('click', () => toggle(false));
        document.getElementById('ne-save').addEventListener('click', fgCreateEvent);
        document.getElementById('ne-name').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); fgCreateEvent(); } });
    })();

    $yearSel.addEventListener('change', renderEvents);
    /* Load: events.json laden, dann Auswahl rendern */
    (async () => {
        await fgRefreshServerEvents();
        fgSyncYearOptions();
        renderEvents();
    })();

    /* ===== BILDER REINZIEHEN — OHNE FileReader! (100x schneller + wenig RAM) ===== */
    const $drop = document.getElementById('dropzone');
    const $fileInput = document.getElementById('file-input');
    ['dragenter','dragover'].forEach(ev => $drop.addEventListener(ev, e => { e.preventDefault(); $drop.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(ev => $drop.addEventListener(ev, e => { e.preventDefault(); $drop.classList.remove('dragover'); }));
    $drop.addEventListener('drop', e => addFiles([...e.dataTransfer.files].filter(f => f.type.startsWith('image/'))));
    $fileInput.addEventListener('change', e => addFiles([...e.target.files].filter(f => f.type.startsWith('image/'))));

    function addFiles(files) {
        if (!files || files.length === 0) return;
        if (!currentEventId) { alert('Bitte zuerst ein Event auswählen!'); return; }
        let added = 0;
        for (const file of files) {
            try {
                const url = URL.createObjectURL(file);
                _objectUrls.add(url);
                uploadedPhotos.push({
                    id: `p${++idCounter}`, _event: currentEventId,
                    file, previewUrl: url, filename: file.name,
                    bibNumbers: [], _selected: true
                });
                added++;
            } catch(e) { console.warn('Bild konnte nicht geladen werden:', file.name, e); }
        }
        $fileInput.value = '';
        if (added > 0) {
            renderPhotos();
            document.getElementById('photo-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /* ===== RENDER: PHOTO GRID — PERFORMANCE OPTIMIERT ===== */
    const $pg = document.getElementById('photo-grid');
    const $empty = document.getElementById('empty-msg');
    const $bulk = document.getElementById('bulkbar');
    const $selCount = document.getElementById('sel-count');
    const $publishBtn = document.getElementById('publish-btn');

    function parseBibs(s) {
        return String(s||'').split(/[\s,\/;]+/).map(x => x.replace(/^#/,'').trim()).filter(Boolean)
            .map(x => isNaN(Number(x)) ? x : Number(x));
    }
    function parseList(s) { return String(s||'').split(/[,;]+/).map(x => x.trim()).filter(Boolean); }
    function getCurrentPhotoList() { return uploadedPhotos.filter(p => p._event === currentEventId); }

    /* Ein einzelnes Foto-Karten HTML erzeugen (wird von beiden Render Pfaden genutzt) */
    function _photoCardHTML(p) {
        const bibStr = (p.bibNumbers||[]).join(', ');
        /* Badge: Startnummern mit # , Kategorien (z. B. U7-U9) ohne # */
        const bibBadge = (p.bibNumbers||[]).map(b => /^\d+$/.test(String(b).trim()) ? '#' + b : b).join(' ');
        const sel = p._selected ? 'selected' : '';
        const hasBibs = (p.bibNumbers||[]).length > 0;
        const existingBadge = p._wasExisting
            ? `<div style="position:absolute;top:6px;left:6px;background:#198754;color:#fff;padding:3px 7px;border-radius:5px;font-size:0.68rem;font-weight:700;"><i class="fa-solid fa-server"></i> Bereits auf Server</div>`
            : '';
        return `
            <div class="fg-photo-card ${sel}" data-id="${p.id}" style="contain: layout paint style;">
                <div class="fg-photo-card__header js-lb-open" style="cursor: zoom-in;" title="öffnen + bearbeiten" data-id="${p.id}">
                    <input type="checkbox" class="fg-photo-card__select" ${p._selected?'checked':''} onclick="event.stopPropagation();">
                    <button class="fg-photo-card__del js-del" data-id="${p.id}" title="Bild löschen (Original + Vorschau + Display)" onclick="event.stopPropagation();"><i class="fa-solid fa-trash"></i></button>
                    <img src="${p.previewUrl}" alt="${p.filename}" loading="lazy" decoding="async" fetchpriority="auto" style="pointer-events:none; width:100%;">
                    ${hasBibs ? `<div style="position:absolute;bottom:6px;left:6px;background:#ffc107;color:#111;padding:3px 7px;border-radius:5px;font-size:0.72rem;font-weight:700;">🏁 ${bibBadge}</div>` : ''}
                    ${existingBadge || ''}
                    <div style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,0.7);color:#fff;padding:3px 7px;border-radius:5px;font-size:0.72rem;font-weight:600;"><i class="fa-solid fa-expand"></i> öffnen</div>
                </div>
                <div class="fg-photo-card__body">
                    <div class="fg-photo-card__title"><i class="fa-regular fa-file-image"></i> ${p.filename}</div>
                    <div>
                        <label>🏁 STARTNUMMER / KATEGORIE</label>
                        <input type="text" class="bibs js-bib" placeholder="42, 12 oder U7-U9" value="${bibStr}" data-id="${p.id}" autocomplete="off">
                    </div>
                </div>
            </div>
        `;
    }

    /* KOMPLETTES Grid NEU rendern (nur bei Event-Wechsel / Erstladen / Bulk-Massen-Änderung!) */
    function renderPhotos() {
        const list = getCurrentPhotoList();
        $empty.style.display = list.length ? 'none' : 'block';
        if (list.length === 0) { $pg.innerHTML = ''; wireCardEvents(); updateBulk(); updatePublishBtn(); return; }
        /* Schnell: Ein großes innerHTML statt N Manipulationen */
        $pg.innerHTML = list.map(_photoCardHTML).join('');
        wireCardEvents();
        updateBulk();
        updatePublishBtn();
        renderEvents();
    }

    /* NUR EINE einzelne Karte NEU rendern (für einzelne Änderungen → 100x schneller!) */
    function updatePhotoCard(photoId) {
        const p = uploadedPhotos.find(x => x.id === photoId);
        if (!p) return;
        const node = $pg.querySelector(`.fg-photo-card[data-id="${photoId}"]`);
        if (!node) { /* Karte existiert noch nicht (z.B. anderes Event) */ return; }
        const replacement = document.createElement('div');
        replacement.innerHTML = _photoCardHTML(p);
        const newCard = replacement.firstElementChild;
        node.replaceWith(newCard);
        wireSingleCardEvents(newCard);
    }

    /* ===== FOTO LÖSCHEN =====
     * Server-Fotos: löscht events.json-Eintrag + Original + Thumbnail + Display-Datei.
     * Neu gezogene (noch nicht hochgeladene) Fotos: nur aus der Liste entfernen. */
    async function fgDeletePhoto(photoId) {
        const p = uploadedPhotos.find(x => x.id === photoId);
        if (!p) return false;
        const label = p.filename || 'Bild';
        if (p.src) {
            const okDel = confirm(`„${label}" endgültig vom Server löschen?\n\nEntfernt werden: Original, Vorschau (Thumbnail) und Display-Version.`);
            if (!okDel) return false;
            try {
                const res = await fetch('delete_photo.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf: fgAdminCsrf(),
                        eventId: p._event,
                        filename: basename(p.src || p.filename)
                    })
                });
                const data = await res.json().catch(() => ({ ok: false }));
                if (!data || !data.ok) {
                    alert('Löschen fehlgeschlagen:\n' + ((data && data.message) || 'Unbekannter Fehler'));
                    return false;
                }
            } catch (e) {
                alert('Netzwerkfehler beim Löschen: ' + (e.message || e));
                return false;
            }
        } else {
            if (!confirm(`„${label}" aus der Liste entfernen? (noch nicht hochgeladen)`)) return false;
        }
        const idx = uploadedPhotos.findIndex(x => x.id === photoId);
        if (idx !== -1) uploadedPhotos.splice(idx, 1);
        try { await fgRefreshServerEvents(); } catch (e) {} /* events.json + Kachel-Zähler aktualisieren */
        return true;
    }
    async function fgDeletePhotoFromCard(photoId) {
        if (await fgDeletePhoto(photoId)) {
            renderPhotos(); updateBulk(); updatePublishBtn(); renderEvents();
        }
    }

    /* Alle Cards im Grid mit Event-Listenern versehen (einmal nach großem Re-Render) */
    function wireCardEvents() {
        /* Checkboxen: KEIN renderPhotos() mehr — nur toggeln + bulk update */
        $pg.querySelectorAll('.fg-photo-card__select').forEach(cb => {
            cb.addEventListener('change', e => {
                const card = e.target.closest('.fg-photo-card');
                const id = card.getAttribute('data-id');
                const p = uploadedPhotos.find(x => x.id === id);
                if (!p) return;
                p._selected = !!cb.checked;
                card.classList.toggle('selected', p._selected);
                updateBulk();
            });
        });
        /* Lightbox Open */
        $pg.querySelectorAll('.js-lb-open').forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault(); e.stopPropagation();
                lbOpenById(el.getAttribute('data-id'));
            });
        });
        /* Bib-Feld Inline-Change: KEIN renderPhotos() mehr, nur updatePhotoCard() */
        $pg.querySelectorAll('.js-bib').forEach(i => i.addEventListener('change', e => {
            const id = e.target.getAttribute('data-id');
            const p = uploadedPhotos.find(x => x.id === id);
            if (p) { p.bibNumbers = parseBibs(e.target.value); updatePhotoCard(id); }
            updateBulk();
        }));
        /* Löschen (1 Klick) */
        $pg.querySelectorAll('.js-del').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault(); e.stopPropagation();
                fgDeletePhotoFromCard(btn.getAttribute('data-id'));
            });
        });
    }

    /* Event-Listener nur auf EINE neu erstellte Karte (nach updatePhotoCard()) */
    function wireSingleCardEvents(cardEl) {
        const cb = cardEl.querySelector('.fg-photo-card__select');
        if (cb) cb.addEventListener('change', e => {
            e.stopPropagation();
            const id = cardEl.getAttribute('data-id');
            const p = uploadedPhotos.find(x => x.id === id);
            if (!p) return;
            p._selected = !!cb.checked;
            cardEl.classList.toggle('selected', p._selected);
            updateBulk();
        });
        const header = cardEl.querySelector('.js-lb-open');
        if (header) header.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); lbOpenById(header.getAttribute('data-id')); });
        const bibInput = cardEl.querySelector('.js-bib');
        if (bibInput) bibInput.addEventListener('change', e => {
            const id = bibInput.getAttribute('data-id');
            const p = uploadedPhotos.find(x => x.id === id);
            if (p) { p.bibNumbers = parseBibs(bibInput.value); updatePhotoCard(id); updateBulk(); }
        });
        const delBtn = cardEl.querySelector('.js-del');
        if (delBtn) delBtn.addEventListener('click', e => {
            e.preventDefault(); e.stopPropagation();
            fgDeletePhotoFromCard(delBtn.getAttribute('data-id'));
        });
    }

    function updateBulk() {
        const n = uploadedPhotos.filter(p => p._selected && p._event === currentEventId).length;
        $selCount.textContent = n;
        $bulk.classList.toggle('show', n > 0);
    }
    function updatePublishBtn() {
        $publishBtn.disabled = getCurrentPhotoList().length === 0;
    }

    /* Select-All / Select-None: SCHNELL via DOM ClassList toggeln ohne ganzen Grid neu zu bauen! */
    document.getElementById('select-all').addEventListener('click', () => {
        const list = getCurrentPhotoList();
        list.forEach(p => p._selected = true);
        $pg.querySelectorAll('.fg-photo-card').forEach(card => {
            card.classList.add('selected');
            const cb = card.querySelector('.fg-photo-card__select'); if (cb) cb.checked = true;
        });
        updateBulk();
    });
    document.getElementById('select-none').addEventListener('click', () => {
        const list = getCurrentPhotoList();
        list.forEach(p => p._selected = false);
        $pg.querySelectorAll('.fg-photo-card').forEach(card => {
            card.classList.remove('selected');
            const cb = card.querySelector('.fg-photo-card__select'); if (cb) cb.checked = false;
        });
        updateBulk();
    });

    /* Bulk Apply: Tags ändern → einzelne betroffene Karten updaten (kein ganzer Grid Rerender!) */
    document.getElementById('bulk-apply').addEventListener('click', () => {
        const bib = document.getElementById('bulk-bib').value;
        if (!bib) { alert('Bitte gib zuerst Startnummern ein!'); return; }
        const pb = parseBibs(bib);
        if (!pb.length) return;
        let n = 0;
        const updatedIds = [];
        uploadedPhotos.forEach(p => {
            if (!(p._selected && p._event === currentEventId)) return;
            p.bibNumbers = Array.from(new Set([...(p.bibNumbers||[]), ...pb]));
            updatedIds.push(p.id);
            n++;
        });
        document.getElementById('bulk-bib').value = '';
        updatedIds.forEach(updatePhotoCard);
        updateBulk();
        renderEvents();
        alert(`✅ ${n} Bilder aktualisiert!`);
    });

    /* ================================================================
     *  LIGHTBOX (große Bildansicht + Bearbeiten)
     * ================================================================ */
    const $lb = document.getElementById('admin-lightbox');
    const $lbImg = document.getElementById('lb-img');
    const $lbFilename = document.getElementById('lb-filename');
    const $lbPosition = document.getElementById('lb-position');
    const $lbBib = document.getElementById('lb-bib');
    const $lbProgressFill = document.getElementById('lb-progress-fill');
    const $lbProgressText = document.getElementById('lb-progress-text');
    const $lbSaveStatus = document.getElementById('lb-savestatus');

    /* ---- Speicher-Status (unten rechts in der Sidebar) ---- */
    let _lbStatusTimer = null;
    function lbSetStatus(kind, text) {
        if (!$lbSaveStatus) return;
        $lbSaveStatus.className = 'admin-lb__savestatus' + (kind ? ' ' + kind : '');
        $lbSaveStatus.textContent = text || '';
        if (_lbStatusTimer) { clearTimeout(_lbStatusTimer); _lbStatusTimer = null; }
        if (kind === 'ok') {
            _lbStatusTimer = setTimeout(() => { if ($lbSaveStatus) $lbSaveStatus.textContent = ''; }, 1800);
        }
    }

    /* ---- Fortschritt: welches Bild + wie viele schon getaggt ---- */
    function lbUpdateProgress() {
        const total = lbCurrentList.length;
        const tagged = lbCurrentList.filter(p => (p.bibNumbers || []).length > 0).length;
        const pct = total ? Math.round(tagged / total * 100) : 0;
        if ($lbProgressFill) $lbProgressFill.style.width = pct + '%';
        if ($lbProgressText) $lbProgressText.textContent = `${tagged} / ${total} getaggt (${pct}%)`;
        if ($lbPosition) $lbPosition.textContent = total ? `Bild ${lbCurrentIndex + 1}/${total}` : '';
    }

    /* ---- Hintergrund-Speichern: schreibt NUR die Startnummer in events.json ----
     * Requests laufen STRIKT NACHEINANDER (Queue) → keine Race-Condition beim
     * Schreiben der events.json bei sehr schnellem Tippen! */
    const _bibSaveQueue = [];
    let _bibSaveBusy = false;
    function lbQueueServerSave(p) {
        /* Nur Fotos, die SCHON am Server liegen (neue Bilder kommen erst beim Veröffentlichen rauf) */
        if (!p || !p.src || p.file) return;
        const filename = basename(p.src || p.filename);
        const job = { eventId: currentEventId, filename, bibNumbers: (p.bibNumbers || []).slice() };
        /* Gleiches Foto erneut geändert → alten Job ersetzen (nicht doppelt schreiben) */
        for (let i = _bibSaveQueue.length - 1; i >= 0; i--) {
            if (_bibSaveQueue[i].filename === filename) _bibSaveQueue.splice(i, 1);
        }
        _bibSaveQueue.push(job);
        lbSaveDrain();
    }
    async function lbSaveDrain() {
        if (_bibSaveBusy) return;
        _bibSaveBusy = true;
        while (_bibSaveQueue.length) {
            const job = _bibSaveQueue.shift();
            try {
                const res = await fetch('save_bib.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.assign({ csrf: fgAdminCsrf() }, job))
                });
                const data = await res.json().catch(() => ({ ok: false }));
                if (data && data.ok) lbSetStatus('ok', '✓ Auf Server gespeichert');
                else lbSetStatus('warn', '⚠ ' + ((data && data.message) || 'nicht gespeichert'));
            } catch (e) {
                lbSetStatus('warn', '⚠ Offline – Änderung nur lokal');
            }
        }
        _bibSaveBusy = false;
    }

    function lbOpenById(photoId) {
        lbCurrentList = getCurrentPhotoList();
        const idx = lbCurrentList.findIndex(p => p.id === photoId);
        if (idx === -1) return;
        lbCurrentIndex = idx;
        lbRender();
        $lb.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function lbClose() {
        $lb.classList.remove('show');
        document.body.style.overflow = '';
    }
    function lbRender() {
        if (lbCurrentIndex < 0 || lbCurrentIndex >= lbCurrentList.length) return;
        const p = lbCurrentList[lbCurrentIndex];
        $lbImg.src = p.previewUrl;
        $lbImg.alt = p.filename;
        $lbFilename.textContent = p.filename;
        $lbBib.value = (p.bibNumbers || []).join(', ');
        lbUpdateProgress();
        lbSetStatus('', '');
        /* Fokus IMMER zurück ins Nummern-Feld → unterbrechungsfreies Tippen am Ziffernblock! */
        requestAnimationFrame(() => { try { $lbBib.focus(); $lbBib.select(); } catch (e) {} });
        setTimeout(() => { try { if (document.activeElement !== $lbBib) { $lbBib.focus(); $lbBib.select(); } } catch (e) {} }, 90);
    }
    function lbSaveCurrent() {
        const p = lbCurrentList[lbCurrentIndex];
        if (!p) return null;
        p.bibNumbers = parseBibs($lbBib.value);
        lbQueueServerSave(p); /* im Hintergrund auf den Server schreiben (ohne Seiten-Reload!) */
        return p;
    }
    function lbNext() {
        if (lbCurrentIndex < lbCurrentList.length - 1) {
            const saved = lbSaveCurrent();
            if (saved) updatePhotoCard(saved.id);
            lbCurrentIndex++;
            lbRender();
        }
    }
    function lbPrev() {
        if (lbCurrentIndex > 0) {
            const saved = lbSaveCurrent();
            if (saved) updatePhotoCard(saved.id);
            lbCurrentIndex--;
            lbRender();
        }
    }
    function lbSaveAndNext() {
        const saved = lbSaveCurrent();
        if (saved) updatePhotoCard(saved.id);
        if (lbCurrentIndex < lbCurrentList.length - 1) {
            lbCurrentIndex++;
            lbRender();
        } else {
            lbUpdateProgress();
            updateBulk(); renderEvents();
            alert(`✅ Alle ${lbCurrentList.length} Bilder durchgesehen!`);
            lbClose();
        }
    }

    /* Bild löschen (Datei + alle Auflösungen) direkt aus der Lightbox */
    async function lbDeleteCurrent() {
        const p = lbCurrentList[lbCurrentIndex];
        if (!p) return;
        if (!(await fgDeletePhoto(p.id))) return;
        lbCurrentList.splice(lbCurrentIndex, 1);
        renderPhotos(); updateBulk(); updatePublishBtn(); renderEvents();
        if (lbCurrentList.length === 0) { lbClose(); return; }
        if (lbCurrentIndex >= lbCurrentList.length) lbCurrentIndex = lbCurrentList.length - 1;
        lbRender();
    }

    document.getElementById('lb-close').addEventListener('click', () => {
        const s = lbSaveCurrent(); if (s) updatePhotoCard(s.id);
        lbClose(); updateBulk(); renderEvents();
    });
    document.getElementById('lb-prev').addEventListener('click', lbPrev);
    document.getElementById('lb-next').addEventListener('click', lbNext);
    document.getElementById('lb-prev-top').addEventListener('click', () => { const s = lbSaveCurrent(); if (s) updatePhotoCard(s.id); lbPrev(); });
    document.getElementById('lb-save-top').addEventListener('click', lbSaveAndNext);
    document.getElementById('lb-save-sidebar').addEventListener('click', lbSaveAndNext);
    document.getElementById('lb-copy-bib').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText($lbBib.value); } catch(e){}
    });
    document.getElementById('lb-delete').addEventListener('click', lbDeleteCurrent);
    $lb.addEventListener('click', e => {
        if (e.target !== $lb) return;
        const s = lbSaveCurrent(); if (s) updatePhotoCard(s.id);
        lbClose(); updateBulk(); renderEvents();
    });

    /* ================================================================
     *  🎯 STARTNUMMERN-MODUS: reine Tastatur-Steuerung
     *    Enter        → speichern & nächstes Bild
     *    → (Rechts)   → nächstes Bild   (speichert ebenfalls → nichts geht verloren)
     *    ← (Links)    → vorheriges Bild (falls man sich vertippt hat)
     *    ESC          → schließen
     *  Alles funktioniert AUCH während der Fokus im Nummern-Feld liegt!
     * ================================================================ */
    document.addEventListener('keydown', e => {
        if (!$lb.classList.contains('show')) return;
        const isField = (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA'));
        const k = e.key;

        /* Strg/Cmd + S → Speichern & Weiter */
        if ((e.ctrlKey || e.metaKey) && (k === 's' || k === 'S')) { e.preventDefault(); lbSaveAndNext(); return; }

        /* Enter → Speichern & Weiter (auch direkt im Nummern-Feld!) */
        if (k === 'Enter') { e.preventDefault(); lbSaveAndNext(); return; }

        /* ESC → schließen (letzte Eingabe wird gespeichert) */
        if (k === 'Escape') {
            const s = lbSaveCurrent(); if (s) updatePhotoCard(s.id);
            lbClose(); updateBulk(); renderEvents();
            return;
        }

        /* Pfeiltasten → blättern (funktioniert auch im Nummern-Feld = schneller Workflow!) */
        if (k === 'ArrowRight') { e.preventDefault(); lbNext(); return; }
        if (k === 'ArrowLeft')  { e.preventDefault(); lbPrev(); return; }

        /* Delete/Entf → aktuelles Bild löschen (nur außerhalb des Eingabefelds, damit Text-Löschen funktioniert) */
        if ((k === 'Delete' || k === 'Entf') && !isField) { e.preventDefault(); lbDeleteCurrent(); return; }

        /* Außerhalb von Feldern: S = Speichern & Weiter */
        if (!isField && (k === 's' || k === 'S')) lbSaveAndNext();
    });

    /* 🎯 Einstieg: Startnummern-Modus beim ersten noch nicht getaggten Bild starten */
    const $bibModeBtn = document.getElementById('bib-mode-btn');
    if ($bibModeBtn) $bibModeBtn.addEventListener('click', () => {
        const list = getCurrentPhotoList();
        if (!list.length) { alert('Keine Bilder geladen – bitte zuerst Bilder reinziehen oder ein Event wählen.'); return; }
        const firstUntagged = list.find(p => !(p.bibNumbers || []).length) || list[0];
        lbOpenById(firstUntagged.id);
    });

    /* ================================================================
     *  SERVER PING + AUTO-PUBLISH (Upload Button)
     * ================================================================ */
    function setServerStatus(cls, msg) {
        const dot = document.getElementById('server-dot');
        const txt = document.getElementById('server-msg');
        dot.className = 'dot ' + cls;
        txt.innerHTML = msg;
    }
    
    /* ============= RV HARD ADMIN AUTH HELPERS ============= */
    function fgAdminCsrf() {
        const el = document.getElementById('fg-admin-csrf');
        return (el && el.value) ? el.value : (window.__FG_CSRF__ || '');
    }
    function fgAdminAppendCsrf(fdOrUrl, isUrl=false) {
        const t = fgAdminCsrf();
        if (!t) return fdOrUrl;
        if (isUrl) {
            const sep = fdOrUrl.indexOf('?') === -1 ? '?' : '&';
            return fdOrUrl + sep + 'csrf=' + encodeURIComponent(t);
        }
        if (fdOrUrl && typeof fdOrUrl.append === 'function') {
            try { fdOrUrl.append('csrf', t); } catch(e){}
        }
        return fdOrUrl;
    }
    function fgAdminSessionExpired(respText) {
        let json = null;
        try { json = respText ? JSON.parse(respText) : null; } catch(e){}
        if (json && json.login_url) { window.location.href = json.login_url; return true; }
        return false;
    }
    /* ============= ENDE AUTH HELPERS ============= */
    
    async function pingServer() {
        try {
            /* 🔥🔥 SUBDOMAIN KOMPATIBEL! Statt /fotos/admin/upload.php → RELATIV zum aktuellen HTML!
               Also egal ob admin unter fotos.rv-hard.at/admin/upload.php ODER rv-hard.at/fotos/admin/upload.php
               → funktioniert! */
            const r = await fetch(fgAdminAppendCsrf('./upload.php?action=ping&_=' + Date.now(), true), { method: 'GET', credentials: 'same-origin' });
            if (!r.ok) {
                const txt = await r.text();
                if (r.status === 401 && fgAdminSessionExpired(txt)) return;
                throw new Error('HTTP ' + r.status);
            }
            const j = await r.json();
            if (!j.ok) throw new Error(j.msg || 'Fehler');
            if (j.data_writable) {
                setServerStatus('ok', `✅ Server bereit`);
            } else {
                /* Pfad-Ausgabe dynamisch basierend auf Antwort! */
                const dataPath = j.data_prefix || '/fotos/data/';
                setServerStatus('warn', `⚠️ Ordner <code>${fgEscapeHtml(dataPath)}</code> nicht beschreibbar – bitte CHMOD 755 setzen!`);
            }
        } catch(err) {
            setServerStatus('err', `❌ Upload-Endpoint nicht erreichbar (PHP? Rechte?)`);
        }
    }
    setTimeout(pingServer, 300);

    $publishBtn.addEventListener('click', async () => {
        const list = getCurrentPhotoList();
        if (list.length === 0) return alert('Keine Bilder!');
        const pickItem = buildPickerItems().find(i => i.id === currentEventId);
        if (!pickItem) return alert('Unbekanntes Event – bitte oben ein Event auswählen!');
        const year = String(pickItem.id).match(/20\d{2}/)?.[0] || $yearSel.value;
        const folder = pickItem.folder;

        const ohne = list.filter(p => !p.bibNumbers || p.bibNumbers.length === 0).length;
        if (ohne > 0) {
            const jn = confirm(`${ohne} Bilder ohne Startnummern! Trotzdem veröffentlichen?`);
            if (!jn) return;
        }

        /* 🔥 Prüfung: Fehlen File-Objekte (also wurden Bilder nicht neu gezogen nach Reload?) */
        const withoutFile = list.filter(p => !p.file || typeof p.file.arrayBuffer !== 'function').length;
        if (withoutFile > 0) {
            const jn = confirm(
                `⚠️ ${withoutFile}/${list.length} Bilder haben KEINE Originaldaten mehr!\n\n` +
                `Das passiert wenn:\n` +
                `  → Du die Seite NEU GELADEN hast, nachdem Bilder gezogen wurden\n` +
                `  → Bilder waren nur Vorschau aus einem alten Publish.\n\n` +
                `TROTZDEM FORTFAHREN?\n` +
                `(Bilder mit Startnummern aus alten Publikationen bleiben erhalten! Neue Bilder müssen unbedingt neu gezogen werden!)`
            );
            if (!jn) return;
        }

        /* Metadaten-Eintrag für EIN Foto bauen — NIE src(previewUrl) als Datei mitschicken! */
        const photoMeta = p => {
            const bibNums = Array.isArray(p.bibNumbers)
                ? p.bibNumbers
                : parseBibs(String(p.bibNumbers || ''));
            const entry = {
                id: p.id,
                originalName: String(p.filename || p.originalName || 'img.jpg').trim(),
                bibNumbers: bibNums
            };
            /* 🔥 WICHTIG: Bestehende Fotos (aus events.json) MÜSSEN .src senden!
               Sonst erkennt upload.php nicht dass Foto schon existiert und würde es nicht mergen! */
            if (p.src) entry.src = String(p.src).trim();
            if (p.thumbnail) entry.thumbnail = String(p.thumbnail).trim();
            if (p.display)   entry.display   = String(p.display).trim();
            return entry;
        };

        /* 🔥 BATCHING: PHP nimmt standardmäßig nur max_file_uploads (20) Dateien pro
           Request an und verwirft den Rest still! Deshalb in kleinen Häppchen senden.
           Jeder Batch wird einzeln in events.json gemergt (upload.php bewahrt Alte auf). */
        const BATCH_SIZE = 12;
        const withFileList = list.filter(p => p.file && typeof p.file.arrayBuffer === 'function');
        const batches = [];
        for (let i = 0; i < withFileList.length; i += BATCH_SIZE) batches.push(withFileList.slice(i, i + BATCH_SIZE));
        if (batches.length === 0) batches.push([]); /* nur Metadaten (Merge bestehender Fotos) */
        console.log(`[Publish] ${withFileList.length} Datei(en) in ${batches.length} Batch(es), ${list.length} Einträge gesamt (${withoutFile} ohne Datei)`);

        /* 2) UI sperren + Progress */
        $publishBtn.disabled = true;
        $publishBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Wird hochgeladen…`;
        document.getElementById('progress-wrap').classList.add('show');
        const $pb = document.getElementById('progress-bar');
        const $pt = document.getElementById('progress-text');
        $pt.style.display = 'block';
        $pt.style.color = '';
        document.getElementById('success-msg').style.display = 'none';

        /* FormData für einen Batch (erster Batch sichert ALLE Startnummern) */
        const buildForm = (batch, firstBatch) => {
            const fd = new FormData();
            fd.append('action', 'publish');
            fgAdminAppendCsrf(fd);
            if (!fd.has('csrf')) { console.warn('CSRF Token fehlt!'); }
            const metaSource = firstBatch ? list : batch;
            fd.append('metadata', JSON.stringify({
                eventId: currentEventId, folder, year, discKey: pickItem.custom ? '' : pickItem.key,
                photos: metaSource.map(photoMeta)
            }));
            batch.forEach(p => fd.append('images[]', p.file, p.filename));
            return fd;
        };

        const sendBatch = (fd, batchIdx) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', './upload.php', true); /* 🔥 SUBDOMAIN KOMPATIBEL: RELATIV! */
            xhr.upload.onprogress = e => {
                if (e.lengthComputable) {
                    const per = 100 / batches.length;
                    const pct = Math.min(100, Math.round(batchIdx * per + (e.loaded / e.total) * per));
                    $pb.style.width = pct + '%';
                    $pt.textContent = `📤 Batch ${batchIdx + 1}/${batches.length} · ${pct}% (${(e.loaded/1024/1024).toFixed(1)} von ${(e.total/1024/1024).toFixed(1)} MB)…`;
                }
            };
            xhr.onload = () => {
                try {
                    if (xhr.status === 401) { fgAdminSessionExpired(xhr.responseText); return reject(new Error('Sitzung abgelaufen – bitte neu anmelden!')); }
                    const j = JSON.parse(xhr.responseText);
                    if (!j.ok) return reject(new Error(j.msg || 'Fehler'));
                    resolve(j);
                } catch(err) { reject(new Error('Ungültige Antwort vom Server: ' + xhr.responseText.slice(0, 300))); }
            };
            xhr.onerror = () => reject(new Error('Netzwerkfehler während Upload!'));
            xhr.onabort = () => reject(new Error('Upload abgebrochen!'));
            xhr.send(fd);
        });

        try {
            let savedTotal = 0, lastJ = null;
            for (let i = 0; i < batches.length; i++) {
                const j = await sendBatch(buildForm(batches[i], i === 0), i);
                lastJ = j;
                savedTotal += (j.photos_saved_count || 0);
                $pt.textContent = `📤 Batch ${i + 1}/${batches.length} fertig…`;
            }
            const total = (lastJ && typeof lastJ.photos_total === 'number') ? lastJ.photos_total : savedTotal;
            $pb.style.width = '100%';
            $pt.textContent = `✅ ${savedTotal} Datei(en) hochgeladen · ${total} Fotos im Event`;
            const sucMsg = document.getElementById('success-msg');
            sucMsg.style.display = 'block';
            if (lastJ && lastJ.preview_url) document.getElementById('success-link').href = lastJ.preview_url;
            $publishBtn.innerHTML = `<i class="fa-solid fa-check"></i> Nochmal veröffentlichen`;
            $publishBtn.disabled = false;
            setTimeout(pingServer, 500);
        } catch(err) {
            console.error(err);
            $pb.style.width = '0%';
            $pt.textContent = '❌ Fehler: ' + (err.message || String(err));
            $pt.style.color = '#ffd855';
            alert('❌ Upload fehlgeschlagen!\n' + (err.message || String(err)));
            $publishBtn.innerHTML = `<i class="fa-solid fa-cloud-arrow-up"></i> 🚀 Erneut versuchen`;
            $publishBtn.disabled = false;
            setTimeout(pingServer, 300);
        }
    });

    </script>
</body>
</html>
