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
    #
    # On Linux, Node 24+ names its main thread "MainThread", and lsof prints that
    # as "MainThrea". Other programs can use that thread name too, so that name
    # only counts when /proc says the binary really is node.
    local pids
    pids=$(lsof -d cwd 2>/dev/null \
        | awk -v p="$wt_path" '($1 == "node" || $1 ~ /^MainThre/) && ($NF == p || index($NF, p "/") == 1) { print $1, $2 }' \
        | while read -r name pid; do
            exe="$(readlink "/proc/$pid/exe" 2>/dev/null)"
            if [ "$name" = "node" ] || [ "${exe##*/}" = "node" ]; then
                echo "$pid"
            fi
        done \
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

# wtg_etime_secs <etime> — print the seconds in a `ps -o etime=` value
# ([[dd-]hh:]mm:ss, same shape on BSD and procps ps). Returns 1 on any other
# shape, so a caller that cannot read an age kills nothing.
wtg_etime_secs() {
    local e="${1//[[:space:]]/}"
    local re='^(([0-9]+)-)?(([0-9]+):)?([0-9]+):([0-9]+)$'
    [[ $e =~ $re ]] || return 1
    local d="${BASH_REMATCH[2]:-0}" h="${BASH_REMATCH[4]:-0}"
    local m="${BASH_REMATCH[5]}" s="${BASH_REMATCH[6]}"
    echo $(( 10#$d * 86400 + 10#$h * 3600 + 10#$m * 60 + 10#$s ))
}

# wtg_pid_alive <pid> — 0 when ps shows the pid and it is not a zombie.
# A failed ps reads as gone, so no SIGKILL follows (fail closed).
wtg_pid_alive() {
    local st
    st=$(ps -o stat= -p "$1" 2>/dev/null) || return 1
    st="${st//[[:space:]]/}"
    case "$st" in ''|Z*) return 1 ;; esac
    return 0
}

# kill_leaked_orphans <wt_path> <dry_run:true|false> — kill processes that pin a
# worktree the CALLER HAS ALREADY PROVEN MERGED (bin/cleanup's merged path only).
# A process is a leaked orphan only when ALL hold: its cwd is the worktree or
# under it; its parent pid is 1 (WTG_ORPHAN_PARENT_PID, a test seam); its
# command is not claude, not node running claude, and not a terminal
# multiplexer; and it has run longer than WT_GC_ORPHAN_MIN_AGE seconds
# (default 86400). WT_GC_KILL_ORPHANS="0" disables the kill; unset or empty
# means on. A live post-plan run on the worktree means touch nothing. Every
# probe failure (lsof, ps, an unreadable age) skips the process. SIGTERM
# first, then SIGKILL for survivors after 3 seconds. Prints one
# `orphan-kill:` line per action on stdout (bin/wt-gc-tick appends cleanup's
# stdout to WT_GC_LOG). Always returns 0.
kill_leaked_orphans() {
    local wt_path="${1%/}" dry_run="${2:-true}"
    [ "${WT_GC_KILL_ORPHANS-1}" = "0" ] && return 0
    local min_age="${WT_GC_ORPHAN_MIN_AGE:-86400}"
    case "$min_age" in
        ''|*[!0-9]*) echo "  orphan-kill: WT_GC_ORPHAN_MIN_AGE='$min_age' is not a whole number of seconds; killing nothing"; return 0 ;;
    esac
    local parent="${WTG_ORPHAN_PARENT_PID:-1}"
    case "$parent" in *[!0-9]*) return 0 ;; esac
    has_live_postplan_run "$wt_path" && return 0
    local phys
    phys=$(cd "$wt_path" 2>/dev/null && pwd -P) || phys="$wt_path"
    # -Fpn prints `p<pid>` then `n<cwd>` per process. awk reads all input (no
    # early exit) so lsof never takes SIGPIPE under pipefail. A failed lsof
    # yields no candidates, so nothing is killed.
    local pids
    pids=$(lsof -d cwd -Fpn 2>/dev/null | WTG_P="$wt_path" WTG_Q="$phys" awk '
        BEGIN { p = ENVIRON["WTG_P"]; q = ENVIRON["WTG_Q"] }
        substr($0, 1, 1) == "p" { pid = substr($0, 2) }
        substr($0, 1, 1) == "n" {
            n = substr($0, 2); hit = 0
            if (n == p || index(n, p "/") == 1) hit = 1
            if (q != p && (n == q || index(n, q "/") == 1)) hit = 1
            if (hit) print pid
        }' | sort -u || true)
    local pid info ppid etime comm base age args started killed=""
    for pid in $pids; do
        [ "$pid" = "$$" ] && continue
        info=$(ps -o ppid=,etime=,comm= -p "$pid" 2>/dev/null) || continue
        read -r ppid etime comm <<< "$info"
        [ "$ppid" = "$parent" ] || continue
        age=$(wtg_etime_secs "$etime") || continue
        [ "$age" -gt "$min_age" ] || continue
        base="${comm##*/}"
        case "$base" in
            [Cc]laude|[Cc]laude[-.]*) continue ;;
        esac
        case "$base" in
            tmux|tmux:*|screen|zellij|mosh-server) continue ;;
        esac
        if [ "$base" = node ] || [ "${base#MainThre}" != "$base" ]; then
            args=$(ps -o args= -p "$pid" 2>/dev/null) || continue
            case "$args" in *[Cc]laude*) continue ;; esac
        fi
        started=$(ps -o lstart= -p "$pid" 2>/dev/null || true)
        if [ "$dry_run" = true ]; then
            echo "  orphan-kill: would SIGTERM pid=$pid etime=$etime started=$started cmd=$comm"
            continue
        fi
        kill -TERM "$pid" 2>/dev/null || true
        echo "  orphan-kill: SIGTERM pid=$pid etime=$etime started=$started cmd=$comm"
        killed="$killed $pid"
    done
    [ -n "$killed" ] || return 0
    local i alive=""
    for i in 1 2 3; do
        alive=""
        for pid in $killed; do wtg_pid_alive "$pid" && alive="$alive $pid"; done
        [ -n "$alive" ] || return 0
        sleep 1
    done
    for pid in $alive; do
        # PID-reuse guard: SIGKILL only a pid that still has the orphan parent.
        info=$(ps -o ppid= -p "$pid" 2>/dev/null) || continue
        [ "${info//[[:space:]]/}" = "$parent" ] || continue
        kill -KILL "$pid" 2>/dev/null || true
        echo "  orphan-kill: SIGKILL survivor pid=$pid"
    done
    return 0
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

# shellcheck source=bin/lib/gh-pr.sh
source "$(dirname "${BASH_SOURCE[0]}")/gh-pr.sh"

# Check if a branch has an open PR on GitHub.
# Returns 0 (open PR exists) or 1 (no open PR).
# Sets WTG_PR_NUM to the PR number if found.
WTG_PR_NUM=""
# WTG_PR_NUM is read by callers, not here.
# shellcheck disable=SC2034
has_open_pr() {
    local branch="$1"
    WTG_PR_NUM=""
    pr_info "$branch" || return 1
    if [ "$PR_STATE" = "OPEN" ]; then
        WTG_PR_NUM="$PR_NUM"
        return 0
    fi
    return 1
}

# Resolve the worktree path for a branch name.
# Prints the path to stdout; empty output means no worktree found.
get_worktree_path() {
    local branch="$1"
    # awk reads all input (no early exit) so git never takes SIGPIPE under
    # pipefail. An early `exit` killed wt-remove with 141 on long lists.
    git worktree list --porcelain | awk -v branch="refs/heads/$branch" '
        /^worktree / { path = substr($0, 10) }
        !found && $0 == "branch " branch { print path; found = 1 }
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

# Print the reason a worktree must be kept; print nothing when it is empty.
# Empty = HEAD has 0 commits not on origin/master AND no tracked diff AND no
# untracked non-ignored file. Fail-closed (shell-fail-open-probe.md): when
# rev-list cannot run, that is a keep reason, never "0 ahead". The diff and
# ls-files probes are inlined rather than reusing has_uncommitted_changes and
# has_untracked_files: those treat a git failure (unreadable file, corrupt
# index) as "clean", which would let --if-empty delete a worktree holding work.
worktree_keep_reason() {
    local wt="$1" ahead rc untracked
    if ! ahead=$(git -C "$wt" rev-list --count origin/master..HEAD 2>/dev/null); then
        echo "cannot verify emptiness (origin/master or HEAD unresolvable)"
        return 0
    fi
    if [ "$ahead" != "0" ]; then
        echo "$ahead commit(s) ahead of origin/master"
        return 0
    fi
    git -C "$wt" diff --quiet 2>/dev/null
    rc=$?
    if [ "$rc" -eq 1 ]; then
        echo "uncommitted changes"
        return 0
    elif [ "$rc" -ne 0 ]; then
        echo "cannot verify emptiness (git diff exited $rc)"
        return 0
    fi
    git -C "$wt" diff --cached --quiet 2>/dev/null
    rc=$?
    if [ "$rc" -eq 1 ]; then
        echo "uncommitted changes"
        return 0
    elif [ "$rc" -ne 0 ]; then
        echo "cannot verify emptiness (git diff --cached exited $rc)"
        return 0
    fi
    if ! untracked=$(git -C "$wt" ls-files --others --exclude-standard 2>/dev/null); then
        echo "cannot verify emptiness (git ls-files failed)"
        return 0
    fi
    if [ -n "$untracked" ]; then
        echo "untracked files"
        return 0
    fi
}
