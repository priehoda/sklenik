<?php
// Zobrazenie dát zo skleníka. ?raw=1 vypíše posledné prijaté dáta (JSON).

declare(strict_types=1);
require __DIR__ . '/lib.php';

$cfg  = load_config();
$data = load_latest();

if (isset($_GET['raw'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo $data === null ? '{"error":"zatiaľ neprišli žiadne dáta"}' : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$daily = daily_stats();

if (isset($_GET['stats'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['today' => date('Y-m-d'), 'days' => (object) $daily]);
    exit;
}

$v = is_array($data['values'] ?? null) ? $data['values'] : [];
$outdoorOk = ($v['t1cn'] ?? null) === 1;
$minToday = $daily[date('Y-m-d')][0] ?? null;

function fmt(array $v, string $k, string $unit, int $dec): string
{
    if (!isset($v[$k]) || !is_numeric($v[$k])) {
        return '–';
    }
    return number_format((float) $v[$k], $dec, ',', ' ') . ($unit !== '' ? ' ' . $unit : '');
}

function compass(array $v): string
{
    if (!isset($v['t1wdir']) || !is_numeric($v['t1wdir'])) {
        return '';
    }
    $dirs = ['S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ'];
    return $dirs[((int) round(((float) $v['t1wdir']) / 45)) % 8];
}

$outdoor = [
    ['Teplota vonku',  fmt($v, 't1tem', '°C', 1)],
    ['Vlhkosť vonku',  fmt($v, 't1hum', '%', 0)],
    ['Vietor',         trim(fmt($v, 't1ws', 'm/s', 1) . ' ' . compass($v))],
    ['Nárazy',         fmt($v, 't1wgust', 'm/s', 1)],
    ['Zrážky dnes',    fmt($v, 't1raindy', 'mm', 1)],
    ['UV index',       fmt($v, 't1uvi', '', 1)],
    ['Slnečné žiarenie', fmt($v, 't1solrad', 'W/m²', 0)],
    ['Rosný bod',      fmt($v, 't1dew', '°C', 1)],
];
$console = [
    ['Teplota (konzola)', fmt($v, 'intem', '°C', 1)],
    ['Vlhkosť (konzola)', fmt($v, 'inhum', '%', 0)],
    ['Tlak',              fmt($v, 'rbar', 'hPa', 1)],
];

$ts     = (int) ($data['received_at'] ?? 0);
$ageMin = $ts ? intdiv(time() - $ts, 60) : null;
$stale  = $ageMin === null || $ageMin > (int) ($cfg['stale_minutes'] ?? 30);

header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1f5130">
<meta name="robots" content="noindex">
<title>Skleník</title>
<style>
  :root { color-scheme: light dark; }
  body { font-family: system-ui, sans-serif; margin: 0 auto; padding: 1.25rem; max-width: 32rem; }
  h1 { font-size: 1.4rem; margin: 0 0 1rem; }
  h2 { font-size: 1rem; margin: 1.25rem 0 .5rem; opacity: .8; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
  .card { border: 1px solid #8884; border-radius: .75rem; padding: .9rem; }
  .label { font-size: .85rem; opacity: .7; }
  .value { font-size: 1.5rem; font-weight: 600; margin-top: .25rem; }
  .meta { margin-top: 1rem; font-size: .85rem; opacity: .75; }
  .warn { color: #c0392b; opacity: 1; }
  .outdoor { grid-auto-flow: dense; }
  button.card { font: inherit; color: inherit; background: none; text-align: left; cursor: pointer; }
  button.card[aria-expanded="true"] { border-color: currentColor; }
  .more { float: right; opacity: .6; }
  .sub { font-size: .85rem; margin-top: .3rem; opacity: .8; }
  .sub.frost { color: #2a78d6; opacity: 1; font-weight: 600; }
  .panel { grid-column: 1 / -1; border: 1px solid #8884; border-radius: .75rem; padding: .9rem; min-width: 0;
           --bar: #eb6834; --muted: #8888; --ink2: #66655f; }
  .panel.rain { --bar: #2a78d6; }
  .tabs { display: flex; gap: .25rem; }
  .tabs button { flex: 1; font: inherit; font-size: .85rem; color: inherit; background: none; border: 1px solid #8884; border-radius: .5rem; padding: .45rem 0; cursor: pointer; }
  .tabs button[aria-pressed="true"] { background: #8883; font-weight: 600; }
  .nav { display: flex; align-items: center; justify-content: space-between; margin: .6rem 0 .2rem; font-size: .9rem; }
  .nav button { font: inherit; font-size: 1.2rem; line-height: 1; color: inherit; background: none; border: 1px solid #8884; border-radius: .5rem; width: 2.75rem; height: 2.75rem; cursor: pointer; }
  .nav button:disabled { opacity: .3; cursor: default; }
  .chart { display: block; width: 100%; }
  .chart text { font-size: 11px; fill: var(--ink2); }
  .chart .val { fill: currentColor; font-weight: 600; }
  .chart .grid { stroke: #8883; }
  .chart .zero { stroke: currentColor; opacity: .55; }
  .chart .hit { fill: transparent; cursor: pointer; }
  .chart .hit:hover, .chart .hit.on { fill: #8882; }
  .chart .bar { fill: var(--bar); }
  .readout { min-height: 1.3em; font-size: .85rem; margin: .2rem 0 .6rem; }
  .panel details { font-size: .85rem; margin-bottom: .6rem; }
  .panel summary { cursor: pointer; padding: .3rem 0; }
  .panel table { width: 100%; border-collapse: collapse; margin-top: .3rem; font-variant-numeric: tabular-nums; }
  .panel th, .panel td { text-align: right; padding: .25rem 0; border-bottom: 1px solid #8883; font-weight: normal; }
  .panel th:first-child, .panel td:first-child { text-align: left; }
  .panel th { opacity: .7; }
  .facts { font-size: .85rem; margin: 0; }
  .facts h3 { font-size: .85rem; margin: .8rem 0 .25rem; }
  .facts div { display: flex; justify-content: space-between; gap: 1rem; padding: .15rem 0; }
  .facts dd { margin: 0; text-align: right; font-weight: 600; }
  .facts dt { opacity: .8; }
  .since { font-size: .8rem; opacity: .7; margin: .7rem 0 0; }
  @media (prefers-color-scheme: dark) {
    .panel { --bar: #d95926; --ink2: #c3c2b7; }
    .panel.rain { --bar: #3987e5; }
    .sub.frost { color: #3987e5; }
  }
</style>
</head>
<body>
<h1>🌱 Skleník – Bojnice</h1>

<h2>Vonkajší senzor</h2>
<?php if (!$outdoorOk && $data !== null): ?>
  <p class="meta warn">Vonkajší senzor nie je pripojený (t1cn ≠ 1).</p>
<?php endif; ?>
<div class="grid outdoor">
<?php foreach ($outdoor as [$label, $val]): ?>
  <?php $panel = ['Teplota vonku' => 'temp', 'Zrážky dnes' => 'rain'][$label] ?? null; ?>
  <?php if ($panel !== null): ?>
  <button type="button" class="card" data-panel="<?= $panel ?>" aria-expanded="false">
    <div class="label"><span class="more" aria-hidden="true">▾</span><?= htmlspecialchars($label) ?></div>
    <div class="value"><?= htmlspecialchars($outdoorOk ? $val : '–') ?></div>
    <?php if ($panel === 'temp' && $minToday !== null): ?>
      <div class="sub<?= $minToday <= 0 ? ' frost' : '' ?>">dnes min <?= number_format((float) $minToday, 1, ',', ' ') ?> °C<?= $minToday <= 0 ? ' · ❄ mrzlo' : '' ?></div>
    <?php endif; ?>
  </button>
  <?php else: ?>
  <div class="card"><div class="label"><?= htmlspecialchars($label) ?></div><div class="value"><?= htmlspecialchars($outdoorOk ? $val : '–') ?></div></div>
  <?php endif; ?>
<?php endforeach; ?>
</div>

<h2>Konzola</h2>
<div class="grid">
<?php foreach ($console as [$label, $val]): ?>
  <div class="card"><div class="label"><?= htmlspecialchars($label) ?></div><div class="value"><?= htmlspecialchars($val) ?></div></div>
<?php endforeach; ?>
</div>

<p class="meta<?= $stale ? ' warn' : '' ?>">
<?php if ($ageMin === null): ?>
  Zatiaľ neprišli žiadne dáta zo stanice. Pozri storage/log.txt.
<?php else: ?>
  Prijaté <?= htmlspecialchars(date('d.m.Y H:i', $ts)) ?> (pred <?= $ageMin ?> min)<?= $stale ? ' – dáta sú staré' : '' ?>
<?php endif; ?>
</p>
<script src="stats.js?v=1"></script>
</body>
</html>
