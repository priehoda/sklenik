<?php
// Prijímač dát zo stanice podľa „WSLink data upload API“ v0.6.
// Stanica volá: https://<doména>/data/upload.php?wsid=...&wspw=...&t1tem=...
// Kódy odpovede podľa API: 200 OK, 400 zlá požiadavka, 401 zlé ID/heslo.

declare(strict_types=1);
require dirname(__DIR__) . '/lib.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$cfg = load_config();
$in  = $_GET + $_POST; // API uvádza GET; POST pre istotu tiež

$id = (string) ($in['wsid'] ?? '');
$pw = (string) ($in['wspw'] ?? '');
$cfgPw = (string) ($cfg['station_password'] ?? '');

if ($id === '' && $pw === '') {
    http_response_code(400);
    exit("Bad request\n");
}
if ($cfgPw === '' || $cfgPw === 'ZMEN_MA'
    || !hash_equals((string) ($cfg['station_id'] ?? ''), $id)
    || !hash_equals($cfgPw, $pw)) {
    write_log('ODMIETNUTÉ: zlé wsid/wspw z ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    http_response_code(401);
    exit("Unauthorized\n");
}

// Ulož iba číselné hodnoty s názvom z API (malé písmená a čísla). Prázdne = chýba.
$values = [];
foreach ($in as $k => $v) {
    if (in_array($k, ['wsid', 'wspw', 'datetime'], true) || !is_string($v)) {
        continue;
    }
    if (preg_match('/^[a-z0-9]{2,16}$/', (string) $k) && is_numeric(trim($v))) {
        $values[$k] = trim($v) + 0;
    }
}

if ($values === []) {
    write_log('CHYBA: požiadavka bez číselných hodnôt');
    http_response_code(400);
    exit("Bad request\n");
}

$now = time();
$latest = [
    'received_at'      => $now,
    'station_datetime' => isset($in['datetime']) && is_string($in['datetime'])
        ? substr(preg_replace('/[^0-9: \-]/', '', $in['datetime']), 0, 19) : null,
    'values'           => $values,
];

ensure_storage();
if (!save_json_atomic(LATEST_FILE, $latest)) {
    write_log('CHYBA: nedá sa zapísať latest.json');
    http_response_code(500);
    exit("Server error\n");
}
append_history($values, $now, (int) ($cfg['history_days'] ?? 90));

$t1cn = $values['t1cn'] ?? null;
write_log(sprintf('OK: %d hodnôt, vonkajší senzor t1cn=%s', count($values), $t1cn === null ? '-' : (string) $t1cn));
echo "OK\n";
