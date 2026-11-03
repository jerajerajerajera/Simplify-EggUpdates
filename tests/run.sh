#!/usr/bin/env bash
# Runs every check that needs no panel: php -l, the unit tests, the translations and the Pelican Hub checks.
set -euo pipefail
cd "$(dirname "$0")/.."
find src tests resources lang database -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
php tests/egg-updates.php
php tests/lang.php
python3 tests/hub-check.py
# What a download of the repository holds (the Hub's zip): every file the plugin runs on, nothing of the tests or docs.
archive="$(git archive HEAD 2>/dev/null | tar -t 2>/dev/null || true)"
if [ -n "$archive" ]; then
    for need in plugin.json README.md LICENSE.md src/ resources/ lang/ database/; do
        grep -qx "$need" <<<"$archive" || grep -q "^$need" <<<"$archive" || { echo "FAIL the repository download misses $need"; exit 1; }
    done
    if grep -qE '^(docs|tests|scripts|\.github)/' <<<"$archive"; then echo "FAIL the repository download still holds docs, tests, scripts or .github"; exit 1; fi
    echo "PASS the repository download holds the plugin without docs, tests, scripts and .github"
fi
