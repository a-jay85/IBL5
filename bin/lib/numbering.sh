# shellcheck shell=bash
# bin/lib/numbering.sh — shared ADR and migration numbering grammar (ADR-0110).
# Sourced by bin/next-number (allocation) and bin/check-numbering (collision keys).
# Source-safe: variables and functions only; no `set`, no `exit`, no output when sourced.
# numbering_next needs bin/lib/git-helpers.sh sourced first.
#
# Two grammars per kind, on purpose:
#   allocator NAME grammar  - which names count when computing the next free number
#   gate KEY grammar        - what two added files must share to collide
# ADR: name = ^[0-9]{4}-   key = ^[0-9]{4} (no hyphen). 0000 is never a number.
# Migration: name = ^[0-9]{1,3}[a-z]?_ then the digits only (letter suffix dropped);
#            key  = ^[0-9]{1,3}[a-z]?_ minus the `_` (letter suffix KEPT: 044b != 044).

NUMBERING_ADR_DIR="ibl5/docs/decisions"
NUMBERING_MIGRATION_DIR="ibl5/migrations"

numbering_dir() { # <kind>
    case "$1" in
        adr)       printf '%s\n' "$NUMBERING_ADR_DIR" ;;
        migration) printf '%s\n' "$NUMBERING_MIGRATION_DIR" ;;
        *)         return 1 ;;
    esac
    return 0
}

# Gate collision key for a BASENAME, or nothing when it is not numbered.
numbering_adr_key() { # <basename>
    case "$1" in
        0000-*) return 0 ;;
    esac
    printf '%s\n' "$1" | grep -oE '^[0-9]{4}' || true
    return 0
}

numbering_migration_key() { # <basename>
    printf '%s\n' "$1" | grep -oE '^[0-9]{1,3}[a-z]?_' | sed 's/_$//' || true
    return 0
}

# Max number from a stream of BASENAMES on stdin, or nothing.
numbering_max_from_names() { # <kind>
    case "$1" in
        adr)
            ( grep -oE '^[0-9]{4}-' | tr -d '-' | grep -v '^0000$' \
                | sort -n | tail -1 ) || true ;;
        migration)
            ( grep -E '^[0-9]{1,3}[a-z]?_' | grep -oE '^[0-9]{1,3}' \
                | sort -n | tail -1 ) || true ;;
        *) return 1 ;;
    esac
    return 0
}

# Basenames in a local directory. ADR: the NNNN-*.md glob only (.md only).
# Migration: every entry, every extension (.sql, .php and .md share the namespace).
numbering_local_names() { # <kind> <abs-dir>
    [ -d "$2" ] || return 0
    case "$1" in
        adr)       ( ls "$2"/[0-9][0-9][0-9][0-9]-*.md 2>/dev/null | sed 's/.*\///' ) || true ;;
        migration) printf '%s\n' "$2"/* | sed 's|.*/||' ;;
        *)         return 1 ;;
    esac
    return 0
}

# Print the main-checkout refusal for <kind> on stderr, verbatim from the
# pre-refactor allocators (callers and tests grep `bin/wt-new` in it).
numbering_refuse_main() { # <kind>
    case "$1" in
        adr)
            echo "Error: refusing to run from the main checkout — bin/next-adr cp's the" >&2
            echo "       template into THIS repo root before any content is written, which" >&2
            echo "       strands an empty ADR on master. Create a worktree first:" >&2
            echo "         bin/wt-new <slug>" >&2 ;;
        migration)
            echo "Error: refusing to hand out a migration number from the main checkout." >&2
            echo "       Create a worktree first:" >&2
            echo "         bin/wt-new <slug>" >&2 ;;
    esac
    return 0
}

# Print the next free padded number for <kind>. Merges the worktree dir, the
# canonical dir, every origin ref and every registered worktree (all offline).
# Returns 1 with "No migrations found" on stderr when a migration scan finds nothing.
numbering_next() { # <kind> <repo-root>
    local kind="$1" repo_root="$2" rel width canonical
    local m_local m_canon m_origin m_wts max cand
    rel=$(numbering_dir "$kind") || return 2
    case "$kind" in adr) width=4 ;; *) width=3 ;; esac
    canonical=$(resolve_canonical_root "$repo_root")
    m_local=$(numbering_local_names "$kind" "$repo_root/$rel" | numbering_max_from_names "$kind")
    m_canon=$(numbering_local_names "$kind" "$canonical/$rel" | numbering_max_from_names "$kind")
    m_origin=$(scan_origin_refs "$rel" "$repo_root" | numbering_max_from_names "$kind")
    m_wts=$(scan_worktrees "$rel" "$repo_root" | numbering_max_from_names "$kind")
    if [ "$kind" = migration ] && [ -z "$m_local$m_canon$m_origin$m_wts" ]; then
        echo "No migrations found" >&2
        return 1
    fi
    max="${m_local:-0}"
    for cand in "$m_canon" "$m_origin" "$m_wts"; do
        if [ -n "$cand" ] && [ "$((10#$cand))" -gt "$((10#$max))" ]; then
            max=$cand
        fi
    done
    printf "%0${width}d\n" "$((10#$max + 1))"
    return 0
}
