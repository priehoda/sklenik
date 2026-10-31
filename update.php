<?php
// Volá ho CRON: https://priehoda.com/sklenik/update.php?token=<TOKEN>
// Stiahne dáta z Weathercloud a uloží ich do data/data.json.

declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$cfg = load_config();
$token = (string) ($cfg['token'] ?? '');
if ($token === '' || str_starts_with($token, 'ZMEN_MA') || !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("Forbidden\n");
}

if (!is_dir(__DIR__ . '/data') && !mkdir(__DIR__ . '/data', 0755, true)) {
    http_response_code(500);
    exit("Nedá sa vytvoriť priečinok data/\n");
}

$url = str_replace('{id}', rawurlencode((string) $cfg['device_id']), (string) $cfg['endpoint']);
[$code, $body, $err] = http_get($url, (string) ($cfg['cookie'] ?? ''));

if ($err !== '') {
    write_log("CHYBA spojenia: $err");
    http_response_code(502);
    exit("CHYBA spojenia: $err\n");
}

$json = json_decode($body, true);
if ($code !== 200 || !is_array($json) || $json === []) {
    $snippet = preg_replace('/\s+/', ' ', substr($body, 0, 200));
    $msg = "CHYBA: HTTP $code, odpoveď nie je platné JSON pole. Začiatok odpovede: $snippet";
    write_log($msg);
    http_response_code(502);
    exit($msg . "\n");
}

$out = [
    'fetched_at' => time(),
    'source'     => $url,
    'values'     => $json,
];
$tmp = DATA_FILE . '.tmp';
if (file_put_contents($tmp, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false
    || !rename($tmp, DATA_FILE)) {
    write_log('CHYBA: nedá sa zapísať data.json');
    http_response_code(500);
    exit("CHYBA: nedá sa zapísať data.json\n");
}

$keys = implode(',', array_slice(array_keys($json), 0, 20));
write_log("OK: HTTP $code, kľúče: $keys");
echo "OK\n";
