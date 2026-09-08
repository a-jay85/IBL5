---
description: Read-on-demand detail for agent-tiering — the skip-vs-spawn heuristic, flat-fan-out (nested sub-agent) rationale, boundary keys on task type, orchestrator context economics (delegate-don't-dismiss, split-don't-self-clear), the measured evidence behind the offload-`/plan`-by-default rule, and per-tier prompt style. Fable approval gate moved to `agent-tiering-fable-gate.md`; bounded-checklist diff-triage rationale moved to `agent-tiering-bounded-checklist.md`. Loads only when editing workflow orchestration defs.
last_verified: 2026-09-08
paths:
  - ".claude/skills/**/*.md"
  - ".claude/agents/*.md"
---

# Agent Tiering — Detail

Read-on-demand companion to `agent-tiering.md` (always-loaded). The parent holds the
operative Tier table and Explore tiering. This file holds the longer rationale — the
skip-vs-spawn heuristic, the Fable gate, flat-fan-out and orchestrator context economics,
and prompt style — pulled out of the always-loaded budget.

## Skip the Agent — Direct Tool Calls

Each sub-agent costs ~17–23K tokens (system prompt + rules + memory, loaded before its prompt) [CORRECTED 2026-08-14: was "~3–5K"; measured p50 spawn context is 17–23K], and its output re-loads in Opus's context every later turn.

**Measured 2026-08-25** — 139 Opus main sessions, 1,580.7 Mtok total:

| Quantity | Value |
|---|---|
| Delegatable tool results | 8,292 calls / 4.73 Mtok |
| p50 result | 194 tokens |
| p75 / p90 | 565 / 1,272 tokens |
| p95 result | 2,039 tokens (~8 KB on disk) |
| p99 result | 5,389 tokens |
| Calls ≥ 2,000 tokens | 5.2% of calls / **43.6% of total residue** |
| Sub-agent spawn cost | 17–23K tokens (p50 spawn context) |
| Sessions with zero spawns | 87 / 139 = 63% |

**This gives the "~50 lines" threshold below a measured basis.** ~50 lines of output lands right around the measured p50 of 194 tokens — roughly 90× cheaper than the 17–23K a spawn costs before it does any work. The figure stands exactly as written; it was an estimate and is now an estimate the data agrees with.

**The fat-tail batching rule names *which* calls are worth a spawn at all** — not how few spawns to make. `agent-tiering.md` § Fat-tail delegation lets two fat calls per turn through and denies the 3rd, routing it and the rest into a `sonnet-4-6` spawn. It identifies the tail worth delegating (the 5.2% carrying 43.6% of the residue). **One spawn is the default there for a wall-clock reason, not a token one:** those calls are cheap to *run*, so serializing them in one agent costs almost no wall-clock. When a batched item is genuinely long-running and independent (a full Playwright run beside an unbounded log dump), fan out instead — § Fan out by independence.

**Read this as headroom, not as savings.** Break-even for this corpus is roughly 353 spawns; the same 139 sessions produced 135. We sit about 2.5× *below* break-even, so the finding is **unused delegation headroom** — room to route more of the fat tail through a sub-agent — not a token saving already banked and not cost pressure to relieve.

**Run directly (no agent) when ALL hold:** single command/tool call · output under ~50 lines · nothing else to run in parallel · output won't persist across turns.

**Spawn an agent when ANY hold:** output unpredictably verbose (large grep, failing suites with stack traces) and the agent can return a summary · multiple independent verbose tasks run concurrently · the task is multiple sequential tool calls.

**PHPUnit and PHPStan are always direct Bash calls** — passing output is ~5 lines, failures usually under 50; agent overhead dwarfs it. Use `run_in_background` for parallelism without an agent — **but only in the interactive harness**, where a finished background task re-invokes you. In a **headless** run (`claude -p`, e.g. `/post-plan` under automouse) there is no re-invocation: a live background task at turn-end stall-kills the run — run blocking, or poll `BashOutput` to completion in-turn (post-plan `SKILL.md` Phase 5).

> The Fable tier approval procedure (incl. the asm-level static-RE exception) has moved to `agent-tiering-fable-gate.md`.

### Fan out by independence

**When you spawn, the discriminator is independence — not invocation count.** The old "token spend outranks wall-clock time; batch N related tasks into one agent" priority is **retired** (2026-09-08, explicit user decision): the 2026-08-25 table above puts us ~2.5× *below* delegation break-even (135 spawns against a ~353 break-even), so (N−1) × 17–23K of extra spawns is negligible. **Wall-clock time is the scarce resource; token spend is not.**

- **Fan out** when the tasks are **mutually independent** — issue every `Agent` call in one message so they run concurrently, collapsing a serial chain into one wall-clock unit. Work that would otherwise run serially and *can* run concurrently should; "that costs another spawn" is no longer a reason not to.
- **Batch into one agent** when the tasks are **sequential or dependent** — splitting those re-pays the spawn overhead for **zero** wall-clock gain, and hands each agent a partial view of a coherent change.

Read the two together: logically-distinct-but-dependent is still one agent; logically-related-but-independent may fan out. "Each agent needs its own context" (independent worktrees, isolating verbose output) is still a *sufficient* reason to split — it is no longer a *necessary* one.

**Three limits on fan-out.**

1. **Trivial work stays inline.** Below the ~17–23K spawn cost there is no serial baseline worth beating; fanning three trivial edits out is three losing trades, not one saved minute (`work-triage-detail.md` § Inline vs. delegated).
2. **Interactive main thread only.** Under `claude -p` (headless `/post-plan`, automouse) an async `Agent` delegate emits nothing on the parent stream for its whole runtime — precisely why the automouse watchdog sits at 30 min rather than 10 (`automouse-workflow.md`). Wide concurrent fan-out there multiplies stall-kill exposure. Keep headless runs narrow.
3. **Width, not depth.** Fanning wider at one level does not license *nesting* — flat fan-out below is unchanged. Same-tier Sonnet→Sonnet delegation also remains waste, concurrent or not.

## Boundary keys on task type, not model capability

Re-validated 2026-06-30 against Sonnet 5 (then the `sonnet` alias, native 1M context). The
Opus-only column (final code review, diff-triage, rule/ADR authoring, novel reasoning,
ambiguous failures) stays Opus because **"never delegate understanding" is a *delegation*
rule, not a "wait for a smarter model" rule** — a more capable Sonnet does not make
delegating the judgment safe, because the cost was never Sonnet's raw ability, it was that
the orchestrator session loses the findings it would otherwise filter (see the flat-fan-out
rationale below, and `feedback_sonnet_proving_negatives` / `feedback_review_agent_full_diff`).
Sonnet 5's larger context window only **strengthens** the existing "spawn Sonnet to absorb
verbose output" rationale; it is not a reason to push understanding-class work down a tier.
**Tripwire to revisit:** a model generation where the *delegation* failure mode itself
changes (e.g. a coordinator that can surface its own filtered-out findings), not merely a
higher per-task capability score.

## Nested Sub-Agents — One Carve-Out, Otherwise Unused

Sub-agents can spawn sub-agents — `CLAUDE_CODE_MAX_SUBAGENT_SPAWN_DEPTH` is **3** in `~/.claude/settings.json`, not the 5 this rule claimed — but we keep **flat fan-out**: the orchestrator session owns every fan-out and absorbs every agent's output. Do not nest in `/pr-review`, `/security-audit`, `/post-plan`, or automouse. **One carve-out, in `/plan` only:** `plan-architect` and `plan-architect-xhigh` may spawn at most **one** `Explore` for a question that surfaces mid-design (`.claude/skills/plan/_architect-contract.md` § Mid-design exploration). That subtree terminates — `Explore` denies `Agent` — so it adds a depth, not a tree. Every other in-repo def (`plan-architect-sonnet`, `sonnet-4-6`, `automouse-delegate`) and `~/.claude/agents/Explore.md` denies `Agent` outright, which is what keeps the carve-out a carve-out rather than a general loosening. Budget: ≤1 `Explore` per architect invocation, on top of the `/plan` Step-2 cap of 2 — run-wide ceiling **3**. Why — **the orchestrator owns triage**: the pipelines keep review/triage **in the orchestrator session** by design, whatever tier that session runs at (the review→score→filter step *is* triage — a coordinator would blind the orchestrator to the findings it filtered, and delegated judgment degrades — see `feedback_sonnet_proving_negatives`, `feedback_review_agent_full_diff`); and `/post-plan` is a single-context state machine whose Phase 3/5/6.5 gates read from main-session context, where nesting could only hide the filtered-out findings, not the survivor list the orchestrator still needs.

**Depth, not width.** This constrains *nesting*, not how many agents one level runs at once — § Fan out by independence governs width and leaves this untouched. The old "our fan-out is narrow (1–4 agents/phase)" justification is dropped, not defended: width is now explicitly allowed. The load-bearing reason is orchestrator-owns-triage, which is width-independent.

**Tripwire to revisit** (still live, for the surfaces above): a *measured* post-plan context-window problem, or a new workflow with genuinely wide fan-out and verbose per-agent intermediates.

**The `/plan` carve-out did not come from the tripwire.** Neither condition fired. It was taken on an explicit user decision, on a different argument: the architect could *see* an unknown mid-design and had no way to close it, because Step-2's fan-out is spent before the architect starts. That is a capability gap, not a context-window measurement. Recorded this way on purpose — the tripwire's evidentiary bar is not retroactively claimed to have been met.

> The bounded-checklist rationale for `/post-plan` Phase 6.5 condition (9) has moved to `agent-tiering-bounded-checklist.md`.

## Orchestrator context economics — delegate to never-hold, split don't self-clear

The context saving from a sub-agent comes from **delegation, not dismissal**. A sub-agent runs in its own window; when it finishes, only its final message returns — every intermediate tool call and result stays isolated and evaporates. So "spin up → dismiss → spin up fresh" beats inlining the same work because the bulk **never entered the orchestrator**, not because dismissal evicts it (dismissal reclaims nothing — the internals were never in the parent). Corollary: keep returns **thin** — pointers (`path:line`), not file bodies (`feedback_orchestrator_pass_pointers_not_contents`).

**The orchestrator cannot clear itself.** Its context grows monotonically by the sum of return summaries across a run. The `/clear`-equivalent lives one layer down, in sub-agent lifecycle: a fresh `Agent()` spawn = clean context + cold cache + the ~17–23K spawn overhead; continuing an agent via `SendMessage` = warm cache but carries the prior task's context forward. **Fresh spawn = clear; `SendMessage` = keep talking** — pick by whether the next task actually needs the prior one's context.

**The only true reset is the session boundary.** That is exactly why `/post-plan` runs in a **fresh** session (`workflow-continuity.md`: inline re-reads full implementation context every phase, costing several times a fresh run). For a run too large to fit one orchestrator context, the fix is **split into multiple plans/sessions**, not orchestrator-level sub-agent juggling — and nesting orchestrators is closed by design (see Nested Sub-Agents above).

**Automouse:** same rules, headless. Lean-orchestrator + thin returns apply as-is, but it cannot self-clear between phases — a very long plan pays for its accumulating orchestrator context until the session ends. If that measurably hurts, split the plan into stacked pieces; don't reach for nested orchestrators. **Tripwire to revisit:** a *measured* automouse orchestrator-context problem — then split the plan first, before reconsidering nesting.

## Prompt Style by Tier

**Haiku** (compensate for its tendency to stop at "enough"): lead with a concrete grep/find command · say "list EVERY match" / "do NOT skip files" when exhaustiveness matters · pre-resolve absolute paths · request structured output (table/list) · for checklists, "check EACH pattern, cite file:line or state not found" · never ask it to judge relevance, trace multi-hop flows, or relate a past event to the current context.

**Sonnet**: open-ended exploration, multi-file synthesis, ambiguous queries where the first grep might miss — current style is fine.

## `/plan` orchestrator model

The rows in agent-tiering.md tier sub-agents; the `/plan` session model is a separate call. The `plan-architect` is tiered by Step-3 precedence (xhigh → sonnet → opus) regardless of the orchestrator — a Sonnet `/plan` spawning `plan-architect` still gets an Opus-authored plan.

Tier the orchestrator by the judgment **it** retains:

- **Single backlog item** → **Sonnet** (Steps 2.5/3/4 orchestrator calls are light; same recipe-backed class the "Opus (delegated)" row routes to `plan-architect-sonnet`).
- **Multiple items in one pass** → **Opus** (cross-item PR decomposition, **dependency ordering**, tier-boundary splits). Cheaper: run each as its own **Sonnet** `/plan` and make only the ordering call yourself.

**Getting to a Sonnet orchestrator from an Opus session** — the operative rule now lives in `agent-tiering.md` § `/plan` orchestrator model (promoted there 2026-08-02, because a session that never Read this file was the one making the inline call). This file keeps the mechanics and the evidence:

- `/plan-prompt` (`.claude/skills/plan-prompt/SKILL.md`) drafts the handoff and fires it via `bin/plan-now`; the clipboard copy remains, for running it by hand.
- Because the fired run has no human in it, `/plan-prompt` Step 4.5 makes the *drafting* session resolve the user-facing forks that `/plan` Step 3.5 would otherwise put to a human, and `bin/plan-now` holds auto-merge on any fork that survives.
- **Measured 2026-08-02** over ~30 days of this project's transcripts (capacity-proxy $ as a throughput measure, not billing): 195 architect spawns, of which **114 (58%) ran inline inside interactive sessions** rather than in a dedicated `/plan` run. Orchestrator cost per spawn: **Opus $7.36** (53 spawns, $390) vs **Sonnet $2.66** (142 spawns, $377). The architect tier is unchanged either way, so the delta is pure orchestrator overhead.
- Sonnet-orchestrating is only cheaper if the context actually crosses the session boundary — the handoff prompt is the thing that carries it.

## Explore Agent Tiering

Two actors spawn Explore in a `/plan` run: the orchestrator at Step 2 (≤2) and the Opus `plan-architect` defs mid-design (≤1 each). Tier per prompt — don't default all Explore agents to one tier. Explore itself is pinned to Sonnet 4.6 (see agent-tiering.md § Sonnet 4.6 pins); the choice below is Haiku-vs-Sonnet-4.6 for the Explore *task*.

| Tier | Model param | Use for Explore | Examples |
|------|-------------|-----------------|---------|
| **Haiku** | `model: "haiku"` | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "does column X exist in migration Y" |
| **Sonnet 4.6** | *omit `model`* | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page" |

**Heuristic:** notice connections / judge relevance / trace data flow → omit `model` (Sonnet 4.6). Answerable by grep + format → `model: "haiku"`.
