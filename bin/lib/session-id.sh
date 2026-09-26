#!/usr/bin/env bash
# Mint, validate and persist the claude -p --session-id uuid for detached headless
# runs; writes the ${LOG%.log}.session sidecar that bin/fleet-status reads to locate
# a live run's transcript.
#
# Bash 3.2 / macOS compatible: no declare -A, no mapfile, no ${var,,}, no touch -d.
# set -u safe: every expansion uses ${1:-} / ${2:-} defaults.
# No set -e assumptions: each function returns a status the caller tests.
# Sourced by bin/plan-now, bin/docfix-run, bin/post-plan-now, and bin/fleet-status.

# Regex stored unquoted so [[ =~ ]] treats it as an ERE, not a literal string
# (Bash 3.2 compatibility: quoting the RHS of =~ makes it a literal match).
_SESSION_ID_RE='^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'

# is_session_id <s>
# Returns 0 iff $s is a canonical lowercase uuid.
is_session_id() {
    local s="${1:-}"
    [[ "$s" =~ $_SESSION_ID_RE ]]
}

# mint_session_id
# Prints one fresh lowercase uuid on stdout, returns 0.
# Returns 1 (printing nothing) if every source fails or the result fails validation.
mint_session_id() {
    local raw=''
    if command -v uuidgen >/dev/null 2>&1; then
        raw=$(uuidgen | tr 'A-Z' 'a-z')
    elif [ -r /proc/sys/kernel/random/uuid ]; then
        raw=$(tr 'A-Z' 'a-z' < /proc/sys/kernel/random/uuid)
    elif command -v python3 >/dev/null 2>&1; then
        raw=$(python3 -c 'import uuid;print(uuid.uuid4())' | tr 'A-Z' 'a-z')
    fi
    if [ -z "$raw" ] || ! is_session_id "$raw"; then
        return 1
    fi
    printf '%s\n' "$raw"
}

# session_sidecar_path <log>
# Single derivation point: prints ${log%.log}.session.
# Both producers and consumer call this so the convention lives in one place.
session_sidecar_path() {
    local log="${1:-}"
    printf '%s\n' "${log%.log}.session"
}

# write_session_sidecar <log> <uuid>
# Validates uuid with is_session_id (returns 1 without writing if it fails),
# then writes uuid + newline to a temp file beside the target and mv's it into
# place so a concurrent reader never sees a half-written file.
write_session_sidecar() {
    local log="${1:-}" uuid="${2:-}"
    if ! is_session_id "$uuid"; then
        return 1
    fi
    local target="${log%.log}.session"
    local tmp="${target}.tmp.$$"
    printf '%s\n' "$uuid" > "$tmp" && mv "$tmp" "$target"
}
