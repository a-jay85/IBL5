#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/pr-cycle-watch.sh: per-PR skip predicates for bin/pr-cycle-tick. Sourced, never
# executed. Predicates return 0 for "true". No gh calls here: the tick fetches, this lib
# decides, so every predicate is testable from fixtures.
[[ -n "${_PCW_SOURCED:-}" ]] && return 0
_PCW_SOURCED=1
_PCW_LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=bin/lib/launchd-job.sh
source "$_PCW_LIB/launchd-job.sh"
# shellcheck source=bin/lib/ci-checks-green.sh
source "$_PCW_LIB/ci-checks-green.sh"

PCW_GRACE_MIN=30       # skip a PR whose last post-plan run finished this recently
# shellcheck disable=SC2034  # read by bin/pr-cycle-tick
PCW_MAX_PER_TICK=3     # PRs handed to one pr-cycle run (also its --max-ready)

# State dir: PR_CYCLE_WATCH_STATE_DIR when non-empty, else $HOME/.ibl5-pr-cycle-watch.
# Unset and empty both mean "use the default". rc 1 when neither it nor HOME is set.
pcw_state_dir() {
    if [[ -n "${PR_CYCLE_WATCH_STATE_DIR:-}" ]]; then
        printf '%s\n' "$PR_CYCLE_WATCH_STATE_DIR"; return 0
    fi
    [[ -n "${HOME:-}" ]] || return 1
    printf '%s\n' "$HOME/.ibl5-pr-cycle-watch"
}

# {check_runs:[...]} on stdin (one `gh api .../commits/<sha>/check-runs` page) ->
# prints pending | red | green | none. Unparseable input reads as pending, so a bad
# fetch skips the PR and never triggers a rescue.
pcw_check_verdict() {
    local json pending failed
    json="$(ccg_dedupe '[]' 2>/dev/null)" || { echo pending; return 0; }
    [[ "$(jq -r '.check_runs | length' <<< "$json" 2>/dev/null)" =~ ^[1-9][0-9]*$ ]] \
        || { echo none; return 0; }
    pending="$(ccg_pending <<< "$json")"
    [[ -z "$pending" ]] || { echo pending; return 0; }
    failed="$(ccg_failed <<< "$json")"
    if [[ -n "$failed" ]]; then echo red; else echo green; fi
}

# pcw_inflight <pr> <branch> <launchctl-snapshot>: 0 when a fleet rescue for the PR
# (com.ibl5.post-plan-fleet-<pr>, exact) or a worktree-fired post-plan-now for the
# branch (com.ibl5.postplan-now-<safe-slug>-<ts>-<pid>) is loaded.
pcw_inflight() {
    local pr="$1" branch="$2" snap="${3-}" _pid _status label
    while IFS=$'\t' read -r _pid _status label; do
        [[ "$label" == "com.ibl5.post-plan-fleet-$pr" ]] && return 0
    done <<< "$snap"
    ljob_postplan_now_live "$(ljob_safe_slug "$branch")" "$snap" >/dev/null
}

# pcw_recent_finish <branch> <out-dir>...: 0 when a harness run for this branch wrote
# <out-dir>/live-<safe-slug>-<ts>/result.json within PCW_GRACE_MIN minutes. result.json
# is written once, when the harness run ends, so its existence is the completion signal
# and its mtime is the finish time. Pass every checkout's out dir: a fleet run writes
# under the main checkout, a worktree-fired run under the worktree.
pcw_recent_finish() {
    local safe d hit
    safe="$(ljob_safe_slug "$1")"; shift
    [[ -n "$safe" ]] || return 1
    for d in "$@"; do
        [[ -d "$d" ]] || continue
        hit="$(find "$d" -mindepth 2 -maxdepth 2 -name result.json \
            -path "$d/live-$safe-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-*/result.json" \
            -mmin "-$PCW_GRACE_MIN" -print 2>/dev/null || true)"
        [[ -n "$hit" ]] && return 0
    done
    return 1
}

# Retry cap, keyed by (PR, head SHA). attempts/<pr> holds the head SHA of the last
# rescue the tick launched; dm-sent/<pr> holds the head SHA it last DM'd about.
pcw_attempted_head() { [[ -f "$1/attempts/$2" && "$(cat "$1/attempts/$2")" == "$3" ]]; }
pcw_record_attempt() { _pcw_write "$1/attempts/$2" "$3"; }
pcw_dm_due()         { ! [[ -f "$1/dm-sent/$2" && "$(cat "$1/dm-sent/$2")" == "$3" ]]; }
pcw_mark_dm()        { _pcw_write "$1/dm-sent/$2" "$3"; }
_pcw_write() {   # atomic: temp file then rename, so a killed tick never leaves half a SHA
    mkdir -p "$(dirname "$1")" && printf '%s\n' "$2" > "$1.tmp.$$" && mv -f "$1.tmp.$$" "$1"
}

# pcw_prune <state-dir> <newline-separated open PR numbers>: drop state for closed PRs.
pcw_prune() {
    local dir="$1" open="$2" f
    for f in "$dir"/attempts/* "$dir"/dm-sent/*; do
        [[ -f "$f" ]] || continue
        grep -qxF "$(basename "$f")" <<< "$open" || rm -f "$f"
    done
}
