<?php
// Spoločné funkcie.

date_default_timezone_set('Europe/Bratislava');

const STORAGE_DIR  = __DIR__ . '/storage';
const LATEST_FILE  = STORAGE_DIR . '/latest.json';
const HISTORY_FILE = STORAGE_DIR . '/history.csv';
const LOG_FILE     = STORAGE_DIR . '/log.txt';
const LOG_MAX_BYTES = 200000;

/** Stĺpce zapisované do histórie (podmnožina parametrov WSLink API). */
const HISTORY_COLUMNS = ['intem', 'inhum', 'rbar', 't1tem', 't1hum', 't1ws', 't1wgust', 't1wdir', 't1raindy', 't1uvi', 't1solrad'];

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

function ensure_storage(): void
{
    if (!is_dir(STORAGE_DIR) && !mkdir(STORAGE_DIR, 0755, true)) {
        http_response_code(500);
        exit("Nedá sa vytvoriť priečinok storage/\n");
    }
}

function write_log(string $msg): void
{
    ensure_storage();
    if (is_file(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_BYTES) {
        $tail = substr((string) file_get_contents(LOG_FILE), -intdiv(LOG_MAX_BYTES, 2));
        file_put_contents(LOG_FILE, $tail, LOCK_EX);
    }
    file_put_contents(LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function load_latest(): ?array
{
    if (!is_file(LATEST_FILE)) {
        return null;
    }
    $d = json_decode((string) file_get_contents(LATEST_FILE), true);
    return is_array($d) ? $d : null;
}

function save_json_atomic(string $file, array $data): bool
{
    $tmp = $file . '.tmp';
    return file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false
        && rename($tmp, $file);
}

function append_history(array $values, int $ts, int $days): void
{
    if ($days <= 0) {
        return;
    }
    $new = !is_file(HISTORY_FILE);
    $fh = fopen(HISTORY_FILE, 'a');
    if (!$fh) {
        write_log('CHYBA: nedá sa zapísať history.csv');
        return;
    }
    flock($fh, LOCK_EX);
    if ($new) {
        fputcsv($fh, array_merge(['time'], HISTORY_COLUMNS), ',', '"', '');
    }
    $row = [date('Y-m-d\TH:i:s', $ts)];
    foreach (HISTORY_COLUMNS as $c) {
        $row[] = $values[$c] ?? '';
    }
    fputcsv($fh, $row, ',', '"', '');
    flock($fh, LOCK_UN);
    fclose($fh);

    // Raz denne (približne) orež staré riadky.
    if (mt_rand(1, 288) === 1) {
        prune_history($days);
    }
}

function prune_history(int $days): void
{
    $lines = file(HISTORY_FILE, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return;
    }
    $limit = date('Y-m-d\TH:i:s', time() - $days * 86400);
    $keep = [$lines[0]];
    foreach (array_slice($lines, 1) as $l) {
        if (substr($l, 0, 19) >= $limit) {
            $keep[] = $l;
        }
    }
    file_put_contents(HISTORY_FILE, implode("\n", $keep) . "\n", LOCK_EX);
}
