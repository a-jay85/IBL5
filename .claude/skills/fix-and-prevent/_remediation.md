---
description: Shared remediation procedure for /fix-and-prevent and the /post-plan Phase 5.5 in-PR fix path: declared mode, fix, class-wide prevention, and ship hand-off.
last_verified: 2026-11-10
---

# /fix-and-prevent remediation procedure

Shared include. Two callers:

- `.claude/skills/fix-and-prevent/SKILL.md` reads it by path (same directory).
- `.claude/review-shared/_phase65-remediation.md` loads it with
  `git show <MASTER_SHA>:.claude/skills/fix-and-prevent/_remediation.md`.

**Callers MUST declare their mode at invocation.** Write one of these two lines
into the run notes before step 1, verbatim:

- `Mode: standalone` — invoked from `/fix-and-prevent`. You own the ship step:
  after step 5, return to `/fix-and-prevent` Step 3, which fires
  `bin/post-plan-now --auto`.
- `Mode: in-PR`: invoked from `/post-plan` Phase 5.5 step 4. You do NOT own the ship
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
| Nothing left to do (every row `fixed this pass`, ladder at rung 0 or no gate) | file the entry anyway (step 5) | record the entry in the verdict, file **no** issue (step 5) |
| Review layer + corpus case (step 2a) | yours | skipped: the PR is still in flight, so no layer has missed a shipped bug |
| Commit / push | `bin/post-plan-now --auto` at SKILL.md Step 3 | the caller's Phase 6.5 commit + push steps |
| Ends with | a PR opened by `/post-plan` | control returned to the caller |
| Finding tagged `[unplanned-scope]` | Not applicable. | Classify with `harness.unplanned_scope` first; `KEEP` and `HOLD` findings are never deleted (`.claude/review-shared/_phase65-remediation.md` step 3). |

Everything below runs identically in both modes, except step 2a, which runs in `Mode: standalone` only.

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

## Step 2a: Name the review layer that should have caught it (`Mode: standalone` only)

In `Mode: in-PR`, skip this step. The PR is still in flight, so no layer has missed a shipped bug yet.

Pick exactly one layer from this table. Every layer exists today, and the last column names where it is defined.

| Layer | What it is | Defined in |
|---|---|---|
| `pre-commit` | the local commit hook | `bin/pre-commit-hook` |
| `plan-gates` | the plan-time gates `/plan` Step 4 runs | `bin/check-plan` |
| `phase-4-review` | the post-plan harness review: Agent A, Agent D, and the security agent | `tools/postplan-harness/harness/review.py`, `.claude/review-shared/_review-agents.md`, `.claude/review-shared/_security-agents.md` |
| `phase-5.5-fidelity` | the post-plan plan-intent fidelity review | `tools/postplan-harness/harness/fidelity.py`, `.claude/review-shared/_plan-fidelity-review.md` |
| `ci-tests` | the `Tests and Analysis` CI job and the jobs it rolls up | `.github/workflows/tests.yml` |
| `ci-e2e` | the `E2E Tests` and `Visual Regression` CI jobs | `.github/workflows/e2e-tests.yml` |
| `ci-mutation` | the `Infection PHP (per-PR diff)` CI job | `.github/workflows/mutation-pr.yml` |
| `ci-meta` | the `Meta checks` CI job | `.github/workflows/pr-meta-checks.yml` |
| `smoke-prod` | the post-deploy `Production Health Check` | `.github/workflows/smoke-prod.yml` |
| `none` | no layer above could see the bug, for example one that needs prod-only data | n/a |

Choose by these rules, in order:

1. A mechanical layer (`pre-commit`, `plan-gates`, any `ci-*`, `smoke-prod`) that already checks this defect class and missed it wins. The ladder at step 4 field 3 then extends that layer.
2. Otherwise, pick `phase-4-review` when the defect is visible in the introducing PR's diff alone. That is the third confirmation test in `ibl5/docs/reviewer-eval.md` § Adding a case.
3. Otherwise, pick `phase-5.5-fidelity` when the defect is a gap between the plan and the diff.
4. Otherwise, pick `none` and say what input no layer had.

Record one line for step 4:

```
review layer: <layer> — <one-line reason>
```

### Corpus case (layer `phase-4-review` only)

The fix PR cannot carry the corpus case. `bin/reviewer-eval validate` requires `fix_commit` to be the fix's squash-merge commit on master, and that commit exists only after the merge. So the case becomes part of the prevention item, and a later run appends it.

1. Take the introducing PR `#N` from the step 4 blame instruction.
2. Run `gh pr view <N> --json comments --jq '[.comments[].body | select(startswith("## Code review"))] | length'`.
3. Record exactly one of these for step 4:
   - Layer is not `phase-4-review`: `corpus case: n/a — layer is <layer>`
   - The count is `0` (a hotfix, a pre-harness PR, or a direct push): `corpus case: skipped — introducing PR #<N> has no "## Code review" comment`
   - `gh` exits non-zero: `corpus case: skipped — gh pr view #<N> failed: <first error line>`
   - The count is above `0`: the block below.

```
corpus case: pending — append after the fix PR merges
  pr: <N>
  commit: <git log -1 --format=%H --grep='(#<N>)$' origin/master>
  expected: <path>:<start>-<end> (lines #<N> added that the fix changes)
  description: <one sentence, at most 300 characters, no names, no '@'>
  how: follow ibl5/docs/reviewer-eval.md § Adding a case. Set fix_pr to the
    fix PR, fix_commit to its squash SHA, status to confirmed. Update
    test_real_corpus_expected_ranges_pinned in
    tools/postplan-harness/tests/test_reviewer_eval.py (count and
    PINNED_EXPECTED) in the same change. Run bin/reviewer-eval validate.
```

## Step 3 — Select the backlog label

Deterministic. Select by the defect class's surface:

- App code / game logic / data model → label: `maintenance`; command: `bin/backlog new maintenance "<title>" "<body>"`
- CI or GitHub Actions → label: `ci`; command: `bin/backlog new ci "<title>" "<body>"`
- E2E test quality → label: `e2e`; command: `bin/backlog new e2e "<title>" "<body>"`
- Accessibility (non-contrast) → label: `a11y`; command: `bin/backlog new a11y "<title>" "<body>"`
- Accessibility contrast → label: `a11y-contrast`; command: `bin/backlog new a11y-contrast "<title>" "<body>"`
- Token spend / Claude context economy → label: `token-spend`; command: `bin/backlog new token-spend "<title>" "<body>"`
- Developer tooling (inner loop, scripts, worktree) → label: `dev-efficiency`; command: `bin/backlog new dev-efficiency "<title>" "<body>"`
- Autonomous-loop or harness behavior → label: `loop-engineering`; command: `bin/backlog new loop-engineering "<title>" "<body>"`

**Consolidate.** When one invocation produces two or more findings sharing a
surface, file ONE issue with a combined body, not N issues. In
`Mode: standalone`, never file zero issues. `Mode: in-PR` files only what
step 5 lets through.

## Step 4 — File the backlog issue

Dedup before filing. When the finding came from a PR, run
`bin/backlog for-pr <pr-url>` first to see issues already filed from it.
Then run `bin/backlog search <keywords>` for older issues. When an open issue
covers the same surface, comment on it and file nothing new. Otherwise run the
`bin/backlog new <label> "<title>" "<body>"` command from Step 3. Legacy
markdown IDs survive as title prefixes (e.g. `E46: <title>`).

In `Mode: in-PR`, file in descending severity order. `bin/backlog new` keeps each origin PR to 3 follow-up issues and folds the fourth and later into a roll-up on the third, so filing order decides which findings keep a standalone issue. The full rule is the **Order and cap** bullet in `.claude/review-shared/_phase65-remediation.md`.

Filing rules. A finding whose only location is a file the PR deletes or renames
away gets no issue. A finding with no nameable failure scenario gets no issue.
A cosmetic-only finding (wording, formatting, comment style, naming with no
behavior change) is fixed now or dropped. It is never filed. In `Mode: in-PR`,
also apply the file-after-the-last-fix rule in
`.claude/review-shared/_phase65-remediation.md` step 3.

`bin/backlog new` rejects a body that breaks the first two lines below, so write
them first. Line 1 is the origin PR URL alone
(`https://github.com/a-jay85/IBL5/pull/N`). In `Mode: in-PR` that is the current
PR from `gh pr view --json url -q .url`. In `Mode: standalone` it is the PR
that introduced the cited line: run `git blame -L <line>,<line> -- <file>`, and
the squash-merge subject of that commit ends in `(#N)`. Line 2 cites the
occurrence as `path/to/file.ext:LINE`. The failure scenario (concrete input or
state, then the wrong output, crash, or cost) follows. After those lines the
body carries the five fields:

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
   When step 2a named `phase-4-review` and no rung applies, the reason reads
   `the reviewer-eval corpus case in this entry is the prevention`.
4. **artifact destination** — the exact path the recommended gate would land at,
   and whether it is out-of-repo (`~/.claude/hooks/…`, `~/.claude/settings*.json`)
   and therefore edited in place and absent from any PR diff.
5. **provenance** — `(discovered YYYY-MM-DD during #<PR>)`. In `Mode: standalone`
   with no PR number yet, `(discovered YYYY-MM-DD during /fix-and-prevent)`.

In `Mode: standalone`, the body also carries the step 2a `review layer:` line and
the `corpus case:` line or block, after field 5. They are not numbered fields, so
`Mode: in-PR` still writes five.

The entry must be **self-sufficient**: a later `/plan` reading only this entry
must be able to build the gate without re-deriving any of the analysis above.

**Do not build the gate now.** Prevention is always a filed GitHub Issue, in
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
- in `Mode: standalone`, the step 2a `review layer:` and `corpus case:` lines.

A silent skip is a failure of this procedure. The reasoned verdict is the
deliverable.

`Mode: in-PR` files an issue only when work outlives the PR. That means a
`not fixed — filed` row in the step 2 table, or a ladder landing on rungs 1-5.
When every row is `fixed this pass` and the ladder lands on rung 0 or
`no gate warranted`, write the five fields into the Phase 7 verdict comment and
run no `bin/backlog new`. The entry still exists on the PR, so this is not a
silent skip. A backlog issue for work already done gets closed unread at the
next triage. The 2026-09-24 triage closed dozens of them, with titles ending
"fixed in 6.5 remediation" or "corrected in Phase 6.5".
