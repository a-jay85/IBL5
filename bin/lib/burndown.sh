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
    BD_TMP="$(mktemp -d)" || bd_die 3 "mktemp failed"
    trap 'rm -rf "$BD_TMP"' EXIT
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

# bd_inflight <out-tsv> — write num<TAB>reason lines for every in-flight issue
bd_inflight() {
    local out="$1"

    # Source 1: open PRs in the code repo
    local prs_out prs_rc=0
    prs_out="$("$GH" pr list --repo "$BD_CODE_REPO" --state open --limit 200 \
        --json number,body 2>/dev/null)" || prs_rc=$?
    [ "$prs_rc" -eq 0 ] || bd_die 3 "gh pr list failed"
    local prs_count
    prs_count="$(jq 'length' <<< "$prs_out")"
    [ "$prs_count" -lt 200 ] || bd_die 3 "gh pr list hit --limit 200; results may be truncated"
    jq -r '.[] | .number as $p | (.body // "") |
        [scan("IBL5-backlog#([0-9]+)")[0]] | unique[] |
        "\(.)\topen PR #\($p)"' <<< "$prs_out" >> "$out"

    # Source 2: ~/claude-plans/*.md (top-level only)
    local plan_files plan_rc
    plan_files=("$BD_PLANS_DIR"/*.md)
    if [ "${#plan_files[@]}" -gt 0 ] && [ -e "${plan_files[0]}" ]; then
        local gout=""
        plan_rc=0
        gout="$(grep -oHE 'IBL5-backlog#[0-9]+' "${plan_files[@]}" 2>/dev/null)" || plan_rc=$?
        [ "$plan_rc" -ne 2 ] || bd_die 3 "cannot read plans in $BD_PLANS_DIR"
        if [ -n "$gout" ]; then
            while IFS= read -r gline; do
                local fname="${gline%%:*}"
                local match="${gline##*:}"
                local n="${match##*#}"
                [ -n "$n" ] && printf '%s\tplan %s\n' "$n" "$(basename "$fname")" >> "$out"
            done <<< "$gout"
        fi
    fi

    # Source 3: automouse queue entries
    if [ -d "$BD_QUEUE_DIR" ]; then
        local entry
        for entry in "$BD_QUEUE_DIR"/*; do
            [ -e "$entry" ] || continue
            local target qrc=0 qout=""
            target="$(readlink -f "$entry" 2>/dev/null || printf '%s' "$entry")"
            qout="$(grep -oE 'IBL5-backlog#[0-9]+' "$target" 2>/dev/null)" || qrc=$?
            [ "$qrc" -ne 2 ] || bd_die 3 "cannot read queue entry $(basename "$entry")"
            if [ -n "$qout" ]; then
                while IFS= read -r match; do
                    local qn="${match##*#}"
                    [ -n "$qn" ] && printf '%s\tautomouse queue %s\n' "$qn" "$(basename "$entry")" >> "$out"
                done <<< "$qout"
            fi
        done
    fi

    # Source 4: prior burndown ledgers (burndown-batch-*.json)
    local ledger
    for ledger in "$BD_REPORTS_DIR"/burndown-batch-*.json; do
        [ -e "$ledger" ] || continue
        local lname
        lname="$(basename "$ledger")"
        jq -e '.items | type=="array"' "$ledger" >/dev/null 2>&1 || bd_die 3 "malformed ledger $lname"
        jq -r --arg lname "$lname" \
            '.items[] |
             select(.status != "merged" and .status != "closed-fixed" and .status != "skipped") |
             .status as $s |
             ([ (.issue_num | tostring) ] + [ (.also_closes // [])[] | tostring ])[] |
             . + "\tledger \($lname) (\($s))"' \
            "$ledger" >> "$out"
    done

    # Source 5: live worktrees
    local wt_out wt_rc=0
    wt_out="$("$BD_GIT" -C "$BD_REPO_ROOT" worktree list --porcelain 2>/dev/null)" || wt_rc=$?
    [ "$wt_rc" -eq 0 ] || bd_die 3 "git worktree list failed"
    if [ -n "$wt_out" ]; then
        local wbranch=""
        while IFS= read -r wline; do
            if [[ "$wline" =~ ^branch\ refs/heads/(.+)$ ]]; then
                wbranch="${BASH_REMATCH[1]}"
                # Issues from any ledger item (any status) whose slug matches this branch
                for ledger in "$BD_REPORTS_DIR"/burndown-batch-*.json; do
                    [ -e "$ledger" ] || continue
                    jq -r --arg b "$wbranch" \
                        '.items[] | select(.slug == $b) | .issue_num | tostring' \
                        "$ledger" 2>/dev/null | \
                    while IFS= read -r wn; do
                        [ -n "$wn" ] && printf '%s\tworktree %s\n' "$wn" "$wbranch" >> "$out"
                    done
                done
                # Issues from ~/claude-plans/<branch>.md
                local wplan="$BD_PLANS_DIR/$wbranch.md"
                if [ -f "$wplan" ]; then
                    grep -oE 'IBL5-backlog#[0-9]+' "$wplan" 2>/dev/null | \
                    while IFS= read -r wmatch; do
                        local wn="${wmatch##*#}"
                        [ -n "$wn" ] && printf '%s\tworktree %s\n' "$wn" "$wbranch" >> "$out"
                    done
                fi
            fi
        done <<< "$wt_out"
    fi
}

# bd_issue_is_open <num> <issues_file> — exit 0 if open
bd_issue_is_open() {
    jq -e --argjson n "$1" 'any(.[]; .number == $n)' "$2" >/dev/null 2>&1
}

# bd_issue_field <num> <field> <issues_file> — print field value
bd_issue_field() {
    jq -r --argjson n "$1" --arg f "$2" '.[] | select(.number == $n) | .[$f] // ""' "$3"
}

# bd_inflight_reason <num> <inflight_tsv> — print first reason or ""
bd_inflight_reason() {
    local num="$1" tsv="$2" line
    while IFS=$'\t' read -r inum ireason; do
        if [ "$inum" = "$num" ]; then
            printf '%s' "$ireason"
            return 0
        fi
    done < "$tsv"
    return 1
}

# bd_cost_for <title> — sets _bd_cost and _bd_tier
bd_cost_for() {
    local title="$1" tier rc=0
    tier="$("$BD_TIER_HINT" --desc "$title" 2>/dev/null)" || rc=$?
    if [ "$rc" -ne 0 ] || { [ "$tier" != "xhigh" ] && [ "$tier" != "default" ] && [ "$tier" != "sonnet" ]; }; then
        _bd_tier="error"; _bd_cost=1
    elif [ "$tier" = "xhigh" ]; then
        _bd_tier="xhigh"; _bd_cost=2
    else
        _bd_tier="$tier"; _bd_cost=1
    fi
}

# bd_paths_for <body> — sets _bd_paths (newline-sep file paths, sorted -u)
bd_paths_for() {
    _bd_paths="$(grep -oE "$FILE_LINE_RE" <<< "$1" | sed 's/:[0-9]*$//' | sort -u)"
}

# bd_has_overlap <paths> <held_tsv_file> — print "path<TAB>holder_num"; exit 1 if none
# held_tsv_file has lines: path<TAB>issue_num
bd_has_overlap() {
    local p
    while IFS= read -r p; do
        [ -n "$p" ] || continue
        local hit
        hit="$(grep -F "$p"$'\t' "$2" 2>/dev/null | head -1)" || true
        if [ -n "$hit" ]; then
            printf '%s\t%s' "$p" "${hit##*$'\t'}"
            return 0
        fi
    done <<< "$1"
    return 1
}

# bd_pair_find <num> <pairs_newline_list> — print partner or return 1
bd_pair_find() {
    local num="$1" pairs="$2" pair pa pb
    while IFS= read -r pair; do
        [ -n "$pair" ] || continue
        pa="${pair%%,*}"; pb="${pair##*,}"
        if [ "$pa" = "$num" ]; then printf '%s' "$pb"; return 0; fi
        if [ "$pb" = "$num" ]; then printf '%s' "$pa"; return 0; fi
    done <<< "$pairs"
    return 1
}

bd_cmd_burndown() {
    # 1. Argument parsing — only --pair A,B accepted
    local pairs=""   # newline-sep "A,B" strings
    while [ $# -gt 0 ]; do
        case "$1" in
            --pair)
                if [ $# -lt 2 ]; then bd_die 2 "--pair wants A,B (two issue numbers)"; fi
                shift
                local pv="$1"
                [[ "$pv" =~ ^[0-9]+,[0-9]+$ ]] || bd_die 2 "--pair wants A,B (two issue numbers)"
                local pa="${pv%%,*}" pb="${pv##*,}"
                [ "$pa" != "$pb" ] || bd_die 2 "--pair needs two distinct issues"
                if [ -n "$pairs" ]; then
                    local ep ea eb
                    while IFS= read -r ep; do
                        [ -n "$ep" ] || continue
                        ea="${ep%%,*}"; eb="${ep##*,}"
                        if [ "$ea" = "$pa" ] || [ "$eb" = "$pa" ]; then bd_die 2 "#$pa appears in two --pair flags"; fi
                        if [ "$ea" = "$pb" ] || [ "$eb" = "$pb" ]; then bd_die 2 "#$pb appears in two --pair flags"; fi
                    done <<< "$pairs"
                fi
                pairs="${pairs}${pv}"$'\n'
                shift
                ;;
            *)
                bd_die 2 "unknown argument $1"
                ;;
        esac
    done

    # 2. Load data
    bd_latest_report
    bd_parse_report "$BD_REPORT"
    bd_report_since "$BD_REPORT"
    local issues_file="$BD_TMP/issues.json"
    bd_fetch_issues "$issues_file"
    local inflight_file="$BD_TMP/inflight.tsv"
    touch "$inflight_file"
    bd_inflight "$inflight_file"

    # 3. Main selection loop — walk BD_REPORT_TSV in rank order
    local used=0 done_flag=0 notreached=0
    local held_file="$BD_TMP/held.tsv"
    touch "$held_file"
    local processed_file="$BD_TMP/processed.txt"
    touch "$processed_file"
    local picked_rows=""   # accumulate for picked.tsv (Phase 4)

    while IFS=$'\t' read -r num rank _rawline; do
        # Skip if already handled (second pair member)
        if grep -qxF "$num" "$processed_file" 2>/dev/null; then continue; fi
        printf '%s\n' "$num" >> "$processed_file"

        # Determine budget-remaining label
        local rem=$((BD_BUDGET - used))

        # Is this issue open?
        if ! bd_issue_is_open "$num" "$issues_file"; then continue; fi

        # Is this a paired issue? Find partner.
        local partner=""
        partner="$(bd_pair_find "$num" "$pairs")" || true
        local is_pair=0
        [ -z "$partner" ] || is_pair=1

        # For pairs: mark partner as processed too (will SKIP "paired with" when reached)
        if [ "$is_pair" -eq 1 ]; then
            printf '%s\n' "$partner" >> "$processed_file"
        fi

        if [ "$done_flag" -eq 1 ]; then
            notreached=$((notreached + 1))
            if [ "$is_pair" -eq 1 ]; then
                # partner also counted (but it's already in processed)
                notreached=$((notreached + 1))
            fi
            continue
        fi

        # --- Verdicts for primary candidate (and partner if pair) ---
        local skip_reason=""
        local p_skip_reason=""  # partner's skip reason

        # Check primary: stale?
        local upd title body
        upd="$(bd_issue_field "$num" "updatedAt" "$issues_file")"
        title="$(bd_issue_field "$num" "title" "$issues_file")"
        body="$(bd_issue_field "$num" "body" "$issues_file")"
        if [ "$upd" \> "$BD_SINCE" ]; then
            skip_reason="stale-rank (updated after report; run burndown-delta + burndown-refresh)"
        fi

        # Check primary: in-flight?
        if [ -z "$skip_reason" ]; then
            local ifr=""
            ifr="$(bd_inflight_reason "$num" "$inflight_file")" || true
            [ -z "$ifr" ] || skip_reason="in-flight: $ifr"
        fi

        # Check primary: paths?
        local paths=""
        if [ -z "$skip_reason" ]; then
            bd_paths_for "$body"
            paths="$_bd_paths"
            [ -n "$paths" ] || skip_reason="no file:line refs (overlap unknowable)"
        fi

        # Check primary: overlap?
        if [ -z "$skip_reason" ]; then
            local conflict=""
            conflict="$(bd_has_overlap "$paths" "$held_file")" || true
            if [ -n "$conflict" ]; then
                local c_path c_holder
                c_path="${conflict%%	*}"; c_holder="${conflict##*	}"
                skip_reason="overlap with #$c_holder on $c_path"
            fi
        fi

        # Pair partner checks (if applicable)
        if [ "$is_pair" -eq 1 ] && [ -z "$p_skip_reason" ]; then
            # Is partner ranked and open?
            if ! awk -F'\t' -v n="$partner" '$1==n{found=1;exit} END{exit !found}' <<< "$BD_REPORT_TSV" ||
               ! bd_issue_is_open "$partner" "$issues_file"; then
                p_skip_reason="pair: #$partner not an open ranked issue"
                skip_reason="$p_skip_reason"
            else
                local p_upd p_title p_body
                p_upd="$(bd_issue_field "$partner" "updatedAt" "$issues_file")"
                p_title="$(bd_issue_field "$partner" "title" "$issues_file")"
                p_body="$(bd_issue_field "$partner" "body" "$issues_file")"
                if [ "$p_upd" \> "$BD_SINCE" ]; then
                    p_skip_reason="pair: #$partner stale-rank"
                fi
                if [ -z "$p_skip_reason" ]; then
                    local p_ifr=""
                    p_ifr="$(bd_inflight_reason "$partner" "$inflight_file")" || true
                    [ -z "$p_ifr" ] || p_skip_reason="pair: #$partner in-flight: $p_ifr"
                fi
                if [ -z "$p_skip_reason" ]; then
                    bd_paths_for "$p_body"
                    local p_paths="$_bd_paths"
                    [ -n "$p_paths" ] || p_skip_reason="pair: #$partner no file:line refs"
                fi
                if [ -z "$p_skip_reason" ]; then
                    local p_conflict=""
                    p_conflict="$(bd_has_overlap "$p_paths" "$held_file")" || true
                    if [ -n "$p_conflict" ]; then
                        local pc_path pc_holder
                        pc_path="${p_conflict%%	*}"; pc_holder="${p_conflict##*	}"
                        p_skip_reason="pair: #$partner overlap with #$pc_holder on $pc_path"
                    fi
                fi
                if [ -n "$p_skip_reason" ]; then skip_reason="$p_skip_reason"; fi
            fi
        fi

        # Handle skip
        if [ -n "$skip_reason" ]; then
            printf '%-5s #%-5s %-3s %-4s %s\n' "SKIP" "$num" "$rank" "" "$skip_reason"
            continue
        fi

        # Compute cost (pair = max of both costs)
        bd_cost_for "$title"
        local cost="$_bd_cost" tier="$_bd_tier"
        if [ "$is_pair" -eq 1 ]; then
            bd_cost_for "$p_title"
            [ "$_bd_cost" -le "$cost" ] || cost="$_bd_cost"
            [ "$_bd_cost" -le 1 ] || tier="$_bd_tier"
        fi

        # Budget check — continue (not break) so later 1-unit items can fit
        if [ "$((used + cost))" -gt "$BD_BUDGET" ]; then
            printf '%-5s #%-5s %-3s %-4s %s\n' "SKIP" "$num" "$rank" "$cost" \
                "over budget (cost $cost, $rem left)"
            continue
        fi

        # PICK
        printf '%-5s #%-5s %-3s %-4s %s\n' "PICK" "$num" "$rank" "$cost" \
            "tier=${tier}${partner:+ +#$partner}"
        if [ "$is_pair" -eq 1 ]; then
            local p_rank
            p_rank="$(awk -F'\t' -v n="$partner" '$1==n{print $2;exit}' <<< "$BD_REPORT_TSV")"
            printf '%-5s #%-5s %-3s %-4s %s\n' "SKIP" "$partner" "${p_rank:-?}" "" "paired with #$num"
        fi
        used=$((used + cost))
        # Add paths to held set (path<TAB>issue_num)
        if [ -n "$paths" ]; then
            while IFS= read -r _hp; do
                [ -n "$_hp" ] || continue
                printf '%s\t%s\n' "$_hp" "$num" >> "$held_file"
            done <<< "$paths"
        fi
        if [ "$is_pair" -eq 1 ] && [ -n "${p_paths:-}" ]; then
            while IFS= read -r _hp; do
                [ -n "$_hp" ] || continue
                printf '%s\t%s\n' "$_hp" "$partner" >> "$held_file"
            done <<< "$p_paths"
        fi
        # Accumulate for picked.tsv (Phase 4)
        local also_c="${partner:-}"
        picked_rows="${picked_rows}${num}	${also_c}	${rank}	${cost}	${tier}	${title}	${paths//$'\n'/|}"$'\n'

        [ "$used" -lt "$BD_BUDGET" ] || done_flag=1
    done <<< "$BD_REPORT_TSV"

    # Unranked open issues
    jq -r '.[].number' "$issues_file" | while IFS= read -r unum; do
        if ! grep -qxF "$unum" "$processed_file" 2>/dev/null; then
            local urank
            urank="$(awk -F'\t' -v n="$unum" '$1==n{print $2;exit}' <<< "$BD_REPORT_TSV")"
            if [ -z "$urank" ]; then
                printf '%-5s #%-5s %-3s %-4s %s\n' "SKIP" "$unum" "" "" \
                    "unranked (run burndown-delta + burndown-refresh)"
            fi
        fi
    done

    printf 'units: %d/%d\n' "$used" "$BD_BUDGET"
    [ "$notreached" -eq 0 ] || printf 'not reached: %d candidate(s)\n' "$notreached"

    # Write picked.tsv for Phase 4 (ledger writing)
    printf '%s' "$picked_rows" > "$BD_TMP/picked.tsv"
}

bd_cmd_refresh()      { bd_die 2 "burndown-refresh: not implemented"; }
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
