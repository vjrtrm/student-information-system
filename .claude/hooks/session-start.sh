#!/usr/bin/env bash
# Cheap, deterministic context so the model doesn't have to re-scan the repo.
set -euo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || echo .)"
echo "## SIS session context"
echo "Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'no-git')"
echo "Last commit: $(git log -1 --oneline 2>/dev/null || echo 'none')"
echo "Spec files present:"
ls SIS_M*_*.md 2>/dev/null | sed 's/^/  - /' || echo "  (none yet)"
echo "Reminder: follow Requirements -> Design -> Tasks, approval-gated (see CLAUDE.md). Commit after each module."
