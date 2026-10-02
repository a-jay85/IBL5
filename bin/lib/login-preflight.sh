#!/usr/bin/env bash
# Login preflight for the automouse run loop: a zero-token login probe plus the
# park-and-alert decision. Parks only on a positive "expired" signal and fails open
# (loudly) on anything inconclusive.
#
# Bash 3.2 / macOS compatible. Sourced library: functions only, no top-level side
# effects, no `set -e`. No line here echoes, logs, or writes the OAuth token or the
# keychain JSON.

[ -n "${_LOGIN_PREFLIGHT_SH:-}" ] && return 0
_LOGIN_PREFLIGHT_SH=1

# login_probe
# Prints exactly one verdict line and returns 0:
#   ok | expired:<reason> | unknown:<reason>
# Test seam: AUTOMOUSE_LOGIN_PROBE_CMD, when set AND non-empty, replaces the real probe.
login_probe() {
    local out
    if [ -n "${AUTOMOUSE_LOGIN_PROBE_CMD:-}" ]; then
        # shellcheck disable=SC2086
        out=$($AUTOMOUSE_LOGIN_PROBE_CMD 2>/dev/null | head -1)
        if printf '%s\n' "$out" | grep -Eq '^(ok|expired:[a-z0-9-]+|unknown:[a-z0-9-]+)$'; then
            printf '%s\n' "$out"
        else
            echo "unknown:bad-probe-output"
        fi
        return 0
    fi

    local unknown="" claude_bin="${LOGIN_PROBE_CLAUDE_BIN:-claude}"
    local sec_bin="${LOGIN_PROBE_SECURITY_BIN:-security}"
    local curl_bin="${LOGIN_PROBE_CURL_BIN:-curl}"

    # Tier 1: local `claude auth status`. Positive signal only.
    if command -v "$claude_bin" >/dev/null 2>&1; then
        local st rc
        st=$("$claude_bin" auth status --json 2>/dev/null); rc=$?
        if [ "$rc" -eq 1 ] && printf '%s' "$st" | grep -q '"loggedIn":[[:space:]]*false'; then
            echo "expired:not-logged-in"
            return 0
        fi
    else
        unknown="unknown:claude-missing"
    fi

    # Tier 2: keychain record. Only the two expiry numbers are ever held in a variable.
    local exp_line="" refresh_ms="" access_ms="" now_ms
    if command -v "$sec_bin" >/dev/null 2>&1; then
        local raw rcs sec_rc jq_rc
        # The last line carries the two pipeline exit codes; the lines before it hold
        # only the two expiry numbers.
        raw=$("$sec_bin" find-generic-password -s "Claude Code-credentials" -w 2>/dev/null \
            | jq -r '[(.claudeAiOauth.refreshTokenExpiresAt // empty), (.claudeAiOauth.expiresAt // empty)] | map(tostring) | join(" ")' 2>/dev/null
            echo "rc ${PIPESTATUS[0]} ${PIPESTATUS[1]}")
        rcs=$(printf '%s\n' "$raw" | tail -1)
        sec_rc=$(printf '%s' "$rcs" | cut -d' ' -f2)
        jq_rc=$(printf '%s' "$rcs" | cut -d' ' -f3)
        if [ "$sec_rc" != "0" ]; then
            [ -n "$unknown" ] || unknown="unknown:keychain-unreadable"
        elif [ "$jq_rc" != "0" ]; then
            echo "unknown:bad-json"
            return 0
        else
            exp_line=$(printf '%s\n' "$raw" | sed '$d' | head -1)
            if [ -z "$exp_line" ]; then
                echo "unknown:bad-json"
                return 0
            fi
        fi
    else
        [ -n "$unknown" ] || unknown="unknown:keychain-unreadable"
    fi

    if [ -n "$exp_line" ]; then
        refresh_ms=${exp_line%% *}
        access_ms=""
        case "$exp_line" in *" "*) access_ms=${exp_line#* } ;; esac
        case "$refresh_ms" in
            ''|*[!0-9]*)
                echo "unknown:bad-json"
                return 0
                ;;
        esac
        now_ms=$(( $(date +%s) * 1000 ))
        if [ "$refresh_ms" -le "$now_ms" ]; then
            echo "expired:refresh-token-expired"
            return 0
        fi

        # Tier 3: usage endpoint, only while the access token is unexpired so a 401
        # cannot just mean "needs refresh".
        case "$access_ms" in ''|*[!0-9]*) access_ms=0 ;; esac
        if [ "$access_ms" -gt "$now_ms" ]; then
            local _lp_x="" tok code
            case $- in *x*) _lp_x=1 ;; esac
            { set +x; } 2>/dev/null
            tok=$("$sec_bin" find-generic-password -s "Claude Code-credentials" -w 2>/dev/null \
                | jq -r '.claudeAiOauth.accessToken // empty' 2>/dev/null)
            if [ -n "$tok" ]; then
                code=$(printf 'Authorization: Bearer %s\n' "$tok" \
                    | "$curl_bin" -s -o /dev/null -w '%{http_code}' -m 4 -H @- \
                        -H 'anthropic-beta: oauth-2025-04-20' \
                        https://api.anthropic.com/api/oauth/usage 2>/dev/null)
            else
                code=""
            fi
            unset tok
            [ -n "$_lp_x" ] && set -x
            case "$code" in
                401|403) echo "expired:usage-endpoint-$code"; return 0 ;;
                200) echo "ok"; return 0 ;;
                000|""|*[!0-9]*) echo "unknown:usage-endpoint-timeout"; return 0 ;;
                *) echo "unknown:usage-endpoint-$code"; return 0 ;;
            esac
        fi
        # Refresh token valid, access token expired: refreshable, not an outage.
        echo "ok"
        return 0
    fi

    echo "${unknown:-unknown:keychain-unreadable}"
    return 0
}

# login_preflight <nightly_dir> <log_file> <discord_dm_bin>
# Returns 0 = proceed, 1 = park. One DM per outage, deduped by an O_EXCL marker.
login_preflight() {
    local nightly_dir=$1 log_file=$2 dm_bin=$3
    local state="$nightly_dir/login-expired.alerted"
    local reports="$nightly_dir/reports" verdict reason msg day
    mkdir -p "$reports"
    day=$(date +%F)
    verdict=$(login_probe)

    case "$verdict" in
        ok)
            if [ -e "$state" ]; then
                rm -f "$state"
                echo "login-preflight: login ok; outage marker cleared (alerts re-armed)" >> "$log_file"
            fi
            return 0
            ;;
        expired:*)
            reason=${verdict#expired:}
            if ( set -C; printf '%s %s\n' "$(date +%s)" "$reason" > "$state" ) 2>/dev/null; then
                msg="automouse parked: Claude login expired ($reason). Queue left intact, no attempt used. Run 'claude auth login' on the Mac; the next automouse launch resumes."
                printf '%s' "$msg" | "$dm_bin" - --attempts 3 --quiet \
                    || echo "login-preflight: DM undeliverable (spooled)" >> "$log_file"
                echo "login-preflight: login expired ($reason); alert sent, parking" >> "$log_file"
            else
                echo "login-preflight: login still expired ($reason); alert already sent this outage, parking" >> "$log_file"
            fi
            {
                printf 'Automouse parked: Claude login expired (%s).\n\n' "$reason"
                printf -- '- The queue is intact and no attempt was used.\n'
                printf -- "- Resume: run 'claude auth login' on the Mac. The next automouse launch probes again and continues.\n"
            } > "$reports/$day-login-expired.md"
            return 1
            ;;
        *)
            reason=${verdict#unknown:}
            echo "login-preflight: WARNING probe inconclusive ($reason); failing open, item will start" >> "$log_file"
            {
                printf 'Automouse login probe inconclusive (%s).\n\n' "$reason"
                printf -- '- The item started anyway (fail open).\n'
                printf -- '- The existing env-stop breaker remains the backstop if the login really is gone.\n'
            } > "$reports/$day-login-probe-inconclusive.md"
            return 0
            ;;
    esac
}
