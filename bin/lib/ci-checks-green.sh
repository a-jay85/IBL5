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
