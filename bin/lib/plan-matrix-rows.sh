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
# strip_shell_comment <cmd> — prints cmd without a trailing shell comment: an
# unquoted, unescaped `#` at the start or after a space or tab, and the rest of
# the line. A `#` inside quotes, after a backslash, or inside a word (a URL
# `#anchor`, `$#`, `${#x}`) stays. An unclosed quote prints cmd unchanged, so a
# row the scanner cannot read keeps its full text. Trailing whitespace is
# trimmed. Only the exact-match checks call it (is_whole_suite_cmd,
# is_parse_only_cmd). PLAN_ROW_LOCAL_RE, the docker check, the remote-host
# guard and row_ci_needs read the full row, so a comment can still pin a row
# local and can never send one to CI.
strip_shell_comment() {
    local s="$1" i=0 n=${#1} q="" ch prev=" "
    while [ "$i" -lt "$n" ]; do
        ch="${s:$i:1}"
        if [ -n "$q" ]; then
            if [ "$ch" = "\\" ] && [ "$q" = '"' ]; then i=$((i + 1))
            elif [ "$ch" = "$q" ]; then q=""; fi
        else
            case "$ch" in
                "'"|'"') q="$ch" ;;
                "\\") i=$((i + 1)); ch="x" ;;
                '#') case "$prev" in ' '|$'\t') break ;; esac ;;
            esac
        fi
        prev="$ch"
        i=$((i + 1))
    done
    if [ -n "$q" ]; then printf '%s' "$s"; return 0; fi
    mtrim "${s:0:$i}"
}

CD_IBL5_RE='^cd[[:space:]]+ibl5[[:space:]]*&&[[:space:]]*(.*)$'
# A bare worktree suite wrapper: bin/e2e-wt <slug> (the full Playwright suite,
# CI job E2E Tests) or bin/db-test-up [<slug>] (PHPUnit against a seeded DB,
# CI jobs test and db-integration in tests.yml). Both workflows run on every
# PR, stacked PRs included (bin/test-plan-checks-block pins the triggers). Any
# extra argument, pipe, chain or cd prefix falls through and the row runs.
PLAN_ROW_SUITE_WRAPPER_RE='^(bin/e2e-wt[[:space:]]+[A-Za-z0-9][A-Za-z0-9._-]*|bin/db-test-up([[:space:]]+[A-Za-z0-9][A-Za-z0-9._-]*)?)$'
is_whole_suite_cmd() {
    local c
    c=$(strip_shell_comment "$1")
    c="${c#"${c%%[![:space:]]*}"}"
    c="${c%"${c##*[![:space:]]}"}"
    [[ "$c" =~ $PLAN_ROW_SUITE_WRAPPER_RE ]] && return 0
    if [[ "$c" =~ $CD_IBL5_RE ]]; then c="${BASH_REMATCH[1]}"; fi
    case "$c" in
        'vendor/bin/phpunit'|'vendor/bin/phpunit --no-progress'|'composer run analyse'|'composer run analyse:tests'|'composer run test'|'composer test') return 0 ;;
    esac
    return 1
}

# Exit 0 when the row, minus a trailing comment, only parses or lints files: one `bash -n`, `sh -n` or
# `shellcheck` call with no `&`, `;`, `|`, `$(`, backtick, `<(` or `>(`. Such a row
# can name bin/db-test-up without running it, so prefire-verify skips the DB
# probe for it. Anything chained still probes (fail toward probing).
is_parse_only_cmd() {
    local c
    c=$(strip_shell_comment "$1")
    c="${c#"${c%%[![:space:]]*}"}"
    case "$c" in
        *'&'*|*';'*|*'|'*|*'$('*|*'`'*|*'<('*|*'>('*) return 1 ;;
        'bash -n '*|'sh -n '*|'shellcheck '*) return 0 ;;
    esac
    return 1
}

# DB-touching rows. prefire-verify probes the DB only for a DB row that stays
# local, and row_ci_needs uses this regex to decide the CI job starts the db stack.
PLAN_ROW_DB_RE='db-query|docker|--group[= ]database|DatabaseIntegration|db-test-up|run-migrations|mariadb'

# A row runs in CI only when it matches no Mac-only marker, uses docker only
# in the PLAN_ROW_COMPOSE_PHP form, AND its first word is on the allowlist.
# Anything else stays local (fail toward local). DB, web and compose rows run
# in CI: the Plan checks job starts the stacks row_ci_needs names for them.
# A /tmp path the row only writes is not a marker (see _tmp_write_scrub).
# PLAN_ROW_IBLBOT_VITEST_RE is the one npx exemption (an IBLbot vitest run).
PLAN_ROW_LOCAL_RE='(^|[^[:alnum:]_/-])gh[[:space:]]|playwright|npx|npm|wget|bin/wt-|bin/automouse|bin/post-plan|bin/plan-now|bin/check-plan|bin/db-query|bin/db-test-up|launchctl|osascript|ssh|scp|rsync|~/|\$HOME|/Users/|/tmp/|claude-plans|PLAN|\.\./'
# The one npx form that may reach CI: `cd ibl5/IBLbot && npx vitest run <file>`,
# optionally inside ( ... ). The Plan checks job sets up Node and runs npm ci in
# ibl5/IBLbot for it. Group 1 is the cd prefix, group 3 the vitest words; the
# first vitest argument must not be a flag, so a bare whole-suite run stays
# local. Every other npx or npm row still matches PLAN_ROW_LOCAL_RE.
PLAN_ROW_IBLBOT_VITEST_RE='^([[:space:]]*\(?[[:space:]]*cd[[:space:]]+(\./)?ibl5/IBLbot[[:space:]]*&&[[:space:]]*)npx([[:space:]]+vitest[[:space:]]+run[[:space:]]+[^-[:space:]].*)$'
# A chained, piped or substituted command, or a URL, keeps such a row local.
PLAN_ROW_IBLBOT_VITEST_BAD_RE='&&|\||;|`|\$\(|://'
# The one docker form that may reach CI. The ci step sets COMPOSE_FILE to the
# absolute path ${{ github.workspace }}/docker-compose.ci.yml, and
# plan-checks-block ci pins any relative value to the repo root, so a
# `cd ibl5 && docker compose exec -T php ...` row still reaches the CI php container.
PLAN_ROW_COMPOSE_PHP='docker compose exec -T php '
# Web rows: CI starts the web stack and ci_host_rewrite maps the host.
PLAN_ROW_WEB_RE='localhost|127\.0\.0\.1|curl'

# A /tmp path a row writes: the operand of curl -o, -<letters>o, --output PATH
# or --output=PATH (first word curl only), or the target of a > or N> redirect
# (never >> or &>). Only a plain literal path counts, so a quoted, $-built or
# globbed path stays local.
PLAN_ROW_TMP_PATH_RE='/tmp/[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*'
PLAN_ROW_TMP_CURL_OUT_RE='(^|[[:space:]])(-[A-Za-z]*o[[:space:]]*|--output([[:space:]]+|=))('"$PLAN_ROW_TMP_PATH_RE"')'
PLAN_ROW_TMP_REDIR_RE='(^|[^>&0-9])[0-9]?>[[:space:]]*('"$PLAN_ROW_TMP_PATH_RE"')'

# _tmp_write_scrub <cmd> — sets _TW_REST to cmd minus each /tmp path the row
# only writes and exits 0, when every such path appears in at most one line of
# PLAN_ROW_ALL_CMDS (the caller's whole row set, so no other row reads it).
# Exits 1 with _TW_REST=cmd when PLAN_ROW_ALL_CMDS is unset or empty, or a
# written path shows up in a second row. A /tmp path left in _TW_REST is a
# read, so PLAN_ROW_LOCAL_RE keeps the row local.
_tmp_write_scrub() {
    local s="$1" m p n
    _TW_REST="$1"
    [ -n "${PLAN_ROW_ALL_CMDS:-}" ] || return 1
    _row_first_word "$1"
    while :; do
        if [ "$_RFW_FIRST" = curl ] && [[ "$s" =~ $PLAN_ROW_TMP_CURL_OUT_RE ]]; then
            p="${BASH_REMATCH[4]}"
        elif [[ "$s" =~ $PLAN_ROW_TMP_REDIR_RE ]]; then
            p="${BASH_REMATCH[2]}"
        else
            break
        fi
        m="${BASH_REMATCH[0]}"
        n=$(grep -cF -- "$p" <<< "$PLAN_ROW_ALL_CMDS" || true)
        [ "${n:-0}" -le 1 ] || return 1
        s="${s/"$m"/ }"
    done
    _TW_REST="$s"
    return 0
}

# Prefix forms _row_first_word strips after the plain `cd <dir> &&` hops.
# Each fails toward local: a form that does not match exactly leaves its own
# first word in place, and no such word is on the row_runs_in_ci allowlist.
#   NAME=value   value empty, or a literal that does not start with / ~ $ or a quote
PLAN_ROW_ENV_ASSIGN_RE='^([A-Za-z_][A-Za-z0-9_]*)=([A-Za-z0-9_.,:@+-][A-Za-z0-9_.,:/@+=-]*)?[[:space:]]+(.*)$'
#   env [-u NAME]... [NAME=value]...   any other env flag stops the strip
PLAN_ROW_ENV_CMD_RE='^env[[:space:]]+(.*)$'
PLAN_ROW_ENV_UNSET_RE='^-u[[:space:]]+[A-Za-z_][A-Za-z0-9_]*[[:space:]]+(.*)$'
#   cd "$(git rev-parse --show-toplevel)[/sub]" &&   no sub segment starts with a dot
PLAN_ROW_TOPCD_RE='^cd[[:space:]]+"\$\(git rev-parse --show-toplevel\)((/[A-Za-z0-9_-][A-Za-z0-9_.-]*)*)"[[:space:]]*&&[[:space:]]*(.*)$'
#   ( ... )   one whole-command subshell that holds no other parenthesis
PLAN_ROW_SUBSHELL_RE='^\(([^()]*)\)[[:space:]]*$'
PLAN_ROW_CD_HOP_RE='^cd[[:space:]]+([A-Za-z0-9_./-]+)[[:space:]]*&&[[:space:]]*(.*)$'
# The Homebrew python3 path some plans hard-code. As the command word it
# routes like python3, and ci_row_rewrite swaps it for the runner's python3.
PLAN_ROW_BREW_PY=/opt/homebrew/bin/python3
# Mac-only absolute paths. A row that reaches the allowlist through a prefix
# form or the Homebrew python3 word stays local when one of these remains.
PLAN_ROW_MAC_ABS_RE='/opt/|/usr/local/|/Applications/|/Library/|/private/|/var/folders/|/Volumes/'
# A go test fuzz run never ends without -fuzztime, so it stays local.
PLAN_ROW_GO_FUZZ_RE='(^|[[:space:]])-{1,2}(test\.)?fuzz([=[:space:]]|$)'

# _row_first_word <cmd> — sets _RFW_FIRST and _RFW_SECOND: the first two words
# after any leading `cd <dir> &&` hops, a leading `!`, and the prefix forms
# above. Also sets _RFW_REST (the command from the first word on), _RFW_NEW
# (1 when a prefix form or the Homebrew python3 word was seen, else 0) and
# _RFW_BREW (1 when the first word is PLAN_ROW_BREW_PY, reported as python3).
# Shared by row_runs_in_ci, row_ci_needs, ci_row_rewrite and _tmp_write_scrub,
# so they never disagree on the first word.
_row_first_word() {
    local c="$1" in_env=0
    _RFW_NEW=0; _RFW_BREW=0
    c="${c#"${c%%[![:space:]]*}"}"
    while [[ "$c" =~ ^cd[[:space:]]+([A-Za-z0-9_./-]+)[[:space:]]*\&\&[[:space:]]*(.*)$ ]]; do
        c="${BASH_REMATCH[2]}"
    done
    c="${c#!}"; c="${c#"${c%%[![:space:]]*}"}"
    while :; do
        if [ "$in_env" = 1 ] && [[ "$c" =~ $PLAN_ROW_ENV_UNSET_RE ]]; then
            c="${BASH_REMATCH[1]}"
        elif [[ "$c" =~ $PLAN_ROW_ENV_ASSIGN_RE ]]; then
            case "${BASH_REMATCH[1]}" in PATH|HOME|DYLD_*|LD_*) break ;; esac
            c="${BASH_REMATCH[3]}"
            [ "$in_env" = 1 ] && in_env=2
        elif [ "$in_env" = 0 ] && [[ "$c" =~ $PLAN_ROW_ENV_CMD_RE ]]; then
            in_env=1; c="${BASH_REMATCH[1]}"
        elif [[ "$c" =~ $PLAN_ROW_TOPCD_RE ]]; then
            in_env=0; c="${BASH_REMATCH[3]}"
        elif [[ "$c" =~ $PLAN_ROW_SUBSHELL_RE ]]; then
            in_env=0; c="${BASH_REMATCH[1]}"
        elif [ "$_RFW_NEW" = 1 ] && [[ "$c" =~ $PLAN_ROW_CD_HOP_RE ]]; then
            c="${BASH_REMATCH[2]}"
        else
            break
        fi
        c="${c#"${c%%[![:space:]]*}"}"
        _RFW_NEW=1
    done
    if [ "${c%%[[:space:]]*}" = "$PLAN_ROW_BREW_PY" ]; then
        _RFW_BREW=1; _RFW_NEW=1
        c="python3${c#"$PLAN_ROW_BREW_PY"}"
    fi
    _RFW_REST="$c"
    _RFW_FIRST="${c%%[[:space:]]*}"
    _RFW_SECOND="${c#"$_RFW_FIRST"}"; _RFW_SECOND="${_RFW_SECOND#"${_RFW_SECOND%%[![:space:]]*}"}"; _RFW_SECOND="${_RFW_SECOND%%[[:space:]]*}"
}

row_runs_in_ci() {
    local c="$1" first second
    local lc="$c"
    _tmp_write_scrub "$c" && lc="$_TW_REST"
    # Capture BASH_REMATCH before the second =~ overwrites it.
    local iblbot=0 ibl_pre ibl_rest
    if [[ "$lc" =~ $PLAN_ROW_IBLBOT_VITEST_RE ]]; then
        ibl_pre="${BASH_REMATCH[1]}"; ibl_rest="${BASH_REMATCH[3]}"
        if ! [[ "$ibl_rest" =~ $PLAN_ROW_IBLBOT_VITEST_BAD_RE ]]; then
            iblbot=1
            lc="$ibl_pre$ibl_rest"
        fi
    fi
    grep -Eq -- "$PLAN_ROW_LOCAL_RE" <<< "$lc" && return 1
    # A curl row aimed at a remote host stays local: ci_host_rewrite leaves
    # such a URL alone, so CI would test production instead of the PR.
    local url host i=0
    _fetch_split "$c"
    if [ "$_FS_N" -gt 0 ]; then
        # The same guard covers php, bun and bunx rows: a remote URL in their
        # arguments would run against production in CI.
        while [ "$i" -lt "$_FS_N" ]; do
            while IFS= read -r url; do
                host="${url#*://}"; host="${host##*@}"; host="${host%%:*}"
                case "$host" in
                    localhost|127.0.0.1|*.localhost) ;;
                    *) return 1 ;;
                esac
            done < <(grep -Eo -- 'https?://[A-Za-z0-9._%:@-]+' <<< "${_FS_SEG[$i]}")
            # A curl call with no scheme:// URL stays local too. Its host may be
            # remote (curl iblhoops.net/...), and ci_host_rewrite needs a scheme,
            # so even curl localhost/x would miss the CI web stack. A php, bun or
            # bunx segment that holds a curl word, as in php x $(curl host/y),
            # gets the same check, so a nested curl cannot skip it.
            if { [ "${_FS_CURL[$i]}" = 1 ] \
                    || grep -Eq -- '(^|[^[:alnum:]_./-])curl([[:space:]]|$)' <<< "${_FS_SEG[$i]}"; } \
                && ! grep -Eq -- 'https?://[A-Za-z0-9]' <<< "${_FS_SEG[$i]}"; then
                return 1
            fi
            i=$((i + 1))
        done
    fi
    local nd="${c//"$PLAN_ROW_COMPOSE_PHP"/}"
    grep -Eq -- '(^|[^[:alnum:]_-])docker([^[:alnum:]_-]|$)' <<< "$nd" && return 1
    is_whole_suite_cmd "$c" && return 1
    _row_first_word "$c"
    first="$_RFW_FIRST"; second="$_RFW_SECOND"
    # A prefix form or the Homebrew python3 word must not hide a whole-suite
    # run or a Mac-only path. The PLAN_ROW_BREW_PY command word is the one
    # /opt/ path allowed, and ci_row_rewrite replaces it in CI.
    if [ "$_RFW_NEW" = 1 ]; then
        is_whole_suite_cmd "$_RFW_REST" && return 1
        grep -Eq -- "$PLAN_ROW_MAC_ABS_RE" <<< "${c/"$PLAN_ROW_BREW_PY"/}" && return 1
    fi
    # An IBLbot vitest row passed every guard above; its first word is npx.
    [ "$iblbot" = 1 ] && return 0
    case "$first" in
        bin/test-*|bin/check-*|bin/lib/*|vendor/bin/phpunit|python3|shellcheck|grep|rg|jq) return 0 ;;
        php|actionlint|test|\[|printf|seq|diff|curl|bun|bunx|bin/run-shell-harnesses|bin/generate-codebase-map) return 0 ;;
        docker) [ "$second" = compose ] && return 0 ;;
        command) [ "$second" = grep ] && return 0 ;;
        git) case "$second" in grep|ls-files|check-ignore) return 0 ;; esac ;;
        bash) case "$second" in -n|bin/test-*|bin/check-*|bin/lib/*|bin/run-shell-harnesses|bin/generate-codebase-map) return 0 ;; esac ;;
        go) [ "$second" = test ] && ! [[ "$c" =~ $PLAN_ROW_GO_FUZZ_RE ]] && return 0 ;;
    esac
    return 1
}

# row_ci_needs <cmd> — prints the space-separated subset of `db web bun
# actionlint go node` that the Plan checks job must start before this row runs.
# Pass it only commands row_runs_in_ci accepted.
#   db          the command matches PLAN_ROW_DB_RE
#   web db      it matches PLAN_ROW_WEB_RE or holds PLAN_ROW_COMPOSE_PHP (the
#               php container depends on mariadb, and the DB step seeds first)
#   bun         bun or bunx is a command word anywhere in the row
#   actionlint  actionlint is a command word anywhere in the row
#   go          go is the first word (row_runs_in_ci sends only go test)
#   node        the row matches PLAN_ROW_IBLBOT_VITEST_RE (setup-node + npm ci)
row_ci_needs() {
    local c="$1" db=0 web=0 bun=0 al=0 gon=0 node_n=0 out=""
    grep -Eq -- "$PLAN_ROW_DB_RE" <<< "$c" && db=1
    if grep -Eq -- "$PLAN_ROW_WEB_RE" <<< "$c" || [[ "$c" == *"$PLAN_ROW_COMPOSE_PHP"* ]]; then
        web=1; db=1
    fi
    _row_first_word "$c"
    case "$_RFW_FIRST" in
        bun|bunx) bun=1 ;;
        actionlint) al=1 ;;
        go) gon=1 ;;
    esac
    [[ "$c" =~ $PLAN_ROW_IBLBOT_VITEST_RE ]] && node_n=1
    # bun, bunx or actionlint as a command word later in the row, such as
    # test "$(bun ...)" = 1, needs the same stack as when it comes first.
    local i=0 pre
    _fetch_split "$c"
    while [ "$i" -lt "$_FS_N" ]; do
        pre="${_FS_PRE[$i]}"; pre="${pre%"${pre##*[![:space:]]}"}"
        case "$pre" in *bun|*bunx) bun=1 ;; esac
        i=$((i + 1))
    done
    grep -Eq -- '(^|\$\(|<\(|>\(|[|;&`!])[[:space:]]*actionlint([[:space:]]|$)' <<< "$c" && al=1
    [ "$db" -eq 1 ] && out="db"
    [ "$web" -eq 1 ] && out="${out:+$out }web"
    [ "$bun" -eq 1 ] && out="${out:+$out }bun"
    [ "$al" -eq 1 ] && out="${out:+$out }actionlint"
    [ "$gon" -eq 1 ] && out="${out:+$out }go"
    [ "$node_n" -eq 1 ] && out="${out:+$out }node"
    printf '%s\n' "$out"
}

# ci_host_rewrite <cmd> — the only place the CI host is rewritten. A worktree
# slug host (http://<slug>.localhost) or a bare localhost / 127.0.0.1 URL maps
# to the CI web stack on localhost:8080. The host ends at any character that
# cannot be part of a host name: /, ?, #, a quote, a space, ) and so on. A
# host with its own :port, or followed by @ (user info), is left as written.
# Anything else is left as written too, so a miss fails red in CI.
ci_host_rewrite() {
    # Bare hosts go first. The slug expression then cannot turn a port-less
    # result back into localhost:8080, so each expression is tested alone.
    sed -E -e 's#(https?)://(localhost|127\.0\.0\.1)([^A-Za-z0-9._:@-]|$)#\1://localhost:8080\3#g' \
           -e 's#(https?)://([A-Za-z0-9-]+\.)+localhost([^A-Za-z0-9._:@-]|$)#\1://localhost:8080\3#g' <<< "$1"
}

# Where the arguments of a fetching command start: curl as any word, or php,
# bun or bunx as a command word (row start, or after $(, <(, >(, a backtick,
# |, ;, & or !). A file name such as ibl5/index.php is not a command word.
# The ! matches _row_first_word, which strips a leading ! before it routes.
PLAN_ROW_FETCH_SEG_RE='(^|[[:space:]|;&`!('"'"'"])curl[[:space:]]|(^|\$\(|<\(|>\(|[|;&`!])[[:space:]]*(php|bun|bunx)([[:space:]]|$)|docker compose exec -T php[[:space:]]+(php|bun|bunx)([[:space:]]|$)'

# _fetch_split <cmd> — splits cmd at each fetching command. For segment i,
# _FS_PRE[i] is the text before its arguments (ending with the command word)
# and _FS_SEG[i] holds the arguments. A curl segment ends at the next | ; & )
# < > or backtick. PHP code and bun scripts hold ; and ), so a php, bun or
# bunx segment ends at an unquoted && || ; or |, at the unquoted ) that
# closes an enclosing substitution or group, or at the backtick that closes
# an enclosing backtick substitution (see _unquoted_end and _bt_open).
# _FS_CURL[i] is 1 for curl.
# _FS_TAIL is the text after the last segment; _FS_N counts the segments.
# _unquoted_end <text> [paren] [bt] — sets _UE_LEN to the length of the leading part
# of text that stops at the first && || ; or | outside single and double quotes.
# With a second argument `paren`, it also stops at an unquoted ) that has no
# matching ( in text. In well-formed shell that ) closes a $(, <(, >( or ( that
# opened before text began, either at the command word ($(php) or before a cd
# hop or pipe ($(cd x && php, $(echo x | php). An expected value after it, as
# in test "$(cd x && php -r 1)" = "<url>", stays byte-exact.
# With a third argument `bt` (the text starts inside a backtick substitution,
# per _bt_open), it also stops at the first backtick not preceded by a
# backslash, inside quotes or not, so test "`cd x && php -r 1`" = "<url>"
# keeps its expected value too.
_unquoted_end() {
    local s="$1" i=0 n=${#1} q="" ch nx depth=0 paren="${2:-}" bt="${3:-}"
    while [ "$i" -lt "$n" ]; do
        ch="${s:$i:1}"; nx="${s:$((i + 1)):1}"
        # Inside a backtick substitution bash ends the substitution at the
        # first backtick not preceded by a backslash, even inside quotes.
        if [ "$bt" = bt ]; then
            if [ "$ch" = "\\" ] && [ "$nx" = '`' ]; then i=$((i + 2)); continue
            elif [ "$ch" = '`' ]; then break; fi
        fi
        if [ -n "$q" ]; then
            if [ "$ch" = "\\" ] && [ "$q" = '"' ]; then i=$((i + 1))
            elif [ "$ch" = "$q" ]; then q=""; fi
        else
            case "$ch" in
                "'"|'"') q="$ch" ;;
                "\\") i=$((i + 1)) ;;
                ';'|'|') break ;;
                '&') [ "$nx" = '&' ] && break ;;
                '(') depth=$((depth + 1)) ;;
                ')')
                    if [ "$depth" -gt 0 ]; then depth=$((depth - 1))
                    elif [ "$paren" = paren ]; then break; fi ;;
            esac
        fi
        i=$((i + 1))
    done
    _UE_LEN=$i
}

# _bt_open <text> — sets _BT_OPEN to 1 when text ends inside a backtick
# command substitution, else 0. It keeps a stack of open scopes: N (top
# level), S (single quote), D (double quote), B (backtick) and P ($( or ( ).
# A backtick inside single quotes at top level is literal. A backtick inside
# double quotes opens a substitution, as in test "`php -r 1`" = "<url>".
# Inside B only a backslash (it escapes the next character) and the closing
# backtick count, because bash ends the substitution at the first backtick
# not preceded by a backslash, even inside quotes.
_bt_open() {
    local s="$1" i=0 n=${#1} st=N top ch nx
    while [ "$i" -lt "$n" ]; do
        ch="${s:$i:1}"; nx="${s:$((i + 1)):1}"; top="${st:$(( ${#st} - 1 ))}"
        case "$top" in
            S) [ "$ch" = "'" ] && st="${st%?}" ;;
            B)
                if [ "$ch" = "\\" ]; then i=$((i + 1))
                elif [ "$ch" = '`' ]; then st="${st%?}"; fi ;;
            D)
                case "$ch" in
                    "\\") i=$((i + 1)) ;;
                    '"') st="${st%?}" ;;
                    '`') st="${st}B" ;;
                    '$') if [ "$nx" = '(' ]; then st="${st}P"; i=$((i + 1)); fi ;;
                esac ;;
            *)
                case "$ch" in
                    "\\") i=$((i + 1)) ;;
                    "'") st="${st}S" ;;
                    '"') st="${st}D" ;;
                    '`') st="${st}B" ;;
                    '(') st="${st}P" ;;
                    ')') if [ "$top" = P ]; then st="${st%?}"; fi ;;
                esac ;;
        esac
        i=$((i + 1))
    done
    case "$st" in *B) _BT_OPEN=1 ;; *) _BT_OPEN=0 ;; esac
}

_fetch_split() {
    local s="$1" m pre rest seg term='[|;&)<>`]*' bt
    _FS_PRE=(); _FS_SEG=(); _FS_CURL=(); _FS_TAIL=""; _FS_N=0
    while [[ "$s" =~ $PLAN_ROW_FETCH_SEG_RE ]]; do
        m="${BASH_REMATCH[0]}"
        pre="${s%%"$m"*}"
        rest="${s:$(( ${#pre} + ${#m} ))}"
        case "$m" in
            *curl*) seg="${rest%%$term}"; _FS_CURL[_FS_N]=1 ;;
            *)
                # The consumed text is everything of $1 before rest. Ask whether
                # it ends inside a backtick substitution (backlog issue 1944).
                _bt_open "${1:0:$(( ${#1} - ${#rest} ))}"
                bt=""; if [ "$_BT_OPEN" = 1 ]; then bt=bt; fi
                _unquoted_end "$rest" paren "$bt"
                seg="${rest:0:$_UE_LEN}"; _FS_CURL[_FS_N]=0 ;;
        esac
        _FS_PRE[_FS_N]="$pre$m"; _FS_SEG[_FS_N]="$seg"
        _FS_N=$((_FS_N + 1))
        s="${rest:${#seg}}"
    done
    _FS_TAIL="$s"
}

# ci_row_rewrite <cmd> — what plan-checks-block ci runs on each row command and
# mutation check field. It runs ci_host_rewrite only on the arguments of curl,
# php, bun and bunx (see _fetch_split). All other text, such as a grep pattern
# or an expected value in test "$(curl …)" = "<url>", stays byte-exact.
ci_row_rewrite() {
    local out="" i=0 in_php_re='docker compose exec -T php[[:space:]]+(php|bun|bunx|curl)[[:space:]]*$'
    local in_sh_sq_re="docker compose exec -T php[[:space:]]+(sh|bash)[[:space:]]+-l?c[[:space:]]+'[^']*\$"
    local in_sh_dq_re='docker compose exec -T php[[:space:]]+(sh|bash)[[:space:]]+-l?c[[:space:]]+"[^"]*$'
    _fetch_split "$1"
    while [ "$i" -lt "$_FS_N" ]; do
        # A command run inside the php container reaches Apache on its own
        # port 80. The 8080 mapping exists only on the CI host, so its URLs
        # stay as written.
        # A shell wrapper, as in exec -T php sh -c 'curl ...', also runs in the
        # container while its quote is still open. Test all text so far.
        if [[ "${_FS_PRE[$i]}" =~ $in_php_re ]] \
            || [[ "$out${_FS_PRE[$i]}" =~ $in_sh_sq_re ]] \
            || [[ "$out${_FS_PRE[$i]}" =~ $in_sh_dq_re ]]; then
            out="$out${_FS_PRE[$i]}${_FS_SEG[$i]}"
        else
            out="$out${_FS_PRE[$i]}$(ci_host_rewrite "${_FS_SEG[$i]}")"
        fi
        i=$((i + 1))
    done
    out="$out$_FS_TAIL"
    # A row whose command word is the Homebrew python3 path runs the runner's
    # python3 in CI. Only that first occurrence changes; the same path as a
    # later argument (a grep pattern, say) stays as written. The local
    # mutation stage never calls this, so the Mac keeps the real path.
    _row_first_word "$out"
    [ "$_RFW_BREW" = 1 ] && out="${out/"$PLAN_ROW_BREW_PY"/python3}"
    printf '%s\n' "$out"
}
