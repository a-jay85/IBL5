---
description: Read-on-demand detail for agent-tiering: skip-vs-spawn heuristic, fan-out and nesting rationale, task-type boundary, orchestrator context economics, /plan orchestrator evidence, prompt style, Haiku 5.5 measurement and price cliff, extra Sonnet pins. Attaches only on `.claude/agents/*.md`. Fable test and bounded checklist live in their own files.
last_verified: 2026-10-08
paths:
  - ".claude/agents/*.md"
---

# Agent Tiering — Detail

Read-on-demand companion to `agent-tiering.md` (always-loaded). The parent holds the
operative Tier table. This file holds the longer rationale: the skip-vs-spawn heuristic,
flat-fan-out and orchestrator context economics, and prompt style. The Fable test is in
`agent-tiering-fable.md`.

## Skip the Agent — Direct Tool Calls

Each sub-agent costs ~17-23K tokens (system prompt, rules, and memory, loaded before its prompt; measured p50 spawn context), and its output re-loads in Opus's context every later turn.

**Measured 2026-08-25** (139 Opus main sessions, 1,580.7 Mtok total):

Delegatable tool results: 8,292 calls / 4.73 Mtok. p50 result 194 tokens, p90 1,272, p95 2,039 (~8 KB on disk), p99 5,389. Calls ≥ 2,000 tokens are 5.2% of calls but **43.6% of total residue**. 87 of 139 sessions (63%) spawned nothing.

**This gives the "~50 lines" threshold below a measured basis.** ~50 lines of output lands right around the measured p50 of 194 tokens — roughly 90× cheaper than the 17–23K a spawn costs before it does any work. The figure stands exactly as written; it was an estimate and is now an estimate the data agrees with.

**The fat-tail batching rule names *which* calls are worth a spawn at all.** Not how few spawns to make. `agent-tiering.md` § Fat-tail delegation lets two fat calls per turn through and denies the 3rd, routing it and the rest into one Haiku digest spawn. A call is **fat** when it is a `Read` ≥ 8 KB, or a Bash command in: bare `cat`, `git log` with no bound, `find` with no limit, a full Playwright run (hook `output-guard.sh` Check F; fails open; the deny message names the one-off override). It identifies the tail worth delegating (the 5.2% carrying 43.6% of the residue). **One spawn is the default for wall-clock reasons:** those calls are cheap to *run*, so serializing them in one agent costs almost no wall-clock. When a batched item is genuinely long-running and independent (a full Playwright run beside an unbounded log dump), fan out instead. See § Fan out by independence.

**Treat this as unused headroom.** The 2026-08-25 spawn-count comparison (135 spawns against a ~353 break-even) was corrected on 2026-09-23 by `bin/measure-delegate-cost`: a Sonnet automouse impl session costs $2.65 at p50 against $4.33 for Opus (`work-triage-detail.md` § Inline vs. delegated). The finding is room to route more of the fat tail through a sub-agent, which no token saving has banked.

**Run directly (no agent) when ALL hold:** single command/tool call · output under ~50 lines · nothing else to run in parallel · output won't persist across turns.

**Spawn an agent when ANY hold:** output unpredictably verbose (large grep, failing suites with stack traces) and the agent can return a summary · multiple independent verbose tasks run concurrently · the task is multiple sequential tool calls.

**PHPUnit and PHPStan are always direct Bash calls** — passing output is ~5 lines, failures usually under 50; agent overhead dwarfs it. Use `run_in_background` for parallelism without an agent — **but only in the interactive harness**, where a finished background task re-invokes you. In a **headless** run (`claude -p`, e.g. `/post-plan` under automouse) there is no re-invocation: a live background task at turn-end stall-kills the run — run blocking, or poll `BashOutput` to completion in-turn (post-plan `SKILL.md` Phase 5).

> When to use Fable (incl. the asm-level static-RE default) lives in `agent-tiering-fable.md`.

### Fan out by independence

When you spawn, the discriminator is independence. The old "token spend outranks wall-clock time; batch N related tasks into one agent" priority is **retired** (2026-09-08, explicit user decision): the measured cost basis (see the headroom note above) makes (N-1) × 17-23K of extra spawns negligible. Wall-clock time is the scarce resource; token spend is not.

- **Fan out** when the tasks are **mutually independent** — issue every `Agent` call in one message so they run concurrently, collapsing a serial chain into one wall-clock unit. Work that would otherwise run serially and *can* run concurrently should; "that costs another spawn" is no longer a reason not to.
- **Batch into one agent** when the tasks are **sequential or dependent** — splitting those re-pays the spawn overhead for **zero** wall-clock gain, and hands each agent a partial view of a coherent change.

Read the two together: logically-distinct-but-dependent is still one agent; logically-related-but-independent may fan out. "Each agent needs its own context" (independent worktrees, isolating verbose output) is still a *sufficient* reason to split — it is no longer a *necessary* one.

**Three limits on fan-out.**

1. **Trivial work stays inline.** Below the ~17–23K spawn cost there is no serial baseline worth beating; fanning three trivial edits out is three losing trades, not one saved minute (`work-triage-detail.md` § Inline vs. delegated).
2. **Interactive main thread only.** Under `claude -p` (headless `/post-plan`, automouse) an async `Agent` delegate emits nothing on the parent stream for its whole runtime — precisely why the automouse watchdog sits at 30 min rather than 10 (`automouse-workflow.md`). Wide concurrent fan-out there multiplies stall-kill exposure. Keep headless runs narrow.
3. **Width, not depth.** Fanning wider at one level does not license *nesting* — flat fan-out below is unchanged. Same-tier Sonnet→Sonnet delegation also remains waste, concurrent or not.

## Sonnet 5.5 pins, other surfaces

The resident file lists Explore and `sonnet-5-5`. The rest, each carrying its own pin in the def or skill frontmatter:

| Surface | Def / File | Spawn / invoke with |
|---------|-----------|---------------------|
| **Automouse impl delegates** | `.claude/agents/automouse-delegate.md` | `subagent_type: "automouse-delegate"`, omit `model`. Fired by `bin/automouse/prompt-impl` for each `### Delegate` packet. |
| **Plan architect (Sonnet tier)** | `.claude/agents/plan-architect-sonnet.md` | `subagent_type: "plan-architect-sonnet"`, omit `model`. Selected by `/plan` Step 3 precedence. |
| **`/pr-review` and `/security-audit` runners** | Their `SKILL.md` frontmatter | `model: claude-sonnet-5-5` in frontmatter; no spawn change. |

## Boundary keys on task type, not model capability

Re-validated 2026-06-30 against Sonnet 5. The Opus-only column (final code review, diff-triage, rule/ADR authoring, novel reasoning, ambiguous failures) stays Opus because **"never delegate understanding" is a delegation rule**, and waiting for a smarter model does not change it. The cost was never Sonnet's raw ability. It is that the orchestrator loses the findings it would otherwise filter (`feedback_sonnet_proving_negatives`, `feedback_review_agent_full_diff`). A larger Sonnet context only strengthens the "spawn Sonnet to absorb verbose output" rationale. **Tripwire to revisit:** a model generation where the delegation failure mode itself changes (e.g. a coordinator that can surface its own filtered-out findings). A higher capability score alone does not count.

## Nested Sub-Agents — One Carve-Out, Otherwise Unused

Sub-agents can spawn sub-agents. `CLAUDE_CODE_MAX_SUBAGENT_SPAWN_DEPTH` is **3** in `~/.claude/settings.json` (the rule previously claimed 5). We keep **flat fan-out**: the orchestrator session owns every fan-out and absorbs every agent's output. Do not nest in `/pr-review`, `/security-audit`, `/post-plan`, or automouse. **One carve-out, in `/plan` only:** `plan-architect` and `plan-architect-xhigh` may spawn at most **one** `Explore` for a question that surfaces mid-design (`.claude/skills/plan/_architect-contract.md` § Mid-design exploration). That subtree terminates. `Explore` denies `Agent`, adding a depth rather than a tree. Every other in-repo def (`plan-architect-sonnet`, `sonnet-5-5`, `automouse-delegate`) and `~/.claude/agents/Explore.md` denies `Agent` outright, which is what keeps the carve-out a carve-out rather than a general loosening. Budget: ≤1 `Explore` per architect invocation, on top of the `/plan` Step-2 cap of 2. Run-wide ceiling: **3**. The orchestrator owns triage: the pipelines keep review/triage **in the orchestrator session** by design, whatever tier it runs at, because a coordinator would blind the orchestrator to the findings it filtered and delegated judgment degrades (`feedback_sonnet_proving_negatives`, `feedback_review_agent_full_diff`). `/post-plan` is a single-context state machine whose Phase 3/5/6.5 gates read from main-session context.

**Depth, not width.** This constrains *nesting*, not how many agents one level runs at once. § Fan out by independence governs width and leaves this untouched. Width is explicitly allowed; the load-bearing reason is orchestrator-owns-triage, which is width-independent.

**Tripwire to revisit** (still live, for the surfaces above): a *measured* post-plan context-window problem, or a new workflow with genuinely wide fan-out and verbose per-agent intermediates.

The `/plan` carve-out did not come from the tripwire. It was an explicit user decision: the architect could see an unknown mid-design and had no way to close it, because Step-2's fan-out is spent before the architect starts. That is a capability gap, and the tripwire's bar is not retroactively claimed to have been met.

> The bounded-checklist rationale for `/post-plan` Phase 6.5 condition (9) has moved to `agent-tiering-bounded-checklist.md`.

## Orchestrator context economics — delegate to never-hold, split don't self-clear

The context saving from a sub-agent comes from **delegation, not dismissal**. A sub-agent runs in its own window and only its final message returns, so the bulk **never entered the orchestrator**. Dismissal reclaims nothing, because the internals were never in the parent. Keep returns **thin**: pointers (`path:line`) in place of file bodies (`feedback_orchestrator_pass_pointers_not_contents`).

**The orchestrator cannot clear itself.** Its context grows monotonically by the sum of return summaries across a run. The `/clear`-equivalent lives one layer down, in sub-agent lifecycle: a fresh `Agent()` spawn = clean context + cold cache + the ~17–23K spawn overhead; continuing an agent via `SendMessage` = warm cache but carries the prior task's context forward. **Fresh spawn = clear; `SendMessage` = keep talking** — pick by whether the next task actually needs the prior one's context.

**The only true reset is the session boundary.** That is exactly why `/post-plan` runs in a **fresh** session (`workflow-continuity.md`: inline re-reads full implementation context every phase, costing several times a fresh run). For a run too large to fit one orchestrator context, the fix is **split into multiple plans/sessions**, not orchestrator-level sub-agent juggling — and nesting orchestrators is closed by design (see Nested Sub-Agents above).

**Automouse:** same rules, headless. It cannot self-clear between phases, so a very long plan pays for its accumulating orchestrator context. If that measurably hurts, split the plan into stacked pieces before reconsidering nesting.

## Haiku 5.5 measurement

A/B on 2026-10-08 (Sonnet twice, Haiku once, bar sealed first): `~/claude-plans/_reports/2026-10-08-haiku-5-5-vs-sonnet-5-5-ab.md`.

| Surface | Tasks | Verdict | $/task S, H |
|---|---|---|---|
| `explore` | 8 | PASS | 0.154, 0.010 |
| `manual_test` | 8 | FAIL | 0.059, 0.003 |
| `fat_tail_digest` | 6 | PASS | 0.082, 0.005 |
| `security_probe` | 5 | PASS (on Haiku) | n/a, 0.007 |
| `backlog_housekeeping` | 0 | NO-LIVE-SURFACE | n/a |

Only parity moves a surface. Cost never offsets a quality drop; a refusal fails it (`case-refusal-fail`, `case-price-cliff`). Agent D stays on Sonnet until its trigger holds (20+ examples per category, 4 weeks of precision data).

**Price cliff.** Haiku bills $0.10/$0.50 per MTok up to 100K prompt tokens per request (cache included) and $0.50/$2.50 above, 0.25 of Sonnet's input rate. Send jobs past ~100K tokens to Sonnet.

## Prompt Style by Tier

**Haiku 5.5** (tips checked against the 2026-10-08 A/B, § Haiku 5.5 measurement): lead with a concrete grep/find command · ask for every match when exhaustiveness matters, and for checklists, check each pattern and cite file:line or state not found · pre-resolve absolute paths · name the output block the caller parses · never ask it to judge relevance, trace multi-hop flows, or relate a past event to the current context.

**Sonnet**: open-ended exploration, multi-file synthesis, ambiguous queries where the first grep might miss — current style is fine.

## `/plan` orchestrator model

The rows in agent-tiering.md tier sub-agents; the `/plan` session model is a separate call. The `plan-architect` is tiered by Step-3 precedence (xhigh → sonnet → opus) regardless of the orchestrator — a Sonnet `/plan` spawning `plan-architect` still gets an Opus-authored plan.

Tier the orchestrator by the judgment **it** retains:

- **Single backlog item** → **Sonnet** (Steps 2.5/3/4 orchestrator calls are light; same recipe-backed class the "Opus (delegated)" row routes to `plan-architect-sonnet`).
- **Multiple items in one pass** → **Opus** (cross-item PR decomposition, **dependency ordering**, tier-boundary splits). Cheaper: run each as its own **Sonnet** `/plan` and make only the ordering call yourself.

Getting to a Sonnet orchestrator from an Opus session: the operative rule is in `agent-tiering.md` § `/plan` orchestrator model. Mechanics and evidence:

- `/plan-prompt` (`.claude/skills/plan-prompt/SKILL.md`) drafts the handoff and fires it via `bin/plan-now`; the clipboard copy remains, for running it by hand.
- Because the fired run has no human in it, `/plan-prompt` Step 4.5 makes the *drafting* session resolve the user-facing forks that `/plan` Step 3.5 would otherwise put to a human, and `bin/plan-now` holds auto-merge on any fork that survives.
- **Measured 2026-08-02** over ~30 days of this project's transcripts (capacity-proxy $ as a throughput measure, not billing): 195 architect spawns, of which **114 (58%) ran inline inside interactive sessions** rather than in a dedicated `/plan` run. Orchestrator cost per spawn: **Opus $7.36** (53 spawns, $390) vs **Sonnet $2.66** (142 spawns, $377). The architect tier is unchanged either way, so the delta is pure orchestrator overhead.
- Sonnet-orchestrating is only cheaper if the context actually crosses the session boundary — the handoff prompt is the thing that carries it.

## Explore Agent Tiering

Two actors spawn Explore in a `/plan` run: the orchestrator at Step 2 (≤2) and the Opus `plan-architect` defs mid-design (≤1 each). Tier per prompt. Explore is pinned to Sonnet 5.5; the choice below is Haiku vs Sonnet 5.5 for the task.

| Tier | Model param | Use for Explore | Examples |
|------|-------------|-----------------|---------|
| **Haiku** | `model: "haiku"` | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "does column X exist in migration Y" |
| **Sonnet 5.5** | *omit `model`* | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page" |

**Heuristic:** notice connections / judge relevance / trace data flow → omit `model` (Sonnet 5.5). Answerable by grep + format → `model: "haiku"`.

Measured 2026-10-08: Haiku 5.5 matched Sonnet 5.5 on 8 grep-and-list tasks (§ Haiku 5.5 measurement).
