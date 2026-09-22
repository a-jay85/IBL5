#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/shell-scripts.sh — single source of truth for shell-script discovery.
# Follows the bin/lib/critical-files.sh single-source-of-truth convention.
#
# Usage (direct execution):
#   bin/lib/shell-scripts.sh --full    enumerate every shell script in the
#                                      three find roots; one path per line, sorted
#   bin/lib/shell-scripts.sh --filter  read newline-delimited paths from stdin;
#                                      emit only the shell scripts among them
#
# Usage (sourced):
#   source "$(git rev-parse --show-toplevel)/bin/lib/shell-scripts.sh"
#   shl_list_shell_scripts --full
#   shl_list_shell_scripts --filter
#
# Shebang patterns recognised:
#   #!/bin/sh  #!/bin/bash  #!/usr/bin/sh  #!/usr/bin/bash
#   #!/usr/bin/env sh  #!/usr/bin/env bash
#
# Bash 3.2 compatible (no mapfile, no declare -A, no ${var^^}).

# ── helpers ─────────────────────────────────────────────────────────────────

_shl_is_shell_script() {
    local f="$1"
    # .sh extension — no shebang check needed
    case "$f" in
        *.sh) return 0 ;;
    esac
    # shebang check: read first 40 bytes; pattern covers (/usr)?/bin and env forms
    head -c 40 "$f" 2>/dev/null \
        | grep -qE '^#!((/usr)?/bin/(ba)?sh|/usr/bin/env (ba)?sh)$'
}

_shl_full() {
    local repo_root
    repo_root="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
    local tmp_list
    tmp_list="$(mktemp)"
    # .sh files pass (extension alone is sufficient)
    find "$repo_root/bin" "$repo_root/ibl5/bin" \
         "$repo_root/.claude/skills/pr-ready/scripts" \
         -type f -name '*.sh' 2>/dev/null >> "$tmp_list" || true
    # non-.sh files pass (shebang check)
    local f
    find "$repo_root/bin" "$repo_root/ibl5/bin" \
         "$repo_root/.claude/skills/pr-ready/scripts" \
         -type f -not -name '*.sh' 2>/dev/null \
    | while IFS= read -r f; do
        if head -c 40 "$f" 2>/dev/null \
               | grep -qE '^#!((/usr)?/bin/(ba)?sh|/usr/bin/env (ba)?sh)$'; then
            echo "$f"
        fi
    done >> "$tmp_list" || true
    sort -u "$tmp_list"
    rm -f "$tmp_list"
}

_shl_filter() {
    local f
    while IFS= read -r f; do
        [ -z "$f" ] && continue
        _shl_is_shell_script "$f" && echo "$f"
    done
    return 0
}

# ── public function (for sourced use) ───────────────────────────────────────

shl_list_shell_scripts() {
    case "${1:-}" in
        --full)   _shl_full ;;
        --filter) _shl_filter ;;
        *)
            printf 'Usage: shl_list_shell_scripts --full | --filter\n' >&2
            return 1
            ;;
    esac
}

# ── direct execution ─────────────────────────────────────────────────────────

# Guard: only run main() when executed directly, not when sourced
if [ "${BASH_SOURCE[0]}" = "$0" ] || [ -z "${BASH_SOURCE[0]:-}" ]; then
    case "${1:-}" in
        --full)   _shl_full ;;
        --filter) _shl_filter ;;
        *)
            printf 'Usage: %s --full | --filter\n' "$(basename "$0")" >&2
            exit 1
            ;;
    esac
fi
