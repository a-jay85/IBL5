#!/usr/bin/env bash
# Login preflight for automouse and plan-now: a near-zero-cost login probe plus the
# park-and-alert decision. Parks only on a positive "expired" signal and fails open
# (loudly) on anything inconclusive.
# Tier 3 spends one haiku call (about $0.0004) to prove the session works server-side.
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
        if grep -Eq '^(ok|expired:[a-z0-9-]+|unknown:[a-z0-9-]+)$' <<< "$out"; then
            printf '%s\n' "$out"
        else
            echo "unknown:bad-probe-output"
        fi
        return 0
    fi

    local unknown="" claude_bin="${LOGIN_PROBE_CLAUDE_BIN:-claude}"
    local sec_bin="${LOGIN_PROBE_SECURITY_BIN:-security}"

    # Tier 1: local `claude auth status`. Positive signal only.
    if command -v "$claude_bin" >/dev/null 2>&1; then
        local st rc
        st=$("$claude_bin" auth status --json 2>/dev/null); rc=$?
        if [ "$rc" -eq 1 ] && grep -q '"loggedIn":[[:space:]]*false' <<< "$st"; then
            echo "expired:not-logged-in"
            return 0
        fi
    else
        unknown="unknown:claude-missing"
    fi

    # Tier 2: keychain refresh timestamp. Positive signal only: a past timestamp parks
    # without spending Tier 3. Any read or parse failure falls through to Tier 3.
    # Only the one expiry number is ever held in a variable. One keychain read total.
    if command -v "$sec_bin" >/dev/null 2>&1; then
        local refresh_ms now_ms
        refresh_ms=$("$sec_bin" find-generic-password -s "Claude Code-credentials" -w 2>/dev/null \
            | jq -r '.claudeAiOauth.refreshTokenExpiresAt // empty' 2>/dev/null)
        case "$refresh_ms" in
            ''|*[!0-9]*) ;;
            *)
                now_ms=$(( $(date +%s) * 1000 ))
                if [ "$refresh_ms" -le "$now_ms" ]; then
                    echo "expired:refresh-token-expired"
                    return 0
                fi
                ;;
        esac
    fi

    # Tier 3 needs the binary. Without it nothing can run a session either.
    if [ -n "$unknown" ]; then
        echo "$unknown"
        return 0
    fi
    login_session_check "$claude_bin"
    return 0
}

# login_session_check <claude_bin>
# Tier 3: one minimal headless model call. It proves the session is usable NOW
# (an expired access token is refreshed exactly as a real run would). It cannot
# prove a refresh that only becomes due mid-run will succeed.
# Runs from / with --setting-sources project so no user or repo hooks load.
login_session_check() {
    local claude_bin=$1 perl_bin="${LOGIN_PROBE_PERL_BIN:-/usr/bin/perl}"
    local t="${LOGIN_PROBE_TIMEOUT_S:-}" out rc
    case "$t" in ''|*[!0-9]*|0) t=30 ;; esac
    if [ ! -x "$perl_bin" ]; then
        # Never run the call unbounded: a dead proxy hangs it forever.
        echo "unknown:session-no-timeout"
        return 0
    fi
    out=$(cd / && CLAUDE_HEADLESS=1 "$perl_bin" -e 'alarm shift; exec @ARGV or exit 127' "$t" \
        "$claude_bin" -p "reply with the single word ok" --model haiku --max-turns 1 \
        --no-session-persistence --tools "" --setting-sources project \
        --output-format json < /dev/null 2>/dev/null); rc=$?
    _lp_classify_session "$rc" "$out"
}

# _lp_classify_session <rc> <json>
# expired:* ONLY for a definite auth failure with is_error true. Everything else
# (429, overloaded, timeout, usage limit, junk) is unknown:* and fails open.
_lp_classify_session() {
    local rc=$1 out=$2 is_err status result
    if [ "$rc" -eq 142 ]; then echo "unknown:session-timeout"; return 0; fi
    if [ -z "$out" ]; then echo "unknown:session-no-output"; return 0; fi
    is_err=$(jq -r 'if type == "object" then (.is_error | tostring) else "x" end' <<< "$out" 2>/dev/null | tail -1)
    case "$is_err" in
        false)
            if [ "$rc" -eq 0 ]; then echo "ok"; else echo "unknown:session-rc-$rc"; fi
            return 0 ;;
        true) ;;
        *) echo "unknown:session-bad-output"; return 0 ;;
    esac
    status=$(jq -r '(.api_error_status // "") | tostring' <<< "$out" 2>/dev/null)
    result=$(jq -r '(.result // "") | tostring' <<< "$out" 2>/dev/null)
    case "$status" in
        429|5[0-9][0-9]) echo "unknown:session-$status"; return 0 ;;
        401) echo "expired:session-401"; return 0 ;;
    esac
    case "$result" in
        *"OAuth session expired"*|*"could not be refreshed"*) echo "expired:session-refresh-failed" ;;
        *"Not logged in"*) echo "expired:session-not-logged-in" ;;
        *"Invalid bearer token"*) echo "expired:session-401" ;;
        *"Failed to authenticate"*)
            if [ "$status" = 403 ]; then echo "expired:session-403"; else echo "unknown:session-auth-unclear"; fi ;;
        *)
            case "$status" in ''|*[!0-9]*) echo "unknown:session-error" ;; *) echo "unknown:session-$status" ;; esac ;;
    esac
}

# login_preflight <nightly_dir> <log_file> <discord_dm_bin> [caller] [resume_hint]
# caller "plan-now" sends no DM (plan-now DMs its own result); any other value, or none, is automouse.
# Returns 0 = proceed, 1 = park. One DM per outage, deduped by an O_EXCL marker.
login_preflight() {
    local nightly_dir=$1 log_file=$2 dm_bin=$3 caller=${4:-automouse} resume_hint=${5:-}
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
                if [ "$caller" = plan-now ]; then
                    echo "login-preflight: login expired ($reason); plan-now parking, its own result DM is the alert" >> "$log_file"
                else
                    msg="automouse parked: Claude login expired ($reason). Queue left intact, no attempt used. Run 'claude auth login' on the Mac; the next automouse launch resumes."
                    printf '%s' "$msg" | "$dm_bin" - --attempts 3 --quiet \
                        || echo "login-preflight: DM undeliverable (spooled)" >> "$log_file"
                    echo "login-preflight: login expired ($reason); alert sent, parking" >> "$log_file"
                fi
            else
                echo "login-preflight: login still expired ($reason); alert already sent this outage, parking" >> "$log_file"
            fi
            {
                if [ "$caller" = plan-now ]; then
                    printf 'plan-now parked: Claude login expired (%s).\n\n' "$reason"
                    printf -- '- No model call was made, so nothing was spent.\n'
                    printf -- "- Resume: run 'claude auth login' on the Mac, then %s\n" "${resume_hint:-re-run bin/plan-now}"
                else
                    printf 'Automouse parked: Claude login expired (%s).\n\n' "$reason"
                    printf -- '- The queue is intact and no attempt was used.\n'
                    printf -- "- Resume: run 'claude auth login' on the Mac. The next automouse launch probes again and continues.\n"
                fi
            } > "$reports/$day-login-expired.md"
            return 1
            ;;
        *)
            reason=${verdict#unknown:}
            echo "login-preflight: WARNING probe inconclusive ($reason); failing open, item will start" >> "$log_file"
            {
                if [ "$caller" = plan-now ]; then
                    printf 'plan-now login probe inconclusive (%s).\n\n' "$reason"
                    printf -- '- The plan-now run started anyway (fail open).\n'
                else
                    printf 'Automouse login probe inconclusive (%s).\n\n' "$reason"
                    printf -- '- The item started anyway (fail open).\n'
                    printf -- '- The existing env-stop breaker remains the backstop if the login really is gone.\n'
                fi
            } > "$reports/$day-login-probe-inconclusive.md"
            return 0
            ;;
    esac
}
