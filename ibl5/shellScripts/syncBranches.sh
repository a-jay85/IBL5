#!/bin/bash
set -e

# Sync all local tracking branches with their origin counterparts.
#
# For each branch:
#   - No dirty working tree → hard reset to origin
#   - Dirty working tree (current branch only) → stash, reset, pop
#   - Skips branches with no origin counterpart

CURRENT=$(git branch --show-current)

echo "Fetching origin..."
git fetch origin -q --prune

SYNCED=0
SKIPPED=0

# Branches checked out in any worktree can't be force-updated
WORKTREE_BRANCHES=$(git worktree list --porcelain | grep '^branch ' | sed 's|^branch refs/heads/||')

is_in_worktree() {
    echo "$WORKTREE_BRANCHES" | grep -qx "$1"
}

# Check if current working tree is dirty
is_dirty() {
    ! git diff --quiet HEAD 2>/dev/null || ! git diff --cached --quiet HEAD 2>/dev/null
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
        if is_dirty; then
            echo "  SKIP    $branch (dirty working tree — commit or stash first)"
            ((SKIPPED++))
        else
            git reset --hard "$remote_ref" -q
            echo "  reset   $branch"
            ((SYNCED++))
        fi
    else
        git branch -f "$branch" "$remote_ref"
        echo "  reset   $branch"
        ((SYNCED++))
    fi
done < <(git for-each-ref --format='%(refname:short)' refs/heads/)

echo ""
echo "Done: $SYNCED reset, $SKIPPED skipped."
