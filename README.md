<img src=".github/logo.svg" alt="" width="64">

# Simplify Egg Updates

A [Pelican](https://pelican.dev) plugin that checks every egg for a new version, tells root admins and updates eggs
safely. The previous version of an egg is always kept, so it can be put back.

- Every six hours each egg with an update URL is compared with the version published there, the same way Pelican
  compares them. The result is kept until the egg is updated, so nothing is missed.
- New versions show up in Pelican's notifications for root admins and on **Server → Egg updates**.
- Update one egg or all of them. Running servers keep running and get the new egg at their next start or reinstall.
- Put back returns the egg to the version it had before the last update. Automatic updates then leave it alone.
- **Test first**: before an egg that servers use is updated, a temporary test server is made with the new version on
  a free port between 45000 and 45999. Only when it installs and starts is the egg updated. The test server, its port
  and the copy of the egg are removed either way. Do it by hand per egg, or let it happen for every new version.
- **Move servers**: servers keep their own startup command and Docker image when an egg is updated. Move servers gives
  servers that still use a command or image the update changed or removed the new one, from their next start. Commands
  and images set by hand stay.
- Optionally, eggs that no server uses are updated automatically.
- Pelican's own Update button on its Eggs page follows the same result.

It works on its own. With [Simplify](https://github.com/jerajerajerajera/Simplify) installed it also shows up in
Simplify's add-on list.

## Set up

1. Install the plugin from the [Pelican Hub](https://hub.pelican.dev). Updates come from the Hub only.
2. Open **Server → Egg updates** in the admin area. Under **Settings** choose what happens with new versions:
   *Ask first* (default), *Update eggs without servers automatically*, *Test first, then update* or *Off*. The same settings are behind the
   gear on Pelican's Plugins page.

Pelican's scheduler must run (`php artisan schedule:run` every minute), as in Pelican's own installation guide.

Test first needs a node with room for 2 GB RAM, 8 GB disk and 200% CPU. The test server belongs to the first root
admin and is named "Egg update test: <egg>". With Simplify installed it stays out of Simplify's lists.

## What it sends where

Only the update URL of each egg, as set in the egg itself (for Pelican's eggs that is GitHub, where they are
published). The plugin reads the egg definition there, the same request Pelican makes for its own update check.
Nothing else leaves the panel.

## What it stores

Four tables: `simplify_egg_updates` (what was found per egg and how its last test ended),
`simplify_egg_update_backups` (the last five definitions of each updated egg), `simplify_egg_update_tests` (tests
that wait or run) and `simplify_egg_update_settings`. They are removed with the eggs they belong to.

## Development

- `bash tests/run.sh` runs the checks that need no panel, including `tests/hub-check.py`.
- `pint` formats the code (Laravel preset).
- `tests/panel/smoke.php` runs inside a real panel; CI does this on Pelican beta38 and rc.2.
- `python3 scripts/build-package.py` builds the zip for the Hub.

## License

Source available, see [LICENSE.md](LICENSE.md). Copyright (c) 2026 Jeroen (jerajerajerajera).
