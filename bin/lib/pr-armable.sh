# Shared auto-merge "live hold" predicate — the single source of truth for the
# `/post-plan` Phase 6.5 arming conditions that are derivable from a LIVE PR with
# no post-plan-run-local state. Sourced by both `bin/pr-triage` (cross-PR) and
# `/post-plan` Phase 6.5 (current branch) so the shared logic can never drift.
#
# Usage: source "$(dirname "$0")/lib/pr-armable.sh"
#
# Covers the four live-derivable conditions:
#   (1) Manual-Testing clearance   -> pr_manual_testing_clearance <body>
#   (5) golden-snapshot touch      -> pr_golden_hold <files_json>
#   (6) Depends-on merge-order     -> pr_dep_holds <body>
#   (8) feat: floor                -> pr_feat_hold <title> <labels_json>
#
# Conditions (2) review>=80, (3) MISSING-tests, (4) Phase-5 local verify, (7)
# non-UI auto_merge:false, (9) realized-diff verdict are deliberately NOT here:
# they are knowable only from a post-plan run's local state (/tmp, the local plan
# file, the realized diff) and cannot be evaluated for an arbitrary PR. Callers
# fail CLOSED on them — a PR is only ARMABLE with a POSITIVE clearance signal
# (pr_manual_testing_clearance == CLEARED), never the mere absence of holds.
#
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.
#
# Test seam: GH_CMD (default `gh`) is a single-token command (a path to a shim in
# tests) invoked as `"$GH_CMD" pr view ...`. Only pr_dep_holds touches it.

GH_CMD="${GH_CMD:-gh}"

# pr_manual_testing_clearance <body>
#   The fail-closed positive-clearance axis (Phase 6.5 condition (1), mechanized).
#   Echoes exactly one of:
#     CLEARED  — a `## Manual Testing` section exists AND its body matches
#                `No manual testing needed` (case-insensitive). This is the
#                positive "post-plan evaluated and cleared this" signal.
#     HELD     — a `## Manual Testing` section exists but is NOT the sentinel
#                (it carries real manual rows) -> a human must review.
#     UNKNOWN  — there is NO `## Manual Testing` section at all (a hand-made PR,
#                or one post-plan never processed) -> NOT auto-armable.
#   The sentinel has two suffixes in the source ("...unit and E2E tests" and
#   "...automated tests"), so this PREFIX-matches `No manual testing needed`.
pr_manual_testing_clearance() {
    local body="$1"
    local section content
    section=$(printf '%s\n' "$body" | sed -n '/^## Manual Testing/,/^## /p')
    if [ -z "$section" ]; then
        echo "UNKNOWN"
        return
    fi
    # Drop the heading line; inspect the remaining content for the sentinel.
    content=$(printf '%s\n' "$section" | sed '1d')
    if printf '%s\n' "$content" | grep -qiE '^[[:space:]]*No manual testing needed'; then
        echo "CLEARED"
    else
        echo "HELD"
    fi
}

# pr_golden_hold <files_json>
#   Phase 6.5 condition (5), detection only. Reports the raw FACT that the PR's
#   `--json files` array touches the engine golden snapshot; the CALLER applies
#   the mode policy (Phase 6.5: hold only when headless; pr-triage --arm: always
#   hold). Echoes `golden-changed` when present, nothing otherwise.
#   Pass the `.files` array, e.g. `gh pr view N --json files --jq '.files'`.
pr_golden_hold() {
    local files_json="$1"
    if printf '%s' "$files_json" \
        | jq -e 'any(.[]?; .path == "engine/internal/sim/testdata/golden.json")' \
            >/dev/null 2>&1; then
        echo "golden-changed"
    fi
}

# pr_feat_hold <title> <labels_json>
#   Phase 6.5 condition (8), the `feat:` floor. A conventional-commit feature
#   title holds for human sign-off UNLESS the `human-approved` label is already
#   applied (the label, set by a maintainer, flips it). Echoes
#   `feat-awaiting-signoff` when held, nothing otherwise.
#   Pass the `.labels` array, e.g. `gh pr view N --json labels --jq '.labels'`.
pr_feat_hold() {
    local title="$1" labels_json="$2"
    if printf '%s' "$title" | grep -qiE '^feat(\([^)]*\))?!?:'; then
        if printf '%s' "$labels_json" \
            | jq -e 'any(.[]?; .name == "human-approved")' >/dev/null 2>&1; then
            return  # label flips it — not held
        fi
        echo "feat-awaiting-signoff"
    fi
}

# pr_dep_holds <body>
#   Phase 6.5 condition (6), merge-order. For each anchored `Depends-on: #N` line
#   (start-of-line only, so an inline prose mention of the marker is ignored),
#   query the predecessor's state and echo `depends-on:#N` for any that is not
#   yet MERGED. `while read` (not a `for` loop) splits per-line in bash AND zsh.
pr_dep_holds() {
    local body="$1" nums d st
    # `|| true`: grep exits 1 on no-match, which would abort a `set -o pipefail`
    # caller (this file is sourced into one) when the result is captured.
    nums=$(printf '%s\n' "$body" \
        | grep -iE '^[[:space:]]*depends-on:' \
        | grep -oE '[0-9]+' || true)
    [ -z "$nums" ] && return 0
    printf '%s\n' "$nums" | while read -r d; do
        st=$("$GH_CMD" pr view "$d" --json state 2>/dev/null | jq -r '.state // empty')
        [ "$st" != "MERGED" ] && echo "depends-on:#$d"
    done
}
