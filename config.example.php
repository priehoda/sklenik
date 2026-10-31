<?php
// Skopíruj tento súbor ako config.php a vyplň hodnoty.
// config.php sa NEDÁVA do gitu (je v .gitignore).

return [
    // Tajný token pre CRON volanie update.php?token=...
    // Vygeneruj napr.: php -r 'echo bin2hex(random_bytes(24)), "\n";'
    'token' => 'ZMEN_MA_NA_DLHY_NAHODNY_RETAZEC',

    // Device ID stanice (z adresy https://app.weathercloud.net/d5462888509 bez "d").
    'device_id' => '5462888509',

    // Neoficiálny endpoint Weathercloud (NEOVERENÝ). {id} sa nahradí device_id.
    'endpoint' => 'https://app.weathercloud.net/device/values?code={id}',

    // Session cookie z prihláseného prehliadača, ak endpoint bez nej nefunguje.
    // Nechaj prázdne, kým sa nepotvrdí, že je potrebná. Napr. 'PHPSESSID=abc123'.
    'cookie' => '',

    // Mierka hodnôt NIE JE overená. Ak ?raw=1 ukáže napr. temp=215 a Weathercloud
    // ukazuje 21.5 °C, nastav true (hodnoty sa vydelia 10).
    'divide_by_10' => false,

    // Po koľkých minútach sa dáta na stránke označia ako staré.
    'stale_minutes' => 30,
];
