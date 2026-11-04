#!/usr/bin/env bash
# No secret or internal address in the repository (WR-12): tokens in the shapes of Pelican, OpenAI-style keys, AWS,
# GitHub and Cloudflare, a bearer token written out, private IP ranges and hosts under deckzwabbers.nl. GitHub's push
# protection only knows the token shapes; this also catches the addresses. Example data uses documentation addresses
# (192.0.2.x, 203.0.113.x) and example.com instead.
set -euo pipefail
cd "$(dirname "$0")/.."
patterns=(
    'pnl_[A-Za-z0-9]{16,}'
    'sk-[A-Za-z0-9_-]{20,}'
    'AKIA[0-9A-Z]{16}'
    'gh[pousr]_[A-Za-z0-9]{30,}'
    'github_pat_[A-Za-z0-9_]{20,}'
    'Bearer [A-Za-z0-9._~+/-]{20,}'
    'CLOUDFLARE_API_TOKEN=[^[:space:]$"]'
    '\b10\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\b'
    '\b192\.168\.[0-9]{1,3}\.[0-9]{1,3}\b'
    '\b172\.(1[6-9]|2[0-9]|3[01])\.[0-9]{1,3}\.[0-9]{1,3}\b'
    '[a-z0-9-]+\.deckzwabbers\.nl'
)
found=$(git ls-files -z | grep -zv '^tests/secrets.sh$' | xargs -0 grep -nIE "$(IFS='|'; echo "${patterns[*]}")" 2>/dev/null || true)
if [ -n "$found" ]; then
    echo "FAIL a secret or internal address in the repository:"
    printf '%s\n' "$found" | cut -c1-200 | sed 's/^/  /'
    exit 1
fi
echo "PASS no secrets or internal addresses in the repository"
