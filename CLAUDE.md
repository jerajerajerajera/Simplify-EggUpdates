<!-- GEMEENSCHAPPELIJK DEEL: in alle 6 Simplify-repo's gelijk. De regels staan in docs/regels.md, niet hier (V40). -->
# Werkwijze Simplify

Simplify is één product: de kern `simplify` plus de add-ons `simplify-servermaker`, `-toolkit`, `-eggupdates`,
`-explain` en `-hosting`. De splitsing is alleen verpakking: elke add-on volgt dezelfde UI en dezelfde regels.
Schrijf met de eigenaar in het Nederlands; code, README, CHANGELOG, commits en PR's in het Engels.

## Lees eerst

De regels staan niet in dit bestand. Lees bij de start, in deze volgorde:

1. `docs/regels.md`: werkregels (WR) en productregels (P). De kernrepo is de bron; elke add-on heeft een identieke kopie.
2. `docs/besluiten.md`: zoek hier eerst voor je zelf iets kiest (kopie van `plannen/besluiten.md` in project-files).
3. `docs/bugs.md`: bekende bugs en hun tests. Raak je die code, draai die tests.
4. `docs/prompts/startbrieven.md`: het algemene deel en de brief van je rol.

Bij verschil tussen dit bestand, de chat en `docs/regels.md` geldt `docs/regels.md`.

## Testen

- `bash tests/run.sh` (draait ook `tests/hub-check.py` en `tests/lang.php`), `pint --test` en in CI `checks.yml`
  met panel-smoke op elke Pelican-versie in de matrix. Wat "klaar" is, staat in P-31.
- Geen `composer install` of `yarn install` in de cloudcontainer; het echte paneel draait op GitHub Actions.
- `tests/docs-sync.sh` (in CI) controleert dat `docs/regels.md`, `docs/besluiten.md` en `docs/prompts/` gelijk
  zijn aan de kern op `main`.

## Deze repo: Egg Updates (`simplify-eggupdates`)

- Controleert eggs op nieuwe versies, meldt het aan root admins en werkt eggs veilig bij (Test first, Move servers,
  Put back). De vorige versie blijft altijd bewaard.
- Test first maakt een tijdelijke testserver op een vrije poort 45000-45999 en ruimt die altijd op, ook bij falen.
  Test dat opruimen bij elke wijziging aan dit pad.
- Doorklikken: Check now, Update één egg, Update all, Put back, en de melding in Pelicans bel.
