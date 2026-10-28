# Changelog

## 1.0.1

- The settings page title now reads "Egg Updates", the same name as in Simplify's add-on list.

## 1.0.0

- First version as its own plugin: checks every egg for a new version every six hours, tells root admins in the notifications, and lists updates on Server → Egg updates.
- Update one egg or all of them; the previous version is kept and can be put back.
- Test first: a temporary test server with the new version must install and start before the egg is updated.
- Move servers: servers still on a startup command or Docker image the update changed get the new one at their next start.
- Settings: ask first, update eggs without servers automatically, test first then update, or off.
