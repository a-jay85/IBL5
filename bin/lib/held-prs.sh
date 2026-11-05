#!/usr/bin/env bash
# shellcheck shell=bash
# Held-PR and sticky-verdict logic for `bin/pr-triage`. Pure functions: no gh calls.
# bin/pr-triage fetches the PR list, check-runs and comments and passes them in.
# Fixture-tested by bin/test-pr-triage (cases H1-H13, V1-V10).

# shellcheck source=bin/lib/ci-checks-green.sh
source "$(dirname "${BASH_SOURCE[0]}")/ci-checks-green.sh"

HP_STICKY_MARKER='<!-- pr-ready-verdict -->'

# hp_is_held <pr-json> <viewer-login>: 0 when the PR is the viewer's, not a draft,
# and auto-merge is not enabled. A missing author or an empty login is not held.
hp_is_held() {
    jq -e --arg me "$2" '$me != "" and (.author.login // "") == $me
        and (.isDraft // false) == false and .autoMergeRequest == null' \
        >/dev/null 2>&1 <<< "$1"
}

# hp_pick_sticky: stdin = comments JSON, one or more arrays (gh api --paginate
# concatenates pages). Prints the body of the LAST comment carrying the marker.
hp_pick_sticky() {
    jq -rs --arg m "$HP_STICKY_MARKER" \
        '[ .[][]? | select((.body // "") | contains($m)) ] | last | .body // empty' \
        2>/dev/null || true
}

# hp_sticky_reasons <sticky-body>: one line per `- (N) name — reason` bullet in the
# `Auto-merge held:` block. An armed banner, no block, or the
# `- no arming decision recorded` bullet prints nothing.
hp_sticky_reasons() {
    local line bare in_block=0
    while IFS= read -r line; do
        line="${line%$'\r'}"
        if [ "$in_block" = 0 ]; then
            bare="${line//\*/}"
            if [ "$bare" = "Auto-merge held:" ]; then in_block=1; fi
            continue
        fi
        case "$line" in
            "- ("[0-9]*) printf '%s\n' "${line#- }" ;;
            "- "*) ;;
            *) break ;;
        esac
    done <<< "$1"
    return 0
}

# hp_verdict_banner <sticky-body>: the sticky's banner, the first line carrying
# `· auto-merge armed` or `· auto-merge held`. Prints nothing when no line matches.
hp_verdict_banner() {
    local line
    while IFS= read -r line; do
        line="${line%$'\r'}"
        case "$line" in
            *"· auto-merge armed"*|*"· auto-merge held"*) printf '%s\n' "$line"; return 0 ;;
        esac
    done <<< "$1"
    return 0
}

# hp_verdict_holds <sticky-body> <comments-status>: space-separated HOLDS tokens the
# latest pr-ready-verdict sticky imposes. Prints nothing ONLY for a READY or READY WITH
# NOTES banner that says `· auto-merge armed` and carries no `Auto-merge held:` reason.
# Every other shape is a hold (fail closed). <comments-status> is `failed` when the
# comments read failed.
hp_verdict_holds() {
    local body="$1" banner bare word="" reasons line num armed=0 out=""
    if [ "${2:-ok}" = "failed" ]; then printf 'verdict:fetch-failed\n'; return 0; fi
    if [ -z "$body" ]; then printf 'verdict:none\n'; return 0; fi
    banner="$(hp_verdict_banner "$body")"
    bare="${banner//\*/}"
    bare="${bare#LATEST VERDICT: }"
    case "$bare" in
        "NOT READY "*) word="NOT READY" ;;
        "READY WITH NOTES "*) word="READY WITH NOTES" ;;
        "READY "*) word="READY" ;;
    esac
    if [ -z "$word" ]; then printf 'verdict:unreadable\n'; return 0; fi
    if [ "$word" = "NOT READY" ]; then out="verdict:not-ready "; fi
    case "$banner" in
        *"· auto-merge armed"*) armed=1 ;;
    esac
    reasons="$(hp_sticky_reasons "$body")"
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        num="${line#(}"
        num="${num%%)*}"
        out="${out}verdict-hold:${num} "
    done <<< "$reasons"
    if [ "$armed" != "1" ] && [ -z "$reasons" ]; then out="${out}verdict-hold:undecided "; fi
    out="${out% }"
    if [ -n "$out" ]; then printf '%s\n' "$out"; fi
    return 0
}

# hp_check_reasons: stdin = check-runs JSON ({check_runs:[...]}). Drops
# human-signoff first, then reports red and pending checks.
hp_check_reasons() {
    local json failed pending
    json="$(ccg_dedupe '["human-signoff"]' 2>/dev/null)" || return 0
    failed="$(ccg_failed <<< "$json")"
    pending="$(ccg_pending <<< "$json")"
    if [ -n "$failed" ]; then printf 'red checks: %s\n' "$failed"; fi
    if [ -n "$pending" ]; then printf 'pending checks: %s\n' "$pending"; fi
    return 0
}

# hp_live_reasons <mss> <clearance> <holds> <check-runs-json>: live-state reasons.
# <holds> is pr-triage's space-separated HOLDS string ("-" when nothing fired).
hp_live_reasons() {
    local tok
    if [ "$1" = "DIRTY" ]; then echo "merge conflict (DIRTY)"; fi
    if [ "$2" = "HELD" ]; then echo "manual-testing rows unresolved"; fi
    hp_check_reasons <<< "$4"
    # shellcheck disable=SC2086  # deliberate word split of the HOLDS tokens
    for tok in $3; do
        if [ "$tok" != "-" ]; then echo "$tok"; fi
    done
    return 0
}

# hp_dedupe: stdin reason lines -> same order, blank lines and exact repeats dropped.
hp_dedupe() {
    local line seen=$'\n'
    while IFS= read -r line; do
        if [ -z "$line" ]; then continue; fi
        case "$seen" in *$'\n'"$line"$'\n'*) continue ;; esac
        seen="$seen$line"$'\n'
        printf '%s\n' "$line"
    done
    return 0
}

# hp_format_line <num> <url> <title> <reason-lines>: one report line. Columns are
# split by exactly two spaces (pr-triage's awk -F"  +" contract), so runs of spaces
# inside the title and reasons collapse to one. Reasons join with "; ".
# No reasons -> "unknown".
hp_format_line() {
    local r out="" title
    while IFS= read -r r; do
        if [ -n "$r" ]; then out="${out:+$out; }$r"; fi
    done <<< "$(printf '%s\n' "$4" | hp_dedupe)"
    title="$(printf '%s' "$3" | tr -s ' ')"
    out="$(printf '%s' "${out:-unknown}" | tr -s ' ')"
    printf '#%s  %s  %s  %s\n' "$1" "$2" "$title" "$out"
}
