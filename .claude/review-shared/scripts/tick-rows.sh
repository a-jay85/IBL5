#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
[ -z "${2:-}" ] && { echo "STOP: arg2 (worktree slug) required — was the site rewritten with the literal?"; exit 1; }
WT="/Users/ajaynicolas/GitHub/IBL5-worktrees/$2"
[ -d "$WT" ] || { echo "STOP: worktree $WT does not exist — wrong slug?"; exit 1; }
cd "$WT"

TMPFILE="/tmp/pr-ready-manual-rows-${1}.txt"
BODY_FILE="/tmp/pr-ready-tick-body-${1}.txt"

# Collect PASS ids from the manual-rows output file
PASS_IDS=()
if [ -f "$TMPFILE" ]; then
  while IFS= read -r line; do
    if printf '%s' "$line" | grep -qE '^ROW .+ PASS$'; then
      row_id=$(printf '%s' "$line" | sed 's/^ROW \(.*\) PASS$/\1/')
      PASS_IDS+=("$row_id")
    fi
  done < "$TMPFILE"
fi

count="${#PASS_IDS[@]}"

# Zero PASS ids: no gh pr edit at all
if [ "$count" -eq 0 ]; then
  echo "TICKED: 0"
  echo "TICK-COMPLETE"
  exit 0
fi

# Re-fetch body fresh — never reuse manual-rows.sh's copy;
# Phase 6.5 step 4 may have already run its own gh pr edit on this body.
BODY=$(gh pr view "$1" --json body --jq '.body') || { echo "STOP: gh pr view failed for PR #$1"; exit 1; }
printf '%s\n' "$BODY" > "$BODY_FILE"

# Flip - [ ] **<id>** to - [x] **<id>** for each PASS id.
# Use | as the sed delimiter so any / in an id cannot break the pattern.
for row_id in "${PASS_IDS[@]}"; do
  sed -i '' "s|- \[ \] \*\*${row_id}\*\*|- [x] **${row_id}**|g" "$BODY_FILE"
done

# Write back — || true guards so TICK-COMPLETE always prints even on gh failure
gh pr edit "$1" --body-file "$BODY_FILE" || true

echo "TICKED: ${count}"
echo "TICK-COMPLETE"
