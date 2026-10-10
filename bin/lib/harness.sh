#!/usr/bin/env bash
# shellcheck shell=bash
#
# Shared helpers for bin/test-* shell harnesses: assertions, counters, the
# RESULT summary, temp dirs, and throwaway git repos. Exercised by
# bin/test-harness-lib.
#
# Source it before any `cd`:
#   # shellcheck source=bin/lib/harness.sh
#   . "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib/harness.sh"
#
# Contract:
#   - Every assertion takes the label as $1 and returns 0, so a `set -e`
#     harness keeps running after a failed check. h_done owns the exit code.
#   - Output lines are `ok: <label>`, `FAIL: <label> — <detail>`, and one
#     `RESULT:` line from h_done.
#   - h_tmp and h_git_repo exit 2 with `HARNESS-SETUP: <msg>` on stderr when
#     setup breaks. A broken fixture is not an assertion result.
#   - h_tmp must not run in a subshell: the tmpdir registration would be lost.
#   - Nothing here reads HOME or global git identity, so harnesses still run
#     under `env -i PATH=/usr/bin:/bin`.
#   - bash 3.2 safe: no associative arrays, no empty-array expansion.

[ -n "${_H_LOADED:-}" ] && return 0
_H_LOADED=1
H_PASS=0
H_FAIL=0
H_TMPDIRS=""
H_ORIGIN=""

_h_setup_die() {
    printf 'HARNESS-SETUP: %s\n' "$1" >&2
    exit 2
}

h_pass() {
    printf 'ok: %s\n' "$1"
    H_PASS=$((H_PASS + 1))
    return 0
}

h_fail() {
    if [ -n "${2:-}" ]; then
        printf 'FAIL: %s — %s\n' "$1" "$2"
    else
        printf 'FAIL: %s\n' "$1"
    fi
    H_FAIL=$((H_FAIL + 1))
    return 0
}

h_eq() {
    if [ "$2" = "$3" ]; then
        h_pass "$1"
    else
        h_fail "$1" "want [$2] got [$3]"
    fi
    return 0
}

h_contains() {
    case "$2" in
        *"$3"*) h_pass "$1" ;;
        *) h_fail "$1" "missing [$3]" ;;
    esac
    return 0
}

h_not_contains() {
    case "$2" in
        *"$3"*) h_fail "$1" "unexpected [$3]" ;;
        *) h_pass "$1" ;;
    esac
    return 0
}

h_exists() {
    if [ -e "$2" ]; then
        h_pass "$1"
    else
        h_fail "$1" "no such path [$2]"
    fi
    return 0
}

h_absent() {
    if [ ! -e "$2" ]; then
        h_pass "$1"
    else
        h_fail "$1" "path exists [$2]"
    fi
    return 0
}

h_near() {
    if awk -v a="$2" -v b="$3" -v t="$4" 'BEGIN{d=a-b; if(d<0)d=-d; exit !(d<=t)}'; then
        h_pass "$1"
    else
        h_fail "$1" "want [$2]±[$4] got [$3]"
    fi
    return 0
}

_h_cleanup() {
    local d
    while IFS= read -r d; do
        [ -n "$d" ] && rm -rf "$d"
    done <<EOF
$H_TMPDIRS
EOF
    H_TMPDIRS=""
    return 0
}

h_cleanup() {
    _h_cleanup
}

h_tmp() {
    local _h_var="${1:-}" _h_dir
    case "$_h_var" in
        '' | [0-9]* | *[!A-Za-z0-9_]*) _h_setup_die "h_tmp: invalid variable name [$_h_var]" ;;
    esac
    _h_dir=$(mktemp -d "${TMPDIR:-/tmp}/h.XXXXXX") || _h_setup_die "h_tmp: mktemp failed"
    printf -v "$_h_var" '%s' "$_h_dir"
    if [ -n "$H_TMPDIRS" ]; then
        H_TMPDIRS="$H_TMPDIRS
$_h_dir"
    else
        H_TMPDIRS="$_h_dir"
    fi
    if [ -z "$(trap -p EXIT)" ]; then
        trap _h_cleanup EXIT
    fi
    return 0
}

h_git_repo() {
    local dir="" bare_origin=0 no_commit=0 arg origin
    for arg in "$@"; do
        case "$arg" in
            --bare-origin) bare_origin=1 ;;
            --no-commit) no_commit=1 ;;
            -*) _h_setup_die "h_git_repo: unknown flag $arg" ;;
            *)
                [ -z "$dir" ] || _h_setup_die "h_git_repo: extra argument $arg"
                dir="$arg"
                ;;
        esac
    done
    [ -n "$dir" ] || _h_setup_die "h_git_repo: missing DIR"
    mkdir -p "$dir" || _h_setup_die "h_git_repo: mkdir $dir"
    git -C "$dir" init -q || _h_setup_die "h_git_repo: init"
    git -C "$dir" symbolic-ref HEAD refs/heads/master || _h_setup_die "h_git_repo: symbolic-ref"
    git -C "$dir" config user.name "Harness Test" || _h_setup_die "h_git_repo: config user.name"
    git -C "$dir" config user.email harness@example.invalid || _h_setup_die "h_git_repo: config user.email"
    git -C "$dir" config commit.gpgsign false || _h_setup_die "h_git_repo: config commit.gpgsign"
    if [ "$no_commit" -eq 0 ]; then
        git -C "$dir" commit -q --allow-empty -m init || _h_setup_die "h_git_repo: commit"
    fi
    if [ "$bare_origin" -eq 1 ]; then
        origin="${dir%/}-origin.git"
        git init -q --bare "$origin" || _h_setup_die "h_git_repo: init bare origin"
        git -C "$origin" symbolic-ref HEAD refs/heads/master || _h_setup_die "h_git_repo: origin symbolic-ref"
        git -C "$dir" remote add origin "$origin" || _h_setup_die "h_git_repo: remote add"
        if [ "$no_commit" -eq 0 ]; then
            git -C "$dir" push -q origin master || _h_setup_die "h_git_repo: push"
        fi
        # shellcheck disable=SC2034  # read by the sourcing harness
        H_ORIGIN="$origin"
    fi
    return 0
}

h_done() {
    local total=$((H_PASS + H_FAIL))
    if [ "$total" -eq 0 ]; then
        printf 'RESULT: FAILED (no assertions ran)\n'
        exit 1
    fi
    if [ "$H_FAIL" -gt 0 ]; then
        printf 'RESULT: FAILED (%s of %s assertions failed)\n' "$H_FAIL" "$total"
        exit 1
    fi
    if [ "${FAILED:-0}" != 0 ]; then
        printf 'RESULT: FAILED (legacy FAILED=%s)\n' "${FAILED:-0}"
        exit 1
    fi
    printf 'RESULT: all passed (%s assertions)\n' "$total"
    exit 0
}
