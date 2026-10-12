# Sourced library — do NOT set -e at file scope; this is sourced into other shells.
# Usage: source bin/lib/docfix-health.sh
# shellcheck shell=bash

# docfix_health_validate <alert> <kind>
#
# Returns 0 for a valid pair: alert=false with an empty kind, or alert=true with
# kind in ci-red|died|wedged|never-ran. Returns 1 otherwise, with a one-line
# reason on stderr. A workflow_dispatch input is free text, so allow-list it.
docfix_health_validate() {
    local alert="${1:-}" kind="${2:-}"
    case "$alert" in
        false)
            [ -z "$kind" ] && return 0 ;;
        true)
            case "$kind" in
                ci-red|died|wedged|never-ran) return 0 ;;
            esac ;;
    esac
    printf 'REFUSED: invalid health pair (alert=%s, kind=%s)\n' "$alert" "$kind" >&2
    return 1
}

# docfix_health_compose <kind> <detail> <out_path>
#
# Writes a two-line Discord message to <out_path>: a heading naming <kind>, then
# <detail> collapsed to one line and cut to 1500 characters (Discord caps a
# message at 2000). Returns 1 and does NOT create <out_path> when <kind> is not
# one of the four health kinds or poll-silent, or when <detail> is empty.
docfix_health_compose() {
    local kind="${1:-}" detail="${2:-}" out_path="${3:-}" flat
    case "$kind" in
        ci-red|died|wedged|never-ran|poll-silent) ;;
        *) return 1 ;;
    esac
    [ -n "$detail" ] || return 1
    flat="$(printf '%s' "$detail" | tr '\r\n' '  ')"
    {
        printf 'Doc refresh needs you: the pipeline could not clear the stale docs on its own (%s).\n' "$kind"
        printf '%.1500s\n' "$flat"
    } > "$out_path"
}

# docfix_health_poll_detail <detail> <log_dir> <run_log_dir>
#
# Echoes one line: the detail plus where the Mac poll's launchd logs live and the
# newest docfix-run log ("none" when there is no log). When that log holds a
# usage/session-limit line, the last such line is appended as " | Limit: <line>"
# (trimmed, cut to 200 chars) so the DM says why a run died. Always returns 0.
docfix_health_poll_detail() {
    local detail="${1:-}" log_dir="${2:-}" run_log_dir="${3:-}" newest limit="" suffix=""
    newest="$(ls -t "$run_log_dir"/docfix-run-*.log 2>/dev/null | head -1)" || true
    if [ -n "$newest" ]; then
        limit="$(grep -E 'hit your session limit|usage limit' "$newest" 2>/dev/null | tail -1 \
            | tr '\r\n' '  ' | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//' | cut -c1-200)" || true
    else
        newest="none"
    fi
    [ -n "$limit" ] && suffix=" | Limit: $limit"
    printf '%s | Poll logs: %s/launchd-stdout.log, %s/launchd-stderr.log | Run log: %s%s\n' \
        "$detail" "$log_dir" "$log_dir" "$newest" "$suffix"
}

# docfix_health_heartbeat_verdict <last_iso> <now_epoch> [max_h]
#
# Always returns 0 and echoes exactly one of: none | fresh <age_h> | stale <age_h>.
# "none" means <last_iso> is empty or does not parse (the backstop stays quiet on
# an empty run history). <now_epoch> is a parameter so the harness pins the clock.
#
# Threshold arithmetic for the default max_h=24. The heartbeat is the dispatch
# the poll sends when it completes. bin/docfix-run returns right after
# `launchctl bootstrap`, so the dispatch lands minutes after the launchd poll:
# 04:05Z in PDT, 05:05Z in PST. Allow a conservative latest landing of 06:15Z.
# The backstop cron is `0 12 * * *`. The scheduled-run lag recorded in the
# bin/docfix-poll comment block is median 1h25m, p90 3h49m, p95 4h01m, max
# 9h47m, so the backstop runs between 12:00Z and 21:47Z.
#   Healthy day: the newest heartbeat is that morning's. The oldest it can be at
#     check time is 21:47Z - 04:05Z = 17h42m.
#   Missed day: the newest heartbeat is the previous morning's, at the latest
#     06:15Z. The youngest it can be is 12:00Z + 24h - 06:15Z = 29h45m.
#   Any threshold strictly inside (17h42m, 29h45m) separates the two. 24 leaves
#     6h18m of margin on the healthy side and 5h45m on the missed side.
#   The cron cannot race the poll: the earliest backstop (12:00Z) is 6h55m after
#     the latest PST poll (05:05Z), and the check reads the heartbeat's createdAt,
#     which is stamped at dispatch.
docfix_health_heartbeat_verdict() {
    local last_iso="${1:-}" now_epoch="${2:-0}" max_h="${3:-24}" last_epoch age_s age_h
    if [ -z "$last_iso" ]; then echo none; return 0; fi
    last_epoch="$(jq -rn --arg t "$last_iso" '$t | fromdateiso8601' 2>/dev/null)" || last_epoch=""
    case "$last_epoch" in
        ''|*[!0-9]*) echo none; return 0 ;;
    esac
    age_s=$(( now_epoch - last_epoch ))
    age_h=$(( age_s / 3600 ))
    if [ "$age_s" -ge $(( max_h * 3600 )) ]; then
        echo "stale $age_h"
    else
        echo "fresh $age_h"
    fi
    return 0
}
