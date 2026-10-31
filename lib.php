<?php
// Spoločné funkcie pre update.php a index.php.

date_default_timezone_set('Europe/Bratislava');

const DATA_FILE = __DIR__ . '/data/data.json';
const LOG_FILE  = __DIR__ . '/data/log.txt';
const LOG_MAX_BYTES = 200000; // pri prekročení sa log skráti

function load_config(): array
{
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        http_response_code(500);
        exit("Chýba config.php (skopíruj config.example.php ako config.php).\n");
    }
    $cfg = require $file;
    if (!is_array($cfg)) {
        http_response_code(500);
        exit("config.php musí vracať pole.\n");
    }
    return $cfg;
}

function write_log(string $msg): void
{
    if (is_file(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_BYTES) {
        $tail = substr((string) file_get_contents(LOG_FILE), -intdiv(LOG_MAX_BYTES, 2));
        file_put_contents(LOG_FILE, $tail, LOCK_EX);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/** Vráti [httpKód, telo, chyba] */
function http_get(string $url, string $cookie): array
{
    $headers = [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'X-Requested-With: XMLHttpRequest',
        'Referer: https://app.weathercloud.net/',
    ];
    $ua = 'Mozilla/5.0 (compatible; priehoda-sklenik/1.0)';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($cookie !== '') {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        $body = curl_exec($ch);
        $err  = $body === false ? curl_error($ch) : '';
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $body === false ? '' : $body, $err];
    }

    if (ini_get('allow_url_fopen')) {
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => 20, 'ignore_errors' => true,
            'user_agent' => $ua, 'header' => implode("\r\n", $headers),
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $code = (int) $m[1];
            }
        }
        return [$code, $body === false ? '' : $body, $body === false ? 'file_get_contents zlyhal' : ''];
    }

    return [0, '', 'Na hostingu nie je cURL ani allow_url_fopen'];
}

function load_data(): ?array
{
    if (!is_file(DATA_FILE)) {
        return null;
    }
    $d = json_decode((string) file_get_contents(DATA_FILE), true);
    return is_array($d) ? $d : null;
}
