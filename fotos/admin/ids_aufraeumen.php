<?php
/* ================================================================
 *  RV HARD – EINMAL-SKRIPT: EVENT-IDs AUFRÄUMEN
 * ================================================================
 *  Ältere, selbst angelegte Events haben IDs mit technischem
 *  Präfix (z. B. "sbs-2026-bmx4all-skillday-2026"). Dieses Skript
 *  schreibt id + slug auf den reinen Namen-Slug um
 *  (z. B. "bmx4all-skillday-2026").
 *
 *  WICHTIG:
 *    - Foto-Ordner und Bildpfade werden NICHT angefasst.
 *    - Nur id + slug ändern sich, damit Dinge wie die
 *      Jahres-Zuordnung und die Bilder unverändert bleiben.
 *    - Einmal ausführen, danach diese Datei wieder löschen!
 * ================================================================ */

require_once(__DIR__ . '/auth2.php');

if (!fg2_logged()) {
    fg2_render_login_page('Bitte anmelden.', 'err');
    exit;
}

$csrf = fg2_csrf_token();

/* Umlaute → ASCII, alles andere → Bindestrich (identisch zu event_admin.php) */
function ida_slug($s) {
    $s = trim((string)$s);
    $s = strtr($s, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue','ß'=>'ss']);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    if ($s === '') $s = 'event';
    return substr($s, 0, 60);
}

function ida_name(array $e, $fallback) {
    $nm = trim((string)($e['name'] ?? ''));
    if ($nm === '') $nm = trim((string)($e['shortName'] ?? ''));
    if ($nm === '') $nm = (string)$fallback;
    return $nm;
}

/* Liest events.json auch mit Kommentaren / trailing commas */
function ida_load($file) {
    if (!$file || !is_file($file)) return [];
    $raw = (string)@file_get_contents($file);
    $d = json_decode($raw, true);
    if (is_array($d)) return $d;
    $c = preg_replace('!/\*.*?\*/!s', '', $raw);
    $c = preg_replace('/^\s*\/\/.*$/m', '', $c);
    $c = preg_replace('/,\s*([\]}])/m', '$1', $c);
    $d = json_decode(trim($c), true);
    return is_array($d) ? $d : [];
}

/* Atomar schreiben: temp-Datei + rename */
function ida_save($file, array $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $file . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}

/* Welche Events würden umbenannt? [] => nichts zu tun */
function ida_plan(array $events) {
    $reserved = [];
    foreach ($events as $e) {
        if (!is_array($e) || isset($e['_schemaVersion'])) continue;
        $id = (string)($e['id'] ?? '');
        if ($id === '') continue;
        if (ida_slug(ida_name($e, $id)) === $id) $reserved[$id] = true;
    }

    $plan = [];
    $assigned = $reserved;
    foreach ($events as $i => $e) {
        if (!is_array($e) || isset($e['_schemaVersion'])) continue;
        $id = (string)($e['id'] ?? '');
        if ($id === '') continue;
        $nm = ida_name($e, $id);
        $base = ida_slug($nm);
        if ($base === $id) continue;

        $new = $base; $n = 2;
        while (isset($assigned[$new])) { $new = $base . '-' . $n; $n++; }
        $assigned[$new] = true;

        $plan[$i] = ['old' => $id, 'new' => $new, 'name' => $nm];
    }
    return $plan;
}

$DATA_DIR    = realpath(__DIR__ . '/../data');
$EVENTS_FILE = ($DATA_DIR !== false && is_dir($DATA_DIR))
    ? rtrim(str_replace('\\', '/', $DATA_DIR), '/') . '/events.json'
    : '';

$error    = '';
$done     = -1;   /* -1 = noch nicht gelaufen */
$applied  = [];

if ($EVENTS_FILE === '' || !is_file($EVENTS_FILE)) {
    $error = 'events.json wurde nicht gefunden (' . htmlspecialchars((string)$EVENTS_FILE, ENT_QUOTES) . ').';
}

$events = $error === '' ? ida_load($EVENTS_FILE) : [];
if ($error === '' && !count($events)) {
    $error = 'events.json konnte nicht gelesen werden (ungültiges JSON?).';
}

$plan = $error === '' ? ida_plan($events) : [];

/* ---------- Ausführen ---------- */
if ($error === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['do'] ?? '') === 'apply') {
    $token = (string)($_POST['csrf'] ?? '');
    if ($token === '' || empty($_SESSION['fg2_csrf']) || !hash_equals((string)$_SESSION['fg2_csrf'], $token)) {
        $error = 'Ungültiges CSRF-Token. Bitte Seite neu laden.';
    } elseif (!count($plan)) {
        $done = 0;
    } else {
        foreach ($plan as $i => $p) {
            $events[$i]['id'] = $p['new'];
            if (array_key_exists('slug', $events[$i])) $events[$i]['slug'] = $p['new'];
        }
        if (ida_save($EVENTS_FILE, $events)) {
            $applied = $plan;
            $done = count($plan);
            $plan = []; /* jetzt ist nichts mehr zu tun */
        } else {
            $error = 'events.json konnte nicht geschrieben werden (Rechte prüfen!).';
        }
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Event-IDs aufräumen · Foto-Galerie · RV Hard</title>
<style>
:root{ --bg:#121417; --card:#1b2027; --soft:#232a33; --border:#313b49; --text:#e7ebef; --muted:#98a2b3; --yellow:#ffc107; }
*{box-sizing:border-box}
body{margin:0;padding:0 16px 60px;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-font-smoothing:antialiased}
a{color:var(--yellow);text-decoration:none}
.wrap{max-width:900px;margin:0 auto}
.top{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:18px 0 22px;border-bottom:1px solid var(--border);margin-bottom:24px}
.top h1{margin:0;font-size:1.3rem;font-weight:700}
.top .spacer{flex:1 1 auto}
.top a.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 13px;border:1px solid var(--border);border-radius:9px;color:var(--text);font-size:0.88rem;font-weight:600;background:var(--soft)}
.top a.btn:hover{border-color:var(--yellow);color:var(--yellow)}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px 20px;margin-bottom:18px}
.card h2{margin:0 0 10px;font-size:1.05rem}
.card p{margin:0 0 10px;color:var(--muted);font-size:0.9rem;line-height:1.55}
table{width:100%;border-collapse:collapse;font-size:0.88rem;margin-top:6px}
th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--border);vertical-align:top}
th{color:var(--muted);font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em}
code{background:var(--soft);padding:2px 6px;border-radius:5px;font-size:0.84rem}
.arrow{color:var(--yellow);font-weight:700}
.msg{padding:12px 14px;border-radius:10px;margin-bottom:16px;font-size:0.92rem;border-left:4px solid}
.msg.ok{background:#12251a;border-color:#2e7d32;color:#b7e2bf}
.msg.err{background:#2a1417;border-color:#e2001a;color:#f6c2c6}
.msg.info{background:#20242b;border-color:var(--yellow);color:var(--text)}
button{font-family:inherit;font-size:0.92rem;font-weight:700;padding:11px 18px;border-radius:10px;border:1px solid var(--yellow);background:var(--yellow);color:#111;cursor:pointer}
button:hover{background:#ffcf3d}
.hint{font-size:0.84rem;color:var(--muted);margin-top:12px}
</style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <h1><i class="fa-solid fa-broom" style="color:var(--yellow)"></i> Event-IDs aufräumen</h1>
        <div class="spacer"></div>
        <a class="btn" href="./"><i class="fa-solid fa-upload"></i> Admin</a>
        <a class="btn" href="verwalten.php"><i class="fa-solid fa-list-check"></i> Bilder verwalten</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="msg err"><strong>Fehler:</strong> <?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($done > 0): ?>
        <div class="msg ok">
            <strong><?= (int)$done ?> Event<?= $done === 1 ? '' : 's' ?> umbenannt.</strong>
            Die Bilder und Ordner sind unverändert geblieben.
        </div>
        <div class="card">
            <h2>Umbenannt</h2>
            <table>
                <tr><th>alt</th><th></th><th>neu</th></tr>
                <?php foreach ($applied as $p): ?>
                    <tr>
                        <td><code><?= h($p['old']) ?></code></td>
                        <td class="arrow">→</td>
                        <td><code><?= h($p['new']) ?></code><br><span style="color:var(--muted);font-size:0.84rem"><?= h($p['name']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="hint">Diese Datei (<code>ids_aufraeumen.php</code>) kannst du jetzt wieder vom Server löschen.</p>
        </div>
    <?php elseif ($done === 0): ?>
        <div class="msg ok">Nichts zu tun – alle IDs sind bereits sauber.</div>
    <?php endif; ?>

    <?php if ($error === '' && $done <= 0): ?>

        <?php if (!count($plan)): ?>
            <div class="msg ok">Nichts zu tun – alle Event-IDs bestehen bereits nur aus dem Namen.</div>
        <?php else: ?>
            <div class="card">
                <h2><?= count($plan) ?> Event<?= count($plan) === 1 ? '' : 's' ?> betroffen</h2>
                <p>
                    Die ID wird auf den reinen Namen-Slug gekürzt. Foto-Ordner und Bildpfade bleiben unverändert,
                    es geht also kein Bild verloren. Bestehende Links mit der alten ID funktionieren danach nicht mehr.
                </p>
                <table>
                    <tr><th>alt</th><th></th><th>neu</th></tr>
                    <?php foreach ($plan as $p): ?>
                        <tr>
                            <td><code><?= h($p['old']) ?></code></td>
                            <td class="arrow">→</td>
                            <td><code><?= h($p['new']) ?></code><br><span style="color:var(--muted);font-size:0.84rem"><?= h($p['name']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <form method="post" style="margin-top:18px"
                      onsubmit="return confirm('<?= count($plan) ?> Event-ID(s) wirklich umbenennen?');">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="do" value="apply">
                    <button type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Jetzt umbenennen</button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
</body>
</html>
