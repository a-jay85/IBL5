#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
[ -z "${2:-}" ] && { echo "STOP: arg2 (worktree slug) required — was the site rewritten with the literal?"; exit 1; }
WT="/Users/ajaynicolas/GitHub/IBL5-worktrees/$2"
[ -d "$WT" ] || { echo "STOP: worktree $WT does not exist — wrong slug?"; exit 1; }
cd "$WT"
[ -r bin/lib/pr-armable.sh ] || { echo "STOP: bin/lib/pr-armable.sh not readable from $WT"; exit 1; }
# shellcheck source=/dev/null
source bin/lib/pr-armable.sh

# pr-armable.sh is SOURCED and carries no `set -euo pipefail` by design; its own
# closing comment records that a predicate returning non-zero silently aborts a
# `set -e` caller and truncates this report into something that looks complete.
run_predicate() {
  local label="$1"; shift
  local out rc
  set +e
  out="$("$@" 2>&1)"
  rc=$?
  set -e
  if [ "$rc" -ne 0 ]; then
    printf '%s: PREDICATE-ERROR (rc=%s) %s\n' "$label" "$rc" "$(printf '%s' "$out" | tr '\n' ' ')"
  elif [ -z "$out" ]; then
    printf '%s: (clear)\n' "$label"
  else
    printf '%s: %s\n' "$label" "$(printf '%s' "$out" | tr '\n' ' ')"
  fi
}

BODY="$(gh pr view "$1" --json body --jq .body)"          || { echo "STOP: gh pr view body failed for PR #$1"; exit 1; }
LABELS="$(gh pr view "$1" --json labels --jq '.labels')"  || { echo "STOP: gh pr view labels failed for PR #$1"; exit 1; }
FILES="$(gh pr view "$1" --json files --jq '.files')"     || { echo "STOP: gh pr view files failed for PR #$1"; exit 1; }
HEADREF="$(gh pr view "$1" --json headRefName --jq .headRefName)" || { echo "STOP: gh pr view headRefName failed for PR #$1"; exit 1; }

run_predicate "manual-testing-clearance" pr_manual_testing_clearance "$BODY"
run_predicate "golden-hold"              pr_golden_hold "$FILES"
run_predicate "dep-holds"                pr_dep_holds "$BODY"
# The UI floor reads the REST file list: only it carries a rename's old path.
UI_FILES="$(gh api "repos/{owner}/{repo}/pulls/$1/files?per_page=100" --paginate --slurp | jq -c 'add // []')" \
  || { echo "STOP: gh api pulls/$1/files failed for PR #$1"; exit 1; }
run_predicate "ui-hold"                  pr_ui_hold "$UI_FILES" "$LABELS"
run_predicate "pipeline-authored-hold"   pr_pipeline_authored_hold "$LABELS" "$HEADREF"
run_predicate "unresolved-findings-hold" pr_unresolved_findings_hold "$1"
