# Overdracht-template

> Vóór een sessie stopt of wordt vervangen: invullen en opslaan als
> `/mnt/project-files/plannen/plugins/<repo>/overdracht-<JJJJ-MM-DD>.md` (naast `stand.md`, niet in Git), en de
> regisseur melden. De opvolger leest hem als eerste (WR-7).

```
## Overdracht <rol> – <datum> <tijd>

**Rol:** <rol>          **Branch:** <branch> @ <hash> (alles gepusht: ja/nee)
**Live/uitgebracht:** <versie> (<datum>)        **Onder Unreleased:** <aantal wijzigingen>

**Open punten (met nummer):**
- <nr> – <wat> – <stand> – <wie is aan zet>

**Wachtrij:**
- bezig: <nr>
- volgende: <nr>, <nr>
- blokkade: <wat> – nodig: <wie/wat> (of: geen)

**Open PR's:** #<n> <titel> (CI groen/rood)

**Checks:** <commando's> – <uitslag x/y>

**Valkuilen:** <wat een opvolger moet weten en niet uit de code haalt>
```
