#!/usr/bin/env bash
# shellcheck shell=bash
# bin/lib/hold-check.sh — sourced helper for ask-shaped-sentence detection in
# `## Automouse Hold Justification` plan sections.
#
# Consumers:
#   bin/check-plan      gate [H] (hold_check_violations, hold_decision_paragraphs)
#   .claude/skills/post-plan/SKILL.md Phase 6 (hold_decision_paragraphs)
#   tools/postplan-harness/harness/classify.py (Python mirror)
#
# Usage: source "$(dirname "$0")/lib/hold-check.sh"
#        (or source with an absolute path)
#
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.

HOLD_CHECK_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Internal state: pattern arrays loaded once at first call to hold_check_violations.
_HOLD_POS=()
_HOLD_EXC=()
_HOLD_PATTERNS_LOADED=0

# hold_check_patterns_file — absolute path to the shared pattern data file.
hold_check_patterns_file() {
    printf '%s\n' "${HOLD_CHECK_LIB_DIR}/hold-check-patterns.txt"
}

# hold_check_section <plan-file>
# Emits `<lineno>:<text>` for every body line of the plan's
# `## Automouse Hold Justification` section that a human ask could hide in.
# Excluded, in this order:
#   (a) the heading line itself;
#   (b) everything from the next `^## ` heading onward (or EOF);
#   (c) fenced code blocks (``` toggles), so a plan DOCUMENTING an
#       ask-shaped sentence as an example is never flagged;
#   (d) a `**Decision:**` block — from a line matching
#       `^[ ]*\*\*Decision:\*\*` through to the next blank line.
# Prints nothing and returns 0 when the section is absent.
hold_check_section() {
    local plan_file="$1"
    local line ws_trimmed sp_trimmed
    local in_section=0 in_fence=0 in_decision=0 lineno=0
    # Every line test below is a bash `case` glob, never `printf ... | grep -q`:
    # under `set -o pipefail` (bin/check-plan sets it) grep's early exit SIGPIPEs
    # printf and the pipeline reports failure even on a match
    # (.claude/rules/shell-pipefail-grep.md).
    while IFS= read -r line; do
        lineno=$(( lineno + 1 ))
        # Not yet in section — look for the heading
        if [ "$in_section" -eq 0 ]; then
            case "$line" in
                '## Automouse Hold Justification'*) in_section=1 ;;
            esac
            continue  # skip lines before (and including) the heading (a)
        fi
        # (b) Next top-level heading terminates the section
        case "$line" in
            '## '*) break ;;
        esac
        # (c) Fence toggle — line whose first non-space run is ```
        ws_trimmed="${line#"${line%%[![:space:]]*}"}"
        case "$ws_trimmed" in
            '```'*)
                in_fence=$(( 1 - in_fence ))
                continue
                ;;
        esac
        if [ "$in_fence" -eq 1 ]; then
            continue
        fi
        # (d) Decision block — start (anchor is `^[ ]*`, spaces only)
        sp_trimmed="${line#"${line%%[! ]*}"}"
        case "$sp_trimmed" in
            '**Decision:**'*)
                in_decision=1
                continue
                ;;
        esac
        # (d) Decision block — body (skip until blank line)
        if [ "$in_decision" -eq 1 ]; then
            if [ -z "$line" ]; then
                in_decision=0
            fi
            continue
        fi
        printf '%d:%s\n' "$lineno" "$line"
    done < "$plan_file"
    return 0
}

# hold_decision_paragraphs <plan-file>
# Prints the raw lines of every `**Decision:**` block in the plan's
# `## Automouse Hold Justification` section. This is the complement of
# hold_check_section's (d) exclusion, under the same (a)-(c) bounds:
# heading skipped, section ends at the next `## `, fenced lines ignored,
# anchor is leading SPACES only. A block runs from the `**Decision:**` line
# to the next empty line; a second `**Decision:**` line inside an open block
# continues that block. Separate blocks are joined by exactly one empty line.
# No line numbers, no trailing empty line. Prints nothing and returns 0 when
# the section or every Decision line is absent.
# Byte parity with harness classify.decision_paragraphs is pinned by
# tools/postplan-harness/tests/test_decision_render_parity.py.
hold_decision_paragraphs() {
    local plan_file="$1"
    local line ws_trimmed sp_trimmed
    local in_section=0 in_fence=0 in_decision=0 emitted=0
    # `|| [ -n "$line" ]` keeps a final line that lacks a trailing newline;
    # Python's splitlines() keeps it, so parity needs it here too.
    while IFS= read -r line || [ -n "$line" ]; do
        if [ "$in_section" -eq 0 ]; then
            case "$line" in
                '## Automouse Hold Justification'*) in_section=1 ;;
            esac
            continue
        fi
        case "$line" in
            '## '*) break ;;
        esac
        ws_trimmed="${line#"${line%%[![:space:]]*}"}"
        case "$ws_trimmed" in
            '```'*)
                in_fence=$(( 1 - in_fence ))
                continue
                ;;
        esac
        if [ "$in_fence" -eq 1 ]; then
            continue
        fi
        sp_trimmed="${line#"${line%%[! ]*}"}"
        case "$sp_trimmed" in
            '**Decision:**'*)
                if [ "$in_decision" -eq 0 ] && [ "$emitted" -eq 1 ]; then
                    printf '\n'
                fi
                in_decision=1
                emitted=1
                printf '%s\n' "$line"
                continue
                ;;
        esac
        if [ "$in_decision" -eq 1 ]; then
            if [ -z "$line" ]; then
                in_decision=0
            else
                printf '%s\n' "$line"
            fi
        fi
    done < "$plan_file"
    return 0
}

# Internal: load pattern arrays once.
_hold_check_load_patterns() {
    [ "$_HOLD_PATTERNS_LOADED" -eq 1 ] && return 0
    local pat_file pat
    pat_file="$(hold_check_patterns_file)"
    while IFS= read -r pat; do
        case "$pat" in
            ''|'#'*) continue ;;
            '!'*)    _HOLD_EXC+=( "${pat:1}" ) ;;
            *)       _HOLD_POS+=( "$pat" ) ;;
        esac
    done < "$pat_file"
    _HOLD_PATTERNS_LOADED=1
}

# hold_check_violations <plan-file>
# For each line hold_check_section emits, test it against the shared
# patterns. Prints `<lineno>:<text>` for each violating line.
# Returns 0 when there are none, 1 when there is at least one.
hold_check_violations() {
    local plan_file="$1"
    _hold_check_load_patterns
    local found=0 entry lineno text p e matches_pos matches_exc
    while IFS= read -r entry; do
        lineno="${entry%%:*}"
        text="${entry#*:}"
        matches_pos=0
        for p in "${_HOLD_POS[@]}"; do
            if grep -qiE -- "$p" <<< "$text"; then
                matches_pos=1
                break
            fi
        done
        [ "$matches_pos" -eq 0 ] && continue
        matches_exc=0
        for e in "${_HOLD_EXC[@]}"; do
            if grep -qiE -- "$e" <<< "$text"; then
                matches_exc=1
                break
            fi
        done
        [ "$matches_exc" -eq 1 ] && continue
        printf '%s\n' "$entry"
        found=1
    done < <(hold_check_section "$plan_file")
    return "$found"
}
