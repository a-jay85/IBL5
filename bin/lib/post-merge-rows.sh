# shellcheck shell=bash
# post-merge-rows.sh — sourced only, never executed.
#
# The single home of the `## After merge` row grammar (ADR-0198):
#   - **Row N** [<tag>] — text
# where <tag> is `manual`, `auto:ci <name>`, or `auto:mac <name>`. A row with no tag is
# manual. Consumers: bin/after-merge-checklist and bin/digest-dm-build. Later units add the
# executors (see pmr_executor_ready) and the writer gates. Tested by bin/test-post-merge-rows.
#
# Portable to bash 3.2: no associative arrays, no ${v,,}, no mapfile, no declare -n.

PMR_END_ANCHOR='<!-- after-merge:end -->'
PMR_NAME_RE='^[A-Za-z0-9][A-Za-z0-9._-]*$'
# The bin/ directory that holds this lib. Executors are looked up here, so a checkout
# that carries the executor file turns its rows automatic and one that lacks it does not.
PMR_BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# pmr_rows FILE -- print the raw row lines of the `## After merge` section.
pmr_rows() {
    local _s
    _s="$(sed -n '/^## After merge/,/^## /p' "$1")"
    _s="$(awk -v a="$PMR_END_ANCHOR" 'index($0, a) { exit } { print }' <<< "$_s")"
    grep -E '^- (\[ \] )?\*\*Row' <<< "$_s" || true
}

# pmr_parse_row LINE -- sets PMR_KIND (manual|auto:ci|auto:mac), PMR_NAME,
# PMR_LABEL (`**Row N**`), PMR_TEXT (text after the tag), PMR_VALID (1|0).
# No tag => manual, valid (legacy #3264/#3286 shape). A malformed tag or a bad
# name => manual, PMR_VALID=0, PMR_TEXT keeps the whole remainder. Fail closed:
# anything unrecognised is a person's job, so it pings.
pmr_parse_row() {
    local _l="$1" _rest _tag _nm _after
    local _re_head='^(\*\*Row [0-9]+\*\*)[[:space:]]*(.*)$'
    local _re_tag='^\[([^]]*)\][[:space:]]*(.*)$'
    local _re_link='^\[[^]]*\]\('
    _l="${_l%$'\r'}"; _l="${_l#- }"; _l="${_l#\[ \] }"
    PMR_KIND=manual; PMR_NAME=""; PMR_LABEL=""; PMR_TEXT="$_l"; PMR_VALID=1
    if [[ "$_l" =~ $_re_head ]]; then
        PMR_LABEL="${BASH_REMATCH[1]}"; _rest="${BASH_REMATCH[2]}"; PMR_TEXT="$_rest"
        # A row whose text opens with a markdown link `[text](url)` carries no tag.
        if [[ "$_rest" =~ $_re_link ]]; then
            :
        elif [[ "$_rest" =~ $_re_tag ]]; then
            _tag="${BASH_REMATCH[1]}"; _after="${BASH_REMATCH[2]}"
            case "$_tag" in
                manual) PMR_TEXT="$_after" ;;
                "auto:ci "*|"auto:mac "*)
                    _nm="${_tag#* }"
                    if [[ "$_nm" =~ $PMR_NAME_RE ]]; then
                        PMR_KIND="${_tag%% *}"; PMR_NAME="$_nm"; PMR_TEXT="$_after"
                    else
                        PMR_VALID=0
                    fi ;;
                *) PMR_VALID=0 ;;
            esac
        fi
    fi
}

# pmr_executor_ready KIND -- 0 iff the executor that runs KIND rows exists as a regular
# file in PMR_BIN_DIR: post-merge-ci-run for auto:ci (unit 2), post-merge-run for auto:mac (unit 3).
# `-f`, not `-x`: callers run executors as `bash bin/...`, so the exec bit is not trusted.
# Fail closed: until the file lands, a row with that tag is a person's job, so it pings.
# Contract for the executor units: the file lands in the same PR that wires it to run
# (unit 2 also replaces the workflow's literal --auto-failed-count 0), because its
# presence on master is what turns the rows automatic.
pmr_executor_ready() {
    local _f
    case "${1-}" in
        auto:ci)  _f=post-merge-ci-run ;;
        auto:mac) _f=post-merge-run ;;
        *) return 1 ;;
    esac
    [ -f "$PMR_BIN_DIR/$_f" ]
}

# pmr_is_count VALUE -- 0 iff VALUE is one or more ASCII digits and nothing else.
pmr_is_count() { local _re='^[0-9]+$'; [[ "${1-}" =~ $_re ]]; }

# pmr_ping_decision MANUAL AUTO_FAILED [JOB=RESULT ...] -- sets PMR_PING (true|false)
# and PMR_PING_REASONS (space-separated). Fail closed: a non-numeric count or any
# result other than `success` pings. "> 0" is tested as "contains a 1-9 digit",
# so a 25-digit count cannot overflow shell arithmetic.
pmr_ping_decision() {
    local _m="${1-}" _f="${2-}" _j _nz='[1-9]'
    PMR_PING=false; PMR_PING_REASONS=""
    shift 2 2>/dev/null || shift $#
    if ! pmr_is_count "$_m"; then PMR_PING_REASONS+=" bad-count:manual"
    elif [[ "$_m" =~ $_nz ]]; then PMR_PING_REASONS+=" manual"; fi
    if ! pmr_is_count "$_f"; then PMR_PING_REASONS+=" bad-count:auto_failed"
    elif [[ "$_f" =~ $_nz ]]; then PMR_PING_REASONS+=" auto-failed"; fi
    for _j in "$@"; do
        if [ "${_j#*=}" != success ]; then PMR_PING_REASONS+=" job:$_j"; fi
    done
    PMR_PING_REASONS="${PMR_PING_REASONS# }"
    if [ -n "$PMR_PING_REASONS" ]; then PMR_PING=true; fi
}
