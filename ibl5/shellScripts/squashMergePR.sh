#!/bin/bash
set -e

fail() { echo "FAILED: $1"; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BRANCH=$(git branch --show-current)

# 1. Guard: not master/production
[[ "$BRANCH" == "master" || "$BRANCH" == "production" ]] && fail "On $BRANCH — not a PR branch"

# 2. Guard: has open PR
gh pr view --json state -q '.state' 2>/dev/null | grep -q "OPEN" || fail "No open PR for $BRANCH"

# 3. Stash if dirty
STASHED=0
if ! git diff --quiet HEAD 2>/dev/null || ! git diff --cached --quiet HEAD 2>/dev/null; then
  git stash push -m "pr-merge-wip" -q
  STASHED=1
  echo "Stashed uncommitted changes."
fi

# 4. Push unpushed commits
git push -q 2>/dev/null || true

# 5. Fetch
git fetch origin -q

# 6. Rebase if not at tip of master
if ! git merge-base --is-ancestor origin/master HEAD; then
  git rebase origin/master -q || fail "Rebase conflicts — resolve manually"
  git push --force-with-lease -q || fail "Force push after rebase"
  echo "Rebased onto master."
fi

# 7. Squash merge + cleanup
gh pr merge --squash --delete-branch || fail "gh pr merge"

# 8. mergeAndPush
bash "$SCRIPT_DIR/mergeMasterToProdAndPush.sh"

# 9. Restore stash
if [ "$STASHED" -eq 1 ]; then
  git stash pop -q && echo "Restored stashed changes." || echo "WARNING: stash pop had conflicts — resolve manually."
fi

echo "Done."
