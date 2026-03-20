#!/bin/bash
set -e

# Sync all local tracking branches with their origin counterparts.
#
# Safety: stashes any uncommitted changes before syncing, then restores
# them afterward so local work is never lost.
#
# For each branch:
#   - Hard reset to origin (non-current branches via branch -f)
#   - Current branch via reset --hard
#   - Skips branches with no origin counterpart or checked out in a worktree

CURRENT=$(git branch --show-current)

# Stash uncommitted changes upfront so they survive the reset
STASHED=0
if ! git diff --quiet HEAD 2>/dev/null || ! git diff --cached --quiet HEAD 2>/dev/null; then
    echo "Stashing uncommitted changes..."
    git stash push -q -m "syncBranches: auto-stash before sync"
    STASHED=1
fi

# Restore stash on exit (normal or error) so changes are never stranded
restore_stash() {
    if [[ "$STASHED" -eq 1 ]]; then
        echo "Restoring stashed changes..."
        git stash pop -q
    fi
}
trap restore_stash EXIT

echo "Fetching origin..."
git fetch origin -q --prune

SYNCED=0
SKIPPED=0

# Branches checked out in any worktree can't be force-updated
WORKTREE_BRANCHES=$(git worktree list --porcelain | grep '^branch ' | sed 's|^branch refs/heads/||')

is_in_worktree() {
    echo "$WORKTREE_BRANCHES" | grep -qx "$1"
}

# Iterate local branches that have an origin tracking branch
while IFS= read -r branch; do
    remote_ref="origin/$branch"

    # Skip if origin branch doesn't exist (deleted upstream)
    if ! git rev-parse --verify "$remote_ref" &>/dev/null; then
        continue
    fi

    local_sha=$(git rev-parse "$branch")
    remote_sha=$(git rev-parse "$remote_ref")

    # Already up to date
    if [[ "$local_sha" == "$remote_sha" ]]; then
        continue
    fi

    # Skip branches checked out in a worktree
    if [[ "$branch" != "$CURRENT" ]] && is_in_worktree "$branch"; then
        echo "  SKIP    $branch (checked out in a worktree)"
        ((SKIPPED++))
        continue
    fi

    if [[ "$branch" == "$CURRENT" ]]; then
        git reset --hard "$remote_ref" -q
        echo "  reset   $branch"
        ((SYNCED++))
    else
        git branch -f "$branch" "$remote_ref"
        echo "  reset   $branch"
        ((SYNCED++))
    fi
done < <(git for-each-ref --format='%(refname:short)' refs/heads/)

echo ""
echo "Done: $SYNCED reset, $SKIPPED skipped."
