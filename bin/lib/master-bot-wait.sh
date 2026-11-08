# shellcheck shell=bash
#
# bin/lib/master-bot-wait.sh — wait out master CI runs that can push a bot commit.
#
# Two master-push workflows push an [auto] commit to master after they finish:
# Tests and Analysis (coverage & PHPStan baseline snapshots, codebase-map) and
# Migration Safety (current-schema.sql). They land 2 to 22 minutes after a
# merge. A PR updated onto master before then goes BEHIND again when the bot
# commit lands, and master's strict protection throws away its whole CI run.
# Anything that updates or rebases PRs onto master calls master_bot_wait first.
#
# The check covers every master SHA. The bot commit can come from an earlier
# merge's run, and a later push that triggers neither workflow (a
# workflows-only merge, or the schema bot push) would otherwise look clear.
#
# In Tests and Analysis only two jobs push the bot commit (MASTER_BOT_TA_JOBS
# below). The rest of the run, harness tests most of all, can keep it open
# about ten more minutes without pushing anything. So an open Tests and
# Analysis run blocks only while one of those two jobs is absent from its jobs
# list, queued, or in progress. Migration Safety is short and stays a
# whole-run wait.
#
# Sourced by .github/workflows/update-behind-prs.yml,
# .github/workflows/rebase-prs.yml and bin/merge-line-watch. Tested by
# bin/test-merge-line-watch. Source-safe: no set, no exit.
#
# Seams: GH_CMD (default gh), MASTER_BOT_WAIT_POLL (seconds between checks,
# default 30), MASTER_BOT_WAIT_CAP (seconds before giving up, default 2700).

# Display names of the Tests and Analysis jobs that push an [auto] commit to
# master. Keep them equal to the name: lines of jobs update-baselines and
# regen-codebase-map in .github/workflows/tests.yml. Case V of
# bin/test-merge-line-watch fails when either name drifts, when either job
# stops running git push, or when another tests.yml job starts running it.
MASTER_BOT_TA_JOBS=("Update coverage & PHPStan baselines" "Regenerate codebase-map")

# master_bot_jobs_jq
# Prints the jq filter run over one Tests and Analysis run's jobs list. It
# prints "absent" when a pinned job is missing from the list (its needs are
# still pending, or it was renamed), "running" when one is queued or in
# progress, and "clear" when both are completed. A skipped job is completed.
master_bot_jobs_jq() {
    local names
    names=$(printf '"%s",' "${MASTER_BOT_TA_JOBS[@]}")
    printf '%s\n' '[.jobs[].name] as $all
  | [.jobs[] | select(.status == "completed") | .name] as $done
  | ['"${names%,}"'] as $bot
  | if ($bot - $all | length) > 0 then "absent"
    elif ($bot - $done | length) > 0 then "running"
    else "clear" end'
}

# master_bot_runs_open <owner/name>
# Prints the open bot-committing master-push workflows as "A, B", or nothing
# when none is open. Migration Safety counts while any of its runs is queued or
# running. Tests and Analysis counts while an open run's pinned jobs are
# absent, queued or running (master_bot_jobs_jq). Returns 1 when the runs list
# fails and 2 when a jobs list fails.
# Status is filtered in jq: the status= query param takes one value and would
# miss queued runs. Runs come newest first, so 100 reaches many merges back.
# A run has about 30 jobs, so one jobs page of 100 holds them all.
master_bot_runs_open() {
    local runs id name verdict jobs_jq ta="" ms=""
    runs=$("${GH_CMD:-gh}" api "repos/$1/actions/runs?branch=master&event=push&per_page=100" \
        --jq '.workflow_runs[]
              | select(.name == "Tests and Analysis" or .name == "Migration Safety")
              | select(.status != "completed")
              | "\(.id)\t\(.name)"') || return 1
    jobs_jq=$(master_bot_jobs_jq)
    while IFS=$'\t' read -r id name; do
        [ -n "$id" ] || continue
        case "$name" in
            "Migration Safety") ms=1 ;;
            "Tests and Analysis")
                [ -n "$ta" ] && continue
                verdict=$("${GH_CMD:-gh}" api "repos/$1/actions/runs/$id/jobs?per_page=100" \
                    --jq "$jobs_jq" </dev/null) || return 2
                [ "$verdict" = clear ] || ta=1 ;;
        esac
    done <<< "$runs"
    if [ -n "$ms" ] && [ -n "$ta" ]; then echo "Migration Safety, Tests and Analysis"
    elif [ -n "$ms" ]; then echo "Migration Safety"
    elif [ -n "$ta" ]; then echo "Tests and Analysis"
    fi
    return 0
}

# master_bot_wait <owner/name> [say-command]
# Blocks until no bot-committing master run or job is open, then returns 0. Fails
# open: an API error or the cap ends the wait, and the caller goes ahead as it
# did before this wait existed. Progress lines go through say-command
# (default echo).
master_bot_wait() {
    local repo="$1" say="${2:-echo}" open rc start
    local poll="${MASTER_BOT_WAIT_POLL:-30}" cap="${MASTER_BOT_WAIT_CAP:-2700}"
    start=$(date +%s)
    while :; do
        if open=$(master_bot_runs_open "$repo"); then rc=0; else rc=$?; fi
        if [ "$rc" -eq 2 ]; then
            "$say" "bot-commit wait: could not list Tests and Analysis jobs; going ahead"
            return 0
        fi
        if [ "$rc" -ne 0 ]; then
            "$say" "bot-commit wait: could not list master runs; going ahead"
            return 0
        fi
        if [ -z "$open" ]; then
            "$say" "bot-commit wait: no bot-committing master run open"
            return 0
        fi
        if [ $(( $(date +%s) - start )) -ge "$cap" ]; then
            "$say" "bot-commit wait: still open after ${cap}s ($open); going ahead"
            return 0
        fi
        "$say" "bot-commit wait: waiting on $open"
        sleep "$poll"
    done
}
