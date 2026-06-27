#!/usr/bin/env bash
# Commit (and push if a remote is configured & authenticated) after a module is completed.
# Usage: bash scripts/commit-module.sh "M1 Authentication: specs complete"
set -euo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || { echo 'Not a git repo'; exit 1; })"

MSG="${1:-}"
if [ -z "$MSG" ]; then echo "Usage: $0 \"commit message\""; exit 1; fi

git add -A
if git diff --cached --quiet; then
  echo "Nothing to commit."; exit 0
fi
git commit -m "$MSG"
echo "Committed: $MSG"

if git remote get-url origin >/dev/null 2>&1; then
  if git push origin HEAD 2>/dev/null; then
    echo "Pushed to origin."
  else
    echo "Commit saved locally but PUSH FAILED (no GitHub auth)."
    echo "Authenticate once, then re-run, e.g.:"
    echo "  git push https://<TOKEN>@github.com/vjrtrm/student-information-system HEAD:main"
  fi
else
  echo "No 'origin' remote configured."
fi
