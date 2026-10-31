<?php
// Skopíruj tento súbor ako config.php a vyplň hodnoty.
// config.php sa NEDÁVA do gitu (je v .gitignore).

return [
    // Rovnaké hodnoty, aké zadáš v aplikácii WSLink pri vlastnom serveri
    // (parameter wsid = ID stanice, wspw = heslo stanice). Vymysli si ich sám.
    // Heslo: dlhý náhodný reťazec z písmen a číslic (max. dĺžka v WSLink nie je overená).
    'station_id'       => 'sklenik',
    'station_password' => 'ZMEN_MA',

    // Po koľkých minútach sa dáta na stránke označia ako staré.
    'stale_minutes' => 30,

    // Koľko dní histórie držať v storage/history.csv (0 = nezapisovať históriu).
    'history_days' => 90,
];
