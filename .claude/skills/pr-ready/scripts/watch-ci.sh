#!/usr/bin/env bash
#
# watch-ci.sh — silent-until-settled CI watcher for one PR at one head SHA.
#
# Usage: watch-ci.sh <PR> <HEAD_SHA> [TIMEOUT_SECS]
#
# WHY IT PRINTS NOTHING UNTIL IT IS DONE. The previous shape armed this loop under
# `Monitor`, whose contract is "every stdout line is a conversation message". IBL5's PRs
# carry ~46 checks that land in waves over a ~10-minute run, so a loop that emitted each
# settled check woke the orchestrator a dozen-odd times, and every wake re-sent the whole
# /pr-ready context. The watching was costing more than the work. This script therefore
# buffers: it polls in silence and prints exactly one verdict block at the end, so the
# caller can run it with `Bash(run_in_background: true)` and be woken **once**, on exit.
#
# WHY IT DOES NOT FAIL FAST. `gh pr checks --watch --fail-fast` (what /post-plan uses)
# returns on the first red check. Here the caller is Phase 6.5 remediation, which fixes
# what CI reports and pushes again — a ~10-minute round trip. Surfacing one failure at a
# time turns three bad checks into three round trips, and the wake cost is identical
# either way. So: wait for every check to settle, then report all of them at once.
#
# SILENCE IS NOT SUCCESS. Every terminal path prints a verdict line and carries its own
# exit code — there is no path on which this script exits quietly.
#
#   0  CI COMPLETE      every check settled, none red
#   1  CI FAILED        every check settled, at least one not pass/skipping
#   2  MERGE CONFLICT   mergeStateStatus=DIRTY — the rebase did not clear the conflict
#   3  STALE            the head moved (or never arrived); this watcher is obsolete
#   4  CI TIMEOUT       TIMEOUT_SECS elapsed with checks still pending
#   5  STOP             usage error
#
# THE `seen` GATE IS LOAD-BEARING — DO NOT REMOVE IT. This runs immediately after a
# force-push, and GitHub's API serves the PREVIOUS head for a window of seconds
# afterwards. In that window `gh pr checks` returns the OLD head's already-settled
# buckets, so the all-settled predicate is true on iteration one and the script reports a
# false CI COMPLETE having never watched the new head at all (observed live on PR #1830).
# Until `live` has matched HEAD_SHA at least once, nothing is evaluated — not the stale
# break, not mergeStateStatus, not the checks predicate. GRACE bounds that silence so a
# genuinely superseded push still reports STALE promptly instead of idling to the timeout.

set -uo pipefail

PR="${1:-}"
HEAD_SHA="${2:-}"
TIMEOUT="${3:-3600}"
# Test seams (bin/test-pr-ready-now drives them; nothing in /pr-ready sets either).
# Unset in normal use, so the defaults below are what the skill actually runs.
INTERVAL="${WATCH_CI_INTERVAL:-30}"   # seconds between polls; 30s+ respects gh rate limits
GRACE="${WATCH_CI_GRACE:-10}"         # polls (~5 min) to wait for the pushed SHA to become the live head

[ -n "$PR" ]       || { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 5; }
[ -n "$HEAD_SHA" ] || { echo "STOP: arg2 (head SHA) required — was the site rewritten with the literal?"; exit 5; }

# The last non-empty mergeStateStatus we saw. Printed in the verdict because the caller's
# pre-watch read is usually UNKNOWN (GitHub computes mergeability asynchronously) and this
# is what resolves it — without costing a second main-thread read.
LAST_MS="(never read)"
SEEN=""
STARTED=$(date +%s)

# One line per check that did not end pass/skipping. `cancel`, `fail`, and anything else
# gh grows later all land here: the filter is "not one of the two OK buckets", never a
# whitelist of known-bad ones.
report_checks() {
    jq -r '.[] | select(.bucket != "pass" and .bucket != "skipping") | "  \(.name): \(.bucket)"' <<< "$1" | sort
}

while :; do
    ELAPSED=$(( $(date +%s) - STARTED ))
    if [ "$ELAPSED" -ge "$TIMEOUT" ]; then
        if [ -z "$SEEN" ]; then
            echo "CI TIMEOUT after ${ELAPSED}s — $HEAD_SHA never became the live head (mergeStateStatus: $LAST_MS)"
            exit 4
        fi
        echo "CI TIMEOUT after ${ELAPSED}s — checks still pending on $HEAD_SHA (mergeStateStatus: $LAST_MS)"
        S=$(gh pr checks "$PR" --json name,bucket 2>/dev/null || echo '[]')
        jq -r '.[] | select(.bucket == "pending") | "  pending: \(.name)"' <<< "$S" | sort
        exit 4
    fi

    V=$(gh pr view "$PR" --json headRefOid,mergeStateStatus 2>/dev/null || echo '{}')
    LIVE=$(jq -r '.headRefOid // ""' <<< "$V")
    MS=$(jq -r '.mergeStateStatus // ""' <<< "$V")
    [ -n "$MS" ] && LAST_MS="$MS"
    [ "$LIVE" = "$HEAD_SHA" ] && SEEN=1

    if [ -z "$SEEN" ]; then
        GRACE=$((GRACE - 1))
        if [ "$GRACE" -le 0 ]; then
            echo "STALE: head never reached $HEAD_SHA (last seen ${LIVE:-none}); this watcher is obsolete"
            exit 3
        fi
        sleep "$INTERVAL"
        continue
    fi

    if [ -n "$LIVE" ] && [ "$LIVE" != "$HEAD_SHA" ]; then
        echo "STALE: head moved $HEAD_SHA -> $LIVE; this watcher is obsolete"
        exit 3
    fi

    if [ "$MS" = "DIRTY" ]; then
        echo "MERGE CONFLICT: mergeStateStatus=DIRTY; stop and re-run Phases 2-3"
        exit 2
    fi

    S=$(gh pr checks "$PR" --json name,bucket 2>/dev/null || echo '[]')
    if jq -e 'length > 0 and all(.[]; .bucket != "pending")' <<< "$S" > /dev/null; then
        TOTAL=$(jq -r 'length' <<< "$S")
        BAD=$(report_checks "$S")
        if [ -z "$BAD" ]; then
            echo "CI COMPLETE — all $TOTAL checks settled, none red (mergeStateStatus: $LAST_MS)"
            exit 0
        fi
        NBAD=$(printf '%s\n' "$BAD" | grep -c .)
        echo "CI FAILED — $NBAD of $TOTAL checks did not pass (mergeStateStatus: $LAST_MS)"
        printf '%s\n' "$BAD"
        exit 1
    fi

    sleep "$INTERVAL"
done
