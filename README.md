# Skleník – meteostanica Sencor SWS 10500 → vlastný server

Stanica Sencor SWS 10500 (WiFi, skleník, Bojnice) posiela dáta **priamo na vlastný server** cez funkciu „vlastný server“ v aplikácii WSLink. Stránka ich zobrazí na mobile (Google Pixel, odkaz na ploche). Weathercloud ani žiadne neoficiálne API nie sú potrebné.

> **Stav: otestované iba simulovanými požiadavkami** (vrátane ukážkovej požiadavky z dokumentácie API). So skutočnou stanicou zatiaľ nie.

## Protokol

Podľa dokumentu **„WSLink data upload API“ v0.6** (výrobca stanice, 7. 11. 2023; kópia: [miroslavmatysek/wslink-api – docs/API.pdf](https://github.com/miroslavmatysek/wslink-api/blob/main/docs/API.pdf)):

- Stanica volá `GET https://<doména>/data/upload.php?wsid=<ID>&wspw=<heslo>&...` (http alebo https). **Cesta `/data/upload.php` je pevná**, zadáva sa iba doména.
- Jednotky: °C, %, hPa, m/s, mm, W/m². Hodnoty sú desatinné čísla (žiadne desatiny ×10).
- Dôležité parametre: `intem`/`inhum` (konzola), `rbar` (relatívny tlak), `t1tem`, `t1hum`, `t1ws`, `t1wgust`, `t1wdir`, `t1raindy`, `t1uvi`, `t1solrad`, `t1dew`, `t1cn` (vonkajší senzor pripojený = 1).
- Odpovede servera: 200 OK, 400 zlá požiadavka, 401 zlé ID alebo heslo.

## Súbory

| Súbor | Účel |
|---|---|
| `data/upload.php` | Prijíma dáta zo stanice. Overí `wsid`/`wspw`, uloží posledné hodnoty do `storage/latest.json`, pridá riadok do `storage/history.csv` a zapíše záznam do `storage/log.txt` (heslo sa nikam neukladá). |
| `index.php` | Stránka pre mobil: vonkajší senzor (teplota, vlhkosť, vietor, nárazy, zrážky, UV, žiarenie, rosný bod) a konzola (teplota, vlhkosť, tlak). Ak senzor nie je pripojený, ukáže varovanie. Obnovuje sa každých 5 minút. `?raw=1` vypíše posledné dáta ako JSON. |
| `lib.php` | Spoločné funkcie. |
| `config.example.php` | Vzor nastavení. Skopíruj ho ako `config.php` (ten nie je v gite). |
| `.htaccess`, `storage/.htaccess` | Zakážu priamy prístup ku `config.php`, `storage/` a výpis priečinka (Apache 2.4; či ich Websupport rešpektuje, treba overiť). |

Heslo stanice je iba v `config.php` na serveri a v aplikácii WSLink. **Nikdy ho necommituj.**

## Nasadenie na Websupport

Keďže stanica posiela vždy na `/data/upload.php` v koreni domény, odporúčam **subdoménu** (napr. `sklenik.priehoda.com`), ktorej koreň je priečinok `sklenik`. Potom funguje `https://sklenik.priehoda.com/data/upload.php` a stránka je aj na `https://priehoda.com/sklenik`. (Postup vytvorenia subdomény a nastavenia jej koreňového priečinka vo Websupport som neoveril.)

1. Nahraj obsah repozitára do priečinka `sklenik` (vrátane `.htaccess`, `storage/.htaccess` a priečinka `data/`).
2. Na serveri skopíruj `config.example.php` ako `config.php` a nastav `station_id` a `station_password`. Heslo vygeneruj napr. takto (dĺžkový limit vo WSLink nie je overený, ak ho aplikácia neprijme, skráť ho):
   ```bash
   php -r 'echo bin2hex(random_bytes(8)), "\n";'
   ```
3. Over server ručne v prehliadači:
   - `https://sklenik.priehoda.com/data/upload.php?wsid=<ID>&wspw=<HESLO>&intem=21.5&inhum=60&rbar=1013&t1cn=0` musí vrátiť `OK`.
   - `https://sklenik.priehoda.com/` potom ukáže 21,5 °C v časti Konzola.
   - `https://sklenik.priehoda.com/config.php` a `https://sklenik.priehoda.com/storage/log.txt` musia vrátiť 403.
4. V aplikácii WSLink pri vlastnom serveri zadaj URL (doménu, napr. `sklenik.priehoda.com`; či sa píše aj `https://`, závisí od aplikácie), ID stanice a heslo rovnaké ako v `config.php`.
5. Sleduj `storage/log.txt` (cez FTP/správcu súborov) alebo `?raw=1`. Pri `ODMIETNUTÉ` nesedí ID alebo heslo.
6. Na Pixeli otvor stránku v Chrome → menu ⋮ → „Pridať na plochu“.

Aj bez spárovaného vonkajšieho senzora by mali prichádzať údaje z konzoly (`intem`, `inhum`, `rbar`). Tak sa dá overiť, že spojenie stanica → server funguje. (Že ich stanica posiela aj s odpojeným senzorom, je predpoklad.)

## Nepotvrdené

- Či stanica vie posielať súčasne na Weathercloud aj na vlastný server.
- Či stanica akceptuje HTTPS certifikát hostingu (podľa API podporuje http aj https).
- Ako často stanica posiela dáta (interval v WSLink).

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
- [ ] Spárovať vonkajší senzor (bez neho prídu len údaje z konzoly).
- [ ] Nahrať súbory, nastaviť subdoménu a `config.php`, otestovať `data/upload.php` ručne.
- [ ] Nastaviť vlastný server vo WSLink a skontrolovať `storage/log.txt`.
- [ ] Pridať stránku na plochu Pixelu.
