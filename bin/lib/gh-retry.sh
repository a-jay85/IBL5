# shellcheck shell=bash
#
# gh_retry — rerun a `gh` call when it fails for a transient reason.
#
# Sourced by bin/lib/burndown.sh and bin/merge-line-select. Never executed.
# Defines two functions and nothing else: no top-level `set`, no `exit`, no
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
#      connection reset, connection refused      -> retry after the next delay
#   4. anything else (including silent non-zero) -> fail at once
#
# Return codes: the final attempt's exit code, unchanged; 64 for a bad
# IBL5_GH_RETRY_DELAYS; 1 if a temp file cannot be created. Every attempt's
# stderr is replayed to stderr. Only the final attempt's stdout reaches stdout.

# _gh_retry_is_transient <stderr-file>: 0 = retry, 1 = do not retry.
_gh_retry_is_transient() {
  # A 4xx is a caller error. It never retries, even when a timeout word also appears.
  if grep -qE 'HTTP 4[0-9][0-9]' "$1"; then
    return 1
  fi
  grep -qiE 'HTTP 5[0-9][0-9]|Bad Gateway|Service Unavailable|Gateway Time-?out|timed out|timeout|connection reset|connection refused' "$1"
}

# gh_retry <cmd> [args...]
gh_retry() {
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
    if [ "$rc" -eq 0 ] || [ "$attempt" -ge "$max" ] || ! _gh_retry_is_transient "$err"; then
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
