# shellcheck shell=bash
# post-merge-rows.sh — sourced only, never executed.
#
# The single home of the `## After merge` row grammar (ADR-0198):
#   - **Row N** [<tag>] — text
# where <tag> is `manual`, `auto:ci <name>`, or `auto:mac <name>`. A row with no tag is
# manual. Consumers: bin/after-merge-checklist, bin/digest-dm-build, bin/check-plan gate [AM],
# bin/check-post-merge-recipe and bin/post-merge-run. The auto:mac executor exists; a later
# unit adds the auto:ci executor (see pmr_executor_ready). Tested by bin/test-post-merge-rows.
#
# Portable to bash 3.2: no associative arrays, no ${v,,}, no mapfile, no declare -n.

PMR_END_ANCHOR='<!-- after-merge:end -->'
PMR_NAME_RE='^[A-Za-z0-9][A-Za-z0-9._-]*$'
# Owner-after-merge prose (ADR-0198 update 2026-11-20). Case-insensitive ERE, one line at a
# time. Calibrated 2026-11-20: 2 hits in 250 merged bodies (#4046, #4035), 0 false positives.
PMR_PROSE_RE='(^|[^[:alnum:]_])(owner|you|someone|a person)( (will|must|should|can|has to|have to|needs to|need to))? (run|runs|install|installs)([^[:alnum:]_]|$)[^.;]{0,80}((after|once) (the )?(this )?(pr )?(is |gets |has )?(merge|merges|merged)([,.;:)]|$| (on|in|from|to|by|and|then))|by hand|manually)|not installed by (merging|the merge)|((after|once) (the )?(this )?(pr )?(is |gets |has )?(merge|merges|merged)([,.;:)]|$| (on|in|from|to|by|and|then))[^.;]{0,60}(^|[^[:alnum:]_])(owner|you) (run|runs|must|install))|(^|[^[:alnum:]_])(owner|you) (run|runs|must run)[^.;]{0,60}by hand'
PMR_WAIVER_TAG='after-merge-prose-ok:'
# The bin/ directory that holds this lib. Executors are looked up here, so a checkout
# that carries the executor file turns its rows automatic and one that lacks it does not.
PMR_BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# pmr_strip_fences FILE -- print FILE with fenced code blocks (fence lines included) dropped.
# A fence opens on ``` or ~~~; it closes on a run of the opener's character, at least as long,
# with no text after it. A 3-backtick block inside a 4-backtick block stays fenced.
# The Python copy is planfile.py _strip_fenced_after_merge (parse_after_merge_section copies
# pmr_rows' section bounds); test_after_merge_parsers_agree in the harness tests checks both.
pmr_strip_fences() {
    awk '/^[[:space:]]*(```|~~~)/ {
            s = $0; sub(/^[[:space:]]*/, "", s); c = substr(s, 1, 1); n = 0
            while (substr(s, n + 1, 1) == c) n++
            if (!f) { f = 1; fc = c; fl = n }
            else if (c == fc && n >= fl && substr(s, n + 1) ~ /^[[:space:]]*$/) f = 0
            next
        }
        !f' "$1"
}

# pmr_rows FILE -- print the raw row lines of the `## After merge` section. Fenced code blocks
# are dropped first: a row (or a heading) inside a fence is an example, not a real row.
pmr_rows() {
    local _s
    _s="$(pmr_strip_fences "$1" | sed -n '/^## After merge/,/^## /p')"
    _s="$(awk -v a="$PMR_END_ANCHOR" 'index($0, a) { exit } { print }' <<< "$_s")"
    grep -E '^- (\[ \] )?\*\*Row' <<< "$_s" || true
}

# pmr_parse_row LINE -- sets PMR_KIND (manual|auto:ci|auto:mac), PMR_NAME,
# PMR_LABEL (`**Row N**`), PMR_TEXT (text after the tag), PMR_VALID (1|0).
# No tag => manual, valid (legacy #3264/#3286 shape). A malformed tag or a bad
# name => manual, PMR_VALID=0, PMR_TEXT keeps the whole remainder. Fail closed:
# anything unrecognised is a person's job, so the digest lists it.
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
                    # shellcheck disable=SC2034  # PMR_TEXT is an output callers read
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
# Fail closed: until the file lands, a row with that tag is a person's job, so the digest lists it.
# Contract for the executor units: the file lands in the same PR that wires it to run
# (unit 2, the post-merge-ci-run executor, replaces the workflow's literal --auto-failed-count 0 with its
# auto:ci failures; auto:mac failures never reach that count, because bin/post-merge-run runs on
# the Mac after the digest and sends one DM per failing PR itself), because its
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

# pmr_prose_text FILE [KEEP_COMMENTS] -- `lineno<TAB>text` for prose lines only: fenced blocks
# and blockquotes are dropped, each inline code span becomes CODE, and HTML comments are cut
# out (only the commented text goes, so prose before `<!--` on the same line stays). With
# KEEP_COMMENTS=1 the comments stay, so pmr_waiver can read its marker.
pmr_prose_text() {
    awk -v keep="${2-0}" '
        /^[[:space:]]*(```|~~~)/ {
            # Same fence walk as pmr_strip_fences: a closing fence uses the same
            # character, is at least as long as the opener, and has no text after it.
            s = $0; sub(/^[[:space:]]*/, "", s); c = substr(s, 1, 1); n = 0
            while (substr(s, n + 1, 1) == c) n++
            if (!infence) { infence = 1; fc = c; fl = n }
            else if (c == fc && n >= fl && substr(s, n + 1) ~ /^[[:space:]]*$/) infence = 0
            next
        }
        infence { next }
        # Inside a comment a `>` line goes to the cutter, so a `-->` on it closes the comment.
        # Outside one it is skipped whole, so a `<!--` on it opens nothing.
        !incomment && /^[[:space:]]*>/ { next }
        {
            line = $0; gsub(/`[^`]*`/, "CODE", line)
            if (keep != 1) {
                out = ""
                while (length(line) > 0) {
                    if (incomment) {
                        i = index(line, "-->")
                        if (i == 0) { line = ""; break }
                        line = substr(line, i + 3); incomment = 0
                    } else {
                        i = index(line, "<!--")
                        if (i == 0) { out = out line; line = ""; break }
                        out = out substr(line, 1, i - 1); line = substr(line, i + 4); incomment = 1
                    }
                }
                line = out
                if (line == "" && $0 != "") next
            }
            print NR "\t" line
        }
    ' "$1"
}

# pmr_prose_hits FILE -- prints each owner-after-merge prose line; 0 iff at least one.
pmr_prose_hits() {
    local _t
    _t="$(pmr_prose_text "$1" | grep -iE "$PMR_PROSE_RE" || true)"
    [ -n "$_t" ] || return 1
    printf '%s\n' "$_t"
}

# pmr_waiver FILE -- 0 valid `<!-- after-merge-prose-ok: <15+ chars> -->`, 1 none, 2 malformed.
pmr_waiver() {
    local _m _r
    _m="$(pmr_prose_text "$1" 1 | grep -m1 -F "<!-- $PMR_WAIVER_TAG" || true)"
    [ -n "$_m" ] || return 1
    _r="${_m#*"$PMR_WAIVER_TAG"}"; _r="${_r%%-->*}"
    _r="$(printf '%s' "$_r" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"
    [ "${#_r}" -ge 15 ] || return 2
}

# pmr_plan_check PLAN STEPS_DIR [TAKEN] -- one `[AM] ...` line per violation; 0 iff none.
# TAKEN is a space-separated list of Verification Matrix row numbers.
pmr_plan_check() {
    local _plan="$1" _steps="$2" _taken=" ${3-} " _out="" _line _n _rows _w=0 _seen=" "
    _rows="$(pmr_rows "$_plan")"
    while IFS= read -r _line; do
        [ -n "$_line" ] || continue
        pmr_parse_row "$_line"
        _n="${PMR_LABEL//[!0-9]/}"
        if [ "$PMR_VALID" -ne 1 ]; then
            _out+="[AM] unparseable After merge row (tag must be manual, auto:mac <name> or auto:ci <name>): $_line"$'\n'; continue
        fi
        case "$_seen" in *" $_n "*) _out+="[AM] After merge row $_n repeats an earlier After merge row number (bin/post-merge-run keys its done marker by number, so the second row would be skipped): $_line"$'\n' ;; esac
        _seen+="$_n "
        case "$_taken" in *" $_n "*) _out+="[AM] After merge row $_n repeats a Verification Matrix row number: $_line"$'\n' ;; esac
        if [ "$PMR_KIND" = auto:ci ] && ! pmr_executor_ready auto:ci; then
            _out+="[AM] auto:ci has no executor on this checkout; write the row as manual: $_line"$'\n'
        fi
        if [ "$PMR_KIND" = auto:mac ] && [ ! -f "$_steps/$PMR_NAME" ] && ! grep -qE "bin/post-merge-steps/$PMR_NAME.*(— NEW|Create)" "$_plan"; then
            _out+="[AM] auto:mac $PMR_NAME: no bin/post-merge-steps/$PMR_NAME on disk and no creation-cue line in the plan: $_line"$'\n'
        fi
    done <<< "$_rows"
    # Herestring, not a pipe: under pipefail an early grep -q exit SIGPIPEs the producer and
    # flips the test (.claude/rules/shell-pipefail-grep.md).
    if [ -z "$_rows" ] && ! grep -qiE '(^|[^a-z-])post-merge:' <<< "$(pmr_prose_text "$_plan")"; then
        pmr_waiver "$_plan" || _w=$?
        if [ "$_w" -eq 2 ]; then
            _out+="[AM] after-merge-prose-ok waiver needs a reason of at least 15 characters"$'\n'
        elif [ "$_w" -eq 1 ] && pmr_prose_hits "$_plan" > /dev/null; then
            _out+="[AM] owner-after-merge prose with no After merge row; add a ## After merge row (ADR-0198) or a post-merge: matrix row"$'\n'
        fi
    fi
    [ -z "$_out" ] && return 0
    printf '%s' "$_out"; return 1
}

# pmr_needs_you_reasons MANUAL AUTO_FAILED [JOB=RESULT ...] -- sets
# PMR_NEEDS_YOU_REASONS (space-separated), the after-merge items the digest names.
# Fail closed: a non-numeric count or any result other than `success` adds a reason.
# "> 0" is tested as "contains a 1-9 digit", so a 25-digit count cannot overflow
# shell arithmetic.
pmr_needs_you_reasons() {
    local _m="${1-}" _f="${2-}" _j _nz='[1-9]'
    PMR_NEEDS_YOU_REASONS=""
    shift 2 2>/dev/null || shift $#
    if ! pmr_is_count "$_m"; then PMR_NEEDS_YOU_REASONS+=" bad-count:manual"
    elif [[ "$_m" =~ $_nz ]]; then PMR_NEEDS_YOU_REASONS+=" manual"; fi
    if ! pmr_is_count "$_f"; then PMR_NEEDS_YOU_REASONS+=" bad-count:auto_failed"
    elif [[ "$_f" =~ $_nz ]]; then PMR_NEEDS_YOU_REASONS+=" auto-failed"; fi
    for _j in "$@"; do
        if [ "${_j#*=}" != success ]; then PMR_NEEDS_YOU_REASONS+=" job:$_j"; fi
    done
    PMR_NEEDS_YOU_REASONS="${PMR_NEEDS_YOU_REASONS# }"
}
