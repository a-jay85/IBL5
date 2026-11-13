#!/usr/bin/env bash
# bin/lib/text-hold-recheck.sh: decide whether a held PR may re-arm after a body edit.
# Sourced by bin/text-hold-recheck and bin/test-text-hold-recheck. No gh, no git, no network.
# The record grammar mirrors machine_hold_record() in tools/postplan-harness/harness/fidelity.py.
# shellcheck source=bin/lib/pr-armable.sh
. "$(dirname "${BASH_SOURCE[0]}")/pr-armable.sh"

THR_STICKY_MARKER='<!-- pr-ready-verdict -->'
THR_RECORD_RE='^<!-- pr-ready-holds: v1 diff=([0-9a-f]{40}) held=([0-9]+(:[A-Z][A-Z-]*)?(,[0-9]+(:[A-Z][A-Z-]*)?)*) -->$'

# thr_is_text_hold <token>: 0 only for a deterministic body-text hold.
thr_is_text_hold() {
    case "$1" in
        20) return 0 ;;
        18:MISSING|18:MALFORMED) return 0 ;;
        *) return 1 ;;  # non-text hold: never re-armable
    esac
}

# thr_pick_trusted_sticky <comments_json_file> <owner_login>: newest sticky the owner wrote.
# A comment by anyone else, or one whose last line is not the marker, is ignored.
thr_pick_trusted_sticky() {
    jq -r --arg o "$2" --arg m "$THR_STICKY_MARKER" \
        '[.[] | select(.user.login == $o) | select((.body // "") | rtrimstr("\n") | endswith($m))] | last | .body // empty' "$1"
}

# thr_parse_record <sticky_file>: print "<diff> <held>" or return 1 (unreadable record).
thr_parse_record() {
    local n line
    n=$(grep -c '^<!-- pr-ready-holds:' "$1" 2>/dev/null) || n=0
    [ "$n" = 1 ] || return 1  # zero or duplicate records: unreadable
    line=$(grep '^<!-- pr-ready-holds:' "$1")
    [[ "$line" =~ $THR_RECORD_RE ]] || return 1
    printf '%s %s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
}

# thr_decide <event> <record> <current_diff> <body> <flagged_keys> <ui_hold>
# prints "ARM cleared=<held>" or "NOARM <reason>". Every input it cannot vouch for is NOARM.
thr_decide() {
    local event="$1" record="$2" cur_diff="$3" body="$4" flagged="$5" ui_hold="$6"
    local rec_diff held tok
    [ "$event" = "edited" ] || { echo "NOARM event-not-edited"; return 0; }
    [ -n "$record" ] || { echo "NOARM no-record"; return 0; }
    rec_diff=${record%% *}; held=${record#* }
    [[ "$cur_diff" =~ ^[0-9a-f]{40}$ ]] || { echo "NOARM no-current-diff"; return 0; }
    [ "$cur_diff" = "$rec_diff" ] || { echo "NOARM diff-moved"; return 0; }
    for tok in ${held//,/ }; do
        thr_is_text_hold "$tok" || { echo "NOARM non-text-hold:$tok"; return 0; }
    done
    [ -z "$ui_hold" ] || { echo "NOARM ui-hold"; return 0; }
    [ -z "$(pr_merge_danger_hold "$body")" ] || { echo "NOARM merge-danger-still-held"; return 0; }
    [ -z "$(pr_removed_guards_hold "$body" "$flagged")" ] || { echo "NOARM removed-guards-still-held"; return 0; }
    echo "ARM cleared=$held"
}

# thr_cleared_comment <held>: the PR comment posted after a re-arm. Carries no HTML marker.
thr_cleared_comment() {
    printf 'Text-hold re-check: the edited PR body now passes held condition(s) %s, the diff is unchanged since the last post-plan run, and that run recorded no other hold, so auto-merge is armed again.\n' "$1"
}
