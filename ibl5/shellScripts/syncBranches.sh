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
#   - Worktree branches synced from within the worktree (skipped if in active use)
#   - After sync, rebases all worktree branches onto origin/master

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
source "$REPO_ROOT/bin/lib/wt-guards.sh"
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

# Get worktree path for a branch name
get_worktree_path() {
    git worktree list --porcelain | awk -v branch="refs/heads/$1" '
        /^worktree / { path = substr($0, 10) }
        $0 == "branch " branch { print path; exit }
    '
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

    # Sync branches checked out in a worktree from within the worktree
    if [[ "$branch" != "$CURRENT" ]] && is_in_worktree "$branch"; then
        wt_path=$(get_worktree_path "$branch")
        if [ -n "$wt_path" ]; then
            # Skip if worktree is actively in use
            if is_worktree_in_use "$wt_path"; then
                echo "  SKIP    $branch (worktree is in active use)"
                ((SKIPPED++))
                continue
            fi
            # Skip if worktree has uncommitted changes
            if ! git -C "$wt_path" diff --quiet HEAD 2>/dev/null || \
               ! git -C "$wt_path" diff --cached --quiet HEAD 2>/dev/null; then
                echo "  SKIP    $branch (worktree has uncommitted changes)"
                ((SKIPPED++))
                continue
            fi
            git -C "$wt_path" reset --hard "$remote_ref" -q
            echo "  reset   $branch (worktree)"
            ((SYNCED++))
        else
            echo "  SKIP    $branch (worktree path not found)"
            ((SKIPPED++))
        fi
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

# Rebase worktree branches onto updated origin/master
if [ -x "$REPO_ROOT/bin/wt-rebase" ]; then
    echo ""
    "$REPO_ROOT/bin/wt-rebase"
fi
