#!/usr/bin/env bash
set -euo pipefail
# Phase 4.3 / Phase 6.5 step 5, collapsed into one call.
#
# The guards are the point: this push must fail CLOSED. A bare
# `git push --force-with-lease` on a worktree branch with no upstream publishes
# NOTHING and its exit status is push.default-dependent, so "no recognised error"
# must NEVER be read as "pushed". Three defenses, all required:
#   1. EXPLICIT refspec        -> origin "$BRANCH:refs/heads/$BRANCH"
#   2. EXPLICIT lease ref      -> --force-with-lease="$BRANCH:$LEASE", derived below from
#                                 refs/remotes/origin/<branch>, never a fresh ls-remote
#   3. VERIFY against origin   -> re-read the ref after the push; rc is never trusted
# Every exit path prints one verdict word: PUSHED, STALE LEASE, HOOK REJECTED, PUSH FAILED, or STOP.
#   rc 0 PUSHED · 2 STALE LEASE · 3 HOOK REJECTED (recoverable — see _phase4-push-and-ci.md step 3) · 1 PUSH FAILED / STOP
# Silence is not success.
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
PR="$1"
ZERO="0000000000000000000000000000000000000000"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 \
  || { echo "STOP: not inside a git work tree — push.sh must run from the PR worktree"; exit 1; }

BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null) \
  || { echo "STOP: could not resolve the current branch"; exit 1; }
[ -n "$BRANCH" ] || { echo "STOP: could not resolve the current branch"; exit 1; }
[ "$BRANCH" = "HEAD" ] && { echo "STOP: detached HEAD — refusing to push"; exit 1; }
case "$BRANCH" in
  master|main) echo "STOP: refusing to push '$BRANCH' — /pr-ready never pushes to $BRANCH"; exit 1 ;;
esac

HEAD_SHA=$(git rev-parse HEAD 2>/dev/null) \
  || { echo "STOP: could not resolve HEAD"; exit 1; }

# --- was 3a: read origin (fully qualified: no tag ambiguity), then derive the lease ---
BEFORE=$(git ls-remote origin "refs/heads/$BRANCH" 2>/dev/null | awk '{print $1}') \
  || { echo "PUSH FAILED — could not read refs/heads/$BRANCH from origin (pre-push lease read)"; exit 1; }

# The lease is our remote-tracking ref -- what WE last knew origin held -- NOT the fresh read
# above. A fresh read already reflects a concurrent third-party push, so it always matches,
# which silently degrades --force-with-lease into --force. $BEFORE is kept only to report
# `moved`. (Deliberate deviation from plan 5.1 property 2; see the PR body.)
LEASE=$(git rev-parse --verify --quiet "refs/remotes/origin/$BRANCH" 2>/dev/null || true)
if [ -z "$LEASE" ]; then
  if [ -n "$BEFORE" ]; then
    # origin has the branch but we have never fetched it: we hold no knowledge to lease
    # against, so any lease we could form would be a wildcard. Fail closed.
    echo "STALE LEASE — origin holds refs/heads/$BRANCH ($BEFORE) but this worktree has no"
    echo "refs/remotes/origin/$BRANCH, so there is no lease to hold. Nothing was pushed."
    echo "Run: git fetch origin $BRANCH — inspect what landed, then re-run."
    exit 2
  fi
  LEASE="$ZERO"   # neither side knows the branch -> all-zeros lease CREATES it, not a wildcard
fi

# --- was 3b: push. rc is captured, never allowed to abort before verification ---
OUT=$(git push -u --force-with-lease="$BRANCH:$LEASE" origin "$BRANCH:refs/heads/$BRANCH" 2>&1) && RC=0 || RC=$?
printf '%s\n' "$OUT"

# --- was 3c: verify against origin. AUTHORITATIVE. Always runs. ---
AFTER=$(git ls-remote origin "refs/heads/$BRANCH" 2>/dev/null | awk '{print $1}') \
  || { echo "PUSH FAILED — could not read refs/heads/$BRANCH from origin (post-push verify); remote state UNKNOWN"; exit 1; }
[ -n "$AFTER" ] || AFTER="(absent)"

if [ "$AFTER" != "$BEFORE" ]; then MOVED=yes; else MOVED=no; fi

case "$OUT" in
  *"stale info"*)
    echo "STALE LEASE — origin/$BRANCH moved under us (lease=${BEFORE:-none} now=$AFTER); nothing was clobbered."
    echo "moved: $MOVED"
    echo "Re-read the remote and re-run push.sh. Do NOT drop the lease."
    exit 2
    ;;
  *"pre-push-adr-hook: branch is not rebased onto origin/master"*)
    # Client-side pre-push hook refused the update. Recoverable ONLY when the remote is
    # provably unchanged: nothing was pushed, nothing was clobbered, and origin/$BRANCH is
    # known -- the STALE LEASE neighbourhood, not the PUSH FAILED one. If the remote moved,
    # it is not in the state we reasoned about, so fall through to the catch-all below.
    if [ "$MOVED" = no ]; then
      echo "HOOK REJECTED — pre-push-adr-hook refused this push: the branch is not rebased onto origin/master."
      echo "origin/$BRANCH is unchanged at $AFTER; nothing was pushed and nothing was clobbered."
      echo "Recover with ONE bounded attempt: git fetch origin master && git rebase origin/master, then re-run push.sh."
      echo "If that rebase reports a conflict: git rebase --abort — and treat this as terminal."
      exit 3
    fi
    ;;
esac

if [ "$AFTER" = "$HEAD_SHA" ]; then
  echo "PUSHED PR #$PR $HEAD_SHA (moved: $MOVED, push rc: $RC)"
  exit 0
fi

echo "PUSH FAILED — origin refs/heads/$BRANCH is $AFTER, expected $HEAD_SHA (moved: $MOVED, push rc: $RC)."
echo "Do NOT read this as pushed."
exit 1
