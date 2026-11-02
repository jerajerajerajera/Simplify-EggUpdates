#!/usr/bin/env bash
# The rules, decisions and start briefs belong to the core repo; this repo keeps an identical copy.
# Fails when a copy differs from the core's main branch, so no copy is forgotten.
set -uo pipefail
core=$(mktemp -d)
if ! git clone --quiet --depth 1 https://github.com/jerajerajerajera/Simplify.git "$core"; then
    echo "FAIL could not fetch the core repo"
    exit 1
fi
fail=0
for f in docs/regels.md docs/besluiten.md docs/prompts/startbrieven.md docs/prompts/overdracht-template.md; do
    if cmp -s "$core/$f" "$f"; then
        echo "ok   $f"
    else
        echo "FAIL $f differs from the core repo (main)"
        fail=1
    fi
done
exit $fail
