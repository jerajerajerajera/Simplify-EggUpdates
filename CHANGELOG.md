# Changelog

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
