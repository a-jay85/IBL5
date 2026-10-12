#!/bin/bash
# bin/lib/headless-elapsed-hook.sh — PostToolUse hook that appends an elapsed-
# clock line after every tool call in automouse impl runs.
#
# Opt-in via env vars set by bin/automouse/run on the impl invocation:
#   IBL5_BUDGET_START_EPOCH  — unix epoch when the impl started
#   IBL5_BUDGET_SECS         — total wall-clock budget (REMAINING_SECS)
#
# When either var is absent, empty, non-numeric, or budget <= 0: exits 0 with
# no output, keeping this a complete no-op in interactive sessions.
#
# Emits one JSON object consumed by the Claude Code harness as additionalContext.
# Always exits 0 (fail-open: if jq is missing, exit 0 silently).

set -u

# Discard stdin (the harness pipes tool result JSON here; we don't use it).
cat > /dev/null

# Validate env vars.
START="${IBL5_BUDGET_START_EPOCH:-}"
BUDGET="${IBL5_BUDGET_SECS:-}"

# Must be set, non-empty, all-digits, and budget > 0.
if [ -z "$START" ] || [ -z "$BUDGET" ]; then
    exit 0
fi
case "$START" in ''|*[!0-9]*) exit 0 ;; esac
case "$BUDGET" in ''|*[!0-9]*) exit 0 ;; esac
if [ "$BUDGET" -le 0 ]; then
    exit 0
fi

# Compute elapsed (integer seconds).
NOW=$(date +%s)
ELAPSED=$(( NOW - START ))
if [ "$ELAPSED" -lt 0 ]; then
    ELAPSED=0
fi

# Determine context string.
# Past 90% threshold: elapsed*10 >= budget*9 (integer math, no floats needed).
if [ $(( ELAPSED * 10 )) -ge $(( BUDGET * 9 )) ]; then
    CONTEXT="elapsed ${ELAPSED}s / ${BUDGET}s. You are past 90% of your budget: stop implementing and write the handoff file now."
else
    CONTEXT="elapsed ${ELAPSED}s / ${BUDGET}s"
fi

# Emit JSON via jq (fail-open if jq unavailable).
if ! command -v jq > /dev/null 2>&1; then
    exit 0
fi

jq -n \
    --arg ctx "$CONTEXT" \
    '{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":$ctx}}'

exit 0
