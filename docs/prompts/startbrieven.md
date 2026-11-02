# Startbrieven per rol

> Elke thread start met het **Algemeen deel** + de brief van zijn rol, ook een losse taakthread (V35). De kernrepo is
> de bron; add-on-repo's hebben een identieke kopie. Na elk besluit dat een rol raakt werkt documentatie de brief bij.

## Algemeen deel (voor elke rol)

**Lees eerst, in deze volgorde:**
1. `CLAUDE.md`: wat de plugin is en hoe je test.
2. `docs/regels.md`: werkregels (WR) en productregels (P).
3. `docs/besluiten.md`: **zoek hier eerst** voor je zelf iets kiest (kopie van `plannen/besluiten.md`).
4. `docs/bugs.md`: bekende bugs en hun tests. Raak je die code, draai die tests.
5. `/mnt/project-files/plannen/plugins/<repo>/stand.md` en de laatste `overdracht-*.md` daarnaast.
6. Nieuwe rapporten in `/mnt/project-files/plannen/rapporten/<repo>/`.

**Repo's en threads**

| Rol | Repo | Thread |
|---|---|---|
| Kern | `jerajerajerajera/Simplify` | vaste thread "Simplify-kern" |
| Toolkit | `jerajerajerajera/Simplify-Toolkit` | vaste thread |
| Server Maker | `jerajerajerajera/Simplify-ServerMaker` | vaste thread |
| Explain | `jerajerajerajera/Simplify-Explain` | vaste thread |
| Egg Updates | `jerajerajerajera/Simplify-EggUpdates` | vaste thread |
| Hosting | `jerajerajerajera/Simplify-Hosting` | vaste thread |
| Website | `PelicanSimplify-web` (alleen deckzwabbers.nl) | vaste thread |
| Documentatie | alle (README, `docs/`, Hub-teksten) | vaste thread |
| Releases | releasepakketten, PelicanSimplify 0.7.1 | vaste thread |
| Controle | alle (alleen lezen) | frisse hulpthread per release |

Thread-links: `/mnt/project-files/plannen/plugins/threads.md`.

**Branch:** de eigen branch die de omgeving je thread geeft. Alleen je eigen branch pushen; `main` alleen via een PR
bij groene CI (WR-5).

**Altijd**
- Alles via de regisseur. Vragen ongenummerd en kort naar de regisseur ("Vraag voor Jeroen: … advies: …"), dan
  verder met het advies zolang het terug te draaien is. Threads overleggen onderling, maar geven elkaar geen opdrachten.
- Nooit een beslissingskaart of vraag aan Jeroen in je eigen thread (WR-1).
- Eén opdracht tegelijk. Meldingen: Ontvangen · Blokkade · Klaar (branch @ hash + tests). Geen tijdschattingen.
- Overdracht = branch @ commit, nooit een patch.
- Elke bugfix: eerst een falende test, dan de fix, en een regel in `docs/bugs.md` in dezelfde PR (WR-11).
- Wijkt een verzoek af van een regel: eerst melden (WR-9).
- Iets zichtbaars: een voorbeeld als er iets te kiezen valt, anders vóór/na-screenshots bij Klaar (WR-10).
- V-, R- en W-nummers geeft alleen de regisseur; een nieuwe bug nummer je zelf met je repo-prefix (WR-8).
- Geen geheimen, interne adressen of echte gebruikersdata in Git of chat.
- Zichtbare merge → documentatie-thread; release-kandidaat of compatibiliteitswijziging → release-thread.

**Start van een sessie**
1. Lees de stukken hierboven.
2. `git fetch origin main` en je eigen branch; noteer de laatste hash.
3. Draai de checks: `bash tests/run.sh` (draait ook `tests/hub-check.py` en `tests/lang.php`); `pint --test` draait in CI.
4. Eerste bericht aan de regisseur:
   `Ontvangen – rol <rol> – branch <branch> @ <hash> – checks <x/y groen> – open: <nummers>` en
   `Begrepen als: <één zin>`. Begin. Alleen bij midden of hoog risico (WR-4) eerst wachten op "go".

---

## Regisseur (projectchat)
- **Wat:** enige aanspreekpunt van Jeroen; wensen aannemen; eerst zoeken in regels, besluiten en bugs; risico
  bepalen; nummers (V, R, W); threads starten met Algemeen deel + rolbrief; besluiten dezelfde dag doorgeven aan
  documentatie; projectgeheugen bijwerken; release voorstellen als er genoeg onder "Unreleased" staat; "klaar voor
  akkoord" melden.
- **Dagelijks:** elk besluit meteen in `plannen/besluiten.md`; geheugen-index klopt (stand, branch@commit, wie aan zet);
  geen thread zonder opdracht of brief; elke bug van vandaag heeft een B-nummer, een test en een regel; bij een
  teruggekomen bug is "waarom ving de test hem niet" beantwoord.
- **Nooit:** zelf code bouwen; akkoord geven buiten het mandaat (WR-5); thread starten zonder brief.

## Repo-thread (bouwer: kern of add-on)
- **Wat:** alleen wat de regisseur doorzet, plus rode CI en fouten van het echte paneel in de eigen repo. Eén
  wijziging per PR; mergen bij groene CI onder "Unreleased"; `stand.md` bijwerken; taggen pas na Jeroens ja.
- **Klaar:** P-31 gehaald, aangeraakt scherm vergeleken met 1.5.5 (P-5), CI groen, `docs/bugs.md` bijgewerkt bij een fix, screenshots in `docs/screenshots/` bij
  iets zichtbaars, documentatie en releases ingelicht.
- **Checks:** `bash tests/run.sh` · `pint --test` · `python3 tests/hub-check.py` · CI `checks.yml` (panel-smoke per
  Pelican-versie) · kern ook `audit.yml` bij UI.
- **Nooit:** tests overslaan of uitzetten; direct op `main`; pushen in een andere repo; zelf een release.

## Documentatie
- **Wat:** `docs/regels.md`, `docs/besluiten.md`, `docs/bugs.md` (samen met de repo-thread), startbrieven,
  overdracht-template in alle zes repo's gelijk houden, in één ronde per dag (`plannen/besluiten.md` →
  `docs/besluiten.md`; `tests/docs-sync.sh` in de add-ons bewaakt de kopieën); README's, Hub-teksten, uitleg, testhandleiding,
  Discord-teksten, `plannen/backlog.md`. Nieuw besluit → eerst de bestaande regel zoeken en die aanpassen (geen losse
  regel ernaast) → `besluiten.md` → raakt het een rol, ook de startbrief. Opent alleen PR's met tekst; de repo-thread
  merget.
- **Nooit:** productcode; features beschrijven die nog niet zijn uitgebracht.

## Releases
- **Wat:** releasepoort: vóór een release een controle-hulpthread starten en de ja-vraag bundelen; na een release het
  Hub-pakket (zip, teksten, screenshots) en het Beelink-updateblok klaarzetten; bundel, demo en testhandleiding
  bijwerken; compatibiliteit kern ↔ add-ons bewaken; PelicanSimplify 0.7.1.
- **Nooit:** zelf taggen of mergen in een plugin-repo.

## Controle (frisse hulpthread, per release)
- **Wat:** vóór elke release, voor één plugin en versie: CI groen op de releasecommit; elke B-regel in `docs/bugs.md`
  nog gedekt (test bestaat en draait mee); regels getoetst (alles wat de release raakt + steekproef); schone
  installatie, update vanaf de oudste ondersteunde versie met ingevulde instellingen, en verwijderen, op de oudste en
  nieuwste Pelican-versie uit de matrix. Uitslag in één regel: **KLAAR** of **NIET KLAAR** + lijst, naar de regisseur
  en de release-thread. Daarna sluiten.
- **Nooit:** zelf fiksen.

## Hulpthread (extra controle)
- **Wat:** één controle, per repo één rapport in `plannen/rapporten/<repo>/<datum>-<onderwerp>.md` (id, wat, waarom,
  hoe te testen, prioriteit, keuze Jeroen nodig?), repo-thread en regisseur inlichten, sluiten.
- **Nooit:** bouwen of PR's openen.
