---
description: Decision records in ibl5/docs/decisions/ are append-only — frozen sections (Context, Decision, Rationale, Alternatives) must never be rewritten in place; corrections go in a dated Addendum section.
last_verified: 2026-09-16
paths: "ibl5/docs/decisions/**"
---

# Decision Records Are Append-Only

`ibl5/docs/decisions/` records what was true and what was **decided at the time each ADR was
written**. "Current reality" is the standard for a README; it is the wrong standard for an ADR.
Rewriting an ADR's `## Context`, `## Decision`, `Rationale`, or `## Alternatives Considered`
in place to match today destroys the record — the ADR then asserts a decision nobody took.

When reality moves away from a figure in one of those sections, leave the original sentence
unchanged and append a dated `## Addendum — <topic> (<date>)` section giving the original
figure, today's figure, and what changed. Repairing a dead file path is still fine, and
`last_verified` is still bumped.

The one permitted in-place edit to a frozen sentence is appending a `[CORRECTED …]` /
`[SUPERSEDED …]` stamp, and only when `bin/check-docs` fails on that line under the
Retired-Figure Rule — the sentence's own words still never change.

Two adjacent failure modes, both observed in the 2026-09-02 nightly refresh (issue #2047,
PR #2059) and all three of its content edits:

- **A swapped figure orphans its sentence.** ADR-0026's rationale read "approximately one-third
  the size of the largest hotspot (RecordHoldersRepository at 995 LOC)". The refresh substituted
  the current hotspot and its size but left "one-third" bound to the old value, turning a stale
  sentence into a false one. Before replacing any value, re-read the whole sentence with the new
  value in place; if it cannot survive the substitution, rewrite it as dated history.
- **A swapped figure measures the wrong quantity.** ADR-0077's decision recorded "147 call sites
  across 16 files", verified correct against `ibl5/phpstan-baseline.neon` at the ADR's own
  creation commit. The refresh replaced 147 with the *file* count read as a site count, and left
  "16 files" stale — both halves wrong where neither had been. Confirm a replacement measures the
  same quantity as what it replaces.

ADR-0034 showed the plain form: `gitleaks-action@v2` in `## Decision` was overwritten with `@v3`
because the workflow had been upgraded. The upgrade is real; the decision it records is still v2.

Enforcement is by construction rather than by a runtime gate: the constraint is written into the
headless prompt in `bin/docfix-run`, which also requires every non-date content change to be disclosed verbatim
(before/after sentence plus the evidence checked) under a `## Content rewritten` heading in the
commit body and PR body — the same forced-disclosure shape already used for deleted `paths:`
globs. `bin/docfix-check-veronly` remains binary and unchanged: a date-only diff arms auto-merge,
any content change HOLDs for a human. That gate worked in the case above; what failed was the
quality of the edits it held, which is why the fix is upstream in the prompt.

Because a prose clause in a prompt is discipline and not enforcement, `bin/test-docfix-run`
case 48 statically asserts both clauses are still present in `$PROMPT`, so deleting one fails
CI instead of silently reverting the behaviour.
