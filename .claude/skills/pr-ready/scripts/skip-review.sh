#!/usr/bin/env bash
# skip-review.sh — fail-closed skip predicate for /pr-ready Phase 6.
# Compares the `**Reviewed tree:**` SHA recorded in the PR's sticky comment to the current
# HEAD^{tree}. On a match (and no conflicts) writes carry-forward files and prints
# SKIP-REVIEW <sha>, authorising Phase 6 to omit the Opus fidelity spawn.
# Every failure path prints RUN-REVIEW <reason> and exits 0 — the safe direction.
# Never set -e or set -u: either can abort before a verdict is printed.
#
# Usage: bash skip-review.sh [--delta] <PR-number> "<conflicts-flag>"
#   --delta  Delta mode: emit DELTA-STATUS/DELTA-BASE/DELTA_* flags for Phase 4B.
#            On success: CURRENT-TREE, DELTA-STATUS: delta, DELTA-BASE, DELTA-REASON: none,
#            DELTA_FILE, and ten DELTA_* flag lines. On DELTA-STATUS: full, only
#            CURRENT-TREE, DELTA-STATUS: full, and DELTA-REASON are emitted.
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

DELTA_MODE=false
if [ "${1:-}" = "--delta" ]; then DELTA_MODE=true; shift; fi

CUR="unavailable"
PR="${1:-}"

delta_run() {
  printf 'CURRENT-TREE: %s\n' "${CUR:-unavailable}"
  printf 'DELTA-STATUS: full\n'
  printf 'DELTA-REASON: %s\n' "$1"
  exit 0
}
run() {
  if [ "$DELTA_MODE" = true ]; then delta_run "$1"; fi
  printf 'CURRENT-TREE: %s\n' "${CUR:-unavailable}"
  printf 'RUN-REVIEW %s\n' "$1"
  exit 0
}

# Step 0: clear stale carry-forward unconditionally — keyed by PR number so a previous
# run's files never pollute a later RUN-REVIEW path that does not write new ones.
if [ "$DELTA_MODE" = false ]; then
  rm -f "/tmp/pr-ready-digest-lines-${PR}.txt" "/tmp/pr-ready-prior-verdict-${PR}.md"
fi

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

# Delta base resolution ladder (runs only when --delta, after Step 9's conflicts guard).
if [ "$DELTA_MODE" = true ]; then
  [ -z "$RECORDED_TREE" ] && delta_run "no-recorded-tree"

  DELTA_BASE=""
  DIFF_AWK='
    /^diff --git.*(migrations\/|composer\.lock|package-lock\.json|bun\.lock|__snapshots__\/|\.snap$)/ {skip=1; next}
    /^diff --git/ {skip=0}
    skip==0 {print}
  '

  # Rung 1: local object probe (normal path — rebase detaches but does not delete objects).
  if git cat-file -e "${RECORDED_TREE}^{tree}" 2>/dev/null; then
    DELTA_BASE="$RECORDED_TREE"
  else
    # Rung 2: force-push timeline scan — newest-first, stop at first tree match.
    _GQL="query { repository(owner: \"a-jay85\", name: \"IBL5\") { pullRequest(number: ${PR}) { timelineItems(last: 10, itemTypes: [HEAD_REF_FORCE_PUSHED_EVENT]) { nodes { ... on HeadRefForcePushedEvent { beforeCommit { oid } } } } } } }"
    TL_OIDS="$(gh api graphql -f "query=${_GQL}" \
        --jq '.data.repository.pullRequest.timelineItems.nodes | reverse | .[].beforeCommit | select(. != null) | .oid' \
        2>/dev/null)" || delta_run "timeline-unavailable"
    [ -n "$TL_OIDS" ] || delta_run "timeline-unavailable"

    while IFS= read -r OID; do
      [ -z "$OID" ] && continue
      [ "$OID" = "null" ] && continue
      COMMIT_TREE="$(gh api "repos/a-jay85/IBL5/git/commits/${OID}" --jq .tree.sha 2>/dev/null)" \
        || continue
      [ -z "$COMMIT_TREE" ] && continue
      [ "$COMMIT_TREE" = "null" ] && continue
      if [ "$COMMIT_TREE" = "$RECORDED_TREE" ]; then
        git fetch --no-tags origin "$OID" 2>/dev/null || delta_run "fetch-failed"
        if git cat-file -e "${RECORDED_TREE}^{tree}" 2>/dev/null; then
          DELTA_BASE="$RECORDED_TREE"
        fi
        break
      fi
    done <<< "$TL_OIDS"

    # Rung 3: full diff fallback.
    [ -z "$DELTA_BASE" ] && delta_run "timeline-no-match"
  fi

  # Phase 3: delta file set, whole-file emission, size guard, DELTA_* flags.

  # File set: intersection of tree diff and PR diff (excludes rebase-only master changes).
  DELTA_FILES_TREE="$(git diff --name-only "$DELTA_BASE" HEAD 2>/dev/null | sort -u || true)"
  DELTA_FILES_PR="$(git diff --name-only origin/master...HEAD 2>/dev/null | sort -u || true)"
  if [ -z "$DELTA_FILES_TREE" ] || [ -z "$DELTA_FILES_PR" ]; then
    DELTA_FILES=""
  else
    DELTA_FILES="$(comm -12 \
      <(printf '%s\n' "$DELTA_FILES_TREE") \
      <(printf '%s\n' "$DELTA_FILES_PR"))"
  fi

  # Whole-file context diff artifact (filtered, same DIFF_AWK as Phase 3).
  DELTA_DIFF_FILE="/tmp/post-plan-delta-diff-${PR}"
  if [ -z "$DELTA_FILES" ]; then
    : > "$DELTA_DIFF_FILE"
  else
    _DF_ARRAY=()
    while IFS= read -r _f; do
      _DF_ARRAY+=("$_f")
    done <<< "$DELTA_FILES"
    git diff --unified=100000 "$DELTA_BASE" HEAD -- "${_DF_ARRAY[@]}" 2>/dev/null \
      | awk "$DIFF_AWK" > "$DELTA_DIFF_FILE" \
      || delta_run "delta-diff-write-failed"
  fi

  # Size guard: whole-file context can make the delta artifact larger than the hunk-only full diff.
  if [ "$(wc -c < "$DELTA_DIFF_FILE")" -gt 102400 ]; then
    delta_run "delta-too-large"
  fi

  # DELTA_* flags — mirroring Phase 3 grep ladder shapes on the delta file set / artifact.
  _D_FILES="${DELTA_FILES:-}"
  D_COUNT_PHP=$(printf '%s\n' "$_D_FILES" | grep -cE '\.php$' || true)
  D_COUNT_MD=$(printf '%s\n' "$_D_FILES" | grep -cE '\.md$' || true)
  D_COUNT_LOCK=$(printf '%s\n' "$_D_FILES" | grep -cE '(composer|package|bun)\.lock$' || true)
  D_COUNT_SNAPSHOT=$(printf '%s\n' "$_D_FILES" | grep -cE '__snapshots__/|\.snap$' || true)
  D_COUNT_NON_CODE=$(( D_COUNT_MD + D_COUNT_LOCK + D_COUNT_SNAPSHOT ))
  D_COUNT_TOTAL=$(printf '%s\n' "$_D_FILES" | grep -c . || true)
  D_COUNT_IBL5=$(printf '%s\n' "$_D_FILES" | grep -cE '^ibl5/' || true)
  D_GO_TOUCHED=$(printf '%s\n' "$_D_FILES" | grep -cE '^engine/' || true)
  D_COUNT_E2E=$(printf '%s\n' "$_D_FILES" | grep -cE '^ibl5/tests/e2e/.*\.ts$' || true)
  D_COUNT_SHELL=$(printf '%s\n' "$_D_FILES" | grep -E '(^|/)bin/|\.sh$' | grep -cvE '\.(php|md|json|py|ts|tsx|css|sql|ya?ml|lock|txt|neon)$' || true)
  D_COUNT_WORKFLOW=$(printf '%s\n' "$_D_FILES" | grep -cE '^\.github/workflows/.*\.ya?ml$' || true)

  DELTA_HAS_PHP=$([ "$D_COUNT_PHP" -gt 0 ] && echo true || echo false)
  DELTA_NON_CODE_ONLY=$([ "$D_COUNT_TOTAL" -gt 0 ] && [ "$D_COUNT_NON_CODE" -eq "$D_COUNT_TOTAL" ] && echo true || echo false)
  _D_GO_BOOL=$([ "$D_GO_TOUCHED" -gt 0 ] && echo true || echo false)
  DELTA_ENGINE_ONLY=$([ "$_D_GO_BOOL" = true ] && [ "$D_COUNT_PHP" -eq 0 ] && [ "$D_COUNT_IBL5" -eq 0 ] && echo true || echo false)
  DELTA_HAS_E2E_SPECS=$([ "$D_COUNT_E2E" -gt 0 ] && echo true || echo false)
  DELTA_HAS_SHELL=$([ "$D_COUNT_SHELL" -gt 0 ] && echo true || echo false)
  DELTA_HAS_WORKFLOW=$([ "$D_COUNT_WORKFLOW" -gt 0 ] && echo true || echo false)
  # No COUNT_SKILL_PROSE — only the boolean is computed (mirrors Phase 3 comment).
  # Herestring avoids printf-pipe-grep-q SIGPIPE under pipefail (shell-pipefail-grep.md).
  DELTA_HAS_SKILL_PROSE=$(grep -qE '^\.claude/.*\.md$' <<< "$_D_FILES" && echo true || echo false)

  # HAS_MODIFIED: files modified (not added) within the delta set.
  D_MODIFIED_COUNT=$(comm -12 \
    <(git diff --diff-filter=M --name-only "$DELTA_BASE" HEAD 2>/dev/null | sort -u || true) \
    <(printf '%s\n' "$_D_FILES" | sort -u || true) \
    | grep -c . || true)
  DELTA_HAS_MODIFIED=$([ "$D_MODIFIED_COUNT" -gt 0 ] && echo true || echo false)

  # HAS_COMMENTS_IN_DIFF: code-comment detection on added lines in the delta artifact.
  D_COMMENT_COUNT=$(grep -cE '^\+[[:space:]]*(//|#|/\*|\*)' "$DELTA_DIFF_FILE" || true)
  DELTA_HAS_COMMENTS_IN_DIFF=$([ "$D_COMMENT_COUNT" -gt 0 ] && echo true || echo false)

  # LINES_PHP_CHANGED: PHP added-line count from the delta artifact.
  DELTA_LINES_PHP_CHANGED=$(awk '
    /^diff --git/ { p=$NF; sub(/^b\//,"",p); in_php=(p~/\.php$/) ? 1 : 0; next }
    in_php && /^\+[^+]/ { n++ }
    END { print n+0 }
  ' "$DELTA_DIFF_FILE")

  # Full delta-mode stdout contract.
  printf 'CURRENT-TREE: %s\n' "${CUR:-unavailable}"
  printf 'DELTA-STATUS: delta\n'
  printf 'DELTA-BASE: %s\n' "$DELTA_BASE"
  printf 'DELTA-REASON: none\n'
  printf 'DELTA_FILE=%s\n' "$DELTA_DIFF_FILE"
  printf 'DELTA_HAS_PHP=%s\n' "$DELTA_HAS_PHP"
  printf 'DELTA_LINES_PHP_CHANGED=%s\n' "$DELTA_LINES_PHP_CHANGED"
  printf 'DELTA_NON_CODE_ONLY=%s\n' "$DELTA_NON_CODE_ONLY"
  printf 'DELTA_ENGINE_ONLY=%s\n' "$DELTA_ENGINE_ONLY"
  printf 'DELTA_HAS_MODIFIED=%s\n' "$DELTA_HAS_MODIFIED"
  printf 'DELTA_HAS_COMMENTS_IN_DIFF=%s\n' "$DELTA_HAS_COMMENTS_IN_DIFF"
  printf 'DELTA_HAS_E2E_SPECS=%s\n' "$DELTA_HAS_E2E_SPECS"
  printf 'DELTA_HAS_SHELL=%s\n' "$DELTA_HAS_SHELL"
  printf 'DELTA_HAS_WORKFLOW=%s\n' "$DELTA_HAS_WORKFLOW"
  printf 'DELTA_HAS_SKILL_PROSE=%s\n' "$DELTA_HAS_SKILL_PROSE"
  exit 0
fi

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
  DIGEST_ARRAY[n]="$dline"
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
