---
name: fix-and-prevent
description: "Fix a reported bug/breakage AND land the prevention for its whole defect class in one pass — use whenever the user says something is broken, wrong, failing, or asks to fix a bug; the fix alone is never the finished unit of work."
last_verified: 2026-08-24
---

# /fix-and-prevent — Fix it, then make it not happen again

The user's issue text is `$ARGUMENTS`.

**Core invariant: the fix alone is NOT the deliverable.** A run is complete only when both landed: (A) the reported issue is fixed, and (B) that issue's *class* has its prevention designed and **filed as a backlog item** — or a stated, justified verdict that no prevention is warranted. Never end the turn after Phase A.

## Invocation shapes

| Typed | Behavior |
|-------|----------|
| `/goal /fix-and-prevent <issue>` | **Preferred.** The `/goal` evaluator persists the completion condition across turns, so the run survives a long fix and can't quietly stop after Phase A. |
| `/fix-and-prevent <issue>` | Same two phases, no cross-turn persistence. |
| Model-invoked (user says "X is broken", "fix Y") | Same. Announce in one line that you're running fix-and-prevent so the user can redirect. |

**One goal, not two.** `/goal` holds a single session-level completion condition. Set it to the conjunction — *"fix landed AND (backlog item filed with full analysis OR a 'no gate warranted' verdict stated)"* — never two sequential goals; nobody is present to set the second one after the first resolves. Do not attempt to invoke `/goal` yourself: there is no tool for it, only the user can type it.

## Step 0 — Worktree + triage (before any edit)

1. **Be in a worktree — the right one.** All work happens in a worktree, never the main checkout (ADR-0062, `.claude/rules/workflow-continuity.md`). `~/.claude/hooks/plan-gate-edit.sh` **denies** the first Edit on `master`, so this is not optional.
   ```bash
   git rev-parse --abbrev-ref HEAD    # master/main/HEAD → create one:
   bin/wt-new <slug>                  # slug = kebab-case name for the defect
   ```
   Already in a worktree whose branch is **unrelated** to this defect (or carries someone else's dirty work)? Create a fresh one anyway — otherwise the fix rides an unrelated PR.
   Then work in `IBL5-worktrees/<slug>/ibl5/` and derive the Docker hostname per `.claude/rules/worktree-hostname.md`.
2. **State the triage verdict** — ad-hoc vs `/plan` — per `.claude/rules/work-triage.md`, plus its safety mirror (security surface, destructive migration, new user-visible UI, subjective-judgment property → prefer `/plan`). One line, out loud. If the verdict is `/plan`, hand off to `/plan` and stop; the plan carries both phases.

**Exception — prevention artifacts that live outside the repo tree** (`~/.claude/hooks/`, `~/.claude/settings*.json`, per-project memory): these are worktree-**exempt**, edited in place, and will **not** appear in the PR diff. See `_remediation.md` step 4, field 4 (artifact destination).

## Phase A — Fix the reported issue

1. **Reproduce first.** A failing test, a command with its output, a curl against `<slug>.localhost/ibl5/…`. If you cannot reproduce, say so and ask before "fixing" a symptom you can't see.
2. **Diagnose to root cause, not to the symptom.** The root cause is what Phase B generalizes from; a symptom-level fix produces a worthless gate.
3. **Fix it, with a regression test that pins *this* bug** — fails before, passes after. This is artifact #1 of two (see Phase B step 3).
4. **Verify clean** — the relevant test suite / linter / gate, not just the new test.

## Phase B — Prevent the class

Phase A fixed one occurrence. Phase B is about the family.

### 1. Name the defect class

One sentence: *"any code that <does the wrong thing> in <this kind of place>."* If you can't write that sentence, the class isn't identified yet — keep diagnosing, don't skip to a gate.

### 2. Scan for other live occurrences — enumerate, don't guess

Grep/LSP the repo for the class. Produce a **per-occurrence table with a status column** — never a bare count and never an assertion that "the rest are fine" without a per-member check:

| File:line | Same defect? | Disposition |
|-----------|--------------|-------------|

**Overflow rule.** If the scan turns up more than a handful, do **not** silently expand this PR into a sweep — `~/.claude/hooks/plan-gate-edit.sh` denies the **5th** distinct repo file edited on the main thread in one turn, and a sweep needs its own review surface anyway. Instead:

- Fix the reported occurrence (Phase A), then file the prevention backlog item (step 4).
- Paste the occurrence table into that same entry and mark the unfixed rows `not fixed — filed` — do not open a second item for the remainder.
- Say so in the PR body.
- If a bounded sweep genuinely belongs in this PR and is design-resolved + machine-verifiable, route it to **one** `subagent_type: "sonnet-4-6"` sub-agent (omit `model`) per `.claude/rules/work-triage.md` § Execution routing — before the 5th edit, not after the denial.

### 3. Two artifacts, not one

| Artifact | Scope | Where |
|----------|-------|-------|
| **Regression test** | pins *this* bug | shipped in Phase A step 3 |
| **Prevention backlog item** | designs the gate that catches the *next* one | filed per `_remediation.md`, at the surface its step 3 selects |

Shipping only the regression test is the default failure mode this skill exists to prevent.

### 4. Prevent the class — follow the shared remediation procedure

Read `.claude/skills/fix-and-prevent/_remediation.md` and follow the printed
procedure in **standalone mode**. State `Mode: standalone` out loud before its
step 1; the procedure refuses to run without a declared mode.

It carries the whole of what used to live here: the backlog-file selection rule,
the five-rung prevention ladder, the artifact-destination call, and the
"no gate warranted" escape. Steps 1 and 2 above feed it — do not redo them.

Two framing rules survive the relocation and are restated here because they
govern *what you write into the ladder analysis*, not how you write it:

- **Do NOT reflexively add a matching `bin/test-*` for a recommended gate.**
  `.claude/rules/meta-tooling-bar.md` names that as the trap — `test-*` is the
  symptom being capped, not a blanket requirement. Test coverage for a gate is a
  per-gate judgment call, made when the gate is actually built.
- **Never propose "remember to do X" or manual discipline as the prevention** —
  it has drifted within weeks every time. Recommend a mechanism, or record the
  no-gate verdict.

**The gate does not get built in this pass.** Prevention lands as a filed backlog
item with the full analysis, so a later `/plan` can build it from the entry
alone. That is why there is no longer a "prove the gate fires" step: nothing
fires yet.

## Step 3 — Ship

Only when **both** phases verified clean.

- **Do NOT commit.** Leave the worktree **dirty** — `/post-plan` Phase 2 commits the tree and opens the PR.
- Fire it, **no confirmation prompt** (shipping verified-complete worktree work is pre-authorized per `.claude/rules/workflow-continuity.md`):
  ```bash
  bin/post-plan-now --auto
  ```
- **Commit/PR title type** — classify by what the diff **is**, never by the desired merge outcome (`.claude/rules/commit-conventions.md`): the prevention backlog item is `chore:`; the fix is usually `fix:`. If the fix restores or adds something a league GM would notice as new, it's `feat:` — which trips the human-signoff hold. That is the gate working; do not retitle to route around it.

**If verification did NOT pass** (failing tests, unresolved blocker, you stopped to ask the user something): do **not** fire post-plan. Leave the worktree dirty and hand off in prose.

## Completion criteria — what "done" means

Every line must be true (this is what a `/goal` condition should check):

- [ ] Issue reproduced, root cause named (not just the symptom).
- [ ] Fix landed + regression test pinning *this* bug.
- [ ] Defect class named in one sentence.
- [ ] Occurrence scan run, per-occurrence status table produced; overflow filed as backlog/follow-up if any.
- [ ] Backlog item filed with the full analysis (defect class, occurrence table, ladder rung + why cheaper rungs were insufficient, artifact destination, provenance) — **or** an explicit, reasoned "no gate warranted" verdict, filed the same way.
- [ ] Any out-of-repo prevention artifact called out by path.
- [ ] Full verification clean, then `bin/post-plan-now --auto` fired on a dirty tree.

**Terminal-failure branch — this also completes the run.** If the work is blocked (verification won't pass, a real blocker, or you need an answer only the user has): state the blocker, leave the worktree dirty, hand off in prose, and **stop**. That is a finished run, not a pending one — a `/goal` condition must treat it as satisfied, or the evaluator loops forever on a condition it can't reach.

## Calibration

**Out of scope** — don't run the full two-phase ritual for:

- A typo, a one-line fix, or a local mistake with no defect *class* behind it (you can't write the Phase B step 1 sentence). Just fix it.
- A **question** about why something fails, as opposed to a request to fix it. Answer the question.
- Work already inside a `/plan` — the plan carries its own verification and prevention design.

On **model-invocation** (the user said "X is broken", not `/fix-and-prevent`), announce in one line that you're running it and what the two phases will cost, **before** Step 0 — so the user can redirect to a plain fix.

**Headless:** no-op under headless/automouse. A model-invoked run there would spawn a worktree mid-plan and derail a pre-vetted run; automouse fires its own post-plan. Governs interactive work only.
