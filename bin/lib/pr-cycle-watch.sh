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
# shellcheck disable=SC2034  # read by bin/pr-cycle-tick
PCW_STRIKE_CAP=2       # no-op rescues per (PR, head SHA) before the tick stops relaunching
# shellcheck disable=SC2034  # read by bin/pr-cycle-tick
# A launched worker's result file is overdue after 180 min: post-plan-fleet --wait tops out
# at 7200 s and each session at timeout 5400, plus Stage 2 and launchd start-up margin.
PCW_PENDING_MAX_AGE_S=10800

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
# fetch skips the PR and never triggers a rescue. human-signoff is dropped first. It is
# red by design on every feat: PR and no rescue can turn it green, so a PR whose only
# failure is the sign-off reads as green and is skipped.
pcw_check_verdict() {
    local json pending failed
    json="$(ccg_dedupe '["human-signoff"]' 2>/dev/null)" || { echo pending; return 0; }
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

# pcw_last_block <branch> <out-dir>...: for the newest harness run dir for this branch
# across the out dirs, prints "<run-dir>\t<why>". <why> is the "Why:" line of that run's
# blocked-ship.txt, capped at 200 chars, or empty when the run left no such file. Prints
# nothing when no run dir exists. Only the newest run counts, so an older block never
# stands in for a later failure of another kind.
pcw_last_block() {
    local safe d run newest="" why=""
    safe="$(ljob_safe_slug "$1")"; shift
    [[ -n "$safe" ]] || return 0
    for d in "$@"; do
        [[ -d "$d" ]] || continue
        while IFS= read -r run; do
            if [[ -z "$newest" || "${run##*/}" > "${newest##*/}" ]]; then newest="$run"; fi
        done < <(find "$d" -mindepth 1 -maxdepth 1 -type d \
            -name "live-$safe-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-*" 2>/dev/null)
    done
    [[ -n "$newest" ]] || return 0
    # sed quits on the first match itself; a `| head -1` would SIGPIPE under pipefail.
    if [[ -f "$newest/blocked-ship.txt" ]]; then
        why="$(sed -n '/^Why:/{s/^Why:[[:space:]]*//;p;q;}' "$newest/blocked-ship.txt" \
            | tr -d '\t\r' | cut -c1-200)"
    fi
    printf '%s\t%s\n' "$newest" "$why"
}

# Retry cap, keyed by (PR, head SHA). attempts/<pr> holds the head SHA of the last
# rescue the tick launched; dm-sent/<pr> holds the head SHA it last DM'd about.
pcw_attempted_head() { [[ -f "$1/attempts/$2" && "$(cat "$1/attempts/$2")" == "$3" ]]; }
pcw_record_attempt() { _pcw_write "$1/attempts/$2" "$3"; }
pcw_dm_due()         { ! [[ -f "$1/dm-sent/$2" && "$(cat "$1/dm-sent/$2")" == "$3" ]]; }
pcw_mark_dm()        { _pcw_write "$1/dm-sent/$2" "$3"; }
# declined/<pr> holds the head SHA that post-plan holdrepeat declined. A declined head is not
# an attempt: nothing ran, and a new push clears it by changing the SHA.
pcw_declined_head()  { [[ -f "$1/declined/$2" && "$(cat "$1/declined/$2")" == "$3" ]]; }
pcw_record_declined() { _pcw_write "$1/declined/$2" "$3"; }
# strikes/<pr> holds "<sha> <n>": rescues that never reached post-plan for that head.
pcw_strike_count() {   # prints n when strikes/<pr> holds this SHA, else 0
    local f="$1/strikes/$2" s="" n=""
    if [[ -f "$f" ]]; then read -r s n < "$f" || true; fi
    if [[ "$s" == "$3" && "$n" =~ ^[0-9]+$ ]]; then printf '%s\n' "$n"; else printf '0\n'; fi
}
pcw_add_strike() {     # writes "<sha> <count+1>" and prints the new count
    local n
    n=$(( $(pcw_strike_count "$1" "$2" "$3") + 1 ))
    _pcw_write "$1/strikes/$2" "$3 $n" && printf '%s\n' "$n"
}
# Per-kind DM ledger, same shape as dm-sent/: dm-<kind>/<pr> holds the SHA last DM'd about.
pcw_kind_dm_due()    { ! [[ -f "$1/dm-$2/$3" && "$(cat "$1/dm-$2/$3")" == "$4" ]]; }
pcw_kind_mark_dm()   { _pcw_write "$1/dm-$2/$3" "$4"; }
# pcw_pending_has <state-dir> <pr>: 0 when a launched, not yet settled rescue lists the PR.
# pending/<id> is "# result=<path> started=<epoch>" then one "<pr><TAB><sha>" line per PR.
pcw_pending_has() {
    local f
    for f in "$1"/pending/*; do
        [[ -f "$f" ]] || continue
        awk -F'\t' -v p="$2" 'NR > 1 && $1 == p { found = 1 } END { exit !found }' "$f" && return 0
    done
    return 1
}
_pcw_write() {   # atomic: temp file then rename, so a killed tick never leaves half a SHA
    mkdir -p "$(dirname "$1")" && printf '%s\n' "$2" > "$1.tmp.$$" && mv -f "$1.tmp.$$" "$1"
}

# pcw_prune <state-dir> <newline-separated open PR numbers>: drop state for closed PRs.
pcw_prune() {
    local dir="$1" open="$2" f
    for f in "$dir"/attempts/* "$dir"/dm-sent/* "$dir"/declined/* "$dir"/strikes/* \
             "$dir"/dm-declined/* "$dir"/dm-strikes/*; do
        [[ -f "$f" ]] || continue
        grep -qxF "$(basename "$f")" <<< "$open" || rm -f "$f"
    done
    # Result files of a settled entry are removed by reconcile. Sweep strays a day old; pending
    # entries are never pruned here, only settled by reconcile.
    [[ -d "$dir/results" ]] && find "$dir/results" -type f -mmin +1440 -delete 2>/dev/null
    return 0
}
