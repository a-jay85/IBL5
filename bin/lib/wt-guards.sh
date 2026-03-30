# Shared worktree safety guards.
# Source this file from scripts that modify or remove worktrees.
#
# Usage: source "$(dirname "$0")/lib/wt-guards.sh"  (from bin/)
#        source "$REPO_ROOT/bin/lib/wt-guards.sh"    (from elsewhere)

# Kill infrastructure processes (browser-sync, CSS watcher) for a worktree.
# These are background watchers started by wt-up that should not block cleanup.
kill_infra_processes() {
    local wt_path="${1%/}"

    # Kill via PID files first (fast, reliable)
    for pid_file in "$wt_path/.bs-sync.pid" "$wt_path/.css-watch.pid"; do
        if [ -f "$pid_file" ]; then
            local pid
            pid=$(cat "$pid_file")
            kill -9 "$pid" 2>/dev/null || true
            rm -f "$pid_file"
        fi
    done

    # Kill any remaining node processes with CWD in this worktree.
    local pids
    pids=$(lsof -d cwd 2>/dev/null \
        | grep "$wt_path" \
        | awk '/^node / { print $2 }' \
        | sort -u || true)
    if [ -n "$pids" ]; then
        echo "$pids" | xargs kill -9 2>/dev/null || true
    fi

    # Kill processes whose command line references this worktree path.
    # CSS watchers started by wt-up use absolute paths in -i/-o flags but their
    # CWD is wherever wt-up was called from, so lsof -d cwd misses them.
    # After rm -rf deletes the worktree, the PID files are gone too, so the
    # PID-file check above also misses them — they become unkillable zombies
    # that recreate the output directory on every rebuild cycle.
    pids=$(pgrep -f "$wt_path" 2>/dev/null || true)
    if [ -n "$pids" ]; then
        echo "$pids" | xargs kill -9 2>/dev/null || true
    fi
}

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
