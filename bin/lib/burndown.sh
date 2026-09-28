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
    BD_REPORTS_DIR="$BD_PLANS_DIR/_reports"
    BD_QUEUE_DIR="$HOME/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/queue"
    BD_REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
    BD_TIER_HINT="${BURNDOWN_TIER_HINT:-$BD_REPO_ROOT/bin/plan-tier-hint}"
    BD_GIT="${BURNDOWN_GIT:-git}"
}

# bd_latest_report — sets BD_REPORT to the newest backlog-triage-YYYY-MM-DD.md
bd_latest_report() {
    local f name best=""
    for f in "$BD_REPORTS_DIR"/backlog-triage-*.md; do
        name="$(basename "$f")"
        if [[ "$name" =~ ^backlog-triage-[0-9]{4}-[0-9]{2}-[0-9]{2}\.md$ ]]; then
            if [ -z "$best" ] || [[ "$name" > "$(basename "$best")" ]]; then
                best="$f"
            fi
        fi
    done
    [ -n "$best" ] || bd_die 3 "no triage report in $BD_REPORTS_DIR (want backlog-triage-YYYY-MM-DD.md); run a full backlog triage first, then re-run /burndown"
    BD_REPORT="$best"
}

# bd_parse_report <file> — prints TSV: num<TAB>rank<TAB>raw-line; sets BD_RANKED_NUMS array
bd_parse_report() {
    local file="$1"
    BD_REPORT_TSV="$(awk '
        /^## / { rank = ""; if (match($0, /^## P[1-4]( |$)/)) rank = substr($0, 4, 2); next }
        rank != "" && /^- \[#[0-9]+\]/ {
            n = $0; sub(/^- \[#/, "", n); sub(/\].*/, "", n)
            print n "\t" rank "\t" $0
        }
    ' "$file")"
    [ -n "$BD_REPORT_TSV" ] || bd_die 3 "report $file has no ranked lines (malformed)"
    local dups
    dups="$(cut -f1 <<< "$BD_REPORT_TSV" | sort | uniq -d)"
    if [ -n "$dups" ]; then
        bd_die 3 "report $file ranks #$dups twice"
    fi
    # Build array of ranked issue numbers (while-read for bash 3.2 compat)
    BD_RANKED_NUMS=()
    while IFS= read -r _num; do
        BD_RANKED_NUMS+=("$_num")
    done < <(cut -f1 <<< "$BD_REPORT_TSV")
}

# bd_report_since <file> — sets BD_SINCE to ISO timestamp of file mtime
bd_report_since() {
    local file="$1" mtime
    mtime="$(stat -c %Y "$file" 2>/dev/null || stat -f %m "$file" 2>/dev/null)"
    [[ "$mtime" =~ ^[0-9]+$ ]] || bd_die 3 "cannot read mtime of $file"
    BD_SINCE="$(jq -rn --argjson s "$mtime" '$s | todate')"
}

# bd_fetch_issues <out-file> — fetches open backlog issues to a JSON file
bd_fetch_issues() {
    local out="$1" raw
    if ! raw="$("$GH" issue list --repo "$REPO" --state open --limit 1000 \
            --json number,title,body,url,updatedAt,labels 2>/dev/null)"; then
        bd_die 3 "gh issue list failed"
    fi
    if ! jq -e 'type=="array"' <<< "$raw" >/dev/null 2>&1; then
        bd_die 3 "gh issue list returned non-array JSON"
    fi
    local count
    count="$(jq 'length' <<< "$raw")"
    [ "$count" -lt 1000 ] || bd_die 3 "gh issue list hit --limit 1000; results may be truncated"
    printf '%s' "$raw" > "$out"
}

bd_cmd_delta() {
    [ $# -eq 0 ] || bd_die 2 "burndown-delta takes no arguments"
    bd_latest_report
    bd_parse_report "$BD_REPORT"
    bd_report_since "$BD_REPORT"
    local issues_file
    issues_file="$(mktemp)"
    bd_fetch_issues "$issues_file"
    local ranked_json
    ranked_json="$(printf '[%s]' "$(IFS=,; echo "${BD_RANKED_NUMS[*]}")")"
    local out
    out="$(jq -c \
        --arg report "$BD_REPORT" \
        --arg since "$BD_SINCE" \
        --argjson ranked "$ranked_json" \
        '{
            report: $report,
            since: $since,
            issues: (
                [.[] | select(.updatedAt > $since or ([.number] | inside($ranked) | not))]
                | sort_by(.number)
                | [.[] | {number: .number, title: .title, url: .url, updatedAt: .updatedAt, body: (.body // "" | .[0:1500])}]
            ),
            dropped: ($ranked - [.[].number])
        }' "$issues_file")"
    rm -f "$issues_file"
    local k
    k="$(jq '.issues | length' <<< "$out")"
    printf 'delta: %s issue(s) to rank since %s\n' "$k" "$BD_SINCE" >&2
    printf '%s\n' "$out"
}

bd_cmd_refresh()      { bd_die 2 "burndown-refresh: not implemented"; }
bd_cmd_burndown()     { bd_die 2 "burndown: not implemented"; }
bd_cmd_record()       { bd_die 2 "burndown-record: not implemented"; }
bd_cmd_status()       { bd_die 2 "burndown-status: not implemented"; }
bd_cmd_close_merged() { bd_die 2 "burndown-close-merged: not implemented"; }

bd_main() {
    local cmd="$1"; shift
    bd_init
    case "$cmd" in
        burndown-delta)        bd_cmd_delta        "$@" ;;
        burndown-refresh)      bd_cmd_refresh      "$@" ;;
        burndown)              bd_cmd_burndown     "$@" ;;
        burndown-record)       bd_cmd_record       "$@" ;;
        burndown-status)       bd_cmd_status       "$@" ;;
        burndown-close-merged) bd_cmd_close_merged "$@" ;;
        *)                     bd_die 2 "unknown burndown subcommand: $cmd" ;;
    esac
}
