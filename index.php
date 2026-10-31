<?php
// Zobrazenie dát zo skleníka. ?raw=1 vypíše surové uložené dáta.

declare(strict_types=1);
require __DIR__ . '/lib.php';

$cfg  = load_config();
$data = load_data();

if (isset($_GET['raw'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo $data === null ? '{"error":"data.json zatiaľ neexistuje"}' : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$values = is_array($data['values'] ?? null) ? $data['values'] : [];
// Niektoré odpovede môžu byť vnorené (neoverené) – skús bežné obaly.
foreach (['values', 'data'] as $wrap) {
    if (isset($values[$wrap]) && is_array($values[$wrap])) {
        $values = $values[$wrap];
    }
}

function fmt(array $v, string $key, string $unit, int $dec, bool $div10): string
{
    if (!isset($v[$key]) || !is_numeric($v[$key])) {
        return '–';
    }
    $n = (float) $v[$key];
    if ($div10) {
        $n /= 10;
    }
    return number_format($n, $dec, ',', ' ') . ' ' . $unit;
}

$div10 = (bool) ($cfg['divide_by_10'] ?? false);
$rows = [
    ['Teplota',  fmt($values, 'temp', '°C', 1, $div10)],
    ['Vlhkosť',  fmt($values, 'hum', '%', 0, false)],
    ['Tlak',     fmt($values, 'bar', 'hPa', 1, $div10)],
    ['Vietor',   fmt($values, 'wspdavg', 'm/s', 1, $div10)],
];

$fetched = (int) ($data['fetched_at'] ?? 0);
$ageMin  = $fetched ? intdiv(time() - $fetched, 60) : null;
$stale   = $ageMin === null || $ageMin > (int) ($cfg['stale_minutes'] ?? 30);

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
  body { font-family: system-ui, sans-serif; margin: 0; padding: 1.25rem; max-width: 32rem; margin-inline: auto; }
  h1 { font-size: 1.4rem; margin: 0 0 1rem; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
  .card { border: 1px solid #8884; border-radius: .75rem; padding: .9rem; }
  .label { font-size: .85rem; opacity: .7; }
  .value { font-size: 1.6rem; font-weight: 600; margin-top: .25rem; }
  .meta { margin-top: 1rem; font-size: .85rem; opacity: .75; }
  .stale { color: #c0392b; opacity: 1; }
</style>
</head>
<body>
<h1>🌱 Skleník – Bojnice</h1>
<div class="grid">
<?php foreach ($rows as [$label, $val]): ?>
  <div class="card"><div class="label"><?= htmlspecialchars($label) ?></div><div class="value"><?= htmlspecialchars($val) ?></div></div>
<?php endforeach; ?>
</div>
<p class="meta<?= $stale ? ' stale' : '' ?>">
<?php if ($ageMin === null): ?>
  Zatiaľ žiadne dáta. Spusti update.php a pozri data/log.txt.
<?php else: ?>
  Aktualizované <?= htmlspecialchars(date('d.m.Y H:i', $fetched)) ?> (pred <?= $ageMin ?> min)<?= $stale ? ' – dáta sú staré' : '' ?>
<?php endif; ?>
</p>
</body>
</html>
