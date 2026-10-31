# Skleník – meteostanica Sencor SWS 10500 → priehoda.com/sklenik

Zobrazenie dát zo stanice Sencor SWS 10500 (WiFi, skleník, Bojnice) na https://priehoda.com/sklenik (hosting Websupport), aby sa dali jednoducho otvoriť na telefóne Google Pixel (odkaz na ploche).

> **Stav kódu: NEOTESTOVANÝ proti skutočnému Weathercloud.** Otestovaný je iba so simulovanými dátami. Z vývojového prostredia sa k Weathercloud nedalo pripojiť (proxy 403).

## Súbory

| Súbor | Účel |
|---|---|
| `update.php` | Volá ho CRON. Overí `?token=`, stiahne dáta z Weathercloud (cURL, inak `file_get_contents`), uloží ich do `data/data.json`, výsledok alebo chybu zapíše do `data/log.txt`. |
| `index.php` | Zobrazí teplotu (`temp`), vlhkosť (`hum`), tlak (`bar`) a vietor (`wspdavg`) a čas poslednej aktualizácie. Stránka sa obnovuje každých 5 minút. `?raw=1` vypíše surové uložené dáta. |
| `lib.php` | Spoločné funkcie. |
| `config.example.php` | Vzor nastavení. Skopíruj ho ako `config.php` (ten nie je v gite). |
| `.htaccess`, `data/.htaccess` | Zakážu priamy prístup ku `config.php`, `data/` a výpis priečinka (Apache 2.4; či ich Websupport rešpektuje, treba overiť). |

Tajný token a prípadná cookie sú iba v `config.php` na serveri. **Nikdy ich necommituj.**

## Nasadenie na Websupport

1. Cez FTP alebo správcu súborov nahraj obsah repozitára do priečinka `sklenik` v koreni webu priehoda.com (vrátane `.htaccess` a `data/.htaccess`).
2. Na serveri skopíruj `config.example.php` ako `config.php` a nastav `token` na dlhý náhodný reťazec, napr. z príkazu:
   ```bash
   php -r 'echo bin2hex(random_bytes(24)), "\n";'
   ```
3. Ručne otvor v prehliadači `https://priehoda.com/sklenik/update.php?token=<TOKEN>`. Musí vypísať `OK`. Ak vypíše chybu, pozri ju (aj v `data/log.txt`).
4. Over, že `https://priehoda.com/sklenik/config.php` a `https://priehoda.com/sklenik/data/log.txt` vracajú 403 (Forbidden).
5. CRON: Pokročilá konfigurácia → CRON úlohy → typ „Návšteva URL adresy (wget)“, každých 10 minút, URL `https://priehoda.com/sklenik/update.php?token=<TOKEN>`, e-mailové výsledky vypnúť.
6. Na Pixeli otvor `https://priehoda.com/sklenik` v Chrome → menu ⋮ → „Add to Home screen“ / „Pridať na plochu“.

## Čo treba overiť po prvých dátach

- **Endpoint bez prihlásenia.** Ak `update.php` hlási HTML namiesto JSON alebo HTTP 401/403, endpoint pravdepodobne vyžaduje session cookie (podľa TapHome). Vtedy ju skopíruj z prihláseného prehliadača do `cookie` v `config.php` (nie do verejného súboru).
- **Mierka hodnôt.** Porovnaj `?raw=1` so stránkou Weathercloud. Ak je napr. `temp` = 215 a Weathercloud ukazuje 21,5 °C, nastav `divide_by_10 => true` (delí teplotu, tlak a vietor; vlhkosť nie).
- **Jednotky vetra.** Stránka predpokladá m/s. Neoverené.
- **Hosting.** Či PHP smie volať externé weby (cURL / allow_url_fopen). Ak nie, `update.php` vypíše „Na hostingu nie je cURL ani allow_url_fopen“.

---

## Kontext (z exportu konverzácie, 2026-10-31)

### Stav stanice
- Stanica je pripojená na WiFi cez aplikáciu WSLink a posiela údaje na Weathercloud. Na Weathercloud je online od 2026-10-31 10:47, ale všetky hodnoty sú prázdne. Od stanice zatiaľ neprišli žiadne merania.
- **Vonkajší 7-v-1 senzor nie je spárovaný s konzolou.** Vyskúšané bez úspechu: vybratie a vloženie batérií, reštart konzoly, poradie zapnutia (najprv senzor, potom konzola), senzor hneď vedľa konzoly. Neskúšané: RESET tlačidlo na senzore, továrenský reset konzoly (RESET vzadu ~10 s), nové lítiové batérie. Možná chyba senzora (reklamácia, Sencor servis SK).
- Pri aktualizácii firmvéru sa objavila chyba E01 (zaseknutie na 15 %). Nie je potvrdené, že je vyriešená.

### Weathercloud
- Stanica: **Sklenik**, verejná stránka https://app.weathercloud.net/d5462888509
- Device ID na čítanie: `5462888509` (z adresy stanice bez „d“).
- Pole „Weathercloud ID“ (16 znakov) slúži na odosielanie zo stanice, nie na čítanie. V repozitári zámerne nie je.
- Weathercloud nemá oficiálne verejné API na čítanie. Používa sa neoficiálny, reverzne zistený endpoint `https://app.weathercloud.net/device/values?code=5462888509` (zdroje: TapHome šablóna, knižnice `weathercloud` na PyPI a `weathercloud-js` na npm). Názvy polí sú z dokumentácie `weathercloud-js`.
- Bezplatný účet aktualizuje dáta každých 10 minút (podľa TapHome).
- Riziko: neoficiálne API sa môže zmeniť alebo zablokovať.

### Wunderground
Pridanie stanice zlyhalo s hláškou „There was a problem. Please try again.“ (skúšaný iný názov bez diakritiky, obnova stránky, iný prehliadač). Príčina nezistená. Rozhodnutie: používa sa Weathercloud.

### Ďalšie
- Oficiálnu mobilnú aplikáciu Weathercloud sa nenašla (nepotvrdené, že neexistuje). Wunderground má oficiálnu aplikáciu „Weather Underground: Local Map“.
- Postup zmeny jednotky vetra na konzole (m/s → km/h) nebol overený. Weathercloud web má v nastaveniach voľbu jednotiek.
- TapHome sa nepoužíva, lebo ho používateľ má v inom dome než stanicu.

## Otvorené body
- [ ] Spárovať vonkajší senzor, aby Weathercloud dostával dáta.
- [ ] Nahrať súbory, spustiť `update.php` ručne, skontrolovať `data/log.txt`.
- [ ] Overiť, či endpoint funguje bez prihlásenia (inak cookie v `config.php`).
- [ ] Overiť mierku hodnôt (`?raw=1` vs. stránka Weathercloud).
- [ ] Pridať stránku na plochu Pixelu.
