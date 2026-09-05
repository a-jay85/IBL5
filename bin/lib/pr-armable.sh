# Shared auto-merge "live hold" predicate — the single source of truth for the
# `/post-plan` Phase 6.5 arming conditions that are derivable from a LIVE PR with
# no post-plan-run-local state. Sourced by both `bin/pr-triage` (cross-PR) and
# `/post-plan` Phase 6.5 (current branch) so the shared logic can never drift.
#
# Usage: source "$(dirname "$0")/lib/pr-armable.sh"
#
# Covers the six live-derivable conditions:
#   (1) Manual-Testing clearance   -> pr_manual_testing_clearance <body>
#   (5) golden-snapshot touch      -> pr_golden_hold <files_json>
#   (6) Depends-on merge-order     -> pr_dep_holds <body>
#   (8) feat: floor                -> pr_feat_hold <title> <labels_json>
#   (10) pipeline-authored floor   -> pr_pipeline_authored_hold <labels_json> [head_ref]
#   (11) unresolved scored finding -> pr_unresolved_findings_hold <pr_number>
#
# Conditions (2) review>=80, (3) MISSING-tests, (4) Phase-5 local verify, (7)
# non-UI auto_merge:false, (9) realized-diff verdict are deliberately NOT here:
# they are knowable only from a post-plan run's local state (/tmp, the local plan
# file, the realized diff) and cannot be evaluated for an arbitrary PR. Callers
# fail CLOSED on them — a PR is only ARMABLE with a POSITIVE clearance signal
# (pr_manual_testing_clearance == CLEARED), never the mere absence of holds.
# (11) is NOT a duplicate of (2): (2) scores findings produced by the current
# post-plan run; (11) reads live GitHub thread state, catching findings left
# unresolved by an earlier run or by a standalone /pr-review or /security-audit.
#
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.
#
# Test seam: GH_CMD (default `gh`) is a single-token command (a path to a shim in
# tests) invoked as `"$GH_CMD" pr view ...`. Only pr_dep_holds and
# pr_unresolved_findings_hold touch it. REPO_SLUG (default `a-jay85/IBL5`) is the
# second seam, consumed only by pr_unresolved_findings_hold's GraphQL call; it is
# the same seam name bin/lib/post-review-findings.sh already uses.

GH_CMD="${GH_CMD:-gh}"
REPO_SLUG="${REPO_SLUG:-a-jay85/IBL5}"

# pr_manual_testing_clearance <body>
#   The fail-closed positive-clearance axis (Phase 6.5 condition (1), mechanized).
#   Echoes exactly one of:
#     CLEARED  — a `## Manual Testing` section exists AND its body matches
#                `No manual testing needed` (case-insensitive). This is the
#                positive "post-plan evaluated and cleared this" signal.
#     HELD     — a `## Manual Testing` section exists but is NOT the sentinel
#                (it carries real manual rows) -> a human must review. Also HELD
#                when the sentinel names an explicit type whose matching file is
#                absent from <changed_files> (AND semantics, see tail-clause).
#     UNKNOWN  — there is NO `## Manual Testing` section at all (a hand-made PR,
#                or one post-plan never processed) -> NOT auto-armable.
#   The sentinel prefix `No manual testing needed` covers both the template-
#   generated suffix ("...automated tests") and any historical PR that used the
#   old wording ("...unit and E2E tests"); the tail-clause check (second param)
#   uses AND semantics: each named type must have a matching file; any absent
#   type holds. Type-agnostic wording ("automated tests") always clears.
pr_manual_testing_clearance() {
    local body="$1"
    local changed_files="${2:-}"
    local section content sentinel_line tail
    section=$(printf '%s\n' "$body" | sed -n '/^## Manual Testing/,/^## /p')
    if [ -z "$section" ]; then
        echo "UNKNOWN"
        return
    fi
    # Drop the heading line; inspect the remaining content for the sentinel.
    content=$(printf '%s\n' "$section" | sed '1d')
    if ! printf '%s\n' "$content" | grep -qiE '^[[:space:]]*No manual testing needed'; then
        echo "HELD"
        return
    fi
    # Sentinel found. Skip tail-clause check when no changed-file list provided.
    if [ -z "$changed_files" ]; then
        echo "CLEARED"
        return
    fi
    # Extract the sentinel line and strip the prefix to isolate the tail clause.
    sentinel_line=$(printf '%s\n' "$content" \
        | grep -iE '^[[:space:]]*No manual testing needed' | head -1)
    tail=$(printf '%s' "$sentinel_line" \
        | sed 's/^[[:space:]]*[Nn]o manual testing needed[[:space:]]*[—–-]*[[:space:]]*//')
    # Keyword → required changed-file pattern table (case-insensitive, \b word boundary).
    # AND semantics: each named type must have a matching file; any absent type holds.
    local held=0
    if printf '%s' "$tail" | grep -qiE '\b(e2e|playwright)\b'; then
        if ! printf '%s\n' "$changed_files" \
                | grep -qE '^ibl5/tests/e2e/.*\.spec\.ts$'; then
            held=1
        fi
    fi
    if printf '%s' "$tail" | grep -qiE '\b(unit|phpunit)\b'; then
        if ! printf '%s\n' "$changed_files" \
                | grep -qE '^ibl5/tests/.*Test\.php$'; then
            held=1
        fi
    fi
    if printf '%s' "$tail" | grep -qiE '\bintegration\b'; then
        if ! printf '%s\n' "$changed_files" \
                | grep -qE '^ibl5/tests/DatabaseIntegration/'; then
            held=1
        fi
    fi
    if [ "$held" -eq 1 ]; then
        echo "HELD"
    else
        echo "CLEARED"
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
    # REQUIRED, same reason as pr_unresolved_findings_hold's: the `[ ... ] && echo`
    # above is the LAST command in the `while` body, so a predecessor that IS merged
    # makes the test false -> body rc 1 -> while rc 1 -> pipeline rc 1 -> function
    # rc 1. bin/pr-triage captures this as `deps="$(pr_dep_holds ... | tr ...)"` under
    # `set -euo pipefail`, so without this line the whole report ABORTS silently on
    # the first PR whose Depends-on: predecessor has already merged — no stderr, a
    # truncated table that looks complete. Observed live: 5 of 30 PRs reported.
    return 0
}

# pr_pipeline_authored_hold <labels_json> [head_ref]
#   Phase 6.5 condition (10), the two-axis pipeline floor. Holds any PR opened by
#   the Discord bug/feature pipeline from auto-merge, unconditionally. Two
#   independent axes — either can hold:
#
#   Axis 1 (label): echoes `pipeline-authored` when the `.labels` array contains
#     the `pipeline-authored` label. Pass the `.labels` array, e.g.
#     `gh pr view N --json labels --jq '.labels'`.
#
#   Axis 2 (branch name): echoes `pipeline-branch` when head_ref is non-empty and
#     matches `^bug-[0-9]+(-|$)`. This axis exists because the `pipeline-authored`
#     label is applied by reconcile_pr_open_rows() in bin/bug-pipeline-tick on a
#     LATER cron tick than ship_via_cron() which arms auto-merge — so at arming
#     time the PR is unlabeled and label-alone cannot hold it. The branch name is
#     deterministic (from bug_slug() in bin/bug-pipeline-tick: `bug-<id>-<desc>`
#     or bare `bug-<id>`). The digit anchor (`[0-9]+`) is load-bearing: the human
#     branch `bug-pipeline-reproduce-gate` must NOT be caught by this predicate.
#
#   UNCONDITIONAL: unlike pr_feat_hold there is NO override label; a pipeline PR
#   ALWAYS holds for a human merge regardless of commit type.
#   head_ref is optional — an absent/empty second arg behaves exactly as today
#   (backward compatible; use ${2:-} to avoid an unbound-variable error).
pr_pipeline_authored_hold() {
    local labels_json="$1"
    local head_ref="${2:-}"
    if printf '%s' "$labels_json" \
        | jq -e 'any(.[]?; .name == "pipeline-authored")' >/dev/null 2>&1; then
        echo "pipeline-authored"
    fi
    if [ -n "$head_ref" ] && printf '%s' "$head_ref" | grep -qE '^bug-[0-9]+(-|$)'; then
        echo "pipeline-branch"
    fi
}

# pr_unresolved_findings_hold <pr_number>
#   Phase 6.5 condition (11), the unresolved-scored-finding floor. An UNRESOLVED review
#   thread whose first comment embeds `<!-- score: N -->` with N >= 80 holds auto-merge.
#   Complements (2), which sees only THIS run's scores: (11) reads live GitHub thread
#   state, so a finding from an earlier run or a standalone /pr-review or /security-audit
#   still blocks. Human (unscored) threads, scores < 80, and resolved threads NEVER block.
#   FAIL-CLOSED on every error path. Echoes one `unresolved-finding:SCORE` line per
#   qualifying thread, or one of `unresolved-findings-cap` / `-api-error`, or nothing.
pr_unresolved_findings_hold() {
    local pr="$1"
    local owner="${REPO_SLUG%%/*}" repo="${REPO_SLUG##*/}"
    local raw rc=0
    raw=$("$GH_CMD" api graphql \
        -F owner="$owner" -F repo="$repo" -F pr="$pr" \
        -f query='
        query($owner:String!, $repo:String!, $pr:Int!) {
          repository(owner:$owner, name:$repo) {
            pullRequest(number:$pr) {
              reviewThreads(first:100) {
                nodes {
                  isResolved
                  comments(first:1) { nodes { databaseId body } }
                }
              }
            }
          }
        }' 2>/dev/null) || rc=$?
    [ "$rc" -ne 0 ] && { echo "unresolved-findings-api-error"; return 0; }
    # GraphQL protocol errors return HTTP 200 with a top-level `errors` array, so a
    # zero exit status alone does NOT mean the payload is usable.
    if printf '%s' "$raw" | jq -e '.errors' >/dev/null 2>&1; then
        echo "unresolved-findings-api-error"; return 0
    fi
    if ! printf '%s' "$raw" \
            | jq -e '.data.repository.pullRequest.reviewThreads.nodes != null' \
            >/dev/null 2>&1; then
        echo "unresolved-findings-api-error"; return 0
    fi
    local count
    count=$(printf '%s' "$raw" \
        | jq '.data.repository.pullRequest.reviewThreads.nodes | length' 2>/dev/null) \
        || { echo "unresolved-findings-api-error"; return 0; }
    # first:100 is unpaginated: a full page means threads 101+ are invisible, so hold.
    [ "$count" -eq 100 ] && { echo "unresolved-findings-cap"; return 0; }
    local findings
    findings=$(printf '%s' "$raw" | jq -r '
        .data.repository.pullRequest.reviewThreads.nodes[]
        | select(.isResolved == false)
        | select(
            (.comments.nodes[0].body // "")
            | capture("<!-- score: (?<s>[0-9]+) -->")
            | .s | tonumber >= 80
          )
        | "unresolved-finding:" +
          ((.comments.nodes[0].body // "")
           | capture("<!-- score: (?<s>[0-9]+) -->") | .s)
    ' 2>/dev/null) || { echo "unresolved-findings-api-error"; return 0; }
    [ -n "$findings" ] && printf '%s\n' "$findings"
    # REQUIRED: without it the clear path returns 1 and aborts bin/pr-triage
    # (set -euo pipefail) on the first clear PR. Every predicate in this file must
    # return 0 on its clear path; pr_dep_holds violated that until 2026-08-22, so
    # treat a trailing bare `[ ... ] && cmd` in any predicate here as a live bug.
    return 0
}
