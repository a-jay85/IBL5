#!/usr/bin/env bash
# /pr-ready prior-collapse guard. Detects a PREVIOUS run's destructive history
# rewrite (e.g. `git rebase --onto origin/master --root`) whose collapse silently
# discarded this branch's work. Detects and reports only; never rewrites history.
set -uo pipefail

MODE="${1:-}"
PR="${2:-}"
BRANCH="${3:-}"

if [ -z "$MODE" ] || [ -z "$PR" ] || [ -z "$BRANCH" ]; then
  echo "STOP: usage: collapse-guard.sh <check|record> <PR-number> <branch> — were the placeholders rewritten with literals?"
  exit 1
fi

SAFE_BRANCH=$(printf '%s' "$BRANCH" | tr '/' '-')
META="/tmp/pr-ready-collapse-guard-$PR-$SAFE_BRANCH.meta"

blob() {
  git rev-parse --quiet --verify "$1:$2" 2>/dev/null
}

if [ "$MODE" = "record" ]; then
  HEAD_SHA=$(git rev-parse HEAD) || { echo "STOP: cannot resolve HEAD"; exit 1; }
  COUNT=$(git rev-list --count origin/master..HEAD 2>/dev/null) || COUNT=0
  {
    echo "schema=1"
    echo "pr=$PR"
    echo "branch=$BRANCH"
    echo "head=$HEAD_SHA"
    echo "count=$COUNT"
    echo "recorded=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  } > "$META" || { echo "STOP: cannot write $META"; exit 1; }
  echo "COLLAPSE-GUARD: RECORDED $HEAD_SHA -> $META"
  exit 0
fi

if [ "$MODE" != "check" ]; then
  echo "STOP: unknown mode '$MODE' — expected 'check' or 'record'"
  exit 1
fi

# Arm 1 (retroactive): the reflog. Walk newest->oldest, skip into the most recent
# contiguous run of rewrite entries, then print the first NON-rewrite entry below
# it. That commit is the branch tip as it stood before the rewrite episode.
PRIOR=$(git reflog show --no-abbrev --format='%H%x09%gs' "$BRANCH" 2>/dev/null | awk -F'\t' '
  { rw = ($2 ~ /^(rebase|reset:|filter-branch|amend)/)
    if (!seen) { if (rw) seen = 1; else next }
    else if (!rw) { print $1; exit }
  }')

# Arm 2 (precision): the tip this skill recorded at the END of a prior run's
# Phase 2a, before that run rewrote anything.
PRIOR_META=""
if [ -f "$META" ]; then
  PRIOR_META=$(awk -F= '$1 == "head" { print $2; exit }' "$META")
  if [ -n "$PRIOR_META" ] && ! git cat-file -e "${PRIOR_META}^{commit}" 2>/dev/null; then
    PRIOR_META=""
  fi
fi

if [ -z "$PRIOR" ] && [ -z "$PRIOR_META" ]; then
  echo "COLLAPSE-GUARD: NO-PRIOR-REWRITE — no rewrite entry in this branch's reflog and no recorded prior tip"
  exit 0
fi

CANDIDATES=$(printf '%s\n%s\n' "$PRIOR" "$PRIOR_META" | grep -v '^$' | sort -u)

WORST=clear
REPORT=""
STOP_TIP=""
while IFS= read -r CAND; do
  [ -n "$CAND" ] || continue
  git diff --quiet "$CAND" HEAD 2>/dev/null && continue
  BASE=$(git merge-base origin/master "$CAND" 2>/dev/null) || continue
  [ -n "$BASE" ] || continue
  SURV=0
  NLOST=0
  LOST=""
  while IFS= read -r P; do
    [ -n "$P" ] || continue
    if [ "$(blob HEAD "$P")" = "$(blob "$CAND" "$P")" ]; then
      SURV=$((SURV + 1))
    else
      NLOST=$((NLOST + 1))
      LOST="$LOST
    - $P"
    fi
  done <<INNER
$(git diff --name-only "$BASE" "$CAND" 2>/dev/null)
INNER
  [ "$NLOST" -gt 0 ] || continue
  if [ "$SURV" -eq 0 ]; then
    WORST=stop
    STOP_TIP="$CAND"
    REPORT="prior tip $CAND: $NLOST branch-changed path(s), 0 of them still carry the branch's version:$LOST"
    break
  fi
  if [ "$WORST" = clear ]; then
    WORST=warn
    REPORT="prior tip $CAND: $NLOST of $((SURV + NLOST)) branch-changed path(s) no longer carry the branch's version:$LOST"
  fi
done <<OUTER
$CANDIDATES
OUTER

if [ "$WORST" = "stop" ]; then
  echo "STOP: PRIOR-COLLAPSE-DETECTED — a previous run rewrote this branch's history and none of its work survives."
  echo ""
  echo "$REPORT"
  echo ""
  echo "Why this is not a normal squash: a squash preserves the tree byte-for-byte,"
  echo "so every branch-changed path would still match the pre-rewrite tip. Here none"
  echo "do. That is the signature of a forced collapse such as"
  echo "  git rebase --onto origin/master --root"
  echo "followed by skipping the resulting conflicts."
  echo ""
  echo "What to do, in order — nothing is permanently lost yet:"
  echo "  1. Do NOT rebase, commit, push or force-push from this state."
  echo "  2. Park the pre-collapse tip on a rescue branch:"
  echo "       git branch $BRANCH-precollapse $STOP_TIP"
  echo "  3. See exactly what went missing:"
  echo "       git diff $STOP_TIP HEAD"
  echo "  4. Restore the branch's work from the rescue branch, then re-run /pr-ready."
  echo ""
  echo "This guard has changed nothing. It only reports."
  exit 1
fi

if [ "$WORST" = "warn" ]; then
  echo "COLLAPSE-GUARD: WARN — partial loss across a prior rewrite; the branch still carries work, so this is not a collapse."
  echo "$REPORT"
  echo "Confirm each path above against _rebase-and-conflicts.md 2e.2 (whole-file side-take) before continuing."
  exit 0
fi

echo "COLLAPSE-GUARD: NO-COLLAPSE — every branch-changed path still carries the branch's version across the prior rewrite"
exit 0
