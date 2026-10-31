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

$v = is_array($data['values'] ?? null) ? $data['values'] : [];
$outdoorOk = ($v['t1cn'] ?? null) === 1;

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
<meta http-equiv="refresh" content="300">
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
</style>
</head>
<body>
<h1>🌱 Skleník – Bojnice</h1>

<h2>Vonkajší senzor</h2>
<?php if (!$outdoorOk && $data !== null): ?>
  <p class="meta warn">Vonkajší senzor nie je pripojený (t1cn ≠ 1).</p>
<?php endif; ?>
<div class="grid">
<?php foreach ($outdoor as [$label, $val]): ?>
  <div class="card"><div class="label"><?= htmlspecialchars($label) ?></div><div class="value"><?= htmlspecialchars($outdoorOk ? $val : '–') ?></div></div>
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
</body>
</html>
