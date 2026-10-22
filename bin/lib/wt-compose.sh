# shellcheck shell=bash
# Shared docker-compose helpers for worktree Docker stacks
# (bin/wt-up, bin/wt-down, bin/wt-new, bin/e2e-wt).
#
# Usage: source "$(dirname "$0")/lib/wt-compose.sh"  (from bin/)
#        source "$REPO_ROOT/bin/lib/wt-compose.sh"    (from elsewhere)
# Functions read REPO_ROOT when called, never when sourced.

# wt_slug <name> [<pr-number>]: the compose slug for a worktree. A non-empty
# pr-number yields pr-<N>; otherwise every "/" in name becomes "-" (Docker
# project and container names cannot contain "/"). Owns the format only;
# PR lookup stays with the caller.
wt_slug() {
    local name="${1-}" pr="${2-}"
    if [ -n "$pr" ]; then
        printf 'pr-%s\n' "$pr"
        return 0
    fi
    printf '%s\n' "${name//\//-}"
}

# wt_compose_file: the static worktree compose file under canonical main.
wt_compose_file() {
    printf '%s\n' "$REPO_ROOT/docker/worktree-compose.yml"
}

# wt_compose_env_flag: "--env-file <REPO_ROOT>/.env" when that file exists,
# nothing otherwise. Without it compose resolves .env next to the compose
# file and ${DB_USER}/${DB_PASS} come up empty.
wt_compose_env_flag() {
    if [ -f "$REPO_ROOT/.env" ]; then
        printf '%s\n' "--env-file $REPO_ROOT/.env"
    fi
}

# wt_compose_export <slug> <worktree-path>: export the variables the static
# compose file interpolates.
wt_compose_export() {
    export SLUG="$1" WORKTREE_PATH="$2" REPO_ROOT
}

# wt_project_name <slug>: the compose project of the running php container,
# read from its label (it may differ from the ibl5-<slug> convention). Falls
# back to ibl5-<slug> when the container is missing or the label is empty.
wt_project_name() {
    local slug="$1" project
    project=$(docker inspect "ibl5-php-$slug" --format '{{index .Config.Labels "com.docker.compose.project"}}' 2>/dev/null || echo "ibl5-$slug")
    if [ -z "$project" ]; then
        project="ibl5-$slug"
    fi
    printf '%s\n' "$project"
}

# wt_compose <project> <compose args...>: docker compose against the static
# worktree compose file. Returns compose's exit status; callers keep their
# own 2>/dev/null / || true.
wt_compose() {
    local project="$1" env_flag
    shift
    env_flag=$(wt_compose_env_flag)
    # shellcheck disable=SC2086 # env_flag is empty or "--env-file <path>"; word-split on purpose, exactly as the callers expanded it before extraction
    docker compose -f "$(wt_compose_file)" $env_flag -p "$project" "$@"
}
