# Shared worktree safety guards.
# Source this file from scripts that modify or remove worktrees.
#
# Usage: source "$(dirname "$0")/lib/wt-guards.sh"  (from bin/)
#        source "$REPO_ROOT/bin/lib/wt-guards.sh"    (from elsewhere)

# Returns 0 when a detached post-plan run is loaded in launchd for this worktree.
# Its label is com.ibl5.postplan-now-<SAFE_SLUG>-<YYYYmmdd-HHMMSS>-<pid>, SAFE_SLUG being
# the branch name with every run of chars outside [A-Za-z0-9._-] flattened to "-". The
# harness `run` wrapper cd's to the harness dir, so only the caffeinate wrapper's cwd shows
# the worktree: the label is the reliable signal. Both the branch-derived and the
# directory-name slug are tried. The glob is anchored on the timestamp, so slug `foo`
# never matches a job for `foo-bar`. LAUNCHCTL_CMD is a test override.
has_live_postplan_run() {
    local wt_path="${1%/}" branch snap label slug
    local -a slugs=()
    slugs[0]=$(basename "$wt_path")
    branch=$(git -C "$wt_path" rev-parse --abbrev-ref HEAD 2>/dev/null || true)
    if [ -n "$branch" ] && [ "$branch" != HEAD ]; then
        slugs[1]=$(printf '%s' "$branch" | tr -cs 'A-Za-z0-9._-' '-')
    fi
    snap=$("${LAUNCHCTL_CMD:-launchctl}" list 2>/dev/null || true)
    [ -n "$snap" ] || return 1
    while IFS=$'\t' read -r _ _ label; do
        for slug in "${slugs[@]}"; do
            case "$label" in
                com.ibl5.postplan-now-"$slug"-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9]-[0-9]*)
                    return 0 ;;
            esac
        done
    done <<< "$snap"
    return 1
}

# Kill infrastructure processes (browser-sync, CSS watcher) for a worktree.
# These are background watchers started by wt-up that should not block cleanup.
kill_infra_processes() {
    local wt_path="${1%/}"

    # A live post-plan run owns this worktree: touch nothing (is_worktree_in_use also
    # reports it, so callers skip the worktree).
    if has_live_postplan_run "$wt_path"; then
        return 0
    fi

    # Kill via PID files first (fast, reliable)
    for pid_file in "$wt_path/.bs-sync.pid" "$wt_path/.css-watch.pid"; do
        if [ -f "$pid_file" ]; then
            local pid
            pid=$(cat "$pid_file")
            kill -9 "$pid" 2>/dev/null || true
            rm -f "$pid_file"
        fi
    done

    # Kill any remaining node processes with CWD in this worktree. The path match
    # is exact (the worktree or a dir under it), so cleaning `foo` never touches
    # a process in a sibling `foo-bar` worktree.
    local pids
    pids=$(lsof -d cwd 2>/dev/null \
        | awk -v p="$wt_path" '$1 == "node" && ($NF == p || index($NF, p "/") == 1) { print $2 }' \
        | sort -u || true)
    if [ -n "$pids" ]; then
        echo "$pids" | xargs kill -9 2>/dev/null || true
    fi

    # No kill by command line. A `pgrep -f <worktree path>` sweep used to live here
    # for host CSS watchers, which wt-up no longer starts (Tailwind --watch runs in
    # a container). It SIGKILLed any live job whose argv named the worktree, e.g.
    # post-plan-now's `bash -lc "cd <worktree> ..."`. That ran before the in-use
    # check below could see the job, so a post-merge `bin/cleanup --all` killed
    # in-flight post-plan runs (PRs #2466, #2606) and then removed their worktrees.
}

# Check if any process has its working directory inside the worktree.
# Returns 0 (active) or 1 (safe to modify).
is_worktree_in_use() {
    local wt_path="${1%/}"
    if has_live_postplan_run "$wt_path"; then
        return 0
    fi
    # lsof -d cwd lists every process's current working directory.
    # Fast: only checks the cwd file descriptor, not all open files.
    # -Fn prints one `n<path>` line per cwd (safe for paths with spaces). Match
    # literally: the path itself or anything under it (path + "/"). A bare
    # substring match let sibling `feat-bar` block cleanup of `feat`. The path
    # goes via ENVIRON, not -v, so awk does not interpret backslash escapes.
    # awk reads all input (no early exit) so lsof never takes SIGPIPE under pipefail.
    lsof -d cwd -Fn 2>/dev/null | WTG_P="$wt_path" awk '
        BEGIN { p = ENVIRON["WTG_P"]; pl = length(p) }
        substr($0, 1, 1) == "n" {
            n = substr($0, 2)
            if (n == p || substr(n, 1, pl + 1) == p "/") found = 1
        }
        END { exit !found }'
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

# Resolve the worktree path for a branch name.
# Prints the path to stdout; empty output means no worktree found.
get_worktree_path() {
    local branch="$1"
    git worktree list --porcelain | awk -v branch="refs/heads/$branch" '
        /^worktree / { path = substr($0, 10) }
        $0 == "branch " branch { print path; exit }
    '
}

# Check if a directory has uncommitted or staged changes.
# Returns 0 (has changes) or 1 (clean / not a valid git repo).
has_uncommitted_changes() {
    local dir="${1:-.}"
    local rc
    git -C "$dir" diff --quiet 2>/dev/null
    rc=$?
    # Exit 1 = has diffs; exit 128 = broken/missing gitdir (not "dirty")
    if [ "$rc" -eq 1 ]; then return 0; fi
    git -C "$dir" diff --cached --quiet 2>/dev/null
    rc=$?
    if [ "$rc" -eq 1 ]; then return 0; fi
    return 1
}

# Check if a directory has untracked (but not gitignored) files.
# Returns 0 (has untracked work) or 1 (none / not a valid git repo).
# has_uncommitted_changes is git-diff-based and NEVER sees untracked files;
# callers that gate destructive actions on "is there work here?" must check
# BOTH. --exclude-standard is load-bearing: without it gitignored build
# artifacts/logs would trip the guard and falsely flag nearly every worktree.
has_untracked_files() {
    local dir="${1:-.}"
    local out
    # A broken/missing gitdir yields empty output (treated as "no untracked"),
    # mirroring has_uncommitted_changes's exit-128 tolerance.
    out=$(git -C "$dir" ls-files --others --exclude-standard 2>/dev/null)
    [ -n "$out" ]
}
