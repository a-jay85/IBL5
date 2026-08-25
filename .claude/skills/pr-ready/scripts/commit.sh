#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
[ -z "${2:-}" ] && { echo "STOP: arg2 (worktree slug) required — was the site rewritten with the literal?"; exit 1; }
WT="/Users/ajaynicolas/GitHub/IBL5-worktrees/$2"
[ -d "$WT" ] || { echo "STOP: worktree $WT does not exist — wrong slug?"; exit 1; }
cd "$WT"
git add -A
if git diff --cached --quiet; then
  echo "STOP: nothing staged — Phase 6.5 made no edits, so there is nothing to commit"
  exit 1
fi
git commit -m "chore: pr-ready remediation for PR #$1"
