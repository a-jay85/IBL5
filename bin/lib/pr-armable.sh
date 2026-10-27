# Shared auto-merge "live hold" predicate — the single source of truth for the
# `/post-plan` Phase 6.5 arming conditions that are derivable from a LIVE PR with
# no post-plan-run-local state. Sourced by both `bin/pr-triage` (cross-PR) and
# `/post-plan` Phase 6.5 (current branch) so the shared logic can never drift.
#
# Usage: source "$(dirname "$0")/lib/pr-armable.sh"
#
# Covers the live-derivable conditions (17: pr_gate_backtest_hold <body>; 18: pr_merge_danger_hold <body>):
#   (1) Manual-Testing clearance   -> pr_manual_testing_clearance <body>
#   (5) golden-snapshot touch      -> pr_golden_hold <files_json>
#   (6) Depends-on merge-order     -> pr_dep_holds <body>
#   (8) feat: floor                -> pr_feat_hold <title> <labels_json>
#   (10) pipeline-authored floor   -> pr_pipeline_authored_hold <labels_json> [head_ref]
#   (11) unresolved review thread  -> pr_unresolved_findings_hold <pr_number>
#   (15) already-red CI checks     -> pr_red_check_hold <pr_number>
#   (7, docs-only-ready) docs-refresh  -> pr_docs_refresh_hold <head_ref> <files_json> <md_state> <verdict>
#        demoted to a warning when  -> pr_aggregator_required  (reads master protection)
#
# Conditions (2) review>=80, (3) MISSING-tests, (4) Phase-5 local verify, (7)
# frontmatter read (the clause evaluation behind `auto_merge: docs-only-ready`
# lives here as pr_docs_refresh_hold), (9) realized-diff verdict are
# deliberately NOT here:
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
# tests) invoked as `"$GH_CMD" pr view ...` or `"$GH_CMD" pr checks ...`.
# pr_dep_holds, pr_unresolved_findings_hold, and pr_red_check_hold all touch it.
# pr_aggregator_required also touches GH_CMD via "$GH_CMD" api repos/$REPO_SLUG/branches/master.
# REPO_SLUG (default `a-jay85/IBL5`) is the second seam, consumed only by
# pr_unresolved_findings_hold's GraphQL call; it is the same seam name
# bin/lib/post-review-findings.sh already uses.

GH_CMD="${GH_CMD:-gh}"
REPO_SLUG="${REPO_SLUG:-a-jay85/IBL5}"

# The aggregator context name. bin/check-composite-contracts A2 pins this literal to
# the --context= of .github/workflows/all-checks-settled.yml and to armable.py.
# It is a commit status on the PR head, so condition (15) still reads protection
# contexts, never check-runs.
PR_ARMABLE_AGGREGATOR_CONTEXT='All checks settled'

# pr_manual_testing_clearance <body>
#   The fail-closed positive-clearance axis (Phase 6.5 condition (1), mechanized).
#   Echoes exactly one of:
#     CLEARED  — a `## Manual Testing` section exists AND its body matches
#                `No manual testing needed` (case-insensitive). This is the
#                positive "post-plan evaluated and cleared this" signal.
#     HELD     — a `## Manual Testing` section exists but is NOT the sentinel
#                (it carries real manual rows) -> a human must review. Also HELD
#                when the section carries an UNCHECKED `- [ ]` row even if the
#                sentinel is also present (the row outranks it); and when the
#                sentinel names an explicit type whose matching file is absent
#                from <changed_files> (AND semantics, see tail-clause).
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
    # An UNCHECKED box is an outstanding manual step, and it outranks the
    # sentinel: a section carrying both must HOLD, never clear. Without this the
    # sentinel grep below wins on a mixed section and arms auto-merge on a PR
    # with real manual work left. The window is markdown-bounded at the next
    # `## ` heading only when one exists — the LAST section runs to EOF — so a
    # sentinel-looking line in a trailing machine footer lands inside the window
    # of a checkbox-bearing section. Hardening: 0 occurrences across the most
    # recent 200 PR bodies (`gh pr list --state all --limit 200`, 2026-09-07).
    #
    # Deliberately NOT `[[ x]]`: a CHECKED `- [x]` row is completed evidence and
    # legitimately co-occurs with the sentinel ("no manual testing needed" +
    # "- [x] bin/test-check-pr-manual-testing — 19/19 PASS"). Holding on those would block correct
    # PRs. bin/check-pr-manual-testing's shape regex conflates the two on
    # purpose (both shapes are well-formed); this clearance axis must not.
    if grep -qE '^[[:space:]]*- \[ \]' <<< "$content"; then
        echo "HELD"
        return
    fi
    if ! grep -qiE '^[[:space:]]*No manual testing needed' <<< "$content"; then
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
    if grep -qiE '\b(e2e|playwright)\b' <<< "$tail"; then
        if ! grep -qE '^ibl5/tests/e2e/.*\.spec\.ts$' <<< "$changed_files"; then
            held=1
        fi
    fi
    if grep -qiE '\b(unit|phpunit)\b' <<< "$tail"; then
        if ! grep -qE '^ibl5/tests/.*Test\.php$' <<< "$changed_files"; then
            held=1
        fi
    fi
    if grep -qiE '\bintegration\b' <<< "$tail"; then
        if ! grep -qE '^ibl5/tests/DatabaseIntegration/' <<< "$changed_files"; then
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
    if grep -qiE '^feat(\([^)]*\))?!?:' <<< "$title"; then
        if printf '%s' "$labels_json" \
            | jq -e 'any(.[]?; .name == "human-approved")' >/dev/null 2>&1; then
            return  # label flips it — not held
        fi
        echo "feat-awaiting-signoff"
    fi
}

# pr_gate_backtest_hold <body>
#   Phase 6.5 condition (17). Mirrors harness/gate_backtest.py parse_gate_backtest_state.
#   No '<!-- gate-backtest:begin -->' line -> prints nothing (not a gate PR).
#   Block present: exactly one '<!-- gate-backtest-state: X -->' line between the
#   markers, X in NOT-APPLICABLE|CLEARED -> prints nothing; X in HELD|UNKNOWN ->
#   prints 'gate-backtest:X'. Zero or 2+ state lines, or no end marker -> prints
#   'gate-backtest:UNKNOWN' (fail-closed).
pr_gate_backtest_hold() {
    local body="$1" line inside=0 seen_begin=0 seen_end=0 n=0 state=""
    while IFS= read -r line; do
        case "$line" in
            '<!-- gate-backtest:begin -->') seen_begin=1; inside=1 ;;
            '<!-- gate-backtest:end -->')   [ "$inside" = 1 ] && seen_end=1; inside=0 ;;
            '<!-- gate-backtest-state: '*' -->')
                if [ "$inside" = 1 ]; then
                    n=$((n + 1)); state="${line#<!-- gate-backtest-state: }"; state="${state% -->}"
                fi ;;
        esac
    done <<EOF
$body
EOF
    [ "$seen_begin" = 0 ] && return 0
    if [ "$seen_end" = 0 ] || [ "$n" != 1 ]; then echo "gate-backtest:UNKNOWN"; return 0; fi
    case "$state" in
        NOT-APPLICABLE|CLEARED) ;;
        HELD|UNKNOWN) echo "gate-backtest:$state" ;;
        *) echo "gate-backtest:UNKNOWN" ;;
    esac
}

# pr_merge_danger_hold <body>
#   Phase 6.5 condition (18). Mirrors harness/merge_danger.py classify_body.
#   Prints nothing for exactly one well-formed line with Door two-way; otherwise prints
#   merge-danger:ONE-WAY | merge-danger:MISSING | merge-danger:MALFORMED (fail-closed).
pr_merge_danger_hold() {
    local body="$1" n well
    n=$(printf '%s\n' "$body" | grep -cE '^\*\*Merge danger:\*\*' || true)
    [ "$n" = 0 ] && { echo "merge-danger:MISSING"; return 0; }
    [ "$n" != 1 ] && { echo "merge-danger:MALFORMED"; return 0; }
    well=$(printf '%s\n' "$body" | grep -E '^\*\*Merge danger:\*\* Door: (one-way|two-way) · Blast radius: (localized|module|site-wide|external) · Why: .{20,}$' || true)
    [ -z "$well" ] && { echo "merge-danger:MALFORMED"; return 0; }
    case "$well" in *'Door: one-way'*) echo "merge-danger:ONE-WAY" ;; esac
}

# pr_docs_refresh_path_ok <repo-relative path>
#   True (rc 0) when the path is a documentation file the stale-docs refresh
#   pipeline may auto-merge: a `.md` file under ibl5/docs/, a class README at
#   ibl5/classes/*/README.md, or the root README.md. Everything else (any
#   .claude/ path, bin/, .github/, tests, code, config, a non-.md file under
#   ibl5/docs/) is rc 1. Default-deny: a path has to match the allowlist to pass.
#   Mirrors harness/armable.py docs_refresh_path_ok; the parity test pins both.
pr_docs_refresh_path_ok() {
    case "$1" in
        *.md) ;;
        *) return 1 ;;
    esac
    case "$1" in
        ibl5/docs/*)              return 0 ;;
        ibl5/classes/*/README.md) return 0 ;;
        README.md)                return 0 ;;
        *)                        return 1 ;;
    esac
}

# pr_docs_refresh_hold <head_ref> <files_json> <merge_danger_state> <verdict_word>
#   Phase 6.5 condition (7) when the plan's frontmatter reads
#   `auto_merge: docs-only-ready`. Mirrors harness/armable.py docs_refresh_holds.
#   Prints nothing when every clause passes. Otherwise prints one line per
#   FAILED clause, in this fixed order (fail-closed; the caller holds on any line):
#     docs-refresh:branch:<head_ref>       head ref lacks the docs-stale-refresh- prefix
#     docs-refresh:paths:empty             the PR changed no files at all
#     docs-refresh:paths:<path>            first changed path pr_docs_refresh_path_ok rejects
#     docs-refresh:merge-danger:<STATE>    state is anything other than TWO-WAY
#     docs-refresh:verdict:<word>          selected verdict is anything other than READY
#   <files_json> is the `.files` array (same shape pr_golden_hold reads).
#   <merge_danger_state> is TWO-WAY, or the suffix of pr_merge_danger_hold's
#   output (ONE-WAY | MISSING | MALFORMED); an empty argument reads as MISSING.
#   <verdict_word> is the SELECTED Phase 5.5 verdict (run-local state the caller
#   supplies); READY WITH NOTES does not qualify, by decision. Empty reads as <none>.
pr_docs_refresh_hold() {
    local head_ref="$1" files_json="$2" md_state="$3" verdict="$4" paths p bad=""
    case "$head_ref" in
        docs-stale-refresh-*) ;;
        *) echo "docs-refresh:branch:${head_ref:-<empty>}" ;;
    esac
    # `|| true`: jq exits non-zero on malformed JSON; an empty path list holds below.
    paths=$(printf '%s' "$files_json" | jq -r '.[]?.path // empty' 2>/dev/null || true)
    if [ -z "$paths" ]; then
        echo "docs-refresh:paths:empty"
    else
        while IFS= read -r p; do
            [ -z "$p" ] && continue
            if ! pr_docs_refresh_path_ok "$p"; then bad="$p"; break; fi
        done <<< "$paths"
        [ -n "$bad" ] && echo "docs-refresh:paths:$bad"
    fi
    [ "$md_state" != "TWO-WAY" ] && echo "docs-refresh:merge-danger:${md_state:-MISSING}"
    verdict=$(printf '%s' "$verdict" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')
    [ "$verdict" != "READY" ] && echo "docs-refresh:verdict:${verdict:-<none>}"
    # REQUIRED for the same reason pr_dep_holds documents: the `[ ... ] && echo`
    # above is the last command, so a passing verdict leaves rc 1 and a
    # `set -euo pipefail` caller would abort on the qualifying PR.
    return 0
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
    if [ -n "$head_ref" ] && grep -qE '^bug-[0-9]+(-|$)' <<< "$head_ref"; then
        echo "pipeline-branch"
    fi
}

# pr_unresolved_findings_hold <pr_number>
#   Phase 6.5 condition (11), the unresolved-thread floor. EVERY unresolved review
#   thread holds auto-merge: scored (`<!-- score: N -->`, any N), unscored (human or
#   bot prose), outdated, or root-comment-deleted. Author and trust are NOT consulted
#   here; list_trusted_open_threads decides which threads a run may action, and
#   this predicate decides whether the PR may merge. Complements (2), which sees only
#   THIS run's scores: (11) reads live GitHub thread state, so a thread from an
#   earlier run, a standalone /pr-review or /security-audit, or a human question
#   still blocks. Resolved threads NEVER block.
#   FAIL-CLOSED on every error path. Echoes one `unresolved-finding:SCORE` line per
#   scored unresolved thread, `unresolved-finding:-` per unscored one, or one of
#   `unresolved-findings-cap` / `-api-error`, or nothing.
#   History: until 2026-09 only N >= 80 held; the widening is additive.
#   Exception (2026-10): a thread whose FIRST comment begins with
#   `<!-- label: nit -->` or `<!-- label: pre-existing -->` AND whose first-comment
#   author is trusted (a-jay85, a GitHub Bot, or a `[bot]` login) never holds. The
#   marker is written only by the posting code; an untrusted author, a marker not on
#   line 1, or any other label word holds as before. Skipped threads go to stderr.
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
                  comments(first:1) { nodes { databaseId body author { login __typename } } }
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
    # A trusted first comment that opens with a nit / pre-existing label marker is
    # non-holding (see header Exception). Shared by the stdout and stderr passes.
    local nonholding_def='
        def nonholding:
            ((.comments.nodes[0].body // "") | test("^<!-- label: (nit|pre-existing) -->")) and
            (((.comments.nodes[0].author.login // "") == "a-jay85")
             or ((.comments.nodes[0].author.__typename // "") == "Bot")
             or ((.comments.nodes[0].author.login // "") | endswith("[bot]")));'
    local findings
    findings=$(printf '%s' "$raw" | jq -r "$nonholding_def"'
        .data.repository.pullRequest.reviewThreads.nodes[]
        | select(.isResolved == false)
        | select(nonholding | not)
        | "unresolved-finding:" +
          (((.comments.nodes[0].body // "")
            | capture("<!-- score: (?<s>[0-9]+) -->") // null)
           | if . == null then "-" else .s end)
    ' 2>/dev/null) || { echo "unresolved-findings-api-error"; return 0; }
    # Skipped threads are logged to stderr only: any stdout line holds.
    printf '%s' "$raw" | jq -r "$nonholding_def"'
        .data.repository.pullRequest.reviewThreads.nodes[]
        | select(.isResolved == false)
        | select(nonholding)
        | "pr_unresolved_findings_hold: non-holding thread "
          + ((.comments.nodes[0].databaseId // "-") | tostring) + " ("
          + ((.comments.nodes[0].body // "") | capture("^<!-- label: (?<l>[a-z-]+) -->").l)
          + ")"
    ' >&2 2>/dev/null || true
    [ -n "$findings" ] && printf '%s\n' "$findings"
    # REQUIRED: without it the clear path returns 1 and aborts bin/pr-triage
    # (set -euo pipefail) on the first clear PR. Every predicate in this file must
    # return 0 on its clear path; pr_dep_holds violated that until 2026-08-22, so
    # treat a trailing bare `[ ... ] && cmd` in any predicate here as a live bug.
    return 0
}

# pr_red_check_hold <pr_number>
#   Phase 6.5 condition (15), already-red CI check floor. Echoes the name of each
#   check that is already in gh's `fail` bucket on the PR's current head, minus
#   `human-signoff` (which is red-by-design on every feat: PR and is reported by
#   condition (8) instead). Echoes nothing when no qualifying failure is found.
#
#   FAIL-OPEN ON PENDING CHECKS: an empty result means "no failure proven at this
#   moment", never "all checks are green". A check that has not yet reported is
#   invisible to the probe and therefore clears (15). `gh pr checks` exit 8 with
#   only pending rows still returns an empty list. Any `gh` error also returns
#   nothing (fail-open), so a network hiccup at arm time does not block the merge.
#
#   Condition (19) exec-mutations is run-local too (it applies edits in the
#   post-plan worktree), so it has no twin here either.
#   Why (15) lives in this shared predicate while the run-local conflict-resolved
#   hold does not: the conflict-resolved hold's evidence is a run-local flag in a
#   /tmp directory keyed to the current branch, invisible to any other checkout.
#   A red CI check is live-derivable for any PR number, so cross-PR consumers such
#   as bin/pr-triage can call this predicate directly without run-local state.
#
#   EXIT 8 IS THE HOLD CASE, NOT AN ERROR: `gh pr checks` exits 8 precisely when a
#   check is failing, which is the state this predicate exists to detect. Treating
#   any non-zero rc as an error would make this function a no-op in production
#   while still passing a shim-based test that exits 0. Accept 0 and 8; bail only
#   on anything else (1 = no checks reported yet, 2+ = a real gh/auth failure).
#   This mirrors `probe_failed_checks` in tools/postplan-harness/harness/ciwatch.py,
#   which gates on `returncode not in (0, 8)` for the same reason.
#
#   REQUIRED trailing `return 0`: capturing output with `|| rc=$?` and returning 0
#   on the clear path keeps this predicate from aborting a `set -euo pipefail`
#   caller. Every predicate in this file must return 0 on its clear path.
pr_red_check_hold() {
    local pr="$1"
    local raw rc=0
    raw=$("$GH_CMD" pr checks "$pr" --json name,bucket 2>/dev/null) || rc=$?
    # Fail-open on a genuine gh error; rc 8 carries the payload we want.
    if [ "$rc" -ne 0 ] && [ "$rc" -ne 8 ]; then
        return 0
    fi
    printf '%s' "$raw" | jq -r '.[] | select(.bucket == "fail") | select(.name != "human-signoff") | .name' \
        2>/dev/null || true
    return 0
}

# pr_aggregator_required
#   Returns 0 iff master's live branch protection lists $PR_ARMABLE_AGGREGATOR_CONTEXT
#   as a required status check; 1 otherwise. FAIL-CLOSED: any gh error, a missing
#   .protection object, or an empty contexts list returns 1, which keeps condition
#   (15) BLOCKING exactly as before this predicate existed. Uses the public
#   branches endpoint (.protection is populated for the repo owner's token; the
#   .../protection endpoint needs admin and is deliberately not used).
#   Boolean predicate: call it in an `if`, never capture it under set -e.
pr_aggregator_required() {
    local ctxs
    ctxs=$("$GH_CMD" api "repos/$REPO_SLUG/branches/master" \
        --jq '.protection.required_status_checks.contexts[]?' 2>/dev/null) || return 1
    grep -qxF -- "$PR_ARMABLE_AGGREGATOR_CONTEXT" <<< "$ctxs"
}
