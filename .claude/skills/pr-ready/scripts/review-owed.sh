#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }

VERDICT_FILE="/tmp/pr-ready-phase6-verdict-${1}.md"
PLIST="$HOME/Library/LaunchAgents/com.ibl5.pr-review-now-${1}.plist"

# Read coverage marker — grep the whole file, never tail
# Phase 6 requires the verdict word at the end, but grepping whole-file is more robust.
coverage_value="UNKNOWN"
if [ -f "$VERDICT_FILE" ]; then
  coverage_line=$(grep -m1 -oE 'REVIEW-COVERAGE: (NONE|STALE|CURRENT|UNKNOWN)' "$VERDICT_FILE" 2>/dev/null) || coverage_line=""
  if [ -n "$coverage_line" ]; then
    coverage_value="${coverage_line#REVIEW-COVERAGE: }"
  fi
fi

# CURRENT: review is up-to-date, nothing owed
if [ "$coverage_value" = "CURRENT" ]; then
  echo "REVIEW-OWED: NO (coverage current)"
  echo "REVIEW-OWED-COMPLETE"
  exit 0
fi

# Live slot check — plist exists OR review agent is already running.
# The $ anchor is mandatory: without it PR 1901 matches a live slot for PR 19011.
if [ -f "$PLIST" ] || pgrep -f "name com.ibl5.pr-review-now-${1}\$" > /dev/null 2>&1; then
  echo "REVIEW-OWED: SKIP (slot live)"
  echo "REVIEW-OWED-COMPLETE"
  exit 0
fi

# Fire a background review — nohup + & detaches immediately; we never wait on it
cd /Users/ajaynicolas/GitHub/IBL5
nohup /Users/ajaynicolas/GitHub/IBL5/bin/pr-review-now "$1" >/dev/null 2>&1 &

echo "REVIEW-OWED: FIRED (coverage ${coverage_value})"
echo "REVIEW-OWED-COMPLETE"
