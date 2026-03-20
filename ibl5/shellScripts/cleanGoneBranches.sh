#!/bin/bash
set -e

# Clean up local branches whose remote tracking branch has been deleted ([gone]).
# Also tears down associated worktrees and Docker environments if present.

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

echo "Fetching origin..."
git fetch origin -q --prune

DELETED=0
SKIPPED=0

# Protected branches — never delete these
is_protected() {
    case "$1" in
        master|production) return 0 ;;
        *) return 1 ;;
    esac
}

# Collect branches checked out in worktrees
WORKTREE_BRANCHES=$(git worktree list --porcelain | grep '^branch ' | sed 's|^branch refs/heads/||')

has_worktree() {
    echo "$WORKTREE_BRANCHES" | grep -qx "$1"
}

# Parse gone branches from git branch -v
# The output may have a leading + (checked out in worktree) or * (current branch)
while IFS= read -r line; do
    # Skip empty lines
    [[ -z "$line" ]] && continue

    # Extract branch name: strip leading whitespace, *, +
    branch=$(echo "$line" | sed 's/^[[:space:]]*[*+]*[[:space:]]*//' | awk '{print $1}')

    [[ -z "$branch" ]] && continue

    if is_protected "$branch"; then
        echo "  SKIP    $branch (protected)"
        ((SKIPPED++))
        continue
    fi

    # Tear down worktree if one exists for this branch
    if has_worktree "$branch"; then
        echo "  WORKTREE $branch — tearing down..."
        if [ -x "$REPO_ROOT/bin/wt-down" ]; then
            "$REPO_ROOT/bin/wt-down" "$branch" --volumes --force 2>&1 | sed 's/^/    /'
        fi
        if [ -x "$REPO_ROOT/bin/wt-remove" ]; then
            "$REPO_ROOT/bin/wt-remove" "$branch" 2>&1 | sed 's/^/    /'
        fi
    fi

    git branch -D "$branch" >/dev/null 2>&1
    echo "  DELETE  $branch"
    ((DELETED++))

done < <(git branch -v | grep '\[gone\]')

echo ""
if [[ $DELETED -eq 0 && $SKIPPED -eq 0 ]]; then
    echo "No [gone] branches to clean up."
else
    echo "Done: $DELETED deleted, $SKIPPED skipped."
fi
