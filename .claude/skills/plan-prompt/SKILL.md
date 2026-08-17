---
name: plan-prompt
description: "Draft a /plan prompt distilled from the current conversation — ground-truth pointers, already-measured evidence, scope, constraints, verification, and the Step-3 architect tier — then, unless the Step-1.5 size triage says the work clears the ad-hoc bar, fire it as a detached headless Sonnet 4.6 run via bin/plan-now. Use after a design discussion when the planning run should be offloaded off the expensive session."
disable-model-invocation: true
last_verified: 2026-08-17
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

Firing spends a full `/plan` run and an implementation run behind it. Work that clears
the ad-hoc bar needs neither. Make the call **before** composing — Step 3 is where the
tokens go.

`.claude/rules/work-triage.md` is always loaded and already owns this decision: apply
§ The ad-hoc bar and § Ad-hoc safety mirror as written — downgrade only when every
clause of the bar holds, never when a mirror trigger fires. Four things are specific to
triaging from *here*:

- **You cannot resolve the empirical unknowns.** Step 0 forbids scans and re-reads, so
  the bar's "resolve empirical unknowns first" inverts: an unknown that would change the
  design (how many call sites? does the pattern generalize?) is itself a
  **not-ad-hoc** verdict. Never go measure now in order to reach an ad-hoc answer.
- **Triage runs before Step 2, so make the PR-count call here** — a multi-PR split is a
  not-ad-hoc signal in its own right.
- **Borderline is not ad-hoc.** An over-planned small change costs one `/plan` run; an
  under-planned large one ships a wrong PR through a pipeline with no human in it.
- **The bar's verification clause binds harder here** — one obvious check, not a matrix
  worth designing, because nobody reviews the result before it ships.

**On an ad-hoc verdict: stop here.** Do not compose a block, do not fire, do not start
implementing. Say what the work is, that it clears the bar, and which clause carried the
call. Execution still routes per `work-triage.md` § Execution routing — a `sonnet-4-6`
delegate, not inline on this session. The verdict retracts the `/plan` run, never the
offload; offloading is why you invoked this skill.

## Step 2 — Split by PR before composing

**One plan = one PR** (`.claude/skills/plan/SKILL.md`). If the work spans multiple
PRs, emit **one prompt block per PR**, in dependency order, each naming its base
branch. Do not hand Sonnet a bundle and expect Step 2.5 to untangle it — you already
know the split; encode it.

## Step 3 — Compose each block

The block opens with `/plan <one-sentence task statement>` and then carries the
sections below. Include a section only when it has real content — an empty heading is
padding. The block is written to a file, not printed (Step 5), so it needs no
enclosing fence; add one (` ```markdown `, four backticks if the body itself contains
fences) only in the draft-only case where you do print it.

1. **`## Exploration pointers`** — explicit `path:line` pointers — repo files, ADRs,
   backlog entries, prior `~/claude-plans/*.md` — each with the *one load-bearing fact*
   it establishes. Cite at whatever precision this conversation already established
   (Step 0) — a bare path is fine; do not open files now to sharpen a pointer into a
   `path:line`. This is the load-bearing section: it is what lets a cheap orchestrator
   reach the same understanding this conversation did. When a fresh `/plan` session sees
   this section in `$ARGUMENTS`, it treats the pointers as trusted — cheap confirmation
   only, no re-exploration (`.claude/skills/plan/SKILL.md` § Step 2 trusted-context
   detection). Emit the heading **verbatim**: that exact string is the detection trigger.

2. **`## Resolved design decisions`** — the conclusions this conversation produced:
   counts, `path:line` anchors, traced call paths, ruled-out hypotheses. Include the
   **anti-patterns** explicitly ("do not 'fix' the auth attribution — it's intentional";
   "ignore the stale InnoDB `table_rows` estimate"). Anything omitted here gets re-derived or re-litigated.
   This section alone does **not** trip `/plan`'s trusted-
   context detection — item 1's heading is the trigger — but `/plan` transcribes it into
   the context artifact it seeds, so its contents bind the plan as fixed constraints.

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

- security surface / trust boundary / destructive migration / a **gate removal
  or weakening** in the ship-pipeline surface (`.claude/skills`, `.claude/rules`,
  `~/.claude/hooks`) or a **bootstrap hazard** (the change rewrites the rules
  governing its own merge) → **`plan-architect-xhigh`**
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
the plan: a fork you could have resolved here costs a held PR. And a fork you *did*
resolve, but with low confidence, is not a Step 5 disposition trigger — it means you
have not finished this step: reopen it with `AskUserQuestion` now rather than carrying
the uncertainty into an `--implement` run.

## Step 5 — Fire, then report

**Do not print the block in the conversation.** It runs to a hundred lines or more and
nobody reads it there — it exists to reach a headless Sonnet session, not your output.
If it misreads the intent, that surfaces at the PR, which is the cheaper place to catch
it. Printing it also manufactures this step's characteristic failure: a long emitted
artifact *feels* like the work landed, and a fire gets reported that never happened.
The block goes to a file and to `bin/plan-now`; the conversation gets the report in
step 3.

1. Write block 1 to a temp file and copy it to the clipboard:

   ```bash
   f=$(mktemp -t plan-prompt); : > "$f"   # write the block into "$f" first
   pbcopy < "$f"
   ```

   Use `mktemp`, not a fixed `/tmp` name — several sessions run this repo at once.
   With multiple blocks, copy block 1 and say which one is on the clipboard.
   Keep the `pbcopy` even though step 2 fires the run: if the fire fails, the block
   is still on the clipboard and you have degraded to the old manual paste, not lost
   the draft.
2. Fire it — the file from step 1 is the argument:

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
   `--implement` **if and only if** the work triggers `plan-architect-xhigh` — the same
   trigger list Step 4 names above, authoritative in `.claude/rules/agent-tiering.md` §
   Tiers. That is a blast-radius test, not a size-or-novelty one: those triggers mark
   the changes whose bad outcome you cannot take back, and only those are worth a human
   reading the plan before it implements. Everything else queues safely because
   `/post-plan` always opens the PR, Phase 6.5's arming conditions only ever *hold*, and
   a `feat:` title stays held by the commit-type floor and the `human-signoff` required
   check regardless — so a wrong queue call costs a PR you read at review time instead
   of at plan time.

   Because the plan will be *executed*, not skimmed: Step 4.5's fork pre-resolution and
   the Step-3 tier directive are what stand between this block and a wrong PR.

   With multiple blocks, fire **only block 1**. Later blocks are stacked PRs whose base
   branch does not exist yet — write them out, and say they fire after block 1's PR.

   Skip the fire (draft only) when the user asked for the prompt itself, or when
   Step 4.5 left a fork you could not resolve. Say which happened — and only in the
   first case print the block, since there the prompt *is* the deliverable.
3. Report, in prose:
   - **the log path, pasted from `bin/plan-now`'s own stdout — never composed.** If
     that output is not in front of you, you have not fired it: say so plainly and
     fire it. A path you wrote instead of read is a fabricated one, and it reads
     exactly like a real report. Then don't poll the log — `claude -p` doesn't
     stream, so it stays empty until the run exits. Say the run **DMs its verdict
     on finish**, every outcome, queued or not (`bin/plan-now` → `bin/discord-dm`;
     `--no-dm` opts out): that ping, not the log, is how the user learns it
     finished, and it is the only thing that surfaces a plan left unqueued for
     them to read.
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
