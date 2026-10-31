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

    // Upozornenie e-mailom pri mraze (najviac raz denne). Prázdne = vypnuté.
    'alert_email' => '',
    'alert_temp'  => 0,
];
