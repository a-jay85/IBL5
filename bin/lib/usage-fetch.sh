#!/usr/bin/env bash
# Shared plan-usage fetcher with a persistent cache. Sourced by the usage-gate hook,
# the coordinator, every runner, and the interactive UserPromptSubmit hook.
#
# The cache lives under the project state dir (not /tmp) so the launchd coordinator
# can read usage an interactive or headless session fetched, even when its own keychain
# read fails (the --probe-keychain check in usage-gate-cron-setup is one-shot only).
# Freshness comes from the fetched_at field, never file mtime, because
# `stat -f %m` (BSD) breaks on the Linux CI runner. No `date -d` / `date -v`.
#
# A shared 429 backoff file and a mkdir fetch lock in the state dir keep concurrent
# callers to one live request.
#
# Bash 3.2 / macOS compatible. Sourced library: no `main`, no `set -e`.

[ -n "${_USAGE_FETCH_SH:-}" ] && return 0
_USAGE_FETCH_SH=1

# Fetch backoff and single-flight tunables (seconds). Plain assignments on purpose:
# no environment override, so tests drive expiry by seeding the state files.
USAGE_FETCH_LOCK_STALE=15
USAGE_FETCH_BACKOFF_BASE=30
USAGE_FETCH_BACKOFF_CAP=480
USAGE_FETCH_RETRY_AFTER_CAP=900
USAGE_FETCH_SKIPLOG_EVERY=30
# A 429 episode stays live until USAGE_FETCH_EPISODE_QUIET seconds pass with no 429.
# 1800 = 2 x USAGE_FETCH_RETRY_AFTER_CAP: the longest armed wait plus as much again for caller cadence.
USAGE_FETCH_EPISODE_QUIET=1800
# Account-switch fast path: the switch signal stays active for USAGE_SWITCH_WINDOW;
# within it, at most USAGE_SWITCH_RETRY_MAX 429s under the new fingerprint back off
# for min(retry_after, USAGE_SWITCH_RETRY_CAP) instead of the normal arm.
USAGE_SWITCH_WINDOW=1800
USAGE_SWITCH_RETRY_CAP=45
USAGE_SWITCH_RETRY_MAX=3

# usage_state_dir
# Echoes the state dir (creating it and markers/). Honors IBL5_USAGE_GATE_STATE_DIR;
# an empty value falls back to the default.
# Test mode (IBL5_USAGE_GATE_TEST_MODE non-empty; unset or empty = off): an unset/empty
# IBL5_USAGE_GATE_STATE_DIR, or one equal to the default real path, is refused. The
# function then warns on stderr, prints an unwritable sink path, and returns 1, so every
# later write fails and the gate fails open. Callers use $(usage_state_dir) and ignore
# rc, so the sink must never be an empty string.
usage_state_dir() {
    if [ -n "${IBL5_USAGE_GATE_TEST_MODE:-}" ]; then
        local real="${HOME:-}/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/usage-gate"
        local want="${IBL5_USAGE_GATE_STATE_DIR:-}"
        if [ -z "$want" ] || [ "${want%/}" = "$real" ]; then
            echo "USAGE-GATE-TEST: refusing the real state dir $real (set IBL5_USAGE_GATE_STATE_DIR to a temp dir)" >&2
            printf '%s\n' "/dev/null/usage-gate-refused"
            return 1
        fi
    fi
    local d="${IBL5_USAGE_GATE_STATE_DIR:-$HOME/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/usage-gate}"
    mkdir -p "$d/markers" 2>/dev/null
    printf '%s\n' "$d"
}

# usage_log <msg...>
# Appends "<ISO-UTC> <pid> <msg>" to usage-gate.log. Never fails the caller.
usage_log() {
    local d
    d=$(usage_state_dir)
    printf '%s %s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$$" "$*" >> "$d/usage-gate.log" 2>/dev/null
    return 0
}

usage_cache_path() {
    printf '%s/cache.json\n' "$(usage_state_dir)"
}

# _usage_cred_fp
# Echoes a short sha256 of the keychain OAuth access token, or nothing when the
# keychain is unreadable (possible under launchd). Never the token itself. A token
# refresh changes it too, so switch detection pairs it with _usage_acct_fp.
_usage_cred_fp() {
    local tok
    [ -n "${IBL5_USAGE_GATE_TEST_MODE:-}" ] && return 0
    tok=$(security find-generic-password -s "Claude Code-credentials" -w 2>/dev/null \
        | jq -r '.claudeAiOauth.accessToken // empty' 2>/dev/null)
    [ -n "$tok" ] || return 0
    printf '%s' "$tok" | shasum -a 256 2>/dev/null | cut -c1-16
}

# _usage_acct_fp: short sha256 of ~/.claude.json .oauthAccount.accountUuid, or nothing.
# The account id survives a token refresh and changes on /login to another account.
# Plain file, so no keychain needed. Never logged or stored raw.
_usage_acct_fp() {
    local u
    [ -n "${HOME:-}" ] || return 0
    u=$(jq -r '.oauthAccount.accountUuid // empty' "$HOME/.claude.json" 2>/dev/null)
    [ -n "$u" ] || return 0
    printf '%s' "$u" | shasum -a 256 2>/dev/null | cut -c1-16
}

# _usage_switch_signal <state_dir> <fp|-> <kind>: records a detected login switch.
_usage_switch_signal() {
    local f="$1/cred-switch"
    { printf '%s %s 0 %s\n' "$(date +%s)" "${2:--}" "$3" > "$f.tmp.$$" && mv "$f.tmp.$$" "$f"; } 2>/dev/null \
        || rm -f "$f.tmp.$$"
    usage_log "cred-switch signal kind=$3"
}

# usage_switch_active [now]: echoes the switch epoch while a cred-switch signal younger
# than USAGE_SWITCH_WINDOW exists; echoes nothing otherwise.
usage_switch_active() {
    local f at rest now
    f="$(usage_state_dir)/cred-switch"
    [ -f "$f" ] || return 0
    now="${1:-$(date +%s)}"
    read -r at rest < "$f" 2>/dev/null
    case "$at" in ''|*[!0-9]*) return 0 ;; esac
    if [ "$now" -ge "$at" ] && [ $((now - at)) -lt "$USAGE_SWITCH_WINDOW" ]; then
        printf '%s\n' "$at"
    fi
    return 0
}

# _usage_switch_short_retry <state_dir> <fp> <retry_after|"">: on a 429 inside an active
# switch window, under the switch's fingerprint (or "-"), with short retries left, bumps
# the count and echoes "<capped_retry_after> <count>". Returns 1 for the normal arm.
_usage_switch_short_retry() {
    local f="$1/cred-switch" fp="$2" ra="$3" at sfp n kind now
    [ -f "$f" ] || return 1
    read -r at sfp n kind < "$f" 2>/dev/null
    case "$at" in ''|*[!0-9]*) return 1 ;; esac
    case "$n" in ''|*[!0-9]*) n=0 ;; esac
    now=$(date +%s)
    [ "$now" -ge "$at" ] && [ $((now - at)) -lt "$USAGE_SWITCH_WINDOW" ] || return 1
    [ "$sfp" = "-" ] || [ "$sfp" = "$fp" ] || return 1
    [ "$n" -lt "$USAGE_SWITCH_RETRY_MAX" ] || return 1
    case "$ra" in ''|*[!0-9]*) ra=0 ;; esac
    if [ "$ra" -eq 0 ] || [ "$ra" -gt "$USAGE_SWITCH_RETRY_CAP" ]; then ra=$USAGE_SWITCH_RETRY_CAP; fi
    n=$((n + 1))
    { printf '%s %s %s %s\n' "$at" "$sfp" "$n" "${kind:--}" > "$f.tmp.$$" && mv "$f.tmp.$$" "$f"; } 2>/dev/null \
        || rm -f "$f.tmp.$$"
    printf '%s %s\n' "$ra" "$n"
}

# usage_account_switch_check: keychain-free detection for the coordinator. When the
# cached reading was fetched under another account than ~/.claude.json now names,
# drop the cache and backoff (both belong to the old account) and signal the switch.
# An unknown account on either side does nothing.
usage_account_switch_check() {
    local d old="" new
    d=$(usage_state_dir)
    [ -s "$d/cache.json" ] || return 0
    [ -f "$d/acct-last" ] && read -r old < "$d/acct-last" 2>/dev/null
    [ -n "$old" ] || return 0
    new=$(_usage_acct_fp)
    [ -n "$new" ] && [ "$new" != "$old" ] || return 0
    rm -f "$d/cache.json" "$d/fetch-backoff" "$d/fetch-backoff.logged" "$d"/fetch-backoff.logged.*
    _usage_fetch_episode_end "$d"
    usage_log "account-switch cache-dropped"
    _usage_switch_signal "$d" "-" account
}

# usage_cache_age
# Echoes now - .fetched_at, or -1 when the cache is missing or unparseable.
usage_cache_age() {
    local c fa
    c=$(usage_cache_path)
    [ -s "$c" ] || { echo -1; return 0; }
    fa=$(jq -r '.fetched_at // empty' "$c" 2>/dev/null)
    case "$fa" in
        ''|*[!0-9]*) echo -1; return 0 ;;
    esac
    echo $(( $(date +%s) - fa ))
}

# _usage_fetch_backoff_read <file>: echoes "<until> <step>", "0 0" when absent or garbled.
_usage_fetch_backoff_read() {
    local u="" s=""
    [ -f "$1" ] && read -r u s < "$1" 2>/dev/null
    case "$u" in ''|*[!0-9]*) u=0 ;; esac
    case "$s" in ''|*[!0-9]*) s=0 ;; esac
    printf '%s %s\n' "$u" "$s"
}

# _usage_fetch_backoff_arm <state_dir> <retry_after|""> [<min_step>]: writes the backoff file,
# echoes the delay. The doubling step is the larger of the file's step and min_step.
_usage_fetch_backoff_arm() {
    local f="$1/fetch-backoff" ra="$2" bo s delay i now
    bo=$(_usage_fetch_backoff_read "$f"); s=${bo#* }
    case "${3:-}" in ''|*[!0-9]*) ;; *) [ "$3" -gt "$s" ] && s=$3 ;; esac
    case "$ra" in ''|*[!0-9]*) ra="" ;; esac
    [ -n "$ra" ] && [ "$ra" -eq 0 ] && ra=""
    if [ -n "$ra" ]; then
        delay=$ra
        [ "$delay" -gt "$USAGE_FETCH_RETRY_AFTER_CAP" ] && delay=$USAGE_FETCH_RETRY_AFTER_CAP
    else
        delay=$USAGE_FETCH_BACKOFF_BASE; i=0
        while [ "$i" -lt "$s" ] && [ "$delay" -lt "$USAGE_FETCH_BACKOFF_CAP" ]; do
            delay=$((delay * 2)); i=$((i + 1))
        done
        [ "$delay" -gt "$USAGE_FETCH_BACKOFF_CAP" ] && delay=$USAGE_FETCH_BACKOFF_CAP
    fi
    now=$(date +%s)
    { printf '%s %s\n' "$((now + delay))" "$((s + 1))" > "$f.tmp.$$" && mv "$f.tmp.$$" "$f"; } 2>/dev/null \
        || rm -f "$f.tmp.$$"
    echo "$delay"
}

# _usage_fetch_episode_read <file>: echoes "<start> <last_429> <attempts>", "0 0 0" when absent or garbled.
_usage_fetch_episode_read() {
    local a="" l="" n=""
    [ -f "$1" ] && read -r a l n < "$1" 2>/dev/null
    case "$a" in ''|*[!0-9]*) a=0 ;; esac
    case "$l" in ''|*[!0-9]*) l=0 ;; esac
    case "$n" in ''|*[!0-9]*) n=0 ;; esac
    printf '%s %s %s\n' "$a" "$l" "$n"
}

# _usage_fetch_episode_end <state_dir> [stale]: ends the 429 episode and logs its summary once.
# With "stale", ends it only when no 429 landed in the last USAGE_FETCH_EPISODE_QUIET seconds.
# The rename claims the file, so exactly one process logs the end, with or without the fetch lock.
_usage_fetch_episode_end() {
    local f="$1/fetch-429-episode" a l n
    [ -f "$f" ] || return 0
    if [ "${2:-}" = stale ]; then
        read -r a l n <<< "$(_usage_fetch_episode_read "$f")"
        [ $(( $(date +%s) - l )) -ge "$USAGE_FETCH_EPISODE_QUIET" ] || return 0
    fi
    mv "$f" "$f.end.$$" 2>/dev/null || return 0
    read -r a l n <<< "$(_usage_fetch_episode_read "$f.end.$$")"
    rm -f "$f.end.$$"
    if [ "$n" -gt 0 ] && [ "$a" -gt 0 ] && [ "$l" -ge "$a" ]; then
        usage_log "429-episode end duration=$((l - a)) attempts=$n"
    fi
    return 0
}

# _usage_fetch_episode_429 <state_dir>: records one normal-arm 429. Ends a stale episode first.
# Echoes "<prior_attempts> new|cont". The caller holds the fetch lock.
_usage_fetch_episode_429() {
    local f="$1/fetch-429-episode" a l n now
    _usage_fetch_episode_end "$1" stale
    now=$(date +%s)
    read -r a l n <<< "$(_usage_fetch_episode_read "$f")"
    if [ "$n" -eq 0 ] || [ "$a" -eq 0 ]; then a=$now; n=0; fi
    { printf '%s %s %s\n' "$a" "$now" "$((n + 1))" > "$f.tmp.$$" && mv "$f.tmp.$$" "$f"; } 2>/dev/null \
        || rm -f "$f.tmp.$$"
    if [ "$n" -eq 0 ]; then echo "0 new"; else echo "$n cont"; fi
}

# _usage_fetch_log_once <marker_file> <key> <msg...>: logs only when key differs from the last logged key.
_usage_fetch_log_once() {
    local f="$1" key="$2" last="" old
    shift 2
    [ -f "$f" ] && read -r last < "$f" 2>/dev/null
    [ "$last" = "$key" ] && return 0
    # Claim the key atomically (O_EXCL) so concurrent callers log it once, not once each.
    ( set -C; : > "$f.$key" ) 2>/dev/null || return 0
    { printf '%s\n' "$key" > "$f"; } 2>/dev/null
    for old in "$f".*; do
        [ "$old" = "$f.$key" ] || rm -f "$old"
    done
    usage_log "$@"
}

# _usage_fetch_lock_acquire <state_dir>: rc 0 when this process now owns fetch.lock.d. Never waits.
_usage_fetch_lock_acquire() {
    local lock="$1/fetch.lock.d" now at="" pid="" got=""
    now=$(date +%s)
    if mkdir "$lock" 2>/dev/null; then
        { printf '%s %s\n' "$now" "$$" > "$lock/at"; } 2>/dev/null
        return 0
    fi
    if [ ! -f "$lock/at" ]; then
        # Orphan from a holder that died between mkdir and its stamp: stamp it so it ages out.
        # noclobber (O_EXCL): never overwrite the real holder's stamp, or its release can't match its pid.
        ( set -C; printf '%s 0\n' "$now" > "$lock/at" ) 2>/dev/null
        return 1
    fi
    read -r at pid < "$lock/at" 2>/dev/null
    case "$at" in ''|*[!0-9]*) at=$now ;; esac
    [ $((now - at)) -gt "$USAGE_FETCH_LOCK_STALE" ] || return 1
    mv "$lock" "$lock.stale.$$" 2>/dev/null || return 1
    read -r got pid < "$lock.stale.$$/at" 2>/dev/null
    case "$got" in ''|*[!0-9]*) got=0 ;; esac
    if [ $((now - got)) -le "$USAGE_FETCH_LOCK_STALE" ]; then
        # A peer reclaimed first and this mv took its fresh lock: hand it back and lose.
        if [ -e "$lock" ]; then rm -rf "$lock.stale.$$"; else mv "$lock.stale.$$" "$lock" 2>/dev/null; fi
        return 1
    fi
    rm -rf "$lock.stale.$$"
    mkdir "$lock" 2>/dev/null || return 1
    { printf '%s %s\n' "$now" "$$" > "$lock/at"; } 2>/dev/null
    return 0
}

# _usage_fetch_lock_release <state_dir>: removes fetch.lock.d only when this process owns it.
_usage_fetch_lock_release() {
    local lock="$1/fetch.lock.d" at="" pid=""
    read -r at pid < "$lock/at" 2>/dev/null
    [ "$pid" = "$$" ] && rm -rf "$lock"
    return 0
}

# _usage_fetch_serve_stale <age> <cache>: rc 2 with the cached body, or rc 1 when there is none.
_usage_fetch_serve_stale() {
    if [ "$1" -ge 0 ] && [ -s "$2" ]; then
        jq 'del(.fetched_at, .cred_fp)' "$2" 2>/dev/null
        return 2
    fi
    return 1
}

# usage_fetch <max_age_seconds>
# stdout = usage JSON body (without fetched_at).
# rc 0: fresh (cache hit within max_age, or live fetch succeeded)
# rc 2: no fresh reading (live fetch failed, a 429 backoff is active, or another
#       process holds the fetch lock); stale cache printed (caller decides)
# rc 1: nothing usable; prints nothing
usage_fetch() {
    local max_age="${1:-}"
    case "$max_age" in
        ''|*[!0-9]*)
            echo "usage_fetch: max_age must be an integer" >&2
            return 1
            ;;
    esac

    local cache age fp cfp
    cache=$(usage_cache_path)
    age=$(usage_cache_age)
    # A login switch (`/login` to another account) changes the token. The cached
    # reading and any 429 backoff belong to the old account, so drop both. A cache
    # with no cred_fp, or a caller that cannot read the keychain, keeps today's path.
    fp=$(_usage_cred_fp)
    if [ -n "$fp" ] && [ "$age" -ge 0 ]; then
        cfp=$(jq -r '.cred_fp // empty' "$cache" 2>/dev/null)
        if [ -n "$cfp" ] && [ "$cfp" != "$fp" ]; then
            local sd
            sd=$(usage_state_dir)
            rm -f "$cache" "$sd/fetch-backoff" "$sd/fetch-backoff.logged" "$sd"/fetch-backoff.logged.*
            _usage_fetch_episode_end "$sd"
            usage_log "cred-switch cache-dropped"
            local aold="" anew
            [ -f "$sd/acct-last" ] && read -r aold < "$sd/acct-last" 2>/dev/null
            anew=$(_usage_acct_fp)
            if [ -n "$aold" ] && [ "$aold" = "$anew" ]; then
                usage_log "cred-switch same-account"
            elif [ -n "$aold" ] && [ -n "$anew" ]; then
                _usage_switch_signal "$sd" "$fp" account
            else
                _usage_switch_signal "$sd" "$fp" token
            fi
            age=-1
        fi
    fi
    if [ "$age" -ge 0 ] && [ "$age" -lt "$max_age" ]; then
        jq 'del(.fetched_at, .cred_fp)' "$cache" 2>/dev/null && return 0
    fi

    local d now bo bo_until
    d=$(usage_state_dir)
    now=$(date +%s)
    bo=$(_usage_fetch_backoff_read "$d/fetch-backoff"); bo_until=${bo% *}
    if [ "$now" -lt "$bo_until" ]; then
        _usage_fetch_log_once "$d/fetch-backoff.logged" "$bo_until" "backoff-skip until=$bo_until"
        _usage_fetch_serve_stale "$age" "$cache"; return $?
    fi

    # Test mode never reads the keychain or calls the usage API. A fresh seeded cache was
    # already served above; anything else serves stale (rc 2) or nothing (rc 1).
    if [ -n "${IBL5_USAGE_GATE_TEST_MODE:-}" ]; then
        usage_log "fetch-skipped reason=test-mode"
        _usage_fetch_serve_stale "$age" "$cache"; return $?
    fi

    if ! _usage_fetch_lock_acquire "$d"; then
        _usage_fetch_log_once "$d/fetch-inflight.logged" "$((now / USAGE_FETCH_SKIPLOG_EVERY))" \
            "fetch-skip reason=in-flight"
        _usage_fetch_serve_stale "$age" "$cache"; return $?
    fi

    # Re-check under the lock: a peer may have refreshed the cache or armed a backoff.
    age=$(usage_cache_age)
    if [ "$age" -ge 0 ] && [ "$age" -lt "$max_age" ]; then
        _usage_fetch_lock_release "$d"
        jq 'del(.fetched_at, .cred_fp)' "$cache" 2>/dev/null
        return 0
    fi
    bo=$(_usage_fetch_backoff_read "$d/fetch-backoff"); bo_until=${bo% *}
    if [ "$(date +%s)" -lt "$bo_until" ]; then
        _usage_fetch_lock_release "$d"
        _usage_fetch_serve_stale "$age" "$cache"; return $?
    fi

    local reason="" tok="" body="" hdr="$d/fetch.hdr.$$" crc=0 status="" ra=""
    tok=$(security find-generic-password -s "Claude Code-credentials" -w 2>/dev/null \
        | jq -r '.claudeAiOauth.accessToken // empty' 2>/dev/null)
    if [ -z "$tok" ]; then
        reason="no-token"
    else
        rm -f "$hdr"
        body=$(curl -s -m 4 -D "$hdr" https://api.anthropic.com/api/oauth/usage \
            -H "Authorization: Bearer $tok" -H "anthropic-beta: oauth-2025-04-20"); crc=$?
        tok=""
        status=$({ tr -d '\r' < "$hdr"; } 2>/dev/null | awk '/^HTTP\//{s=$2} END{print s}')
        ra=$({ tr -d '\r' < "$hdr"; } 2>/dev/null \
            | awk 'tolower($0) ~ /^retry-after:/{sub(/^[^:]*:[ \t]*/, ""); r=$0} END{print r}')
        rm -f "$hdr"
        case "$status" in [0-9][0-9][0-9]) ;; *) status="" ;; esac
        if [ "$status" = 429 ]; then
            reason="rate-limited"
        elif [ -n "$status" ] && [ "$status" != 200 ]; then
            reason="http-$status"
        elif ! printf '%s' "$body" | jq -e '.five_hour.utilization' >/dev/null 2>&1; then
            if [ "$crc" -ne 0 ] && [ -z "$status" ]; then reason="curl-exit-$crc"; else reason="bad-body"; fi
        fi
    fi

    if [ -z "$reason" ]; then
        local tmp="$cache.tmp.$$"
        if printf '%s' "$body" | jq --argjson t "$(date +%s)" --arg fp "$fp" '. + {fetched_at:$t, cred_fp:$fp}' > "$tmp" 2>/dev/null \
            && mv "$tmp" "$cache"; then
            rm -f "$d/fetch-backoff" "$d/fetch-backoff.logged" "$d"/fetch-backoff.logged.*
            _usage_fetch_episode_end "$d" stale
            local acct
            acct=$(_usage_acct_fp)
            if [ -n "$acct" ]; then printf '%s\n' "$acct" > "$d/acct-last" 2>/dev/null; else rm -f "$d/acct-last"; fi
            _usage_fetch_lock_release "$d"
            printf '%s' "$body" | jq 'del(.fetched_at, .cred_fp)'
            return 0
        fi
        rm -f "$tmp"
        reason="bad-body"
    fi

    if [ "$reason" = "rate-limited" ]; then
        local delay sr ep
        if sr=$(_usage_switch_short_retry "$d" "$fp" "$ra"); then
            delay=$(_usage_fetch_backoff_arm "$d" "${sr% *}")
            usage_log "fetch-failed reason=rate-limited retry_after=${ra:-none} backoff=$delay switch-retry=${sr#* }/$USAGE_SWITCH_RETRY_MAX"
        else
            ep=$(_usage_fetch_episode_429 "$d")
            delay=$(_usage_fetch_backoff_arm "$d" "$ra" "${ep% *}")
            [ "${ep#* }" = new ] && usage_log "429-episode start retry_after=${ra:-none} backoff=$delay"
        fi
    else
        usage_log "fetch-failed reason=$reason"
    fi
    _usage_fetch_lock_release "$d"
    _usage_fetch_serve_stale "$age" "$cache"
}
