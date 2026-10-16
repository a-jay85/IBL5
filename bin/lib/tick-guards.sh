# shellcheck shell=bash
# tick-guards.sh: shared guards for the launchd ticks bin/wt-gc-tick and
# bin/wt-sync-tick. Sourced, never executed. Sets no shell option, installs no
# trap, never calls exit, reads no env var. Safe under `set -euo pipefail`
# when the caller uses the documented `|| rc=$?` / `if !` call forms.

# tick_lock_acquire <lock-file> <logfn>
# Single-flight PID lock. <logfn> is an event callback the caller owns, so log
# wording stays per tick:  <logfn> held <pid> | stale <pid-or-empty> |
# write-failed <lock-file>.  Returns 0 acquired, 1 held, 2 write failed.
tick_lock_acquire() {
    local lock_file="$1" logfn="$2" existing_pid=""
    if [ -f "$lock_file" ]; then
        existing_pid="$(cat "$lock_file" 2>/dev/null || true)"
        if [ -n "$existing_pid" ] && kill -0 "$existing_pid" 2>/dev/null; then
            "$logfn" held "$existing_pid"
            return 1
        fi
        "$logfn" stale "$existing_pid"
    fi
    if ! printf '%d\n' "$$" > "$lock_file" 2>/dev/null; then
        "$logfn" write-failed "$lock_file"
        return 2
    fi
    return 0
}

# tick_lock_release <lock-file>: remove the lock. Always returns 0.
tick_lock_release() {
    rm -f "$1" 2>/dev/null || true
    return 0
}

# hid_idle_secs <reader>: HID idle seconds from `<reader> -c IOHIDSystem`.
#   rc 0: stdout = idle seconds (digits only)
#   rc 1: reader empty, missing, or not executable; stdout empty
#   rc 2: value unreadable (no line, non-numeric, or > 18 digits);
#         stdout = the raw offending field, possibly empty
# The raw field is digit-checked before any arithmetic: awk's coercion of a
# non-numeric field differs by implementation. The 18-digit cap keeps bash
# arithmetic inside 64 bits. 10# forces base 10 so a leading zero never
# parses as octal.
hid_idle_secs() {
    local reader="${1-}" raw=""
    if [ -z "$reader" ] || [ ! -x "$reader" ]; then
        return 1
    fi
    raw="$("$reader" -c IOHIDSystem 2>/dev/null | awk '/HIDIdleTime/ && !f {print $NF; f=1}' 2>/dev/null)" || raw=""
    case "$raw" in
        ''|*[!0-9]*) printf '%s\n' "$raw"; return 2 ;;
    esac
    if [ "${#raw}" -gt 18 ]; then
        printf '%s\n' "$raw"
        return 2
    fi
    printf '%s\n' "$(( 10#$raw / 1000000000 ))"
}
