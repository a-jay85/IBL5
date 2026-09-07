# bin/lib/plan-resolve.sh — shared plan-file resolver for /post-plan
#
# Sourced by /post-plan Phase 1, condition (7), and condition (13) blocks so the
# plan-resolution logic has exactly one executable home and cannot drift between them.
#
# Usage:
#   source "$(git rev-parse --show-toplevel)/bin/lib/plan-resolve.sh"
#   resolve_plan_file   # sets PLAN_FILE and PLAN_SLUG_DRIFT in the caller's shell
#
# Covers:
#   resolve_plan_file  -> sets two variables in the caller's shell:
#       PLAN_FILE        absolute path to the resolved plan, or empty string (plan-blind)
#       PLAN_SLUG_DRIFT  basename of an adopted drift match, else empty string
#
# Resolution order (mirrors harness/planfile.py::locate_plan/_resolve_variant/_resolve_drift):
#   1. Non-empty PLAN_FILE that exists on disk -> use verbatim; PLAN_SLUG_DRIFT="".
#   2. Non-empty PLAN_FILE that does NOT exist -> plan-blind (PLAN_FILE="", PLAN_SLUG_DRIFT="").
#   3. Branch slug derivation:
#      a. Highest-numbered variant <slug>-N.md (N purely digits) wins; bare <slug>.md is
#         variant 0. When multiple variants exist, a WARNING is emitted and the highest wins.
#      b. No variants and no bare match -> slug-drift: exactly one <prefix>-<slug>.md with
#         non-empty prefix -> adopt it, set PLAN_SLUG_DRIFT to its basename, and emit a
#         WARNING (auto-merge is HELD via condition 13). Two or more candidates -> ambiguous
#         -> WARNING emitted, stays plan-blind.
#
# Test seams (override before sourcing or before calling resolve_plan_file):
#   PLAN_DIR   (default $HOME/claude-plans) — directory scanned for plan files.
#              The shell seam is PLAN_DIR; the harness uses PLANS_DIR — they are distinct env vars.
#   PLAN_SLUG  (default: git rev-parse --abbrev-ref HEAD) — branch slug override for tests.
#
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.

PLAN_DIR="${PLAN_DIR:-$HOME/claude-plans}"

# resolve_plan_file
#   Set PLAN_FILE and PLAN_SLUG_DRIFT in the caller's shell.
#   See header comment for the full resolution contract.
resolve_plan_file() {
    local slug f stem v best best_f ncands cand_list first_cand selected

    # Step 1/2: honor a pre-set PLAN_FILE (automouse handoff path or test seam).
    # Pre-set + exists -> use verbatim. Pre-set + absent -> plan-blind (don't fall through
    # to slug derivation — a bogus path must not accidentally adopt a real plan file).
    if [ -n "${PLAN_FILE:-}" ]; then
        if [ -f "$PLAN_FILE" ]; then
            PLAN_SLUG_DRIFT=""
        else
            PLAN_FILE=""
            PLAN_SLUG_DRIFT=""
        fi
        return 0
    fi

    PLAN_SLUG_DRIFT=""
    if [ -n "${PLAN_SLUG:-}" ]; then
        slug="$PLAN_SLUG"
    else
        slug=$(git rev-parse --abbrev-ref HEAD 2>/dev/null) || { PLAN_FILE=""; return 0; }
    fi

    # Step 3a: variant resolution — bare <slug>.md is variant 0; <slug>-N.md is variant N.
    # Only purely-numeric suffixes are variants; -shared-context, -1a-trading-pins are not.
    best=-1; best_f=""
    for f in "${PLAN_DIR}/${slug}.md" "${PLAN_DIR}/${slug}"-*.md; do
        [ -f "$f" ] || continue
        stem=${f##*/}; stem=${stem%.md}
        if [ "$stem" = "$slug" ]; then
            v=0
        else
            v=${stem#"${slug}-"}
            case "$v" in ''|*[!0-9]*) continue ;; esac
        fi
        [ "$v" -gt "$best" ] && { best=$v; best_f=$f; }
    done

    if [ "$best" -ge 0 ]; then
        if [ "$best" -gt 0 ]; then
            printf 'post-plan: WARNING — plan variants for slug '\''%s'\''; selected %s (highest-numbered)\n  override:   bin/post-plan-now --plan <abs-path>\n' \
                "$slug" "$(basename "$best_f")" >&2
        fi
        PLAN_FILE="$best_f"
        return 0
    fi

    # Step 3b: drift resolution — <prefix>-<slug>.md where prefix is non-empty.
    # Shell glob *-<slug>.md matches empty prefix too (unlike Python's .+ regex); guard it.
    ncands=0; cand_list=""; first_cand=""
    for f in "${PLAN_DIR}/"*"-${slug}.md"; do
        [ -f "$f" ] || continue
        stem=${f##*/}
        [ "$stem" = "-${slug}.md" ] && continue  # empty-prefix guard (shell * matches empty)
        ncands=$((ncands + 1))
        [ -z "$cand_list" ] && cand_list="$stem" || cand_list="$cand_list, $stem"
        [ -z "$first_cand" ] && first_cand="$stem"
    done

    case "$ncands" in
        0)
            PLAN_FILE=""
            ;;
        1)
            selected="$first_cand"
            printf 'post-plan: WARNING — no plan at %s.md; adopted '\''%s'\'' by slug drift\n  branch name and plan filename disagree\n  auto-merge is HELD for this run (condition 13)\n  override:   bin/post-plan-now --plan <abs-path>\n' \
                "$slug" "$selected" >&2
            PLAN_FILE="${PLAN_DIR}/${selected}"
            PLAN_SLUG_DRIFT="$selected"
            ;;
        *)
            printf 'post-plan: WARNING — %d slug-drift candidates for branch '\''%s'\'': %s\n  none adopted (ambiguous); running plan-blind\n  override:   bin/post-plan-now --plan <abs-path>\n' \
                "$ncands" "$slug" "$cand_list" >&2
            PLAN_FILE=""
            ;;
    esac
}
