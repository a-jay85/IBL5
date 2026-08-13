# Sourced library — do NOT set -e at file scope; this is sourced into other shells.
# Usage: source bin/lib/docfix-dm.sh
# shellcheck shell=bash

# docfix_dm_compose <pr_number> <out_path>
#
# Validates <pr_number>, fetches PR metadata, guards on OPEN state and
# docs-stale-refresh- head-ref prefix, then composes a Discord DM into <out_path>.
#
# Exit codes:
#   0 — message composed into <out_path>
#   1 — REFUSED: input not a bare integer, PR not OPEN, or head ref lacks the
#       docs-stale-refresh- prefix
#   2 — environment error (gh call failed)
#
# On any refusal or environment error, <out_path> is not written.
docfix_dm_compose() {
    local PR="$1"
    local out_path="$2"
    local meta head_ref state url files ok

    # Validate input: must be a bare integer. A workflow_dispatch input is
    # free-text; everything downstream interpolates it into a gh call and into
    # the DM body.
    case "$PR" in
        ''|*[!0-9]*) return 1 ;;
    esac

    # One metadata call, fixed post-jq shape: headRefName, state, url in that
    # order. The test shim dispatches on this exact argument text — do not merge
    # or reorder. Use || return 2 so a gh failure does not silently exit the
    # caller's shell under set -e.
    meta=$(gh pr view "$PR" --json headRefName,state,url --jq '[.headRefName, .state, .url] | @tsv') || return 2

    # Parse the TSV into local vars. <<< keeps vars in scope (no subshell).
    IFS=$'\t' read -r head_ref state url <<< "$meta"

    # Guard: refuse unless OPEN and docs-stale-refresh- prefix.
    # Emit one-line reason to stderr so a mistyped dispatch is self-diagnosing.
    ok=1
    [ "$state" = "OPEN" ] || ok=0
    case "$head_ref" in docs-stale-refresh-*) ;; *) ok=0 ;; esac
    if [ "$ok" -eq 0 ]; then
        printf 'REFUSED: PR #%s (head ref: %s, state: %s) — must be OPEN with docs-stale-refresh- prefix\n' \
            "$PR" "$head_ref" "$state" >&2
        return 1
    fi

    # File list — verbatim call; test shim matches on exact argument text.
    files=$(gh pr view "$PR" --json files --jq '.files[].path') || return 2

    # Compose atomically: all validation passed before any write touches out_path.
    # PR title is deliberately excluded: it is author-controlled text.
    {
        printf 'Claude refreshed your stale docs — ready for your review. PR #%s:\n' "$PR"
        # shellcheck disable=SC2001  # multiline prefix; ${var//} cannot do per-line prepend
        echo "$files" | sed 's/^/  /'
        printf 'Review and merge: %s\n' "$url"
    } > "$out_path"
}
