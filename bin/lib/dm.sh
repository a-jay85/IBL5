#!/usr/bin/env bash
# shellcheck shell=bash
# bin/lib/dm.sh: resolve and call bin/discord-dm. Sourced; sets no shell options.
#
# dm_bin <bin-dir> [SEAM_VAR ...]
#   Prints the value of the first named variable that is set and non-empty.
#   Unset and empty both fall through, the same as ${X:-default}. With no
#   non-empty seam it prints <bin-dir>/discord-dm. Returns 2 when <bin-dir> is
#   missing or a seam name is not a shell identifier.
#
# dm_ping <msg> [flag ...]
#   Runs "$DM_CMD" --ping [flag ...] <msg>. The message goes last, matching
#   discord-dm's argument order. DM_CMD is the caller's resolved binary.
#   Returns discord-dm's exit status. Callers keep their own gating and || true.

[ -n "${_DM_SOURCED:-}" ] && return 0
_DM_SOURCED=1

dm_bin() {
    local dir="${1:-}" name val
    if [ -z "$dir" ]; then
        printf '%s\n' "dm_bin: missing <bin-dir>" >&2
        return 2
    fi
    shift
    for name in "$@"; do
        case "$name" in
            '' | [0-9]* | *[!A-Za-z0-9_]*)
                printf '%s\n' "dm_bin: invalid seam name: '$name'" >&2
                return 2 ;;
        esac
        val="${!name:-}"
        if [ -n "$val" ]; then
            printf '%s\n' "$val"
            return 0
        fi
    done
    printf '%s\n' "$dir/discord-dm"
}

dm_ping() {
    if [ $# -lt 1 ]; then
        printf '%s\n' "dm_ping: missing <msg>" >&2
        return 2
    fi
    if [ -z "${DM_CMD:-}" ]; then
        printf '%s\n' "dm_ping: DM_CMD is not set" >&2
        return 2
    fi
    local msg="$1"
    shift
    "$DM_CMD" --ping "$@" "$msg"
}
