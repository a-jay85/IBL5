#!/usr/bin/env bash
# review-owed.sh — decides whether a PR still owes a structured /pr-review after the
# sticky verdict is posted, and fires bin/pr-review-now <N> when it does.
#
# Reads two facts from the pr-ready sticky comment (<!-- pr-ready-verdict -->):
#   line 1    **Reviewed tree:** <40-hex>              (the tree the fidelity reviewer judged)
#             (line 1 ONLY, by design: this script fails toward firing; the post-plan
#             audit-trail layout is deliberately NOT read here, so a post-plan sticky yields
#             no-tree-line -> owed. skip-review.sh Step 8 is the only widened reader.)
#   any line  REVIEW-COVERAGE: NONE|STALE|CURRENT|UNKNOWN
# and compares the recorded tree to the current HEAD^{tree}. Coverage is current only when
# the marker reads CURRENT AND the recorded tree equals the current tree. Every other state,
# including every parse failure, is REVIEW-OWED: a false "already covered" is the worse
# failure, so ambiguity resolves toward firing.
#
# Usage: bash review-owed.sh <PR-number> [--sticky-file <path>] [--current-tree <40-hex>] [--dry-run]
#   --sticky-file    read the sticky body from a file instead of `gh pr view`.
#   --current-tree   use this SHA instead of `git rev-parse HEAD^{tree}` in cwd.
#   --dry-run        print the decision and the command; do not execute it.
# Env: REVIEW_OWED_PR_REVIEW_NOW  path to the pr-review-now binary
#      (default: <git toplevel of cwd>/bin/pr-review-now).
#
# stdout (verdict last):
#   REVIEWED-TREE: <40-hex | unavailable>
#   CURRENT-TREE: <40-hex | unavailable>
#   COVERAGE: NONE|STALE|CURRENT|UNKNOWN
#   FIRED: <bin> <PR> | DRY-RUN: <bin> <PR> | FIRE-FAILED: <reason>   (owed path only)
#   REVIEW-OWED <reason> | REVIEW-CURRENT <40-hex>
# Exit: 0 always, except 2 on a usage error (nothing is fired on exit 2).
# Never set -e or set -u: either can abort before a verdict is printed.

set -o pipefail

PR=""; STICKY_FILE=""; CUR_OVERRIDE=""; DRY_RUN=false
while [ "$#" -gt 0 ]; do
  case "$1" in
    --sticky-file)  STICKY_FILE="${2:-}"; shift 2 ;;
    --current-tree) CUR_OVERRIDE="${2:-}"; shift 2 ;;
    --dry-run)      DRY_RUN=true; shift ;;
    -*)             echo "review-owed.sh: unknown flag $1" >&2; exit 2 ;;
    *)              PR="$1"; shift ;;
  esac
done
[[ "$PR" =~ ^[0-9]+$ ]] || { echo "review-owed.sh: PR number required (digits only)" >&2; exit 2; }

RECORDED="unavailable"; CUR="unavailable"; COVERAGE="UNKNOWN"

emit_facts() {
  printf 'REVIEWED-TREE: %s\n' "$RECORDED"
  printf 'CURRENT-TREE: %s\n' "$CUR"
  printf 'COVERAGE: %s\n' "$COVERAGE"
}

# owed <reason>: print facts, fire (or dry-run), print the verdict, exit 0.
owed() {
  emit_facts
  local bin="${REVIEW_OWED_PR_REVIEW_NOW:-}" root rc
  if [ -z "$bin" ]; then
    root="$(git rev-parse --show-toplevel 2>/dev/null)"
    bin="${root:-.}/bin/pr-review-now"
  fi
  if [ "$DRY_RUN" = true ]; then
    printf 'DRY-RUN: %s %s\n' "$bin" "$PR"
  elif [ ! -x "$bin" ]; then
    printf 'FIRE-FAILED: missing-pr-review-now %s\n' "$bin"
  else
    # The fired binary's own stdout goes to stderr so this script's stdout stays parseable.
    "$bin" "$PR" 1>&2; rc=$?
    if [ "$rc" -eq 0 ]; then
      printf 'FIRED: %s %s\n' "$bin" "$PR"
    else
      printf 'FIRE-FAILED: rc=%s\n' "$rc"
    fi
  fi
  printf 'REVIEW-OWED %s\n' "$1"
  exit 0
}

# Current tree: override wins; otherwise the cwd worktree. Non-hex collapses to unavailable.
if [ -n "$CUR_OVERRIDE" ]; then
  CUR="$CUR_OVERRIDE"
else
  CUR="$(git rev-parse 'HEAD^{tree}' 2>/dev/null)" || CUR="unavailable"
fi
[[ "$CUR" =~ ^[0-9a-f]{40}$ ]] || CUR="unavailable"

# Sticky body: file seam, else one gh call (same selectors as skip-review.sh Steps 4-7).
BODY=""
if [ -n "$STICKY_FILE" ]; then
  BODY="$(tr -d '\r' < "$STICKY_FILE" 2>/dev/null)" || BODY=""
  [ -n "$BODY" ] || owed "sticky-file-unreadable"
else
  GH_OUT="$(gh pr view "$PR" --json comments 2>/dev/null)" || owed "gh-failed"
  [ -n "$GH_OUT" ] || owed "gh-failed"
  STICKY_COUNT="$(jq '[.comments[]? | select((.body // "") | contains("<!-- pr-ready-verdict -->"))] | length' <<< "$GH_OUT" 2>/dev/null)" || owed "jq-failed"
  [ "$STICKY_COUNT" -gt 1 ] 2>/dev/null && owed "multiple-sticky-comments"
  [ "$STICKY_COUNT" -eq 0 ] 2>/dev/null && owed "no-verdict-comment"
  BODY="$(jq -r '[.comments[]? | select((.body // "") | contains("<!-- pr-ready-verdict -->"))][0].body' <<< "$GH_OUT" 2>/dev/null | tr -d '\r')" || owed "jq-failed"
  [ -n "$BODY" ] || owed "no-verdict-comment"
fi

# Facts. Line 1 only for the tree line (parity with skip-review.sh Step 8); first marker wins.
TREE_LINE="$(head -1 <<< "$BODY" | grep -m1 -oE '^\*\*Reviewed tree:\*\* [0-9a-f]{40}$')" || true
[ -n "$TREE_LINE" ] && RECORDED="${TREE_LINE##* }"
MARK="$(grep -m1 -oE 'REVIEW-COVERAGE: (NONE|STALE|CURRENT|UNKNOWN)' <<< "$BODY")" || true
[ -n "$MARK" ] && COVERAGE="${MARK##* }"

# Decision ladder: any non-CURRENT coverage owes on its own; then tree evidence.
case "$COVERAGE" in
  NONE)    owed "coverage-none" ;;
  STALE)   owed "coverage-stale" ;;
  UNKNOWN) owed "coverage-unknown" ;;
esac
[ "$RECORDED" != "unavailable" ] || owed "no-tree-line"
[ "$CUR" != "unavailable" ]      || owed "current-tree-unavailable"
[ "$RECORDED" = "$CUR" ]         || owed "tree-changed"

emit_facts
printf 'REVIEW-CURRENT %s\n' "$CUR"
exit 0
