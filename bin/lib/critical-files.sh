#!/usr/bin/env bash
# shellcheck shell=bash
# shellcheck disable=SC2016  # backticks in regex patterns are literal chars, not substitution
#
# bin/lib/critical-files.sh — the single canonical parser for a plan's
# `## Critical Files` section.
#
# Usage: source "$(git rev-parse --show-toplevel)/bin/lib/critical-files.sh"
#
# Three consumers must agree on "is this entry exempt from must-appear?":
#   1. bin/check-plan            — gates [C] (counting) and [F] (format)
#   2. _phase-5-final-verification.md Phase 5.0 conformance block
#   3. tools/postplan-harness/harness/planfile.py
# (1) and (2) source THIS file, so they are one implementation, not two copies.
# (3) cannot source shell; it mirrors the two patterns below and is pinned to
# them byte-for-byte by tests/test_planfile.py::test_lib_pattern_sync.
#
# History: before #927-followup these were three divergent parsers. The two
# unanchored keyword EREs matched their tokens anywhere in the annotation
# PROSE, so `— add the context menu helper` silently exempted a real change
# target (the #923 failure mode). Exemption is now PAREN-SCOPED: parentheses
# are the author's explicit "this is a marker, not a description" signal.

# A Critical Files entry: a list bullet whose first token is a backticked path.
# Leading whitespace is tolerated — bin/check-plan's old `^- ` awk was not, and
# that silent fourth divergence made an indented entry invisible to it alone.
CF_LINE_PATTERN='^[[:space:]]*-[[:space:]]*`'

# Exempt marker: a parenthesized group whose CONTENTS include a canonical token
# as a WHOLE WORD. Canonical set: reference, read-only, read-only reference,
# verify, verification, template, no-edit, no edit, no-change, no change,
# unchanged, context — plus `conditional`, which is restricted further (below).
# Always applied case-insensitively (grep -iE) so `(Reference)` is exempt.
#
# Two scoping rules, both learned from measuring the 259-plan corpus:
#
#   1. WHOLE WORD. Substring matching exempted declared change targets whose
#      parenthetical merely inflected a token — `(filename references the
#      affected class)` on a `— CHANGE:` entry, `(the referenced file is
#      deleted)` on a `**CHANGE TARGET**` entry. Boundaries are spelled
#      `[^0-9A-Za-z]` rather than `[[:alnum:]]`/`\b`: POSIX classes are absent
#      from Python `re` and `\b` is not portable across BSD/GNU grep, so the
#      literal range keeps this string byte-identical to planfile.py's mirror.
#
#   2. `conditional` MUST BE THE MARKER, not a word in the prose. It only
#      counts when it opens the group and is followed by the close paren or a
#      separator: `(conditional)`, `(conditional — only if …)`. The loose form
#      exempted two real change targets — `(conditional \`AbsFloor\` literal
#      lowering …)` and `(… restructure conditional \`$qdb\` fragments …)` —
#      each the #923 failure mode the conformance check exists to catch.
#      `(conditional Phase 4 only)` is therefore MUST_APPEAR; write
#      `(conditional — Phase 4 only)`.
#
# Measured: vs. the unscoped form these two rules flip exactly 4 of 1578 corpus
# entries, all 4 to the correct verdict, with no other entry changing.
CF_EXEMPT_PATTERN='[(](([^)]*[^0-9A-Za-z])?(reference|read-?only|verif(y|ication)|template|no[- ]edit|no[- ]change|unchanged|context)([^0-9A-Za-z][^)]*)?|conditional([ ]*[-–—:;,][^)]*)?[ ]*)[)]'

# cf_is_exempt <annotation> -> exit 0 exempt, 1 must-appear
cf_is_exempt() {
    printf '%s\n' "$1" | grep -qiE "$CF_EXEMPT_PATTERN"
}

# cf_table_detected <section-text> -> exit 0 when a markdown table row is present
cf_table_detected() {
    printf '%s\n' "$1" | grep -qE '^[[:space:]]*\|'
}

# cf_section <planfile> -> the raw `## Critical Files` section body
# Skips fenced code blocks so embedded fixture content with a ## Critical Files
# heading inside a plan's bash block is not parsed as the real section.
#
# Fence tracking is WIDTH-AWARE. bin/check-plan-staleness's simpler
# `/^[[:space:]]*```/ { in_fence = !in_fence }` toggle — which this function
# originally copied — also matches a 4-backtick fence, this repo's convention
# for an outer block wrapping 3-backtick inner ones. Such a block toggles
# parity once instead of twice, so an odd marker count before the heading
# swallows the entire section. Per CommonMark a closing fence must be at least
# as long as the opening one, so we record the opening run length and close
# only on a run >= it. Measured on the 259-plan corpus: this recovers a plan
# whose 11 entries had silently become 0.
#
# Known limitation, inherited and unfixable here: an UNCLOSED fence leaves
# in_fence set and swallows every later heading. That yields an empty section,
# which bin/check-plan gate [F] reports rather than passing silently.
cf_section() {
    awk '
        {
            line = $0
            sub(/^[[:space:]]*/, "", line)
            n = 0
            while (substr(line, n + 1, 1) == "`") n++
            if (n >= 3) {
                if (!in_fence) { in_fence = 1; fence_len = n; next }
                else if (n >= fence_len) { in_fence = 0; next }
            }
        }
        in_fence { next }
        /^##[[:space:]]*Critical Files/{f=1;next}
        /^## /{f=0}
        f' "$1"
}

# cf_parse_section <planfile> -> one `MUST_APPEAR:<path>` or `EXEMPT:<path>`
# line per entry, in source order. Emits nothing when the section is absent.
cf_parse_section() {
    cf_section "$1" | grep -E "$CF_LINE_PATTERN" | while IFS= read -r line; do
        path=$(printf '%s\n' "$line" | grep -oE '`[^`]+`' | head -1 | tr -d '`')
        [ -z "$path" ] && continue
        rest=$(printf '%s\n' "$line" | sed -E 's/`[^`]*`//g')
        if cf_is_exempt "$rest"; then
            printf 'EXEMPT:%s\n' "$path"
        else
            printf 'MUST_APPEAR:%s\n' "$path"
        fi
    done
}
