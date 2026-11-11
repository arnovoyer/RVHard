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
<html lang="de" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Statistik · Foto-Galerie · RV Hard</title>
<script>
(function () {
    try {
        var t = localStorage.getItem('rv-hard-theme');
        document.documentElement.setAttribute('data-theme', (t === 'dark' || t === 'light') ? t : 'light');
    } catch (e) {}
    var h = location.hostname;
    var base = (h === 'rv-hard.at' || h === 'www.rv-hard.at') ? '/assets/' : 'https://rv-hard.at/assets/';
    document.write('<link rel="stylesheet" href="' + base + 'admin.css?v=20261103">');
    document.write('<script src="' + base + 'admin.js?v=20261103"><\/script>');
})();
</script>
<link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css?v=20261111">
<style>
  .a-chart { display:flex; align-items:flex-end; gap:3px; height:150px; padding-top:8px; }
  .a-chart .bar { flex:1 1 0; background:var(--accent-yellow); border-radius:3px 3px 0 0; min-height:2px; opacity:.85; transition:opacity .15s; }
  .a-chart .bar:hover { opacity:1; }
  .a-chart-x { display:flex; gap:3px; margin-top:6px; }
  .a-chart-x span { flex:1 1 0; font-size:0.6rem; color:var(--site-muted); text-align:center; overflow:hidden; white-space:nowrap; }
  .a-table td.trunc { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .devbar { height:12px; border-radius:6px; background:var(--site-surface-soft); overflow:hidden; display:flex; margin:8px 0 6px; }
  .devbar i { display:block; height:100%; }
  .devbar .m { background:var(--accent-yellow); }
  .devbar .d { background:var(--site-muted); }
</style>
</head>
<body class="a-admin">
<div class="a-wrap">

    <div class="a-topbar">
        <div class="a-topbar__brand"><i class="fa-solid fa-shield-halved"></i> <span>RV Hard <small>· Admin</small></span></div>
        <div class="a-topbar__spacer"></div>
        <a class="a-topbar__link" href="index.php"><i class="fa-solid fa-upload"></i> <span>Hochladen</span></a>
        <a class="a-topbar__link" href="verwalten.php"><i class="fa-solid fa-list-check"></i> <span>Bilder</span></a>
        <a class="a-topbar__link is-active" href="stats.php"><i class="fa-solid fa-chart-simple"></i> <span>Statistik</span></a>
        <a class="a-topbar__link" href="https://rv-hard.at/bilder/admin.php"><i class="fa-solid fa-inbox"></i> <span>Bild-Eingang</span></a>
        <a class="a-topbar__link" href="https://rv-hard.at/cms/"><i class="fa-solid fa-newspaper"></i> <span>Admin</span></a>
        <a class="a-topbar__link" href="https://fotos.rv-hard.at/" target="_blank" rel="noopener"><i class="fa-solid fa-image"></i> <span>Galerie</span></a>
        <button type="button" class="a-topbar__btn" data-theme-toggle title="Hell/Dunkel umschalten" aria-label="Theme umschalten"><i class="fa-solid fa-moon"></i></button>
        <a class="a-topbar__link a-topbar__link--danger" href="?logout=1" onclick="if(!confirm('Wirklich abmelden?'))return false"><i class="fa-solid fa-right-from-bracket"></i> <span>Abmelden</span></a>
    </div>

    <h1 class="a-page-title"><i class="fa-solid fa-chart-simple"></i> Statistik</h1>
    <p class="a-page-sub">Anonyme Reichweiten-Messung der Foto-Galerie.</p>

    <?php if (!$hasData): ?>
        <div class="a-card">
            <p class="a-empty">
                <i class="fa-solid fa-circle-info"></i>
                Noch keine Daten vorhanden. Die Messung startet automatisch, sobald Besucher die
                Galerie aufrufen. (Zahlen erscheinen hier erst nach dem ersten Aufruf.)
            </p>
        </div>
    <?php endif; ?>

    <div class="a-kpis">
        <div class="a-kpi">
            <div class="a-kpi__lbl">Galerie-Aufrufe gesamt</div>
            <div class="a-kpi__val"><?= (int)($st['totals']['views'] ?? 0) ?></div>
            <div class="a-kpi__sub"><?= $viewsToday ?> heute</div>
        </div>
        <div class="a-kpi">
            <div class="a-kpi__lbl">Aufrufe 30 Tage</div>
            <div class="a-kpi__val"><?= $views30 ?></div>
            <div class="a-kpi__sub"><?= $views7 ?> in den letzten 7 Tagen</div>
        </div>
        <div class="a-kpi">
            <div class="a-kpi__lbl">Besucher 30 Tage</div>
            <div class="a-kpi__val"><?= $uniq30 ?></div>
            <div class="a-kpi__sub"><?= $uniqToday ?> heute (ohne Doppelzählung)</div>
        </div>
        <div class="a-kpi">
            <div class="a-kpi__lbl">Event-Aufrufe</div>
            <div class="a-kpi__val"><?= (int)($st['totals']['events'] ?? 0) ?></div>
            <div class="a-kpi__sub">Events insgesamt geöffnet</div>
        </div>
        <div class="a-kpi">
            <div class="a-kpi__lbl">Foto-Aufrufe</div>
            <div class="a-kpi__val"><?= (int)($st['totals']['photos'] ?? 0) ?></div>
            <div class="a-kpi__sub">Bilder in der Großansicht</div>
        </div>
    </div>

    <div class="a-card">
        <h2>Aufrufe der letzten 30 Tage</h2>
        <?php if ($views30 === 0): ?>
            <p class="a-empty">Noch keine Aufrufe im Zeitraum.</p>
        <?php else: ?>
            <div class="a-chart">
                <?php foreach ($days30 as $d): $v = (int)($st['days'][$d]['views'] ?? 0); ?>
                    <div class="bar" style="height:<?= max(1, (int)round($v / $chartMax * 100)) ?>%"
                         title="<?= h(date('d.m.Y', strtotime($d))) ?>: <?= $v ?> Aufrufe"></div>
                <?php endforeach; ?>
            </div>
            <div class="a-chart-x">
                <?php foreach ($days30 as $i => $d): ?>
                    <span><?= $i % 5 === 0 ? h(date('d.m.', strtotime($d))) : '&nbsp;' ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="a-grid2">
        <div class="a-card">
            <h2>Beliebteste Events</h2>
            <?php if (!$evList): ?>
                <p class="a-empty">Noch keine Event-Aufrufe.</p>
            <?php else: ?>
                <table class="a-table">
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

        <div class="a-card">
            <h2>Beliebteste Fotos</h2>
            <?php if (!$phList): ?>
                <p class="a-empty">Noch keine Foto-Aufrufe.</p>
            <?php else: ?>
                <table class="a-table">
                    <tr><th>Foto</th><th class="num">Aufrufe</th></tr>
                    <?php foreach ($phList as $p): ?>
                        <tr>
                            <td class="trunc" title="<?= h($p['event'] . ' · ' . $p['file']) ?>">
                                <?= h($p['file']) ?>
                                <span class="a-muted"> · <?= h($p['event']) ?></span>
                            </td>
                            <td class="num"><?= $p['n'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="a-grid2">
        <div class="a-card">
            <h2>Woher kommen die Besucher</h2>
            <?php if (!$refList): ?>
                <p class="a-empty">Noch keine Verweise erfasst.</p>
            <?php else: ?>
                <table class="a-table">
                    <tr><th>Quelle</th><th class="num">Aufrufe</th></tr>
                    <?php foreach ($refList as $r): ?>
                        <tr><td class="trunc"><?= h($r['host']) ?></td><td class="num"><?= $r['n'] ?></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="a-card">
            <h2>Geräte</h2>
            <?php if ($devMobile + $devDesktop === 0): ?>
                <p class="a-empty">Noch keine Daten.</p>
            <?php else: ?>
                <div class="devbar">
                    <i class="m" style="width:<?= round($devMobile / $devTotal * 100) ?>%"></i>
                    <i class="d" style="width:<?= round($devDesktop / $devTotal * 100) ?>%"></i>
                </div>
                <p class="a-muted">
                    Mobil: <?= $devMobile ?> (<?= round($devMobile / $devTotal * 100) ?> %) ·
                    Desktop: <?= $devDesktop ?> (<?= round($devDesktop / $devTotal * 100) ?> %)
                </p>
            <?php endif; ?>
        </div>
    </div>

    <p class="a-muted" style="margin-top:8px">
        Messung ohne Cookies. IP-Adressen werden nicht gespeichert; für die Besucherzahl wird nur ein
        tagesaktueller, anonymisierter Hash verwendet (Löschung nach 60 Tagen).
    </p>

</div>
</body>
</html>
