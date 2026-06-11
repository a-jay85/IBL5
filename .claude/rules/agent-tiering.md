---
description: Sub-agent decision rules — when to spawn, when to skip, and which model to pick
last_verified: 2026-06-10
---

# Agent Tiering

When spawning sub-agents or writing plans that will spawn them, always tier by the reasoning the task actually needs — never default everything to Opus.

## Skip the Agent — Direct Tool Calls

Every sub-agent pays a fixed context overhead: system prompt, rules, and memory (~3-5K tokens depending on path-conditional loading) are loaded before the agent reads its prompt. This overhead is justified when the agent absorbs verbose output, runs in parallel, or needs multi-step reasoning. It is not justified for a single short-output command.

**Run it directly (no agent) when ALL of these are true:**
- The task is a single command or tool call (one bash invocation, one file read)
- The expected output is short (under ~50 lines)
- No other agents need to run in parallel at the same time
- The output won't persist in context across many subsequent turns

**Still spawn an agent when ANY of these are true:**
- Output is unpredictably verbose (large grep results, failing test suites with stack traces) — the agent absorbs it and returns a summary, keeping Opus's context clean
- Multiple independent tasks can run concurrently AND each produces verbose output — parallelism saves wall-clock time
- The task involves multiple sequential tool calls (run command, read file, run another command)

**PHPUnit and PHPStan are always direct Bash calls** — passing output is ~5 lines; even failures are typically under 50 lines. The 20-27K token agent overhead (system prompt + rules + context) dwarfs the output. Use `run_in_background` for parallelism without agent overhead.

The context-window cost compounds: every token of verbose output in Opus's context is re-sent on every subsequent turn. A Haiku agent that absorbs 500 lines and returns a 10-line summary saves Opus-rate tokens for the rest of the conversation.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, pattern-matching against named checklists, grep-and-format, mechanical lookups. The task can be answered by running commands and reporting results without judging relevance. |
| **Sonnet** | `model: "sonnet"` | Tasks requiring synthesis: "is this finding relevant to the current change?", cross-file traces, semantic compliance checks, rename sweeps needing judgment about call sites. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, interpreting ambiguous test failures, final code review, diff-triage. Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3. The one delegated-but-still-Opus case: the agent def carries `model: opus` + `effort: xhigh`, so planning runs at Opus depth in a clean sub-context. Do not pass an inline `model` override — the def owns it. |
| **Fable** | `model: "fable"` — **opt-in only, see gate below** | The rung above Opus, for tasks where the intelligence *ceiling* (not cost) is the binding constraint: JSB engine reverse-engineering / RNG-sub recovery, high-stakes negative proofs ("no path reaches X"), final diff-triage on the riskiest PRs (column-rename sweeps, FK-ordering migrations, the Olympics rewrite), cross-cutting ADR/rule authoring. At ~2× Opus cost, never the default — Claude proposes, you approve. |

## Fable Approval Gate

**Claude must never select Fable on its own.** Fable is opt-in: the user approves each use explicitly. This holds for the main session model *and* for any `model: "fable"` sub-agent.

When Claude judges a task Fable-appropriate (matches the Fable row above), it does **not** silently run on Opus and it does **not** switch to Fable. It **surfaces a suggestion** and waits for an explicit yes. The suggestion must include:

- **What** the task is and why it hits the intelligence ceiling (which Opus-row trait it exceeds — novel reasoning, exhaustive negative proof, high-blast-radius triage).
- **Pros** of Fable here: the specific failure mode Opus risks (missed aliased reference, wrong FK order, an edge case that reaches prod) and what one-shot correctness is worth.
- **Cons**: ~2× Opus token cost ($10/$50 vs $5/$25 per MTok); whether Opus is *likely sufficient* (most tasks are); that the gain is a ceiling-raise, not a guarantee.
- **Recommendation**: a clear "I'd use Fable here" / "Opus is probably fine, but flagging it" — not a neutral survey.

Default action absent approval: proceed on Opus (or the correct lower tier). Do not block work waiting for an answer — flag, recommend, and continue at the current tier unless the user says to escalate. Once the user approves Fable for a given task, that approval covers that task only; a new task re-triggers the gate.

Use `AskUserQuestion` when the Fable-vs-Opus call is a genuine fork worth pausing on; otherwise inline the suggestion in the response and keep going on Opus.

Planning is delegated rather than done in-session **only** because the custom `plan-architect` agent pins `effort: xhigh` (no per-call effort override exists on the built-in Plan agent). An A/B test proved it methodologically equivalent to the built-in. Everything else in the Opus row stays in-session — never delegate understanding.

## Nested Sub-Agents — Available, Deliberately Unused

Claude Code supports sub-agents spawning their own sub-agents (up to 5 levels deep). We keep **flat fan-out** — the Opus session owns every fan-out and absorbs every agent's output directly. Do not build nested orchestration into the recurring workflows (`/plan`, `/pr-review`, `/security-audit`, `/post-plan`, nightly).

Why flat wins here:

- **Our fan-out is narrow.** Workflows spawn 1–4 agents per phase, not the wide fan-out (with verbose intermediates to absorb) where nesting pays.
- **The recurring pipelines keep review/triage in Opus by design.** `final code review` and `diff-triage` live in the Opus row above — the review→collect→score→filter step *is* triage. Pushing it into a coordinator agent blinds Opus to the findings it filtered, and our experience is that delegated judgment degrades (see `feedback_sonnet_proving_negatives`, `feedback_review_agent_full_diff`).
- **`/post-plan` is a single-context state machine.** "Execute all phases sequentially in one response"; the Phase 3 diff-flags, Phase 5 verify-status, and Phase 6.5 auto-merge gate all read from main-session context. It's the only long autonomous run where context compounds — but also the run that poison-pills on a bad filter decision, and Phase 6.5 needs the scored survivor list in context regardless. Nesting could only hide the *filtered-out* findings; the thing Opus structurally still needs is the thing you can't remove. Upside and downside concentrate in the same workflow and the downside wins.

**Tripwire to revisit:** a *measured* post-plan context-window problem, or a new workflow that genuinely develops wide fan-out with verbose per-agent intermediates. Absent that, stay flat.

## Prompt Style by Tier

Prompts targeting Haiku must compensate for its tendency to stop exploring after finding "enough":

**Haiku prompts — be explicit:**
- Lead with a concrete grep/find command or search strategy
- Say "list EVERY match" or "do NOT skip files" when exhaustiveness matters
- Pre-resolve directory paths (absolute, not relative)
- Request structured output (table, numbered list) — not narrative
- For checklist tasks: "check EACH pattern and cite file:line if present, or state not found"
- Never ask Haiku to judge relevance, trace multi-hop flows, or decide whether a past event relates to the current context

**Sonnet prompts — current style is fine:**
- Open-ended exploration and "figure it out" delegation
- Multi-file synthesis and connection-drawing
- Ambiguous queries where the first grep might miss

## Explore Agents

Choose the tier per prompt — do not default all Explore agents to one tier:

| Tier | Use for Explore | Examples |
|------|-----------------|---------|
| **Haiku** | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "which files import WaiverService", "list all test files for DepthChart", "does column X exist in migration Y" |
| **Sonnet** | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page display", "how does module A interact with B", "what patterns does this module use" |

**Decision heuristic:** if the prompt asks the agent to notice connections, judge relevance, or trace data flow — use Sonnet. If the prompt can be answered by running a grep and formatting the output — use Haiku.

Plan-authoring tiering guidance (labeling each implementation phase Sonnet / Haiku / self, mechanical-recipe agents, bulk-sweep patterns) lives in `.claude/commands/plan.md` Step 3, where it is consumed.
