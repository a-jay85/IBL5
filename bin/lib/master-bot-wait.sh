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
# Sourced by .github/workflows/update-behind-prs.yml,
# .github/workflows/rebase-prs.yml and bin/merge-line-watch. Tested by
# bin/test-merge-line-watch. Source-safe: no set, no exit.
#
# Seams: GH_CMD (default gh), MASTER_BOT_WAIT_POLL (seconds between checks,
# default 30), MASTER_BOT_WAIT_CAP (seconds before giving up, default 2700).

# master_bot_runs_open <owner/name>
# Prints the open (queued or running) bot-committing master-push workflows as
# "A, B", or nothing when none is open. Returns 1 when the API call fails.
# Status is filtered in jq: the status= query param takes one value and would
# miss queued runs. Runs come newest first, so 100 reaches many merges back.
master_bot_runs_open() {
    "${GH_CMD:-gh}" api "repos/$1/actions/runs?branch=master&event=push&per_page=100" \
        --jq '[.workflow_runs[]
               | select(.name == "Tests and Analysis" or .name == "Migration Safety")
               | select(.status != "completed")
               | .name] | unique | join(", ")'
}

# master_bot_wait <owner/name> [say-command]
# Blocks until no bot-committing master run is open, then returns 0. Fails
# open: an API error or the cap ends the wait, and the caller goes ahead as it
# did before this wait existed. Progress lines go through say-command
# (default echo).
master_bot_wait() {
    local repo="$1" say="${2:-echo}" open start
    local poll="${MASTER_BOT_WAIT_POLL:-30}" cap="${MASTER_BOT_WAIT_CAP:-2700}"
    start=$(date +%s)
    while :; do
        if ! open=$(master_bot_runs_open "$repo"); then
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
