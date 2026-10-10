#!/usr/bin/env bash
# shellcheck shell=bash
#
# usage_test_isolate <dir>: points the usage gate at <dir> for this shell and
# its children. Every bin/test-* that runs a usage-gated entry point (the
# automouse runner, plan-now, post-plan-now, the coordinator, ...) calls it,
# directly or through bin/lib/harness.sh, so no test reads or writes the real
# usage-gate state under ~/.claude. It exports:
#   IBL5_USAGE_GATE_STATE_DIR=<dir>/state
#   IBL5_USAGE_CLAUDE_PROJECTS=<dir>/projects
#   IBL5_USAGE_GATE_TEST_MODE=1  (bin/lib/usage-fetch.sh then skips the keychain
#                                 and the usage API, and refuses the real dir)
# Inherited values are overwritten on purpose: a child test never shares its
# parent's dir. No cleanup here; pass a dir under a tmp dir the caller removes.
# Fails closed: on a bad <dir> it still exports test mode and unsets the state
# dir, so bin/lib/usage-fetch.sh refuses the real path. Reads no HOME, never
# calls set -e, bash 3.2 safe.

[ -n "${_USAGE_TEST_ISOLATION_LOADED:-}" ] && return 0
_USAGE_TEST_ISOLATION_LOADED=1

usage_test_isolate() {
    local root="${1:-}"
    export IBL5_USAGE_GATE_TEST_MODE=1
    if [ -z "$root" ] || ! mkdir -p "$root/state/markers" "$root/projects"; then
        unset IBL5_USAGE_GATE_STATE_DIR IBL5_USAGE_CLAUDE_PROJECTS
        printf 'USAGE-TEST-ISOLATE: cannot isolate in [%s]\n' "$root" >&2
        return 2
    fi
    export IBL5_USAGE_GATE_STATE_DIR="$root/state"
    export IBL5_USAGE_CLAUDE_PROJECTS="$root/projects"
    return 0
}
