# shellcheck shell=bash
# Shared Verification Matrix row parser. Sourced by bin/check-plan and
# bin/lib/prefire-verify. A matrix row is `| N | what | type | timing | location |`.
# A pipe inside a cell is written `\|`; it is swapped for a sentinel before the
# split and restored afterward, so the restored cell is the runnable command.

MSEN=$'\001'

mtrim() { _s="${1#"${1%%[![:space:]]*}"}"; printf '%s' "${_s%"${_s##*[![:space:]]}"}"; }

# mtrim_v <var> <string> — same trim as mtrim, assigned to <var> in the
# current shell (no subshell). <var> is always a literal name chosen by the
# caller, never plan data. Scratch is _mtv_s so a caller's _s survives.
mtrim_v() { _mtv_s="${2#"${2%%[![:space:]]*}"}"; printf -v "$1" '%s' "${_mtv_s%"${_mtv_s##*[![:space:]]}"}"; }

# matrix_split <row> — sets MC_NUM / MC_TYPE / MC_TIME / MC_LOC (trimmed, any
# surrounding backticks stripped, pipes restored) and MC_PIPES (count of
# STRUCTURAL pipes, i.e. after escaped pipes are hidden). Returns 1 for a
# separator row or a row with no location cell.
matrix_split() {
    case "$1" in
        *[!\ \|:-]*) : ;;
        *) return 1 ;;              # separator / all-dashes row
    esac
    _r="${1//\\|/$MSEN}"
    IFS='|' read -r _lead MC_NUM MC_WHAT MC_TYPE MC_TIME MC_LOC _rest <<< "$_r"
    _mp_t="${_r//[!|]/}"
    # shellcheck disable=SC2034  # read by bin/check-plan gates
    MC_PIPES=${#_mp_t}
    mtrim_v MC_NUM "$MC_NUM"; mtrim_v MC_TYPE "$MC_TYPE"; mtrim_v MC_TIME "$MC_TIME"
    mtrim_v MC_WHAT "$MC_WHAT"
    mtrim_v MC_LOC "${MC_LOC//$MSEN/|}"
    case "$MC_LOC" in '`'*'`') mtrim_v MC_LOC "${MC_LOC#\`}"; mtrim_v MC_LOC "${MC_LOC%\`}" ;; esac
    [ -n "$MC_LOC" ] || return 1
    return 0
}

# plan_matrix_rows <plan-file> — prints "<num><TAB><command>" for each runnable
# row of the plan's `## Verification Matrix` section. Runnable means: a real
# data row, whole-cell CLI-executable type, post-impl timing, not a one-time
# check, and not a post-merge location. Returns 2 when the plan has no
# Verification Matrix heading, 0 otherwise (zero runnable rows is valid).
plan_matrix_rows() {
    local plan="$1" in_matrix=0 found=0 line type_lc
    [ -f "$plan" ] || return 2
    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in
            '## Verification Matrix'*) in_matrix=1; found=1; continue ;;
            '## '*) in_matrix=0; continue ;;
        esac
        [ "$in_matrix" -eq 1 ] || continue
        case "$line" in '|'*) : ;; *) continue ;; esac
        matrix_split "$line" || continue
        case "$MC_NUM" in ''|'#') continue ;; esac
        type_lc=$(printf '%s' "$MC_TYPE" | tr '[:upper:]' '[:lower:]')
        # whole-cell match, optional parenthesized annotation
        [[ "$type_lc" =~ ^cli[-\ ]executable(\ *\([^\)]*\))?$ ]] || continue
        case "$MC_TIME" in *post-impl*) : ;; *) continue ;; esac
        case "$MC_WHAT" in *'(one-time-check:'*) continue ;; esac
        case "$MC_LOC" in *'post-merge:'*) continue ;; esac
        printf '%s\t%s\n' "$MC_NUM" "$MC_LOC"
    done < "$plan"
    [ "$found" -eq 1 ] || return 2
    return 0
}

# --- row helpers shared with prefire-verify and plan-checks-block ----------
# (moved from prefire-verify)
CD_IBL5_RE='^cd[[:space:]]+ibl5[[:space:]]*&&[[:space:]]*(.*)$'
is_whole_suite_cmd() {
    local c="$1"
    c="${c#"${c%%[![:space:]]*}"}"
    c="${c%"${c##*[![:space:]]}"}"
    if [[ "$c" =~ $CD_IBL5_RE ]]; then c="${BASH_REMATCH[1]}"; fi
    case "$c" in
        'vendor/bin/phpunit'|'vendor/bin/phpunit --no-progress'|'composer run analyse'|'composer run analyse:tests'|'composer run test'|'composer test') return 0 ;;
    esac
    return 1
}

# DB-touching rows: prefire-verify probes the DB before the first one, and the
# CI sorter keeps them local.
PLAN_ROW_DB_RE='db-query|docker|--group[= ]database|DatabaseIntegration|db-test-up|run-migrations|mariadb'

# A row runs in CI only when it matches no local marker AND its first word
# is on the allowlist. Anything else stays local (fail toward local).
PLAN_ROW_LOCAL_RE="$PLAN_ROW_DB_RE"'|localhost|127\.0\.0\.1|curl|wget|playwright|npx|npm|(^|[^[:alnum:]_/-])gh[[:space:]]|bin/wt-|bin/automouse|bin/post-plan|bin/plan-now|bin/check-plan|launchctl|osascript|ssh|scp|rsync|~/|\$HOME|/Users/|/tmp/|claude-plans|PLAN|\.\./'
row_runs_in_ci() {
    local c="$1" first second
    grep -Eq -- "$PLAN_ROW_LOCAL_RE" <<< "$c" && return 1
    is_whole_suite_cmd "$c" && return 1
    c="${c#"${c%%[![:space:]]*}"}"
    while [[ "$c" =~ ^cd[[:space:]]+([A-Za-z0-9_./-]+)[[:space:]]*\&\&[[:space:]]*(.*)$ ]]; do
        c="${BASH_REMATCH[2]}"
    done
    c="${c#!}"; c="${c#"${c%%[![:space:]]*}"}"
    first="${c%%[[:space:]]*}"; second="${c#"$first"}"; second="${second#"${second%%[![:space:]]*}"}"; second="${second%%[[:space:]]*}"
    case "$first" in
        bin/test-*|bin/check-*|bin/lib/*|vendor/bin/phpunit|python3|shellcheck|grep|rg|jq) return 0 ;;
        command) [ "$second" = grep ] && return 0 ;;
        git) case "$second" in grep|ls-files|check-ignore) return 0 ;; esac ;;
        bash) [ "$second" = -n ] && return 0 ;;
    esac
    return 1
}
