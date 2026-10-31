<?php
// Prijímač dát zo stanice vo formáte Wunderground (túto adresu stanica reálne volá).
// Stanica volá: https://<doména>/weatherstation/updateweatherstation.php?ID=...&PASSWORD=...&tempf=...
// Imperiálne jednotky sa prevedú na názvy a jednotky z WSLink API, ktoré používa zvyšok aplikácie.

declare(strict_types=1);
require dirname(__DIR__) . '/lib.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$cfg = load_config();
$in  = $_GET + $_POST;

$id = (string) ($in['ID'] ?? '');
$pw = (string) ($in['PASSWORD'] ?? '');
$cfgPw = (string) ($cfg['station_password'] ?? '');

if ($id === '' && $pw === '') {
    http_response_code(400);
    exit("Bad request\n");
}
if ($cfgPw === '' || $cfgPw === 'ZMEN_MA'
    || !hash_equals((string) ($cfg['station_id'] ?? ''), $id)
    || !hash_equals($cfgPw, $pw)) {
    write_log('ODMIETNUTÉ: zlé ID/PASSWORD z ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    http_response_code(401);
    exit("Unauthorized\n");
}

$fToC    = static fn (float $f): float => round(($f - 32) * 5 / 9, 1);
$mphToMs = static fn (float $x): float => round($x * 0.44704, 1);
$same    = static fn (float $x): float => $x;

// Parameter stanice => [názov v aplikácii, prevod].
$map = [
    'indoortempf'    => ['intem', $fToC],
    'indoorhumidity' => ['inhum', $same],
    'baromin'        => ['rbar', static fn (float $x): float => round($x * 33.8639, 1)],
    'tempf'          => ['t1tem', $fToC],
    'humidity'       => ['t1hum', $same],
    'windspeedmph'   => ['t1ws', $mphToMs],
    'windgustmph'    => ['t1wgust', $mphToMs],
    'winddir'        => ['t1wdir', $same],
    'dailyrainin'    => ['t1raindy', static fn (float $x): float => round($x * 25.4, 1)],
    'UV'             => ['t1uvi', $same],
    'solarradiation' => ['t1solrad', $same],
    'dewptf'         => ['t1dew', $fToC],
];

// Hodnota -9999 alebo mimo čísla znamená, že senzor údaj nemá.
$values = [];
foreach ($map as $param => [$name, $convert]) {
    $raw = $in[$param] ?? null;
    if (is_string($raw) && is_numeric(trim($raw)) && (float) $raw > -9000) {
        $values[$name] = $convert((float) $raw);
    }
}

// Názvy parametrov (bez hodnôt a hesla) kvôli kontrole, čo stanica naozaj posiela.
$names = implode(',', array_diff(array_map('strval', array_keys($in)), ['ID', 'PASSWORD']));

if ($values === []) {
    write_log('CHYBA: žiadne známe hodnoty, parametre: ' . $names);
    http_response_code(400);
    exit("Bad request\n");
}

// Tento formát nemá údaj o pripojení senzora, takže ho odvodíme z vonkajšej teploty.
$values['t1cn'] = isset($values['t1tem']) ? 1 : 0;

$now = time();
$latest = [
    'received_at'      => $now,
    'station_datetime' => isset($in['dateutc']) && is_string($in['dateutc'])
        ? substr(preg_replace('/[^0-9: \-]/', '', $in['dateutc']), 0, 19) : null,
    'values'           => $values,
];

ensure_storage();
if (!save_json_atomic(LATEST_FILE, $latest)) {
    write_log('CHYBA: nedá sa zapísať latest.json');
    http_response_code(500);
    exit("Server error\n");
}
append_history($values, $now);
check_frost_alert($cfg, $values, $now);

write_log(sprintf('OK: %d hodnôt, vonkajší senzor t1cn=%d, parametre: %s', count($values), $values['t1cn'], $names));
echo "success\n";
