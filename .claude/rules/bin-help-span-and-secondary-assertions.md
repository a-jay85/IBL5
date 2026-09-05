---
description: When authoring a bin/ script: do not use a bare '#' line inside a '# Usage:' help-comment span (it terminates the sed extractor early); every verification-matrix row that declares a secondary stderr/stdout token must have a matching 'want' assertion in the test harness for that specific token.
last_verified: 2026-09-04
paths:
  - "bin/**"
---

# bin/ Help-Span Terminator Hygiene and Secondary-Assertion Completeness

## Clause 1 — No bare `#` inside a `# Usage:` help span

`bin/plan-now:111` reads its own help block with:

    sed -n '/^# Usage:/,/^#$/p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;

The sed range `^#$` stops at the **first** line that is exactly `#`. Any bare `#` inside the span silently truncates the displayed help — every line after it is dropped.

**Before (breaks `--help` output):**

    # Usage: bin/example [options]
    # Description line.
    #
    # Section heading:
    #
    # Operator note that the user never sees.

**After (bridge sections with `# ---` or any non-empty comment line):**

    # Usage: bin/example [options]
    # Description line.
    #
    # Section heading:
    # ---
    # Operator note that is now visible.

`bin/plan-now:18` was changed from a bare `#` to `# ---` when this class was discovered (PR #1966).

## Why

A bare `#` is visually identical to a section separator in shell comments. It silently ends the help span. The reviewer sees the full help block in source; `--help` shows a truncated version; the test suite does not contradict either. The gap ships undetected.

## Clause 2 — Every declared secondary token must have a `want` assertion

A `bin/test-*` harness row that names a secondary stderr/stdout token in its label must include a `want "label" "$output" "token"` call asserting that token. A row that names the token but omits the `want` call is vacuous — the test suite passes even if the secondary behaviour never fires.

**Before (token named in label, not asserted):**

    # Matrix row: "warning names the proceed decision (token: 'proceeding')"
    [ "$RC" = 0 ] && ok "warning printed" || fail "warning printed"
    want "runner is written" "$OUT" "runner:"
    # 'proceeding' is in the row label — but no want call checks it

**After (secondary token explicitly asserted):**

    [ "$RC" = 0 ] && ok "warning printed, proceeds" || fail "warning printed, proceeds (got $RC)"
    want "runner is written" "$OUT" "runner:"
    want "warning names the proceed decision" "$OUT" "proceeding"

`bin/test-plan-now:776` — `want "non-3 exit: warning names the proceed decision" "$PRE_OUT5" "proceeding"` — is the assertion added when this class was discovered (PR #1966).

## Why

A declared-but-unasserted secondary token gives reviewers false confidence: the matrix documents the behaviour, the code implements it, but the test never verifies it. The suite stays green regardless.
