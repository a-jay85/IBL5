#!/usr/bin/env bash
# bin/lib/claim-file.sh: sourced. Exclusive file claims for directory locks.
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

# claim_file_lying_mkdir_stub <dir>: test seam. Writes <dir>/mkdir, a stand-in that makes
# the directory but always exits 0, the worst case of uutils mkdir under a race.
# Harnesses prepend <dir> to PATH.
claim_file_lying_mkdir_stub() {
    command mkdir -p "$1" || return 1
    printf '%s\n' '#!/bin/bash' '/bin/mkdir -p "$@" 2>/dev/null' 'exit 0' >| "$1/mkdir" \
        && chmod +x "$1/mkdir"
}
