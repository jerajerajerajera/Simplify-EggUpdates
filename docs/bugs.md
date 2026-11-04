# Bugregister Simplify Egg Updates

> Elke gefikste bug staat hier, met de test die hem bewaakt (WR-11). Een terugkerende bug is een regressie: zoek
> eerst uit waarom de test hem niet ving. Een rij weghalen mag niet, de test weghalen ook niet. Nieuwe nummers geeft de
> repo-thread zelf met het prefix van deze repo (WR-8); Startvulling 2-11 (V35): de
> bugs die twee keer of vaker terugkwamen, uit CHANGELOG en projectnotities. "Nog geen test" is een open punt voor
> de repo-thread.

| Nr | Datum | Wat zag de gebruiker | Oorzaak | Fix (versie) | Regressietest | Teruggekomen | Regel |
|---|---|---|---|---|---|---|---|
| B15 | 1.1.3, 1.1.4 | Test first faalt op het echte paneel: "geen node met ruimte" bij volle allocatie (1.1.3); eggs die de EULA vragen of een verplichte variabele zonder default hebben (1.1.4) | Testserver nabootsing week af van het echte paneel en de echte Paper-egg | 1.1.3, 1.1.4 | `tests/panel/smoke.php` (volle nodes zonder overallocatie, EULA alleen op de testserver, verplichte variabelen zonder default) | 1× | P-31 |
| B4 | 2-11 | Move servers liet servers zonder waarde voor een veld dat de update verplicht maakte | Nieuw verplicht veld zonder default werd niet ingevuld | 1.1.5 (V24) | `tests/panel/smoke.php` "Move servers asks for a field the update made required…" (rood 8c3cc7a, groen 61f8b26) | 0× | P-28 |
| B6 | 2-11 | Een egg met een kapotte update-link bleef stil | Geen status voor een link die niet meer werkt | 1.1.5 (V24) | `tests/panel/smoke.php` "an egg whose update link stopped working…" (rood 8c3cc7a, groen 61f8b26) | 0× | P-32 |
| EU-B2 | 4-11 | Test first van FTB Server faalde: "the test server stopped while starting" | Een lege standaardwaarde (modpack-ID) ging voor de waarde van de eigen servers, dus de testserver installeerde geen modpack | 2.0.0-beta.5 | `tests/egg-updates.php` "an empty default takes the value the servers use" (rood 8fdc791, groen in deze PR) en smoke-stap Test first (MODPACK_ID) | 0× | P-31 |
| EU-B1 | 3-11 | Alleen Egg Updates had "View" en een tandwiel in Pelicans Plugins-lijst | De plugin-klasse implementeerde Pelicans `HasPluginSettings`; instellingen horen alleen op de Egg updates-pagina in Simplify | 2.0.0-beta.4 | `tests/egg-updates.php` "no Pelican settings gear" (rood f7c46ef, groen in deze PR) en smoke-stap "No settings gear on Pelican's Plugins page" | 0× | P-1 |
| B7 | 2-11 | Tijdelijke "Copy log"-knop (V31) moet weer weg | Tijdelijke hulp voor support | weg vóór 2.0.0-beta.1 | `tests/panel/smoke.php` "Copy log is gone again after 1.1.5 (B7)" | 0× | V31 |

## Patronen

Bugs met dezelfde oorzaak, en de regel of check die het hele patroon voorkomt.
- **Pagina scrolt** (B9: kern 6×, Toolkit, Server Maker, Explain, Hosting): P-7. Elke instellingspagina van een add-on
  heeft `s-fit` en een smoke-stap die dat controleert; elk kernscherm een audit `scroll-*`.
- **Updatepad** (B11, kern 1.4.4 en 1.5.1): P-28. Upgradetest met echte 0.7.0-instellingen in `checks.yml`.
- **Testpaneel lijkt niet op het echte paneel** (B15 Egg Updates 1.1.3/1.1.4, kern-audit): P-31. Smoke en audit bootsen
  het echte paneel na (volle nodes, echte eggs, 20+ servers, nep-plugin).
