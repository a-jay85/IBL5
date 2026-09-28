# Sourced by bin/backlog for burndown* subcommands. Never executed directly.
# shellcheck shell=bash
#
# Exit-code contract (mirrored verbatim in .claude/skills/burndown/SKILL.md):
#   0 — success
#   1 — completed but at least one item's live state was unknown (reported, never guessed)
#   2 — usage error (bad subcommand, flag, or argument)
#   3 — fail-closed abort (missing report, gh/git/jq failure, HOME unset,
#       missing or malformed ledger)

# shellcheck disable=SC2034  # used in later phases sourced from this file
BD_BUDGET=5
BD_CODE_REPO="a-jay85/IBL5"
BD_RANKS='^P[1-4]$'

bd_die() {
    local code="$1"; shift
    printf 'burndown: %s\n' "$*" >&2
    exit "$code"
}

bd_init() {
    command -v jq >/dev/null 2>&1 || bd_die 3 "jq not found on PATH"
    [ -n "${HOME:-}" ] || bd_die 3 "HOME is unset; cannot locate ~/claude-plans"
    BD_PLANS_DIR="$HOME/claude-plans"
    # shellcheck disable=SC2034
    BD_REPORTS_DIR="$BD_PLANS_DIR/_reports"
    # shellcheck disable=SC2034
    BD_QUEUE_DIR="$HOME/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/queue"
    BD_REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
    # shellcheck disable=SC2034
    BD_TIER_HINT="${BURNDOWN_TIER_HINT:-$BD_REPO_ROOT/bin/plan-tier-hint}"
    # shellcheck disable=SC2034
    BD_GIT="${BURNDOWN_GIT:-git}"
}

bd_cmd_burndown-delta()        { bd_die 2 "burndown-delta: not implemented"; }
bd_cmd_burndown-refresh()      { bd_die 2 "burndown-refresh: not implemented"; }
bd_cmd_burndown()              { bd_die 2 "burndown: not implemented"; }
bd_cmd_burndown-record()       { bd_die 2 "burndown-record: not implemented"; }
bd_cmd_burndown-status()       { bd_die 2 "burndown-status: not implemented"; }
bd_cmd_burndown-close-merged() { bd_die 2 "burndown-close-merged: not implemented"; }

bd_main() {
    local cmd="$1"; shift
    bd_init
    case "$cmd" in
        burndown-delta)        bd_cmd_burndown-delta        "$@" ;;
        burndown-refresh)      bd_cmd_burndown-refresh      "$@" ;;
        burndown)              bd_cmd_burndown              "$@" ;;
        burndown-record)       bd_cmd_burndown-record       "$@" ;;
        burndown-status)       bd_cmd_burndown-status       "$@" ;;
        burndown-close-merged) bd_cmd_burndown-close-merged "$@" ;;
        *)                     bd_die 2 "unknown burndown subcommand: $cmd" ;;
    esac
}
