#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/branch-base.sh — shared base-ref resolver for stacked-PR-aware ADR gating.
#
# SOURCED ONLY — no top-level side effects; no `set -e` (safe under caller's set -euo pipefail).
# Bash 3.2 / macOS compatible; every env read uses ${VAR:-} so it is safe under set -u.
#
# BRANCH_BASE_OFFLINE=1 removes the `gh` fallback layer. It can only turn a PASS into a
# BLOCK, never the reverse — a reviewer seeing a new env var on a gate should reach the
# conclusion that it is not an escape hatch before reading further.

BRANCH_BASE_GH_TIMEOUT_SECS=5

# _branch_base_valid_name <name>
# Pure-case validation with no subprocess and no Bash =~ (quoting shifted around 3.2).
# Allowlist: alnum start, then alnum/._/- in the rest, no dotdot sequences.
# The leading-character rule prevents hostile values like "--upload-pack=evil" from being
# handed to git as an option rather than a ref.
_branch_base_valid_name() {
    local n="$1"
    [ -n "$n" ] || return 1
    case "$n" in [!A-Za-z0-9]*) return 1 ;; esac
    case "$n" in *[!A-Za-z0-9._/-]*) return 1 ;; esac
    case "$n" in *'..'*) return 1 ;; esac
    return 0
}

# _branch_base_accept <repo_root> <name> <require_ancestor: 0|1>
# Shared acceptance gate: validate the name, verify origin/<name> resolves, and optionally
# check that it is an ancestor of HEAD (D6). Both resolution layers funnel through here so
# neither can accept something the other would reject. On success prints "origin/<name>" and
# returns 0. require_ancestor defaults to 1 when omitted.
_branch_base_accept() {
    local repo="$1" name="$2" require_ancestor="${3:-1}" ref
    _branch_base_valid_name "$name" || return 1
    ref="origin/$name"
    git -C "$repo" rev-parse --verify --quiet "$ref^{commit}" >/dev/null 2>&1 || return 1
    if [ "$require_ancestor" = "1" ]; then
        git -C "$repo" merge-base --is-ancestor "$ref" HEAD 2>/dev/null || return 1
    fi
    printf '%s\n' "$ref"
    return 0
}

# branch_base_recorded <repo_root> <branch>
# Strictly offline — reads branch.<name>.iblBase from git config, validates, verifies the ref
# exists. No ancestry check: used by wt-rebase, which needs the base precisely because the
# branch is behind it. Prints the resolved ref on success, returns 1 on any failure.
branch_base_recorded() {
    local name
    name="$(git -C "$1" config --get "branch.$2.iblBase" 2>/dev/null)" || return 1
    _branch_base_accept "$1" "$name" 0
}

# branch_base_from_config <repo_root> <branch>
# Strictly offline — branch_base_recorded plus the ancestry check (D6). Used by
# branch_base_resolve (hook layer 1). Prints the resolved ref on success, returns 1 on any
# failure (missing key, bad name, stale or non-ancestor base).
branch_base_from_config() {
    local name
    name="$(git -C "$1" config --get "branch.$2.iblBase" 2>/dev/null)" || return 1
    _branch_base_accept "$1" "$name" 1
}

# _branch_base_run_timed <seconds> <cmd...>
# Bash 3.2 compatible timeout: backgrounds the command, polls kill -0, kills on expiry.
# Uses explicit XXXXXX template for mktemp (not -t PREFIX, which is deprecated on GNU coreutils).
# </dev/null is mandatory: a pre-push hook receives git's ref-update list on stdin; the first
# subprocess that forgets to close it would consume that list and misbehave silently.
_branch_base_run_timed() {
    local secs="$1"; shift
    local tmp rc ticks=0 maxTicks pid
    maxTicks=$(( secs * 5 ))
    tmp="$(mktemp "${TMPDIR:-/tmp}/branchbase.XXXXXX")" || return 1
    "$@" >"$tmp" 2>/dev/null </dev/null &
    pid=$!
    while kill -0 "$pid" 2>/dev/null; do
        if [ "$ticks" -ge "$maxTicks" ]; then
            kill -9 "$pid" 2>/dev/null
            wait "$pid" 2>/dev/null
            rm -f "$tmp"
            return 1
        fi
        sleep 0.2
        ticks=$(( ticks + 1 ))
    done
    if wait "$pid" 2>/dev/null; then rc=0; else rc=$?; fi
    if [ "$rc" -eq 0 ]; then cat "$tmp"; fi
    rm -f "$tmp"
    return "$rc"
}

# _branch_base_gh <repo_root> <branch>
# Network fallback: calls `gh pr view` under a hard 5s timeout.
# Skipped if BRANCH_BASE_OFFLINE=1 or gh is not installed.
_branch_base_gh() {
    [ "${BRANCH_BASE_OFFLINE:-}" = "1" ] && return 1
    command -v gh >/dev/null 2>&1 || return 1
    local name
    name="$(_branch_base_run_timed "$BRANCH_BASE_GH_TIMEOUT_SECS" \
              gh pr view "$2" --json baseRefName --jq .baseRefName)" || return 1
    _branch_base_accept "$1" "$name"
}

# branch_base_resolve <repo_root> <branch>
# Layered entry point: config (offline, cheapest) then gh (network, 5s timeout), then fail.
# On failure prints nothing to stdout — the caller owns the user-facing error message.
branch_base_resolve() {
    branch_base_from_config "$1" "$2" && return 0
    _branch_base_gh "$1" "$2" && return 0
    return 1
}
