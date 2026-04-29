# Shared database helper functions for Docker MariaDB interactions.
# Source this from scripts that exec commands in DB containers.
#
# Usage: source "$(dirname "$0")/lib/db-helpers.sh"

# Wrapper to suppress MariaDB "password on command line" warning.
# Preserves the real exit code from docker exec.
db_exec() {
    local output rc=0
    output=$(docker exec "$@" 2>&1) || rc=$?
    [ -n "$output" ] && printf '%s\n' "$output" | grep -v '\[Warning\].*password' || true
    return $rc
}

# Stdin variant that preserves exit code. Buffers output in a subshell —
# suitable for small inputs (individual migrations).
db_exec_stdin() {
    local output rc=0
    output=$(docker exec -i "$@" 2>&1) || rc=$?
    [ -n "$output" ] && printf '%s\n' "$output" | grep -v '\[Warning\].*password' || true
    return $rc
}

# Streaming stdin variant — pipes stdin to docker exec, suppresses warnings,
# always returns 0. Use for large file imports where error detection is handled
# via a separate error-log-grep pipeline.
db_exec_stdin_stream() {
    docker exec -i "$@" 2>&1 | grep -v '\[Warning\].*password' || true
}

# Pipeline filter: strips MariaDB password warnings from a stream.
db_strip_warnings() {
    grep -v '\[Warning\].*password' || true
}

# Import SQL into a Docker MariaDB container from stdin.
# Wraps input in FK_CHECKS=0/1, strips DEFINER clauses, uses --force to
# continue past non-fatal errors. ERROR 1906 (generated-column values) is
# treated as a harmless warning; any other ERROR causes a non-zero exit.
#
# Usage: cat dump.sql | db_import_sql <container> [user] [pass] [dbname]
db_import_sql() {
    local container="$1"
    local user="${2:-root}"
    local pass="${3:-root}"
    local dbname="${4:-iblhoops_ibl5}"
    local error_log
    error_log=$(mktemp)

    {
        echo "SET FOREIGN_KEY_CHECKS=0;"
        cat
        echo "SET FOREIGN_KEY_CHECKS=1;"
    } \
        | perl -pe 's/ DEFINER=\S+ / /g' \
        | docker exec -i "$container" mariadb --force -u"$user" -p"$pass" "$dbname" 2>&1 \
        | db_strip_warnings \
        | grep -i 'ERROR' > "$error_log" || true

    local rc=0
    if [ -s "$error_log" ]; then
        if grep -v 'ERROR 1906' "$error_log" | grep -qi 'ERROR'; then
            echo "ERROR: Import had fatal errors:" >&2
            grep -v 'ERROR 1906' "$error_log" | head -20 >&2
            rc=1
        fi
        local warn_count
        warn_count=$(grep -c 'ERROR 1906' "$error_log" || true)
        if [ "$warn_count" -gt 0 ]; then
            echo "  ($warn_count generated-column warnings during import, harmless)" >&2
        fi
    fi
    rm -f "$error_log"
    return $rc
}
