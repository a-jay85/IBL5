#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/ci-checks-green.sh — pure jq/bash verdict logic for "is this set of
# GitHub check-runs green?". Sourced by bin/check-master-ci-green,
# bin/check-pr-checks-green, and bin/test-ci-checks-green. Never executed.
#
# Each function takes JSON on stdin (or a CSV/array argument) and prints to
# stdout. No I/O, no `set -e` at file scope. Test seams (GH_API_CMD) belong to
# the callers, never to this lib.
#
# One deliberate delta from the verdict jq this was extracted from: a completed
# run with a null conclusion used to print as "<name> ()" (jq concatenates a
# string with null as the empty string); it is still a failure, and is now
# reported as "<name> (unknown)".

# ccg_dedupe <skip-json-array> — drop exact-name matches in $1, keep the latest
#   run per name (sort_by(.completed_at // .started_at) | last). Input/output:
#   {check_runs:[...]} (total_count recomputed). Byte-identical to the former
#   DEDUPE_JQ in bin/check-master-ci-green.
ccg_dedupe() { jq --argjson skip "$1" '
  [ .check_runs[] | select(.name | IN($skip[]) | not) ]
  | group_by(.name) | map(sort_by(.completed_at // .started_at) | last)
  | { total_count: length, check_runs: . }'; }

# ccg_pending — names of runs whose .status != "completed", comma+space joined.
ccg_pending() { jq -r '[.check_runs[] | select(.status != "completed")] | map(.name) | join(", ")'; }

# ccg_failed — "name (conclusion)" for every completed run whose conclusion is
#   NOT in the pass set {success, skipped, neutral}. failure, cancelled,
#   timed_out, action_required, stale, startup_failure and null all land here.
ccg_failed() { jq -r '[.check_runs[] | select(.status == "completed" and ((.conclusion // "unknown") | IN("success","skipped","neutral") | not))] | map(.name + " (" + (.conclusion // "unknown") + ")") | join(", ")'; }

# ccg_skip_json <csv> — CSV of exact names -> JSON array, trimming spaces,
#   dropping empties ("" -> []). Former SKIP_JSON derivation.
ccg_skip_json() { printf '%s\n' "$1" | jq -R 'split(",") | map(sub("^ +";"") | sub(" +$";"")) | map(select(length > 0))'; }

# ccg_drop_pending <ignore-json-array> — drop every run whose name is an EXACT
#   match in $1 AND whose .status != "completed". A completed run of a listed
#   name is kept whatever its conclusion, so a red (or cancelled) deploy still
#   fails the verdict. Input/output: {check_runs:[...]} (total_count
#   recomputed). Callers apply it BEFORE ccg_dedupe: dedupe keeps the newest run
#   per name, and a pending re-run is newer than the completed-red run of the
#   same name it would otherwise replace. Dropping the pending re-run first
#   leaves the older red run in place, which is the fail-closed reading.
#   An empty list is a no-op apart from total_count being recomputed.
ccg_drop_pending() { jq --argjson ignore "$1" '
  [ .check_runs[] | select(((.name | IN($ignore[])) and (.status != "completed")) | not) ]
  | { total_count: length, check_runs: . }'; }

# ccg_drop_failed <ignore-json-array> — drop every run whose name is an EXACT
#   match in $1, whose .status == "completed", AND whose conclusion is outside
#   the ccg_failed pass set {success, skipped, neutral}. A null conclusion
#   counts as non-passing, as in ccg_failed. A pending or passing run of a
#   listed name is kept, so a still-running helper still reports as running.
#   Callers apply it AFTER ccg_dedupe so only the newest run per name is
#   judged: a newer pending re-run hides an older red run, which is today's
#   behavior. Input/output: {check_runs:[...]} (total_count recomputed). An
#   empty list is a no-op apart from total_count being recomputed.
ccg_drop_failed() { jq --argjson ignore "$1" '
  [ .check_runs[] | select(((.name | IN($ignore[])) and (.status == "completed")
      and ((.conclusion // "unknown") | IN("success","skipped","neutral") | not)) | not) ]
  | { total_count: length, check_runs: . }'; }

# ccg_ghost_reason <now-epoch> <grace-seconds> — decides whether a workflow run that
#   the runs API still reports as NOT completed is a "ghost": every job of its latest
#   attempt completed inside the pass set, and the run's updated_at has been frozen
#   for longer than the grace window. Input: {run: <workflow_run>, jobs: [<job>...]}
#   (jobs as returned by GET /actions/runs/{id}/jobs, any attempt mix). Output: one
#   line, "settled" or a reason. Every unknown is a reason (fail-closed): a missing
#   id, run_attempt or updated_at, an unparseable date, zero jobs on the latest
#   attempt, a job not completed, a job outside {success, skipped, neutral} (null
#   reads as "unknown"), or an age of grace seconds or less. Only jobs whose
#   run_attempt equals the run's run_attempt count; an older attempt's red job is
#   neither a pass nor a hold here (the check-run verdict V1-V3 still sees it).
#   Age is strictly greater than grace: at exactly grace the run is still "young".
ccg_ghost_reason() { jq -r --argjson now "$1" --argjson grace "$2" '
  .run as $r
  | ($r.updated_at | if type == "string" then (try fromdateiso8601 catch null) else null end) as $upd
  | [ .jobs[] | select(.run_attempt == $r.run_attempt) ] as $jobs
  | if ($r.id | type) != "number" then "no-run-id"
    elif ($r.run_attempt | type) != "number" then "no-run-attempt"
    elif $upd == null then "no-updated-at"
    elif ($jobs | length) == 0 then "zero-jobs"
    elif ([ $jobs[] | select(.status != "completed") ] | length) > 0 then
      "job-pending: " + ([ $jobs[] | select(.status != "completed") | .name ] | join(", "))
    elif ([ $jobs[] | select((.conclusion // "unknown") | IN("success","skipped","neutral") | not) ] | length) > 0 then
      "job-red: " + ([ $jobs[] | select((.conclusion // "unknown") | IN("success","skipped","neutral") | not)
                      | .name + " (" + (.conclusion // "unknown") + ")" ] | join(", "))
    elif ($now - $upd) <= $grace then
      "young: " + (($now - $upd) | tostring) + "s <= " + ($grace | tostring) + "s"
    else "settled" end'; }
