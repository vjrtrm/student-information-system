#!/usr/bin/env bash
# Guard against duplicate migration number prefixes (we hit a 014 clash once).
# Deterministic, no model tokens; prints a warning the model will see.
set -euo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || echo .)"
dir="database/migrations"
[ -d "$dir" ] || exit 0
dups=$(ls "$dir" 2>/dev/null | sed -E 's/^([0-9]+)_.*/\1/' | sort | uniq -d)
if [ -n "$dups" ]; then
  echo "⚠️  DUPLICATE migration number prefix(es): $dups — rename so each NNN_ is unique (migrations run in sorted order)."
fi
