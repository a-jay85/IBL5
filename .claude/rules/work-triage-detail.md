---
description: Read-on-demand detail for work-triage — NO auto-attach trigger (its only `paths:` entry is out-of-repo and never matches); Read it when work-triage.md cites it. Covers measurement context for the inline-Opus leak, ADR-0067 gateway framing, hard-trigger gate properties (sub-agent exemption, per-turn scoping, escape hatch, self-test), the cross-worktree straddle gate's four-rung remedy ladder, inline-vs-delegated criteria, safety-mirror backstop, and repeat-polling spend rationale.
last_verified: 2026-08-14
paths:
  - "~/.claude/hooks/plan-gate-edit.sh"
---

# Work Triage — Detail

Read-on-demand companion to `work-triage.md` (always-loaded).

**This file never auto-attaches.** Its `paths:` entry points at a hook outside the repo, and out-of-repo entries do not match (`doc-freshness.md` § `paths:` residency semantics). The `paths:` key is kept non-empty only so the doc isn't promoted to always-loaded — access is by explicit Read, at the points where `work-triage.md` names this file. Do **not** "fix" it by globbing `.claude/rules/work-triage.md`: that is the compaction cascade PR #1730 removed.

## Execution routing context

The measured leak (2026-07-07): ~90% of Opus main-thread calls were mechanical; 44% of sessions breached 150K context — the dumb-zone delegation rules exist to prevent this. An ad-hoc verdict silently defaulting to "the Opus session implements inline" is exactly what the Sonnet-execution-routing rule guards against.

The user should never have to ask "is this big enough for a `/plan`?" — that judgment is yours to volunteer. This is the **gateway** of the deployment funnel (ADR-0067): everything downstream flows from this call.

## Hard trigger

### Why a numeric rule

The prose routing guidance in `work-triage.md` is a judgment call, and judgment loses to batch momentum — measured 2026-07-21: a 30-reference / 18-file rename was swept inline on Opus *after* the ≥125K dumb-zone `systemMessage` had already fired and been ignored. A warning you can read past is not a control.

### Gate properties

`~/.claude/hooks/plan-gate-edit.sh` § Check 1 enforces the ≥5-distinct-files rule by **denying** the Edit/Write tool call. The threshold is the hook's `SWEEP_LIMIT` constant — raised from 3 to 5 on 2026-07-22, once repo-scoping had removed the scratch-file false positives and 3 proved to bind on ordinary multi-file changes rather than on sweeps. The self-test reads `SWEEP_LIMIT` from the hook, so retuning it needs no test edit. Properties worth knowing:

- **Sub-agent edits are exempt** (`agent_id` present in the PreToolUse payload). The delegate you spawn in response is never blocked — otherwise the gate would brick the delegation it exists to force. Note sub-agents share the parent's `session_id` *and* `transcript_path`, so `agent_id` is the only usable discriminator.
- **Per user turn, not per session** (keyed on `prompt_id`). A handful of unrelated one-file edits spread across a long session is not a sweep and doesn't trip it.
- **Distinct files, not calls** — editing one file ten times counts once.
- **Repo files only** — a path counts only when it resolves inside a git working tree; `/tmp` scratch and `~/.claude` hook/settings edits never accrue, so they can't push a later repo file over the line. A new file in a not-yet-created repo subdirectory still counts, because the check walks up to the nearest existing ancestor directory.
- **Fails open** on a malformed payload; never blocks editing because a field was missing.
- **Escape hatch, deliberately loud:** `touch /tmp/claude-sweep-override-<prompt_id>` (example) releases it for that turn. Legitimate when the edits are genuinely *entangled* with the design — for example authoring a rule doc, its detail companion, and the ADR recording the decision together. Using it silently defeats the gate: **say out loud that you're overriding and why**, in the same turn.

Self-test: `bash ~/.claude/hooks/test-plan-gate-edit.sh`

### Other checks in the same hook

`plan-gate-edit.sh` is one file registered on `Read|Edit|Write`, but only Check 1 implements the sweep trigger. Don't read a deny from it as "I hit the ≥5-file limit" without reading the message — the **cross-worktree straddle gate** (Check 3) denies a **Read** whose target resolves inside a *different* worktree of this same repo than the session cwd, because touching it loads that tree's `.claude/rules/*.md` on top of the byte-identical set already resident, re-sent every turn for the rest of the session (ADR-0046). Reading back into the tree whose rules are already loaded stays allowed.

**Its remedy is not one thing — the deny message prints a four-rung ladder, and the right rung depends on the direction.** Take the first that fits; don't skip to the override:

1. **`git show <ref>:<path>` from your own tree** when you only need to READ the file. The object store is shared across the repo family, so no foreign path is touched and nothing new loads. The gate probes first and prints the exact command **only** when the path is committed on that tree's branch *and* unmodified there; otherwise it says why rung 1 is unavailable and routes you on (a `git show` of a locally-modified file returns pre-edit bytes silently, which is worse than an error).
2. **A gate-exempt sub-agent** (`Agent(subagent_type: "sonnet-4-6")`, omit `model`) for bounded work in that tree. Its rules load in ITS context and are discarded on return. **This is the default for cross-tree edits** — delegation, not re-rooting.
3. **Relocate or hand off**, direction-dependent: `EnterWorktree` when the session is in the **main checkout** and the target is a worktree; a **fresh session** rooted in the target when the session is already in a worktree (`ExitWorktree` no-ops for a session *launched* in a worktree, and a direct worktree→worktree `EnterWorktree` is rejected — leave the tree dirty and hand off in prose). Never relocate INTO the main checkout: it is read-only reference (ADR-0062).
4. **The escape hatch** — `touch /tmp/claude-tree-override-<session_id>` (example), keyed per **session**, not per turn like Check 1's — for the genuine both-trees-at-once case (diffing two worktrees).

Why the ladder replaced a bare "use `EnterWorktree`": in the gate's first 27h, all 8 override releases came from sessions already inside a worktree, where re-rooting is exactly the rung that does not work. Prescribing it there taught callers the remedy was broken and sent them to the override.

## Repeat-polling

A poll loop re-reads the full context every call (~81K tokens); eight 60s checks burns ~650K tokens vs ~81K for one deferred check. **Never poll on the main thread.**

**Instead:** `run_in_background: true` + Monitor (re-invokes on completion, main thread free), or ScheduleWakeup matched to the expected completion time (one ~480s wakeup for a CI run beats eight 60s checks). Avoid repeated `gh pr checks` / `gh run watch` inline loops — both re-read full context per call.

**Headless exception:** no re-invocation in headless/automouse — block until exit or structure as sequenced plan phases. See `agent-tiering-detail.md`.

### The readiness predicate

Moving the poll off the main thread fixes *where* it runs, not whether it is asking the right question. A watcher with a wrong readiness predicate is worse than no watcher: it returns a confident verdict on an unfinished artifact, and the caller has no reason to doubt it.

**Valid completion signals:** the process exiting; its launchd/job label disappearing from `launchctl list`; a runner's own verdict line in its log; a sentinel the producer writes **last** (including a scratch/draft file it deletes on completion). **Not a completion signal:** mtime, size, or existence of a file the producer appends to incrementally — those all go true mid-write.

**Prefer the producer's verdict over your own.** If the producing process already runs the check you were going to run, wait for it and read its answer. Recomputing invites the two to disagree, and yours is the one running against a partial artifact.

Observed 2026-07-27: a watcher for detached headless `/plan` runs (`bin/plan-now`) used "final plan is newer than its draft" as the done-test. `/plan` appends the final plan section-by-section, so the predicate went true while the file was still truncated, `bin/check-plan` failed on the partial text, and a passing plan was reported as `CHECK-PLAN FAILED`. Three valid signals were available and unused: the launchd label vanishing, `bin/plan-now`'s own `RESULT ok — check-plan PASSED` log line, and the draft file being deleted. This is the same family as the ADR-0096 identity bug — a verdict trusted from a heuristic instead of from the run's own self-report.

## Safety mirror backstop

**What counts as a gate removal/weakening** (the resident bullet carries the trigger; these are the examples): deleting, relaxing, or disabling an enforcement mechanism — a hook deny, a `bin/check-plan` gate condition, a `plan-gate-edit` check, a `/post-plan` Phase 6.5 arming condition. **Bootstrap hazard** means the change rewrites the arming, escalation, or auto-merge rules that govern its own merge. Same trigger as `/plan` Step 3's `plan-architect-xhigh` escalation — full clause in `.claude/skills/plan/SKILL.md` Step 3 check 1.

Whatever still ships ad-hoc is caught at PR time by `/post-plan` Phase 6.5 condition (9) — but designing it in the plan beats relying on the backstop. The safety-mirror trigger list in `work-triage.md` routes security work into `/plan` *before* the backstop exists; losing it strands the backstop as the only protection.

## Inline vs. delegated

Stay inline (Opus edits directly) only when:
- the edits and the design are genuinely **entangled** — writing the recipe would mean making each edit-level judgment anyway, so the handoff buys nothing; or
- the chunk is **trivial** — a one-or-two-edit change where the sub-agent's fixed spawn cost (~17–23K tokens [CORRECTED 2026-08-14: was "~3–5K"; measured p50 spawn context is 17–23K], `agent-tiering-detail.md` § Skip the Agent) exceeds the work being moved.

Either way the routing decision is **stated, not silent** — one line, like the triage verdict. The user should see which way it went and be able to override in the moment.
