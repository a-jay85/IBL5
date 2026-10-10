#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/scan-scope.sh: shared scan scope for the diff-scoped gates
# bin/check-e2e-hygiene, bin/check-phpunit-hygiene and bin/check-hot-files.
# Owns the --pr/--base/--help arg loop, the base-ref resolution and the
# changed-file listing. Each gate keeps its own patterns and reporting.
#
# SOURCED ONLY: no top-level side effects; no `set -e` (safe under the
# caller's set -euo pipefail). Bash 3.2 / macOS compatible: no namerefs, no
# mapfile, no associative arrays, no indirect expansion. The caller passes an
# env var's NAME and VALUE explicitly so the lib never reads it indirectly.
#
# Globals set: SCAN_SCOPE_MODE (all|pr), SCAN_SCOPE_CLI_BASE,
# SCAN_SCOPE_BASE_REF, SCAN_SCOPE_FILES (array).

# scan_scope_parse_args <usage_fn> <strict|lenient> "$@"
# usage_fn must print help and exit 0. strict: an unknown arg prints
# "Unknown argument: X" to stderr and exits 2. lenient: unknown args are
# ignored (bin/check-hot-files' historical behavior). Makes no git call, so a
# caller may run it before resolving its repo root.
scan_scope_parse_args() {
    local usage_fn="$1" unknown="$2"
    shift 2
    SCAN_SCOPE_MODE="all"
    SCAN_SCOPE_CLI_BASE=""
    while [ "$#" -gt 0 ]; do
        case "$1" in
            --help|-h) "$usage_fn" ;;
            --pr) SCAN_SCOPE_MODE="pr" ;;
            --base)
                case "${2:-}" in
                    ''|-*) echo "--base requires a ref value" >&2; exit 2 ;;
                esac
                SCAN_SCOPE_CLI_BASE="$2"
                shift
                ;;
            --base=*)
                SCAN_SCOPE_CLI_BASE="${1#--base=}"
                case "$SCAN_SCOPE_CLI_BASE" in
                    ''|-*) echo "--base requires a ref value" >&2; exit 2 ;;
                esac
                ;;
            *)
                if [ "$unknown" = "strict" ]; then
                    echo "Unknown argument: $1" >&2
                    exit 2
                fi
                ;;
        esac
        shift
    done
    return 0
}

# scan_scope_resolve_base <env-var-name> <env-var-value> <validate: 1|0> <root>
# Sets SCAN_SCOPE_BASE_REF. Precedence: --base > env value > origin/master
# (an EMPTY env value also falls back). A --base ref is always checked in pr
# mode. The env value is checked only when validate=1, mode is pr and the
# value is non-empty. The origin/master default is never checked.
scan_scope_resolve_base() {
    local var_name="$1" var_value="$2" validate="$3" root="$4"
    if [ -n "${SCAN_SCOPE_CLI_BASE:-}" ]; then
        SCAN_SCOPE_BASE_REF="$SCAN_SCOPE_CLI_BASE"
        if [ "${SCAN_SCOPE_MODE:-all}" = "pr" ] \
          && ! git -C "$root" rev-parse --verify --quiet "${SCAN_SCOPE_BASE_REF}^{commit}" >/dev/null; then
            echo "--base ${SCAN_SCOPE_BASE_REF} does not resolve to a commit" >&2
            exit 2
        fi
        return 0
    fi
    SCAN_SCOPE_BASE_REF="${var_value:-origin/master}"
    if [ "$validate" = "1" ] && [ "${SCAN_SCOPE_MODE:-all}" = "pr" ] && [ -n "$var_value" ] \
      && ! git -C "$root" rev-parse --verify --quiet "${SCAN_SCOPE_BASE_REF}^{commit}" >/dev/null; then
        echo "${var_name}=${var_value} does not resolve to a commit" >&2
        exit 2
    fi
    return 0
}

# scan_scope_changed_files <git-dir> <pathspec>
# Prints changed paths vs SCAN_SCOPE_BASE_REF, root-relative. git's stderr
# is deliberately not redirected, so a bad ref stays as noisy as before.
scan_scope_changed_files() {
    git -C "$1" diff "$SCAN_SCOPE_BASE_REF" --name-only -- "$2"
}

# scan_scope_collect_files <root> <pr-pathspec> <find-dir> <find-name>
# Fills SCAN_SCOPE_FILES with absolute paths. pr mode: changed files that
# still exist, in git's order. all mode: find <dir> -name <name> | sort.
scan_scope_collect_files() {
    local root="$1" pathspec="$2" find_dir="$3" find_name="$4" f
    SCAN_SCOPE_FILES=()
    if [ "${SCAN_SCOPE_MODE:-all}" = "pr" ]; then
        while IFS= read -r f; do
            if [ -f "$root/$f" ]; then
                SCAN_SCOPE_FILES+=("$root/$f")
            fi
        done < <(scan_scope_changed_files "$root" "$pathspec")
    else
        while IFS= read -r f; do
            SCAN_SCOPE_FILES+=("$f")
        done < <(find "$find_dir" -name "$find_name" -type f | sort)
    fi
    return 0
}
