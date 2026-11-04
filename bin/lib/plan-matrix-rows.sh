# shellcheck shell=bash
# Shared Verification Matrix row parser. Sourced by bin/check-plan and
# bin/lib/prefire-verify. A matrix row is `| N | what | type | timing | location |`.
# A pipe inside a cell is written `\|`; it is swapped for a sentinel before the
# split and restored afterward, so the restored cell is the runnable command.

MSEN=$'\001'

mtrim() { _s="${1#"${1%%[![:space:]]*}"}"; printf '%s' "${_s%"${_s##*[![:space:]]}"}"; }

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
    # shellcheck disable=SC2034  # read by bin/check-plan gates
    MC_PIPES=$(printf '%s' "$_r" | tr -cd '|' | wc -c | tr -d ' ')
    MC_NUM=$(mtrim "$MC_NUM"); MC_TYPE=$(mtrim "$MC_TYPE"); MC_TIME=$(mtrim "$MC_TIME")
    MC_WHAT=$(mtrim "$MC_WHAT")
    MC_LOC=$(mtrim "${MC_LOC//$MSEN/|}")
    case "$MC_LOC" in '`'*'`') MC_LOC=$(mtrim "${MC_LOC#\`}"); MC_LOC=$(mtrim "${MC_LOC%\`}") ;; esac
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
