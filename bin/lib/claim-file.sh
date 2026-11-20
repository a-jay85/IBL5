#!/usr/bin/env bash
# bin/lib/claim-file.sh: sourced. Exclusive file claims and atomic stale reclaim for directory locks.
# uutils mkdir (Ubuntu 26.04) reports success to both of two racing mkdir calls, so a
# lock is owned by whoever creates its claim file under noclobber (bash opens it O_EXCL).
# mkdir only makes room for that file.
[ -n "${_CLAIM_FILE_SH_LOADED:-}" ] && return 0
_CLAIM_FILE_SH_LOADED=1

# claim_file <path> [cmd [args...]]: create <path> exclusively, filled with cmd's stdout
# (empty when no cmd). rc 0 = this caller created it and owns the lock. rc 1 = <path>
# already exists (a file, or a directory left by an older lock layout) or the write failed.
# Saves and restores the caller's noclobber state.
claim_file() {
    local f="$1" had_c=0 rc=0
    shift
    case $- in *C*) had_c=1 ;; esac
    set -C
    if [ $# -gt 0 ]; then
        if { "$@" > "$f"; } 2>/dev/null; then rc=0; else rc=1; fi
    else
        if { true > "$f"; } 2>/dev/null; then rc=0; else rc=1; fi
    fi
    [ "$had_c" = 1 ] || set +C
    return "$rc"
}

# claim_reclaim <lockdir> <claim-name> <stale-fn> [cmd [args...]]: atomic stale-lock
# reclaim. Takes the sibling mutex <lockdir>.reclaim.lock (mkdir + claim_file owner),
# re-runs "<stale-fn> <lockdir>" under it, and only when that returns 0 (or <lockdir> is
# gone) removes <lockdir> and re-takes it with claim_file <lockdir>/<claim-name> cmd...
# rc 0 = this caller now owns <lockdir>. rc 1 = refused: a live reclaim holds the mutex,
# the re-check found the lock live, or another caller re-took it first.
# The mutex name ends in .lock so bin/automouse/queue move_sidecars skips it like a run
# lock. A mutex whose owner pid is dead, or older than _CLAIM_RECLAIM_GRACE seconds, was
# left by a run killed mid-reclaim and is reaped once. Two callers reaping the same
# orphaned mutex at once is the one window left, the same one wtlock_acquire accepts.
_CLAIM_RECLAIM_GRACE=30

_claim_reclaim_orphaned() {
    local m="$1" o now age
    o=$(cat "$m/owner" 2>/dev/null) || o=""
    case "$o" in
        ''|*[!0-9]*) ;;
        *) kill -0 "$o" 2>/dev/null || return 0 ;;
    esac
    now=$(date +%s)
    age=$(stat -c %Y "$m" 2>/dev/null || stat -f %m "$m" 2>/dev/null || echo "$now")
    [ $(( now - age )) -gt "$_CLAIM_RECLAIM_GRACE" ]
}

claim_reclaim() {
    local d="$1" name="$2" stale="$3" m="$1.reclaim.lock" rc=1
    shift 3
    if ! { mkdir "$m" 2>/dev/null && claim_file "$m/owner" printf '%s\n' "$$"; }; then
        _claim_reclaim_orphaned "$m" || return 1
        rm -rf "$m"
        { mkdir "$m" 2>/dev/null && claim_file "$m/owner" printf '%s\n' "$$"; } || return 1
    fi
    # Under the mutex, re-judge: another caller may have re-taken the lock since the
    # caller's own probe.
    if [ ! -e "$d" ] || "$stale" "$d"; then
        rm -rf "$d"
        if mkdir "$d" 2>/dev/null && claim_file "$d/$name" "$@"; then rc=0; fi
    fi
    rm -rf "$m"
    return "$rc"
}

# claim_file_lying_mkdir_stub <dir>: test seam. Writes <dir>/mkdir, a stand-in that makes
# the directory but always exits 0, the worst case of uutils mkdir under a race.
# Harnesses prepend <dir> to PATH.
claim_file_lying_mkdir_stub() {
    command mkdir -p "$1" || return 1
    printf '%s\n' '#!/bin/bash' '/bin/mkdir -p "$@" 2>/dev/null' 'exit 0' >| "$1/mkdir" \
        && chmod +x "$1/mkdir"
}
