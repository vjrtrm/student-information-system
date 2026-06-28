#!/usr/bin/env bash
# Cheap, deterministic context so the model doesn't have to re-scan the repo.
set -euo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || echo .)"
echo "## SIS session context"
echo "Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'no-git')"
echo "Last commit: $(git log -1 --oneline 2>/dev/null || echo 'none')"
echo "Spec files present:"
ls docs/module-*/SIS_M*_*.md 2>/dev/null | sed 's/^/  - /' || echo "  (none yet)"
DONE=$(ls docs/module-*/SIS_M*_Tasks.md 2>/dev/null | grep -c Tasks || echo 0)
echo "Modules complete: $DONE / 12  (M1 Auth · M2 Master Data · M3 Onboarding · M4 Enrolment · M5 Student Form done)"
echo "Next: M6 Submission & Edit Approval"
echo "Reminder: follow Requirements -> Design -> Tasks, approval-gated (see CLAUDE.md). Commit after each module."
