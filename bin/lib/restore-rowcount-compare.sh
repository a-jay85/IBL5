#!/usr/bin/env bash
# shellcheck shell=bash
#
# Pre-dump vs restored COUNT(*) check for the nightly backup.
#
# Subcommands:
#   snapshot <database> <table>...    print one "ROWCOUNT <table> <n|ERROR>" line per table
#   extract <log>                     keep only well-formed ROWCOUNT lines from a captured log
#   compare <expected> <actual> <table>...
#                                     require restored >= pre-dump for every table
#
# Line format: ROWCOUNT <table> <n|ERROR>
#
# .github/workflows/db-backup.yml ships this file to prod over `bash -s`, ahead of
# the remote dump heredoc. When piped or sourced, only the functions are defined;
# main runs only when the file is executed directly.
#
# Harness: bin/test-restore-rowcount-compare

rrc_usage() {
    echo "usage: restore-rowcount-compare.sh snapshot <database> <table>..." >&2
    echo "       restore-rowcount-compare.sh extract <log>" >&2
    echo "       restore-rowcount-compare.sh compare <expected> <actual> <table>..." >&2
}

rrc_client() {
    if [ -n "${ROWCOUNT_MYSQL:-}" ]; then
        printf '%s\n' "$ROWCOUNT_MYSQL"
    elif command -v mysql >/dev/null 2>&1; then
        printf '%s\n' "mysql"
    else
        printf '%s\n' "mariadb"
    fi
}

rrc_snapshot() {
    if [ "$#" -lt 2 ]; then
        rrc_usage
        return 2
    fi
    local database="$1"
    shift
    local client table out
    client="$(rrc_client)"
    for table in "$@"; do
        if ! printf '%s' "$table" | grep -Eq '^[A-Za-z0-9_]+$'; then
            echo "ROWCOUNT $table ERROR"
            continue
        fi
        # </dev/null is load-bearing: under `bash -s` stdin is the rest of the script.
        if out="$("$client" -N -B -e "SELECT COUNT(*) FROM \`$table\`" "$database" </dev/null 2>/dev/null)" \
            && printf '%s' "$out" | grep -Eq '^[0-9]+$'; then
            echo "ROWCOUNT $table $out"
        else
            echo "ROWCOUNT $table ERROR"
        fi
    done
    return 0
}

rrc_extract() {
    if [ "$#" -lt 1 ] || [ ! -r "$1" ] || [ -d "$1" ]; then
        echo "ERROR: cannot read log: ${1:-}" >&2
        return 2
    fi
    awk '{ sub(/\r$/, "") } /^ROWCOUNT [A-Za-z0-9_]+ ([0-9]+|ERROR)$/ { print }' "$1"
}

rrc_compare() {
    if [ "$#" -lt 3 ]; then
        rrc_usage
        return 2
    fi
    local expected="$1" actual="$2"
    shift 2
    if [ ! -r "$expected" ] || [ -d "$expected" ] || [ ! -r "$actual" ] || [ -d "$actual" ]; then
        rrc_usage
        return 2
    fi
    local tables="$*"
    local rc=0
    if awk -v exp_file="$expected" -v act_file="$actual" -v tables="$tables" '
        function load(path, vals, cnt,    line, f, n) {
            while ((getline line < path) > 0) {
                sub(/\r$/, "", line)
                n = split(line, f, " ")
                if (n == 3 && f[1] == "ROWCOUNT") {
                    cnt[f[2]]++
                    vals[f[2]] = f[3]
                }
            }
            close(path)
        }
        BEGIN {
            load(exp_file, ev, ec)
            load(act_file, av, ac)
            n = split(tables, t, " ")
            fails = 0
            for (i = 1; i <= n; i++) {
                k = t[i]
                has_e = (k in ec)
                has_a = (k in ac)
                e = has_e ? ev[k] : "-"
                a = has_a ? av[k] : "-"
                reason = ""
                if ((has_e && ec[k] > 1) || (has_a && ac[k] > 1)) reason = "duplicate count line"
                else if (!has_e) reason = "missing pre-dump count"
                else if (e !~ /^[0-9]+$/) reason = "pre-dump count invalid"
                else if (!has_a) reason = "missing after restore"
                else if (a !~ /^[0-9]+$/) reason = "restored count invalid"
                else if (a + 0 < e + 0) reason = "restored below pre-dump"
                if (reason == "") {
                    printf "rowcount %s: pre-dump=%s restored=%s OK\n", k, e, a
                } else {
                    printf "rowcount %s: pre-dump=%s restored=%s FAIL (%s)\n", k, e, a, reason
                    fails++
                }
            }
            if (fails > 0) {
                printf "ERROR: row-count check failed for %d of %d tables\n", fails, n > "/dev/stderr"
                exit 1
            }
            printf "Row-count check passed (%d tables).\n", n
            exit 0
        }
    ' </dev/null; then
        rc=0
    else
        rc=$?
    fi
    return "$rc"
}

main() {
    local sub="${1:-}"
    if [ "$#" -gt 0 ]; then
        shift
    fi
    case "$sub" in
        snapshot) rrc_snapshot "$@" ;;
        extract) rrc_extract "$@" ;;
        compare) rrc_compare "$@" ;;
        *)
            rrc_usage
            return 2
            ;;
    esac
}

if [ -n "${BASH_SOURCE[0]:-}" ] && [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
    exit $?
fi
