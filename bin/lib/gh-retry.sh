# shellcheck shell=bash
#
# gh_retry — rerun a `gh` call when it fails for a transient reason.
#
# Sourced by bin/lib/burndown.sh, bin/merge-line-select, bin/settle-pr-status and bin/check-pr-checks-green. Never executed.
# Defines five functions and nothing else: no top-level `set`, no `exit`, no
# top-level commands. It reports through return codes only, so it is safe under
# a caller's `set -euo pipefail` with PATH=/usr/bin:/bin on bash 3.2 and 5.
#
#   gh_retry <cmd> [args...]
#
# Env var IBL5_GH_RETRY_DELAYS: space-separated non-negative integers, the
# sleeps between attempts. Default "5 20 60" (1 initial attempt + 3 retries).
# Unset and empty both select the default. Whitespace-only or any non-integer
# token returns 64 and never runs the command.
#
# Classification order (stderr of the failed attempt):
#   1. exit 0                                    -> done, return 0
#   2. `HTTP 4xx` present                        -> fail at once (never retried)
#   3. 5xx, Bad Gateway, Service Unavailable,
#      Gateway Time-out, timeout, timed out,
#      connection reset, connection refused,
#      unexpected end of JSON input (a truncated
#      body gh could not parse)                  -> retry after the next delay
#   4. anything else (including silent non-zero) -> fail at once
#
# Return codes: the final attempt's exit code, unchanged; 64 for a bad
# IBL5_GH_RETRY_DELAYS; 1 if a temp file cannot be created. Every attempt's
# stderr is replayed to stderr. Only the final attempt's stdout reaches stdout.
#
#   gh_retry_json <cmd> [args...]   (read mode, for idempotent GETs only)
#
# Same IBL5_GH_RETRY_DELAYS schedule and validation (64 on a bad value).
# Classification per attempt:
#   1. exit 0 and the body parses as JSON (jq -e 'true')  -> done, return 0
#   2. exit 0 and the body is empty or not JSON           -> retry
#   3. non-zero and stderr names HTTP 4xx other than 429
#      (a 403 that says "rate limit" counts as 429)       -> fail at once
#   4. any other non-zero (5xx, 429, rate-limit 403, network error,
#      silent non-zero with no stderr)                    -> retry
# Exhaustion: the last attempt's non-zero exit code, or 65 when the last
# attempt exited 0 with an empty or non-JSON body (stdout is then empty).
# Returns 1 without running the command when jq is not on PATH.
# Never use it for a POST that reads its payload from stdin: a retry would
# rerun the command with stdin already consumed.

# _gh_retry_is_transient <stderr-file>: 0 = retry, 1 = do not retry.
_gh_retry_is_transient() {
  # A 4xx is a caller error. It never retries, even when a timeout word also appears.
  if grep -qE 'HTTP 4[0-9][0-9]' "$1"; then
    return 1
  fi
  grep -qiE 'HTTP 5[0-9][0-9]|Bad Gateway|Service Unavailable|Gateway Time-?out|timed out|timeout|connection reset|connection refused|unexpected end of JSON input' "$1"
}

# _gh_retry_is_hard_4xx <stderr-file>: 0 when stderr names an HTTP 4xx other than 429.
# A 403 that mentions a rate limit is GitHub's secondary rate limit, so it retries.
_gh_retry_is_hard_4xx() {
  if grep -qE 'HTTP 403' "$1" && grep -qiE 'rate limit' "$1"; then
    return 1
  fi
  grep -qE 'HTTP 4([01][0-9]|2[0-8]|[3-9][0-9])' "$1"
}

# _gh_retry_run <strict|json> <cmd> [args...]: the one retry loop.
_gh_retry_run() {
  local mode="$1"
  shift
  if [ "$mode" = json ] && ! command -v jq >/dev/null 2>&1; then
    printf 'gh-retry: jq not found on PATH; read mode cannot validate the body\n' >&2
    return 1
  fi
  local delays="${IBL5_GH_RETRY_DELAYS:-5 20 60}"
  local -a sched
  read -r -a sched <<< "$delays"
  if [ "${#sched[@]}" -eq 0 ]; then
    printf 'gh-retry: IBL5_GH_RETRY_DELAYS must be space-separated non-negative integers, got: %s\n' "'$delays'" >&2
    return 64
  fi
  local d
  for d in "${sched[@]}"; do
    case "$d" in
      ''|*[!0-9]*)
        printf 'gh-retry: IBL5_GH_RETRY_DELAYS must be space-separated non-negative integers, got: %s\n' "'$delays'" >&2
        return 64 ;;
    esac
  done
  local max=$(( ${#sched[@]} + 1 )) attempt=1 rc out err
  out=$(mktemp "${TMPDIR:-/tmp}/gh-retry.out.XXXXXX") || return 1
  err=$(mktemp "${TMPDIR:-/tmp}/gh-retry.err.XXXXXX") || { rm -f "$out"; return 1; }
  while :; do
    rc=0
    "$@" >"$out" 2>"$err" || rc=$?
    cat "$err" >&2
    if [ "$mode" = strict ]; then
      if [ "$rc" -eq 0 ] || [ "$attempt" -ge "$max" ] || ! _gh_retry_is_transient "$err"; then
        cat "$out"
        rm -f "$out" "$err"
        return "$rc"
      fi
    elif [ "$rc" -eq 0 ]; then
      if jq -e 'true' <"$out" >/dev/null 2>&1; then
        cat "$out"
        rm -f "$out" "$err"
        return 0
      fi
      printf 'gh-retry: attempt %d/%d returned an empty or non-JSON body\n' "$attempt" "$max" >&2
      if [ "$attempt" -ge "$max" ]; then
        rm -f "$out" "$err"
        return 65
      fi
    elif [ "$attempt" -ge "$max" ] || _gh_retry_is_hard_4xx "$err"; then
      cat "$out"
      rm -f "$out" "$err"
      return "$rc"
    fi
    d="${sched[$((attempt - 1))]}"
    printf 'gh-retry: attempt %d/%d failed (transient): sleeping %ss\n' "$attempt" "$max" "$d" >&2
    sleep "$d"
    attempt=$((attempt + 1))
  done
}

# gh_retry <cmd> [args...]: strict mode (see the classification table above).
gh_retry() { _gh_retry_run strict "$@"; }

# gh_retry_json <cmd> [args...]: read mode (see the read-mode table above).
gh_retry_json() { _gh_retry_run json "$@"; }
