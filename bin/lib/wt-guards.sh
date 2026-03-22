# Shared worktree safety guards.
# Source this file from scripts that modify or remove worktrees.
#
# Usage: source "$(dirname "$0")/lib/wt-guards.sh"  (from bin/)
#        source "$REPO_ROOT/bin/lib/wt-guards.sh"    (from elsewhere)

# Check if any process has its working directory inside the worktree.
# Returns 0 (active) or 1 (safe to modify).
is_worktree_in_use() {
    local wt_path="${1%/}"
    # lsof -d cwd lists every process's current working directory.
    # Fast: only checks the cwd file descriptor, not all open files.
    lsof -d cwd 2>/dev/null | grep -q "$wt_path"
}

# Check if a branch has an open PR on GitHub.
# Returns 0 (open PR exists) or 1 (no open PR).
# Sets WTG_PR_NUM to the PR number if found.
WTG_PR_NUM=""
has_open_pr() {
    local branch="$1"
    WTG_PR_NUM=""
    if ! command -v gh &>/dev/null; then
        return 1
    fi
    local pr_state
    pr_state=$(gh pr view "$branch" --json state -q .state 2>/dev/null || true)
    if [ "$pr_state" = "OPEN" ]; then
        WTG_PR_NUM=$(gh pr view "$branch" --json number -q .number 2>/dev/null || true)
        return 0
    fi
    return 1
}
