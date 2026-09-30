#!/usr/bin/env bash
# Usage-gate primitives: zones, pause markers, the drain token, the delta log, and
# the limit-hit / env-error predicates. Shared by the PreToolUse hook, the launchd
# coordinator, and every headless runner. See ADR-0143.
#
# Bash 3.2 / macOS compatible: no declare -A, no mapfile, no ${var,,}, no date -d/-v.
# Sourced library: no `main`, no `set -e`. Every function returns a status the caller tests.

[ -n "${_USAGE_GATE_SH:-}" ] && return 0
_USAGE_GATE_SH=1

# shellcheck source=/dev/null
. "$(dirname "${BASH_SOURCE[0]}")/usage-fetch.sh"

# ------------------------------------------------------------ 2a thresholds

# usage_thresholds
# Echoes "<drain> <stop>". Unset and empty env both take the default; a non-integer
# or mis-ordered pair (need 0 < drain < stop <= 100) drops BOTH to 93/98.
usage_thresholds() {
    local d="${IBL5_USAGE_DRAIN_PCT:-93}" s="${IBL5_USAGE_STOP_PCT:-98}" ok=1
    case "$d" in ''|*[!0-9]*) ok=0 ;; esac
    case "$s" in ''|*[!0-9]*) ok=0 ;; esac
    if [ "$ok" -eq 1 ]; then
        if ! [ "$d" -gt 0 ] || ! [ "$d" -lt "$s" ] || ! [ "$s" -le 100 ]; then
            ok=0
        fi
    fi
    if [ "$ok" -eq 0 ]; then
        usage_log "bad-thresholds drain=$d stop=$s"
        echo "93 98"
        return 0
    fi
    echo "$d $s"
}

# ------------------------------------------------------------ 2b zone math

usage_zone_pct() {  # <json>
    printf '%s' "${1:-}" | jq -r '[.five_hour.utilization // 0, .seven_day.utilization // 0] | max'
}

usage_zone() {  # <json>
    local th d s
    th=$(usage_thresholds)
    d=${th%% *}
    s=${th##* }
    printf '%s' "${1:-}" | jq -r --argjson d "$d" --argjson s "$s" \
        '([.five_hour.utilization // 0, .seven_day.utilization // 0] | max) as $p
         | if $p >= $s then "stop" elif $p >= $d then "drain" else "normal" end'
}

# usage_limiting_window <json>: five_hour or seven_day, whichever is larger.
# A tie returns seven_day (the later reset, so resume never fires early).
usage_limiting_window() {
    printf '%s' "${1:-}" | jq -r \
        'if (.five_hour.utilization // 0) > (.seven_day.utilization // 0) then "five_hour" else "seven_day" end'
}

# usage_resets_epoch <json> <window>: epoch seconds, or nothing when null/unparseable.
usage_resets_epoch() {
    printf '%s' "${1:-}" | jq -r --arg w "${2:-}" \
        '.[$w].resets_at // empty | sub("\\.[0-9]+"; "") | sub("\\+00:00$"; "Z") | fromdateiso8601' 2>/dev/null
}

# ------------------------------------------------------------ 2c pause markers

usage_markers_dir() {
    printf '%s/markers\n' "$(usage_state_dir)"
}

usage_valid_sid() {  # <sid>
    case "${1:-}" in
        [0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f]-[0-9a-f][0-9a-f][0-9a-f][0-9a-f]-[0-9a-f][0-9a-f][0-9a-f][0-9a-f]-[0-9a-f][0-9a-f][0-9a-f][0-9a-f]-[0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f])
            return 0 ;;
    esac
    return 1
}

usage_runner_priority() {  # <runner>
    case "${1:-}" in
        post-plan-now) echo 1 ;;
        automouse) echo 2 ;;
        plan-now) echo 3 ;;
        *) return 1 ;;
    esac
}

# usage_marker_write <sid> <runner> <resume_bin> <reason> <zone> <pct> <window> <resets_epoch>
usage_marker_write() {
    local sid="${1:-}" runner="${2:-}" rbin="${3:-}" reason="${4:-}" zone="${5:-}" \
        pct="${6:-0}" window="${7:-}" resets="${8:-}"
    if ! usage_valid_sid "$sid"; then
        usage_log "marker-rejected reason=bad-sid sid=$sid"
        return 1
    fi
    local prio
    if ! prio=$(usage_runner_priority "$runner"); then
        usage_log "marker-rejected reason=bad-runner runner=$runner"
        return 1
    fi
    case "$rbin" in
        /*) ;;
        *) usage_log "marker-rejected reason=relative-resume-bin bin=$rbin"; return 1 ;;
    esac
    if [ ! -x "$rbin" ]; then
        usage_log "marker-rejected reason=resume-bin-not-executable bin=$rbin"
        return 1
    fi
    local dir file tmp cwd prev_count=0
    dir=$(usage_markers_dir)
    file="$dir/$sid.json"
    tmp="$file.tmp.$$"
    cwd=$(git -C "$(dirname "$rbin")" rev-parse --show-toplevel 2>/dev/null || dirname "$rbin")
    if [ -s "$file" ]; then
        prev_count=$(jq -r '.resume_count // 0' "$file" 2>/dev/null)
        case "$prev_count" in ''|*[!0-9]*) prev_count=0 ;; esac
    else
        prev_count=$(cat "$(usage_state_dir)/cleared/$sid.rc" 2>/dev/null)
        case "$prev_count" in ''|*[!0-9]*) prev_count=0 ;; esac
    fi
    case "$pct" in ''|*[!0-9.]*) pct=0 ;; esac
    case "$resets" in ''|*[!0-9]*) resets="" ;; esac
    if jq -n --arg sid "$sid" --arg runner "$runner" --argjson prio "$prio" \
        --arg rbin "$rbin" --arg cwd "$cwd" --arg reason "$reason" --arg zone "$zone" \
        --argjson pct "$pct" --arg window "$window" --arg resets "$resets" \
        --argjson now "$(date +%s)" --argjson rc "$prev_count" \
        '{session_id:$sid, runner:$runner, priority:$prio,
          resume_argv:[$rbin, "--resume-paused", $sid], resume_cwd:$cwd,
          reason:$reason, zone:$zone, pct:$pct, window:$window,
          resets_at:(if $resets == "" then null else ($resets|tonumber) end),
          paused_at:$now, resume_count:$rc}' 2>/dev/null > "$tmp" \
        && mv "$tmp" "$file"; then
        return 0
    fi
    rm -f "$tmp"
    usage_log "marker-write-failed sid=$sid"
    return 1
}

usage_marker_exists() {  # <sid>
    usage_valid_sid "${1:-}" || return 1
    [ -s "$(usage_markers_dir)/$1.json" ]
}

usage_marker_read() {  # <sid>
    usage_valid_sid "${1:-}" || return 1
    cat "$(usage_markers_dir)/$1.json" 2>/dev/null
}

# usage_paused_hold_live <sid> <ttl_secs>: rc 0 while a claim lock held for <sid> must
# not be stolen: the marker still exists, or it was cleared less than <ttl_secs> ago.
usage_paused_hold_live() {
    local sid="${1:-}" ttl="${2:-0}" stamp at now
    usage_valid_sid "$sid" || return 1
    usage_marker_exists "$sid" && return 0
    stamp="$(usage_state_dir)/cleared/$sid"
    [ -s "$stamp" ] || return 1
    at=$(cat "$stamp" 2>/dev/null)
    case "$at" in ''|*[!0-9]*) return 1 ;; esac
    case "$ttl" in ''|*[!0-9]*) return 1 ;; esac
    now=$(date +%s)
    [ $(( now - at )) -lt "$ttl" ]
}

# usage_marker_find <runner> <cwd>: prints the newest marker sid whose runner and
# resume_cwd both match; nothing (rc 1) when none does.
usage_marker_find() {
    local runner="${1:-}" cwd="${2:-}" dir sid
    dir=$(usage_markers_dir)
    compgen -G "$dir/*.json" >/dev/null 2>&1 || return 1
    sid=$(jq -rs --arg r "$runner" --arg c "$cwd" \
        '[.[] | select(.runner == $r and .resume_cwd == $c)] | sort_by(.paused_at) | last | .session_id // empty' \
        "$dir"/*.json 2>/dev/null)
    [ -n "$sid" ] || return 1
    printf '%s\n' "$sid"
}

# usage_paused_worktrees: prints the resume_cwd of every post-plan-now marker, one per
# line. Nothing when no post-plan run is paused.
usage_paused_worktrees() {
    local dir
    dir=$(usage_markers_dir)
    compgen -G "$dir/*.json" >/dev/null 2>&1 || return 0
    jq -rs '.[] | select(.runner == "post-plan-now") | .resume_cwd // empty' \
        "$dir"/*.json 2>/dev/null
    return 0
}

# usage_worktree_paused <path>: rc 0 when a paused post-plan-now marker belongs to the
# worktree at <path>. Both sides are resolved with `pwd -P`, so a symlinked path still matches.
usage_worktree_paused() {
    local want="${1:-}" cwd real
    [ -n "$want" ] && [ -d "$want" ] || return 1
    want=$(cd "$want" 2>/dev/null && pwd -P) || return 1
    while IFS= read -r cwd; do
        [ -n "$cwd" ] || continue
        real=$(cd "$cwd" 2>/dev/null && pwd -P) || real="$cwd"
        [ "$real" = "$want" ] && return 0
    done < <(usage_paused_worktrees)
    return 1
}

# usage_marker_clear <sid>: removes the marker and drops the sid from dm-seen, so a
# later re-pause of the same session counts as a new wave. Also stamps cleared/<sid>
# with the epoch so a claim lock held for that sid stays live for a grace window
# (usage_paused_hold_live). Saves resume_count to cleared/<sid>.rc so a re-pause of
# the same session carries the count forward (runaway guard fires on persistent 429s).
# Idempotent.
usage_marker_clear() {
    usage_valid_sid "${1:-}" || return 1
    local f rc_to_save
    f="$(usage_markers_dir)/$1.json"
    if [ -s "$f" ]; then
        rc_to_save=$(jq -r '.resume_count // 0' "$f" 2>/dev/null)
        case "$rc_to_save" in ''|*[!0-9]*) rc_to_save=0 ;; esac
    else
        rc_to_save=0
    fi
    rm -f "$f"
    mkdir -p "$(usage_state_dir)/cleared" 2>/dev/null \
        && date +%s > "$(usage_state_dir)/cleared/$1" 2>/dev/null \
        && printf '%s\n' "$rc_to_save" > "$(usage_state_dir)/cleared/$1.rc" 2>/dev/null
    local seen tmp
    seen="$(usage_state_dir)/dm-seen"
    if [ -f "$seen" ]; then
        tmp="$seen.tmp.$$"
        grep -vxF "$1" "$seen" > "$tmp" 2>/dev/null
        mv "$tmp" "$seen"
    fi
    return 0
}

usage_marker_bump_resume() {  # <sid>
    usage_valid_sid "${1:-}" || return 1
    local f tmp
    f="$(usage_markers_dir)/$1.json"
    tmp="$f.tmp.$$"
    [ -s "$f" ] || return 1
    if jq '.resume_count = ((.resume_count // 0) + 1)' "$f" > "$tmp" 2>/dev/null && mv "$tmp" "$f"; then
        return 0
    fi
    rm -f "$tmp"
    return 1
}

# usage_marker_eligible <sid> <now>: 0 when the coordinator may resume this marker.
# Not eligible when: stuck; a limit-hit pause younger than 600 s (backoff, so a
# transient 429 does not hot-loop); or a resume is already in flight (live pid).
usage_marker_eligible() {
    local sid="${1:-}" now="${2:-0}" f rpid stuck reason paused_at
    usage_valid_sid "$sid" || return 1
    f="$(usage_markers_dir)/$sid.json"
    [ -s "$f" ] || return 1
    stuck=$(jq -r '.stuck // false' "$f" 2>/dev/null)
    [ "$stuck" = "true" ] && return 1
    reason=$(jq -r '.reason // ""' "$f" 2>/dev/null)
    paused_at=$(jq -r '.paused_at // 0' "$f" 2>/dev/null)
    if [ "$reason" = "limit-hit" ] && [ $(( now - paused_at )) -lt 600 ]; then
        return 1
    fi
    rpid=$(jq -r '.resuming_pid // empty' "$f" 2>/dev/null)
    if [ -n "$rpid" ] && kill -0 "$rpid" 2>/dev/null; then
        return 1
    fi
    return 0
}

# usage_marker_set_resuming <sid> <pid>
usage_marker_set_resuming() {
    usage_valid_sid "${1:-}" || return 1
    local f tmp
    f="$(usage_markers_dir)/$1.json"
    tmp="$f.tmp.$$"
    [ -s "$f" ] || return 1
    if jq --argjson p "${2:-0}" --argjson n "$(date +%s)" \
        '.resuming_pid = $p | .resuming_at = $n' "$f" 2>/dev/null > "$tmp" && mv "$tmp" "$f"; then
        return 0
    fi
    rm -f "$tmp"
    return 1
}

# usage_marker_set_stuck <sid> <reason>: the coordinator leaves a stuck marker paused.
usage_marker_set_stuck() {
    usage_valid_sid "${1:-}" || return 1
    local f tmp
    f="$(usage_markers_dir)/$1.json"
    tmp="$f.tmp.$$"
    [ -s "$f" ] || return 1
    if jq --arg r "${2:-stuck}" '.stuck = true | .stuck_reason = $r' "$f" 2>/dev/null > "$tmp" && mv "$tmp" "$f"; then
        return 0
    fi
    rm -f "$tmp"
    return 1
}

# usage_markers_sorted: sids in drain order (priority asc, then paused_at asc).
usage_markers_sorted() {
    local dir
    dir=$(usage_markers_dir)
    compgen -G "$dir/*.json" >/dev/null 2>&1 || return 0
    jq -rs 'sort_by(.priority, .paused_at) | .[].session_id' "$dir"/*.json 2>/dev/null
}

# ------------------------------------------------------------ 2d drain token

usage_token_dir() {
    printf '%s/drain-token.d\n' "$(usage_state_dir)"
}

usage_token_holder() {
    local h
    h=$(usage_token_dir)/holder
    [ -s "$h" ] || return 0
    awk '{print $1}' "$h"
}

# usage_token_acquire <sid> [pid]
usage_token_acquire() {
    local sid="${1:-}" pid="${2:-}" dir
    usage_valid_sid "$sid" || return 1
    dir=$(usage_token_dir)
    if mkdir "$dir" 2>/dev/null; then
        printf '%s %s %s\n' "$sid" "$pid" "$(date +%s)" > "$dir/holder"
        return 0
    fi
    if [ "$(usage_token_holder)" = "$sid" ]; then
        if [ -n "$pid" ]; then
            printf '%s %s %s\n' "$sid" "$pid" "$(date +%s)" > "$dir/holder"
        fi
        return 0
    fi
    return 1
}

# usage_token_release <sid>: removes the token only when <sid> holds it. Idempotent.
usage_token_release() {
    local sid="${1:-}"
    usage_valid_sid "$sid" || return 0
    if [ "$(usage_token_holder)" = "$sid" ]; then
        rm -rf "$(usage_token_dir)"
    fi
    return 0
}

# usage_token_reclaim_dead: drop the token when its holder pid is dead, or when the
# holder line has no pid and is older than 600 s (crash between mkdir and pid write).
usage_token_reclaim_dead() {
    local dir h sid pid at now
    dir=$(usage_token_dir)
    h="$dir/holder"
    [ -d "$dir" ] || return 0
    now=$(date +%s)
    if [ -s "$h" ]; then
        read -r sid pid at < "$h"
    else
        sid=""; pid=""; at=""
    fi
    if [ -n "$pid" ]; then
        if ! kill -0 "$pid" 2>/dev/null; then
            rm -rf "$dir"
            usage_log "token-reclaimed sid=$sid"
        fi
        return 0
    fi
    case "$at" in ''|*[!0-9]*) at=0 ;; esac
    if [ "$at" -eq 0 ] || [ $(( now - at )) -gt 600 ]; then
        rm -rf "$dir"
        usage_log "token-reclaimed sid=$sid"
    fi
    return 0
}

usage_token_clear() {
    rm -rf "$(usage_token_dir)"
    return 0
}

# ------------------------------------------------------------ 2e delta log

# usage_delta_log <sid> <runner> <model> <json>
# One JSONL line per real change in (five, week) versus last/<sid>.
usage_delta_log() {
    local sid="${1:-}" runner="${2:-}" model="${3:-}" json="${4:-}"
    usage_valid_sid "$sid" || return 1
    local dir five week last_f pf pw
    dir=$(usage_state_dir)
    mkdir -p "$dir/last" 2>/dev/null
    five=$(printf '%s' "$json" | jq -r '.five_hour.utilization // 0')
    week=$(printf '%s' "$json" | jq -r '.seven_day.utilization // 0')
    last_f="$dir/last/$sid"
    if [ -s "$last_f" ]; then
        read -r pf pw < "$last_f"
        if [ "$pf" = "$five" ] && [ "$pw" = "$week" ]; then
            return 0
        fi
    else
        pf=$five
        pw=$week
    fi
    jq -n -c --argjson ts "$(date +%s)" --arg sid "$sid" --arg runner "$runner" \
        --arg model "${model:-unknown}" --argjson five "$five" --argjson week "$week" \
        --argjson pf "$pf" --argjson pw "$pw" \
        '{ts:$ts, session_id:$sid, runner:$runner, model:$model, five:$five, week:$week,
          d_five:($five-$pf), d_week:($week-$pw)}' >> "$dir/deltas.jsonl" 2>/dev/null
    printf '%s %s\n' "$five" "$week" > "$last_f"
    return 0
}

# ------------------------------------------------------------ 2f predicates

USAGE_LIMIT_HIT_RE='hit your [a-z0-9 -]*limit|usage limit reached|api error:[[:space:]]*429|rate_limit_error'
USAGE_ENV_ERROR_RE='hit your [a-z0-9 -]*limit|usage limit reached|api error:[[:space:]]*(401|403|429|529)|overloaded_error|rate_limit_error'

usage_log_matches() {  # $1=regex $2=file $3=after_line (0 = whole file)
    tail -n +"$(( ${3:-0} + 1 ))" "$2" 2>/dev/null | grep -qiE "$1"
}
usage_is_limit_hit() { usage_log_matches "$USAGE_LIMIT_HIT_RE" "$@"; }
usage_is_env_error() { usage_log_matches "$USAGE_ENV_ERROR_RE" "$@"; }

# ------------------------------------------------------------ 6a runner helpers

# usage_argv_save <sid> <args...>: persist the runner argv (JSON array).
usage_argv_save() {
    local sid="${1:-}"
    usage_valid_sid "$sid" || return 1
    shift
    local dir
    dir="$(usage_state_dir)/runs"
    mkdir -p "$dir" 2>/dev/null
    jq -n '$ARGS.positional' --args -- "$@" > "$dir/$sid.argv.tmp.$$" 2>/dev/null \
        && mv "$dir/$sid.argv.tmp.$$" "$dir/$sid.argv"
}

# usage_argv_load <sid>: argv NUL-separated. Read with `while IFS= read -r -d '' a`.
usage_argv_load() {
    usage_valid_sid "${1:-}" || return 1
    local f
    f="$(usage_state_dir)/runs/$1.argv"
    [ -s "$f" ] || return 1
    jq -j '.[] | . + "\u0000"' "$f"
}

# usage_session_started <sid>: 0 when claude has a transcript to --resume.
# IBL5_USAGE_CLAUDE_PROJECTS overrides the projects dir for tests.
usage_session_started() {
    usage_valid_sid "${1:-}" || return 1
    local proj="${IBL5_USAGE_CLAUDE_PROJECTS:-$HOME/.claude/projects}"
    compgen -G "$proj/*/$1.jsonl" >/dev/null 2>&1
}

# usage_prestart_gate <runner> <sid> <resume_bin>
# rc 0: start. rc 10: paused before start (marker written).
# No usable usage data fails open.
usage_prestart_gate() {
    local runner="${1:-}" sid="${2:-}" rbin="${3:-}" body rc zone w
    body=$(usage_fetch 45); rc=$?
    if [ "$rc" -eq 1 ] || [ -z "$body" ]; then
        usage_log "prestart-fail-open reason=no-usage runner=$runner"
        return 0
    fi
    if [ "$rc" -eq 2 ]; then
        local age
        age=$(usage_cache_age)
        if [ "$age" -lt 0 ] || [ "$age" -ge 300 ]; then
            usage_log "prestart-fail-open reason=stale-usage runner=$runner"
            return 0
        fi
    fi
    zone=$(usage_zone "$body")
    [ "$zone" = "normal" ] && return 0
    [ "$(usage_token_holder)" = "$sid" ] && return 0
    w=$(usage_limiting_window "$body")
    if usage_marker_write "$sid" "$runner" "$rbin" usage-pause "$zone" \
        "$(usage_zone_pct "$body")" "$w" "$(usage_resets_epoch "$body" "$w")"; then
        usage_log "prestart-pause runner=$runner sid=$sid zone=$zone"
        return 10
    fi
    usage_log "prestart-fail-open reason=marker-write-failed runner=$runner"
    return 0
}

# usage_postrun_pause <runner> <sid> <resume_bin> <rc> <log> [after_line]
# rc 0 (paused): a marker for <sid> exists, or <rc> is not 0/3/124/143 and the log
# shows a limit-hit (a limit-hit marker is written). rc 1: not paused.
# <rc> = "any" skips the rc filter, for callers whose status is not claude's own exit.
usage_postrun_pause() {
    local runner="${1:-}" sid="${2:-}" rbin="${3:-}" rc="${4:-0}" log="${5:-}" after="${6:-0}"
    if usage_marker_exists "$sid"; then
        return 0
    fi
    case "$rc" in 0|3|124|143) return 1 ;; any) ;; esac
    usage_is_limit_hit "$log" "$after" || return 1
    local body zone="unknown" pct=0 w="five_hour" resets=""
    if body=$(usage_fetch 300) && [ -n "$body" ]; then
        zone=$(usage_zone "$body")
        pct=$(usage_zone_pct "$body")
        w=$(usage_limiting_window "$body")
        resets=$(usage_resets_epoch "$body" "$w")
    fi
    usage_marker_write "$sid" "$runner" "$rbin" limit-hit "$zone" "$pct" "$w" "$resets" || return 1
    usage_log "postrun-limit-hit runner=$runner sid=$sid rc=$rc"
    return 0
}

# usage_resume_paused_args <runner> <sid>: saved argv NUL-separated; rc 2 + stderr
# reason for an invalid sid, no marker, a runner mismatch, or a missing argv file.
usage_resume_paused_args() {
    local runner="${1:-}" sid="${2:-}" mr
    if ! usage_valid_sid "$sid"; then
        echo "--resume-paused: invalid session id" >&2
        return 2
    fi
    if ! usage_marker_exists "$sid"; then
        echo "--resume-paused: no pause marker for $sid" >&2
        return 2
    fi
    mr=$(usage_marker_read "$sid" | jq -r '.runner // empty')
    if [ "$mr" != "$runner" ]; then
        echo "--resume-paused: marker belongs to '$mr', not '$runner'" >&2
        return 2
    fi
    if ! usage_argv_load "$sid"; then
        echo "--resume-paused: no saved argv for $sid" >&2
        return 2
    fi
    return 0
}

# ------------------------------------------------------------ 3b hook decision

# usage_gate_decide
# Reads the PreToolUse hook input JSON on stdin. Prints nothing (allow) or exactly
# {"continue":false,"stopReason":"usage-pause"}. Always returns 0. Invariant: no
# pause output without a marker on disk, and any missing context fails open.
#
# Env contract (exported by runners alongside IBL5_USAGE_GATE=1):
#   IBL5_USAGE_GATE_RUNNER, IBL5_USAGE_GATE_MODEL, IBL5_USAGE_GATE_RESUME_BIN
usage_gate_decide() {
    local input sid runner="${IBL5_USAGE_GATE_RUNNER:-}" rbin="${IBL5_USAGE_GATE_RESUME_BIN:-}"
    input=$(cat)
    sid=$(printf '%s' "$input" | jq -r '.session_id // empty' 2>/dev/null)
    if ! usage_valid_sid "$sid"; then
        usage_log "fail-open reason=bad-sid"
        return 0
    fi
    if ! usage_runner_priority "$runner" >/dev/null; then
        usage_log "fail-open reason=no-runner-context"
        return 0
    fi
    case "$rbin" in
        /*) ;;
        *) usage_log "fail-open reason=no-runner-context"; return 0 ;;
    esac
    if [ ! -x "$rbin" ]; then
        usage_log "fail-open reason=no-runner-context"
        return 0
    fi

    local body rc age
    body=$(usage_fetch 45); rc=$?
    if [ "$rc" -eq 1 ] || [ -z "$body" ]; then
        usage_log "fail-open reason=no-usage"
        return 0
    fi
    if [ "$rc" -eq 2 ]; then
        age=$(usage_cache_age)
        if [ "$age" -lt 0 ] || [ "$age" -ge 300 ]; then
            usage_log "fail-open reason=stale-usage age=$age"
            return 0
        fi
    fi

    usage_delta_log "$sid" "$runner" "${IBL5_USAGE_GATE_MODEL:-unknown}" "$body"

    local zone
    zone=$(usage_zone "$body")
    case "$zone" in
        normal) return 0 ;;
        drain)
            [ "$(usage_token_holder)" = "$sid" ] && return 0
            ;;
        stop)
            if [ "$(usage_token_holder)" = "$sid" ]; then
                usage_token_release "$sid"
            fi
            ;;
        *) return 0 ;;
    esac

    local w
    w=$(usage_limiting_window "$body")
    if usage_marker_write "$sid" "$runner" "$rbin" usage-pause "$zone" \
        "$(usage_zone_pct "$body")" "$w" "$(usage_resets_epoch "$body" "$w")"; then
        printf '%s\n' '{"continue":false,"stopReason":"usage-pause"}'
        usage_log "pause sid=$sid zone=$zone"
    else
        usage_log "fail-open reason=marker-write-failed"
    fi
    return 0
}
