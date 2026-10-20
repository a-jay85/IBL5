#!/usr/bin/env bash
# review-depth.sh: /post-plan Phase 4 review depth from a PR body's **Merge danger:** line.
# Shell twin of classify_depth in tools/postplan-harness/harness/review_depth.py. The parity
# test tools/postplan-harness/tests/test_review_depth_parity.py runs both over one case table.
#
# Usage: review-depth.sh < body          the PR body on stdin
#        review-depth.sh --unreadable    the caller could not read the body
# Prints one line, "<depth> <reason>", depth standard|deep. Deepen only: anything the parser
# cannot vouch for is deep. Exit 0 with a verdict; exit 2 on a usage error with no stdout.
# Phase 4 runs master's copy (git show <MASTER_SHA>:bin/lib/review-depth.sh), so a PR cannot
# lower its own review depth by editing this file.
set -uo pipefail
export LC_ALL=C

usage() { echo "usage: review-depth.sh [--unreadable] < body" >&2; exit 2; }
[ "$#" -le 1 ] || usage
case "${1-}" in
    "") ;;
    --unreadable) echo "deep UNREADABLE"; exit 0 ;;
    *) usage ;;
esac

verdict() { echo "$1 $2"; exit 0; }

# CR to LF matches Python str.splitlines on CRLF and lone-CR bodies.
lines=$(tr '\r' '\n' | grep -E '^\*\*Merge danger:\*\*' || true)
[ -n "$lines" ] || verdict deep MISSING
n=$(printf '%s\n' "$lines" | wc -l | tr -d ' ')
[ "$n" = 1 ] || verdict deep MALFORMED

line=$(printf '%s' "$lines" | sed -E 's/[[:space:]]+$//')
re='^\*\*Merge danger:\*\* Door: ([a-z-]+) · Blast radius: ([a-z-]+) · Why: (.+)$'
[[ $line =~ $re ]] || verdict deep MALFORMED
door=${BASH_REMATCH[1]}
blast=${BASH_REMATCH[2]}
why=${BASH_REMATCH[3]}
case "$door" in two-way|one-way) ;; *) verdict deep MALFORMED ;; esac
case "$blast" in localized|module|site-wide|external) ;; *) verdict deep MALFORMED ;; esac

# Python counts code points; dropping UTF-8 continuation bytes counts the same.
why=$(printf '%s' "$why" | sed -E 's/^[[:space:]]+//')
chars=$(printf '%s' "$why" | tr -d '\200-\277' | wc -c | tr -d ' ')
[ "$chars" -ge 20 ] || verdict deep MALFORMED

[ "$door" = two-way ] || verdict deep one-way
case "$blast" in
    localized|module) verdict standard "two-way/$blast" ;;
    *) verdict deep "blast=$blast" ;;
esac
