#!/usr/bin/env bash
# Regenerates a tiny index of spec docs after any Write/Edit. Deterministic; no model tokens.
set -euo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || echo .)"
{
  echo "# Docs index (generated)"
  echo
  for f in SIS_M*_*.md; do
    [ -e "$f" ] || continue
    title=$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //')
    echo "- \`$f\` — ${title:-untitled}"
  done
} > DOCS_INDEX.md 2>/dev/null || true
