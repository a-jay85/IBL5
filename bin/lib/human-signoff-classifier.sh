# shellcheck shell=bash
#
# bin/lib/human-signoff-classifier.sh — single source of truth for the
# .github/workflows/human-signoff.yml feature-PR sign-off classifier (ADR-0062),
# sourced by BOTH the workflow and the regression harness
# bin/test-human-signoff-classifier so the logic cannot drift.
#
# Usage: source "$(dirname "$0")/lib/human-signoff-classifier.sh"
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.
# It IS, however, sourced INTO a `set -euo pipefail` workflow step, so every
# function must be strict-mode safe.
# The hs_*fingerprint* functions pin human-approved to the PR diff (ADR-0062 Update).

# hs_is_feat_title <title> — 0 if the title is a conventional-commit feature
# (feat:, feat(scope):, feat!:, feat(scope)!:), 1 otherwise. Mirrors the grep in
# /post-plan Phase 6.5 condition (8) and pr-armable.sh pr_feat_hold.
hs_is_feat_title() {
    printf '%s' "$1" | grep -qiE '^feat(\([^)]*\))?!?:'
}

# hs_pr_cleared <title> <labels_csv> — 0 if the PR may merge (non-feat, OR feat
# with the human-approved label), 1 if it is a feat: PR still awaiting sign-off.
# Comma-wrap both sides so the label match is whole-name, never a substring.
hs_pr_cleared() {
    local title="$1" labels="$2"
    if ! hs_is_feat_title "$title"; then
        return 0
    fi
    if printf '%s' ",$labels," | grep -q ',human-approved,'; then
        return 0
    fi
    return 1
}

# hs_eval_pull_request <title> <labels_csv> — the entry gate. 0 = cleared,
# 1 = needs sign-off (emits the GitHub ::error:: that reds the required check).
hs_eval_pull_request() {
    if hs_pr_cleared "$1" "$2"; then
        echo "Entry gate: PR cleared (title: $1)"
        return 0
    fi
    echo "::error title=Human sign-off required::This feature PR must be manually inspected by a maintainer, who then applies the 'human-approved' label. Auto-merge cannot satisfy this gate."
    return 1
}

# ---- Diff-pinned approval (ADR-0062 Update) ----
# The human-approved label covers one PR diff. The approving `labeled` run
# records the diff's patch-id in a github-actions[bot] comment carrying the
# marker below. Every later run on a feat: PR holding the label recomputes the
# id and compares. A clean rebase keeps the id. A real diff change, a missing
# record, or any read/compute error drops the approval (fail closed).
# Marker value: 40 lowercase hex, or `revoked` (written when an approval drops).
HS_FP_MARKER_RE='<!-- hs-approved-diff: (?<v>[0-9a-f]{40}|revoked) -->'

# hs_is_fingerprint <s> — 0 iff <s> is exactly 40 lowercase hex chars.
hs_is_fingerprint() {
    [[ "${1:-}" =~ ^[0-9a-f]{40}$ ]]
}

# hs_diff_fingerprint <base_ref> <head_ref> — print the patch-id of the
# three-dot (merge-base) diff base...head. rc 1 on a git error, an EMPTY diff
# (git patch-id prints nothing and exits 0 on empty input), or a malformed id.
hs_diff_fingerprint() {
    local diff id
    if ! diff=$(git diff --no-color --no-ext-diff --no-textconv --binary \
            --no-renames --diff-algorithm=myers -U3 "$1...$2" --); then
        return 1
    fi
    [ -n "$diff" ] || return 1
    if ! id=$(git patch-id --verbatim <<< "$diff"); then
        return 1
    fi
    id="${id%% *}"
    hs_is_fingerprint "$id" || return 1
    printf '%s\n' "$id"
}

# hs_recorded_fingerprint <comments_json_file> — read a flat JSON array of
# issue comments (GitHub REST shape) and print the newest marker value written
# by github-actions[bot]. Comments from any other author are ignored, because
# anyone can comment on a public PR.
#   rc 0: newest marker is a fingerprint (printed)
#   rc 1: no bot marker, or the newest bot marker is `revoked`
#   rc 2: unreadable file, invalid JSON, or not an array
hs_recorded_fingerprint() {
    local val
    if ! val=$(jq -r --arg re "$HS_FP_MARKER_RE" '
            if type != "array" then error("comments payload is not an array") else . end
            | [ sort_by(.created_at // "")[]
                | select(.user.login == "github-actions[bot]" and .user.type == "Bot")
                | (.body // "") | capture($re) | .v ]
            | last // ""' "$1" 2>&1); then
        echo "hs_recorded_fingerprint: cannot read $1: $val" >&2
        return 2
    fi
    if [ -z "$val" ] || [ "$val" = "revoked" ]; then
        return 1
    fi
    hs_is_fingerprint "$val" || return 2
    printf '%s\n' "$val"
}

# hs_fingerprint_matches <current> <recorded> — 0 iff both are well-formed and
# equal. A malformed or empty side never matches.
hs_fingerprint_matches() {
    hs_is_fingerprint "${1:-}" && hs_is_fingerprint "${2:-}" && [ "$1" = "$2" ]
}

# hs_record_comment_body <fingerprint> <head_sha> — the bot comment the
# approving run posts. Its first line is the marker hs_recorded_fingerprint reads.
hs_record_comment_body() {
    hs_is_fingerprint "${1:-}" || return 1
    printf '<!-- hs-approved-diff: %s -->\n' "$1"
    # shellcheck disable=SC2016  # the backticks are literal Markdown, not expansions
    printf 'Recorded the approved diff for `human-approved` at head `%s` (fingerprint `%s`). A push that keeps this diff, such as a clean rebase, keeps the label. A push that changes the diff removes it.\n' "$2" "$1"
}

# hs_drop_comment_body <reason> <head_sha> — the bot comment posted when an
# approval drops. The `revoked` marker makes the drop the newest record, so an
# older fingerprint can never re-validate the label.
hs_drop_comment_body() {
    local why
    case "${1:-}" in
        mismatch) why="the PR diff changed after the label was applied" ;;
        missing) why="no recorded approval fingerprint exists for this label" ;;
        error) why="the diff fingerprint could not be computed or read, so the approval cannot be confirmed" ;;
        *) return 1 ;;
    esac
    printf '<!-- hs-approved-diff: revoked -->\n'
    # shellcheck disable=SC2016  # the backticks are literal Markdown, not expansions
    printf 'Removed `human-approved` at head `%s`: %s. Review the current diff, then re-apply the label to approve it.\n' "$2" "$why"
}

# hs_eval_approval <action> <event_label> <title> <labels_csv> <current_fp> <comments_json_file>
# The workflow's entry gate. Sets HS_VERDICT and HS_DROP_REASON (globals).
#   pass   (rc 0) not a feat: PR; no fingerprint work
#   hold   (rc 1) feat: PR without the label; same annotation as today
#   record (rc 0) the event IS the human-approved label being applied
#   keep   (rc 0) feat: + label, current fingerprint equals the recorded one
#   drop   (rc 1) feat: + label, reason mismatch | missing | error
# Tightening only: every rc-0 path first passes hs_pr_cleared.
hs_eval_approval() {
    local action="$1" event_label="$2" title="$3" labels="$4" current="$5" comments="$6"
    local recorded rc=0
    HS_VERDICT=""
    HS_DROP_REASON=""
    if ! hs_pr_cleared "$title" "$labels"; then
        hs_eval_pull_request "$title" "$labels" || true
        HS_VERDICT="hold"
        return 1
    fi
    if ! hs_is_feat_title "$title"; then
        echo "Entry gate: PR cleared (title: $title)"
        HS_VERDICT="pass"
        return 0
    fi
    if ! hs_is_fingerprint "$current"; then
        _hs_drop error
        return 1
    fi
    if [ "$action" = "labeled" ] && [ "$event_label" = "human-approved" ]; then
        echo "Entry gate: human-approved applied; recording diff fingerprint $current"
        HS_VERDICT="record"
        return 0
    fi
    recorded=$(hs_recorded_fingerprint "$comments") || rc=$?
    case "$rc" in
        0)
            if hs_fingerprint_matches "$current" "$recorded"; then
                echo "Entry gate: diff fingerprint $current matches the approved diff"
                HS_VERDICT="keep"
                return 0
            fi
            _hs_drop mismatch ;;
        1) _hs_drop missing ;;
        *) _hs_drop error ;;
    esac
    return 1
}

# _hs_drop <reason> — set the drop verdict and emit the annotation that reds
# the required check.
# shellcheck disable=SC2034  # HS_VERDICT and HS_DROP_REASON are read by the sourcing caller
_hs_drop() {
    HS_VERDICT="drop"
    HS_DROP_REASON="$1"
    echo "::error title=Human sign-off required::The 'human-approved' label no longer covers this PR's diff (${1}). The label will be removed; a maintainer must review the current diff and re-apply it."
}
