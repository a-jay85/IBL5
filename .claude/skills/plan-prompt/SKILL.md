---
name: plan-prompt
description: "Draft a /plan prompt distilled from the current conversation — ground-truth pointers, already-measured evidence, scope, constraints, verification, and the Step-3 architect tier — then, unless the Step-1.5 size triage says the work clears the ad-hoc bar, fire it as a detached headless Sonnet 4.6 run via bin/plan-now. Use after a design discussion when the planning run should be offloaded off the expensive session."
disable-model-invocation: true
last_verified: 2026-07-27
---

# Draft a `/plan` handoff prompt and fire it headless

Compose a prompt that a **fresh Sonnet session** runs as `/plan`, carrying everything
this conversation figured out — then hand it to `bin/plan-now`, which runs it in a
detached headless Sonnet 4.6 session (Step 5). The clipboard copy stays, as the
fallback for running it by hand.

**Why this exists.** A `/plan` run costs mostly orchestration, not design: the design
happens in one `plan-architect` sub-agent spawn (`.claude/rules/agent-tiering.md`
§ Tiers). A Sonnet orchestrator driving an Opus architect costs far less than an Opus
orchestrator doing the same, and a fresh session survives a mid-plan network failure
that would otherwise cost this whole context. The handoff prompt is the only thing
carrying design context across that boundary — everything the prompt omits, Sonnet
either re-derives (expensive) or gets wrong.

## Step 0 — Draft from this conversation only

**Spawn no agents. Re-read no source files. Run no scans.** The skill exists to
*offload* context, so growing this one defeats it.

If a fact isn't already established here, do not resolve it now — emit it as a
**Read first** pointer for Sonnet to resolve, or as an open question outside the
block. "Sonnet, go read `X`" is cheap; Opus reading `X` right now is the exact cost
this skill removes.

## Step 1 — Decide what to plan

`$ARGUMENTS` has three shapes:

| Argument | Meaning |
|---|---|
| *(empty)* — the common case | Plan the thing this conversation converged on. If the conversation covered several things, pick the one most recently discussed and say which you picked outside the block. |
| A selector — `items 1-3`, `just the migration part` | Narrow to that slice; state what you dropped as out-of-scope. |
| A fresh description | Plan that, using this conversation only as background. |

## Step 1.5 — Size triage: does this even want a `/plan`?

Firing spends a full `/plan` run and, by default (`--queue`), an unattended
implementation run behind it. Work that clears the ad-hoc bar needs neither. Make the
call **before** composing — Step 3 is where the tokens go.

**Triage from what this conversation already established.** Step 0 still binds: no
scans, no re-reads. That inverts `.claude/rules/work-triage.md`'s "resolve empirical
unknowns first" — you cannot resolve one here, so an unknown that would change the
design (how many call sites? does the pattern generalize?) is itself a **not-ad-hoc**
verdict. Never go measure now in order to reach an ad-hoc answer.

**Never downgrade**, regardless of size, when the work touches anything in
`work-triage.md` § Ad-hoc safety mirror — a security surface, a destructive or
schema-tightening migration, new or redesigned user-visible UI/UX, or a property
needing subjective human judgment — or a `.claude/skills` ship-pipeline invariant.
High-stakes outranks small, the same precedence `/plan` Step 3 applies to the architect
tier. A multi-PR split is also a not-ad-hoc signal, and triage runs *before* Step 2, so
make the PR-count call here.

**Downgrade only when every clause of § The ad-hoc bar holds** — known blast radius, an
existing pattern to copy, no multi-phase reasoning, no unresolved design fork — and the
verification is one obvious check rather than a matrix worth designing. Borderline is
not ad-hoc: an over-planned small change costs one `/plan` run, while an under-planned
large one ships a wrong PR through a pipeline with no human in it.

**On an ad-hoc verdict: stop here.** Do not compose a block, do not fire, do not start
implementing. Say what the work is, that it clears the bar, and which clause carried the
call. Execution then routes per `work-triage.md` § Execution routing — a `sonnet-4-6`
delegate, not inline on this session. An ad-hoc verdict retracts the `/plan` run, never
the offload; offloading is why you invoked this skill.

## Step 2 — Split by PR before composing

**One plan = one PR** (`.claude/skills/plan/SKILL.md`). If the work spans multiple
PRs, emit **one prompt block per PR**, in dependency order, each naming its base
branch. Do not hand Sonnet a bundle and expect Step 2.5 to untangle it — you already
know the split; encode it.

## Step 3 — Compose each block

The block opens with `/plan <one-sentence task statement>` and then carries the
sections below. Include a section only when it has real content — an empty heading is
padding. Fence with ` ```markdown ` (four backticks if the body itself contains
fences).

1. **Read first (ground truth) — do NOT plan from my summary alone.**
   Explicit `path:line` pointers — repo files, ADRs, backlog entries, prior
   `~/claude-plans/*.md` — each with the *one load-bearing fact* it establishes.
   Cite at whatever precision this conversation already established (Step 0) — a bare
   path is fine; do not open files now to sharpen a pointer into a `path:line`.
   This is the load-bearing section: it is what lets a cheap orchestrator reach the
   same understanding this conversation did.

2. **Already measured / already traced — verify, don't re-derive.**
   The conclusions this conversation produced: counts, `path:line` anchors, traced
   call paths, ruled-out hypotheses. Include the **anti-patterns** explicitly
   ("do not 'fix' the auth attribution — it's intentional"; "ignore the stale InnoDB
   `table_rows` estimate"). Anything omitted here gets re-derived or re-litigated.

3. **Scope** — numbered parts, plus an explicit **out of scope** list.

4. **Hard constraints** — ADR references, invariants and orderings to preserve, PII
   boundaries, `auto_merge: false` when the change wants human signoff, whether
   `/backlog-housekeep` ships with the PR.

5. **Blocking questions to resolve inside the plan** — unknowns that *change the
   design*. Mark them "resolve inside the plan"; don't leave them implicit and don't
   burn this session resolving them.

6. **Verification** — what the plan's matrix must actually run and prove, not just
   "add tests".

7. **Step 3 architect tier** — see Step 4 below. State it as a directive:
   `Step 3 MUST route to plan-architect-xhigh`.

8. **Sequencing** — intended branch slug, base branch, rebase expectation, and any
   peer session that owns the same files (the SessionStart hook already told you).
   Naming the slug makes `~/claude-plans/<slug>.md` predictable for the later
   `/post-plan`.

Facts that may move by the time Sonnet runs (next migration number, current `master`
tip) — tell it to **resolve at plan time**, don't hardcode.

## Step 4 — Name the architect tier

The tier directive is the highest-leverage line in the prompt: it is the mechanism by
which a Sonnet orchestrator still gets Opus-grade design. Abbreviated below;
`.claude/rules/agent-tiering.md` § Tiers is authoritative — read it when the call
isn't obvious:

- security surface / trust boundary / destructive migration / ship-pipeline
  invariant → **`plan-architect-xhigh`**
- explicit recipe **plus** a named existing pattern to copy →
  **`plan-architect-sonnet`**
- otherwise → **`plan-architect`**

Also state the orchestrator model outside the block: a single item → **Sonnet**;
several items decomposed in one pass → **Opus** (`agent-tiering.md` §
`/plan` orchestrator model). If the answer is Opus, say so — this skill's default
isn't always right.

## Step 4.5 — Pre-resolve the user-facing forks (gate)

The run this skill fires has **no human in it**, so `/plan` Step 3.5 — which surfaces
each `needs-user-input` fork with `AskUserQuestion` — has nobody to ask. That
resolution has to happen *here*, where a human is present.

Re-read the block you just composed and ask: does anything in it turn on a
**preference the codebase cannot reveal** — which of two UXes, how aggressive a
default, whether a behavior change is acceptable? If yes, **ask it now** with
`AskUserQuestion` (2–4 options, your recommendation first) and bake the answer into
the block as a hard constraint (section 4), not as an open question.

Keep the distinction sharp:

| Fork | Where it goes |
|---|---|
| The architect can settle it by reading the code | Block section 5 — "resolve inside the plan" |
| Only the user can settle it | **Ask now**, then block section 4 as a fixed constraint |

Do not ask conventional or irreducible forks (`/plan` Step 3.5 rule) — asking about
things with an obvious answer is the failure mode on the other side.

`bin/plan-now` appends a coda telling the run to never ask, and to record + hold
(`auto_merge: false`) any fork that survives anyway. That coda is the backstop, not
the plan: a fork you could have resolved here costs a held PR.

## Step 5 — Emit and fire

1. Print the block(s).
2. Write block 1 to a temp file and copy it to the clipboard:

   ```bash
   f=$(mktemp -t plan-prompt); : > "$f"   # write the block into "$f" first
   pbcopy < "$f"
   ```

   Use `mktemp`, not a fixed `/tmp` name — several sessions run this repo at once.
   With multiple blocks, copy block 1 and say which one is on the clipboard.
   Keep the `pbcopy` even though step 3 fires the run: if the fire fails, the block
   is still on the clipboard and you have degraded to the old manual paste, not lost
   the draft.
3. Fire it — the file from step 2 is the argument:

   ```bash
   bin/plan-now "$f"                    # default: queue for automouse if the plan passes
   bin/plan-now --implement "$f"        # write the plan but leave it unqueued for review
   bin/plan-now --model opus "$f"       # when Step 4 said the ORCHESTRATOR wants Opus
   ```

   This runs `/plan` in a **detached headless Sonnet 4.6 session** (launchd — it
   survives closing Claude Code), then runs `bin/check-plan` on the produced plan and
   logs the verdict. Report the log path it prints; don't poll it.

   **Default disposition is `--queue`** — matching `/plan`'s own default. The fired run
   goes design → plan → implementation → PR without a human in the loop, so the block
   you just composed is the last human-authored input to a shipped PR. The gate is
   `bin/plan-now`'s own, not the model's: it queues only when the run **exits clean**
   *and* the plan is **identified by its `PLAN_FILE:` line** *and* **`bin/check-plan`
   passes**. A degraded run lands on disk for a human instead. Reach for
   `--implement` when you want to read the plan before it implements — a novel design,
   a scope you're unsure of, or a Step 4.5 fork you resolved with low confidence.

   Because the plan will be *executed*, not skimmed: Step 4.5's fork pre-resolution and
   the Step-3 tier directive are what stand between this block and a wrong PR.

   With multiple blocks, fire **only block 1**. Later blocks are stacked PRs whose base
   branch does not exist yet — write them out, and say they fire after block 1's PR.

   Skip the fire (draft only) when the user asked for the prompt itself, or when
   Step 4.5 left a fork you could not resolve. Say which happened.
4. **Outside** the fenced block — never inside, so the paste stays clean — add:
   - which model should run it, and why (Step 4);
   - any judgment call you made for the user (scope picked, split chosen);
   - if a network failure kills the architect mid-run, **re-spawn the same tier**.
     `/plan` Step 3 already delivers the plan section-by-section with each section
     appended to disk before the next turn, so a stall costs one section, not the
     plan — the prompt doesn't need to ask for piecewise delivery, and a stall is
     never a reason to downgrade the tier.

Then stop. The plan is being written in another process; do not wait on it, tail its
log on a loop, or start implementing. The user picks it up from
`~/claude-plans/<slug>.md` when the run finishes.
