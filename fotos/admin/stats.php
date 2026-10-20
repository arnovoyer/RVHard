<?php
/* ================================================================
 *  RV HARD – FOTO-GALERIE STATISTIK
 * ================================================================
 *  Dashboard für die anonyme Reichweiten-Messung (siehe track.php).
 *  Nur für eingeloggte Admins.
 *
 *  Zahlen sind aggregiert und enthalten KEINE personenbezogenen Daten:
 *  nur Zähler, Tageswerte und ein tagesaktueller Besucher-Hash.
 * ================================================================ */

require_once(__DIR__ . '/auth2.php');

$__msg = ''; $__mType = 'err';
if (!empty($_SESSION['fg2_msg'])) {
    $__msg = $_SESSION['fg2_msg'];
    $__mType = ($_SESSION['fg2_mtype'] ?? 'err');
    unset($_SESSION['fg2_msg'], $_SESSION['fg2_mtype']);
}

if (!fg2_logged()) {
    fg2_render_login_page($__msg, $__mType);
    exit;
}

/* ---------- Daten laden ---------- */
$DATA_DIR    = rtrim(str_replace('\\', '/', (string)realpath(__DIR__ . '/../data')), '/');
$STATS_FILE  = $DATA_DIR . '/stats.json';
$EVENTS_FILE = $DATA_DIR . '/events.json';

$st = [];
if (is_file($STATS_FILE)) {
    $decoded = json_decode((string)@file_get_contents($STATS_FILE), true);
    if (is_array($decoded)) $st = $decoded;
}
if (!$st) {
    $st = ['totals' => [], 'days' => [], 'visitors' => [], 'events' => [], 'photos' => [], 'refs' => [], 'devices' => []];
}
foreach (['totals', 'days', 'visitors', 'events', 'photos', 'refs', 'devices'] as $k) {
    if (!isset($st[$k]) || !is_array($st[$k])) $st[$k] = [];
}

/* Event-Namen für eine lesbare Anzeige */
$eventNames = [];
if (is_file($EVENTS_FILE)) {
    $raw = (string)@file_get_contents($EVENTS_FILE);
    $evs = json_decode($raw, true);
    if (!is_array($evs)) {
        $c = preg_replace('!/\*.*?\*/!s', '', $raw);
        $c = preg_replace('/^\s*\/\/.*$/m', '', $c);
        $c = preg_replace('/,\s*([\]}])/m', '$1', $c);
        $evs = json_decode(trim($c), true);
    }
    if (is_array($evs)) {
        foreach ($evs as $e) {
            if (!is_array($e) || isset($e['_schemaVersion'])) continue;
            $id = (string)($e['id'] ?? '');
            if ($id === '') continue;
            $eventNames[$id] = (string)($e['shortName'] ?? ($e['name'] ?? $id));
        }
    }
}

/* ---------- Auswertung ---------- */
$today   = date('Y-m-d');
$days30  = [];
for ($i = 29; $i >= 0; $i--) { $days30[] = date('Y-m-d', time() - $i * 86400); }

$sum = function (array $keys) use ($st) {
    $v = 0;
    foreach ($keys as $d) { $v += (int)($st['days'][$d]['views'] ?? 0); }
    return $v;
};

$viewsToday = (int)($st['days'][$today]['views'] ?? 0);
$views7     = $sum(array_slice($days30, -7));
$views30    = $sum($days30);

/* Verschiedene Besucher der letzten 30 Tage (ohne Doppelzählung) */
$uniqSet = [];
foreach ($days30 as $d) {
    if (!empty($st['visitors'][$d]) && is_array($st['visitors'][$d])) {
        foreach ($st['visitors'][$d] as $vid => $_) { $uniqSet[$vid] = 1; }
    }
}
$uniq30    = count($uniqSet);
$uniqToday = !empty($st['visitors'][$today]) ? count((array)$st['visitors'][$today]) : 0;

/* Chart-Daten */
$chartMax = 1;
foreach ($days30 as $d) { $chartMax = max($chartMax, (int)($st['days'][$d]['views'] ?? 0)); }

/* Ranglisten */
$evList = [];
foreach ($st['events'] as $id => $info) {
    $evList[] = [
        'id'    => (string)$id,
        'name'  => $eventNames[(string)$id] ?? (string)$id,
        'n'     => (int)($info['n'] ?? 0),
        'last'  => (string)($info['last'] ?? ''),
    ];
}
usort($evList, function ($a, $b) { return $b['n'] <=> $a['n']; });
$evList = array_slice($evList, 0, 15);

$phList = [];
foreach ($st['photos'] as $info) {
    if (!is_array($info)) continue;
    $eId = (string)($info['e'] ?? '');
    $phList[] = [
        'event' => $eventNames[$eId] ?? $eId,
        'file'  => (string)($info['f'] ?? ''),
        'n'     => (int)($info['n'] ?? 0),
        'last'  => (string)($info['last'] ?? ''),
    ];
}
usort($phList, function ($a, $b) { return $b['n'] <=> $a['n']; });
$phList = array_slice($phList, 0, 20);

$refList = [];
foreach ($st['refs'] as $host => $n) { $refList[] = ['host' => (string)$host, 'n' => (int)$n]; }
usort($refList, function ($a, $b) { return $b['n'] <=> $a['n']; });
$refList = array_slice($refList, 0, 10);

$devMobile  = (int)($st['devices']['mobile'] ?? 0);
$devDesktop = (int)($st['devices']['desktop'] ?? 0);
$devTotal   = max(1, $devMobile + $devDesktop);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($iso) {
    if (!$iso) return '–';
    $t = strtotime($iso);
    return $t ? date('d.m.Y H:i', $t) : '–';
}
$hasData = ((int)($st['totals']['views'] ?? 0) + (int)($st['totals']['events'] ?? 0) + (int)($st['totals']['photos'] ?? 0)) > 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Statistik · Foto-Galerie · RV Hard</title>
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
.wrap{max-width:1080px;margin:0 auto}
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
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:16px}
.kpi .lbl{font-size:0.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;font-weight:600}
.kpi .val{font-size:1.9rem;font-weight:700;margin-top:6px;line-height:1.1}
.kpi .sub{font-size:0.78rem;color:var(--muted);margin-top:4px}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-bottom:20px}
.card h2{margin:0 0 14px;font-size:1.02rem;font-weight:700}
.chart{display:flex;align-items:flex-end;gap:3px;height:150px;padding-top:8px}
.chart .bar{flex:1 1 0;background:var(--yellow);border-radius:3px 3px 0 0;min-height:2px;opacity:.85;transition:opacity .15s}
.chart .bar:hover{opacity:1}
.chart-x{display:flex;gap:3px;margin-top:6px}
.chart-x span{flex:1 1 0;font-size:0.6rem;color:var(--muted);text-align:center;overflow:hidden;white-space:nowrap}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media (max-width:800px){.grid2{grid-template-columns:1fr}}
table{width:100%;border-collapse:collapse;font-size:0.88rem}
th,td{text-align:left;padding:7px 8px;border-bottom:1px solid var(--border)}
th{color:var(--muted);font-size:0.74rem;text-transform:uppercase;letter-spacing:.04em}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
td.trunc{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.muted{color:var(--muted);font-size:0.85rem}
.devbar{height:12px;border-radius:6px;background:var(--soft);overflow:hidden;display:flex;margin:8px 0 6px}
.devbar i{display:block;height:100%}
.devbar .m{background:var(--yellow)}
.devbar .d{background:#4b5b70}
.empty{color:var(--muted);padding:10px 0}
</style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <h1><i class="fa-solid fa-chart-simple" style="color:var(--yellow)"></i> Statistik</h1>
        <div class="spacer"></div>
        <a class="btn" href="./"><i class="fa-solid fa-sliders"></i> Admin</a>
        <a class="btn" href="../" target="_blank" rel="noopener"><i class="fa-solid fa-image"></i> Galerie</a>
    </div>

    <?php if (!$hasData): ?>
        <div class="card">
            <p class="empty">
                <i class="fa-solid fa-circle-info"></i>
                Noch keine Daten vorhanden. Die Messung startet automatisch, sobald Besucher die
                Galerie aufrufen. (Zahlen erscheinen hier erst nach dem ersten Aufruf.)
            </p>
        </div>
    <?php endif; ?>

    <div class="kpis">
        <div class="kpi">
            <div class="lbl">Galerie-Aufrufe gesamt</div>
            <div class="val"><?= (int)($st['totals']['views'] ?? 0) ?></div>
            <div class="sub"><?= $viewsToday ?> heute</div>
        </div>
        <div class="kpi">
            <div class="lbl">Aufrufe 30 Tage</div>
            <div class="val"><?= $views30 ?></div>
            <div class="sub"><?= $views7 ?> in den letzten 7 Tagen</div>
        </div>
        <div class="kpi">
            <div class="lbl">Besucher 30 Tage</div>
            <div class="val"><?= $uniq30 ?></div>
            <div class="sub"><?= $uniqToday ?> heute (ohne Doppelzählung)</div>
        </div>
        <div class="kpi">
            <div class="lbl">Event-Aufrufe</div>
            <div class="val"><?= (int)($st['totals']['events'] ?? 0) ?></div>
            <div class="sub">Events insgesamt geöffnet</div>
        </div>
        <div class="kpi">
            <div class="lbl">Foto-Aufrufe</div>
            <div class="val"><?= (int)($st['totals']['photos'] ?? 0) ?></div>
            <div class="sub">Bilder in der Großansicht</div>
        </div>
    </div>

    <div class="card">
        <h2>Aufrufe der letzten 30 Tage</h2>
        <?php if ($views30 === 0): ?>
            <p class="empty">Noch keine Aufrufe im Zeitraum.</p>
        <?php else: ?>
            <div class="chart">
                <?php foreach ($days30 as $d): $v = (int)($st['days'][$d]['views'] ?? 0); ?>
                    <div class="bar" style="height:<?= max(1, (int)round($v / $chartMax * 100)) ?>%"
                         title="<?= h(date('d.m.Y', strtotime($d))) ?>: <?= $v ?> Aufrufe"></div>
                <?php endforeach; ?>
            </div>
            <div class="chart-x">
                <?php foreach ($days30 as $i => $d): ?>
                    <span><?= $i % 5 === 0 ? h(date('d.m.', strtotime($d))) : '&nbsp;' ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid2">
        <div class="card">
            <h2>Beliebteste Events</h2>
            <?php if (!$evList): ?>
                <p class="empty">Noch keine Event-Aufrufe.</p>
            <?php else: ?>
                <table>
                    <tr><th>Event</th><th class="num">Aufrufe</th></tr>
                    <?php foreach ($evList as $e): ?>
                        <tr>
                            <td class="trunc" title="<?= h($e['name']) ?>"><?= h($e['name']) ?></td>
                            <td class="num"><?= $e['n'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Beliebteste Fotos</h2>
            <?php if (!$phList): ?>
                <p class="empty">Noch keine Foto-Aufrufe.</p>
            <?php else: ?>
                <table>
                    <tr><th>Foto</th><th class="num">Aufrufe</th></tr>
                    <?php foreach ($phList as $p): ?>
                        <tr>
                            <td class="trunc" title="<?= h($p['event'] . ' · ' . $p['file']) ?>">
                                <?= h($p['file']) ?>
                                <span class="muted"> · <?= h($p['event']) ?></span>
                            </td>
                            <td class="num"><?= $p['n'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid2">
        <div class="card">
            <h2>Woher kommen die Besucher</h2>
            <?php if (!$refList): ?>
                <p class="empty">Noch keine Verweise erfasst.</p>
            <?php else: ?>
                <table>
                    <tr><th>Quelle</th><th class="num">Aufrufe</th></tr>
                    <?php foreach ($refList as $r): ?>
                        <tr><td class="trunc"><?= h($r['host']) ?></td><td class="num"><?= $r['n'] ?></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Geräte</h2>
            <?php if ($devMobile + $devDesktop === 0): ?>
                <p class="empty">Noch keine Daten.</p>
            <?php else: ?>
                <div class="devbar">
                    <i class="m" style="width:<?= round($devMobile / $devTotal * 100) ?>%"></i>
                    <i class="d" style="width:<?= round($devDesktop / $devTotal * 100) ?>%"></i>
                </div>
                <p class="muted">
                    Mobil: <?= $devMobile ?> (<?= round($devMobile / $devTotal * 100) ?> %) ·
                    Desktop: <?= $devDesktop ?> (<?= round($devDesktop / $devTotal * 100) ?> %)
                </p>
            <?php endif; ?>
        </div>
    </div>

    <p class="muted" style="margin-top:8px">
        Messung ohne Cookies. IP-Adressen werden nicht gespeichert; für die Besucherzahl wird nur ein
        tagesaktueller, anonymisierter Hash verwendet (Löschung nach 60 Tagen).
    </p>

</div>
</body>
</html>
