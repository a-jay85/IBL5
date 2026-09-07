#!/usr/bin/env bash
# skip-review.sh — fail-closed skip predicate for /pr-ready Phase 6.
# Compares the `**Reviewed tree:**` SHA recorded in the PR's sticky comment to the current
# HEAD^{tree}. On a match (and no conflicts) writes carry-forward files and prints
# SKIP-REVIEW <sha>, authorising Phase 6 to omit the Opus fidelity spawn.
# Every failure path prints RUN-REVIEW <reason> and exits 0 — the safe direction.
# Never set -e or set -u: either can abort before a verdict is printed.
#
# Usage: bash skip-review.sh <PR-number> "<conflicts-flag>"
#   $1  PR number (digits only).
#   $2  conflicts flag — empty string = no conflicts; any non-empty value = conflicts
#       resolved during rebase. Literal substituted by orchestrator — no $(...) allowed.
#
# Output (exactly two lines on stdout, verdict last):
#   CURRENT-TREE: <40-hex | "unavailable">
#   SKIP-REVIEW <40-hex>     — safe to omit Opus fidelity spawn
#   RUN-REVIEW <reason>      — proceed with full Phase 6 review
#
# Exit status: always 0.

set -o pipefail

LABELS=(
  '**What changed:**'
  '**Why:**'
  '**Watch:**'
  '**Touches:**'
  '**Machine-authored fixes:**'
)

CUR="unavailable"
PR="${1:-}"

run() { printf 'CURRENT-TREE: %s\n' "${CUR:-unavailable}"; printf 'RUN-REVIEW %s\n' "$1"; exit 0; }

# Step 0: clear stale carry-forward unconditionally — keyed by PR number so a previous
# run's files never pollute a later RUN-REVIEW path that does not write new ones.
rm -f "/tmp/pr-ready-digest-lines-${PR}.txt" "/tmp/pr-ready-prior-verdict-${PR}.md"

# Step 1: require both args — absent $2 (orchestrator bug) vs. empty $2 (no conflicts) differ.
[ "$#" -lt 2 ] && run "missing-args"

# Step 2: PR number must be all digits.
[[ "$PR" =~ ^[0-9]+$ ]] || run "bad-pr-number"

# Step 3: resolve current tree SHA from cwd worktree.
CUR_RAW="$(git rev-parse 'HEAD^{tree}' 2>/dev/null)" || run "not-a-git-worktree"
[[ "$CUR_RAW" =~ ^[0-9a-f]{40}$ ]] || run "not-a-git-worktree"
CUR="$CUR_RAW"

# Step 4: one API call for comments + branch name.
GH_OUT="$(gh pr view "$PR" --json comments,headRefName 2>/dev/null)" || run "gh-failed"
[ -n "$GH_OUT" ] || run "gh-failed"

# Step 5: branch guard — cwd must be the worktree for this PR.
LOCAL_BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null)" || run "not-a-git-worktree"
REMOTE_BRANCH="$(printf '%s' "$GH_OUT" | jq -r '.headRefName' 2>/dev/null)" || run "jq-failed"
[ "$REMOTE_BRANCH" = "$LOCAL_BRANCH" ] || run "wrong-worktree"

# Step 6: sticky comment count.
STICKY_COUNT="$(printf '%s' "$GH_OUT" | jq '[.comments[]? | select((.body // "") | contains("<!-- pr-ready-verdict -->"))] | length' 2>/dev/null)" || run "jq-failed"
[ "$STICKY_COUNT" -gt 1 ] 2>/dev/null && run "multiple-sticky-comments"
[ "$STICKY_COUNT" -eq 0 ] 2>/dev/null && run "no-verdict-comment"

# Step 7: extract first sticky comment body, strip CR.
BODY="$(printf '%s' "$GH_OUT" | jq -r '[.comments[]? | select((.body // "") | contains("<!-- pr-ready-verdict -->"))][0].body' 2>/dev/null | tr -d '\r')" || run "jq-failed"
[ -n "$BODY" ] || run "no-verdict-comment"

# Step 8: parse line 1 only for the tree contract line; anchored to avoid false positives.
TREE_LINE="$(printf '%s\n' "$BODY" | head -1 | grep -m1 -oE '^\*\*Reviewed tree:\*\* [0-9a-f]{40}$')" || true
[ -n "$TREE_LINE" ] || run "no-tree-line"
RECORDED_TREE="${TREE_LINE##* }"
[[ "$RECORDED_TREE" =~ ^[0-9a-f]{40}$ ]] || run "no-tree-line"

# Step 9: conflicts outrank a matching tree.
[ -n "${2}" ] && run "conflicts-resolved"

# Step 10: tree comparison.
[ "$RECORDED_TREE" != "$CUR" ] && run "tree-changed"

# Step 11: carry-forward extraction — precondition of SKIP.
# Mirrors bin/pr-cycle's _digest_labels: start after `### Merge digest`, stop at next heading.
DIGEST_BLOCK="$(printf '%s\n' "$BODY" | awk '
  { sub(/[[:space:]]*$/, "") }
  /^### Merge digest[[:space:]]*$/ { in_digest=1; next }
  in_digest && /^#+ /              { exit }
  in_digest && $0 != ""            { print }
' 2>/dev/null)" || run "prior-digest-unparseable"

DIGEST_ARRAY=()
n=0
while IFS= read -r dline; do
  DIGEST_ARRAY[$n]="$dline"
  n=$((n + 1))
done <<< "$DIGEST_BLOCK"

# Reject empty DIGEST_BLOCK — <<< appends a newline so n=1 for empty input.
if [ "$n" -eq 1 ] && [ -z "${DIGEST_ARRAY[0]:-}" ]; then
  run "prior-digest-unparseable"
fi

[ "$n" -eq 5 ] || run "prior-digest-unparseable"

i=0
while [ "$i" -lt 5 ]; do
  label="${LABELS[$i]}"
  if [ "${DIGEST_ARRAY[$i]:0:${#label}}" != "$label" ]; then
    run "prior-digest-unparseable"
  fi
  i=$((i + 1))
done

printf '%s\n' "${DIGEST_ARRAY[@]}" > "/tmp/pr-ready-digest-lines-${PR}.txt" || run "carry-forward-write-failed"
printf '%s\n' "$BODY" > "/tmp/pr-ready-prior-verdict-${PR}.md" || run "carry-forward-write-failed"

# Step 12: all checks passed.
printf 'CURRENT-TREE: %s\n' "$CUR"
printf 'SKIP-REVIEW %s\n' "$CUR"
exit 0
