#!/usr/bin/env bash
# bin/lib/launchd-sched.sh — sourced library; defines scheduled-LaunchAgent
# helpers only: plist fragment emitters, main-checkout derivation, install and
# uninstall. Shared by the bin/*-cron-setup family.
#
# Bash 3.2 / macOS compatible.  No set options, no traps, no subprocess on
# source — lib must not alter the caller's shell state.
#
# Callers: . "$REPO_ROOT/bin/lib/launchd-sched.sh"
#   bin/wt-gc-cron-setup, bin/wt-sync-cron-setup, bin/db-sync-cron-setup,
#   bin/sim-recap-cron-setup, bin/pr-cycle-tick-cron-setup,
#   bin/automouse/*-digest-cron-setup, bin/backups-sync-setup,
#   bin/bug-pipeline-cron-setup
#
# Every emitter writes with printf, tab indentation and one trailing newline
# per line. Values are interpolated raw (never XML-escaped) so the output stays
# byte-identical to the hand-written plists this library replaced.

[[ -n "${_LSCHED_SOURCED:-}" ]] && return 0
_LSCHED_SOURCED=1

# shellcheck source=bin/lib/launchd-job.sh
. "$(dirname "${BASH_SOURCE[0]}")/launchd-job.sh"

# lsched_main_wt <repo_root> — print the durable MAIN checkout: the first
# `worktree` entry of `git worktree list`, else <repo_root> when git cannot
# answer (not in a git tree). Always returns 0. Split across two statements
# deliberately: outside a git tree `git worktree list` exits 128 and pipefail
# would abort a caller before the fallback ran.
lsched_main_wt() {
    local root="$1" wt
    wt="$(git -C "$root" worktree list --porcelain 2>/dev/null \
        | awk '/^worktree / && !f {print $2; f=1}' || true)"
    [ -n "$wt" ] || wt="$root"   # fallback: not in a git tree
    printf '%s\n' "$wt"
}

# _lsched_indent <depth> — print <depth> tab characters (no newline).
_lsched_indent() {
    local n="${1:-1}" i=0
    while [ "$i" -lt "$n" ]; do printf '\t'; i=$((i + 1)); done
}

# lsched_key_string <key> <value> [depth]
lsched_key_string() {
    local d="${3:-1}"
    _lsched_indent "$d"; printf '<key>%s</key>\n' "$1"
    _lsched_indent "$d"; printf '<string>%s</string>\n' "$2"
}

# lsched_key_integer <key> <int> [depth]
lsched_key_integer() {
    local d="${3:-1}"
    _lsched_indent "$d"; printf '<key>%s</key>\n' "$1"
    _lsched_indent "$d"; printf '<integer>%s</integer>\n' "$2"
}

# lsched_key_bool <key> true|false [depth]
lsched_key_bool() {
    local d="${3:-1}"
    case "$2" in
        true|false) ;;
        *) printf 'lsched_key_bool: value must be true or false, got: %s\n' "$2" >&2; return 2 ;;
    esac
    _lsched_indent "$d"; printf '<key>%s</key>\n' "$1"
    _lsched_indent "$d"; printf '<%s/>\n' "$2"
}

# lsched_program_arguments <arg>...
lsched_program_arguments() {
    if [ "$#" -eq 0 ]; then
        printf 'lsched_program_arguments: at least one argument required\n' >&2
        return 2
    fi
    local a
    printf '\t<key>ProgramArguments</key>\n\t<array>\n'
    for a in "$@"; do printf '\t\t<string>%s</string>\n' "$a"; done
    printf '\t</array>\n'
}

# lsched_calendar <Key=int>... — StartCalendarInterval dict, args in order.
lsched_calendar() {
    local a
    if [ "$#" -eq 0 ]; then
        printf 'lsched_calendar: at least one Key=int argument required\n' >&2
        return 2
    fi
    for a in "$@"; do
        case "$a" in
            *=*) ;;
            *) printf 'lsched_calendar: expected Key=int, got: %s\n' "$a" >&2; return 2 ;;
        esac
    done
    printf '\t<key>StartCalendarInterval</key>\n\t<dict>\n'
    for a in "$@"; do
        printf '\t\t<key>%s</key>\n\t\t<integer>%s</integer>\n' "${a%%=*}" "${a#*=}"
    done
    printf '\t</dict>\n'
}

# lsched_env_open / lsched_env_close — EnvironmentVariables dict at depth 1.
lsched_env_open() { printf '\t<key>EnvironmentVariables</key>\n\t<dict>\n'; }
lsched_env_close() { printf '\t</dict>\n'; }

# lsched_plist <body_fn> — prolog, then the body function's output, then footer.
lsched_plist() {
    printf '%s\n' '<?xml version="1.0" encoding="UTF-8"?>'
    printf '%s\n' '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">'
    printf '%s\n' '<plist version="1.0">'
    printf '%s\n' '<dict>'
    "$1"
    printf '%s\n' '</dict>'
    printf '%s\n' '</plist>'
}

# lsched_install <label> <plist_path> <body_fn> [dir]...
# mkdir -p the plist dir and each extra dir, write the plist, then bootout +
# bootstrap via ljob_bootstrap. Returns bootstrap's status. Call as a bare
# statement so the caller's `set -e` aborts on a failed write.
lsched_install() {
    local label="$1" plist_path="$2" body_fn="$3"
    shift 3
    mkdir -p "$(dirname "$plist_path")" "$@" || return $?
    lsched_plist "$body_fn" > "$plist_path" || return $?
    ljob_bootstrap "$label" "$plist_path"
}

# lsched_uninstall <label> <plist_path> — idempotent; always returns 0.
lsched_uninstall() {
    launchctl bootout "gui/$(id -u)/$1" 2>/dev/null || true  # idempotent
    rm -f "$2"                                                # idempotent
    return 0
}
