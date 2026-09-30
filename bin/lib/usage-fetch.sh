#!/usr/bin/env bash
# Shared plan-usage fetcher with a persistent cache. Sourced by the usage-gate hook,
# the coordinator, every runner, and the interactive UserPromptSubmit hook.
#
# The cache lives under the project state dir (not /tmp) so the launchd coordinator,
# which has no keychain access, can read usage an interactive or headless session
# fetched. Freshness comes from the fetched_at field, never file mtime, because
# `stat -f %m` (BSD) breaks on the Linux CI runner. No `date -d` / `date -v`.
#
# Bash 3.2 / macOS compatible. Sourced library: no `main`, no `set -e`.

[ -n "${_USAGE_FETCH_SH:-}" ] && return 0
_USAGE_FETCH_SH=1

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

# usage_fetch <max_age_seconds>
# stdout = usage JSON body (without fetched_at).
# rc 0: fresh (cache hit within max_age, or live fetch succeeded)
# rc 2: live fetch failed, stale cache printed (caller decides)
# rc 1: nothing usable; prints nothing
usage_fetch() {
    local max_age="${1:-}"
    case "$max_age" in
        ''|*[!0-9]*)
            echo "usage_fetch: max_age must be an integer" >&2
            return 1
            ;;
    esac

    local cache age
    cache=$(usage_cache_path)
    age=$(usage_cache_age)
    if [ "$age" -ge 0 ] && [ "$age" -lt "$max_age" ]; then
        jq 'del(.fetched_at)' "$cache" 2>/dev/null && return 0
    fi

    local reason="" tok="" body=""
    tok=$(security find-generic-password -s "Claude Code-credentials" -w 2>/dev/null \
        | jq -r '.claudeAiOauth.accessToken // empty' 2>/dev/null)
    if [ -z "$tok" ]; then
        reason="no-token"
    else
        body=$(curl -s -m 4 https://api.anthropic.com/api/oauth/usage \
            -H "Authorization: Bearer $tok" -H "anthropic-beta: oauth-2025-04-20")
        if ! printf '%s' "$body" | jq -e '.five_hour.utilization' >/dev/null 2>&1; then
            reason="bad-body"
        fi
    fi

    if [ -z "$reason" ]; then
        local tmp="$cache.tmp.$$"
        if printf '%s' "$body" | jq --argjson t "$(date +%s)" '. + {fetched_at:$t}' > "$tmp" 2>/dev/null \
            && mv "$tmp" "$cache"; then
            printf '%s' "$body" | jq 'del(.fetched_at)'
            return 0
        fi
        rm -f "$tmp"
        reason="bad-body"
    fi

    usage_log "fetch-failed reason=$reason"
    if [ "$age" -ge 0 ]; then
        jq 'del(.fetched_at)' "$cache" 2>/dev/null
        return 2
    fi
    return 1
}
