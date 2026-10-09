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

# ------------------------------------------------------------ 2c weekly pacing (bin/burndown-loop)

# usage_pace_reserve
# Echoes the weekly reserve percent kept free at seven_day.resets_at. Unset and
# empty IBL5_BURNDOWN_RESERVE_PCT both take the default 10. A non-integer or a
# value above 99 logs bad-reserve and also takes 10 (99 keeps the divisor > 0).
usage_pace_reserve() {
    local r="${IBL5_BURNDOWN_RESERVE_PCT:-10}"
    case "$r" in ''|*[!0-9]*) usage_log "bad-reserve value=$r"; echo 10; return 0 ;; esac
    if [ "$r" -gt 99 ]; then usage_log "bad-reserve value=$r"; echo 10; return 0; fi
    echo "$r"
}

# usage_pace_verdict <json> <now_epoch>
# One line: "<verdict> <weekly_pct|-> <allowed_pct|-> <wait_seconds>".
#   go    weekly pct < allowed; wait 0.
#   wait  weekly pct >= allowed; wait = seconds until allowed reaches pct, or until
#         seven_day.resets_at when pct >= 100-reserve. Floor 60.
#   zone  usage_zone (max of both windows) is drain or stop; wait 0 (caller defers
#         to usage_prestart_gate, which writes the pause marker).
#   blind seven_day.utilization or seven_day.resets_at missing/unparseable, or a
#         bad now; wait 0 (caller sleeps its own cap). Fails closed.
# allowed = (100 - reserve) * (168 - hours_left) / 168, hours_left clamped to [0,168].
usage_pace_verdict() {
    local body="${1:-}" now="${2:-}" r zone pct resets out
    case "$now" in ''|*[!0-9]*) echo "blind - - 0"; return 0 ;; esac
    r=$(usage_pace_reserve)
    zone=$(usage_zone "$body" 2>/dev/null)
    pct=$(printf '%s' "$body" | jq -r '.seven_day.utilization // empty | numbers' 2>/dev/null)
    case "$zone" in drain|stop) echo "zone ${pct:--} - 0"; return 0 ;; esac
    [ -n "$pct" ] || { echo "blind - - 0"; return 0; }
    resets=$(usage_resets_epoch "$body" seven_day)
    case "$resets" in ''|*[!0-9]*) echo "blind $pct - 0"; return 0 ;; esac
    out=$(jq -nr --argjson p "$pct" --argjson r "$r" --argjson now "$now" --argjson rs "$resets" '
        (($rs - $now) / 3600) as $hl
        | (if $hl < 0 then 0 elif $hl > 168 then 168 else $hl end) as $h
        | ((100 - $r) * (168 - $h) / 168) as $a
        | ((($a * 10) | round) / 10) as $ar
        | if $p < $a then "go \($p) \($ar) 0"
          elif $p >= (100 - $r) then "wait \($p) \($ar) \([($rs - $now), 60] | max | floor)"
          else "wait \($p) \($ar) \([((($p * 168 / (100 - $r)) - (168 - $h)) * 3600), 60] | max | ceil)"
          end' 2>/dev/null)
    [ -n "$out" ] || { echo "blind $pct - 0"; return 0; }
    echo "$out"
}

# usage_blind_trust <json> <age> [log-suffix]
# Called when the gate is blind: the live fetch failed and the cache is stale.
# rc 0: trust this last reading as if fresh. Its zone is drain or stop and the
# limiting window's resets_at is still in the future. Logs blind-trust.
# rc 1: fail open. The zone is normal, or resets_at is passed, missing, or
# unparseable, so the reset boundary cannot be shown to lie ahead.
usage_blind_trust() {
    local body="${1:-}" age="${2:-}" zone w resets now
    zone=$(usage_zone "$body")
    case "$zone" in drain|stop) ;; *) return 1 ;; esac
    w=$(usage_limiting_window "$body")
    resets=$(usage_resets_epoch "$body" "$w")
    case "$resets" in ''|*[!0-9]*) return 1 ;; esac
    now=$(date +%s)
    [ "$resets" -gt "$now" ] || return 1
    usage_log "blind-trust zone=$zone age=$age${3:+ $3}"
    return 0
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
        burndown-loop) echo 4 ;;
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
    local fp
    fp=$(_usage_cred_fp)
    local acct
    acct=$(_usage_acct_fp)
    if jq -n --arg fp "$fp" --arg acct "$acct" --arg sid "$sid" --arg runner "$runner" --argjson prio "$prio" \
        --arg rbin "$rbin" --arg cwd "$cwd" --arg reason "$reason" --arg zone "$zone" \
        --argjson pct "$pct" --arg window "$window" --arg resets "$resets" \
        --argjson now "$(date +%s)" --argjson rc "$prev_count" \
        '{session_id:$sid, runner:$runner, priority:$prio,
          resume_argv:[$rbin, "--resume-paused", $sid], resume_cwd:$cwd,
          reason:$reason, zone:$zone, pct:$pct, window:$window,
          resets_at:(if $resets == "" then null else ($resets|tonumber) end),
          paused_at:$now, resume_count:$rc,
          acct_fp:(if $acct == "" then null else $acct end),
          cred_fp:(if $fp == "" then null else $fp end)}' 2>/dev/null > "$tmp" \
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
        # grep -v exits 1 when no line survives (sid was the only entry); callers
        # run under set -e, so that must not abort them.
        grep -vxF "$1" "$seen" > "$tmp" 2>/dev/null || true
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

# usage_marker_cred_switched <sid>: 0 only when the marker and the current login both have a fingerprint and they differ.
usage_marker_cred_switched() {
    local sid="${1:-}" f marker_fp cur_fp
    usage_valid_sid "$sid" || return 1
    f="$(usage_markers_dir)/$sid.json"
    [ -s "$f" ] || return 1
    marker_fp=$(jq -r '.cred_fp // empty' "$f" 2>/dev/null)
    [ -n "$marker_fp" ] || return 1
    cur_fp=$(_usage_cred_fp)
    [ -n "$cur_fp" ] || return 1
    [ "$marker_fp" != "$cur_fp" ]
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
    # The 600 s hold is per-account backoff. After a /login switch the new account
    # has its own budget, so the hold is skipped; the zone check still gates resume.
    if [ "$reason" = "limit-hit" ] && [ $(( now - paused_at )) -lt 600 ]; then
        if usage_marker_cred_switched "$sid"; then
            usage_log "limit-hit-hold-skipped reason=cred-switch sid=$sid"
        else
            return 1
        fi
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
            if ! usage_blind_trust "$body" "$age" "runner=$runner"; then
                usage_log "prestart-fail-open reason=stale-usage runner=$runner"
                return 0
            fi
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

# usage_limit_hit_marker <runner> <sid> <resume_bin>
# Writes a limit-hit marker for <sid> from the current usage reading (zone "unknown" when
# no reading is available). Returns the marker write's status. Callers that have already
# matched the limit text themselves use it directly; usage_postrun_pause matches the log
# first.
usage_limit_hit_marker() {
    local runner="${1:-}" sid="${2:-}" rbin="${3:-}"
    local body zone="unknown" pct=0 w="five_hour" resets=""
    if body=$(usage_fetch 300) && [ -n "$body" ]; then
        zone=$(usage_zone "$body")
        pct=$(usage_zone_pct "$body")
        w=$(usage_limiting_window "$body")
        resets=$(usage_resets_epoch "$body" "$w")
    fi
    usage_marker_write "$sid" "$runner" "$rbin" limit-hit "$zone" "$pct" "$w" "$resets"
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
    usage_limit_hit_marker "$runner" "$sid" "$rbin" || return 1
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

# usage_gate_run_sid <hook_sid>: the sid a pause is keyed on. Unset
# IBL5_USAGE_GATE_SESSION_ID keeps the hook's own sid (skill leg: already the
# run sid). Set-but-empty or not a uuid returns 1 so the caller fails open; a
# child-keyed marker would have no runs/<sid>.argv and would strand.
usage_gate_run_sid() {
    if [ -z "${IBL5_USAGE_GATE_SESSION_ID+x}" ]; then
        printf '%s' "${1:-}"
        return 0
    fi
    usage_valid_sid "$IBL5_USAGE_GATE_SESSION_ID" || return 1
    printf '%s' "$IBL5_USAGE_GATE_SESSION_ID"
}

# usage_gate_decide
# Reads the PreToolUse hook input JSON on stdin. Prints nothing (allow) or exactly
# {"continue":false,"stopReason":"usage-pause"}. Always returns 0. Invariant: no
# pause output without a marker on disk, and any missing context fails open.
#
# Env contract (exported by runners alongside IBL5_USAGE_GATE=1):
#   IBL5_USAGE_GATE_RUNNER, IBL5_USAGE_GATE_MODEL, IBL5_USAGE_GATE_RESUME_BIN
#   IBL5_USAGE_GATE_SESSION_ID (optional): the run sid; keys the marker, token check, and delta row
usage_gate_decide() {
    local input sid runner="${IBL5_USAGE_GATE_RUNNER:-}" rbin="${IBL5_USAGE_GATE_RESUME_BIN:-}"
    input=$(cat)
    sid=$(printf '%s' "$input" | jq -r '.session_id // empty' 2>/dev/null)
    if ! sid=$(usage_gate_run_sid "$sid"); then
        usage_log "fail-open reason=bad-run-sid"
        return 0
    fi
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

    local body rc age blind=0
    body=$(usage_fetch 45); rc=$?
    if [ "$rc" -eq 1 ] || [ -z "$body" ]; then
        usage_log "fail-open reason=no-usage"
        return 0
    fi
    if [ "$rc" -eq 2 ]; then
        age=$(usage_cache_age)
        if [ "$age" -lt 0 ] || [ "$age" -ge 300 ]; then
            if usage_blind_trust "$body" "$age"; then
                blind=1
            else
                usage_log "fail-open reason=stale-usage age=$age"
                return 0
            fi
        fi
    fi

    # A blind reading is not a new delta: keep the delta log to fresh data.
    [ "$blind" -eq 1 ] || usage_delta_log "$sid" "$runner" "${IBL5_USAGE_GATE_MODEL:-unknown}" "$body"

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
