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
#
# Residual, accepted: a canonical token inside a paren group still exempts even
# when the group is plain prose rather than a marker. Three corpus entries land
# this way — `(edit — status flips…; **change target, not reference**)`,
# `(the auto-merge verification surface)`, `(path template: , per )`. Tightening
# further would need per-token positional rules like `conditional`'s, and these
# three were exempt under the pre-unification rule too, so the change is not a
# regression. Reconsider if a real change target is ever missed this way.
CF_EXEMPT_PATTERN='[(](([^)]*[^0-9A-Za-z])?(reference|read-?only|verif(y|ication)|template|no[- ]edit|no[- ]change|unchanged|context)([^0-9A-Za-z][^)]*)?|conditional([ ]*[-–—:;,][^)]*)?[ ]*)[)]'

# cf_is_exempt <annotation> -> exit 0 exempt, 1 must-appear
cf_is_exempt() {
    printf '%s\n' "$1" | grep -qiE "$CF_EXEMPT_PATTERN"
}

# cf_table_detected <section-text> -> exit 0 when a markdown table row is present
cf_table_detected() {
    printf '%s\n' "$1" | grep -qE '^[[:space:]]*\|'
}

# cf_section_named <planfile> <heading> -> the raw named section body
# Width-aware fence stripping: a fenced block inside the section (or before it)
# is skipped so illustrative examples do not yield phantom entries.
# <heading> is matched as the literal text after `## ` (case-sensitive).
#
# Blockquote fence handling: quote markers (> prefixes) are stripped before the
# backtick run is counted, closing the blindspot where `> ``` ` yielded n=0 and
# never opened a fence.  Blockquote fence state (bq_fence / bq_fence_len) is
# deliberately CONFINED and not shared with in_fence: any non->-prefixed line
# (including the blank line that ends a CommonMark blockquote) resets bq state,
# so an unclosed quoted fence cannot swallow the rest of the plan.  Merging the
# two would fail OPEN — one stray `> ``` ` would set in_fence=1, and the blank
# line ending the blockquote would never close it.  This is class consistency
# with bin/check-plan-staleness (PR #1809), not a fix for a live breakage:
# every consumer is ^-anchored, so a >-prefixed line can never satisfy
# CF_LINE_PATTERN or any other downstream pattern today.
cf_section_named() {
    awk -v want="$2" '
        BEGIN { in_fence = 0; fence_len = 0; bq_fence = 0; bq_fence_len = 0 }
        {
            line = $0
            sub(/^[[:space:]]*/, "", line)
            is_bq = (substr(line, 1, 1) == ">")
            if (!is_bq) { bq_fence = 0; bq_fence_len = 0 }
            if (is_bq && !in_fence) {
                sub(/^([[:space:]]*>)+[[:space:]]*/, "", line)
                n = 0
                while (substr(line, n + 1, 1) == "`") n++
                if (n >= 3) {
                    if (!bq_fence) { bq_fence = 1; bq_fence_len = n; next }
                    else if (n >= bq_fence_len) { bq_fence = 0; bq_fence_len = 0; next }
                }
                if (bq_fence) next
            } else {
                n = 0
                while (substr(line, n + 1, 1) == "`") n++
                if (n >= 3) {
                    if (!in_fence) { in_fence = 1; fence_len = n; next }
                    else if (n >= fence_len) { in_fence = 0; next }
                }
            }
        }
        in_fence { next }
        $0 ~ ("^##[[:space:]]*" want) { f=1; next }
        /^## / { f=0 }
        f' "$1"
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
# An unclosed fence still leaves in_fence set and swallows every later heading,
# yielding an empty section — but this is now DETECTED by cf_fence_unbalanced
# and reported by bin/check-plan gate [F]. It is no longer a silent pass.
cf_section() { cf_section_named "$1" "Critical Files"; }

# cf_fence_unbalanced <planfile> -> exit 0 when a code fence is still open at EOF.
# Width-aware, exactly as cf_section: an opening run records its length and only
# a run at least that long closes it (CommonMark). An unclosed fence makes
# cf_section swallow every later heading, so the Critical Files section silently
# parses to zero entries — bin/check-plan gate [F] reports that rather than
# passing clean. Measured on the 260-plan corpus: exactly 1 plan trips this
# (sonnet-recipe-completeness-lint.md, already shipped), zero false positives.
#
# The blockquote branch is carried for textual parity with cf_section_named (the
# two machines should stay diffable) and cannot change this function's exit code:
# a >-prefixed line yielded n=0 before (> is not stripped by sub(/^[[:space:]]*/))
# and so never touched in_fence.  A quoted fence left unclosed at EOF is still
# reported as balanced (exit non-zero), deliberately — only a document-level
# unclosed fence is the unbalanced condition this gate detects.
cf_fence_unbalanced() {
    awk '
        BEGIN { in_fence = 0; fence_len = 0; bq_fence = 0; bq_fence_len = 0 }
        {
            line = $0
            sub(/^[[:space:]]*/, "", line)
            is_bq = (substr(line, 1, 1) == ">")
            if (!is_bq) { bq_fence = 0; bq_fence_len = 0 }
            if (is_bq && !in_fence) {
                sub(/^([[:space:]]*>)+[[:space:]]*/, "", line)
                n = 0
                while (substr(line, n + 1, 1) == "`") n++
                if (n >= 3) {
                    if (!bq_fence) { bq_fence = 1; bq_fence_len = n }
                    else if (n >= bq_fence_len) { bq_fence = 0; bq_fence_len = 0 }
                }
            } else {
                n = 0
                while (substr(line, n + 1, 1) == "`") n++
                if (n >= 3) {
                    if (!in_fence) { in_fence = 1; fence_len = n }
                    else if (n >= fence_len) { in_fence = 0 }
                }
            }
        }
        END { exit !in_fence }
    ' "$1"
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
