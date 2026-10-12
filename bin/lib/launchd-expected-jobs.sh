#!/usr/bin/env bash
# bin/lib/launchd-expected-jobs.sh — sourced library; single source of truth
# for what bin/launchd-health-check (example) expects launchd and Docker to be running.
#
# Bash 3.2 / macOS compatible.  No set options, no traps, no subprocess on
# source: sourcing only defines the LEJ_* variables and lej_* functions.
# Variables are deliberately NOT readonly so a harness can re-source safely.
#
# Callers: . "$SCRIPT_DIR/lib/launchd-expected-jobs.sh"
#   bin/launchd-health-check (example), bin/test-launchd-health-check (example),
#   bin/test-launchd-sched (example)

# shellcheck disable=SC2034  # consumed by the sourcing scripts

# One record per line: "<label> <mode>".
#   standard    — label must appear in `launchctl list`; last exit status 0.
#   keepalive   — standard, plus the PID column must be numeric (running).
#   phase-gated — standard while the league phase is $LEJ_ACTIVE_PHASE.
#                 "Not listed" is healthy only when the phase is CONFIRMED
#                 as something else. Unknown phase => not exempt.
#                 No job uses this mode now (sim-recap-poll was retired).
LEJ_EXPECTED_JOBS='com.ibl5.automouse standard
com.ibl5.automouse-comprehension-digest standard
com.ibl5.automouse-morning-digest standard
com.ibl5.backups-sync standard
com.ibl5.bug-bot keepalive
com.ibl5.bug-pipeline-cron standard
com.ibl5.db-backups-pull standard
com.ibl5.db-sync-nightly standard
com.ibl5.docfix-poll standard
com.ibl5.events-review standard
com.ibl5.usage-gate-coordinator standard
com.ibl5.watch-pr-cycle standard
com.ibl5.wt-gc standard
com.ibl5.wt-sync standard'

# One-shot runner label prefixes (bin/plan-now, bin/pr-review-now,
# bin/docfix-run, bin/post-plan-now). Never alerted on; a leftover plist is
# reported as stale only.
LEJ_TRANSIENT_PREFIXES='com.ibl5.plan-now-
com.ibl5.pr-review-now-
com.ibl5.docfix-run-
com.ibl5.postplan-now-'

# Labels a setup script installs that the health check deliberately does NOT
# expect. One record per line: "<label> <reason>". bin/test-launchd-sched
# fails when a setup script installs a label found in neither list, when an
# entry here has no reason, or when an entry here names no installed label.
LEJ_EXEMPT_JOBS='com.ibl5.launchd-health-daily the health checker itself; it cannot report its own absence
com.ibl5.launchd-health-login the health checker itself; it cannot report its own absence
com.ibl5.sim-recap-poll retired from health expectations in PR 2934; installed on demand only
com.ibl5.usage-gate-keychain-probe one-shot probe loaded and removed inside the install run'

# Main-stack containers that must be running (`docker ps --format '{{.Names}}'`).
LEJ_REQUIRED_CONTAINERS='ibl5-mariadb
ibl5-php
ibl5-traefik'

# The only league phase in which a phase-gated job must be loaded.
# Matches ibl_settings 'Current Season Phase' values
# (ibl5/classes/LeagueControlPanel/LeagueControlPanelRepository.php).
LEJ_ACTIVE_PHASE='Regular Season'

# lej_expected_labels — print every expected label, one per line.
lej_expected_labels() {
    local l m
    while read -r l m; do
        [ -n "$l" ] && printf '%s\n' "$l"
    done <<< "$LEJ_EXPECTED_JOBS"
    return 0
}

# lej_job_mode <label> — print the label's mode; return 1 if not expected.
lej_job_mode() {
    local l m
    while read -r l m; do
        if [ "$l" = "$1" ]; then printf '%s\n' "$m"; return 0; fi
    done <<< "$LEJ_EXPECTED_JOBS"
    return 1
}
