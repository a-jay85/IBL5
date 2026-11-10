#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/worktree-lock.sh: a per-worktree mkdir + pid lock (backlog#1827).
# post-plan-now holds <git-dir>/post-plan-now.lock for the whole run; post-plan-fleet and
# pcw_inflight probe it; plan-exec-mutations holds <git-dir>/exec-mutations/run.lock.
# A lock is stale only when its recorded pid is provably dead (kill -0 fails and ps -p
# finds nothing). File mtime is never consulted. An unreadable lock reads as held.
# Every read uses a redirect or a here-string, never a pipe into grep -q (SIGPIPE-safe).

WTLOCK_SELF_REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"

# wtlock_path <worktree-dir>: prints <absolute git dir>/post-plan-now.lock; rc 1 when git
# cannot resolve the git dir. Same answer from the worktree root or any subdir.
wtlock_path() {
    local gd
    gd=$(git -C "$1" rev-parse --absolute-git-dir 2>/dev/null) || return 1
    [ -n "$gd" ] || return 1
    printf '%s/post-plan-now.lock\n' "$gd"
}

# wtlock_pid_dead <pid>: rc 0 only for a positive integer pid with no live process.
wtlock_pid_dead() {
    [[ "${1:-}" =~ ^[0-9]+$ ]] && [ "$1" -gt 0 ] || return 1
    kill -0 "$1" 2>/dev/null && return 1
    ps -p "$1" >/dev/null 2>&1 && return 1
    return 0
}

# wtlock_read_pid <lockdir>: prints the recorded pid; rc 1 when missing, unreadable or not numeric.
wtlock_read_pid() {
    local p=""
    [ -r "$1/pid" ] || return 1
    IFS= read -r p < "$1/pid" || [ -n "$p" ] || return 1
    [[ $p =~ ^[0-9]+$ ]] || return 1
    printf '%s\n' "$p"
}

wtlock_write_pid() {
    local t="$1/pid.$$.$RANDOM.tmp"
    printf '%s\n' "$2" > "$t" && mv -f "$t" "$1/pid"
}

# wtlock_live <lockdir>: rc 0 and prints the holder pid (or "unknown") when held;
# rc 1 when absent or stale. Fail closed: a pid-less or unreadable lock is held.
wtlock_live() {
    local p d
    if [ ! -e "$1" ]; then
        d=$(dirname "$1")
        if [ -d "$d" ] && [ ! -x "$d" ]; then echo unknown; return 0; fi
        return 1
    fi
    if ! p=$(wtlock_read_pid "$1"); then echo unknown; return 0; fi
    if wtlock_pid_dead "$p"; then return 1; fi
    printf '%s\n' "$p"; return 0
}

# wtlock_acquire <lockdir> <pid> [--steal]: rc 0 when <pid> now holds the lock; rc 1 and
# prints the holder when refused. A dead-pid lock is reclaimed once. --steal overwrites a
# live holder's pid (post-plan-now --force only).
wtlock_acquire() {
    local d="$1" me="$2" mode="${3:-}" h old
    mkdir -p "$(dirname "$d")" 2>/dev/null || { echo unknown; return 1; }
    if mkdir "$d" 2>/dev/null; then wtlock_write_pid "$d" "$me" || { echo unknown; return 1; }; return 0; fi
    if h=$(wtlock_live "$d"); then
        [ "$h" = "$me" ] && return 0
        if [ "$mode" = --steal ]; then wtlock_write_pid "$d" "$me" && return 0; fi
        printf '%s\n' "$h"; return 1
    fi
    if ! old=$(wtlock_read_pid "$d"); then
        if mkdir "$d" 2>/dev/null; then wtlock_write_pid "$d" "$me" || { echo unknown; return 1; }; return 0; fi
        echo unknown; return 1
    fi
    wtlock_pid_dead "$old" || { printf '%s\n' "$old"; return 1; }
    rm -rf "$d"
    if mkdir "$d" 2>/dev/null; then wtlock_write_pid "$d" "$me" || { echo unknown; return 1; }; return 0; fi
    echo unknown; return 1
}

# wtlock_adopt <lockdir> <old-pid> <new-pid>: hand the lock from the submitting shell to
# the long-lived run. Rewrites the pid when <old-pid> or <new-pid> holds it; otherwise
# falls back to a plain acquire, which refuses another live holder.
wtlock_adopt() {
    local d="$1" cur
    if cur=$(wtlock_read_pid "$d") && { [ "$cur" = "$2" ] || [ "$cur" = "$3" ]; }; then
        wtlock_write_pid "$d" "$3"; return $?
    fi
    wtlock_acquire "$d" "$3"
}

# wtlock_release <lockdir> <pid>: removes the lock only when <pid> holds it. Always rc 0.
wtlock_release() {
    local p
    p=$(wtlock_read_pid "$1") || return 0
    [ "$p" = "$2" ] || return 0
    rm -rf "$1"
}

# wtlock_wt_for_branch <branch> [repo-dir]: prints the worktree path checked out on
# <branch>; rc 1 when none. repo-dir defaults to the checkout this file lives in.
wtlock_wt_for_branch() {
    local branch="$1" repo="${2:-$WTLOCK_SELF_REPO}" listing line wt=""
    listing=$(git -C "$repo" worktree list --porcelain 2>/dev/null) || return 1
    while IFS= read -r line; do
        case "$line" in
            "worktree "*) wt="${line#worktree }" ;;
            "branch refs/heads/$branch") printf '%s\n' "$wt"; return 0 ;;
        esac
    done <<< "$listing"
    return 1
}
