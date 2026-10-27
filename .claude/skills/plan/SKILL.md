---
name: plan
description: "Plan an implementation task: enforces a verification matrix, directs code reuse, flags security surfaces, and requires negative-path tests so plans drive clean, secure, well-tested implementations."
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
last_verified: 2026-10-26
---

# /plan — Implementation Planning with Verification Matrix

You are planning an implementation task. The user's request is quoted once below, between the `<user_request>` markers. Everywhere these instructions say **the request**, they mean that block. It appears only here, never further down.

<user_request>
$ARGUMENTS
</user_request>

**Do NOT write or edit any code files.** This skill produces a plan only. The output is one plan file per PR.

**One plan = one PR.** A single plan file must be implementable and mergeable as exactly one pull request. If the work naturally spans multiple PRs (independent concerns, separate review surfaces, a refactor that should land before the feature that uses it, or a change too large to review in one sitting), do NOT bundle them. Split the work into PR-sized units and produce a separate, fully self-contained plan — its own implementation steps and its own Verification Matrix — for each one. The user should never have to ask for this split.

**Maximize automouse autonomy.** A plan is most valuable when automouse can implement *and merge* it with no human in the loop. Three levers turn a would-be-supervised plan autonomous — apply them by default instead of reaching for `auto_merge: false`:

1. **Pin, don't supervise.** Uncovered code is not a reason to hold the merge — it's a reason to add a **Phase-1 characterization pin** (Step 4 gate 11). A behavior-preserving refactor with a pin is green-green; the pin *is* the coverage.
2. **Decide at plan-time, not merge-time.** A single discrete design fork doesn't have to wait for a human at merge — surface it to the user *now* (Step 3.5) and record the answer in the plan; automouse then executes a fully-specified decision.
3. **Mechanize the check, don't watch it.** When the *reason* to want a human at merge is "we can't tell mechanically whether it works" — a silent or integration-only failure mode, an "observe in prod" property, or a reflex to have a human confirm it behaves — that is a missing-verification problem, not irreducible judgment. Design the self-asserting check as an implementation phase: a required CI job that fails *loudly* on the bad outcome, a loud-failure signal replacing a silent fallback, an independent invariant assertion on the real artifact, or a shadow/burn-in rollout that enforces the invariant before flipping. This extends to a **reversible schema tightening** (a `varchar` narrowing, a length / `NOT NULL` constraint): an apply-time fail-closed guard that runs on the *target* DB self-gates the migration on prod at deploy, so "needs prod data unreachable from CI" becomes a guard to build — which *releases* the gate-14(c) hold once present and test-proven — rather than a permanent hold (see Schema-safety mechanization). It extends once more to a **dischargeable security surface** — a state-free extraction, a pure-function move, or a validator that never sees actor identity: name the structural invariant (the extracted collaborator holds no `$db`, no session/actor identity, no `$loggedInTeamID`) and assert it in a required-blocking test, and the gate-14(b) hold is *released* — so "a security-adjacent file was touched" stops being an automatic hold (see Security-surface mechanization). An *irreducible* surface — a check that genuinely carries actor identity, a new POST/form endpoint, a new SQL construction site, new user-facing output rendering — has no release path and stays held. Let auto-merge arm once the check exists; reach for `auto_merge: false` only after the check genuinely can't be built (see Verification-gap mechanization, Step 4 gate 15).

Fall back to `auto_merge: false` only when one of the seven enumerated gate-14 triggers (a) to (g) applies: a Truly-manual row, a security surface, a destructive or tightening schema change, new UI/UX, gate weakening or a bootstrap hazard, a golden-snapshot change, or a recorded-and-unresolved fork. A change invisible to a league GM with none of those takes gate 14's no-hold default. "A decision exists" is not a hold reason; "the decision can't be made once, up-front" is (gate 14g); and "I can't verify it works without watching it" is a verification gap to close with lever 3. The flag holds only the **merge**. Post-plan still runs and opens the PR for review either way.

## Step 1: Verification rule

Read `.claude/review-shared/_plan-verification.md` and use its full content as `$VERIFICATION_RULE` for injection into the Plan agent prompt in Step 3. Do not summarize or paraphrase the rule.

## Step 2: Orient on the codebase

**Prefer direct tool calls over Explore agents.** Most orientation can be done without spawning an agent:

1. Read `.claude/rules/codebase-map.md` to identify affected modules and their file locations. Skip it when the request's pointers already name the files.
2. Run targeted `grep`/`find` via Bash for specific symbols, callers, or file paths
3. Read key files directly (migrations, interfaces, existing tests)

**Spawn an Explore agent when EITHER** trigger fires — read volume is a trigger in its own right, not only residual uncertainty:

1. **Open questions** — direct lookups leave something genuinely unanswered.
2. **Byte isolation** — answering would mean reading large source files (roughly: any file over ~300 lines, or a combined read budget past ~600 lines) into *this* session. `/plan` is **delegation-terminal**: the deliverable is produced by the `plan-architect` sub-agent in its own fresh window, so the orchestrator never needs those files resident. Even with zero open questions, pull the heavy reads into an Explore agent (or defer them to the plan-architect) so the bytes land in a disposable window, not the orchestrator's — three consecutive auto-compactions before any planning work is the failure this prevents. What you carry forward is **pointers** (`path:line` + the one load-bearing fact per file), never the file contents; the plan-architect re-reads at those pointers in its own window (targeted confirmation, not re-exploration — see Step 3 point 2).

Tier per `.claude/rules/agent-tiering.md`:

- Single-module change → 0 agents (direct tools suffice) or 1 Haiku for enumeration
- Spans 2+ modules → up to 2 agents (Explore for cross-module traces, already pinned Sonnet 5.5, use `subagent_type: "Explore"` omit `model`; Haiku for file/grep lookups)
- Never spawn 3 agents — this cap governs **your** Step-2 fan-out only. The `plan-architect` you spawn at Step 3 carries its own separate budget (≤1 `Explore`, for a question that surfaces mid-design), so the run-wide ceiling is 3: 2 here + 1 there. Do not pre-spend the architect's spawn by fanning out wider here on its behalf.

Provide each agent a single concrete question, pre-resolved paths, and a response cap (under 150 lines). An agent spawned purely for byte isolation must return **distilled pointers** (`path:line` + the load-bearing fact), not pasted file bodies — pasting the contents back defeats the isolation.

Collect: file paths, existing patterns, dependencies, blast radius, existing test coverage for affected code, **the specific existing helpers/services/repositories the implementation should reuse** (name the exact methods, e.g. `SalaryCapRepository::getTeamTotalSalary()`, so the plan directs reuse instead of leaving the impl agent to rediscover them), and **which security surfaces the change touches** (SQL queries, POST/form endpoints, auth/authz-gated routes, user-facing output rendering). If none of these surfaces are touched, record that explicitly. Also record **whether the task resolves a finding tracked in GitHub Issues** (repo: `a-jay85/IBL5-backlog`). If so, record the issue number and its current state, so Step 3 can record it in the plan's `## Backlog issues` section, which closes it when this PR merges. Search by keyword with `bin/backlog search <terms>`, using two or three terms from the task. When the request names an issue number, read that one issue with `gh issue view <N> --repo a-jay85/IBL5-backlog --json number,title,state`. Do not list every open issue. The 100-row listing was the largest single result in measured Step 2 runs. Separately, record **whether the work leaves a follow-up that can only run *after* the PR merges**, meaning something the merge event itself unblocks: a stale memory/doc/plan that stays valid until the change lands (e.g. a `MEMORY.md` "delete this entry when #N merges" pointer), a temporary compat shim that can be removed once its consumer deploys, a feature flag to retire post-rollout. Note each one so Step 3 can **mechanize** it as a merge-triggered watcher rather than leaving it to the user's memory. When Step 2.5 seeds a shared-context artifact because the work splits into multiple PRs **or** because this invocation arrived with trusted context (see below), these pointers (`path:line` + the one load-bearing fact per file) are the **source** of the artifact's exploration-pointers section. Step 2.5 persists them **once** at SEED so nothing re-derives them, and Step 3 hands the `plan-architect` the artifact rather than re-inlining them. Keep them as pointers here (never file bodies) so they transcribe straight into the artifact.

```bash
bin/backlog search <terms>
```

### Orient budget: hand off at 12 calls

This budget binds every run, interactive or headless.

**Budget.** Spend at most **12 tool calls** from the Step 1 Read up to the Step 3 `plan-architect` spawn. Step 2.1's three signals and the Step 2.5 artifact write count against it. Under trusted context, the 1-2 targeted confirmations come out of the same 12.

**Batch.** Put independent calls in one message: greps, `ls` checks, and all three Step 2.1 signals. Each extra turn keeps its thinking in context for the rest of the run.

**Pointers only.** Read no source file past about 40 lines in this session. Use `grep -n`, or `sed -n 'A,Bp'` over a range under 40 lines. Write pointers into the artifact (`path:line` plus one fact). Paste no file body into it.

**Hand-off test.** Go to Step 2.5 and Step 3 as soon as you can name, for every file in the blast radius, its path and the one fact the architect needs. The architect re-reads the code in its own window and holds one `Explore` spawn for questions left open.

**At the cap.** At call 12, stop orienting. Write each unanswered item into the artifact as a named open question for the architect. The cap never skips Step 2.1. If the cap would cut it, run Step 2.1 and drop the remaining orientation.

### Trusted context — when the caller already did the exploration

Some `/plan` invocations arrive with the exploration **already done**: a prior
session measured the facts and handed them over rather than making this session
re-derive them. `/plan-prompt` Step 3 emits exactly the two headings below —
see `.claude/skills/plan-prompt/SKILL.md`. Renaming them there breaks this
detection.

**Trigger — auto-detected, no opt-in keyword.** Trusted context is present when
the request contains a `## Exploration pointers` heading with **at least one
bullet** under it. That heading is the whole trigger:

- `## Exploration pointers` present with **zero** bullets → NOT trusted
  context. Orient normally.
- `## Resolved design decisions` present **without** `## Exploration pointers`
  → NOT trusted context. Orient normally; those decisions still bind as caller
  constraints, they just license no skip.

**What trusted context lets you skip.** For every item an exploration pointer
already covers, you MAY skip the Explore-agent spawn and the heavy direct reads
that would have produced it — transcribe the caller's pointer instead. You MAY
spend **1–2 targeted confirmations** (a `grep`, or a bounded read at a cited
`path:line`) on items the pointers leave genuinely open. Confirming a pointer is
cheap; re-running the exploration that produced it is the waste this removes.

**What it never lets you skip. Step 2.1 runs unconditionally — trusted context
covers WHAT THE CODE IS, never WHETHER THE WORK ALREADY EXISTS.** All three of
Step 2.1's signals fire on every run, trusted context or not. A caller's facts
describe the code's *shape*; they say nothing about whether this change has
since been merged or is sitting in an open PR, and Step 2.1 exists precisely
because a stale claim reads exactly like a fresh one — a fact asserted in
the request is a claim too.

This is a **verify-cheaply** channel, not a blind-trust channel. When a targeted
confirmation contradicts a pointer, the repo wins: correct the pointer and say
so in the plan before Step 3 consumes it.

Carry the caller's two sections forward as pointers; Step 2.5 seeds them into
the shared-context artifact.

## Step 2.1: Prior-art check — is this already done?

Before designing anything, verify the work **does not already exist** — merged to master or sitting in an open PR. Backlog/status markers go stale (a finding gets implemented but its marker is never flipped), so the marker Step 2 recorded is a *claim*, not ground truth — verify it against the repo THIS run. Repeatedly, a plan has been designed (and sometimes implemented) for work that was already merged, discoverable only by reading the code, never the marker. This gate spends a few cheap tool calls at the end of orientation to bail *before* the expensive Step 3 `plan-architect` spawn.

A fact asserted in the request is a claim too. A caller (a human, or an upstream session that drafted the task via `/plan-prompt`) can state "this isn't built yet," "the helper doesn't exist," or "no migration touches this table" in perfect good faith and be wrong: the assertion was true when it was written and the branch it described has since merged. Hold caller-asserted facts to the same standard as a status marker. The three signals below are cheap, and they are the only thing standing between a stale premise and a fully designed plan for work that already exists. This does **not** mean re-deriving everything the caller hands you; it means the *specific* claim "this work does not already exist" is never taken on trust, whatever its source.

Check **three signals**, strongest first — using the deliverable paths Step 2 surfaced:

1. **Deliverables already exist** (strongest) — `ls`/`grep` the concrete files/symbols the plan would create (new files present = done; the plan's would-be "Critical Files"). A behavior-changing edit to an existing file won't show here — fall through to signals 2–3.
2. **Already merged** — `git log --oneline --all | grep -iE '<slug|keywords|ADR-number>'` and `git log` the target paths for a commit that already made this change.
3. **Open (or recently-merged) PR** — `gh pr list --state open --search '<keywords>'` (add `--state merged` for a PR merged so recently master orientation may not reflect it). **This is the only signal that sees the open-PR half** — signals 1–2 are master-based and blind to unmerged work. Do not skip it: the user named the open-PR case explicitly.

**On a hit, classify (Opus judgment — do not delegate):**
- **Fully done** → **HALT**. Do not proceed to Step 2.5/3. Report the evidence (PR #, commit SHA, or existing file paths) and point at the real next step (e.g. "already merged as #N — the marker in `<doc>` is stale; flip it"). If Step 2 found a stale status marker, surface that it needs flipping.
- **Partially done** → re-scope the plan to the *remainder* only, and note in the Approach section what already exists and is therefore out of scope.
- **False positive** → proceed. Guard against crying wolf: surface a hit only on a **real** signal (a deliverable file exists, or a substantive PR-title/commit match), not any incidental keyword collision. A gate that fires on every plan gets ignored.

If all three signals are clean, record "prior-art check: clean" and proceed to Step 2.5.

## Step 2.5: Scope into PRs

Using the blast radius from Step 2, decide how many PRs the work requires. Default to **one** — most tasks are a single PR. Split into multiple only when a boundary is real:

- Independent concerns that can land and be reviewed separately
- A refactor/extraction that should merge before the feature that depends on it (stacked PRs)
- A change large enough that one reviewer cannot reasonably review it in a single sitting
- Distinct migrations or schema changes that each warrant their own rollback boundary
- **Implementation-context budget.** The plan's execution should fit one implementation session under the context dumb-zone. The degradation claim is unmeasured on Opus 5.5 as of 2026-10-08 (~/claude-plans/_reports/2026-10-08-opus-5-5-dumb-zone.md: no band reached the minimum n); the size cap stands on cost and reviewability. Size proxies: roughly **12+ numbered phases** or **~500+ plan lines** (2026-07-07: Opus runs that breached 150K peak context ran plans of median ~566 lines / ~20 phases). `bin/check-plan` gate `[C]` enforces the proxy mechanically at Step 5; when it fires, the fix is this split into stacked PR-sized plans. Padding and a reflexive `context-budget:` marker are both wrong answers.
- **Tier-boundary separability** — when the plan's Opus-tier phases (novel design, migration authoring, judgment sweeps) are *separable* from its mechanical phases, split at the tier boundary: a small Opus plan carrying only the judgment work, plus stacked `impl_model: sonnet` plan(s) carrying the bulk of the mechanical edits. This scopes the ~1.7×-per-token Opus run to the phases that genuinely need it instead of dragging every mechanical phase up to the top tier. Pairs with Step 4 gate 13 — when a plan is *mixed*, gate 13 points here (split at the tier boundary) rather than dropping the whole plan to Opus. The split only reaches plans whose judgment and mechanical phases are *cleanly separable*; the *interleaved* per-phase case (judgment and mechanical work entangled within the same phase) is out of scope here and tracked as L13 (per-phase routing) in `ibl5/docs/retrospective-class-registry.md`, which a whole-plan split cannot reach.

If the work is **one PR**, proceed to Step 3 once — **unless** this invocation arrived with trusted context (Step 2), in which case seed the shared-context artifact below **first**, then proceed to Step 3 once.

If the work is **multiple PRs**, list the PR-sized units in dependency order (what must merge first), then run Steps 3–5 **once per unit** — each producing its own plan file. Plans for stacked PRs should note their base branch in the implementation steps (`bin/wt-new --base <branch>`). Do not collapse the units back into one plan to "save effort" — the split is the deliverable.

### Seed the shared-context artifact

Two things trigger a seed: a **multi-PR split**, and a **trusted-context
invocation** (Step 2). They share one format and one lifecycle and differ only
in the artifact's path and in what fills the resolved-decisions section.

**Seed exactly ONE artifact per `/plan` invocation.** When both trigger — a
trusted-context invocation that Step 2.5 also splits into multiple PRs — the **multi-PR case wins**: seed the `<program>` artifact (not a `<slug>` one), and
fill its two sections from the caller's `## Exploration pointers` and
`## Resolved design decisions` instead of from your own Step 2 sweep. Rationale:
the multi-PR path already owns the per-unit plumbing (Step 3 item 2, Step 3.5's
append, Step 5's pointer line), so a second per-slug artifact would fragment the
context the split exists to share.

#### Seed from pre-resolved context (single-PR with trusted context)

**Trigger.** Step 2 detected trusted context (a `## Exploration pointers`
heading with ≥1 bullet in the request) **and** the work is one PR.

- **Path.** `$HOME/claude-plans/<slug>-shared-context.md`, where `<slug>` is this
  plan's own Step-5 slug. Pre-resolve `$HOME` and `<slug>` yourself and write a
  literal path, exactly as for the draft path in Step 3 item 5.
- **When.** After the scoping decision, before Step 3 — the `plan-architect`
  must be able to Read it.
- **Content = the caller's two sections, transcribed as pointers.** Copy
  `## Exploration pointers` and `## Resolved design decisions` out of
  the request verbatim into the artifact's matching sections. **Pointers only; never
  paste file bodies** (`/plan` is delegation-terminal: what moves forward
  is `path:line` + the one load-bearing fact, never file contents). If a Step-2
  targeted confirmation corrected a pointer, transcribe the corrected version
  and mark it.
- **Lifecycle — identical to the multi-PR case.** Append-only, and it **survives
  to implementation: do NOT delete it at Step 5** (unlike the per-unit draft,
  which IS `rm`'d there). A queued plan is implemented later — possibly days
  later, by automouse — and needs this context then. Cleanup is out of scope.

**Seeding is not a bypass.** Step 2.1 has already run by the time you reach
Step 2.5, and it runs **unconditionally** — trusted context or not. Never
reorder the seed ahead of Step 2.1, and never treat a seeded artifact as
evidence the work does not already exist.

When Step 2.5 splits the work into **multiple PRs**, the per-unit Steps 3–5 loop would otherwise make every `plan-architect` run — and every later implementation session — re-derive the same blast radius, patterns, and front-loaded decisions (N× the exploration spend) and re-inline that shared background into every plan body, inflating each toward gate `[C]`. That is a tax on the very decomposition gate `[C]` demands. Avoid it: **before the per-unit loop begins, SEED a shared-context artifact once**, and have each unit reference it (Step 3, Step 5) instead of restating it.

- **Path.** `$HOME/claude-plans/<program>-shared-context.md`, where `<program>` is the **base kebab slug** of the split task — the shared stem of the per-unit plan slugs (`<program>-1-<unit>`, `<program>-2-<unit>`, … from Step 5). Pre-resolve `$HOME` and `<program>` yourself and write a literal path, exactly as you do for the draft path in Step 3 item 5.
- **When (SEED once, before unit 1).** Write it **now** — after deciding the split, before running Steps 3–5 for unit 1 — because unit 1's `plan-architect` must be able to read it in Step 3. Seed it exactly **once** for the whole split, never per unit.
- **Content = Step 2 pointers, never file bodies.** Fill the exploration-pointers section from the `path:line` + one-load-bearing-fact-per-file pointers you already carry out of Step 2 (see Step 2 "Collect"). Copy **pointers only** — never paste file contents. The artifact is an index each unit re-reads *at*, not a cache of source; pasting bodies reintroduces the very bloat this removes.
- **Lifecycle (append-only; survives to implementation).** After seeding, the artifact is **append-only**: Step 3.5 appends each unit's resolved shared decisions as they freeze. It **survives until every split plan is implemented — do NOT delete it at Step 5.** This is unlike the per-unit draft at `$HOME/claude-plans/.drafts/<slug>.draft.md`, which IS `rm`'d at Step 5: queued split plans are implemented later (automouse, possibly days after planning), and each needs this shared context *then*. Cleanup is out of scope — the file persists (formalizing the Discord-pipeline precedent, whose shared-context file likewise persists).

Seed the file with this standard shape — a Purpose line, a how-to note, an exploration-pointers section filled from Step 2, and an **empty** resolved-decisions section that Step 3.5 fills.

**Add the `## Program acceptance` section ONLY when this split has a cross-component seam** — a unit whose output is consumed across a language, process, transport, or schema boundary by a later unit (TS -> PHP over HTTP, migration -> query layer, plist -> runner script). When it applies, **you** fill it here at Step 2.5 — before unit 1's `plan-architect` runs, because that architect is accountable to it (see `.claude/skills/plan/_architect-contract.md`, the multi-PR-split bullet). It is written **once** by you and never rewritten by a unit; units only flip their own row in its status table. **Omit the whole section** for a single-PR plan, and for a split whose units share no seam — an absent section means "no cross-component seam", which is a real and common answer, not an oversight.

The template:
```markdown
# Shared Planning Context — <program>

**Purpose:** Everything a fresh Claude Code instance needs to `/plan` — or implement — ONE PR of this work at full quality without re-exploring.

**How to use:** When planning or implementing a single unit of this work, read this file first for shared orientation, then confirm only what your unit touches at the pointers below. Treat these pointers as authoritative — do NOT re-run the whole Step-2 exploration; spend tool calls on targeted confirmation only (Step 3 point 2).

## Exploration pointers
<!-- Step 2 findings: `path:line` + the one load-bearing fact per file. Pointers only — NEVER file bodies. -->
- `path/to/file:NN` — <the single load-bearing fact for this file>

## Resolved design decisions
<!-- Append-only. For a multi-PR split: empty at seed time — each unit's Step 3.5 decisions are appended here as they freeze (see Step 3.5), and later units reference earlier units' frozen decisions. For a trusted-context seed: PRE-FILLED at seed time from the caller's `## Resolved design decisions`. Append-only holds either way — add, never rewrite. -->

## Program acceptance
<!-- Written ONCE at Step 2.5 by the seeder, before unit 1 begins. Units never rewrite this section — they only flip their own row in the status table below. Omit the whole section if this split has no cross-component seam. -->

**Acceptance test file:** `<path/to/acceptance-test.(ts|php|sh)>` — NEW (created by unit 1)

**Seams covered:**
- `<component A>` -> `<component B>` over `<HTTP | process | schema | filesystem>` — proven by: `<the observable fact, e.g. HTTP 200 + {"status":"success"} from the real server route>`

**Final unit:** `<unit-N slug>` — last unit in dependency order. This unit runs the acceptance test green.

**Per-unit seam status:**

| Unit | Makes a seam newly exercisable? | Acceptance test state after this unit |
|------|---------------------------------|---------------------------------------|
| `<unit-1 slug>` | no | landed, skipped |
| `<unit-N slug>` | yes | un-skipped, runs green |

**Acceptance rule:** Unit 1 creates the acceptance test file, so it is present in the tree from unit 1 onward. A unit that does NOT make a seam newly exercisable lands it **skipped** — `it.skip(...)` (vitest), `markTestSkipped('<seam> not wired until <unit-N slug>')` (PHPUnit), or an early `exit 0` printing a SKIP reason (shell) — and says so in prose in its Approach. The **Final unit** above MUST carry a `CLI-executable` Verification Matrix row that runs this exact file by path and asserts it passes — write the **full runnable command** in the row's location cell (`bash <path>`, `npx vitest run <path>`, `vendor/bin/phpunit <path>`), never the bare path, which `bin/check-plan` gate `[V]` rejects. Un-skipping the test is part of that unit's diff. Read the seam's real address (URL prefix, file path, CLI flag) from the serving code at implementation time — never from a prose table in this document.
```

For a single-PR trusted-context seed, substitute the plan's `<slug>` for `<program>` in the title, fill both sections from the caller's request, then run Steps 3-5 once.

Then run Steps 3–5 once per unit; each unit references this artifact instead of restating the shared background.

## Step 3: Design the plan

Run this step once per PR-sized unit identified in Step 2.5. Each run plans exactly one PR.

The Plan agent auto-loads CLAUDE.md, all always-loaded rules (agent-tiering, core-coding, etc.), and user memory. Do NOT re-inject any of these into the prompt — only supply what the agent cannot get on its own.

Launch a **single Plan agent** with a prompt containing ALL of the items listed below (1–5). **Choose the architect tier by these ORDERED precedence checks — evaluate top to bottom and take the FIRST that matches; an earlier check WINS over a later one:**

1. **`plan-architect-xhigh` (`model: fable`, `effort: high`).** Take this FIRST when Step 2 flagged **any** of: a security surface, a trust boundary (auth/authz-gated route), a destructive migration, a **gate removal or weakening**, or a **bootstrap hazard**. The last two key on **what the diff DOES to an enforcement mechanism**. The directory it touches does not matter:

   - **Gate removal or weakening** — the diff deletes, relaxes, or disables an **executable gate**. A gate is *executable* when it can reject: a hook that emits a deny, a `bin/check-*` failure condition, the tier/model logic under `bin/lib/plan-*`, or a `/post-plan` Phase 6.5 arming condition. The test is behavioural: **after the change, some input that the gate previously rejected now passes.** If no input changes verdict, it is not a removal or weakening — decision-procedure-preserving prose, an additive gate, a mechanism/plumbing refactor, and a non-gating skill step all fail that test and go to the default `plan-architect` (check 3), *whatever directory they sit in*. `.claude/skills`, `.claude/rules`, and `~/.claude/hooks` are where to **look** for an executable gate; they are not themselves the trigger.
   - **Bootstrap hazard** — the diff edits the arming, escalation, or auto-merge rule that governs **its own merge**: a Phase 6.5 arming condition, the `feat:` human-signoff floor, or `auto_merge` handling in `/post-plan`. Rewriting routing that will govern some *later, unrelated* plan is **not** a bootstrap hazard.

   For a mechanical second opinion before you decide, run `bin/plan-tier-hint --desc "<task description>"` (add `--files a,b,c` when Step 2 named the files). It prints `xhigh`, `default`, or `sonnet` and exits 0; `--explain` prints the matching reason. **The hint is advisory — this checklist is authoritative and you own the final call.**

   If any trigger applies, stop here: the high-stakes escalation OUTRANKS the recipe-backed downgrade below, so a plan that is both high-stakes and recipe-backed goes to xhigh, never to Sonnet.
2. **`plan-architect-sonnet` (`model: claude-sonnet-5-5`):** otherwise, when **no** check-1 trigger applies **AND** the source task/backlog entry is *recipe-backed*: it carries an explicit recipe **plus** a named existing pattern to copy (the marker-swap / mechanical-sweep class). Composing a plan from a pre-resolved recipe is mechanical composition, not novel design, so the cheaper Sonnet architect suffices. Pass `subagent_type: "plan-architect-sonnet"` (exactly; it must byte-match the def's `name:`).
3. **`plan-architect` (`model: opus`, `effort: high`)** — the default when neither check 1 nor check 2 matches.

For **every** tier, pass only the `subagent_type` and do **NOT** pass an inline `model` override — each def owns its own `model`/`effort`.

**Never delegate `/plan` itself.** This session owns Steps 1-5 and spawns exactly one `plan-architect` per PR-sized unit. A `general-purpose` or `claude` agent has an unbounded subtree, so an architect nested under one could re-enter the sectioned delivery and append to the same draft file as another author (`agent-tiering-detail.md` § Nested Sub-Agents).

1. **Task description** from the request. When the work was split in Step 2.5, scope this to the single PR being planned and state which PR it is and what it depends on
2. **Exploration results** from Step 2: file paths, code traces, existing patterns, and test coverage findings. Tell the agent these findings are authoritative and that it must not re-explore them. It spends tool calls only on targeted confirmations of anything the findings leave open, at most 2-3, then composes the plan. Every redundant `grep`, `Read`, or agent call lengthens the run and gives the idle timeout another window to land before the next Bash append. How you pass the findings depends on Step 2.5, and the instruction above applies on every path:
   - **Multi-PR split.** Pass the shared-context artifact path `$HOME/claude-plans/<program>-shared-context.md` instead of inlining, and tell the architect to Read it early (the same on-demand convention as item 4's contract Read). The artifact already holds the Step-2 pointers, so no per-unit prompt re-inlines them.
   - **Single PR, seeded artifact.** When Step 2.5 seeded an artifact from trusted context, pass `$HOME/claude-plans/<slug>-shared-context.md` (this plan's Step-5 slug, pre-resolved to a literal path) the same way. The caller's pointers then reach the architect without this session re-emitting them into a prompt.
   - **No artifact.** Inline the Step-2 exploration results. This is the default.
3. **The full `$VERIFICATION_RULE`** from Step 1, prefixed with: `Follow this rule exactly:`
4. **Full output contract.** Instruct the `plan-architect` to Read `.claude/skills/plan/_architect-contract.md` **exactly once, as its first action**, and to Read neither `_architect-contract-detail.md` nor `_plan-verification-detail.md` at any point (both are edit-time rationale for a maintainer; the architect defs carry the same bound under § Your read budget). That reference (created in Phase 1 of this plan) carries the complete "what the plan MUST produce" list, the conditional-section catalogue, the agent-tiering labels to apply per phase (Sonnet / Haiku / self), and the delegation-packet format. Do NOT inline any of it into the prompt. The architect Reads it into its own sub-context, so this bulk never enters the orchestrator's context.
5. **Draft output path.** The absolute path `$HOME/claude-plans/.drafts/<slug>.draft.md` (using this PR's Step-5 slug) that you seed and the agent appends its sections to across the sectioned delivery (see **Deliver the plan in sections** below). Pre-resolve `$HOME` and the slug yourself; pass a literal path. The `.drafts/` segment is part of the path. A draft at the plans-dir root (`$HOME/claude-plans/<slug>.draft.md`) is off-contract: Step 5's `rm -f` never deletes it, so it lingers beside the finished plan, and `bin/plan-now` adopts it only as a fallback when a headless run ends without a `PLAN_FILE:` line.

**Deliver the plan in sections (timeout durability).** The `plan-architect` agent has no `Write`/`Edit`/`NotebookEdit` tool — its only file-writing channel is Bash (a `cat >>` heredoc), and its only message channel is its streamed turn output, which a long generation can lose to an `API Error: Stream idle timeout`. A single-shot "compose the whole plan, then one big persist" puts the entire deliverable in one vulnerable window: a stall anywhere before that lone Bash call completes loses the whole plan. Instead, **YOU (the orchestrator) drive the architect across turns** and the draft is assembled incrementally, so a mid-stream stall costs at most one section — never the whole plan.

- **Setup.** Before turn 1, `mkdir -p "$HOME/claude-plans/.drafts"` and set `$DRAFT` to the **Draft output path** (item 5) verbatim, `.drafts/` segment included. Seed the draft, pass it to the architect, and run the Step 5 drift check against that one path.
- **Turn 1: outline.** This is the single remaining total-loss window. Tell the architect this is a *sectioned* delivery whose first turn returns ONLY a numbered list of the section titles the plan will contain. Ordered implementation phases come first, then every fixed/conditional section (`Critical Files`, `Architectural trade-offs`, `Verification Matrix`, `Executable Mutations`, plus `Out of Scope` / `Automouse Hold Justification` when warranted). Write one title per line, no bodies, no frontmatter, nothing else. Parse the numbered titles into an ordered list (you drive the loop from it) and persist the outline to the draft: write `# <plan title>` then the outline as an HTML comment (`<!-- PLAN OUTLINE (loop scaffold; dropped at Step 5): … -->`). If turn 1 returns empty, times out, or yields zero parseable titles: **retry once**, then **abort loudly** ("plan-architect produced no outline"). Do not proceed. Turn 1 is the only window where a stall loses everything, because nothing is on disk until the outline lands.
- **Turns 2..N.** One section per turn (loss bounded to one turn). For each parsed title, in order, `SendMessage` the architect to output ONLY that section and **Bash-append it to the draft itself.** A completed `cat >> "$DRAFT"` heredoc writing `## <exact title>` plus the body, durable the instant the tool call returns. Return a THIN one-line ack (`section "<title>" appended`). The architect must persist via Bash rather than return the body: a returned body is a long streamed message that reintroduces the exact stall vulnerability, whereas a completed append is transcript-durable and the trailing ack is tiny. **Enforce the turn boundary:** reject/re-prompt any turn whose ack reports more than one section or whose append dumped multiple sections. This orchestrator-side rejection is what makes "loss ≤ one section" a real bound rather than an aspiration. Two acks are **not** boundary violations and must not be re-prompted: a thin ack carrying the trailing phase-count suffix `[phases: B/S]` (`_architect-contract.md` requires it on every phase section), and an ack prefixed `PHASE_DRIFT:` (below). Do not read the growing draft between turns **except** on a `PHASE_DRIFT:` ack, where reading it is the point. **Keep the turn prompts thin.** A title and nothing else. Re-pasting contract text, re-stating the output format, or telling the architect to "re-check the contract" invites the ~7K-token re-Read the architect defs forbid, and a thin prompt is what makes that bound cheap to honor. If an ack reports Reading `_architect-contract.md` a second time, either `*-detail.md` file, or `$DRAFT` itself, re-prompt with the bare title and a one-line reminder of § Your read budget. Those Reads drive an architect into a mid-plan compaction, which loses the contract and the exploration findings together.
- **`PHASE_DRIFT:` ack — stop the loop and remediate before the next turn.** `_architect-contract.md` makes the architect count its own body phases against the scaffold after every append and, when the body count exceeds the scaffold count, prefix its ack `PHASE_DRIFT: N body phases but scaffold declares M` and stop. On receiving one, do **not** send the next phase title. Run the remediation procedure in Step 5 § Pre-finalize drift check against `$DRAFT` immediately, confirm `bin/check-plan --draft "$DRAFT"` exits 0, then resume the loop at the **next closing section** (Approach / Verification Matrix / …), never at another phase title. Remediating at the moment of drift is strictly better than waiting for Step 5: the excess section is the last thing in the file, so deleting it cannot touch a closing section that does not exist yet, and every later turn appends onto a correct draft. The scaffold is binding — never re-issue a revised outline to absorb the extra phase; work that no longer has a heading belongs in the last phase's body as a `**Scope note:**`.
- **Loss bound.** By construction every completed section is on disk before the next turn begins, so a mid-stream stall loses at most the single in-flight section — re-drivable by re-sending that one title. Only turn 1 can lose the whole plan, and it is guarded by retry-once-then-abort above.
- **`bin/check-plan` stays yours — the architect must never run it on the draft.** `.claude/skills/plan/_architect-contract.md` prohibits it on the architect's side; enforce it here. Step 5 is where the **full** gate runs, on the assembled plan *with* the line-1 frontmatter you prepend. The one exception is **yours, not the architect's**: `bin/check-plan --draft "$DRAFT"` (Step 5 § Pre-finalize drift check, and after any `PHASE_DRIFT:` remediation). Draft mode runs gate `[D]` alone — phase-count drift — precisely because it is the only gate a frontmatter-less draft can meaningfully answer. The prohibition on the architect side is unchanged: it never invokes `bin/check-plan` in any mode. A draft cannot pass gates `[13]` (`impl_model` consistency), `[H]` (hold justification), `[T]` (tier binding) or `[S]` (Sonnet-recipe completeness): all four read the frontmatter at `NR==1`, and a draft's first line is always a `## ` section title, never the `---` block the architect has no channel to write. A draft-time run therefore reports only violations the architect is **structurally unable to fix**, and chasing them is an unbounded retry loop that burns the run before the plan is finished. Say so explicitly in the turn prompts, and if a turn's ack mentions running `bin/check-plan` or reports gate failures, re-prompt that turn with the section title alone — do not let the architect try to "fix" them. A `PHASE_DRIFT:` ack is **not** such a case: the architect produces it from its own `grep`/`awk` count, never from `bin/check-plan`, and it routes to remediation (Step 3 § Turns 2..N), not to a re-prompt.

(`API_FORCE_IDLE_TIMEOUT=0` in `~/.claude/settings.json` separately disables the 5-minute idle-gap abort; this sectioning loop defends even if that env var is unset or the run hits the total `API_TIMEOUT_MS` ceiling.) The orchestrator reads the assembled draft only at the end (Step 5).

## Step 3.5: Front-load design decisions

The Plan agent runs in a sub-context and **cannot ask the user**. For each `needs-user-input` fork it flagged in its **Design decisions** section, you (the orchestrator) surface it now with `AskUserQuestion` — one question, 2–4 concrete options, recommendation first; use the `preview` field to show a proposed module layout or code shape for structural choices. Record each answer + a one-line rationale into the plan's **Approach** section as a fixed constraint, then patch the affected implementation steps so the decision is fully specified. **For a multi-PR split:** additionally **append** each resolved decision that binds more than the current unit (whether self-resolved by the architect or answered here via `AskUserQuestion`) to the shared-context artifact's `## Resolved design decisions` section — one entry per decision: the decision plus its one-line rationale. Later units then Read those frozen decisions from the artifact instead of re-litigating them, and cannot silently diverge. Keep this **append-only and incremental** — append as each unit's Step 3.5 resolves; do NOT front-run the whole split into an up-front batch-decision pass over all units (that heavier structure is the Discord precedent's shape, not the minimal model this formalizes).
Single-PR runs, including one that seeded an artifact from trusted context (Step 2.5), do **NOT** append here. The multi-PR qualifier above is exact, not shorthand: the append exists so *later units* can read frozen decisions, and a single-PR plan has no later unit. Its artifact's `## Resolved design decisions` was already filled at seed time from the request; re-appending the same decisions would double-write the seed and break append-only discipline. Record the resolutions in the plan's **Approach** section only.

A recorded decision is **no longer a fork**: it does not trip Step 4 gate 7 (unresolved decision) and does not, by itself, force `auto_merge: false` (gate 14) — the human judgment already happened at plan-time.

Do **not** ask when: the fork is conventional (let the Plan agent's self-resolution stand), the judgment is `irreducible` (distributed per-site/per-test, or data-blocked — that legitimately keeps the plan supervised), or asking is ceremony. `AskUserQuestion` is for forks where the answer actually changes the implementation.

## Step 4: Validate the matrix

Read `.claude/skills/plan/_validate-and-write.md` now, once. It holds every Step 4 gate (1-18) in its original wording and order, and the full Step 5 procedure. Read it only after the architect's last section has returned. Do not Read it at Step 1, Step 2, or Step 3. Do not Read it again at Step 5; you already hold it.

## Step 4.5: Challenge the auto-merge hold

If you did **not** set `auto_merge: false` (Step 4 gate 14), skip the auto-merge challenge below — there is nothing to challenge there. **The pre-prod-exception challenge at the end of this step is separate and is NOT skipped**: it runs whenever the plan carries a `pre-prod-exception:` marker, armed or held, because a coverage exception and a merge hold are independent verdicts.

When you *are* about to hold the merge, run an adversarial second pass on that verdict yourself (Opus — irreducibility is Opus-tier judgment, never delegated). The forcing question, lifted from the manual re-prompt that breaks false holds in practice, is: **"What would I add to this plan to make it safe for automouse to merge unattended?"** Apply it according to the hold's *type* — the standard differs, and conflating them regresses safety in one direction or autonomy in the other.

An intrinsic claim on a merge-adjacent change (a workflow, a `bin/check-*` script, `bin/lib/pr-*`, the post-plan harness, or `/post-plan` prose) must name which of gate 14(e)'s two tests it meets: gate weakening (a previously rejected input now passes) or bootstrap hazard (the diff rewrites the rule that governs its own merge). A claim that names neither is a reducible CI/config/tooling hold and goes through the reducible path below.

**Reducible holds — dissolve or prove infeasible.** The hold is *reducible* when its only basis is one of:
- a **verification gap** (gate 15) — correctness is silent, integration-only, observable-only-in-prod, or "I can't tell mechanically whether it works";
- a **reversible schema tightening** (gate 14c-reducible) — a `varchar` narrowing, or a length / `NOT NULL` add;
- a **dischargeable security surface** (gate 14b-reducible) — a state-free extraction, a pure-function move, or a validator that never sees actor identity, where the safety property is a *structural* invariant a test can assert;
- a **CI/config/tooling** change you "can't verify."

You may **not** keep the hold on that basis alone. Resolve it one of two ways — **both are passing outcomes**, this is not a one-way push to arm:
- (a) **Dissolve it.** Add the mechanization phase — a lever-3 self-check (Verification-gap mechanization), the Schema-safety guard, or the Security-surface mechanization assertion — and its matrix rows, then **remove `auto_merge: false`**. The check now does the watching; the hold is gone.
- (b) **Confirm it, with cause.** Keep the hold only if you can state concretely *why no mechanical check can be built* for this specific gap. "It would be effort" is not a reason; "the only signal is subjective human perception of X" or "the asserting artifact does not exist until prod" is.

**Intrinsic holds — name the category and stand.** Do **not** apply pressure to these; an intrinsic hold *should* wait for a human, and challenging it into arming is the safety regression this step exists to prevent. A hold stands as-is when it rests on:
- **subjective UI/UX taste** (gate 14a/d) — a genuine look-and-feel / flow judgment;
- an **irreducible security surface** (gate 14b) — a check that genuinely carries actor identity (`$loggedInTeamID`, session, current user), a new POST/form endpoint, a new SQL construction site, or new user-facing output rendering. A surface is intrinsic **unless** it matches a named dischargeable shape *and* the plan builds the assertion; ambiguity resolves to intrinsic;
- an **irreversibly-destructive or design-data-blocked** change (gate 14c) — DROP / lossy backfill / in-place data mutation, a column-rename sweep, an FK-ordering migration, or a migration whose *target shape* depends on prod data unreadable from CI;
- a **self-gating / bootstrap hazard** (gate 14e), and only when the change meets one of the two Step 3 check-1 tests: it weakens an executable gate (some input the gate previously rejected now passes) or it rewrites the arming, escalation, or auto-merge rule that governs its own merge. No self-run check can be trusted for that shape, because the thing under change *is* the verifier; a human merges it under the old, known-good floor, and lever 3 cannot mechanize a check whose own validity the PR is rewriting. Making a gate stricter is not weakening. Touching a workflow, `bin/check-*`, `bin/lib/pr-*`, `tools/postplan-harness/`, or `/post-plan` prose on its own is not a trigger either; such a change takes the gate-14 no-hold default or, when its correctness is merely unverified, the reducible path above;
- a **golden-snapshot change** or a **recorded-and-unresolved design fork** (gate 14f / 14g): the first because a regenerated baseline passes its own check by construction, the second because the run has already said it could not decide.

**Record the outcome.** Every plan that *keeps* `auto_merge: false` must carry an `## Automouse Hold Justification` section (its presence is enforced by `bin/check-plan`; validity is your judgment). State in it: the hold **category** (reducible-confirmed or intrinsic) and which gate-14/15 trigger it rests on, plus one line — for a reducible-confirmed hold, *why no mechanical check is buildable*; for an intrinsic hold, *why the judgment is irreducible*. A reducible hold you **dissolved** carries no section, because the plan no longer holds.

**A hold justification carries a decision.** The section names the one judgment the human renders at the merge button; every *observable* claim ("every reference resolves", "the counts agree", "no logic changed outside the escaping hunks") is a Verification Matrix row and belongs nowhere else. Write it in this shape: a `**Category:**` line, a `**Decision:**` line (wording rules below), a `**Discharged by matrix rows:**` line pointing at the rows that already assert what a reader might otherwise expect the human to re-run, and the one line of *why* this step already requires. **The test to apply, per sentence:** *could an agent with the repo, the diff, and a shell settle this?* If yes it is a matrix row, and deleting it from the hold section costs nothing; write the row if it is missing, then cite it. If the only settling instrument is the human's own judgment (taste, risk appetite, willingness to accept an irreversible write) that is the decision, and it stays. `bin/check-plan` gate `[H]` fails a hold section that carries an ask-shaped sentence outside a `**Decision:**` block (the exemption runs from that line to the next blank line, so a decision may wrap but a later paragraph cannot hide inside it). It is a narrow pattern check over `bin/lib/hold-check-patterns.txt`, calibrated to stay silent on perception and taste prose. It catches the "here is a list of things for you to confirm" shape and no other phrasing, so passing it is the floor and the standard sits above it. **Decision wording.** The Decision line is the only hold text the PR body shows, and the person pressing merge reads it. Write one plain yes/no sentence of at most 40 words, for example "Merge if you're OK with about 7% more PRs being held until their body declares scope." Do not open it with "The human accepts that ...", and do not phrase it as an instruction to go run something. Leave a blank line after it. Gate `[H]` enforces the cap, the opener, and `bin/check-prose` tells on the Decision body.

**Second challenge.** A claimed `pre-prod-exception:` marker. Run this whenever the plan carries one (Step 4 gate 16), *independently* of the auto-merge verdict above. Same adversarial posture, same **reducible / intrinsic** vocabulary, and the forcing question is slice-level. Do not ask *"is this testable pre-prod?"*, which invites a yes/no about the whole feature and gets answered "no". Ask:

> **"Which slice of this is intrinsic — and what am I building for the rest?"**

Apply the reducible/intrinsic split from gate 16. The default answer for a `bin/<name>-tick`-class script is fixed: scheduling and the network/credential boundary are intrinsic; everything else is reducible, and the exception does not cover it. Gate 16's resolution list has the moves.

**A plan claiming the exception over a whole artifact is claiming too much. Reject it** and send it back to build the reducible slice's exercise path. "The daemon can't run on my laptop" is a claim about *registration*, not about the code the daemon runs.

**The failure this challenge exists to prevent, concretely:** an exception claimed over the integration's **logic**, licensed by a Verification Matrix that reads fully green because its only coverage of the live boundary is a stub (the `bin/lib/bug-pipeline-test-stubs.sh` shape). The stub proves the caller called the stub. Nothing then verifies the real service does the thing — and the gap ships behind a green matrix *plus* a recorded, plausible-sounding exception. That pairing is worse than an openly uncovered plan, because it reads as covered and no later gate re-opens it.

**Disposal.** An exception that survives the challenge is recorded as `pre-prod-exception: <reason>` naming **which slice** is intrinsic — scheduling, reachability, or credential — never the artifact as a whole and never the plan as a whole, plus its one-line entry in `## Pre-prod Exception Justification`. In the same plan, the reducible slice still gets its pre-prod exercise path **built**, with its own matrix rows. An exception is never a whole-plan escape, and it is never satisfied by deleting the row it was claimed against.

## Step 5: Write the plan file

Follow `_validate-and-write.md` § Step 5, which you Read at Step 4. Its first action is the pre-finalize drift check. Run that before any other Step 5 action, while the draft still carries the scaffold comment.

## Step 5.5: Auto-queue queue-safe plans

A plan is **queue-safe** the moment **both** `bin/check-plan` and `bin/check-plan-staleness` (Step 5) exit 0. Those gates already enforce no unresolved decisions, no `DECIDE`/`TBD`/`subject to…` tokens, resolved decision-triggers, and resolved reuse targets, so a passing plan is fully specified for unattended automouse execution. Queue-safety is **independent of `auto_merge`**: a plan held for human merge (`auto_merge: false`) is still safe to *implement* autonomously. Only its merge waits (Phase 6.5 condition (7)).

For every plan that passed `bin/check-plan` in Step 5, decide its disposition by this precedence (the default is **queue**):

1. **Explicit token in the request** wins outright: `--implement` (or "implement now") → do NOT queue; leave the plan on disk and report it ready to implement. `--queue` → queue.
2. **Else, the Step 5 staleness sweep never cleared** (`bin/check-plan-staleness` still exits 1 after the 3-attempt cap, so the plan carries `auto_merge: false` and an `## Automouse Hold Justification` naming the unresolved `STALE:` token) → do NOT queue. A dead path token spends all three of automouse's nightly attempts before a human sees it; leaving the plan on disk costs one review instead.
3. **Else, default: auto-queue.** Run `bin/automouse/queue <slug>` for the plan.

When the work was split into multiple PRs (Step 2.5), queue **every** queue-safe unit, running `bin/automouse/queue <slug>` once per plan in dependency order (the order they must merge). A plan that did not pass `bin/check-plan` **or** `bin/check-plan-staleness` is never queued; fix it first.

Report which plans were queued (and which were left for in-session implementation) in Step 6.

**Under `CLAUDE_HEADLESS=1` (a `bin/plan-now` run), skip this step.** Never run `bin/automouse/queue` yourself. By default (`--queue`), the job queues the plan after its own `bin/check-plan` verdict. Under `--implement`, the plan stays on disk and nothing is queued.

## Step 6: Report

Tell the user the plan file path, and every path when the work was split into multiple PRs. Then give each plan's disposition (auto-queued for automouse by default, or left for in-session implementation under `--implement`) with the resulting `bin/automouse/queue` state, and a one-line matrix summary per plan. Cover each of these as well, and say so in one line when it did not arise: a flagged security surface and how it is defended; an `auto_merge: false` hold and its reason (Step 4 gate 14); a recorded `pre-prod-exception:` with its intrinsic slice and what was built for the reducible slice (Step 4 gate 16); a mechanized post-merge follow-up and what it runs; the PR order for a multi-PR split; and whether each plan is ready for implementation or has open questions.

**Headless final line.** Under `CLAUDE_HEADLESS=1`, the report's last line is `PLAN_FILE: <absolute path>`, on its own with nothing after it. Report the disposition as "`bin/plan-now` queues it" by default, since this run did not queue it; under `--implement`, say the plan stays on disk for a human to review and nothing was queued. A report that ends on queue state ("Queued at position 11", "Automouse will pick it up") with no `PLAN_FILE:` line is the known failure. `bin/plan-now` then reports `RESULT degraded (recovered)` or `RESULT unconfirmed`.
