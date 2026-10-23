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
# shellcheck disable=SC2034  # read by bin/pr-cycle-tick
# A post-plan-running label this old, with no live run anywhere, is stale and the tick
# removes it. 2x the per-phase post-plan cap MAX_PP_SECS=5400 in bin/automouse/run (the
# observed maximum wall time is about 34 min). bin/test-pr-cycle-tick pins the 2x ratio.
PCW_STALE_LABEL_MIN=180
# shellcheck disable=SC2034  # read by bin/pr-cycle-tick
# DIRTY rescue launches per PR per local calendar day. Bounds the conflict re-try spend.
PCW_CONFLICT_RETRY_DAILY_CAP=2
# shellcheck disable=SC2034  # read by bin/pr-cycle-tick
# Gate-timeout reruns per (PR, head SHA). Kept in gate-reruns/, apart from strikes/, so a
# rerun never spends the no-op rescue cap.
PCW_GATE_RERUN_CAP=2
# shellcheck disable=SC2034  # read by bin/pr-cycle-tick
PCW_GATE_CHECK="All checks green"         # job name of .github/workflows/all-checks-green.yml
# shellcheck disable=SC2034  # read by bin/pr-cycle-tick
PCW_GATE_WORKFLOW="all-checks-green.yml"  # the --workflow value for the run-list call

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

# pcw_gate_failed: {check_runs:[...]} on stdin. rc 0 when the latest check-run named
# $PCW_GATE_CHECK is completed with conclusion failure. Any other state, or bad JSON, is 1.
pcw_gate_failed() {
    local json
    json="$(ccg_dedupe '[]' 2>/dev/null)" || return 1
    jq -e --arg g "$PCW_GATE_CHECK" \
        'any(.check_runs[]; .name == $g and .status == "completed" and .conclusion == "failure")' \
        >/dev/null 2>&1 <<< "$json"
}
# pcw_gate_run_pick <sha>: the run-list JSON (fields databaseId,conclusion,status,headSha,createdAt)
# on stdin. Prints "<databaseId> <status> <conclusion>" for the newest run whose headSha is
# <sha>. An unsettled run has conclusion "" (live shape) or null; both print "null". rc 1 on
# bad JSON or no such run.
pcw_gate_run_pick() {
    local out
    out="$(jq -r --arg s "$1" '[.[] | select(.headSha == $s)] | sort_by(.createdAt) | last
        | select(. != null) | "\(.databaseId) \(.status) \(if (.conclusion // "") == "" then "null" else .conclusion end)"' 2>/dev/null)" || return 1
    [[ "$out" =~ ^[0-9]+\ [a-z_]+\ [a-z_]+$ ]] || return 1
    printf '%s\n' "$out"
}
# pcw_gate_timeout_anchors: the failed-log text of a run (--log-failed) on stdin. Prints the anchor
# names from the first "TIMEOUT: WAIT: anchors not reported/completed: <a>, <b> <dash>
# failing closed after Ns" line, one per line. rc 1 when no line carries that marker or the
# list parses empty. The dash is U+2014; the sed matches it as one non-space run, so the
# parse does not depend on the locale.
pcw_gate_timeout_anchors() {
    local line list
    line="$(grep -F 'TIMEOUT: WAIT: anchors not reported/completed: ' | sed -n '1p')" || return 1
    [[ -n "$line" ]] || return 1
    list="${line#*anchors not reported/completed: }"
    list="$(sed -E 's/ [^ ]+ failing closed after [0-9]+s.*$//' <<< "$list")"
    list="$(jq -Rr 'split(", ") | map(sub("^ +";"") | sub(" +$";"")) | map(select(length > 0)) | .[]' <<< "$list")" || return 1
    [[ -n "$list" ]] || return 1
    printf '%s\n' "$list"
}
# pcw_anchors_green <names>: {check_runs:[...]} on stdin, <names> newline separated. rc 0
# only when every named anchor's latest check-run is completed with conclusion success.
# Missing, pending, failure, cancelled and skipped all return 1.
pcw_anchors_green() {
    local json
    json="$(ccg_dedupe '[]' 2>/dev/null)" || return 1
    jq -e --arg names "$1" '
        . as $root
        | ($names | split("\n") | map(select(length > 0))) as $want
        | ($want | length) > 0
          and all($want[]; . as $n | any($root.check_runs[]; .name == $n
              and .status == "completed" and .conclusion == "success"))' \
        >/dev/null 2>&1 <<< "$json"
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

# Stale post-plan-running label predicates. Five of the six are text-in / rc-out;
# pcw_live_out_dir_fresh reads the out dirs with find. The tick does the strict fetches
# (gh, launchctl, ps, lsof) and passes the text here.
# Return codes: 0 = the condition that KEEPS the label is true (a run is live, the label
# is too young, the out dir is fresh); 1 = evaluated and false; 2 = unevaluable (empty
# or malformed input). The tick treats 0 and 2 alike (keep) and removes the label only
# when every predicate returned 1, so empty input fails closed without a fetch here.

# pcw_label_age_min <labeled-at-iso8601>: prints the age in whole minutes of a GitHub
# created_at value (2026-10-18T02:44:37Z) and returns 0. Returns 2 and prints nothing
# when the argument is empty or does not parse. A negative age (clock skew) prints 0.
pcw_label_age_min() {
    local epoch age
    [[ -n "${1-}" ]] || return 2
    epoch="$(jq -rn --arg t "$1" '$t | fromdateiso8601' 2>/dev/null)" || return 2
    [[ "$epoch" =~ ^[0-9]+$ ]] || return 2
    age=$(( ( $(date +%s) - epoch ) / 60 ))
    (( age < 0 )) && age=0
    printf '%s\n' "$age"
}

# pcw_label_too_young <labeled-at-iso8601>: 0 when the label is younger than
# PCW_STALE_LABEL_MIN, 1 when at or above it (exactly 180 is not too young), 2 when the
# age is unevaluable.
pcw_label_too_young() {
    local age
    age="$(pcw_label_age_min "${1-}")" || return 2
    (( age < PCW_STALE_LABEL_MIN ))
}

# pcw_postplan_argv_live <worktree-root-or-empty> <safe-slug> <ps-text> [exclude-pids]:
# ps-text is `ps -axww -o pid=,command=`. 0 when a non-excluded process argv names the
# worktree root (boundary on both sides, so /x/foo never matches /x/foo-bar), the
# post-plan-now-<slug>-<ts>- log/label token, or the live-<slug>-<date>- run-dir token.
# bin/post-plan-now runs the harness and the skill fallback as children of one
# `/bin/bash -lc "$CMD"` whose argv carries all three. 1 = no match, 2 = empty ps-text
# or empty slug. exclude-pids is a space-separated pid list (the tick passes "$$ $PPID").
pcw_postplan_argv_live() {
    local root="${1-}" safe="${2-}" ps_txt="${3-}" exclude="${4-}"
    local esc_root="" root_re="" slug_re pid cmd
    [[ -n "$ps_txt" && -n "$safe" ]] || return 2
    if [[ -n "$root" ]]; then
        esc_root="$(sed 's/[][\.*^$+?(){}|]/\\&/g' <<< "$root")"
        root_re="(^|[[:space:]\"'=:])${esc_root}(\$|[[:space:]/\"';&|)])"
    fi
    slug_re="post-plan-now-${safe}-[0-9]{8}-[0-9]{6}-|live-${safe}-[0-9]{8}-"
    while read -r pid cmd; do
        [[ -n "$pid" ]] || continue
        [[ " $exclude " == *" $pid "* ]] && continue
        if [[ -n "$root_re" ]] && grep -qE -- "$root_re" <<< "$cmd"; then return 0; fi
        if grep -qE -- "$slug_re" <<< "$cmd"; then return 0; fi
    done <<< "$ps_txt"
    return 1
}

# pcw_claude_print_pids <ps-text>: prints, one per line, the pids of headless `claude`
# processes (-p or --print as a separate word). 0 when it printed at least one, 1 when
# none, 2 on empty text. Interactive claude sessions are excluded on purpose, so an idle
# session sitting in a worktree cannot pin the label forever.
pcw_claude_print_pids() {
    local ps_txt="${1-}" pid cmd found=1
    [[ -n "$ps_txt" ]] || return 2
    while read -r pid cmd; do
        [[ -n "$pid" ]] || continue
        if grep -qE -- '(^|/)claude([[:space:]]|$)' <<< "$cmd" \
            && grep -qE -- '(^|[[:space:]])(-p|--print)([[:space:]]|$)' <<< "$cmd"; then
            printf '%s\n' "$pid"
            found=0
        fi
    done <<< "$ps_txt"
    return "$found"
}

# pcw_cwd_in_worktree <worktree-root> <lsof-Fn-text>: 0 when any n<path> line equals the
# root or sits under <root>/, 1 otherwise, 2 when the root or the text is empty.
pcw_cwd_in_worktree() {
    local root="${1-}" txt="${2-}" line
    [[ -n "$root" && -n "$txt" ]] || return 2
    while IFS= read -r line; do
        [[ "$line" == n* ]] || continue
        line="${line#n}"
        [[ "$line" == "$root" || "$line" == "$root"/* ]] && return 0
    done <<< "$txt"
    return 1
}

# pcw_live_out_dir_fresh <branch> <out-dir>...: 2 when none of the out dirs exists.
# Otherwise picks the newest live-<safe>-<date>-* run dir by name across the existing
# dirs and returns 0 when anything inside it (the dir included) was written within
# PCW_STALE_LABEL_MIN minutes, 1 when not or when no run dir exists.
pcw_live_out_dir_fresh() {
    local safe d run newest="" any=0
    safe="$(ljob_safe_slug "$1")"; shift
    [[ -n "$safe" ]] || return 2
    for d in "$@"; do
        [[ -d "$d" ]] || continue
        any=1
        while IFS= read -r run; do
            if [[ -z "$newest" || "${run##*/}" > "${newest##*/}" ]]; then newest="$run"; fi
        done < <(find "$d" -mindepth 1 -maxdepth 1 -type d \
            -name "live-$safe-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-*" 2>/dev/null)
    done
    (( any )) || return 2
    [[ -n "$newest" ]] || return 1
    [[ -n "$(find "$newest" -mmin "-$PCW_STALE_LABEL_MIN" -print 2>/dev/null)" ]]
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

# Retry cap, keyed by (PR, retry key). The key is the PR's patch-id (pcw_patch_id), which a
# clean rebase keeps. When the patch-id cannot be computed the tick uses the head SHA.
# attempts/<pr> holds the key of the last rescue the tick launched; dm-sent/<pr> holds the
# key it last DM'd about. Each reader takes an optional head SHA as a second match: files
# written before patch-id keying hold a bare head SHA and keep matching that head.
_pcw_holds() {   # <file> <key> [<sha>]: 0 when the file's value equals the key or the sha
    local v
    [[ -f "$1" ]] || return 1
    v="$(cat "$1")"
    [[ "$v" == "$2" || ( -n "${3:-}" && "$v" == "${3:-}" ) ]]
}
pcw_attempted_head() { _pcw_holds "$1/attempts/$2" "$3" "${4:-}"; }
pcw_record_attempt() { _pcw_write "$1/attempts/$2" "$3"; }
pcw_dm_due()         { ! _pcw_holds "$1/dm-sent/$2" "$3" "${4:-}"; }
pcw_mark_dm()        { _pcw_write "$1/dm-sent/$2" "$3"; }
# declined/<pr> holds the key post-plan holdrepeat declined. A declined key is not an
# attempt: nothing ran, and a push that changes the PR's diff clears it by changing the key.
pcw_declined_head()  { _pcw_holds "$1/declined/$2" "$3" "${4:-}"; }
pcw_record_declined() { _pcw_write "$1/declined/$2" "$3"; }
# strikes/<pr> holds "<key> <n>": rescues that never reached post-plan for that key.
# _pcw_keyed_count <file> <key> [<alt-key>]: the n of a "<key> <n>" ledger line when its key
# equals <key> or <alt-key>, else 0. Shared by strikes/ and gate-reruns/.
_pcw_keyed_count() {
    local s="" n=""
    if [[ -f "$1" ]]; then read -r s n < "$1" || true; fi
    if [[ -n "$s" && ( "$s" == "$2" || "$s" == "${3:-}" ) && "$n" =~ ^[0-9]+$ ]]; then
        printf '%s\n' "$n"
    else
        printf '0\n'
    fi
}
pcw_strike_count() {   # <dir> <pr> <key> [<sha>]: prints n when strikes/<pr> holds the key or sha, else 0
    _pcw_keyed_count "$1/strikes/$2" "$3" "${4:-}"
}
pcw_add_strike() {     # <dir> <pr> <key> [<sha>]: writes "<key> <count+1>" and prints the new count
    local n
    n=$(( $(pcw_strike_count "$1" "$2" "$3" "${4:-}") + 1 ))
    _pcw_write "$1/strikes/$2" "$3 $n" && printf '%s\n' "$n"
}
# gate-reruns/<pr> holds "<head-sha> <n>": gate-timeout reruns issued for that head. A new
# head SHA resets the count, because the gate re-runs on its own for a new push.
pcw_gate_rerun_count() { _pcw_keyed_count "$1/gate-reruns/$2" "$3"; }   # <dir> <pr> <sha>
pcw_add_gate_rerun() {  # <dir> <pr> <sha>: writes "<sha> <count+1>", prints the new count
    local n
    n=$(( $(pcw_gate_rerun_count "$1" "$2" "$3") + 1 ))
    _pcw_write "$1/gate-reruns/$2" "$3 $n" && printf '%s\n' "$n"
}
# Per-kind DM ledger, same shape as dm-sent/: dm-<kind>/<pr> holds the key last DM'd about.
# Kinds: declined, strikes, patchid. The patchid ledger stores the head SHA, because it
# fires exactly when there is no patch-id.
pcw_kind_dm_due()    { ! _pcw_holds "$1/dm-$2/$3" "$4" "${5:-}"; }
pcw_kind_mark_dm()   { _pcw_write "$1/dm-$2/$3" "$4"; }
# pcw_pending_has <state-dir> <pr>: 0 when a launched, not yet settled rescue lists the PR.
# pending/<id> is "# result=<path> started=<epoch>" then one "<pr><TAB><sha><TAB><key>[<TAB><master-tip>]" line per PR
# (files from the pre-key tick have no key column; reconcile reads it as key = sha. Files from before the
# master-tip column have no 4th column; settle records the conflict hold as unknown).
pcw_pending_has() {
    local f
    for f in "$1"/pending/*; do
        [[ -f "$f" ]] || continue
        awk -F'\t' -v p="$2" 'NR > 1 && $1 == p { found = 1 } END { exit !found }' "$f" && return 0
    done
    return 1
}
# pcw_stacked_on <baseRefName> <open-heads-tsv>: the TSV is "<headRefName>\t<number>"
# lines for every open PR in the list (drafts included). Prints the parent PR number and
# returns 0 when <baseRefName> is another open PR's head branch, else returns 1. The base
# "master" and an empty base are never stacked. Pure: the caller fetches, this decides.
pcw_stacked_on() {
    local base="$1" heads="$2"
    [[ -n "$base" && "$base" != "master" ]] || return 1
    awk -F'\t' -v b="$base" '$1 == b { print $2; f = 1; exit } END { exit !f }' <<< "$heads"
}
# pcw_patch_id <root> <pr>: print the PR's patch-id (40 lowercase hex), or nothing on any
# failure. Always returns 0. A non-empty PR_CYCLE_PATCH_ID_CMD replaces the git default
# (`<cmd> <pr>` prints the id). The default fetches the PR head into refs/pr-cycle/<pr> and
# hashes `git diff <merge-base with origin/master> <head>` with git patch-id --verbatim.
# The caller removes the ref: bin/pr-cycle via _cleanup_fetch_refs, the tick via
# pcw_drop_pr_ref. The diff keeps git's default 3 context lines. bin/pr-cycle's sticky
# verdicts store ids made this way, so a different diff mode would invalidate them. A
# rebase that changes lines next to the PR's hunks therefore changes the id.
pcw_patch_id() {
    local root="$1" pr="$2" pid="" base=""
    [[ "$pr" =~ ^[0-9]+$ ]] || return 0
    if [[ -n "${PR_CYCLE_PATCH_ID_CMD:-}" ]]; then
        pid="$("$PR_CYCLE_PATCH_ID_CMD" "$pr" 2>/dev/null)" || true
    else
        git -C "$root" fetch --quiet origin master \
            "+refs/pull/$pr/head:refs/pr-cycle/$pr" 2>/dev/null || true
        base="$(git -C "$root" merge-base origin/master "refs/pr-cycle/$pr" 2>/dev/null)" || true
        if [[ -n "$base" ]]; then
            pid="$(git -C "$root" diff "$base" "refs/pr-cycle/$pr" 2>/dev/null \
                | git -C "$root" patch-id --verbatim 2>/dev/null \
                | awk '{print $1; exit}')" || true
        fi
    fi
    [[ "$pid" =~ ^[0-9a-f]{40}$ ]] && printf '%s\n' "$pid"
    return 0
}
# pcw_drop_pr_ref <root> <pr>: delete refs/pr-cycle/<pr> and nothing else, so a concurrent
# bin/pr-cycle run keeps the refs it fetched. Always returns 0.
pcw_drop_pr_ref() {
    [[ "$2" =~ ^[0-9]+$ ]] || return 0
    git -C "$1" update-ref -d "refs/pr-cycle/$2" 2>/dev/null || true
    return 0
}
# pcw_master_tip <root>: print the origin/master tip (40 lowercase hex), or nothing on any
# failure. Always returns 0. A non-empty PR_CYCLE_MASTER_TIP_CMD replaces the git default
# (`<cmd>` takes no argument and prints the tip). Unset and empty both select the default,
# which reads refs/remotes/origin/master without fetching: the tick calls pcw_patch_id
# first, and its default fetches origin master. Callers treat no output as "never eligible".
pcw_master_tip() {
    local root="$1" tip=""
    if [[ -n "${PR_CYCLE_MASTER_TIP_CMD:-}" ]]; then
        tip="$("$PR_CYCLE_MASTER_TIP_CMD" 2>/dev/null)" || true
    else
        tip="$(git -C "$root" rev-parse --verify --quiet refs/remotes/origin/master 2>/dev/null)" || true
    fi
    [[ "$tip" =~ ^[0-9a-f]{40}$ ]] && printf '%s\n' "$tip"
    return 0
}
# pcw_conflict_probe <root> <pr> <tip> <head>: would <head> merge onto <tip> cleanly?
#   0 = clean (prints nothing)
#   1 = conflicted: prints the conflicted paths, LC_ALL=C sorted and unique, one per line
#   2 = cannot tell: prints a one-line reason. Callers fall back to today's behavior.
# Runs `git merge-tree --write-tree --name-only`, which writes objects but no ref and never
# touches a worktree. When <head> is not a local object it fetches refs/pull/<pr>/head once,
# without writing FETCH_HEAD or any ref. A conflict needs exit 1 AND a 40-hex tree OID on
# line 1 AND at least one path: a missing object also exits 1 but prints no OID line.
pcw_conflict_probe() {
    local root="$1" pr="$2" tip="$3" head="$4" out="" mrc=0 paths=""
    if [[ ! "$tip" =~ ^[0-9a-f]{40}$ || ! "$head" =~ ^[0-9a-f]{40}$ ]]; then
        printf 'tip or head is not a 40-hex SHA\n'; return 2
    fi
    if ! git -C "$root" cat-file -e "$head^{commit}" 2>/dev/null; then
        [[ "$pr" =~ ^[0-9]+$ ]] && git -C "$root" fetch --quiet --no-write-fetch-head \
            origin "refs/pull/$pr/head" 2>/dev/null || true
        if ! git -C "$root" cat-file -e "$head^{commit}" 2>/dev/null; then
            printf 'head %s missing after fetch\n' "${head:0:7}"; return 2
        fi
    fi
    out="$(git -C "$root" -c core.quotePath=false merge-tree --write-tree --name-only "$tip" "$head" 2>/dev/null)" \
        && mrc=0 || mrc=$?
    if [[ "$mrc" -eq 0 && "$(sed -n '1p' <<< "$out")" =~ ^[0-9a-f]{40}$ ]]; then
        return 0
    fi
    if [[ "$mrc" -eq 1 && "$(sed -n '1p' <<< "$out")" =~ ^[0-9a-f]{40}$ ]]; then
        paths="$(awk 'NR == 1 { next } /^$/ { stop = 1 } !stop { print }' <<< "$out" \
            | LC_ALL=C sort -u)"
        # quotePath=false leaves only paths with quotes, backslashes or control characters
        # C-quoted. A quoted name cannot be looked up later, so treat it as "cannot tell".
        if [[ "$paths" == *'"'* ]]; then
            printf 'conflicted path is C-quoted\n'; return 2
        fi
        if [[ -n "$paths" ]]; then printf '%s\n' "$paths"; return 1; fi
    fi
    printf 'merge-tree output unrecognised (exit %s)\n' "$mrc"; return 2
}
# conflict-hold/<pr>: line 1 is the origin/master tip recorded at the launch of a rescue that
# settled `conflict`, or `unknown` when that launch had no readable tip. When the conflict
# probe succeeded at record time, line 2 is `head <sha>`, line 3 `paths <N>`, and lines 4..
# the N conflicted paths. settle writes it on `conflict` and clears it on every other outcome.
# pcw_record_conflict_hold <dir> <pr> [tip] [head] [paths, newline-separated]
pcw_record_conflict_hold() {
    local tip="${3:-unknown}" head="${4:-}" paths="${5:-}" n
    if [[ "$tip" =~ ^[0-9a-f]{40}$ && "$head" =~ ^[0-9a-f]{40}$ && -n "$paths" ]]; then
        n="$(grep -c . <<< "$paths")"
        _pcw_write "$1/conflict-hold/$2" "$tip"$'\n'"head $head"$'\n'"paths $n"$'\n'"$paths"
    else
        _pcw_write "$1/conflict-hold/$2" "$tip"
    fi
}
# pcw_conflict_hold_head <dir> <pr>: print the head recorded with the hold, or nothing.
pcw_conflict_hold_head() {
    local f="$1/conflict-hold/$2"
    [[ -f "$f" ]] && sed -n '2s/^head \([0-9a-f]\{40\}\)$/\1/p' "$f"
    return 0
}
# pcw_conflict_hold_paths <dir> <pr>: print the recorded path set. Returns 1 (prints nothing)
# when there is none: a legacy one-line hold, or a `paths <N>` count that does not match.
pcw_conflict_hold_paths() {
    local f="$1/conflict-hold/$2" n="" body=""
    [[ -f "$f" ]] || return 1
    n="$(sed -n '3s/^paths \([0-9][0-9]*\)$/\1/p' "$f")"
    [[ -n "$n" && "$n" -gt 0 ]] || return 1
    body="$(sed -n '4,$p' "$f")"
    [[ "$(grep -c . <<< "$body")" == "$n" ]] || return 1
    printf '%s\n' "$body"
}
pcw_clear_conflict_hold()  { rm -f "$1/conflict-hold/$2"; }
# retries/<pr> holds "<YYYY-MM-DD> <n>": DIRTY rescue launches for the PR on that local day.
pcw_today() { date +%F; }
pcw_retry_count() {   # <dir> <pr> <day>: prints n when retries/<pr> holds that day, else 0
    local f="$1/retries/$2" d="" n=""
    if [[ -f "$f" ]]; then read -r d n < "$f" || true; fi
    if [[ -n "$d" && "$d" == "$3" && "$n" =~ ^[0-9]+$ ]]; then
        printf '%s\n' "$n"
    else
        printf '0\n'
    fi
}
pcw_bump_retry() {    # <dir> <pr> <day>: writes "<day> <count+1>"
    local n
    n=$(( $(pcw_retry_count "$1" "$2" "$3") + 1 ))
    _pcw_write "$1/retries/$2" "$3 $n"
}
# pcw_conflict_retry_due <dir> <pr> <tip> <day> [<head>]: may a retry-capped PR relaunch?
#   0 = yes: a conflict hold exists, <tip> is 40 hex, and either <tip> differs from the held
#       tip or <head> differs from the head recorded with the hold; and the day's launch
#       count is below PCW_CONFLICT_RETRY_DAILY_CAP
#   1 = no conflict hold (today's retry-cap rule applies unchanged)
#   2 = conflict hold, but <tip> is unreadable, or equals the held tip with the head
#       unchanged or unrecorded (and always when <head> is omitted)
#   3 = conflict hold and something new, but the daily cap is spent
# Only line 1 of the hold is the tip; later lines belong to the probe.
pcw_conflict_retry_due() {
    local f="$1/conflict-hold/$2" held="" rhead=""
    [[ -f "$f" ]] || return 1
    [[ "$3" =~ ^[0-9a-f]{40}$ ]] || return 2
    held="$(sed -n '1p' "$f")"
    if [[ "$held" == "$3" ]]; then
        rhead="$(pcw_conflict_hold_head "$1" "$2")"
        [[ -n "${5:-}" && -n "$rhead" && "$rhead" != "$5" ]] || return 2
    fi
    (( $(pcw_retry_count "$1" "$2" "$4") < PCW_CONFLICT_RETRY_DAILY_CAP )) || return 3
    return 0
}

_pcw_write() {   # atomic: temp file then rename, so a killed tick never leaves half a SHA
    mkdir -p "$(dirname "$1")" && printf '%s\n' "$2" > "$1.tmp.$$" && mv -f "$1.tmp.$$" "$1"
}

# pcw_prune <state-dir> <newline-separated open PR numbers>: drop state for closed PRs.
pcw_prune() {
    local dir="$1" open="$2" f
    for f in "$dir"/attempts/* "$dir"/dm-sent/* "$dir"/declined/* "$dir"/strikes/* \
             "$dir"/dm-declined/* "$dir"/dm-strikes/* "$dir"/dm-patchid/* "$dir"/dm-probe/* \
             "$dir"/conflict-hold/* "$dir"/retries/* "$dir"/gate-reruns/*; do
        [[ -f "$f" ]] || continue
        grep -qxF "$(basename "$f")" <<< "$open" || rm -f "$f"
    done
    # Result files of a settled entry are removed by reconcile. Sweep strays a day old; pending
    # entries are never pruned here, only settled by reconcile.
    [[ -d "$dir/results" ]] && find "$dir/results" -type f -mmin +1440 -delete 2>/dev/null
    return 0
}
