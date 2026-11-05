# Changelog

## Unreleased

- Test first: nobody can join a test server any more. It listens on 127.0.0.1 only, so no player plays on a server
  whose EULA the test accepted. The Test first confirmation says so for eggs with a EULA (such as Paper).
- With Simplify (API 10), a test server's page is locked: you can watch the console, but everything else is greyed out.
  Only Force kill (stops the test, keeps the egg as it is and removes the test server) and Retry (tests again on a
  fresh copy) work, with a line that explains the server is deleted when the egg update is done.

## 2.0.0-beta.5

- One eggs list: Egg Updates now sits in Pelican's own Eggs table, with a status column and Test first, Update, Move
  servers and Put back in each egg's row, instead of a second list above it. Egg Updates' Update replaces Pelican's
  Update button for root admins, so a row never has two and the previous version is always kept. If a Pelican version
  stops Egg Updates from adding them, the Updates block above the list comes back.
- Simplify settings › Add-ons › Egg Updates shows when the eggs were last checked, as a grey line under the select,
  with the number of updates waiting and a link to the Eggs page. The Save button on the settings page sits on the
  right.
- Checks: the panel smoke checks that one new update gives root admins one notification, and a later check of the
  same version none.
- Checks: PHPStan runs without a baseline; the 13 findings of 4-11 are fixed (types only, no change in behaviour).
- Uninstalling Egg Updates keeps its settings, the kept previous versions and the test results, so installing it
  again brings them back.
- Checks: CI updates from 1.1.5 (Hub stable) and from the latest release with settings filled in, uninstalls and
  installs again, and puts the previous release back over the new tables. Dependabot keeps the CI actions current,
  and `tests/run.sh` fails on a secret or an internal address.
- With Simplify 2.0 the settings of Egg Updates are a section of Simplify settings › Add-ons, saved with Simplify's
  Save. Without Simplify the gear on the Eggs page opens the same form.
- Egg Updates moved to Pelican's Eggs page: an Updates block at the top of the list (only when there is something to
  do) with the same buttons, and Check now and Update all in the page's title bar. The menu item is gone. The
  settings are under Simplify settings › Add-ons, or without Simplify behind the gear on the Eggs page. Notifications
  open the Eggs page.
- Test first gives an empty field the value the egg's own servers use, so an egg such as FTB Server installs its
  modpack on the test server instead of stopping at the first start.
- Test first runs the test server on the Docker image the egg's servers use, when the new version still offers it,
  instead of the egg's first image (CurseForge lists Java 8 first).
- A failed test keeps the last 100 lines of the test server's install log or console. With Simplify Explain
  installed, a question button after "Last test failed" explains them; only root admins see it.
- Checks: PHPStan (level 5) runs in CI, and the panel smoke also runs with the real Simplify core (main) instead of a stub.

## 2.0.0-beta.4

- Pelican's Plugins page no longer shows a settings gear for Egg Updates; its settings are on the Egg updates page.

## 2.0.0-beta.1

- First version on the Hub (beta).
- The temporary Copy log from 1.1.5 is removed again.
- Checks: a change to docs or Markdown only skips the panel smoke; tests and pint still run.
- The repository download (the Hub's zip) holds only the plugin: docs, tests, scripts and .github are left out.

## 1.1.5

- Move servers also fills a field that an update made required, such as the User Agent of the current Paper egg. It
  asks for the value once and gives it to every server of the egg that has none. Values a server already has stay.
- An egg whose update link stopped working (for example a 404) is listed as "Can't check", with the reason, after two
  checks in a row. Only eggs that servers use are listed, and the row goes away once the link works again.
- Copy log in the title bar shows a short log for support: versions, the mode, the eggs in the list and the last 50
  events, such as Test first steps and failures with their last lines. Addresses, e-mail addresses, internal links,
  tokens and passwords are left out. Only in this version, to help testing.

## 1.1.4

- The Test first server gets the memory, disk and CPU of the egg's largest server (at least 2 GB, 8 GB and 200%),
  so large games such as Steam games fit. The install may take 30 minutes and the first start 10.
- Test first accepts the EULA on its temporary test server, so eggs that ask for it, such as Paper, can pass the test.
  Real servers still ask for it at their first start.
- Test first now also works for eggs with a required variable without a default, such as the User Agent of the
  current Paper egg, a bot token or an admin password. The test server takes the value the egg's servers use, and
  otherwise a neutral value that the variable's own rules accept. Checked against every public egg.

## 1.1.3

- Test first no longer says that no node has room when every node is fully allocated on paper. It then uses a node
  that already runs the egg, or any node that is not in maintenance.

## 1.1.2

- On a phone the Egg updates list keeps its buttons in view: name, status, servers and date share one cell and the
  buttons show their icon only.

## 1.1.1

- Installing Egg Updates from Admin → Plugins or the Hub now also removes the old copy right away. Those installs run in the
  panel's queue, where 1.1.0 only removed it once the queue worker restarted.

## 1.1.0

- The plugin id is now `simplify-eggupdates` (it was `simplify-egg-updates`), like the other Simplify plugins.
  Installing this version over the old one keeps every setting and egg backup and removes the old copy, so the panel
  never shows Egg Updates twice. The check command is now `php artisan simplify-eggupdates:check`.

## 1.0.3

- Simplify's setup (from Simplify 1.3) now asks when eggs are updated, in its last step "Set up add-ons", so a new
  panel is set up in one go. Older Simplify versions and panels without Simplify are not affected.

## 1.0.2

- Egg Updates now sits in the Simplify group of Pelican's admin menu, together with the other Simplify add-ons,
  in the same order everywhere. This works with or without the Simplify core.

## 1.0.1

- The settings page title now reads "Egg Updates", the same name as in Simplify's add-on list.

## 1.0.0

- First version as its own plugin: checks every egg for a new version every six hours, tells root admins in the notifications, and lists updates on Server → Egg updates.
- Update one egg or all of them; the previous version is kept and can be put back.
- Test first: a temporary test server with the new version must install and start before the egg is updated.
- Move servers: servers still on a startup command or Docker image the update changed get the new one at their next start.
- Settings: ask first, update eggs without servers automatically, test first then update, or off.
