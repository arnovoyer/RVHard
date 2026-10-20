<?php
/* ================================================================
 *  RV HARD – BILDER VERWALTEN (separates Dashboard)
 * ================================================================
 *  Zeigt die BEREITS VERÖFFENTLICHTEN Bilder eines Events und
 *  erlaubt Löschen (Original + Thumbnail + Display) sowie das
 *  Bearbeiten der Startnummern.
 *
 *  Zweck: Der normale Upload-Bereich (index.php) bleibt dadurch
 *  leer und wird nicht mit alten Bildern zugemüllt.
 *
 *  Nutzt die bestehenden Endpunkte:
 *    delete_photo.php  → Bild komplett entfernen
 *    save_bib.php      → Startnummern ändern
 * ================================================================ */

require_once(__DIR__ . '/auth2.php');

$__msg = ''; $__mType = 'err';
if (!empty($_SESSION['fg2_msg'])) {
    $__msg = $_SESSION['fg2_msg'];
    $__mType = ($_SESSION['fg2_msgType'] ?? ($_SESSION['fg2_mtype'] ?? 'err'));
    unset($_SESSION['fg2_msg'], $_SESSION['fg2_mtype'], $_SESSION['fg2_msgType']);
}

if (!fg2_logged()) {
    fg2_render_login_page($__msg, $__mType);
    exit;
}

$csrf = fg2_csrf_token();

/* ---------- events.json laden (tolerant) ---------- */
$DATA_DIR    = rtrim(str_replace('\\', '/', (string)realpath(__DIR__ . '/../data')), '/');
$EVENTS_FILE = $DATA_DIR . '/events.json';

$evs = [];
if (is_file($EVENTS_FILE)) {
    $raw = (string)@file_get_contents($EVENTS_FILE);
    $evs = json_decode($raw, true);
    if (!is_array($evs)) {
        $c = preg_replace('!/\*.*?\*/!s', '', $raw);
        $c = preg_replace('/^\s*\/\/.*$/m', '', $c);
        $c = preg_replace('/,\s*([\]}])/m', '$1', $c);
        $evs = json_decode(trim($c), true);
    }
}

/* ---------- Nur Events MIT Bildern übernehmen ---------- */
$manage = [];
if (is_array($evs)) {
    foreach ($evs as $e) {
        if (!is_array($e) || isset($e['_schemaVersion'])) continue;
        $id = (string)($e['id'] ?? '');
        if ($id === '') continue;

        $photos = [];
        if (!empty($e['photos']) && is_array($e['photos'])) {
            foreach ($e['photos'] as $ph) {
                if (!is_array($ph) || empty($ph['src'])) continue;
                $src = str_replace('\\', '/', (string)$ph['src']);
                $thumb = !empty($ph['thumbnail']) ? (string)$ph['thumbnail'] : (string)$ph['src'];
                $bibs  = [];
                if (!empty($ph['bibNumbers']) && is_array($ph['bibNumbers'])) {
                    foreach ($ph['bibNumbers'] as $b) { $bibs[] = (string)$b; }
                }
                $photos[] = [
                    'file'  => basename($src),
                    'thumb' => $thumb,
                    'bibs'  => $bibs,
                ];
            }
        }
        if (!count($photos)) continue;

        $manage[] = [
            'id'     => $id,
            'name'   => (string)($e['shortName'] ?? ($e['name'] ?? $id)),
            'hidden' => !empty($e['hidden']),
            'photos' => $photos,
        ];
    }
}

$MANAGE_JSON = json_encode(
    $manage,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($MANAGE_JSON === false) $MANAGE_JSON = '[]';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Bilder verwalten · Foto-Galerie · RV Hard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
:root{
  --bg:#121417; --card:#1b2027; --soft:#232a33; --border:#313b49;
  --text:#e7ebef; --muted:#98a2b3; --yellow:#ffc107; --radius:12px;
}
*{box-sizing:border-box}
body{
  margin:0; padding:0 16px 60px; background:var(--bg); color:var(--text);
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  -webkit-font-smoothing:antialiased;
}
a{color:var(--yellow);text-decoration:none}
.wrap{max-width:1180px;margin:0 auto}
.top{
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  padding:18px 0 22px;border-bottom:1px solid var(--border);margin-bottom:24px;
}
.top h1{margin:0;font-size:1.35rem;font-weight:700}
.top .spacer{flex:1 1 auto}
.top a.btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:8px 13px;border:1px solid var(--border);border-radius:9px;
  color:var(--text);font-size:0.88rem;font-weight:600;background:var(--soft);
}
.top a.btn:hover{border-color:var(--yellow);color:var(--yellow)}
.controls{
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:14px 16px;margin-bottom:18px;
}
.controls label{font-size:0.8rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em}
select,.bib{
  background:var(--soft);color:var(--text);border:1px solid var(--border);
  border-radius:9px;padding:9px 11px;font-size:0.92rem;font-family:inherit;
}
select{min-width:260px;max-width:100%}
select:focus,.bib:focus{outline:2px solid var(--yellow);outline-offset:1px}
.count{margin-left:auto;font-size:0.86rem;color:var(--muted)}
.grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
  gap:12px;
}
.pcard{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.pcard__img{position:relative;aspect-ratio:4/3;background:#0d1013;overflow:hidden}
.pcard__img img{width:100%;height:100%;object-fit:cover;display:block}
.pcard__del{
  position:absolute;top:8px;right:8px;width:32px;height:32px;border-radius:8px;
  border:1px solid rgba(255,255,255,.25);background:rgba(0,0,0,.6);color:#fff;
  cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:13px;
}
.pcard__del:hover{background:#e2001a;border-color:#e2001a}
.pcard__bibs{
  position:absolute;bottom:8px;left:8px;background:var(--yellow);color:#111;
  padding:3px 8px;border-radius:6px;font-size:0.72rem;font-weight:700;
}
.pcard__body{padding:10px}
.pcard__name{
  font-size:0.78rem;color:var(--muted);margin-bottom:8px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.pcard__body label{
  display:block;font-size:0.66rem;color:var(--muted);font-weight:700;
  text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;
}
.pcard__body .bib{width:100%}
.pcard__body .bib.saving{border-color:var(--yellow)}
.pcard__body .bib.ok{border-color:#2e7d32}
.pcard__body .bib.err{border-color:#e2001a}
.empty{color:var(--muted);padding:22px 4px}
.toast{
  position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(120%);
  background:#1b2027;border:1px solid var(--border);border-left:4px solid var(--yellow);
  color:var(--text);padding:11px 16px;border-radius:10px;font-size:0.9rem;
  box-shadow:0 8px 30px rgba(0,0,0,.5);transition:transform .22s ease;z-index:9999;
}
.toast.show{transform:translateX(-50%) translateY(0)}
.toast.ok{border-left-color:#2e7d32}
.toast.err{border-left-color:#e2001a}
@media (max-width:520px){
  .grid{grid-template-columns:repeat(2,1fr)}
  select{min-width:0;width:100%}
  .count{margin-left:0}
}
</style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <h1><i class="fa-solid fa-list-check" style="color:var(--yellow)"></i> Bilder verwalten</h1>
        <div class="spacer"></div>
        <a class="btn" href="./"><i class="fa-solid fa-upload"></i> Admin</a>
        <a class="btn" href="stats.php"><i class="fa-solid fa-chart-simple"></i> Statistik</a>
        <a class="btn" href="../" target="_blank" rel="noopener"><i class="fa-solid fa-image"></i> Galerie</a>
    </div>

    <div class="controls">
        <label for="verw-event">Event</label>
        <select id="verw-event"></select>
        <span class="count" id="verw-count"></span>
    </div>

    <div class="grid" id="verw-grid"></div>
    <div class="empty" id="verw-empty" hidden>
        <i class="fa-solid fa-circle-info"></i> Keine veröffentlichten Bilder vorhanden.
    </div>

</div>

<script>
(function () {
    'use strict';

    var MANAGE = <?= $MANAGE_JSON ?>;
    var CSRF   = <?= json_encode($csrf) ?>;

    var $sel   = document.getElementById('verw-event');
    var $grid  = document.getElementById('verw-grid');
    var $count = document.getElementById('verw-count');
    var $empty = document.getElementById('verw-empty');
    var $toast = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function toast(msg, kind) {
        if (!$toast) {
            $toast = document.createElement('div');
            $toast.className = 'toast';
            document.body.appendChild($toast);
        }
        $toast.textContent = msg;
        $toast.className = 'toast show' + (kind ? ' ' + kind : '');
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { $toast.className = 'toast'; }, 2600);
    }

    function currentEvent() {
        var id = $sel.value;
        for (var i = 0; i < MANAGE.length; i++) { if (MANAGE[i].id === id) return MANAGE[i]; }
        return null;
    }

    function renderSelect() {
        var wanted = new URLSearchParams(location.search).get('event') || '';
        $sel.innerHTML = MANAGE.map(function (e) {
            return '<option value="' + esc(e.id) + '">' + esc(e.name) +
                   ' (' + e.photos.length + ')' + (e.hidden ? ' – ausgeblendet' : '') + '</option>';
        }).join('');
        if (wanted && MANAGE.some(function (e) { return e.id === wanted; })) $sel.value = wanted;
    }

    function bibBadge(bibs) {
        if (!bibs || !bibs.length) return null;
        return bibs.map(function (b) { return /^\d+$/.test(String(b).trim()) ? '#' + b : b; }).join(' ');
    }

    function renderGrid() {
        var ev = currentEvent();
        if (!ev) { $grid.innerHTML = ''; $count.textContent = ''; $empty.hidden = false; return; }
        $empty.hidden = MANAGE.length > 0;

        $grid.innerHTML = ev.photos.map(function (p) {
            var badge = bibBadge(p.bibs);
            return '<div class="pcard" data-file="' + esc(p.file) + '">' +
                       '<div class="pcard__img">' +
                           '<img src="' + esc(p.thumb) + '" alt="' + esc(p.file) + '" loading="lazy" decoding="async">' +
                           '<button class="pcard__del" type="button" title="Bild endgültig löschen"><i class="fa-solid fa-trash"></i></button>' +
                           (badge ? '<div class="pcard__bibs">🏁 ' + esc(badge) + '</div>' : '') +
                       '</div>' +
                       '<div class="pcard__body">' +
                           '<div class="pcard__name" title="' + esc(p.file) + '">' + esc(p.file) + '</div>' +
                           '<label>Startnummer / Kategorie</label>' +
                           '<input type="text" class="bib" placeholder="42, 12 oder U7-U9" autocomplete="off" value="' + esc(p.bibs.join(', ')) + '">' +
                       '</div>' +
                   '</div>';
        }).join('');

        $count.textContent = ev.photos.length + (ev.photos.length === 1 ? ' Bild' : ' Bilder');

        Array.prototype.forEach.call($grid.querySelectorAll('.pcard'), function (card) {
            var file  = card.getAttribute('data-file');
            var input = card.querySelector('.bib');
            card.querySelector('.pcard__del').addEventListener('click', function () { deletePhoto(ev.id, file, card); });
            input.addEventListener('change', function () { saveBibs(ev.id, file, input, card); });
        });
    }

    function deletePhoto(evId, file, card) {
        if (!confirm('„' + file + '" endgültig vom Server löschen?\n\nEntfernt werden: Original, Vorschau (Thumbnail) und Display-Version.')) return;
        fetch('delete_photo.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: CSRF, eventId: evId, filename: file })
        }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
          .then(function (j) {
              if (!j || !j.ok) { toast('Löschen fehlgeschlagen: ' + ((j && j.message) || 'Unbekannter Fehler'), 'err'); return; }
              removePhotoFromState(evId, file);
              card.remove();
              toast('Bild gelöscht.', 'ok');
              if (!MANAGE.length) { renderSelect(); renderGrid(); return; }
              if (!currentEvent()) { $sel.value = MANAGE[0].id; }
              renderSelectKeepSelection();
              renderGrid();
          })
          .catch(function (e) { toast('Netzwerkfehler beim Löschen: ' + (e.message || e), 'err'); });
    }

    function saveBibs(evId, file, input, card) {
        var val = input.value;
        input.classList.add('saving');
        input.classList.remove('ok', 'err');
        fetch('save_bib.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: CSRF, eventId: evId, filename: file, bibNumbers: val })
        }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
          .then(function (j) {
              input.classList.remove('saving');
              if (!j || !j.ok) {
                  input.classList.add('err');
                  toast('Speichern fehlgeschlagen: ' + ((j && j.message) || 'Unbekannter Fehler'), 'err');
                  return;
              }
              var bibs = Array.isArray(j.bibNumbers) ? j.bibNumbers.map(String) : [];
              updateStateBibs(evId, file, bibs);
              input.value = bibs.join(', ');
              input.classList.add('ok');
              setTimeout(function () { input.classList.remove('ok'); }, 1400);
              var img = card.querySelector('.pcard__img');
              var old = img.querySelector('.pcard__bibs');
              if (old) old.remove();
              var badge = bibBadge(bibs);
              if (badge) {
                  var el = document.createElement('div');
                  el.className = 'pcard__bibs';
                  el.textContent = '🏁 ' + badge;
                  img.appendChild(el);
              }
              toast('Startnummer gespeichert.', 'ok');
          })
          .catch(function (e) {
              input.classList.remove('saving');
              input.classList.add('err');
              toast('Netzwerkfehler beim Speichern: ' + (e.message || e), 'err');
          });
    }

    function removePhotoFromState(evId, file) {
        var ev = null;
        MANAGE.forEach(function (e) { if (e.id === evId) ev = e; });
        if (!ev) return;
        ev.photos = ev.photos.filter(function (p) { return p.file !== file; });
        if (!ev.photos.length) {
            MANAGE = MANAGE.filter(function (e) { return e.id !== evId; });
        }
    }

    function updateStateBibs(evId, file, bibs) {
        MANAGE.forEach(function (e) {
            if (e.id !== evId) return;
            e.photos.forEach(function (p) { if (p.file === file) p.bibs = bibs; });
        });
    }

    function renderSelectKeepSelection() {
        var cur = $sel.value;
        $sel.innerHTML = MANAGE.map(function (e) {
            return '<option value="' + esc(e.id) + '">' + esc(e.name) + ' (' + e.photos.length + ')' +
                   (e.hidden ? ' – ausgeblendet' : '') + '</option>';
        }).join('');
        if (MANAGE.some(function (e) { return e.id === cur; })) $sel.value = cur;
    }

    $sel.addEventListener('change', renderGrid);

    renderSelect();
    renderGrid();
})();
</script>
</body>
</html>
