#!/usr/bin/env bash
# Shared plan-usage fetcher with a persistent cache. Sourced by the usage-gate hook,
# the coordinator, every runner, and the interactive UserPromptSubmit hook.
#
# The cache lives under the project state dir (not /tmp) so the launchd coordinator,
# which has no keychain access, can read usage an interactive or headless session
# fetched. Freshness comes from the fetched_at field, never file mtime, because
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

# usage_state_dir
# Echoes the state dir (creating it and markers/). Honors IBL5_USAGE_GATE_STATE_DIR;
# an empty value falls back to the default.
usage_state_dir() {
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
# keychain is unreadable (the launchd coordinator). Never the token itself.
_usage_cred_fp() {
    local tok
    tok=$(security find-generic-password -s "Claude Code-credentials" -w 2>/dev/null \
        | jq -r '.claudeAiOauth.accessToken // empty' 2>/dev/null)
    [ -n "$tok" ] || return 0
    printf '%s' "$tok" | shasum -a 256 2>/dev/null | cut -c1-16
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

# _usage_fetch_backoff_arm <state_dir> <retry_after|"">: writes the backoff file, echoes the delay.
_usage_fetch_backoff_arm() {
    local f="$1/fetch-backoff" ra="$2" bo s delay i now
    bo=$(_usage_fetch_backoff_read "$f"); s=${bo#* }
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
            usage_log "cred-switch cache-dropped"
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
            _usage_fetch_lock_release "$d"
            printf '%s' "$body" | jq 'del(.fetched_at, .cred_fp)'
            return 0
        fi
        rm -f "$tmp"
        reason="bad-body"
    fi

    if [ "$reason" = "rate-limited" ]; then
        local delay
        delay=$(_usage_fetch_backoff_arm "$d" "$ra")
        usage_log "fetch-failed reason=rate-limited retry_after=${ra:-none} backoff=$delay"
    else
        usage_log "fetch-failed reason=$reason"
    fi
    _usage_fetch_lock_release "$d"
    _usage_fetch_serve_stale "$age" "$cache"
}
