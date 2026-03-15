#!/bin/bash
set -e

fail() { echo "FAILED: $1"; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BRANCH=$(git branch --show-current)

# 1. Guard: not master/production
[[ "$BRANCH" == "master" || "$BRANCH" == "production" ]] && fail "On $BRANCH -- not a PR branch"

# 2. Guard: has open PR
gh pr view --json state -q '.state' 2>/dev/null | grep -q "OPEN" || fail "No open PR for $BRANCH"

# 3. Stash if dirty
STASHED=0
if ! git diff --quiet HEAD 2>/dev/null || ! git diff --cached --quiet HEAD 2>/dev/null; then
  git stash push -m "pr-merge-wip" -q
  STASHED=1
  echo "Stashed uncommitted changes."
fi

# 4. Fetch
git fetch origin -q

# 5. Rebase if not at tip of master
if ! git merge-base --is-ancestor origin/master HEAD; then
  git rebase origin/master -q || fail "Rebase conflicts -- resolve manually"
  echo "Rebased onto master."
fi

# 6. Push all local commits (including any rebase result)
git push --force-with-lease -q || fail "Push to origin failed -- local commits may not be on remote"

# 7. Verify local and remote branch tips match before merging
LOCAL_SHA=$(git rev-parse HEAD)
REMOTE_SHA=$(git rev-parse "origin/$BRANCH")
[[ "$LOCAL_SHA" == "$REMOTE_SHA" ]] || fail "Local ($LOCAL_SHA) and remote ($REMOTE_SHA) out of sync"

# 8. Squash merge + cleanup
gh pr merge --squash --delete-branch || fail "gh pr merge"

# 8a. Wait for GitHub to delete the origin branch
sleep 5

# 9. mergeAndPush
bash "$SCRIPT_DIR/mergeMasterToProdAndPush.sh"

# 10. Restore stash (we're now on master after mergeMasterToProdAndPush)
if [ "$STASHED" -eq 1 ]; then
  echo "NOTE: Stashed changes from '$BRANCH' will be restored onto master (branch was deleted)."
  git stash pop -q && echo "Restored stashed changes." || echo "WARNING: stash pop had conflicts -- resolve manually."
fi

# 11. Fetch all remotes and prune tracking branches
git fetch --all --prune -q

echo "Done."
