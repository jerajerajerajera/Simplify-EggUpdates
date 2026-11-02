# Regels Simplify

> Eén bron voor alle regels van Simplify (kern en vijf add-ons). De kernrepo is de bron; elke add-on-repo heeft een
> identieke kopie die de documentatie-thread gelijk houdt. Wijzigen alleen via een besluit van de eigenaar (WR-9);
> besluiten staan in `besluiten.md`, bugs in `bugs.md`. Deze lijst vervangt de regels R1–R70 uit
> `plannen/werkwijze/werkwijze.md` (tabel "Oud → nieuw" onderaan). De CLAUDE.md in elke repo is hiervan de korte vorm.

## Principes (in volgorde van voorrang)

1. **Veiligheid**: kan dit misbruikt worden of iets kapotmaken?
2. **Geen dataverlies**: blijven instellingen en gegevens van de gebruiker altijd heel, ook bij een update?
3. **Compatibel**: werkt het op elke Pelican-versie in de CI-matrix, met en zonder kern, naast andere plugins?
4. **0.7.0 is de norm**: wat PelicanSimplify 0.7.0 kon, kan Simplify, op dezelfde manier tenzij de eigenaar anders besloot.
5. **Eenvoud**: snel, overzichtelijk, duidelijk en overal hetzelfde; zo klein mogelijk.

Botsen twee principes, dan wint de bovenste.

## Nummers

| Letter | Betekent | Wie geeft het |
|---|---|---|
| V-n | besluit of vraag van de eigenaar | regisseur |
| R-n | request van buiten (GitHub, Discord) | regisseur |
| W-n | wens van de eigenaar (backlog) | regisseur |
| B-n | bug | regisseur |
| WR-n | werkwijzeregel (dit bestand) | via besluit |
| P-n | productregel (dit bestand) | via besluit |

Threads geven zelf geen nummers; ze melden ongenummerd aan de regisseur.

## Overzicht

| Nr | Regel | Groep |
|---|---|---|
| WR-1 | Alles via de regisseur | Werkwijze |
| WR-2 | Eén opdracht tegelijk, volledig gemeld | Werkwijze |
| WR-3 | Eerst zoeken, dan bouwen | Werkwijze |
| WR-4 | Risico bepaalt de route | Werkwijze |
| WR-5 | Akkoord en mandaat | Werkwijze |
| WR-6 | Releases verzameld, weinig pushes | Werkwijze |
| WR-7 | Overdracht via branch @ commit | Werkwijze |
| WR-8 | Nummers alleen van de regisseur | Werkwijze |
| WR-9 | Afwijking eerst melden, regel zelf aanpassen | Werkwijze |
| WR-10 | Zichtbaar = eerst een voorbeeld | Werkwijze |
| WR-11 | Elke bug wordt een test | Werkwijze |
| WR-12 | Niets geheims in Git of chat | Werkwijze |
| WR-13 | Melden aan de eigenaar | Werkwijze |
| WR-14 | Vaste threads, hulpthreads, controle, opruimen | Werkwijze |
| WR-15 | Screenshots in de repo | Werkwijze |
| WR-16 | Backlog | Werkwijze |
| P-1 | Eén product, add-ons binnen Simplify | Product |
| P-2 | Namen en id's | Product |
| P-3 | Add-on-contract | Product |
| P-4 | Admin-menu | Product |
| P-5 | 0.7.0 is de norm | Product |
| P-6 | Simplify neemt alles over | Product |
| P-7 | Pagina scrolt nooit | UI |
| P-8 | Kaart, knoppen in de titelbalk, geen vak-in-vak | UI |
| P-9 | Iconen en knoppen | UI |
| P-10 | Laden met placeholders | UI |
| P-11 | Menu laadt alleen het contentvak | UI |
| P-12 | Servericoon | UI |
| P-13 | Paginatitel | UI |
| P-14 | Scrollbalken | UI |
| P-15 | Boven de console | UI |
| P-16 | EULA bij First start | UI |
| P-17 | Backups & tasks | UI |
| P-18 | Compact overzicht | UI |
| P-19 | Create server als popup | UI |
| P-20 | Uitklapgroepen | UI |
| P-21 | Meldingen in het bel-blad | UI |
| P-22 | Telefoon | UI |
| P-23 | Seizoen is stil | Teksten |
| P-24 | Screenshots en Hub-teksten | Teksten |
| P-25 | Neutraal schrijven | Teksten |
| P-26 | Hub-regels | Code |
| P-27 | Codestijl | Code |
| P-28 | Migraties en instellingen | Code |
| P-29 | 32 talen | Code |
| P-30 | Geen installs in de cloudcontainer | Code |
| P-31 | Definitie van klaar | Testen |
| P-32 | Uitkomst ok of fout | Testen |
| P-33 | Kernrelease | Release |
| P-34 | Versie en zip | Release |
| P-35 | Zo klein mogelijk | Product |
| P-36 | Veiligheid voorop | Product |
| P-37 | Testfase | Release |

## Werkwijze

#### WR-1 Alles via de regisseur
De eigenaar praat alleen met de regisseur (projectchat). Threads stellen de eigenaar geen vragen; ze sturen vragen,
blokkades en keuzes ongenummerd naar de regisseur. Threads mogen onderling overleggen en melden (afhankelijkheid,
fout in andermans repo, gedeelde functie, documentatie, releases), maar opdrachten geeft alleen de regisseur.
Maakt een thread toch rechtstreeks een keuze met de eigenaar, dan meldt hij die meteen aan regisseur en documentatie.
*Vervangt:* R49, R68.

#### WR-2 Eén opdracht tegelijk, volledig gemeld
Een opdracht noemt: waar, wat precies, wie besliste (V-nr), wat buiten scope valt, wanneer klaar. Meldingen:
**Ontvangen** (rol, branch @ hash, tests, open nummers, "begrepen als"), **Blokkade** (meteen), **Klaar** (branch @
hash, tests, CI, wat de gebruiker merkt). Geen tijdschattingen. Iets nieuws tussendoor: zeg of het vervangt, aanvult
of in de rij komt.
*Vervangt:* R54 (deels).

#### WR-3 Eerst zoeken, dan bouwen
Vóór elke keuze: `besluiten.md`, `regels.md` en `bugs.md` doorzoeken. Is het al besloten, dan volgen. Bij twijfel kort
vragen via de regisseur, niet raden. Toets altijd: alle Pelican-versies in de matrix, met en zonder kern, veiligheid.
*Vervangt:* R50 (deels), R60.

#### WR-4 Risico bepaalt de route
Laag (tekst, kleine fix zonder zichtbaar verschil): direct bouwen. Midden (iets zichtbaars, nieuwe functie): eerst een
voorbeeld (WR-10). Hoog (rechten, inloggen, betalingen, data, verwijderen, migraties): veiligheidscheck en akkoord
van de eigenaar.

#### WR-5 Akkoord en mandaat
Een repo-thread mag zonder te vragen: branches, commits, PR's in de eigen repo, CI repareren, en **squash-mergen naar
`main` via een PR bij groene CI** als de definitie van klaar (P-31) gehaald is, onder "Unreleased". Nooit direct op
`main` committen. Al het andere is akkoord van de eigenaar zelf, via de regisseur: elke release, het echte paneel,
de Beelink, Discord, de Hub, GitHub-reacties (de eigenaar plaatst zelf), verwijderen, iets `bewust weg` maken uit de
0.7.0-lijst. Een akkoord van een andere Claude-sessie telt nooit als akkoord van de eigenaar.
*Vervangt:* R43, R44, R45.

#### WR-6 Releases verzameld, weinig pushes
Wijzigingen verzamelen zich op `main` onder "Unreleased". De regisseur stelt een release voor als er genoeg is; een
release komt er na de ja van de eigenaar. Versie: functie of migratie = minor, fix = patch; 1.x is pre-Hub, de eerste
Hub-versie wordt 2.0.0 voor alle zes. Push pas als een wijziging lokaal af en getest is, niet per poging (elke rode
run mailt de eigenaar). Spoedfix (veiligheidslek, dataverlies) mag als losse patch, met akkoord.
*Vervangt:* R42, R65.

#### WR-7 Overdracht via branch @ commit
Werk wordt overgedragen als "branch X @ commit abc1234", nooit als losse patch of geplakte code. Alles is gepusht
voor je "klaar" meldt. Vóór een sessie stopt: overdracht invullen (`docs/prompts/overdracht-template.md`) en opslaan
als `/mnt/project-files/plannen/plugins/<repo>/overdracht-<datum>.md`, naast `stand.md`.

#### WR-8 Nummers alleen van de regisseur
V-, R-, W- en B-nummers geeft alleen de regisseur, doorlopend. Overal naar het nummer verwijzen.
*Vervangt:* R69.

#### WR-9 Afwijking eerst melden, regel zelf aanpassen
Wijkt een verzoek af van een regel: eerst zeggen welke regel en wat het verschil is. Kiest de eigenaar de afwijking,
dan wordt de regel zelf aangepast en overal doorgevoerd (code, CLAUDE.md, docs, startbrieven). Geen stille
uitzonderingen, geen losse regel naast een bestaande. Bij verschil tussen chat en repo geldt de repo; een nieuw
besluit staat binnen dezelfde dag in `besluiten.md`.

#### WR-10 Zichtbaar = eerst een voorbeeld
Elke wijziging die iets zichtbaar verandert, ook een kleine: eerst een voorbeeld (artifact of demo, vóór/na, nepdata,
telefoon en desktop, licht en donker). Bij een keuze twee of drie varianten naast elkaar. De eigenaar beslist op wat
hij ziet; pas na akkoord bouwen. Bron van een voorbeeld in project-files, niet in Git.
*Vervangt:* R26, R66.

#### WR-11 Elke bug wordt een test
Eerst een test die faalt (rood op de commit met de fout), dan de fix, dan groen. In dezelfde PR een regel in
`docs/bugs.md` (oorzaak, fix, testnaam). Komt een bug terug: eerst uitzoeken waarom de test hem niet ving. Was de
oorzaak een patroon, dan ook de regel hier aanvullen (WR-9) en zo mogelijk een automatische check. Nooit een test
overslaan, uitzetten of verzwakken om groen te krijgen.
*Vervangt:* R39.

#### WR-12 Niets geheims in Git of chat
Geen sleutels, tokens, wachtwoorden, interne adressen, echte gebruikersdata of klantnamen in code, PR's, issues,
CLAUDE.md, docs of output. Demo's en tests met verzonnen data.
*Vervangt:* R48.

#### WR-13 Melden aan de eigenaar
Eerst een volledige lijst, dan één ja/nee-vraag met aanbeveling. Teksten voor de eigenaar (Discord, Hub, GitHub,
release) als Engels codeblok. Commando's in één codeblok met erboven op welk systeem (Beelink, VPS, Windows). Op het
paneel altijd `sudo -u www-data php artisan …`; plugins bijwerken met `sudo /opt/deckzwabbers-site/deploy/simplify-plugins.sh`
(`--check` = alleen plan). Eerlijk zeggen wat niet getest is. De eigenaar zo min mogelijk zelf laten doen.
*Vervangt:* R50–R55.

#### WR-14 Vaste threads, hulpthreads, controle, opruimen
Elke repo heeft één vaste thread die als enige merget. Vaste threads daarnaast: documentatie, releases, website.
Hulpthreads alleen voor een extra controle; ze leveren per repo een rapport in
`plannen/rapporten/<repo>/<datum>-<onderwerp>.md` en sluiten. Vóór elke release start de release-thread een frisse
**controle**-hulpthread (CI groen, elke B-regel nog gedekt, regels getoetst, schone installatie, update vanaf de
oudste ondersteunde versie, verwijderen) met uitslag KLAAR of NIET KLAAR; de controle fikst zelf niets. Klaar =
thread sluiten, oude PR's dicht met reden, titels actueel. Elke nacht controleert een routine of er een nieuwe
Pelican-versie is.
*Vervangt:* R56, R57, R58.

#### WR-15 Screenshots in de repo
Bij testen meteen screenshots van de functies die je raakte in `docs/screenshots/` (vaste naam per scherm, licht en
donker, desktop en telefoon), van een echt paneel. Elke zichtbare merge meldt de thread aan documentatie (wat, welke
screenshots); elke release-kandidaat en compatibiliteitswijziging aan releases.
*Vervangt:* R67, R68 (deels).

#### WR-16 Backlog
Requests (R) en wensen (W) staan in `plannen/backlog.md`. Een thread die een nieuw issue of een PR van iemand anders
ziet, meldt die aan de regisseur en bouwt hem niet zonder ja van de eigenaar. Documentatie houdt de lijst bij.
*Vervangt:* R70.

## Product

#### P-1 Eén product, add-ons binnen Simplify
*Principe:* 5. Simplify is één product; de splitsing in plugins is alleen verpakking. Add-ons werken los, maar met de
kern geïnstalleerd openen ze altijd binnen Simplify (frame, servermenu, hostmodus, Simplify-popup, accountmenu), nooit
als losse Pelican-pagina. Altijd het Simplify-design. Testen met én zonder kern.
*Bewaakt door:* panel-smoke per add-on (met en zonder kern); audit `account-page`, `other-plugins`.
*Vervangt:* R1, R2, R3.

#### P-2 Namen en id's
Plugin-id's `simplify-<naam>` zonder streepje tussen twee woorden. Announcements, Embed en Subdomains zijn
kernfuncties. Nooit noemen dat Toolkit vroeger een andere naam had.
*Vervangt:* R4.

#### P-3 Add-on-contract
`ADDONS.md` (API 4). Een nieuwe `Addon`-methode krijgt altijd een default en een regel in ADDONS.md; een nieuwere
add-on blijft werken op een oudere kern.
*Bewaakt door:* `tests/addons.php` (kern).
*Vervangt:* R5.

#### P-4 Admin-menu
Groep "Simplify", volgorde: kern 1/2, Egg Updates 20, Explain 40, Hosting 50, Server Maker 80, Toolkit 95.
*Vervangt:* R6.

#### P-5 0.7.0 is de norm
*Principe:* 4. Herbouw met álle functies van 0.7.0 (snapshot.10): kleiner is minder code, nooit minder functies.
Functielijst `docs/REFERENCE-070.md` (kern): `aanwezig` alleen met test; `bewust weg`/`anders` alleen met akkoord van
de eigenaar. Een aangeraakt scherm vergelijken met 0.7.0, maten niet groter dan 0.7.0 + 10%.
*Bewaakt door:* audit `section=compare`, `diff-inventory.py`.
*Vervangt:* R7, R8, R9.

#### P-6 Simplify neemt alles over
Niemand hoeft terug naar Pelicans eigen admin of profiel, tenzij er echt iets breekt. Een taak waarvoor je nog naar
Pelican moet, is een ontbrekende functie (ook add-on-pagina's in het Simplify-accountmenu).
*Vervangt:* R61.

#### P-7 Pagina scrolt nooit
De pagina scrolt nooit; alleen het contentvak scrolt, binnen zijn ronding. Geldt ook voor instellingspagina's van
add-ons (`s-fit`).
*Bewaakt door:* audit `scroll-*`, `schedule-edit`, `hub-list`.
*Komt uit:* terugkerende scrollfouten (zie `bugs.md`, patroon "pagina scrolt").
*Vervangt:* R10.

#### P-8 Kaart, knoppen in de titelbalk, geen vak-in-vak
Het contentvak is één kaart; knoppen en Save staan in de titelbalk. Geen vak in een vak.
*Vervangt:* R11, R12.

#### P-9 Iconen en knoppen
Alleen Tabler-iconen. Geen grote rode knoppen; gevaarlijke acties in het ⋯-menu.
*Vervangt:* R13, R14.

#### P-10 Laden met placeholders
Nooit een leeg of springend scherm.
*Vervangt:* R15.

#### P-11 Menu laadt alleen het contentvak
Menu-items en tabbladen laden alleen het contentvak (`wire:navigate`); console en websocket blijven staan. Een
volledige paginalading na een menuklik is een fout.
*Bewaakt door:* audit `nav-*` (merkteken op `window`).
*Vervangt:* R16.

#### P-12 Servericoon
Eigen server-icon.png > Pelican-servericoon > egg-icoon > grasblok (Minecraft) > icoon per soort egg (Python,
Node.js, Steam, …) > kubus. Laadt een icoon niet, dan het volgende.
*Bewaakt door:* audit `egg-kind-icon`, `egg-kind-steam`.
*Besluit:* V12.
*Vervangt:* R17.

#### P-13 Paginatitel
`[servernaam] - Simplify`.
*Vervangt:* R18.

#### P-14 Scrollbalken
Dun (11px, duim 4px), binnen de ronding (14px boven/onder, 4px rand), in de kleur van de achtergrond (wit 16%
donker, zwart 16% licht; console altijd licht).
*Vervangt:* R19.

#### P-15 Boven de console
Menu's, pop-ups en dropdowns liggen boven alles: z-index ≥ 20 (xterm gebruikt tot 10). Testen met een klik: onder
het midden van de knop ligt de knop zelf.
*Bewaakt door:* audit `layers-*`.
*Vervangt:* R20.

#### P-16 EULA bij First start
EULA optioneel bij aanmaken. Het verplichte vinkje komt bij de eerste start, in de titelkaart, met de knop
"First start" (play-icoon). Geen Restart zolang de server nooit gestart is.
*Bewaakt door:* audit `first-eula-*`, `server-eula-accept`.
*Vervangt:* R21.

#### P-17 Backups & tasks
Backups en Schedules zijn één item "Backups & tasks"; "Automatic backups" (Toolkit) is daar een tab.
*Vervangt:* R22.

#### P-18 Compact overzicht
Het serveroverzicht is minstens zo compact als 0.7.0; serverbanners en groepen blijven.
*Vervangt:* R23.

#### P-19 Create server als popup
Elke ingang (overzicht, servermenu, admin › Servers) opent de Server Maker als Simplify-popup, nooit het
Pelican-formulier. Zonder Server Maker opent Pelicans formulier in die popup.
*Bewaakt door:* audit `maker-*`.
*Vervangt:* R24.

#### P-20 Uitklapgroepen
Groepen in het admin-menu ("Simplify", "Other plugins") klappen uit; een losse pagina draagt de pluginnaam.
*Bewaakt door:* audit `other-plugins`, `other-plugins-toggle`.
*Vervangt:* R25.

#### P-21 Meldingen in het bel-blad
Alle meldingsopties (dit apparaat, berichten, updates, Pelican, scheduler, apparaten) staan in het venster van de bel.
*Bewaakt door:* audit `bell-customize`.
*Besluit:* V12.

#### P-22 Telefoon
Eén frame: bovenin naam, bel en account; onderin één balk. Menu's openen als blad van onderaf. Instellingen slaan
vanzelf op. Trekken om te verversen alleen op het overzicht; een blad dichttrekken ververst nooit.
*Bewaakt door:* audit `phone-*`, `pull-*`.

## Teksten

#### P-23 Seizoen is stil
Seizoensversiering nooit in CHANGELOG, releasenotes, README of Hub-teksten, en verborgen op screenshots.
*Vervangt:* R27.

#### P-24 Screenshots en Hub-teksten
Nooit Pelicans gewone beheerschermen (legacy) in screenshots, bundel of Hub; add-ons in hostmodus. Elke
add-on-banner "Works best with Simplify". Eerste Hub-changelog "First version on the Hub". README en Hub-teksten
beschrijven alleen wat in een uitgebrachte versie zit.
*Vervangt:* R28, R29.

#### P-25 Neutraal schrijven
README, CHANGELOG, commits en PR's in het Engels, neutraal; nooit over "AI" of hoe de code ontstond.
*Vervangt:* R31.

## Code

#### P-26 Hub-regels
Geen verboden functienamen, geen eigen updater of pluginbeheer, updates alleen via de Hub. De knop-klasse heet
`Button`, niet `Link`. Hub-upload doet de eigenaar.
*Bewaakt door:* `tests/hub-check.py` in elke repo.
*Vervangt:* R30.

#### P-27 Codestijl
Per functie een eigen bestand; PSR-12 en `pint`; schrijf zoals de code eromheen. Laravel-job: nooit een methode
`queue()`. Na een merge in CSS: accolades tellen. Eigen prefix voor klassen, CSS en routes (`simplify`, `s-`, `ps-`).
*Bewaakt door:* `pint --test` in CI.
*Vervangt:* R32, R34, R37.

#### P-28 Migraties en instellingen
*Principe:* 2. Migraties idempotent (`hasTable`/`hasColumn`), naam bevat `simplify`, nooit plugin-klassen (zoals
`PanelSettings`), alleen `DB`/`Schema`. Instellingen nooit stil overschrijven bij een update. Getest op het echte
updatepad met ingevulde instellingen (kern: vanaf 0.7.0-bestanden en de oudste ondersteunde versie).
*Bewaakt door:* upgradetest in `checks.yml` (kern: 1.3.11 + PelicanSimplify-bestanden → 1.4.4 → nieuw, en "Install
Simplify over its own tables").
*Komt uit:* 1.5.1 "Undefined array key logo_text".
*Vervangt:* R33.

#### P-29 32 talen
Nieuwe of gewijzigde tekst: alle 32 talen in dezelfde PR; Arabisch alleen logische CSS. Geen tekst buiten de
vertaalfunctie.
*Bewaakt door:* `tests/lang.php` in elke repo.
*Vervangt:* R35.

#### P-30 Geen installs in de cloudcontainer
Geen `composer install` of `yarn install` in de cloudcontainer; het echte paneel draait op GitHub Actions.
*Vervangt:* R36.

## Testen en release

#### P-31 Definitie van klaar
Lokale checks (`bash tests/run.sh`, `pint --test`, `python3 tests/hub-check.py`), CI groen met panel-smoke op elke
Pelican-versie in de matrix, updatepad getest bij migratie of instelling, echt doorgeklikt in een paneel dat op het
echte lijkt (klikken, niet kijken), stijlaudit groen bij UI (d1440, laag1366, phone390, licht en donker), screenshot
van het echte scherm. Add-ons met én zonder kern. Kan een stap niet: precies zeggen welke.
*Vervangt:* R38, R40.

#### P-32 Uitkomst ok of fout
Een check heeft altijd de uitkomst ok of fout tegen een regel, nooit alleen informatie.
*Vervangt:* R41.

#### P-33 Kernrelease
Vóór een kernrelease: functielijst bijgewerkt, CI groen, upgradetest groen, stijlaudit groen (ook `compare` en
`extras`), screenshots van elk gewijzigd scherm, controle KLAAR (WR-14), ja van de eigenaar. Dan versie in
`plugin.json` + CHANGELOG; `release.yml` maakt de release met de Hub-zip.
*Vervangt:* R46.

#### P-34 Versie en zip
Versienummer in `plugin.json` en CHANGELOG gelijk. Zip:
`github.com/jerajerajerajera/<Repo>/releases/download/v<v>/<id>-v<v>.zip`.
*Vervangt:* R47.

#### P-35 Zo klein mogelijk
Geen dubbele of onnodige functies, niet meer code dan nodig. Na een aantal wijzigingen een opschoonronde als eigen PR.
*Vervangt:* R62.

#### P-36 Veiligheid voorop
*Principe:* 1. Rechten altijd server-side, invoer valideren, uitvoer escapen, CSRF bij elke actie, geen open routes
zonder controle (open routes staan in de README onder "What it sends where"). Bij twijfel niet bouwen maar vragen.
*Vervangt:* R63.

#### P-37 Testfase
De eerste Hub-versie (2.0.0) moet echt goed zijn; liever langer testen dan veel updates afdwingen.
*Vervangt:* R64.

## Oud → nieuw

| Oud | Nieuw | | Oud | Nieuw |
|---|---|---|---|---|
| R1–R3 | P-1 | | R38, R40 | P-31 |
| R4 | P-2 | | R39 | WR-11 |
| R5 | P-3 | | R41 | P-32 |
| R6 | P-4 | | R42 | WR-6 |
| R7–R9 | P-5 | | R43–R45 | WR-5 |
| R10 | P-7 | | R46 | P-33 |
| R11, R12 | P-8 | | R47 | P-34 |
| R13, R14 | P-9 | | R48 | WR-12 |
| R15 | P-10 | | R49, R68 | WR-1 (+ WR-15) |
| R16 | P-11 | | R50–R55 | WR-13 (+ WR-2, WR-3) |
| R17 | P-12 | | R56–R58 | WR-14 |
| R18 | P-13 | | R59 | Principes |
| R19 | P-14 | | R60 | WR-3, P-5 |
| R20 | P-15 | | R61 | P-6 |
| R21 | P-16 | | R62 | P-35 |
| R22 | P-17 | | R63 | P-36 |
| R23 | P-18 | | R64 | P-37 |
| R24 | P-19 | | R65 | WR-6 |
| R25 | P-20 | | R66, R26 | WR-10 |
| R27 | P-23 | | R67 | WR-15 |
| R28, R29 | P-24 | | R69 | WR-8 |
| R30 | P-26 | | R70 | WR-16 |
| R31 | P-25 | | | |
| R32, R34, R37 | P-27 | | | |
| R33 | P-28 | | | |
| R35 | P-29 | | | |
| R36 | P-30 | | | |
