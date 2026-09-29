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

    # 3. Main selection loop — sorted by rank so P1 is always before P2-P4
    local sorted_tsv
    sorted_tsv="$(sort -s -t$'\t' -k2,2 <<< "$BD_REPORT_TSV")"
    local used=0 done_flag=0 notreached=0
    local held_file="$BD_TMP/held.tsv"
    touch "$held_file"
    local processed_file="$BD_TMP/processed.txt"
    touch "$processed_file"
    local picked_rows=""   # accumulate for ledger items
    local skipped_rows=""  # accumulate for ledger skipped

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
            skipped_rows="${skipped_rows}${num}	${skip_reason}"$'\n'
            if [ "$is_pair" -eq 1 ]; then
                local p_rank_skip
                p_rank_skip="$(awk -F'\t' -v n="$partner" '$1==n{print $2;exit}' <<< "$BD_REPORT_TSV")"
                printf '%-5s #%-5s %-3s %-4s %s\n' "SKIP" "$partner" "${p_rank_skip:-?}" "" "pair: with #$num"
                skipped_rows="${skipped_rows}${partner}	pair: with #${num}"$'\n'
            fi
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
            skipped_rows="${skipped_rows}${num}	over budget (cost $cost, $rem left)"$'\n'
            if [ "$is_pair" -eq 1 ]; then
                local p_rank_budget
                p_rank_budget="$(awk -F'\t' -v n="$partner" '$1==n{print $2;exit}' <<< "$BD_REPORT_TSV")"
                printf '%-5s #%-5s %-3s %-4s %s\n' "SKIP" "$partner" "${p_rank_budget:-?}" "" "pair: with #$num (over budget)"
                skipped_rows="${skipped_rows}${partner}	pair: with #${num} (over budget)"$'\n'
            fi
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
        # Accumulate for picked.tsv (Phase 4); use 0 as sentinel for no partner
        # (consecutive TABs collapse under IFS=$'\t', so empty field must be avoided)
        local also_c="${partner:-0}"
        local all_paths="${paths}"
        if [ "$is_pair" -eq 1 ] && [ -n "${p_paths:-}" ]; then
            all_paths="$(printf '%s\n%s' "$paths" "${p_paths}" | sort -u)"
        fi
        picked_rows="${picked_rows}${num}	${also_c}	${rank}	${cost}	${tier}	${title}	${all_paths//$'\n'/|}"$'\n'

        [ "$used" -lt "$BD_BUDGET" ] || done_flag=1
    done <<< "$sorted_tsv"

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

    printf '%s' "$picked_rows" > "$BD_TMP/picked.tsv"

    if [ "$used" -eq 0 ]; then
        printf 'LEDGER: none\n'
        return 0
    fi

    # Build items JSON array
    local items_json=""
    while IFS=$'\t' read -r _n _ac _rk _co _ti _tl _ps; do
        [ -n "$_n" ] || continue
        local _ac_arr="[]"
        [ "$_ac" = "0" ] || _ac_arr="[$_ac]"
        local _ps_arr
        _ps_arr="$(printf '%s\n' "${_ps//|/$'\n'}" | \
            jq -Rsc 'split("\n") | map(select(. != ""))')"
        items_json="${items_json}$(jq -n \
            --argjson n "$_n" --argjson ac "$_ac_arr" --arg tl "$_tl" \
            --arg rk "$_rk" --arg ti "$_ti" --argjson co "$_co" --argjson ps "$_ps_arr" \
            '{issue_num:$n,also_closes:$ac,issue_title:$tl,rank:$rk,tier:$ti,cost:$co,paths:$ps,route:"",slug:"",pr_url:"",status:"picked"}')"$'\n'
    done <<< "$picked_rows"
    local items_arr
    items_arr="$(printf '%s' "$items_json" | jq -sc '.')"

    # Build skipped JSON array
    local skipped_arr="[]"
    if [ -n "$skipped_rows" ]; then
        local skipped_json=""
        while IFS=$'\t' read -r _sn _sr; do
            [ -n "$_sn" ] || continue
            skipped_json="${skipped_json}$(jq -n \
                --argjson n "$_sn" --arg r "$_sr" '{issue_num:$n,reason:$r}')"$'\n'
        done <<< "$skipped_rows"
        [ -z "$skipped_json" ] || \
            skipped_arr="$(printf '%s' "$skipped_json" | jq -sc '.')"
    fi

    local created ledger_base
    created="$(date +%Y-%m-%dT%H:%M:%S)"
    ledger_base="$(date +%Y%m%dT%H%M%S)"
    local ledger_path="$BD_REPORTS_DIR/burndown-batch-${ledger_base}.json"
    [ ! -e "$ledger_path" ] || bd_die 3 "ledger $ledger_path exists; refusing to overwrite"

    local doc_json
    doc_json="$(jq -n \
        --argjson schema 1 \
        --arg created "$created" \
        --arg report "$BD_REPORT" \
        --argjson budget "$BD_BUDGET" \
        --argjson used "$used" \
        --argjson items "$items_arr" \
        --argjson skipped "$skipped_arr" \
        '{schema:$schema,created:$created,report:$report,budget:$budget,units_used:$used,items:$items,skipped:$skipped}')"

    local tmp_path="${ledger_path}.tmp.$$"
    printf '%s\n' "$doc_json" > "$tmp_path"
    if ! jq -e '.items | length > 0' "$tmp_path" >/dev/null 2>&1; then
        rm -f "$tmp_path"
        bd_die 3 "ledger read-back failed for $ledger_path"
    fi
    mv "$tmp_path" "$ledger_path"
    printf 'LEDGER: %s\n' "$ledger_path"
}

# bd_delta_numbers <issues_file> — sets _bd_delta_nums array
# Requires: BD_RANKED_NUMS, BD_SINCE (from bd_parse_report + bd_report_since)
bd_delta_numbers() {
    local issues_file="$1"
    local ranked_json
    ranked_json="$(printf '[%s]' "$(IFS=,; printf '%s' "${BD_RANKED_NUMS[*]:-}")")"
    _bd_delta_nums=()
    while IFS= read -r _n; do
        [ -n "$_n" ] || continue
        _bd_delta_nums+=("$_n")
    done < <(jq -r --arg since "$BD_SINCE" --argjson ranked "$ranked_json" \
        '[.[] | select(.updatedAt > $since or ([.number] | inside($ranked) | not))]
         | sort_by(.number) | .[].number' \
        "$issues_file")
}

# bd_record_apply <ledger> <issue_num> <patch-json>
# Apply a JSON patch to one ledger item, recompute units_used, and write atomically.
bd_record_apply() {
    local ledger="$1" issue_num="$2" patch="$3"
    local updated
    updated="$(jq --argjson n "$issue_num" --argjson patch "$patch" \
        '.items = [.items[] | if .issue_num == $n then . + $patch else . end]' \
        "$ledger")"
    updated="$(jq '.units_used = ([.items[].cost] | add // 0)' <<< "$updated")"
    local tmp_path="${ledger}.tmp.$$"
    printf '%s\n' "$updated" > "$tmp_path"
    if ! jq -e '.items | type=="array"' "$tmp_path" >/dev/null 2>&1; then
        rm -f "$tmp_path"
        bd_die 3 "record read-back failed for $ledger"
    fi
    mv "$tmp_path" "$ledger"
}

bd_cmd_record() {
    if [ $# -lt 3 ]; then
        bd_die 2 "burndown-record wants <ledger> <issue> key=value..."
    fi
    local ledger="$1" issue_num="$2"; shift 2
    { [ -f "$ledger" ] && jq -e '.items | type=="array"' "$ledger" >/dev/null 2>&1; } \
        || bd_die 3 "malformed or missing ledger $ledger"
    jq -e --argjson n "$issue_num" 'any(.items[]; .issue_num == $n)' "$ledger" \
        >/dev/null 2>&1 || bd_die 2 "no item #$issue_num in $ledger"

    local kv route="" slug="" status="" pr_url="" applied_keys=""
    while [ $# -gt 0 ]; do
        kv="$1"; shift
        local k="${kv%%=*}" v="${kv#*=}"
        case "$k" in
            route)
                [[ "$v" =~ ^(ad-hoc|plan)$ ]] \
                    || bd_die 2 "route=$v rejected: must be ad-hoc or plan"
                route="$v" ;;
            slug)
                [[ "$v" =~ ^[a-z0-9][a-z0-9-]{0,62}$ ]] \
                    || bd_die 2 "slug=$v rejected: must match ^[a-z0-9][a-z0-9-]{0,62}\$"
                jq -e --arg s "$v" --argjson n "$issue_num" \
                    'any(.items[]; .slug == $s and .issue_num != $n)' \
                    "$ledger" >/dev/null 2>&1 \
                    && bd_die 2 "slug=$v rejected: already used by another item"
                slug="$v" ;;
            status)
                [[ "$v" =~ ^(picked|queued|shipped|merged|closed-fixed|skipped)$ ]] \
                    || bd_die 2 "status=$v rejected: not an allowed status"
                status="$v" ;;
            pr_url)
                [[ "$v" =~ $PR_URL_EXACT_RE ]] \
                    || bd_die 2 "pr_url=$v rejected: must match PR URL pattern"
                pr_url="$v" ;;
            *)
                bd_die 2 "$k=$v rejected: unknown key $k" ;;
        esac
        applied_keys="${applied_keys} ${kv}"
    done

    # Build patch object
    local patch="{}"
    [ -z "$route" ]   || patch="$(jq -n --argjson p "$patch" --arg v "$route"   '$p+{route:$v}')"
    [ -z "$slug" ]    || patch="$(jq -n --argjson p "$patch" --arg v "$slug"    '$p+{slug:$v}')"
    [ -z "$status" ]  || patch="$(jq -n --argjson p "$patch" --arg v "$status"  '$p+{status:$v}')"
    [ -z "$pr_url" ]  || patch="$(jq -n --argjson p "$patch" --arg v "$pr_url"  '$p+{pr_url:$v}')"

    # Cross-field rules (preview the patched item without writing)
    local preview_item new_status new_route
    preview_item="$(jq --argjson n "$issue_num" --argjson patch "$patch" \
        '.items[] | select(.issue_num==$n) | . + $patch' "$ledger")"
    new_status="$(jq -r '.status' <<< "$preview_item")"
    new_route="$(jq -r '.route' <<< "$preview_item")"
    [ "$new_status" != "queued" ] || [ "$new_route" = "plan" ] \
        || bd_die 2 "status=queued requires route=plan"
    [ "$new_status" != "shipped" ] || [ "$new_route" = "ad-hoc" ] \
        || bd_die 2 "status=shipped requires route=ad-hoc"
    if [ "$new_status" = "closed-fixed" ]; then
        patch="$(jq -n --argjson p "$patch" '$p+{cost:0}')"
    fi

    bd_record_apply "$ledger" "$issue_num" "$patch"
    printf 'recorded #%s:%s\n' "$issue_num" "$applied_keys"
}

bd_cmd_refresh() {
    if [ $# -ne 1 ]; then bd_die 2 "burndown-refresh wants <ranks-file>"; fi
    local ranks_file="$1"
    [ -f "$ranks_file" ] || bd_die 2 "burndown-refresh wants <ranks-file>"

    bd_latest_report
    bd_parse_report "$BD_REPORT"
    bd_report_since "$BD_REPORT"
    local issues_file
    issues_file="$(mktemp)" || bd_die 3 "mktemp failed"
    bd_fetch_issues "$issues_file"

    # Parse ranks file manually (avoid overwriting BD_REPORT_TSV/BD_RANKED_NUMS)
    local ranks_tsv
    ranks_tsv="$(awk '
        /^## / { rank = ""; if (match($0, /^## P[1-4]( |$)/)) rank = substr($0, 4, 2); next }
        rank != "" && /^- \[#[0-9]+\]/ {
            n = $0; sub(/^- \[#/, "", n); sub(/\].*/, "", n)
            print n "\t" rank "\t" $0
        }
    ' "$ranks_file")"
    [ -n "$ranks_tsv" ] || bd_die 2 "ranks file has no ranked lines"

    local rdups
    rdups="$(cut -f1 <<< "$ranks_tsv" | sort | uniq -d)"
    [ -z "$rdups" ] || bd_die 3 "ranks file ranks #$rdups twice"

    bd_delta_numbers "$issues_file"

    # Validate: every delta num must appear in ranks file
    local dn
    for dn in "${_bd_delta_nums[@]:-}"; do
        [ -n "$dn" ] || continue
        awk -F'\t' -v n="$dn" '$1==n{found=1;exit} END{exit !found}' <<< "$ranks_tsv" \
            || bd_die 2 "ranks file omits delta issue #$dn"
    done

    # Validate: every ranks file num must be open
    while IFS=$'\t' read -r rn _rrank _rline; do
        [ -n "$rn" ] || continue
        bd_issue_is_open "$rn" "$issues_file" \
            || bd_die 2 "ranks file ranks #$rn, which is not open"
    done <<< "$ranks_tsv"

    # Build set of re-ranked nums (pipe-delimited for fast membership test)
    local reranked_set="|"
    while IFS=$'\t' read -r rn _rrank _rline; do
        [ -n "$rn" ] || continue
        reranked_set="${reranked_set}${rn}|"
    done <<< "$ranks_tsv"

    # Count re-ranked and dropped for the header line
    local reranked_count=0 dropped_count=0
    while IFS=$'\t' read -r rn _rr _rl; do
        [ -n "$rn" ] || continue
        reranked_count=$((reranked_count + 1))
    done <<< "$ranks_tsv"
    while IFS=$'\t' read -r on _or _ol; do
        [ -n "$on" ] || continue
        bd_issue_is_open "$on" "$issues_file" || dropped_count=$((dropped_count + 1))
    done <<< "$BD_REPORT_TSV"

    # Merge sections P1-P4
    local merged_sections=""
    local p
    for p in P1 P2 P3 P4; do
        local section_lines=""
        # Keep old lines: same rank, still open, not re-ranked
        while IFS=$'\t' read -r on orank oline; do
            [ -n "$on" ] || continue
            [ "$orank" = "$p" ] || continue
            bd_issue_is_open "$on" "$issues_file" || continue
            [[ "$reranked_set" == *"|${on}|"* ]] && continue
            section_lines="${section_lines}${oline}"$'\n'
        done <<< "$BD_REPORT_TSV"
        # Append re-ranked lines for this rank (ranks-file order)
        while IFS=$'\t' read -r rn rrank rline; do
            [ -n "$rn" ] || continue
            [ "$rrank" = "$p" ] || continue
            section_lines="${section_lines}${rline}"$'\n'
        done <<< "$ranks_tsv"
        local count=0
        [ -n "$section_lines" ] && count="$(grep -c '^- ' <<< "$section_lines" || true)"
        merged_sections="${merged_sections}## ${p} (${count})"$'\n'"${section_lines}"$'\n'
    done

    local out_date old_basename
    out_date="$(date +%F)"
    old_basename="$(basename "$BD_REPORT")"
    local report_path="$BD_REPORTS_DIR/backlog-triage-${out_date}.md"
    local tmp_path="${report_path}.tmp.$$"
    {
        printf '# Backlog triage %s — ranked open issues\n' "$out_date"
        printf 'Refreshed by bin/backlog burndown-refresh from %s: %d re-ranked, %d dropped.\n\n' \
            "$old_basename" "$reranked_count" "$dropped_count"
        printf '%s' "$merged_sections"
    } > "$tmp_path"
    mv "$tmp_path" "$report_path"

    rm -f "$issues_file"
    printf 'REPORT: %s\n' "$report_path"
}
# bd_pick_ledger [<ledger>] — sets BD_LEDGER to the chosen ledger path.
bd_pick_ledger() {
    if [ $# -gt 1 ]; then bd_die 2 "too many arguments; want at most one ledger path"; fi
    if [ $# -eq 1 ] && [ -n "${1:-}" ]; then
        local explicit="$1"
        { [ -f "$explicit" ] && jq -e '.items | type=="array"' "$explicit" >/dev/null 2>&1; } \
            || bd_die 3 "malformed or missing ledger $explicit"
        BD_LEDGER="$explicit"
        return
    fi
    local best="" f name
    for f in "$BD_REPORTS_DIR"/burndown-batch-*.json; do
        [ -f "$f" ] || continue
        name="$(basename "$f")"
        if [ -z "$best" ] || [[ "$name" > "$(basename "$best")" ]]; then
            best="$f"
        fi
    done
    [ -n "$best" ] \
        || bd_die 3 "no burndown ledger in $BD_REPORTS_DIR; run bin/backlog burndown first"
    BD_LEDGER="$best"
}

# bd_live_state <item-json> — prints "<state>\t<pr_url_or_empty>"
bd_live_state() {
    local item="$1"
    local slug status route
    slug="$(jq -r '.slug // ""' <<< "$item")"
    status="$(jq -r '.status' <<< "$item")"
    route="$(jq -r '.route // ""' <<< "$item")"

    if [ -z "$slug" ]; then
        if [ "$status" = "picked" ]; then
            printf 'unrouted\t\n'
        else
            printf '%s\t\n' "$status"
        fi
        return
    fi

    local pr_raw pr_rc=0
    pr_raw="$("$GH" pr list --repo "$BD_CODE_REPO" --head "$slug" --state all \
        --limit 5 --json url,state 2>/dev/null)" || pr_rc=$?
    if [ "$pr_rc" -ne 0 ] || ! jq -e 'type=="array"' <<< "$pr_raw" >/dev/null 2>&1; then
        printf 'unknown (gh failed)\t\n'
        return
    fi

    local pr_count
    pr_count="$(jq 'length' <<< "$pr_raw")"
    if [ "$pr_count" -gt 1 ]; then
        printf 'unknown (%s PRs for head %s)\t\n' "$pr_count" "$slug"
        return
    fi

    if [ "$pr_count" -eq 1 ]; then
        local pr_state pr_url
        pr_state="$(jq -r '.[0].state' <<< "$pr_raw")"
        pr_url="$(jq -r '.[0].url' <<< "$pr_raw")"
        case "$pr_state" in
            MERGED) printf 'merged\t%s\n' "$pr_url" ;;
            OPEN)   printf 'open\t%s\n' "$pr_url" ;;
            CLOSED) printf 'pr-closed\t%s\n' "$pr_url" ;;
            *)      printf 'unknown (state %s)\t%s\n' "$pr_state" "$pr_url" ;;
        esac
        return
    fi

    # Zero PRs
    if [ "$route" = "plan" ] && [ -f "$BD_QUEUE_DIR/${slug}.md" ]; then
        printf 'queued\t\n'
    else
        printf '%s\t\n' "$status"
    fi
}

bd_cmd_status() {
    if [ $# -gt 1 ]; then bd_die 2 "burndown-status wants at most one argument"; fi
    bd_pick_ledger "${1:-}"
    local ledger="$BD_LEDGER"

    local basename units_used budget
    basename="$(basename "$ledger")"
    units_used="$(jq '.units_used' "$ledger")"
    budget="$(jq '.budget' "$ledger")"
    printf 'batch %s  units %s/%s\n' "$basename" "$units_used" "$budget"

    local n=0 merged_count=0 open_count=0 queued_count=0 unknown_count=0
    local item_count
    item_count="$(jq '.items | length' "$ledger")"
    while [ "$n" -lt "$item_count" ]; do
        local item issue_num route ledger_status slug also_str
        item="$(jq ".items[$n]" "$ledger")"
        issue_num="$(jq -r '.issue_num' <<< "$item")"
        route="$(jq -r '.route // ""' <<< "$item")"
        ledger_status="$(jq -r '.status' <<< "$item")"
        also_str="$(jq -r '.also_closes // [] | map("+#"+tostring) | join("")' <<< "$item")"
        slug="$(jq -r '.slug // ""' <<< "$item")"

        local live_line live_state live_pr
        live_line="$(bd_live_state "$item")"
        live_state="$(printf '%s' "$live_line" | cut -f1)"
        live_pr="$(printf '%s' "$live_line" | cut -f2)"

        local display_id display_loc
        display_id="#${issue_num}${also_str}"
        display_loc="${live_pr:-$slug}"

        printf '%-8s %-7s %-12s %-26s %s\n' \
            "$display_id" "$route" "$ledger_status" "$live_state" "$display_loc"

        case "$live_state" in
            merged)    merged_count=$((merged_count + 1)) ;;
            open)      open_count=$((open_count + 1)) ;;
            queued)    queued_count=$((queued_count + 1)) ;;
            unknown*)  unknown_count=$((unknown_count + 1)) ;;
        esac
        n=$((n + 1))
    done

    printf 'merged %d  open %d  queued %d  unknown %d\n' \
        "$merged_count" "$open_count" "$queued_count" "$unknown_count"
    [ "$unknown_count" -eq 0 ]
}

bd_cmd_close_merged() {
    if [ $# -gt 1 ]; then bd_die 2 "burndown-close-merged wants at most one argument"; fi
    bd_pick_ledger "${1:-}"
    local ledger="$BD_LEDGER"

    local n=0 item_count any_fail=0
    item_count="$(jq '.items | length' "$ledger")"
    while [ "$n" -lt "$item_count" ]; do
        local item issue_num ledger_status route
        item="$(jq ".items[$n]" "$ledger")"
        issue_num="$(jq -r '.issue_num' <<< "$item")"
        ledger_status="$(jq -r '.status' <<< "$item")"
        route="$(jq -r '.route // ""' <<< "$item")"
        n=$((n + 1))

        case "$ledger_status" in merged|closed-fixed|skipped) continue ;; esac

        local live_line live_state live_pr
        live_line="$(bd_live_state "$item")"
        live_state="$(printf '%s' "$live_line" | cut -f1)"
        live_pr="$(printf '%s' "$live_line" | cut -f2)"

        case "$live_state" in
            unknown*)
                printf 'SKIP #%s %s\n' "$issue_num" "$live_state"
                any_fail=1
                continue
                ;;
        esac

        if [ "$live_state" != "merged" ]; then
            printf 'WAIT #%s %s\n' "$issue_num" "$live_state"
            continue
        fi

        [[ "$live_pr" =~ $PR_URL_EXACT_RE ]] \
            || bd_die 3 "close-merged: invalid PR URL for #$issue_num: ${live_pr:-empty}"

        if [ "$route" = "plan" ]; then
            local patch
            patch="$(jq -n --arg s "merged" --arg u "$live_pr" '{status:$s,pr_url:$u}')"
            bd_record_apply "$ledger" "$issue_num" "$patch"
            printf 'DONE #%s plan PR merged\n' "$issue_num"
            continue
        fi

        local issue_list_file
        issue_list_file="$(mktemp)"
        printf '%s\n' "$issue_num" > "$issue_list_file"
        jq -r '.also_closes // [] | .[]' <<< "$item" >> "$issue_list_file"

        local item_ok=1
        while IFS= read -r inum; do
            [ -n "$inum" ] || continue
            local view_raw="" view_rc=0
            view_raw="$("$GH" issue view "$inum" --repo "$REPO" \
                --json state 2>/dev/null)" || view_rc=$?
            if [ "$view_rc" -ne 0 ]; then
                printf 'FAIL #%s issue view %s failed\n' "$issue_num" "$inum"
                item_ok=0
                break
            fi
            local istate
            istate="$(jq -r '.state // ""' <<< "$view_raw")"
            case "$istate" in
                CLOSED) continue ;;
                OPEN)
                    local close_rc=0
                    "$GH" issue close "$inum" --repo "$REPO" \
                        -c "Fixed by ${live_pr} (merged). Closed by bin/backlog burndown-close-merged from $(basename "$ledger")." \
                        >/dev/null 2>&1 || close_rc=$?
                    if [ "$close_rc" -ne 0 ]; then
                        printf 'FAIL #%s issue close %s failed\n' "$issue_num" "$inum"
                        item_ok=0
                        break
                    fi
                    ;;
                *)
                    printf 'FAIL #%s issue view %s returned state %s\n' \
                        "$issue_num" "$inum" "$istate"
                    item_ok=0
                    break
                    ;;
            esac
        done < "$issue_list_file"
        rm -f "$issue_list_file"

        if [ "$item_ok" -eq 1 ]; then
            local patch
            patch="$(jq -n --arg s "merged" --arg u "$live_pr" '{status:$s,pr_url:$u}')"
            bd_record_apply "$ledger" "$issue_num" "$patch"
            printf 'DONE #%s closed\n' "$issue_num"
        else
            any_fail=1
        fi
    done

    [ "$any_fail" -eq 0 ]
}

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
