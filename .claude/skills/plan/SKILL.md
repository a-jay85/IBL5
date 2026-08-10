---
name: plan
description: "Plan an implementation task: enforces a verification matrix, directs code reuse, flags security surfaces, and requires negative-path tests so plans drive clean, secure, well-tested implementations."
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
last_verified: 2026-08-10
---

# /plan — Implementation Planning with Verification Matrix

You are planning an implementation task. The user's request follows this skill's instructions as `$ARGUMENTS`.

**Do NOT write or edit any code files.** This skill produces a plan only. The output is one plan file per PR.

**One plan = one PR.** A single plan file must be implementable and mergeable as exactly one pull request. If the work naturally spans multiple PRs (independent concerns, separate review surfaces, a refactor that should land before the feature that uses it, or a change too large to review in one sitting), do NOT bundle them. Split the work into PR-sized units and produce a separate, fully self-contained plan — its own implementation steps and its own Verification Matrix — for each one. The user should never have to ask for this split.

**Maximize automouse autonomy.** A plan is most valuable when automouse can implement *and merge* it with no human in the loop. Three levers turn a would-be-supervised plan autonomous — apply them by default instead of reaching for `auto_merge: false`:

1. **Pin, don't supervise.** Uncovered code is not a reason to hold the merge — it's a reason to add a **Phase-1 characterization pin** (Step 4 gate 11). A behavior-preserving refactor with a pin is green-green; the pin *is* the coverage.
2. **Decide at plan-time, not merge-time.** A single discrete design fork doesn't have to wait for a human at merge — surface it to the user *now* (Step 3.5) and record the answer in the plan; automouse then executes a fully-specified decision.
3. **Mechanize the check, don't watch it.** When the *reason* to want a human at merge is "we can't tell mechanically whether it works" — a silent or integration-only failure mode, an "observe in prod" property, or a reflex to have a human confirm it behaves — that is a missing-verification problem, not irreducible judgment. Design the self-asserting check as an implementation phase: a required CI job that fails *loudly* on the bad outcome, a loud-failure signal replacing a silent fallback, an independent invariant assertion on the real artifact, or a shadow/burn-in rollout that enforces the invariant before flipping. This extends to a **reversible schema tightening** (a `varchar` narrowing, a length / `NOT NULL` constraint): an apply-time fail-closed guard that runs on the *target* DB self-gates the migration on prod at deploy, so "needs prod data unreachable from CI" becomes a guard to build — which *releases* the gate-14(c) hold once present and test-proven — rather than a permanent hold (see Schema-safety mechanization). It extends once more to a **dischargeable security surface** — a state-free extraction, a pure-function move, or a validator that never sees actor identity: name the structural invariant (the extracted collaborator holds no `$db`, no session/actor identity, no `$loggedInTeamID`) and assert it in a required-blocking test, and the gate-14(b) hold is *released* — so "a security-adjacent file was touched" stops being an automatic hold (see Security-surface mechanization). An *irreducible* surface — a check that genuinely carries actor identity, a new POST/form endpoint, a new SQL construction site, new user-facing output rendering — has no release path and stays held. Let auto-merge arm once the check exists; reach for `auto_merge: false` only after the check genuinely can't be built (see Verification-gap mechanization, Step 4 gate 15).

Fall back to `auto_merge: false` only when the judgment is **irreducible**: *distributed* across the implementation (per-site security verdicts, per-test correctness on untested code) or *data-blocked* (needs production data unreachable from CI). "A decision exists" is not a hold reason; "the decision can't be made once, up-front" is; and "I can't verify it works without watching it" is a verification gap to close with lever 3, not a hold reason. The flag holds only the **merge** — post-plan still runs and opens the PR for review either way.

## Step 1: Verification rule

Read `.claude/review-shared/_plan-verification.md` and use its full content as `$VERIFICATION_RULE` for injection into the Plan agent prompt in Step 3. Do not summarize or paraphrase the rule.

## Step 2: Orient on the codebase

**Prefer direct tool calls over Explore agents.** Most orientation can be done without spawning an agent:

1. Read `.claude/rules/codebase-map.md` to identify affected modules and their file locations
2. Run targeted `grep`/`find` via Bash for specific symbols, callers, or file paths
3. Read key files directly (migrations, interfaces, existing tests)

**Spawn an Explore agent when EITHER** trigger fires — read volume is a trigger in its own right, not only residual uncertainty:

1. **Open questions** — direct lookups leave something genuinely unanswered.
2. **Byte isolation** — answering would mean reading large source files (roughly: any file over ~300 lines, or a combined read budget past ~600 lines) into *this* session. `/plan` is **delegation-terminal**: the deliverable is produced by the `plan-architect` sub-agent in its own fresh window, so the orchestrator never needs those files resident. Even with zero open questions, pull the heavy reads into an Explore agent (or defer them to the plan-architect) so the bytes land in a disposable window, not the orchestrator's — three consecutive auto-compactions before any planning work is the failure this prevents. What you carry forward is **pointers** (`path:line` + the one load-bearing fact per file), never the file contents; the plan-architect re-reads at those pointers in its own window (targeted confirmation, not re-exploration — see Step 3 point 2).

Tier per `.claude/rules/agent-tiering.md`:

- Single-module change → 0 agents (direct tools suffice) or 1 Haiku for enumeration
- Spans 2+ modules → up to 2 agents (Explore for cross-module traces — already pinned Sonnet 4.6, use `subagent_type: "Explore"` omit `model`; Haiku for file/grep lookups)
- Never spawn 3 agents

Provide each agent a single concrete question, pre-resolved paths, and a response cap (under 150 lines). An agent spawned purely for byte isolation must return **distilled pointers** (`path:line` + the load-bearing fact), not pasted file bodies — pasting the contents back defeats the isolation.

Collect: file paths, existing patterns, dependencies, blast radius, existing test coverage for affected code, **the specific existing helpers/services/repositories the implementation should reuse** (name the exact methods — e.g. `SalaryCapRepository::getTeamTotalSalary()` — so the plan directs reuse instead of leaving the impl agent to rediscover them), and **which security surfaces the change touches** (SQL queries, POST/form endpoints, auth/authz-gated routes, user-facing output rendering). If none of these surfaces are touched, record that explicitly. Also record **whether the task resolves a finding tracked in a status/tracking doc** (e.g. `ibl5/docs/backlog/maintenance-backlog.md`, an a11y/security backlog, a roadmap with per-item status markers) — if so, the doc path, the finding id, and its current status marker — so Step 3 can scope the status-flip edit into the **same** PR. **Read backlogs through `bin/backlog-open <path>`** rather than reading the whole file: it emits only open (⬜ / ◑ / 📋) rows and their detail bodies, byte-identically to source, so a quoted table row is still a valid edit anchor. Output is stdout, so for `maintenance-backlog.md` (~93 KB filtered — over the Bash output cap) redirect and Read instead of capturing inline; the seven smaller backlogs capture fine inline. If the filter errors, or the doc is not one of the 8 LIVE backlogs, fall back to reading the file directly. Separately, record **whether the work leaves a follow-up that can only run *after* the PR merges** — something the merge event itself unblocks: a stale memory/doc/plan that stays valid until the change lands (e.g. a `MEMORY.md` "delete this entry when #N merges" pointer), a temporary compat shim that can be removed once its consumer deploys, a feature flag to retire post-rollout. Note each one so Step 3 can **mechanize** it as a merge-triggered watcher rather than leaving it to the user's memory. When Step 2.5 seeds a shared-context artifact — because the work splits into multiple PRs **or** because this invocation arrived with trusted context (see below) — these pointers (`path:line` + the one load-bearing fact per file) are the **source** of the artifact's exploration-pointers section. Step 2.5 persists them **once** at SEED so nothing re-derives them, and Step 3 hands the `plan-architect` the artifact rather than re-inlining them. Keep them as pointers here (never file bodies) so they transcribe straight into the artifact.

```bash
bin/backlog-open ibl5/docs/backlog/maintenance-backlog.md > /tmp/backlog-open.md   # large: redirect, then Read
bin/backlog-open ibl5/docs/backlog/loop-engineering-backlog.md                     # small: inline is fine
```

### Trusted context — when the caller already did the exploration

Some `/plan` invocations arrive with the exploration **already done**: a prior
session measured the facts and handed them over rather than making this session
re-derive them. `/plan-prompt` Step 3 emits exactly the two headings below —
see `.claude/skills/plan-prompt/SKILL.md`. Renaming them there breaks this
detection.

**Trigger — auto-detected, no opt-in keyword.** Trusted context is present when
`$ARGUMENTS` contains a `## Exploration pointers` heading with **at least one
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
`$ARGUMENTS` is a claim too.

This is a **verify-cheaply** channel, not a blind-trust channel. When a targeted
confirmation contradicts a pointer, the repo wins: correct the pointer and say
so in the plan before Step 3 consumes it.

Carry the caller's two sections forward as pointers; Step 2.5 seeds them into
the shared-context artifact.

## Step 2.1: Prior-art check — is this already done?

Before designing anything, verify the work **does not already exist** — merged to master or sitting in an open PR. Backlog/status markers go stale (a finding gets implemented but its marker is never flipped), so the marker Step 2 recorded is a *claim*, not ground truth — verify it against the repo THIS run. Repeatedly, a plan has been designed (and sometimes implemented) for work that was already merged, discoverable only by reading the code, never the marker. This gate spends a few cheap tool calls at the end of orientation to bail *before* the expensive Step 3 `plan-architect` spawn.

**A fact asserted in `$ARGUMENTS` is a claim too.** A caller — a human, or an upstream session that drafted the task via `/plan-prompt` — can state "this isn't built yet," "the helper doesn't exist," or "no migration touches this table" in perfect good faith and be wrong: the assertion was true when it was written and the branch it described has since merged. Hold caller-asserted facts to the same standard as a status marker — the three signals below are cheap, and they are the only thing standing between a stale premise and a fully designed plan for work that already exists. This does **not** mean re-deriving everything the caller hands you; it means the *specific* claim "this work does not already exist" is never taken on trust, whatever its source.

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
- **Implementation-context budget** — the plan's execution would not fit one implementation session comfortably under the ~100–150K context dumb-zone, where reasoning measurably degrades. Size proxies: roughly **12+ numbered phases**, **~500+ plan lines**, or **2+ inline bulk sweeps** without delegation packets. (Measured 2026-07-07 on the automouse corpus: 60% of Opus implementation runs breached 150K peak context, and the breaching runs executed plans of median ~566 lines / ~20 phases; the runs that stayed under ran ~100-line plans.) `bin/check-plan` gate `[C]` enforces the proxy mechanically at Step 5 — when it fires, the fix is THIS split into stacked PR-sized plans, never padding or a reflexive `context-budget:` marker.
- **Tier-boundary separability** — when the plan's Opus-tier phases (novel design, migration authoring, judgment sweeps) are *separable* from its mechanical phases, split at the tier boundary: a small Opus plan carrying only the judgment work, plus stacked `impl_model: sonnet` plan(s) carrying the bulk of the mechanical edits. This scopes the ~1.7×-per-token Opus run to the phases that genuinely need it instead of dragging every mechanical phase up to the top tier. Pairs with Step 4 gate 13 — when a plan is *mixed*, gate 13 points here (split at the tier boundary) rather than dropping the whole plan to Opus. The split only reaches plans whose judgment and mechanical phases are *cleanly separable*; the *interleaved* per-phase case (judgment and mechanical work entangled within the same phase) is out of scope here and tracked as L13 (per-phase routing) in `ibl5/docs/backlog/loop-engineering-backlog.md`, which a whole-plan split cannot reach.

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
heading with ≥1 bullet in `$ARGUMENTS`) **and** the work is one PR.

- **Path.** `$HOME/claude-plans/<slug>-shared-context.md`, where `<slug>` is this
  plan's own Step-5 slug. Pre-resolve `$HOME` and `<slug>` yourself and write a
  literal path, exactly as for the draft path in Step 3 item 5.
- **When.** After the scoping decision, before Step 3 — the `plan-architect`
  must be able to Read it.
- **Content = the caller's two sections, transcribed as pointers.** Copy
  `## Exploration pointers` and `## Resolved design decisions` out of
  `$ARGUMENTS` verbatim into the artifact's matching sections. **Pointers only —
  never paste file bodies** (`/plan` is delegation-terminal: what moves forward
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

For a single-PR trusted-context seed, substitute the plan's `<slug>` for `<program>` in the title, fill both sections from the caller's `$ARGUMENTS`, then run Steps 3–5 once.

Then run Steps 3–5 once per unit; each unit references this artifact instead of restating the shared background.

## Step 3: Design the plan

Run this step once per PR-sized unit identified in Step 2.5. Each run plans exactly one PR.

The Plan agent auto-loads CLAUDE.md, all always-loaded rules (agent-tiering, core-coding, etc.), and user memory. Do NOT re-inject any of these into the prompt — only supply what the agent cannot get on its own.

Launch a **single Plan agent** with a prompt containing ALL of the items listed below (1–5). **Choose the architect tier by these ORDERED precedence checks — evaluate top to bottom and take the FIRST that matches; an earlier check WINS over a later one:**

1. **`plan-architect-xhigh` (`effort: xhigh`)** — take this FIRST when Step 2 flagged **any** of: a security surface, a trust boundary (auth/authz-gated route), a destructive migration, a **gate removal or weakening** in the ship-pipeline surface (`.claude/skills`, `.claude/rules`, or `~/.claude/hooks`) — a change that deletes, relaxes, or disables an enforcement mechanism (a hook deny, a `bin/check-plan` gate condition, a plan-gate-edit check, a `/post-plan` Phase 6.5 arming condition) — or a **bootstrap hazard** (the PR rewrites the arming, escalation, or auto-merge rules governing its own implementation). Additive changes (new gates), prose edits that preserve the decision procedure, mechanism/plumbing changes, and non-gating skill steps go to the default `plan-architect` (check 3). If any applies, stop here: the high-stakes escalation OUTRANKS the recipe-backed downgrade below, so a plan that is both high-stakes and recipe-backed goes to xhigh, never to Sonnet.
2. **`plan-architect-sonnet` (`model: claude-sonnet-4-6`)** — otherwise, when **no** check-1 trigger applies **AND** the source task/backlog entry is *recipe-backed*: it carries an explicit recipe **plus** a named existing pattern to copy (the marker-swap / mechanical-sweep class). Composing a plan from a pre-resolved recipe is mechanical composition, not novel design, so the cheaper Sonnet architect suffices. Pass `subagent_type: "plan-architect-sonnet"` (exactly — it must byte-match the def's `name:`).
3. **`plan-architect` (`model: opus`, `effort: high`)** — the default when neither check 1 nor check 2 matches.

For **every** tier, pass only the `subagent_type` and do **NOT** pass an inline `model` override — each def owns its own `model`/`effort`.

**Worked example (the precedence in action):** consider a plan that removes the always-on byte-budget cap from `bin/check-plan` — gate `[C]`'s unconditional ">= 500 lines OR >= 12 phases" rejection — and replaces it with an opt-in marker, copying the marker-gated pattern an existing gate in the same script already uses. That plan is *recipe-backed*: it carries an explicit recipe plus a named existing pattern to copy, which by check 2 alone would route it to `plan-architect-sonnet`. But it **deletes an enforcement mechanism** in the ship-pipeline surface, so **check 1 fires first** and it goes to `plan-architect-xhigh`, **not** Sonnet. That ordering is the load-bearing correctness property: recipe-backed must never downgrade a high-stakes plan. Contrast the plan that ADDED this Sonnet tier (the T11+T12 batch): equally recipe-backed, but purely additive — it removes no gate and rewrites no rule governing its own merge — so under clause 4 it routes to check 2, which is the intended narrowing.

**Run this step inline — never delegate `/plan` itself.** The orchestrating session owns Steps 1–5 directly and spawns exactly **one** `plan-architect` per PR-sized unit. Do NOT hand the whole `/plan` invocation to a `general-purpose`/`claude` sub-agent (or fan it out across several), and do NOT have any such agent fire `/plan` on your behalf. Those agent types carry `Tools: *` — they *can* spawn further agents, so delegating `/plan` to them produces a `general-purpose → plan-architect` nest, exactly the multi-level `plan-architect` tree the flat-fan-out rule forbids (`agent-tiering-detail.md` § Nested Sub-Agents). `plan-architect`/`Plan`/`Explore` cannot cause this themselves — they lack the `Agent` tool — so the only way the nest appears is an orchestrator delegating `/plan` outward. Keep planning one level deep: this session → one `plan-architect`.

1. **Task description** from `$ARGUMENTS` — when the work was split in Step 2.5, scope this to the single PR being planned and state which PR it is and what it depends on
2. **Exploration results** from Step 2 — file paths, code traces, existing patterns, test coverage findings. **Tell the agent these findings are authoritative and that it must NOT re-explore them.** The agent already ran with `effort: xhigh`, so its instinct is to re-derive everything from scratch — but you've supplied the orientation, and every redundant `grep`/`Read`/agent call extends the run and raises the stall risk (each tool round-trip is another window for the idle timeout to land before the agent reaches its Bash-persist). Instruct it to spend tool calls only on **targeted confirmations** of anything the findings leave genuinely open — cap ~2–3 — then go straight to composing the plan. "Verify everything myself" is the failure mode here, not diligence. **For a multi-PR split (Step 2.5):** do NOT inline these exploration results — instead pass the shared-context artifact path `$HOME/claude-plans/<program>-shared-context.md` and instruct the architect to **Read it early** in its own window (the same on-demand-Read convention as item 4's contract Read). The artifact already holds the Step-2 pointers, so referencing it keeps the shared orientation out of the orchestrator's context *and* out of every per-unit prompt (no N× re-inlining); the "authoritative — targeted confirmation only, do NOT re-explore" instruction above still applies, now pointing the architect at the artifact's pointers. **For a single-PR invocation that seeded an artifact from trusted context (Step 2.5):** do the same — pass `$HOME/claude-plans/<slug>-shared-context.md` (this plan's own Step-5 slug, pre-resolved to a literal path) instead of inlining, with the identical instruction: **Read it early**, treat its pointers as authoritative, spend tool calls only on ~2–3 targeted confirmations, do NOT re-explore. There is only one architect here, so the win is not N× de-duplication but orchestrator leanness: the caller's pointers move caller → artifact → architect without ever being re-emitted into a prompt this session composes. **When no artifact was seeded** — single PR, no trusted context — inline the Step-2 exploration results as described above; that remains the default.
3. **The full `$VERIFICATION_RULE`** from Step 1, prefixed with: `MANDATORY — you must follow this rule exactly:`
4. **Full output contract** — instruct the `plan-architect` to Read `.claude/skills/plan/_architect-contract.md` as its first action. That reference (created in Phase 1 of this plan) carries the complete "what the plan MUST produce" list, the conditional-section catalogue, the agent-tiering labels to apply per phase (Sonnet / Haiku / self), and the delegation-packet format. Do NOT inline any of it into the prompt — the architect Reads it into its own sub-context, so this bulk never enters the orchestrator's context.
5. **Draft output path** — the absolute path `$HOME/claude-plans/.drafts/<slug>.draft.md` (using this PR's Step-5 slug) that you seed and the agent appends its sections to across the sectioned delivery (see **Deliver the plan in sections** below). Pre-resolve `$HOME` and the slug yourself; pass a literal path.

**Deliver the plan in sections (timeout durability).** The `plan-architect` agent has no `Write`/`Edit`/`NotebookEdit` tool — its only file-writing channel is Bash (a `cat >>` heredoc), and its only message channel is its streamed turn output, which a long generation can lose to an `API Error: Stream idle timeout`. A single-shot "compose the whole plan, then one big persist" puts the entire deliverable in one vulnerable window: a stall anywhere before that lone Bash call completes loses the whole plan. Instead, **YOU (the orchestrator) drive the architect across turns** and the draft is assembled incrementally, so a mid-stream stall costs at most one section — never the whole plan.

- **Setup.** Before turn 1, `mkdir -p "$HOME/claude-plans/.drafts"` and use the **Draft output path** (item 5) as `$DRAFT`.
- **Turn 1 — outline (the single remaining total-loss window).** Tell the architect this is a *sectioned* delivery whose first turn returns ONLY a numbered list of the section titles the plan will contain — ordered implementation phases first, then every fixed/conditional section (`Critical Files`, `Architectural trade-offs`, `Verification Matrix`, plus `Out of Scope` / `Automouse Hold Justification` when warranted) — one title per line, no bodies, no frontmatter, nothing else. Parse the numbered titles into an ordered list (you drive the loop from it) and persist the outline to the draft: write `# <plan title>` then the outline as an HTML comment (`<!-- PLAN OUTLINE (loop scaffold; dropped at Step 5): … -->`). If turn 1 returns empty, times out, or yields zero parseable titles: **retry once**, then **abort loudly** ("plan-architect produced no outline") — do not proceed. Turn 1 is the only window where a stall loses everything, because nothing is on disk until the outline lands.
- **Turns 2..N — one section per turn (loss bounded to one turn).** For each parsed title, in order, `SendMessage` the architect to output ONLY that section and **Bash-append it to the draft itself** — a completed `cat >> "$DRAFT"` heredoc writing `## <exact title>` plus the body, durable the instant the tool call returns — then return a THIN one-line ack (`section "<title>" appended`), never the section body. The architect must persist via Bash rather than return the body: a returned body is a long streamed message that reintroduces the exact stall vulnerability, whereas a completed append is transcript-durable and the trailing ack is tiny. **Enforce the turn boundary:** reject/re-prompt any turn whose ack reports more than one section or whose append dumped multiple sections. This orchestrator-side rejection is what makes "loss ≤ one section" a real bound rather than an aspiration. Do not read the growing draft between turns.
- **Loss bound.** By construction every completed section is on disk before the next turn begins, so a mid-stream stall loses at most the single in-flight section — re-drivable by re-sending that one title. Only turn 1 can lose the whole plan, and it is guarded by retry-once-then-abort above.
- **`bin/check-plan` stays yours — the architect must never run it on the draft.** `.claude/skills/plan/_architect-contract.md` prohibits it on the architect's side; enforce it here. Step 5 is where the gate runs, on the assembled plan *with* the line-1 frontmatter you prepend. A draft cannot pass gates `[13]` (`impl_model` consistency), `[H]` (hold justification), `[T]` (tier binding) or `[S]` (Sonnet-recipe completeness): all four read the frontmatter at `NR==1`, and a draft's first line is always a `## ` section title, never the `---` block the architect has no channel to write. A draft-time run therefore reports only violations the architect is **structurally unable to fix**, and chasing them is an unbounded retry loop that burns the run before the plan is finished. Say so explicitly in the turn prompts, and if a turn's ack mentions running `bin/check-plan` or reports gate failures, re-prompt that turn with the section title alone — do not let the architect try to "fix" them.

(`API_FORCE_IDLE_TIMEOUT=0` in `~/.claude/settings.json` separately disables the 5-minute idle-gap abort; this sectioning loop defends even if that env var is unset or the run hits the total `API_TIMEOUT_MS` ceiling.) The orchestrator reads the assembled draft only at the end (Step 5).

## Step 3.5: Front-load design decisions

The Plan agent runs in a sub-context and **cannot ask the user**. For each `needs-user-input` fork it flagged in its **Design decisions** section, you (the orchestrator) surface it now with `AskUserQuestion` — one question, 2–4 concrete options, recommendation first; use the `preview` field to show a proposed module layout or code shape for structural choices. Record each answer + a one-line rationale into the plan's **Approach** section as a fixed constraint, then patch the affected implementation steps so the decision is fully specified. **For a multi-PR split:** additionally **append** each resolved decision that binds more than the current unit (whether self-resolved by the architect or answered here via `AskUserQuestion`) to the shared-context artifact's `## Resolved design decisions` section — one entry per decision: the decision plus its one-line rationale. Later units then Read those frozen decisions from the artifact instead of re-litigating them, and cannot silently diverge. Keep this **append-only and incremental** — append as each unit's Step 3.5 resolves; do NOT front-run the whole split into an up-front batch-decision pass over all units (that heavier structure is the Discord precedent's shape, not the minimal model this formalizes).
**Single-PR runs — including one that seeded an artifact from trusted context (Step 2.5) — do NOT append here.** The multi-PR qualifier above is exact, not shorthand: the append exists so *later units* can read frozen decisions, and a single-PR plan has no later unit. Its artifact's `## Resolved design decisions` was already filled at seed time from `$ARGUMENTS`; re-appending the same decisions would double-write the seed and break append-only discipline. Record the resolutions in the plan's **Approach** section only.

A recorded decision is **no longer a fork**: it does not trip Step 4 gate 7 (unresolved decision) and does not, by itself, force `auto_merge: false` (gate 14) — the human judgment already happened at plan-time.

Do **not** ask when: the fork is conventional (let the Plan agent's self-resolution stand), the judgment is `irreducible` (distributed per-site/per-test, or data-blocked — that legitimately keeps the plan supervised), or asking is ceremony. `AskUserQuestion` is for forks where the answer actually changes the implementation.

## Step 4: Validate the matrix

After receiving the Plan agent's output, check these gates yourself — do NOT delegate validation.

**The deterministic gates are scripted, not hand-run.** `bin/check-plan` (invoked in Step 5, once the plan is on disk) mechanically enforces the false-positive-free subset: gate 1 (matrix exists), gate 3 (no false manuals), the `DECIDE`/`TBD`/`subject to validation`/`subject to review` tokens of gate 7, gate 8 (decision-trigger ADR — flags a declared new trigger-surface file lacking an ADR step or `no-adr:` marker), reuse-target existence (a PHP `Class::method` named in a **Reuse** note whose class exists in `ibl5/` but whose method is absent — a likely typo), **and** the context budget (gate `[C]` — a marathon-sized plan, ≥500 lines or ≥12 numbered phases; the fix is a Step 2.5 split, not a marker). Do **not** hand-scan for those; fix whatever the script reports. The gates below are the ones that need judgment a script cannot do:

1. *(scripted — see above)*
2. **No unclassified items** — every row's test type is a real classification. *Not scripted on purpose:* the type column is open-ended in practice (`Go-archive-diagnostic`, `Documented (domain rule)`, `read-before-cut` are legitimate), so a closed-set check would false-positive — judge membership yourself.
3. *(scripted — see above)*
4. **Tests woven inline** — pre-impl tests appear before their implementation step, not collected in a bottom appendix
5. **Production comparison classified correctly** — any "compare against production" or "match iblhoops.net" row must be Visual-regression, not Truly-manual
6. **Test file paths present** — every PHPUnit/API-test/E2E/Visual-regression row names a concrete test file path, not just a category
7. **No unresolved decisions** — the literal tokens are scripted (see above). You still hand-resolve an unresolved **`(or `** fork (e.g. "STAY (or move)") — `bin/check-plan` skips that token because the corpus showed it is overwhelmingly a benign aside (`≤5 (or 0 ideally)`, `(or extend existing)`), and telling a real fork from an aside needs reading the alternative. Resolve any genuine fork in-place; the automouse agent cannot make judgment calls.
8. *(scripted — `bin/check-plan` gate `[8]`)* **Decision-trigger pre-classified** — gate `[8]` flags any declared NEW file matching a `bin/adr-check` trigger surface (the pattern table lives in `_plan-verification.md` § Decision-trigger pre-classification — the single source of truth; do not duplicate it) that lacks a resolution. When it fires, do **not** merely "add an ADR step": pre-name the ADR slug and pre-fill the ADR's Context and Decision text directly into the plan body, so the spec carries the ADR draft. The conservative flags (any new `bin/` script; a new migration only when the plan text mentions `DROP`; a `composer.json` `require`/`require-dev` add) cannot read LOC/content at plan time, so they over-include slightly — clear a false flag with a `no-adr:` marker when no real decision is introduced.
9. **Negative-path coverage** — every behavior-changing step has at least one matrix row asserting a failure, boundary, or rejection case, not only happy-path. If a step has only happy-path rows, add the missing negative-path row.
10. **Hot-file extraction** — if any step adds > 100 LOC to a file `bin/check-hot-files` lists as hot (> 500 LOC under `classes/`), the plan must either propose an extraction step or carry an inline justification (per `_plan-verification.md` § Hot-file thresholds). If neither is present, add one.
11. **Refactor characterization** — if any step under `ibl5/classes/**` carries a refactor signal (file rename, method signature change, visibility narrowing, class removal, or > 30-line deletion per `refactor-flag.md`), the matrix must include a pre-impl characterization row for the affected code. If missing, add it. A correct **Phase-1** characterization pin makes a behavior-preserving refactor green-green — the pin *is* the coverage, so "this code was untested" is not on its own grounds for `auto_merge: false` (gate 14).
12. **Security surface resolved** — if Step 2 flagged a touched security surface, the plan contains a Security section with a defense step and matching matrix row for each. If a flagged surface has no resolution, add it.
13. **impl_model criterion — bidirectional; the model is a deliberate choice in BOTH directions, and ABSENCE is never valid.** `bin/lib/plan-model-consistency` (called by `bin/check-plan` gate `[13]` and the automouse queue backstop) rejects any plan with a Verification Matrix that carries no `impl_model:` marker — there is no Opus-by-omission default. (a) If the plan declares `impl_model: sonnet` frontmatter (see Step 5), scan the Verification Matrix; if ANY row is classified `Truly-manual`, replace the marker with an explicit `impl_model: opus` plus a one-line reason in the plan body — do not merely strip it, since absence is itself a gate-`[13]` violation. Sonnet may drive a plan only when every behavior-changing step has an objectively machine-checkable row that fails on a wrong edit. (b) Conversely, if the plan does NOT declare `impl_model` and that criterion **holds** — no `Truly-manual` or subjective row, every behavior-changing step machine-checked — ADD `impl_model: sonnet` to the line-1 frontmatter yourself. Opus-by-omission is a model-assignment miss, not a safe default: audited 2026-07-07, 57 of the 109 Opus-default plans in the corpus carried no `Truly-manual` row, and Opus implementation runs are both ~1.7× the per-token cost and the ones that marathon into the context dumb-zone. The failure mode stays bounded exactly as Step 5 notes: a wrongly-added marker turns the objective matrix red under Sonnet and is caught by CI / post-plan. This composes with the Step 2.5 **Tier-boundary separability** criterion: when (a) would drop a *mixed* plan to Opus — separable Opus-forcing phases (a `Truly-manual`/subjective row or a judgment phase) sitting alongside otherwise-mechanical phases — prefer splitting at the tier boundary (a small Opus plan carrying only the judgment work, plus stacked `impl_model: sonnet` plan(s) for the mechanical bulk) over forcing the whole plan up to Opus. Whole-plan Opus is the fallback only when the tiers are genuinely entangled (judgment interleaved within phases), which no split can separate.
14. **auto-merge hold criterion** — post-plan **always** runs and opens the PR (with code review, security audit, and CI) the moment an implementation session verifies complete; what you decide here is only whether **auto-merge arms** or the PR waits for a human to merge. Decide yourself — do NOT delegate — whether this plan wants a human at merge, and if so declare `auto_merge: false` (see Step 5; `/post-plan` Phase 6.5 condition (7) reads it and refuses to arm). Hold the merge when **any** hold: (a) the Verification Matrix carries a `Truly-manual` (or otherwise subjective) row — post-plan's machine gates can't validate it; (b) Step 2 flagged a **touched security surface** — held under this gate **by default**; the hold is *released* (auto-merge may arm) **only** when the surface is one of the *dischargeable* shapes (a state-free extraction, a pure-function move, a validator that never sees actor identity) **and** the plan carries the complete, test-proven **Security-surface mechanization** phase: a structural invariant named in falsifiable terms, a required-blocking assertion that fails loudly when it is violated, an explicit statement of what the discharge does *not* cover, and the Verification Matrix rows for that assertion — absent, incomplete, or merely *asserted in prose*, it stays held. An **irreducible** surface — a check that genuinely carries actor identity (`$loggedInTeamID`, session, current user), a new POST/form endpoint, a new SQL construction site, or new user-facing output rendering — has no release path under this gate and stays held. Taxonomy and required components: `_architect-contract.md` § Security-surface mechanization; (c) the plan is a high-blast-radius data/schema change — an *irreversibly destructive* migration (DROP / lossy backfill / in-place data mutation), a column-rename sweep, or an FK-ordering migration. A **reversible schema tightening** (a `varchar` narrowing, a length or `NOT NULL` constraint) is **held under this gate by default**; the hold is *released* (auto-merge may arm) **only** when the plan carries the complete, test-proven Schema-safety mechanization phase (apply-time fail-closed guard + forward-bound assertion + idempotency + documented lossless rollback + a guard-abort test) — absent or incomplete mechanization, it stays held. See gate 15; (d) the plan introduces **new or redesigned user-visible UI/UX** — the forced manual-verification trigger in `_plan-verification.md` (new/restyled CSS component, new rendered page/module, new nav entry/indicator/badge, or a new multi-step user flow). Set `auto_merge: false` directly when any of these hold — it is the single authoritative hold, so you do not need to keep it in sync with the matrix: Phase 6.5 condition (1) *independently* holds a UI/UX plan (its Truly-manual row lands in the PR's Manual-Testing section, which blocks arming), so neither lever re-arms if the other is dropped. This composes with gate 13: a `Truly-manual` row both strips `impl_model: sonnet` **and** sets `auto_merge: false`. A genuine design fork is **not** a hold trigger once it has been front-loaded and recorded via Step 3.5 — the judgment already happened at plan-time; hold only when the judgment is irreducible (distributed per-site/per-test, or data-blocked). Omitting the flag leaves the PR eligible to auto-merge, still subject to every Phase-6.5 condition — including the PR-time safety verdict (9) on the realized diff and the `feat:` floor (8).
15. **verification-gap mechanization (autonomy lever 3)** — when the *motivation* for `auto_merge: false` is a **verification gap** — the change's correctness is silent, integration-only, observable-only-in-prod, or would otherwise need a human to confirm it works — rather than one of gate 14's intrinsic triggers (a)–(d), the hold does NOT stand on that basis alone. First require lever 3: the plan must carry the mechanical self-check (a required CI job that fails loudly on the bad outcome, a loud-failure signal replacing a silent fallback, an independent invariant assertion, or a shadow/burn-in rollout) — see Verification-gap mechanization — or state concretely why one cannot be built. A hold whose only justification is an un-mechanized verification gap **fails this gate**: add the self-check phase (and its matrix rows) and leave auto-merge armed, unless an intrinsic 14(a)–(d) trigger independently applies. Note gate 14(c) holds an *irreversibly destructive* schema change (DROP / lossy backfill / in-place data mutation), a column-rename sweep, an FK-ordering migration, or one whose **target shape itself depends on production data you cannot read** (genuinely design-data-blocked). It does **not** *permanently* hold (i) a CI/config/tooling change ("I can't verify this" is mechanizable — lever-3 territory), nor (ii) a **reversible schema tightening** (a `varchar` narrowing, a length or `NOT NULL` constraint): each stays held only until its mechanization phase exists. A tightening's release condition is the complete Schema-safety mechanization phase, **proven by its guard-abort test**. The guard runs on the *target* DB at deploy, so prod gates its own migration — "needs prod `MAX(LENGTH)`, prod unreachable from CI" is a guard to build, not a data-blocked hold. Two correctness traps the plan must respect: the runner is `mysqli::multi_query` (`MigrationRepository`), which halts the batch only on a statement that raises a real **error** — so the guard must use a `sql_mode`-independent erroring idiom (e.g. `SELECT IF(<violation>, (SELECT 1 UNION SELECT 2), 0)` → ERROR 1242), never one that merely *warns* under non-strict mode; and prod `sql_mode` is **non-strict** while local/CI MariaDB is strict-by-default, so a strict-only idiom passes the local guard-abort test yet leaves prod unguarded. Existing rows are then mechanically protected; future over-length writes are bounded only by the forward-bound assertion (a static plan-time argument — non-strict prod truncates rather than rejects). With the phase present and its guard-abort test green, leave auto-merge armed. **Second arm — unconditional, keyed on the diff rather than on the hold's justification.** Everything above is arm 1 (the hold's *motivation* is a verification gap). Arm 2 fires regardless of why the hold exists: **a new silent-fallback or degraded path in a synchronous sim path or a `bin/*-tick` script requires a loud failure signal** — a Discord notification, a required-blocking CI check that fails on the bad outcome, or an equivalent alarm — **even when the hold is justified by a security surface, a destructive migration, a UI/UX row, or any other gate-14 trigger.** A degraded path that logs `WARNING` to a launchd log and returns an empty result is not a signal. This arm exists because #1753 held on 14(b), so arm 1 never ran, and the roster-blind recap could have no-op'd in prod indefinitely with CI green.
16. **pre-prod exercise path — deploy-dependent behavior must be COVERED, not merely well-classified** — the *coverage* sibling of gate 15. Gate 15 asks whether an existing hold was mechanized; this gate asks whether the deploy-dependent behavior has a row **at all**. Scan the plan for behavior that only manifests once something is deployed: a file CI ships on merge, an applied migration, a **registered daemon / cron / scheduled job** — the repo's actual recurring shape is a `bin/<name>-tick` script driving a live external or cross-process service (`bin/bug-pipeline-tick`, `bin/sim-recap-tick`) — a new workflow under `.github/workflows/`, or any call across a live-service boundary. For **each** such behavior the Verification Matrix must carry at least one row, and that row must be **pre-prod-exercisable**: runnable on one of the three environments enumerated in `_plan-verification.md` § Pre-prod exercise paths (worktree Docker stack / CI / `.github/workflows/deploy-rehearsal.yml` prod-clone rehearsal). Three failure shapes, in descending frequency — every one of them passes gates 1–15 today:
    - **(a) No row at all.** The plan never mentions the deployed behavior in the matrix and asserts full automated coverage. A PR body reading *"No manual testing needed — all changes are covered by unit and E2E tests"* is frequently exactly this failure, stated confidently. Silence is not coverage; an absent row **is** the violation.
    - **(b) Stub-only coverage.** The only coverage for a live-service or cross-process integration is a stub, mock, fake, or hand-written double (e.g. `bin/lib/bug-pipeline-test-stubs.sh` and its `STUB_CREATE_THREAD_FAIL` toggle). **A stub proves your code called the stub. It cannot prove the real endpoint is reachable, authenticated, or shaped as assumed.** For this gate such behavior is **UNCOVERED**, not covered — this is the highest-value case the gate catches, because the matrix looks green and no other gate sees it. Require at least one row that crosses the real boundary pre-prod: a scoped smoke run against a **test** channel/queue from the worktree stack, a record-and-replay harness over a genuinely captured response, or a `workflow_dispatch` run on the PR branch.
    - **(c) A correctly-authored manual row that covers something else.** The mere presence of a `Truly-manual` row does **not** satisfy this gate. A row about copy tone or visual polish is not coverage of whether the automation fires or the links resolve. Match rows to *behaviors*, never to counts.
    - **(d) A row whose CI check the PR's own diff never triggers.** The matrix names a CI-run check (`bin/test-*`, `bin/check-*`, a workflow job), but the plan's changed-file set does not match the `changes:` path filter of the job that runs it — `harness-tests` is gated on `shell` (`bin/**`), `db-integration` on `src` (`**.php`), so a PHP-only diff runs zero harness tests and a `bin/`-only diff runs zero DB tests. Named-and-never-run is uncovered. The plan must either add the path to that job's filter or move the check to an always-run job, as a numbered phase.

    **Resolution — dissolving this gate means BUILDING A PRE-PROD EXERCISE PATH. It is never dissolved by DELETING THE ROW, weakening it, or moving it to a non-gating note.** Apply the Step 4.5 **reducible / intrinsic** split verbatim, and expect the line to run *through the middle of one feature*:
    - **Reducible — build the path.** The integration's **logic** is almost always reducible. Concretely: invoke the tick script's body one-shot against the worktree stack; add or use a `--dry-run` / `--once` flag (`bin/sim-recap-tick` already ships `--dry-run --sim=N`, which touches no queue row and performs no DB write — copy that shape rather than inventing one); a record-and-replay harness over a real captured response; a scoped live-service smoke run against a test channel/queue; `workflow_dispatch` / `workflow_call` on the PR branch; a `deploy-rehearsal`-style prod-clone dry-run; a DatabaseIntegration test for the migration half.
    - **Intrinsic — narrow, and this is precisely what the marker is for.** Only two slices are genuinely intrinsic: **scheduling** (the launchd/cron registration itself firing on the prod box on its schedule — `bin/sim-recap-cron-setup`, `bin/bug-pipeline-cron-setup`) and the **network / credential boundary** (an endpoint reachable only on the prod tailnet; a secret that exists only in the prod environment).
    - **Split the behavior — the move that dissolves most of these holds.** A plan may claim the intrinsic exception for the scheduling / reachability slice **only**, and must still build the pre-prod path for the logic slice. "The daemon can't run on my laptop" is a claim about *registration*, not about the code the daemon runs.

    A plan that keeps an intrinsic slice must carry a literal `pre-prod-exception:` marker and a `## Pre-prod Exception Justification` section with one line per exception naming its category (scheduling / reachability / credential) and why no pre-prod path exists. `bin/check-plan` gate `[P]` enforces the marker and the section's presence; the *validity* of each entry is your judgment, exactly as with gate `[H]`. This gate never licenses dropping a forced UI/UX row — a taste judgment is always exercisable on the worktree stack, so it can never be intrinsic (see the anti-abuse guard in `_plan-verification.md` § Pre-prod exercise paths). Satisfying gate 16 also never re-arms a gate-14 hold: coverage and hold are independent verdicts.

If validation fails on any gate, fix the matrix yourself rather than re-running the Plan agent.

## Step 4.5: Challenge the auto-merge hold

If you did **not** set `auto_merge: false` (Step 4 gate 14), skip the auto-merge challenge below — there is nothing to challenge there. **The pre-prod-exception challenge at the end of this step is separate and is NOT skipped**: it runs whenever the plan carries a `pre-prod-exception:` marker, armed or held, because a coverage exception and a merge hold are independent verdicts.

When you *are* about to hold the merge, run an adversarial second pass on that verdict yourself (Opus — irreducibility is Opus-tier judgment, never delegated). The forcing question, lifted from the manual re-prompt that breaks false holds in practice, is: **"What would I add to this plan to make it safe for automouse to merge unattended?"** Apply it according to the hold's *type* — the standard differs, and conflating them regresses safety in one direction or autonomy in the other.

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
- a **self-gating / bootstrap hazard** — a change to the auto-merge, merge-gate, or `/post-plan` machinery **itself**, where arming would let the half-built or just-rewritten mechanism gate its *own* change. No self-run check can be trusted here, because the thing under change *is* the verifier; a human must merge it under the old, known-good floor. (This is why it is not a mere verification gap — lever 3 can't mechanize a check whose own validity the PR is rewriting.)

**Record the outcome.** Every plan that *keeps* `auto_merge: false` must carry an `## Automouse Hold Justification` section (its presence is enforced by `bin/check-plan`; validity is your judgment). State in it: the hold **category** (reducible-confirmed or intrinsic) and which gate-14/15 trigger it rests on, plus one line — for a reducible-confirmed hold, *why no mechanical check is buildable*; for an intrinsic hold, *why the judgment is irreducible*. A reducible hold you **dissolved** carries no section, because the plan no longer holds.

**Second challenge — a claimed `pre-prod-exception:`.** Run this whenever the plan carries a `pre-prod-exception:` marker (Step 4 gate 16), *independently* of the auto-merge verdict above. Same adversarial posture, same **reducible / intrinsic** vocabulary — but the forcing question is **slice-level, not artifact-level**. Do not ask *"is this testable pre-prod?"*, which invites a yes/no about the whole feature and gets answered "no". Ask:

> **"Which slice of this is intrinsic — and what am I building for the rest?"**

For the repo's recurring shape — a `bin/<name>-tick`-class scheduled/daemon script driving a live external or cross-process service (`bin/bug-pipeline-tick`, `bin/sim-recap-tick`) — the default answer is fixed and narrow:

- **Intrinsic:** the **scheduling** slice (launchd/cron actually firing on the prod box on its schedule — `bin/sim-recap-cron-setup`, `bin/bug-pipeline-cron-setup`) and the **network / credential boundary** (an endpoint reachable only from the prod tailnet; a secret that exists only in the prod environment).
- **Reducible:** everything else — and that is most of the feature. **The exception does not cover it.** Gate 16's resolution list has the moves; do not restate them here, apply them.

**A plan claiming the exception over a whole artifact is claiming too much. Reject it** and send it back to build the reducible slice's exercise path. "The daemon can't run on my laptop" is a claim about *registration*, not about the code the daemon runs.

**The failure this challenge exists to prevent, concretely:** an exception claimed over the integration's **logic**, licensed by a Verification Matrix that reads fully green because its only coverage of the live boundary is a stub (the `bin/lib/bug-pipeline-test-stubs.sh` shape). The stub proves the caller called the stub. Nothing then verifies the real service does the thing — and the gap ships behind a green matrix *plus* a recorded, plausible-sounding exception. That pairing is worse than an openly uncovered plan, because it reads as covered and no later gate re-opens it.

**Disposal.** An exception that survives the challenge is recorded as `pre-prod-exception: <reason>` naming **which slice** is intrinsic — scheduling, reachability, or credential — never the artifact as a whole and never the plan as a whole, plus its one-line entry in `## Pre-prod Exception Justification`. In the same plan, the reducible slice still gets its pre-prod exercise path **built**, with its own matrix rows. An exception is never a whole-plan escape, and it is never satisfied by deleting the row it was claimed against.

## Step 5: Write the plan file

Derive a kebab-case slug from the task description (max 50 chars, lowercase, alphanumeric and hyphens only).

```bash
PLAN_PATH="$HOME/claude-plans/<slug>.md"
```

If a plan file already exists at that path, create a new one with a numeric suffix rather than overwriting.

**Source the plan from the persisted draft.** Across Step 3's sectioned delivery the draft at `$HOME/claude-plans/.drafts/<slug>.draft.md` was assembled incrementally — you seeded its title + outline scaffold and the agent Bash-appended one section per turn (see Step 3 § Deliver the plan in sections). Read **that draft file** as the source of truth, and drop the `<!-- PLAN OUTLINE … -->` scaffold comment when writing `$PLAN_PATH`. **Drop any frontmatter or plan-metadata *section* the draft carries, too** — a body heading such as `## Plan Metadata, Frontmatter, …`, a `<!-- FRONTMATTER: … -->` comment, or a fenced restatement of the `---` block. `_architect-contract.md` forbids the architect emitting one (it cannot write line 1), so such a section is a build directive addressed to **you**, already satisfied the moment you prepend the real block below. Fold any *justification* prose it carries into `## Automouse Hold Justification` or **Approach** and drop the directive and the fenced restatement. It is inert to every parser — `bin/lib/plan-impl-model` and `bin/lib/plan-model-consistency` are both `NR==1`-anchored — but left in place it ships a satisfied build directive into every implementation run's context, where it reads as a live instruction. The per-section acks carry no plan content, so never reconstruct the plan from the streamed messages — the draft on disk is authoritative. If the draft is missing or empty (the agent errored before appending any section), re-run the Step-3 sectioned delivery. After writing the final `$PLAN_PATH` and passing `bin/check-plan`, delete the draft (`rm -f "$HOME/claude-plans/.drafts/<slug>.draft.md"`).

Write the validated plan (with corrected matrix if Step 4 required fixes) to the plan file. When the work was split into multiple PRs, give each plan a distinct slug (e.g. `<base-slug>-1-<unit>`, `<base-slug>-2-<unit>`) so they sort in dependency order, and write one file per unit. Each split plan **opens with a single pointer line** to the shared-context artifact — immediately after any line-1 frontmatter block — and **references** it instead of restating the shared background, blast radius, or frozen cross-unit decisions in the plan body:

```
> **Shared context:** `$HOME/claude-plans/<program>-shared-context.md` — read first for blast radius, existing patterns, and frozen cross-unit decisions; this plan covers only unit <n>.
```

(`<program>` is the `<base-slug>` stem above.) This is the plan-shrinking mechanism: the shared orientation lives **once** in the artifact rather than being re-inlined into all N plans, so each split plan stays smaller and comfortably under gate `[C]` — restating shared background in every unit is exactly the bloat that pushes split plans toward the context-budget ceiling.

Then run the mechanical linter on each plan file you wrote and fix anything it reports, in-place, until it exits clean:

```bash
bin/check-plan "$PLAN_PATH"
```

It enforces the deterministic gates from Step 4 (matrix present, no false manuals, no `DECIDE`/`TBD`/`subject to …` tokens, no unresolved decision-trigger surface, reuse targets resolve, context budget, pre-prod exercise path). A non-zero exit prints each violation prefixed by its gate (`[1]`/`[3]`/`[7]`/`[8]`/`[13]`/`[B]`/`[H]`/`[R]`/`[C]`/`[T]`/`[S]`/`[P]`); resolve each and re-run. `[W]` (sweep-verb) also prints, but is **advisory and non-blocking** — it is emitted as a warning and never affects the exit code, so a `[W]` line alongside a clean exit is expected, not a violation you must clear. Do not leave a plan written until `bin/check-plan` passes. A `[C]` (context-budget) violation is resolved by **returning to Step 2.5 and splitting the plan into stacked PR-sized units** — reach for a `context-budget:` justification marker only when the size is illusory (e.g. the length is mostly fenced reference material or delegation-packet recipes and the actual phase count is small).

### Declaring the implementation model (required)

The automouse implementation agent's model is selectable per-plan via a line-1 YAML frontmatter field, and **every plan with a Verification Matrix must declare one** — `bin/check-plan` gate `[13]` (via `bin/lib/plan-model-consistency`) rejects an absent marker; there is no Opus-by-omission default. When **every** behavior-changing step has at least one Verification-Matrix row that is objectively machine-checkable and fails on a wrong edit (PHPStan green, PHPUnit/CLI assertion, baseline regen, identical test count, green-green characterization), prepend this block as the **very first lines** of the plan file:

```
---
impl_model: sonnet
---
```

The implementation then runs at Sonnet (cheaper, verified-equivalent quality on uniformly-mechanical plans — parsed by `bin/lib/plan-impl-model`). For any plan carrying a `Truly-manual` or subjective row, declare an explicit `impl_model: opus` instead, with a one-line reason in the plan body. Only the first frontmatter block is parsed, so documenting this syntax inside a plan body never mis-selects a model. Failure modes are bounded: a garbled marker → the runtime resolver (`bin/lib/plan-impl-model`) falls back to Opus (safe), and gate `[13]` catches absence before the plan ships; a wrongly-applied `sonnet` marker → the plan's objective matrix goes red under Sonnet → caught by CI / post-plan.

### Holding auto-merge (optional)

An interactive implementation session, once it verifies complete, always auto-fires a detached `/post-plan` via `bin/post-plan-now --auto` — opening the PR and running code review, security audit, and CI. Post-plan is **never held**. What you control is whether **auto-merge arms**: for a plan judged by Step 4 gate 14 to want a human at merge, add `auto_merge: false` to the **same** line-1 frontmatter block. Post-plan still opens the PR and reviews it; `/post-plan` Phase 6.5 condition (7) reads the flag and refuses to arm auto-merge, leaving the PR open for a human to merge:

```
---
impl_model: sonnet
auto_merge: false
---
```

`impl_model` is always present (see above — gate `[13]` requires it); `auto_merge` appears only when holding. The block above shows both (a mechanical column-rename sweep, e.g., can be Sonnet-eligible **and** want a human at merge). A plan that keeps `auto_merge: false` must also carry the `## Automouse Hold Justification` section from Step 4.5 — `bin/check-plan` gate `[H]` fails the plan if it is missing. Absence of `auto_merge` leaves the PR eligible to auto-merge (still subject to every other Phase-6.5 condition). Only the line-1 block is parsed (Phase 6.5), so a body that documents the syntax can't opt a plan out. Failure modes are bounded: absent/garbled → eligible to arm (post-plan's own gates — code review, security audit, CI-green-required, the `feat:` floor, the PR-time safety verdict, and the headless golden-snapshot block — still apply); a wrongly-applied `false` → the PR just waits for a manual merge.

## Step 5.5: Auto-queue queue-safe plans

A plan is **queue-safe** the moment `bin/check-plan` (Step 5) exits 0 — that gate already enforces no unresolved decisions, no `DECIDE`/`TBD`/`subject to…` tokens, resolved decision-triggers, and resolved reuse targets, so a passing plan is fully specified for unattended automouse execution. Queue-safety is **independent of `auto_merge`**: a plan held for human merge (`auto_merge: false`) is still safe to *implement* autonomously — only its merge waits (Phase 6.5 condition (7)).

For every plan that passed `bin/check-plan` in Step 5, decide its disposition by this precedence (the default is **queue**):

1. **Explicit token in `$ARGUMENTS`** wins outright: `--implement` (or "implement now") → do NOT queue; leave the plan on disk and report it ready to implement. `--queue` → queue.
2. **Else, clear session intent to implement now** (the user said they will implement it in this session, or implementation is already underway) → do NOT queue.
3. **Else, default: auto-queue.** Run `bin/automouse/queue <slug>` for the plan.

When the work was split into multiple PRs (Step 2.5), queue **every** queue-safe unit, running `bin/automouse/queue <slug>` once per plan in dependency order (the order they must merge). A plan that did not pass `bin/check-plan` is never queued — fix it first.

Report which plans were queued (and which were left for in-session implementation) in Step 6.

## Step 6: Report

Tell the user:
- The plan file path — **all of them** when the work was split into multiple PRs
- The **disposition** of each plan: auto-queued for automouse (default), or left for in-session implementation (`--implement` / clear implement-now intent) — and the resulting `bin/automouse/queue` state
- A one-line matrix summary per plan (e.g., "12 items: 7 PHPUnit, 3 E2E, 2 CLI-executable, 0 truly-manual")
- Whether any security surface was flagged and how each is defended (or "no security surface touched")
- Whether the PR is eligible to auto-merge on green CI (default) or is held for a human merge (`auto_merge: false`, per Step 4 gate 14) — and why, if held
- Whether every deploy-dependent behavior has a pre-prod exercise path (Step 4 gate 16) — and, for any recorded `pre-prod-exception:`, which slice is intrinsic (scheduling / reachability / credential) and what was built for the reducible slice — or "no deploy-dependent behavior"
- Whether any post-merge follow-up was mechanized (the merge-triggered watcher and what it runs on merge), or "no post-merge follow-up"
- For a multi-PR split: the PR sequence and dependency order (which lands first, what each stacks on)
- Whether each plan is ready for implementation or has open questions
