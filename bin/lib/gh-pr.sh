# shellcheck shell=bash
# gh-pr.sh: one-call PR lookup shared by bin/cleanup, bin/wt-status,
# bin/wt-up, bin/wt-down, bin/e2e-wt and bin/lib/wt-guards.sh (has_open_pr).
#
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.
#
# pr_info <ref> [dir]
#   One `gh pr view [<ref>] --json state,number,headRefOid` call. Sets
#   PR_STATE, PR_NUM, PR_OID in the caller's shell. All three are reset to
#   empty at the start of EVERY call, so a failed lookup never leaves the
#   previous ref's values behind.
#   <ref>  branch name or PR number. Empty: the argument is omitted and gh
#          infers the PR from the branch checked out in [dir] (or $PWD).
#   [dir]  run gh inside `cd "$dir"` in a subshell; variables still land in
#          the caller's shell.
#   Returns 0 only when a PR resolved (PR_STATE non-empty). Returns 1 when gh
#   is missing, gh fails (stderr discarded), or no PR exists. Callers under
#   `set -e` write `pr_info ... || true`.
#
# Test seam: GH_CMD (default `gh`) is a single-token command (a path to a shim
# in tests), the same convention as bin/lib/pr-armable.sh.

# PR_NUM and PR_OID are read by the sourcing script, not here.
# shellcheck disable=SC2034
pr_info() {
    local ref="${1:-}" dir="${2:-}" gh_cmd="${GH_CMD:-gh}" out=""
    local -a argv
    PR_STATE=""
    PR_NUM=""
    PR_OID=""
    command -v "$gh_cmd" >/dev/null 2>&1 || return 1
    argv=(pr view)
    if [ -n "$ref" ]; then
        argv+=("$ref")
    fi
    argv+=(--json "state,number,headRefOid" \
        --jq '[.state, .number, .headRefOid] | @tsv')
    if [ -n "$dir" ]; then
        out="$(cd "$dir" && "$gh_cmd" "${argv[@]}" 2>/dev/null)" || out=""
    else
        out="$("$gh_cmd" "${argv[@]}" 2>/dev/null)" || out=""
    fi
    [ -n "$out" ] || return 1
    IFS=$'\t' read -r PR_STATE PR_NUM PR_OID <<< "$out"
    [ -n "$PR_STATE" ]
}
