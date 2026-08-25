# /fix-and-prevent remediation procedure

Shared include. Two callers:

- `.claude/skills/fix-and-prevent/SKILL.md` reads it by path (same directory).
- `.claude/skills/pr-ready/SKILL.md` Phase 6.5 loads it with
  `git show <MASTER_SHA>:.claude/skills/fix-and-prevent/_remediation.md`.

**Callers MUST declare their mode at invocation.** Write one of these two lines
into the run notes before step 1, verbatim:

- `Mode: standalone` — invoked from `/fix-and-prevent`. You own the ship step:
  after step 5, return to `/fix-and-prevent` Step 3, which fires
  `bin/post-plan-now --auto`.
- `Mode: in-PR` — invoked from `/pr-ready` Phase 6.5. You do NOT own the ship
  step: no `bin/post-plan-now`, no `gh pr create`, no auto-merge arming, no
  worktree creation, no worktree teardown, no merge. The caller commits and
  pushes.

If neither line was stated, print `STOP: caller did not declare a mode` and stop.
Do not guess the mode from context.

## Mode differences — the complete list

| | `Mode: standalone` | `Mode: in-PR` |
|---|---|---|
| Worktree | `bin/wt-new <slug>` per SKILL.md Step 0 | the PR's existing worktree; never `bin/wt-new` |
| Which findings get treated | `/fix-and-prevent` § Calibration "Out of scope" applies | **overridden** — every Phase 6 finding, notes and blockers alike, across all six 6d classes |
| Fix edits + backlog edits | yours | yours |
| Commit / push | `bin/post-plan-now --auto` at SKILL.md Step 3 | the caller's Phase 6.5 commit + push steps |
| Ends with | a PR opened by `/post-plan` | control returned to the caller |

Everything below runs identically in both modes.

## Step 1 — Name the defect class, in one sentence

Not "this call was wrong" but the class it belongs to:
*"a `<shape>` in `<surface>` that `<fails how>`."* One sentence, no hedging.

If no class is nameable, do not skip the step — carry that fact to step 5 and
record `class: n/a — <one-line reason>`.

## Step 2 — Scan for other live occurrences of that class

Search the repo for the **class**, not the symptom. Report a table, one row per
occurrence. The `Status` column is mandatory:

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `path/to/a.php:12` | yes | yes | fixed this pass |
| 2 | `path/to/b.php:88` | yes | dead code | not fixed — dead |
| 3 | `path/to/c.php:140` | near-miss | yes | not fixed — filed |

`Status` is one of `fixed this pass`, `not fixed — <reason>`, or
`not fixed — filed`. A table without a `Status` column is incomplete; a scan
that reports "no other occurrences" still emits the table header plus a single
`none found` row, so the reader can tell a scan from a skip.

**Overflow rule.** When the scan finds more occurrences than this unit of work
can carry: fix the reported occurrence, mark the remainder `not fixed — filed`,
and paste this same table into the backlog entry written at step 4. Do not open
a second backlog item for the remainder.

## Step 3 — Select the backlog file

Deterministic. Select by the defect class's surface:

- App code / game logic / data model → `ibl5/docs/backlog/maintenance-backlog.md`
- CI or GitHub Actions → `ibl5/docs/backlog/ci-backlog.md`
- E2E test quality → `ibl5/docs/backlog/e2e-backlog.md`
- Accessibility (non-contrast) → `ibl5/docs/backlog/a11y-backlog.md`
- Accessibility contrast → `ibl5/docs/backlog/a11y-contrast-backlog.md`
- Token spend / Claude context economy → `ibl5/docs/backlog/token-spend-backlog.md`
- Developer tooling (inner loop, scripts, worktree) → `ibl5/docs/backlog/dev-efficiency-backlog.md`
- Autonomous-loop or harness behavior → `ibl5/docs/backlog/loop-engineering-backlog.md`

When the defect class spans surfaces or fits none cleanly, use a standalone item
(a new file at `ibl5/docs/backlog/<slug>.md`) and add a row to
`ibl5/docs/backlog/README.md` § Standalone items.

**Prefer an existing LIVE backlog.** Appending to one costs a `last_verified`
bump on that one file. A new standalone file additionally forces a `README.md`
row and a second `last_verified` bump — avoid it in `Mode: in-PR`, where the
edit budget is shared with the fixes.

**Consolidate.** When one invocation produces two or more findings sharing a
surface, file ONE entry with a combined occurrence table, not N entries. Never
file zero entries.

## Step 4 — Write the backlog entry

Reuse the target file's existing ID scheme: read the last row's ID and
increment. `dev-efficiency-backlog.md` uses `E<n>`,
`loop-engineering-backlog.md` uses `L<n>`, `ci-backlog.md` and
`maintenance-backlog.md` use `<major>.<minor>`. Never invent a new scheme and
never renumber existing rows.

Two edits to the selected file, then one bump:

1. Append one row to its table, matching that file's existing column order
   exactly (they differ between files — read the header before writing).
2. Append one prose section, `### <ID> <title>`, carrying all five fields below.
3. Bump that file's `last_verified:` to today's date.

The five fields:

1. **class** — the step 1 sentence, verbatim. Where no class was nameable this
   line reads `class: n/a — <one-line reason>`.
2. **occurrence table** — the step 2 table, pasted whole, `Status` column
   included.
3. **prevention ladder** — walk all five rungs in order, one line each, then
   state the landing rung and why every cheaper rung was insufficient:
   - rung 0 — already covered by an existing gate?
   - rung 1 — extend an existing gate?
   - rung 2 — a rule doc under `.claude/rules/`?
   - rung 3 — a PHPStan rule?
   - rung 4 — a CI gate?
   - rung 5 — a new hook?

   Rungs 3-5 additionally require all four `.claude/rules/meta-tooling-bar.md`
   extend-before-add conditions to hold; name which ones do. Where no gate is
   warranted this field reads
   `prevention_ladder: no gate warranted — <reason>`.
4. **artifact destination** — the exact path the recommended gate would land at,
   and whether it is out-of-repo (`~/.claude/hooks/…`, `~/.claude/settings*.json`)
   and therefore edited in place and absent from any PR diff.
5. **provenance** — `(discovered YYYY-MM-DD during #<PR>)`. In `Mode: standalone`
   with no PR number yet, `(discovered YYYY-MM-DD during /fix-and-prevent)`.

The entry must be **self-sufficient**: a later `/plan` reading only this entry
must be able to build the gate without re-deriving any of the analysis above.

**Do not build the gate now.** Prevention is always a filed backlog item, in
both modes. There is deliberately no "prove the gate fires" step, because no
gate lands in this pass — the ladder-rung choice recorded at field 3 is what a
later `/plan` consumes in its place.

## Step 5 — The "no gate warranted" escape

A prevention gate is not always the right answer. Cheap-to-catch-later, genuinely
one-off, and already-covered classes warrant none.

Taking the escape does **not** skip the entry. File it anyway, with:

- `class:` — the step 1 sentence when a class was nameable but is not worth
  gating, otherwise `class: n/a — <one-line reason>`;
- `prevention_ladder: no gate warranted — <reason>`;
- the step 2 occurrence table, unchanged;
- fields 4 and 5 as normal (destination reads `n/a — no gate`).

A silent skip is a failure of this procedure. The reasoned verdict is the
deliverable.
