# shellcheck shell=bash
# bin/lib/harness-select.sh — map changed repo paths to the shell harnesses they can
# affect, for bin/run-shell-harnesses --select and bin/lib/prefire-verify.
#
# Sourced, never run. It sets no shell options of its own. Every path is relative to
# the current directory, which callers keep at the repo root. bash 3.2 clean: sets are
# space-padded strings matched with case, and there are no associative arrays.
#
# A harness's coverage is derived, never declared. The scan reads the harness script
# and, breadth-first, every script it names (an executable file, *.sh or *.py, anywhere
# in the repo). It collects each repo path those files name. A variable or glob inside
# a path widens to its static directory. A closure line that reads the tree dynamically
# (git ls-files, find, a tool runner such as phpunit) makes the harness always-run.
# bin/lib/harness-covers.txt can only widen: it adds paths a harness reads that the
# scan cannot see, and lists inert directories that select nothing when no harness
# names the changed path.
#
# Every unknown runs more: a shared path (bin/lib/, .github/, bin/run-shell-harnesses,
# a top-level file), a path no harness claims outside the inert directories, an event
# other than pull_request, a re-run, a non-merge HEAD, a failed diff, an empty list,
# or a harness the scan cannot read selects every harness.

HS_COVERS="${HARNESS_COVERS:-bin/lib/harness-covers.txt}"
HS_LEGS=".github/workflows/harness-legs.txt"
HS_DYNAMIC_RE='(^|[^A-Za-z0-9_-])(git[[:space:]].*(ls-files|grep|ls-tree|archive|diff|log|show|rev-list|cat-file|status)|find|eval|xargs|rg|phpunit|phpstan|composer|php|bun|npm|npx|node|docker|pytest|shellcheck|actionlint)([[:space:]]|$)|python3[[:space:]]+-m|grep[[:space:]]+-[A-Za-z]*[rR]|\*\*|os\.walk|glob|listdir|scandir|subprocess'

shell_harness_for() { # <changed bin/ path>; prints the harness path it maps to
    local rel="${1#bin/}"
    case "$rel" in
        test-*) case "$rel" in */*) ;; *) printf 'bin/%s' "$rel"; return 0 ;; esac ;;
    esac
    rel="${rel#lib/}"
    rel="${rel%.sh}"
    printf 'bin/test-%s' "${rel//\//-}"
}

hs_norm() { # <raw token>; prints PATH <p> or DIR <d>
    local t="$1" pre
    case "$t" in
        *[\$\{\*\?\[]*) pre="${t%%[\$\{\*\?\[]*}"; pre="${pre%/*}"; printf 'DIR %s\n' "${pre:-${t%%/*}}" ;;
        *) printf 'PATH %s\n' "${t%/}" ;;
    esac
}

# --- per-file scan, cached for one run --------------------------------------
HS_CACHE=""
HS_ROOTS=""

hs_cache_open() { # creates the per-run scan cache and the repo root set
    [ -z "$HS_CACHE" ] || return 0
    HS_CACHE="$(mktemp -d "${TMPDIR:-/tmp}/harness-select.XXXXXX")" || return 1
    # Root names come from the repo root listing, minus .git. A token must start with one.
    HS_ROOTS="$(ls -A | awk '$0 != ".git" && $0 !~ /[^A-Za-z0-9_.@-]/' | tr '\n' ' ')"
}

hs_cache_close() {
    [ -z "$HS_CACHE" ] || rm -rf "$HS_CACHE"
    HS_CACHE=""
}

hs_tokens() { # <file>; prints each raw repo-path token the file names, one per line
    HS_ROOTS="$HS_ROOTS" awk -v q="'" '
    function emit(t) {
        while (t ~ /[.,:;)]$/) t = substr(t, 1, length(t) - 1)
        if (t != "") print t
    }
    BEGIN {
        n = split(ENVIRON["HS_ROOTS"], R, " ")
        alt = ""
        for (i = 1; i <= n; i++) {
            r = R[i]; gsub(/\./, "[.]", r); E[i] = r
            alt = alt (alt == "" ? "" : "|") r
        }
        re = "(" alt ")/[A-Za-z0-9_./@{}$*?[-]*"
    }
    /^[[:space:]]*#/ { next }
    n == 0 { next }
    {
        L = $0; pos = 1
        while (pos <= length(L)) {
            s = substr(L, pos)
            if (!match(s, re)) break
            st = pos + RSTART - 1
            b = (st == 1) ? "" : substr(L, st - 1, 1)
            if (b != "" && b ~ /[A-Za-z0-9_.-]/) { pos = st + 1; continue }
            emit(substr(L, st, RLENGTH)); pos = st + RLENGTH
        }
        for (i = 1; i <= n; i++) {
            if (match(L, "(^|[^A-Za-z0-9_.-])cd[[:space:]]+" E[i] "([^A-Za-z0-9_./-]|$)") ||
                match(L, "=" E[i] "([^A-Za-z0-9_./-]|$)") ||
                match(L, "[\"" q "]" E[i] "[\"" q "]")) {
                if (system("test -d \"" R[i] "\"") == 0) print R[i]
            }
        }
    }' "$1"
}

hs_scan_file() { # <file>; fills the cache: <key>.tok (normalized) and <key>.dyn (reason)
    local f="$1" key raw t hit
    key="$HS_CACHE/${f//\//%}"
    [ -f "$key.tok" ] && return 0
    [ -f "$f" ] && [ -r "$f" ] || return 1
    raw="$(hs_tokens "$f")" || return 1
    : >"$key.tmp" || return 1
    while IFS= read -r t; do
        [ -n "$t" ] && hs_norm "$t" >>"$key.tmp"
    done <<<"$raw"
    hit="$(awk '{ if ($0 ~ /^[[:space:]]*#/) print ""; else print }' "$f" | grep -nE "$HS_DYNAMIC_RE" | sed -n '1p')" || true
    if [ -n "$hit" ]; then
        printf 'dynamic read at %s:%s\n' "$f" "${hit%%:*}" >"$key.dyn"
    else
        : >"$key.dyn"
    fi
    sort -u "$key.tmp" >"$key.tok" || return 1
    rm -f "$key.tmp"
}

hs_file_key() { HS_KEY="$HS_CACHE/${1//\//%}"; }

hs_followable() { # <path>; true for a script the closure follows
    [ -f "$1" ] || return 1
    [ "$1" != bin/lib/harness.sh ] || return 1
    case "$1" in *.sh|*.py) return 0 ;; esac
    [ -x "$1" ]
}

hs_closure() { # <harness>; prints every file in its closure, harness first
    # It stops at the first file with a dynamic read and prints ALWAYS <reason> last.
    local h="$1" queue f tok kind seen
    seen=" $h "
    queue="$h"
    while [ -n "$queue" ]; do
        f="${queue%% *}"
        case "$queue" in *" "*) queue="${queue#* }" ;; *) queue="" ;; esac
        [ -n "$f" ] || continue
        hs_scan_file "$f" || return 1
        printf '%s\n' "$f"
        hs_file_key "$f"
        if [ -s "$HS_KEY.dyn" ]; then printf 'ALWAYS %s\n' "$(cat "$HS_KEY.dyn")"; return 0; fi
        while read -r kind tok; do
            [ "$kind" = PATH ] || continue
            hs_followable "$tok" || continue
            case "$seen" in
                *" $tok "*) ;; *) seen="$seen $tok "; queue="$queue $tok" ;;
            esac
        done <"$HS_KEY.tok"
        queue="${queue# }"
    done
}

hs_legs_args() { # <harness>; prints the hs_norm form of every legs-line argument after it
    local a
    [ -f "$HS_LEGS" ] || return 0
    while IFS= read -r a; do
        [ -n "$a" ] && hs_norm "$a"
    done < <(awk -v h="$1" '
        /^[[:space:]]*(#|$)/ || $1 == "exempt" { next }
        { for (i = 2; i <= NF; i++) if ($i !~ /^[A-Za-z_][A-Za-z0-9_]*=/) break
          if (i > NF || $i != h) next
          for (j = i + 1; j <= NF; j++) print $j }' "$HS_LEGS")
    return 0
}

hs_cover_lines() { # prints the override file's entries, comments stripped
    [ -f "$HS_COVERS" ] || return 0
    sed -e 's/[[:space:]]#.*$//' -e '/^[[:space:]]*#/d' -e '/^[[:space:]]*$/d' "$HS_COVERS"
}

hs_override_paths() { # <harness>; prints PATH lines for its override paths
    hs_cover_lines | awk -v h="$1" '$1 == h { for (i = 2; i <= NF; i++) print "PATH " $i }'
}

hs_coverage() { # <harness>; prints ALWAYS <reason>, or its PATH and DIR lines
    local h="$1" files f
    [ -f "$h" ] && [ -r "$h" ] || return 1
    files="$(hs_closure "$h")" || return 1
    case "$files" in *ALWAYS\ *) printf '%s\n' "ALWAYS ${files##*ALWAYS }"; return 0 ;; esac
    {
        while IFS= read -r f; do
            [ -n "$f" ] || continue
            hs_file_key "$f"; cat "$HS_KEY.tok"
        done <<<"$files"
        hs_legs_args "$h"
        hs_override_paths "$h"
    } | awk -v self="PATH $h" '$0 != self' | sort -u
}

hs_universe() { # prints every executable harness path
    local t
    for t in bin/test-* ibl5/bin/test-*; do
        [ -f "$t" ] && [ -x "$t" ] && printf '%s\n' "$t"
    done
    return 0
}

hs_path_matches() { # <path> <table-lines on stdin: KEY KIND VALUE>; prints matching keys
    HS_P="$1" awk -F'\t' '
        BEGIN { p = ENVIRON["HS_P"] }
        ($2 == "PATH" && (p == $3 || index(p, $3 "/") == 1)) || ($2 == "DIR" && index(p, $3 "/") == 1) {
            if (!($1 in seen)) { seen[$1] = 1; print $1 } }'
}

hs_named_by_helper() { # <path>; true when bin/lib/harness.sh names it
    local hits
    [ -f bin/lib/harness.sh ] || return 1
    hs_cache_open || return 1
    hs_scan_file bin/lib/harness.sh || return 0
    hs_file_key bin/lib/harness.sh
    hits="$(awk '{ print "helper\t" $1 "\t" $2 }' "$HS_KEY.tok" | hs_path_matches "$1")"
    [ -n "$hits" ]
}

hs_force_all() { # <path>; prints a reason and returns 0 when the path forces every harness
    case "$1" in
        bin/lib/*|.github/*|bin/run-shell-harnesses) printf 'shared path %s' "$1"; return 0 ;;
        */*) ;;
        *) printf 'top-level file %s' "$1"; return 0 ;;
    esac
    hs_named_by_helper "$1" && { printf '%s is named by bin/lib/harness.sh' "$1"; return 0; }
    return 1
}

hs_inert() { # <path>; true when an inert line of the override file covers it
    local hits
    hits="$(hs_cover_lines | awk '$1 == "inert" { for (i = 2; i <= NF; i++) print "inert\tPATH\t" $i }' | hs_path_matches "$1")"
    [ -n "$hits" ]
}

# --- selection state ----------------------------------------------------------
HS_ALL_REASON=""
HS_PICKED=""
HS_PICK_LINES=""
HS_TABLE=""
HS_SCANFAIL=""
HS_KEY=""

hs_reset() { HS_ALL_REASON=""; HS_PICKED=""; HS_PICK_LINES=""; }
hs_all() { [ -n "$HS_ALL_REASON" ] || HS_ALL_REASON="$1"; }
hs_pick() { # <harness> <reason>
    case " $HS_PICKED " in
        *" $1 "*) ;;
        *) HS_PICKED="$HS_PICKED $1"; HS_PICK_LINES="${HS_PICK_LINES}$1"$'\t'"$2"$'\n' ;;
    esac
}

hs_claim_from_table() { # <path>; picks every harness whose coverage holds it
    local h any=1
    while IFS= read -r h; do
        [ -n "$h" ] || continue
        hs_pick "$h" "covers $1"; any=0
    done < <(awk -F'\t' '$2 != "ALWAYS"' "$HS_TABLE" | hs_path_matches "$1")
    return "$any"
}

hs_build_table() { # writes <harness> TAB <kind> TAB <value> rows for every harness to HS_TABLE
    local h line
    HS_SCANFAIL=""
    while IFS= read -r h; do
        [ -n "$h" ] || continue
        line="$(hs_coverage "$h")" || { HS_SCANFAIL="$h"; return 1; }
        [ -n "$line" ] || continue
        awk -v h="$h" '{ k = $1; v = $0; sub(/^[^ ]+ /, "", v); print h "\t" k "\t" v }' <<<"$line" >>"$HS_TABLE" || return 1
    done < <(hs_universe)
}

harness_select() { # <changed-file> <event> <out-file>; sets the selection state
    local changed="$1" event="$2" p h claimed reason
    HS_TABLE="$HS_CACHE/table.tsv"
    : >"$HS_TABLE" || { hs_all "cannot write the coverage table"; return 0; }
    hs_build_table || { hs_all "cannot scan ${HS_SCANFAIL:-the harnesses}"; return 0; }
    [ "$event" = pull_request ] || { hs_all "event $event is not pull_request"; return 0; }
    grep -q . "$changed" 2>/dev/null || { hs_all "empty or unreadable changed-file list"; return 0; }
    while IFS= read -r p || [ -n "$p" ]; do
        [ -n "$p" ] || continue
        if reason="$(hs_force_all "$p")"; then hs_all "$reason"; return 0; fi
        claimed=0
        case "$p" in
            bin/test-*|ibl5/bin/test-*) hs_claim_from_table "$p" && claimed=1
               [ -f "$p" ] && { hs_pick "$p" "own script changed"; claimed=1; } ;;
            *) hs_claim_from_table "$p" && claimed=1
               # The name map picks the file's own harness but never claims the path: a
               # caller that reaches the file through a variable dir is invisible to the scan.
               h=$(shell_harness_for "$p"); case "$p" in bin/*) [ -x "$h" ] && hs_pick "$h" "name map for $p" ;; esac ;;
        esac
        [ "$claimed" -eq 1 ] || hs_inert "$p" || { hs_all "unclaimed path $p"; return 0; }
    done <"$changed"
    while IFS="$(printf '\t')" read -r h _ reason; do
        [ -n "$h" ] && hs_pick "$h" "always-run: $reason"
    done < <(awk -F'\t' '$2 == "ALWAYS"' "$HS_TABLE")
    return 0
}

harness_select_merge() { # <event> <attempt> <out-file>; selects from the checkout's merge commit
    local event="$1" attempt="$2" out="$3" changed
    changed="$HS_CACHE/changed.txt"
    [ "$attempt" = 1 ] || { hs_all "run attempt $attempt re-runs every harness"; return 0; }
    git rev-parse -q --verify 'HEAD^2' >/dev/null 2>&1 || { hs_all "HEAD is not a merge commit"; return 0; }
    git -c core.quotePath=false diff --name-only --no-renames 'HEAD^1' HEAD > "$changed" || { hs_all "cannot diff HEAD^1..HEAD"; return 0; }
    harness_select "$changed" "$event" "$out"
}

hs_report() { # <out-file or empty>; prints the run/skip lines and the SELECTED line
    local out="$1" sel h reason
    if [ -n "$HS_ALL_REASON" ]; then
        echo "run: every harness ($HS_ALL_REASON)"
        sel=ALL
    else
        while IFS= read -r h; do
            [ -n "$h" ] || continue
            reason="$(awk -F'\t' -v h="$h" '$1 == h { print $2; exit }' <<<"$HS_PICK_LINES")"
            if [ -n "$reason" ]; then echo "run: $h ($reason)"; else echo "skip: $h (no changed path in its coverage)"; fi
        done < <(hs_universe)
        sel="$(tr ' ' '\n' <<<"$HS_PICKED" | sed '/^$/d' | sort | tr '\n' ' ')"
        sel="${sel% }"
        [ -n "$sel" ] || sel=NONE
    fi
    echo "SELECTED: $sel"
    [ -z "$out" ] || printf '%s\n' "$sel" >"$out"
}

hs_run_select() { # <mode file|merge> <changed-file|-> <event> <attempt> <out-file>
    local mode="$1" changed="$2" event="$3" attempt="$4" out="$5" rc=0
    hs_reset
    hs_cache_open || { echo "harness-select: cannot create a temp dir" >&2; return 1; }
    if [ "$mode" = merge ]; then
        harness_select_merge "$event" "$attempt" "$out" || rc=$?
    else
        harness_select "$changed" "$event" "$out" || rc=$?
    fi
    [ "$rc" -eq 0 ] && hs_report "$out" || rc=1
    hs_cache_close
    return "$rc"
}

# --- coverage-map honesty guard -------------------------------------------------
hs_check_covers() { # fails on a stale or malformed override line or an unscannable harness
    local fails=0 line h pat seen=" " always=0 narrow=0 inert=0 cov
    local -a parts
    [ -f "$HS_COVERS" ] || { echo "coverage: no coverage override file $HS_COVERS" >&2; return 1; }
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        read -r -a parts <<<"$line"
        h="${parts[0]}"
        [ "${#parts[@]}" -ge 2 ] || { echo "coverage: $HS_COVERS line has no path: $line" >&2; fails=1; continue; }
        if [ "$h" != inert ]; then
            [ -x "$h" ] || { echo "coverage: $HS_COVERS names a missing harness: $h" >&2; fails=1; }
            case "$seen" in *" $h "*) echo "coverage: $HS_COVERS has two lines for $h" >&2; fails=1 ;; esac
            seen="$seen$h "
        fi
        for pat in "${parts[@]:1}"; do
            case "$pat" in *[\*\?\[\$]*) echo "coverage: override paths are plain paths: $pat" >&2; fails=1; continue ;; esac
            [ -e "$pat" ] || { echo "coverage: $HS_COVERS names a missing path: $pat" >&2; fails=1; }
            [ "$h" != inert ] || inert=$((inert + 1))
        done
    done < <(hs_cover_lines)
    hs_cache_open || { echo "coverage: cannot create a temp dir" >&2; return 1; }
    while IFS= read -r h; do
        [ -n "$h" ] || continue
        if ! cov="$(hs_coverage "$h")"; then echo "coverage: cannot scan $h" >&2; fails=1; continue; fi
        case "$cov" in ALWAYS\ *) always=$((always + 1)) ;; *) narrow=$((narrow + 1)) ;; esac
    done < <(hs_universe)
    hs_cache_close
    [ "$fails" -eq 0 ] || return 1
    echo "coverage: map OK ($always always-run, $narrow narrow harnesses, $inert inert paths)"
}

# --- per-leg filter ---------------------------------------------------------------
hs_filter_entries() { # <names>; filters legs entries on stdin to the selected scripts
    local names input k n
    names="$(printf '%s' "$1" | tr -s ' \t\n' '   ')"
    names="${names# }"; names="${names% }"
    input="$(cat)"
    n="$(grep -c . <<<"$input" || true)"
    case "$names" in
        ''|ALL)
            echo "selection: every entry on this leg runs (${names:-empty selection})" >&2
            printf '%s\n' "$input"; return 0 ;;
        NONE)
            echo "selection: 0 of $n entries on leg ${leg:-?} selected" >&2; return 0 ;;
    esac
    input="$(awk -v names=" $names " '
BEGIN { n = split(names, a, " "); for (i = 1; i <= n; i++) sel[a[i]] = 1 }
{ path = ""; for (i = 1; i <= NF; i++) if ($i !~ /^[A-Za-z_][A-Za-z0-9_]*=/) { path = $i; break }
  if (path in sel) print; else print "skip: " $0 " (not selected)" > "/dev/stderr" }' <<<"$input")"
    k="$(grep -c . <<<"$input" || true)"
    echo "selection: $k of $n entries on leg ${leg:-?} selected" >&2
    [ -z "$input" ] || printf '%s\n' "$input"
}

# --- self-test ----------------------------------------------------------------------
# hs_self_test <runner> builds a fixture repo per case under mktemp, runs the real runner
# by absolute path with cwd in the fixture, and prints ok: or FAIL: per case. Fixture
# harnesses never name themselves and use no dynamic-read words, except where a case
# needs one.
HS_ST_RUNNER=""
HS_ST_TMP=""
HS_ST_FAILS=0
HS_ST_OUT=""
HS_ST_RC=0

hs_st_ok() { echo "ok: $1"; }
hs_st_fail() { echo "FAIL: $1 ($2)"; HS_ST_FAILS=$((HS_ST_FAILS + 1)); }

hs_st_write() { # <file> <line> ...; writes the lines, makes bin scripts executable
    local f="$1"
    shift
    mkdir -p "$(dirname "$f")"
    printf '%s\n' "$@" >"$f"
    case "$f" in bin/test-*|bin/helper|*/bin/test-*) chmod +x "$f" ;; esac
}

hs_st_fixture() { # <case>; prints the new fixture dir
    local d="$HS_ST_TMP/$1"
    mkdir -p "$d"
    (
        cd "$d" || exit 1
        hs_st_write bin/test-alpha '#!/usr/bin/env bash' 'cat data/alpha.txt >/dev/null' 'echo alpha ran'
        hs_st_write bin/test-beta '#!/usr/bin/env bash' 'bin/helper "$1"' 'echo beta ran'
        hs_st_write bin/helper '#!/usr/bin/env bash' 'cat data/helper.txt >/dev/null'
        hs_st_write bin/test-gamma '#!/usr/bin/env bash' 'x=a' 'cat ".claude/rules/${x}.md" >/dev/null'
        hs_st_write bin/lib/util.sh 'util() { :; }'
        hs_st_write bin/lib/harness-covers.txt '# fixture overrides' 'inert ibl5/classes'
        hs_st_write .github/workflows/harness-legs.txt '0 bin/test-alpha' '0 bin/test-beta conf/beta.cfg' '0 bin/test-gamma'
        hs_st_write data/alpha.txt alpha
        hs_st_write data/helper.txt helper
        hs_st_write conf/beta.cfg beta
        hs_st_write .claude/rules/a.md rule
        hs_st_write ibl5/classes/Foo.php '<?php'
        hs_st_write other/x.txt other
        hs_st_write README.md readme
        git init -q . && git config user.email st@example.invalid && git config user.name st \
            && git add -A && git commit -qm init
    ) >/dev/null 2>&1 || return 1
    printf '%s' "$d"
}

hs_st_run() { # <dir> <runner args...>; sets HS_ST_OUT (stdout+stderr) and HS_ST_RC
    local d="$1"
    shift
    HS_ST_RC=0
    HS_ST_OUT="$(cd "$d" && "$HS_ST_RUNNER" "$@" 2>&1 </dev/null)" || HS_ST_RC=$?
}

hs_st_select() { # <case> <event> <changed path> ...; runs --select on a fresh fixture
    local name="$1" event="$2" d list
    shift 2
    d="$(hs_st_fixture "$name")" || { HS_ST_OUT="fixture setup failed"; HS_ST_RC=99; return 0; }
    list="$HS_ST_TMP/$name.changed"
    if [ $# -gt 0 ]; then printf '%s\n' "$@" >"$list"; else : >"$list"; fi
    hs_st_run "$d" --select "$list" --event "$event" --out "$HS_ST_TMP/$name.sel"
}

hs_st_has() { # <text>; true when HS_ST_OUT holds it
    grep -qF -- "$1" <<<"$HS_ST_OUT"
}

hs_st_tail() { tail -3 <<<"$HS_ST_OUT" | tr '\n' ' '; }

hs_st_expect() { # <case> <want rc> <text> ...; every text must appear in HS_ST_OUT
    local name="$1" rc="$2" t
    shift 2
    [ "$HS_ST_RC" = "$rc" ] || { hs_st_fail "$name" "rc $HS_ST_RC, want $rc: $(hs_st_tail)"; return 0; }
    for t in "$@"; do
        hs_st_has "$t" || { hs_st_fail "$name" "missing [$t]: $(hs_st_tail)"; return 0; }
    done
    hs_st_ok "$name"
}

hs_st_merge_fixture() { # <case>; a develop branch with a --no-ff merge of a PR branch
    local d
    d="$(hs_st_fixture "$1")" || return 1
    (
        cd "$d" || exit 1
        git checkout -q -b develop && git checkout -q -b pr \
            && echo changed >data/alpha.txt && git commit -qam 'pr: alpha' \
            && git checkout -q develop \
            && echo changed >data/helper.txt && git commit -qam 'develop: helper' \
            && git merge -q --no-ff -m 'merge pr' pr
    ) >/dev/null 2>&1 || return 1
    printf '%s' "$d"
}

hs_st_block() { # <file> <job>; prints the lines of one top-level job in a workflow
    awk -v job="  $2:" '
        $0 == job { on = 1; print; next }
        on && /^  [A-Za-z0-9_-]+:[[:space:]]*$/ { on = 0 }
        on { print }' "$1"
}

hs_st_needs() { # <job block> <job name>; true when the block's needs line holds harness-select
    local needs
    needs="$(grep -E '^    needs:' <<<"$1" || true)"
    grep -qF 'harness-select' <<<"$needs"
}

hs_st_wf_wiring() { # <tests.yml>; checks the wiring of the harness-select job
    local wf="$1" gate ht iso onb pr bad="" n
    [ -f "$wf" ] || { hs_st_fail st_wf_wiring "no workflow at $wf"; return 0; }
    gate="$(hs_st_block "$wf" gate)"
    ht="$(hs_st_block "$wf" harness-tests)"
    iso="$(hs_st_block "$wf" harness-tests-isolated)"
    onb="$(awk '/^on:/ { on = 1; next } on && /^[A-Za-z]/ { on = 0 } on { print }' "$wf")"
    pr="$(awk '/^  pull_request:/ { on = 1; next } on && /^  [A-Za-z]/ { on = 0 } on { print }' <<<"$onb")"
    hs_st_needs "$gate" || bad="$bad gate-needs"
    hs_st_needs "$ht" || bad="$bad harness-tests-needs"
    hs_st_needs "$iso" || bad="$bad isolated-needs"
    ! grep -qF 'paths-ignore:' <<<"$onb" || bad="$bad on-paths-ignore"
    ! grep -qE '^[[:space:]]+paths:' <<<"$pr" || bad="$bad pull_request-paths"
    grep -qF -- '--only "$HARNESS_SELECTED"' <<<"$ht" || bad="$bad parallel-only"
    grep -qxF "        if: contains(needs.*.result, 'failure') || contains(needs.*.result, 'cancelled')" <<<"$gate" || bad="$bad gate-predicate"
    grep -qE '^      - name: bin/test-' <<<"$iso" || bad="$bad no-isolated-steps"
    n="$(awk -v q="'" '
        /^      - name: bin\/test-/ { if (want != "") miss = miss " " want; split($0, f, " "); want = f[3]; next }
        /^      - name:/ { if (want != "") miss = miss " " want; want = ""; next }
        want != "" && /^        if:/ { if (index($0, q " " want " " q) > 0) want = "" }
        END { if (want != "") miss = miss " " want; print miss }' <<<"$iso")"
    [ -z "$n" ] || bad="$bad isolated-if:$n"
    if [ -z "$bad" ]; then hs_st_ok st_wf_wiring; else hs_st_fail st_wf_wiring "wrong:$bad"; fi
}

hs_st_merge_cases() {
    local d
    if d="$(hs_st_fixture st_not_merge_all)" \
        && (cd "$d" && echo changed >data/alpha.txt && git commit -qam second) >/dev/null 2>&1; then
        hs_st_run "$d" --select-from-merge --event pull_request --attempt 1
        hs_st_expect st_not_merge_all 0 'SELECTED: ALL' 'HEAD is not a merge commit'
    else hs_st_fail st_not_merge_all "fixture setup failed"; fi
    if d="$(hs_st_merge_fixture st_attempt_all)"; then
        hs_st_run "$d" --select-from-merge --event pull_request --attempt 2
        hs_st_expect st_attempt_all 0 'SELECTED: ALL' 'run attempt 2 re-runs every harness'
    else hs_st_fail st_attempt_all "fixture setup failed"; fi
    if d="$(hs_st_merge_fixture st_stacked_base)"; then
        hs_st_run "$d" --select-from-merge --event pull_request --attempt 1 --out "$HS_ST_TMP/stacked.sel"
        if [ "$(cat "$HS_ST_TMP/stacked.sel" 2>/dev/null)" = bin/test-alpha ]; then
            hs_st_expect st_stacked_base 0 'SELECTED: bin/test-alpha' 'skip: bin/test-beta'
        else hs_st_fail st_stacked_base "out file holds [$(cat "$HS_ST_TMP/stacked.sel" 2>/dev/null)]: $(hs_st_tail)"; fi
    else hs_st_fail st_stacked_base "fixture setup failed"; fi
}

hs_st_flag_cases() {
    local d
    if d="$(hs_st_fixture st_covers_env)"; then
        printf 'ibl5/classes/Foo.php\n' >"$HS_ST_TMP/env.changed"
        HS_ST_RC=0
        HS_ST_OUT="$(cd "$d" && HARNESS_COVERS='' "$HS_ST_RUNNER" --select "$HS_ST_TMP/env.changed" --event pull_request 2>&1)" || HS_ST_RC=$?
        if [ "$HS_ST_RC" = 0 ] && hs_st_has 'SELECTED: NONE'; then
            hs_st_run "$d" --select "$HS_ST_TMP/env.changed" --event pull_request
            hs_st_expect st_covers_env 0 'SELECTED: NONE'
        else hs_st_fail st_covers_env "empty HARNESS_COVERS did not use the default file: $(hs_st_tail)"; fi
    else hs_st_fail st_covers_env "fixture setup failed"; fi

    if d="$(hs_st_fixture st_flags_rejected)"; then
        hs_st_run "$d" --event push bin/test-alpha
        if [ "$HS_ST_RC" = 2 ] && hs_st_has 'go with --select or --select-from-merge'; then
            hs_st_run "$d" --select "$d/README.md"
            if [ "$HS_ST_RC" = 2 ] && hs_st_has 'need --event'; then
                hs_st_run "$d" --only bin/test-alpha bin/test-alpha
                hs_st_expect st_flags_rejected 2 'Usage: --only goes with --list and --leg'
            else hs_st_fail st_flags_rejected "--select without --event: rc $HS_ST_RC"; fi
        else hs_st_fail st_flags_rejected "--event without a select mode: rc $HS_ST_RC"; fi
    else hs_st_fail st_flags_rejected "fixture setup failed"; fi
}

hs_st_guard_cases() {
    local d
    if d="$(hs_st_fixture st_cov_missing_path)"; then
        hs_st_run "$d" --check-coverage .github/workflows/harness-legs.txt
        if [ "$HS_ST_RC" = 0 ] && hs_st_has 'coverage: map OK (0 always-run, 3 narrow harnesses, 1 inert paths)'; then
            printf 'bin/test-alpha data/nope.txt\n' >>"$d/bin/lib/harness-covers.txt"
            hs_st_run "$d" --check-coverage .github/workflows/harness-legs.txt
            hs_st_expect st_cov_missing_path 1 'names a missing path: data/nope.txt'
        else hs_st_fail st_cov_missing_path "clean fixture failed the guard: $(hs_st_tail)"; fi
    else hs_st_fail st_cov_missing_path "fixture setup failed"; fi
    if d="$(hs_st_fixture st_cov_missing_harness)"; then
        printf 'bin/test-gone data/alpha.txt\n' >>"$d/bin/lib/harness-covers.txt"
        hs_st_run "$d" --check-coverage .github/workflows/harness-legs.txt
        hs_st_expect st_cov_missing_harness 1 'names a missing harness: bin/test-gone'
    else hs_st_fail st_cov_missing_harness "fixture setup failed"; fi
}

hs_st_only_cases() {
    local d legs
    if ! d="$(hs_st_fixture st_only)"; then
        hs_st_fail st_only_exact_name "fixture setup failed"
        hs_st_fail st_only_none_green "fixture setup failed"
        hs_st_fail st_only_failure_red "fixture setup failed"
        return 0
    fi
    hs_st_write "$d/bin/test-burndown" '#!/usr/bin/env bash' 'echo burndown ran'
    hs_st_write "$d/bin/test-burndown-loop" '#!/usr/bin/env bash' 'echo loop ran'
    hs_st_write "$d/bin/test-fail" '#!/usr/bin/env bash' 'exit 1'
    legs="$HS_ST_TMP/only-legs.txt"
    printf '%s\n' '0 bin/test-burndown' '0 bin/test-burndown-loop' '1 bin/test-fail' '1 bin/test-alpha' >"$legs"
    hs_st_run "$d" --list "$legs" --leg 0 --only "bin/test-burndown"
    if [ "$HS_ST_RC" = 0 ] && hs_st_has '::group::bin/test-burndown (' && ! hs_st_has '::group::bin/test-burndown-loop'; then
        hs_st_run "$d" --list "$legs" --leg 0 --only "bin/test-burndown-loop"
        if [ "$HS_ST_RC" = 0 ] && hs_st_has '::group::bin/test-burndown-loop (' && ! hs_st_has '::group::bin/test-burndown ('; then
            hs_st_ok st_only_exact_name
        else hs_st_fail st_only_exact_name "selecting the loop also ran bin/test-burndown, or failed (rc $HS_ST_RC)"; fi
    else hs_st_fail st_only_exact_name "selecting bin/test-burndown ran the loop, or failed (rc $HS_ST_RC)"; fi
    hs_st_run "$d" --list "$legs" --leg 0 --only NONE
    hs_st_expect st_only_none_green 0 'nothing to run'
    hs_st_run "$d" --list "$legs" --leg 1 --only "bin/test-fail"
    if hs_st_has '::group::bin/test-alpha'; then hs_st_fail st_only_failure_red "an unselected entry ran"
    else hs_st_expect st_only_failure_red 1 'FAILED:' 'bin/test-fail (rc=1)'; fi
}

hs_self_test() { # <runner>; runs every fixture case; returns 1 if any failed
    local d here
    here="$(cd "$(dirname "$1")" && pwd)"
    HS_ST_RUNNER="$here/$(basename "$1")"
    HS_ST_FAILS=0
    HS_ST_TMP="$(mktemp -d "${TMPDIR:-/tmp}/harness-select-st.XXXXXX")" || { echo "FAIL: setup (mktemp)"; return 1; }
    # Each case runs the runner as a child, so a caller's override file must not leak in.
    unset HARNESS_COVERS

    hs_st_select st_php_only_zero pull_request ibl5/classes/Foo.php
    hs_st_expect st_php_only_zero 0 'SELECTED: NONE'
    hs_st_select st_one_reader pull_request data/alpha.txt
    hs_st_expect st_one_reader 0 'SELECTED: bin/test-alpha' 'skip: bin/test-beta'
    hs_st_select st_bin_lib_all pull_request bin/lib/util.sh
    hs_st_expect st_bin_lib_all 0 'SELECTED: ALL' 'shared path bin/lib/util.sh'
    hs_st_select st_github_all pull_request .github/workflows/harness-legs.txt
    hs_st_expect st_github_all 0 'SELECTED: ALL' 'shared path .github/workflows/harness-legs.txt'
    hs_st_select st_toplevel_all pull_request README.md
    hs_st_expect st_toplevel_all 0 'SELECTED: ALL' 'top-level file README.md'
    hs_st_select st_unclaimed_all pull_request other/x.txt
    hs_st_expect st_unclaimed_all 0 'SELECTED: ALL' 'unclaimed path other/x.txt'
    hs_st_select st_push_all push data/alpha.txt
    hs_st_expect st_push_all 0 'SELECTED: ALL' 'event push is not pull_request'
    hs_st_select st_self_harness pull_request bin/test-alpha
    hs_st_expect st_self_harness 0 'SELECTED: bin/test-alpha' 'own script changed'
    hs_st_select st_transitive pull_request data/helper.txt
    hs_st_expect st_transitive 0 'SELECTED: bin/test-beta'
    hs_st_select st_var_prefix pull_request .claude/rules/b.md
    hs_st_expect st_var_prefix 0 'SELECTED: bin/test-gamma'
    hs_st_select st_legs_args pull_request conf/beta.cfg
    hs_st_expect st_legs_args 0 'SELECTED: bin/test-beta'
    hs_st_select st_empty_list_all pull_request
    hs_st_expect st_empty_list_all 0 'SELECTED: ALL' 'empty or unreadable changed-file list'

    if d="$(hs_st_fixture st_dynamic_always)"; then
        hs_st_write "$d/bin/test-delta" '#!/usr/bin/env bash' 'git ls-files >/dev/null'
        printf '0 bin/test-delta\n' >>"$d/.github/workflows/harness-legs.txt"
        printf 'ibl5/classes/Foo.php\n' >"$HS_ST_TMP/dyn.changed"
        hs_st_run "$d" --select "$HS_ST_TMP/dyn.changed" --event pull_request
        hs_st_expect st_dynamic_always 0 'SELECTED: bin/test-delta' 'always-run: dynamic read at bin/test-delta:2'
    else hs_st_fail st_dynamic_always "fixture setup failed"; fi

    hs_st_merge_cases
    hs_st_flag_cases
    hs_st_guard_cases
    hs_st_only_cases
    hs_st_wf_wiring "$here/../.github/workflows/tests.yml"

    rm -rf "$HS_ST_TMP"
    if [ "$HS_ST_FAILS" -eq 0 ]; then echo "self-test: all cases passed"; return 0; fi
    echo "self-test: $HS_ST_FAILS case(s) failed"
    return 1
}
