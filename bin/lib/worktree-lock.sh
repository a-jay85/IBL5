#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/worktree-lock.sh: a per-worktree mkdir + pid lock (backlog#1827).
# post-plan-now holds <git-dir>/post-plan-now.lock for the whole run; post-plan-fleet and
# pcw_inflight probe it; plan-exec-mutations holds <git-dir>/exec-mutations/run.lock.
# A lock is stale when its recorded pid is provably dead (kill -0 fails and ps -p finds
# nothing), or when it has no pid file and is older than WTLOCK_GRACE_SECS (default 30): a
# holder killed between mkdir and the pid write must not wedge the lock. Mtime is read only
# for that pid-less case. An unreadable or non-numeric pid file reads as held.
# Every read uses a redirect or a here-string, never a pipe into grep -q (SIGPIPE-safe).

# shellcheck source=bin/lib/claim-file.sh
. "$(dirname "${BASH_SOURCE[0]}")/claim-file.sh"

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

# wtlock_pidless_old <dir>: rc 0 only when <dir> is a dir with no pid file whose mtime is
# older than the grace. A stat failure reads as young (held), never as epoch 0.
wtlock_pidless_old() {
    local m now g="${WTLOCK_GRACE_SECS:-30}"
    [ -d "$1" ] && [ ! -e "$1/pid" ] || return 1
    m=$(stat -c %Y "$1" 2>/dev/null || stat -f %m "$1" 2>/dev/null) || return 1
    [[ $m =~ ^[0-9]+$ ]] || return 1
    now=$(date +%s)
    [ $((now - m)) -gt "$g" ]
}

# wtlock_live <lockdir>: rc 0 and prints the holder pid (or "unknown") when held;
# rc 1 when absent or stale. Fail closed: an unreadable lock, or a pid-less one still
# inside the grace, is held.
wtlock_live() {
    local p d
    if [ ! -e "$1" ]; then
        d=$(dirname "$1")
        if [ -d "$d" ] && [ ! -x "$d" ]; then echo unknown; return 0; fi
        return 1
    fi
    if ! p=$(wtlock_read_pid "$1"); then
        wtlock_pidless_old "$1" && return 1
        echo unknown; return 0
    fi
    if wtlock_pid_dead "$p"; then return 1; fi
    printf '%s\n' "$p"; return 0
}

# wtlock_stale <lockdir>: rc 0 when the lock is reclaimable (dead pid, or pid-less and
# older than the grace).
wtlock_stale() {
    local p
    if p=$(wtlock_read_pid "$1"); then wtlock_pid_dead "$p"; return $?; fi
    wtlock_pidless_old "$1"
}

# wtlock_create <lockdir> <pid>: mkdir the lock and claim pid with noclobber; rc 1 when the
# dir or pid already exists. When the pid write fails it removes the dir it just made, so it
# never leaves a pid-less lock behind.
wtlock_create() {
    mkdir "$1" 2>/dev/null || return 1
    claim_file "$1/pid" printf '%s\n' "$2" && return 0
    # A lost claim leaves the winner's pid in place; only clean up a dir whose pid never landed.
    [ -e "$1/pid" ] || rm -rf "$1"
    return 1
}

# wtlock_acquire <lockdir> <pid> [--steal]: rc 0 when <pid> now holds the lock; rc 1 and
# prints the holder when refused. A stale lock is reclaimed once, under the <lockdir>.reclaim
# mutex, so two runs never reclaim at once and one never removes the other's fresh lock.
# --steal overwrites a live holder's pid (post-plan-now --force only).
wtlock_acquire() {
    local d="$1" me="$2" mode="${3:-}" h m="$1.reclaim"
    mkdir -p "$(dirname "$d")" 2>/dev/null || { echo unknown; return 1; }
    wtlock_create "$d" "$me" && return 0
    if h=$(wtlock_live "$d"); then
        [ "$h" = "$me" ] && return 0
        if [ "$mode" = --steal ]; then wtlock_write_pid "$d" "$me" && return 0; fi
        printf '%s\n' "$h"; return 1
    fi
    # Stale or gone. A mutex older than the grace was left by a run killed mid-reclaim:
    # clear it and retry once.
    if ! { mkdir "$m" 2>/dev/null && claim_file "$m/owner" printf '%s\n' "$me"; }; then
        if wtlock_pidless_old "$m" && rm -rf "$m" && mkdir "$m" 2>/dev/null && claim_file "$m/owner" printf '%s\n' "$me"; then :
        else echo unknown; return 1
        fi
    fi
    # Under the mutex, re-check: another run may have taken the lock since the probe above.
    if [ -e "$d" ] && ! wtlock_stale "$d"; then
        rm -rf "$m"
        h=$(wtlock_read_pid "$d") || h=unknown
        printf '%s\n' "$h"; return 1
    fi
    rm -rf "$d"
    if wtlock_create "$d" "$me"; then rm -rf "$m"; return 0; fi
    rm -rf "$m"
    h=$(wtlock_read_pid "$d") || h=unknown
    printf '%s\n' "$h"; return 1
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
