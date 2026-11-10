# shellcheck shell=bash
#
# bin/lib/human-signoff-classifier.sh — single source of truth for the
# .github/workflows/human-signoff.yml UI-PR sign-off classifier (ADR-0062),
# sourced by BOTH the workflow and the regression harness
# bin/test-human-signoff-classifier so the logic cannot drift.
#
# Usage: source "$(dirname "$0")/lib/human-signoff-classifier.sh"
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.
# It IS, however, sourced INTO a `set -euo pipefail` workflow step, so every
# function must be strict-mode safe.
# The hs_*fingerprint* functions pin human-approved to the PR diff (ADR-0062 Update).

# hs_pr_cleared <ui_verdict> <labels_csv> — 0 if the PR may merge (the UI
# verdict from bin/lib/ui-change-classifier.sh is not-ui, OR the PR carries the
# human-approved label), 1 if it is a UI PR still awaiting sign-off. Arming
# condition (8) and pr-armable.sh pr_ui_hold read the same classifier.
# Comma-wrap both sides so the label match is whole-name, never a substring.
hs_pr_cleared() {
    [ "$1" = not-ui ] && return 0
    case ",$2," in *,human-approved,*) return 0 ;; esac
    return 1
}

# hs_eval_pull_request <ui_verdict> <labels_csv> — the entry gate. 0 = cleared,
# 1 = needs sign-off (emits the GitHub ::error:: that reds the required check).
hs_eval_pull_request() {
    if hs_pr_cleared "$1" "$2"; then
        echo "Entry gate: PR cleared (UI verdict: $1)"
        return 0
    fi
    echo "::error title=Human sign-off required::This PR changes the UI ($1) and must be manually inspected by a maintainer, who then applies the 'human-approved' label. Auto-merge cannot satisfy this gate."
    return 1
}

# ---- Diff-pinned approval (ADR-0062 Update) ----
# The human-approved label covers one PR diff. The approving `labeled` run
# records the diff's patch-id in a github-actions[bot] comment carrying the
# marker below. Every later run on a UI PR holding the label recomputes the
# id and compares. The id covers changed lines only (no context lines), so a
# clean rebase keeps the id, even when master edits lines next to a hunk. A
# real diff change, a missing record, or any read/compute error drops the
# approval (fail closed).
# Marker value: 40 lowercase hex, or `revoked` (written when an approval drops).
HS_FP_MARKER_RE='\A<!-- hs-approved-diff: (?<v>[0-9a-f]{40}|revoked) -->'

# hs_is_fingerprint <s> — 0 iff <s> is exactly 40 lowercase hex chars.
hs_is_fingerprint() {
    [[ "${1:-}" =~ ^[0-9a-f]{40}$ ]]
}

# hs_diff_fingerprint <base_ref> <head_ref> — print the patch-id of the
# three-dot (merge-base) diff base...head, taken with -U0 so the id covers
# changed lines only. Master edits next to a hunk leave it unchanged, and
# patch-id ignores hunk line numbers, so a shifted hunk keeps its id too.
# --verbatim keeps whitespace in the id. rc 1 on a git error, an EMPTY diff
# (git patch-id prints nothing and exits 0 on empty input), or a malformed id.
hs_diff_fingerprint() {
    local diff id
    if ! diff=$(git diff --no-color --no-ext-diff --no-textconv --binary \
            --no-renames --diff-algorithm=myers -U0 "$1...$2" --); then
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

# hs_eval_approval <action> <event_label> <ui_verdict> <labels_csv> <current_fp> <comments_json_file>
# The workflow's entry gate. Sets HS_VERDICT and HS_DROP_REASON (globals).
#   pass   (rc 0) not a UI PR; no fingerprint work
#   hold   (rc 1) UI PR without the label; same annotation as today
#   record (rc 0) the event IS the human-approved label being applied
#   keep   (rc 0) UI + label, current fingerprint equals the recorded one
#   drop   (rc 1) UI + label, reason mismatch | missing | error
# Tightening only: every rc-0 path first passes hs_pr_cleared.
hs_eval_approval() {
    local action="$1" event_label="$2" verdict="$3" labels="$4" current="$5" comments="$6"
    local recorded rc=0
    HS_VERDICT=""
    HS_DROP_REASON=""
    if ! hs_pr_cleared "$verdict" "$labels"; then
        hs_eval_pull_request "$verdict" "$labels" || true
        HS_VERDICT="hold"
        return 1
    fi
    if [ "$verdict" = not-ui ]; then
        echo "Entry gate: PR cleared (UI verdict: not-ui)"
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

# ---- Approval-record wait (ADR-0062 Update 2026-11-02) ----
# A push that lands seconds after the label (an update-branch merge) starts a
# run that can read comments before the approving `labeled` run posts its
# record. These two functions let that run wait a bounded time for the record.
# Waiting never keeps the label by itself: only hs_eval_approval's `keep`, a
# current fingerprint equal to a github-actions[bot] record, does.

# hs_label_newer_than_marker <events_json_file> <comments_json_file> — read a
# flat array of issue events and a flat array of issue comments (GitHub REST
# shapes) and decide whether a would-be `missing` or `mismatch` drop should wait.
#   rc 0: a `labeled` event for human-approved exists, and no github-actions[bot]
#         hs marker (record or revoked) is strictly newer than the newest one
#   rc 1: no such labeled event, or a bot marker is strictly newer
#   rc 2: unreadable file, invalid JSON, not an array, or a bad timestamp
# A same-second tie waits; waiting is safe because keep still needs an FP match.
hs_label_newer_than_marker() {
    local val
    if ! val=$(jq -nr --arg re "$HS_FP_MARKER_RE" \
            --slurpfile ev "$1" --slurpfile cm "$2" '
            def arr: if type != "array" then error("payload is not an array") else . end;
            ($ev[0] | arr) as $e | ($cm[0] | arr) as $c
            | ([ $e[] | select(.event == "labeled" and .label.name == "human-approved")
                 | .created_at | fromdateiso8601 ] | max) as $lab
            | ([ $c[] | select(.user.login == "github-actions[bot]" and .user.type == "Bot")
                 | select((.body // "") | test($re))
                 | .created_at | fromdateiso8601 ] | max) as $mark
            | if $lab == null then "nowait"
              elif $mark == null or $lab >= $mark then "wait"
              else "nowait" end' 2>&1); then
        echo "hs_label_newer_than_marker: cannot read $1 or $2: $val" >&2
        return 2
    fi
    case "$val" in
        wait) return 0 ;;
        nowait) return 1 ;;
        *) return 2 ;;
    esac
}

# hs_await_record <attempts> <sleep_s> <refetch_fn> <action> <event_label> <ui_verdict> <labels_csv> <current_fp> <comments_json_file> <events_json_file>
# Call right after hs_eval_approval with the same arguments. Changes nothing
# unless the verdict is `drop missing` or `drop mismatch`. When hs_label_newer_than_marker says
# wait, it re-reads comments up to <attempts> times, <sleep_s> seconds apart,
# by calling "$refetch_fn" <comments_json_file> directly (never in a subshell),
# and re-runs hs_eval_approval after each read.
#   rc 0: verdict became keep (a matching bot record appeared)
#   rc 1: any drop. mismatch if a non-matching record appeared; error on a bad
#         argument, an events/comments read error, or a refetch failure;
#         missing or mismatch (the last re-read's reason) on timeout or when a newer bot marker appears
hs_await_record() {
    local attempts="$1" sleep_s="$2" refetch="$3" action="$4" event_label="$5"
    local verdict="$6" labels="$7" current="$8" comments="$9" events="${10}"
    local hs_await_i=0 nrc=0
    if [ "${HS_VERDICT:-}" != "drop" ] || { [ "${HS_DROP_REASON:-}" != "missing" ] && [ "${HS_DROP_REASON:-}" != "mismatch" ]; }; then
        if [ "${HS_VERDICT:-}" = "drop" ]; then return 1; fi
        return 0
    fi
    if ! [[ "$attempts" =~ ^[0-9]+$ ]] || ! [[ "$sleep_s" =~ ^[0-9]+$ ]]; then
        _hs_drop error
        return 1
    fi
    hs_label_newer_than_marker "$events" "$comments" || nrc=$?
    case "$nrc" in
        0) ;;
        1) echo "Entry gate: a bot marker is newer than the approving label; no wait"
           return 1 ;;
        *) _hs_drop error
           return 1 ;;
    esac
    while [ "$hs_await_i" -lt "$attempts" ]; do
        hs_await_i=$((hs_await_i + 1))
        echo "Entry gate: approving label is newer than every record; re-read $hs_await_i/$attempts in ${sleep_s}s"
        sleep "$sleep_s"
        if ! "$refetch" "$comments"; then
            _hs_drop error
            return 1
        fi
        if hs_eval_approval "$action" "$event_label" "$verdict" "$labels" "$current" "$comments"; then
            if [ "$HS_VERDICT" = "keep" ]; then return 0; fi
            _hs_drop error
            return 1
        fi
        if [ "$HS_VERDICT" != "drop" ] || { [ "$HS_DROP_REASON" != "missing" ] && [ "$HS_DROP_REASON" != "mismatch" ]; }; then return 1; fi
        nrc=0
        hs_label_newer_than_marker "$events" "$comments" || nrc=$?
        case "$nrc" in
            0) ;;
            1) echo "Entry gate: a newer bot marker appeared; stop waiting"
               return 1 ;;
            *) _hs_drop error
               return 1 ;;
        esac
    done
    echo "Entry gate: no matching approval record after $attempts re-reads; dropping ($HS_DROP_REASON)"
    return 1
}

# hs_newest_label_at <events_json_file> — print created_at of the newest
# `labeled` event for human-approved. The approving run captures it at eval
# time as its own label event. rc 1: none. rc 2: unreadable, not an array,
# or a bad timestamp.
hs_newest_label_at() {
    local val
    if ! val=$(jq -r '
            if type != "array" then error("events payload is not an array") else . end
            | [ .[] | select(.event == "labeled" and .label.name == "human-approved") ]
            | sort_by(.created_at | fromdateiso8601) | last | .created_at // ""' "$1" 2>&1); then
        echo "hs_newest_label_at: cannot read $1: $val" >&2
        return 2
    fi
    [ -n "$val" ] || return 1
    printf '%s\n' "$val"
}

# hs_record_write_ok <approved_at> <labels_csv> <events_json_file> <comments_json_file>
# Decide, at write time, whether the approving run may post its record.
#   rc 0: write
#   rc 1: skip. A newer human-approved label event exists (its run owns the
#         record), a github-actions[bot] revoked marker is at or after
#         <approved_at>, or the label is no longer held
#   rc 2: skip on error. Bad <approved_at>, or an unreadable/invalid payload
hs_record_write_ok() {
    local approved_at="$1" labels="$2" val
    if ! val=$(jq -nr --arg at "$approved_at" --arg re "$HS_FP_MARKER_RE" \
            --slurpfile ev "$3" --slurpfile cm "$4" '
            def arr: if type != "array" then error("payload is not an array") else . end;
            ($at | fromdateiso8601) as $t
            | ($ev[0] | arr) as $e | ($cm[0] | arr) as $c
            | ([ $e[] | select(.event == "labeled" and .label.name == "human-approved")
                 | .created_at | fromdateiso8601 | select(. > $t) ] | length) as $newer_labels
            | ([ $c[] | select(.user.login == "github-actions[bot]" and .user.type == "Bot")
                 | (.body // "") as $b | select($b | test($re))
                 | select(($b | capture($re) | .v) == "revoked")
                 | .created_at | fromdateiso8601 | select(. >= $t) ] | length) as $newer_revokes
            | if $newer_labels > 0 then "newer-label"
              elif $newer_revokes > 0 then "revoked"
              else "ok" end' 2>&1); then
        echo "hs_record_write_ok: cannot decide: $val" >&2
        return 2
    fi
    case "$val" in
        ok) ;;
        newer-label|revoked) echo "Record write: skip ($val)"; return 1 ;;
        *) return 2 ;;
    esac
    if ! grep -q ',human-approved,' <<< ",$labels,"; then
        echo "Record write: skip (label no longer held)"
        return 1
    fi
    return 0
}

# _hs_drop <reason> — set the drop verdict and emit the annotation that reds
# the required check.
# shellcheck disable=SC2034  # HS_VERDICT and HS_DROP_REASON are read by the sourcing caller
_hs_drop() {
    HS_VERDICT="drop"
    HS_DROP_REASON="$1"
    echo "::error title=Human sign-off required::The 'human-approved' label no longer covers this PR's diff (${1}). The label will be removed; a maintainer must review the current diff and re-apply it."
}
