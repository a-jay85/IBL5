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

# Resolve which database a checkout should talk to, from the checkout root alone.
# Prints `main` for the main checkout (or anything not recognisably a linked
# worktree), or the per-worktree container name `ibl5-db-<slug>`. Always exits 0:
# an unrecognised layout resolves to `main`, i.e. today's behaviour, so a layout
# surprise can never invent a bogus container name.
#
# Slug precedence is <root>/.wt-slug first, basename second. They are NOT always
# equal: bin/wt-up:81 computes SLUG="${WORKTREE_NAME//\//-}" (a branch like
# feature/x lands at .../feature/x, basename `x`, slug `feature-x`) and
# bin/wt-up:92 overrides SLUG="pr-<N>" under --pr. The dotfile is what wt-up
# actually named the container, so it wins whenever it exists and is non-empty.
#
# The git-helpers.sh source below is deliberately lazy and guarded by declare -F:
# this library is sourced by ~a dozen scripts, and a top-of-file source would
# inject six new function names into every one of them plus a hard load-order
# dependency. Callers that already sourced git-helpers.sh pay nothing.
#
# Usage: target=$(db_resolve_target "/path/to/checkout/root")
db_resolve_target() {
    local checkout_root="$1" lib_dir slug
    lib_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if ! declare -F is_main_checkout >/dev/null 2>&1; then
        # shellcheck source=bin/lib/git-helpers.sh
        . "$lib_dir/git-helpers.sh"
    fi

    if is_main_checkout "$checkout_root"; then
        printf 'main\n'
        return 0
    fi

    # Linked worktree: prefer .wt-slug, fall back to directory basename.
    if [ -f "$checkout_root/.wt-slug" ]; then
        slug="$(tr -d '[:space:]' < "$checkout_root/.wt-slug")"
    fi
    [ -z "${slug:-}" ] && slug="$(basename "$checkout_root")"
    printf 'ibl5-db-%s\n' "$slug"
}

# True only when the named container exists AND is currently running. Absent and
# stopped are both false — `docker inspect` alone exits 0 for a stopped container,
# so reading .State.Running is what collapses both into one loud-fail branch: a
# stopped worktree DB must fail loudly, not silently resolve to some other database.
#
# Usage: db_container_running <container-name>
db_container_running() {
    local state
    state="$(docker inspect --format '{{.State.Running}}' "$1" 2>/dev/null)" || return 1
    [ "$state" = "true" ]
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
