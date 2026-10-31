<?php
// Spoločné funkcie.

date_default_timezone_set('Europe/Bratislava');
ini_set('serialize_precision', '-1'); // Bez šumu typu 21.199999 v JSON.

const STORAGE_DIR  = __DIR__ . '/storage';
const LATEST_FILE  = STORAGE_DIR . '/latest.json';
const HISTORY_FILE = STORAGE_DIR . '/history.csv';
const DAILY_FILE   = STORAGE_DIR . '/daily.json';
const ALERT_FILE   = STORAGE_DIR . '/alert.txt';
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

function append_history(array $values, int $ts): void
{
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
}

/**
 * Denné súhrny z histórie: 'RRRR-MM-DD' => [min °C, max °C, zrážky mm].
 * Výsledok je v daily.json a prepočíta sa, až keď pribudne nové meranie.
 */
function daily_stats(): array
{
    if (!is_file(HISTORY_FILE)) {
        return [];
    }
    if (is_file(DAILY_FILE) && filemtime(DAILY_FILE) > filemtime(HISTORY_FILE)) {
        $d = json_decode((string) file_get_contents(DAILY_FILE), true);
        if (is_array($d)) {
            return $d;
        }
    }
    $fh = fopen(HISTORY_FILE, 'r');
    if (!$fh) {
        return [];
    }
    $head = fgetcsv($fh, null, ',', '"', '');
    $iTemp = array_search('t1tem', $head ?: [], true);
    $iRain = array_search('t1raindy', $head ?: [], true);
    $days = [];
    $prevRain = 0.0;
    while (($r = fgetcsv($fh, null, ',', '"', '')) !== false) {
        $day = substr((string) $r[0], 0, 10);
        $temp = $r[$iTemp] ?? '';
        $rain = $r[$iRain] ?? '';
        if (!is_numeric($temp) && !is_numeric($rain)) {
            continue;
        }
        $days[$day] ??= [null, null, null];
        if (is_numeric($temp)) {
            $t = (float) $temp;
            $days[$day][0] = $days[$day][0] === null ? $t : min($days[$day][0], $t);
            $days[$day][1] = $days[$day][1] === null ? $t : max($days[$day][1], $t);
        }
        if (is_numeric($rain)) {
            // Stanica posiela denný súčet a sama ho nuluje, preto sa sčítavajú prírastky.
            // Pokles pod 1 mm je šum zaokrúhlenia, väčší pokles je vynulovanie.
            $cur = (float) $rain;
            $add = $cur >= $prevRain ? $cur - $prevRain : ($prevRain - $cur < 1 ? 0.0 : $cur);
            $days[$day][2] = round(($days[$day][2] ?? 0) + $add, 1);
            $prevRain = $cur;
        }
    }
    fclose($fh);
    save_json_atomic(DAILY_FILE, $days);
    return $days;
}

/** Pošle najviac jeden e-mail denne, keď vonkajšia teplota klesne na hranicu mrazu. */
function check_frost_alert(array $cfg, array $values, int $ts): void
{
    $to = (string) ($cfg['alert_email'] ?? '');
    if ($to === '' || !isset($values['t1tem']) || $values['t1tem'] > (float) ($cfg['alert_temp'] ?? 0)) {
        return;
    }
    $day = date('Y-m-d', $ts);
    if (is_file(ALERT_FILE) && trim((string) file_get_contents(ALERT_FILE)) === $day) {
        return;
    }
    $text = sprintf("Teplota v skleníku klesla na %s °C (%s).\n", number_format((float) $values['t1tem'], 1, ',', ''), date('d.m.Y H:i', $ts));
    if (mail($to, 'Sklenik: mraz', $text, 'Content-Type: text/plain; charset=utf-8')) {
        file_put_contents(ALERT_FILE, $day, LOCK_EX);
        write_log('UPOZORNENIE: mráz, e-mail odoslaný');
    } else {
        write_log('CHYBA: e-mail o mraze sa nepodarilo odoslať');
    }
}
