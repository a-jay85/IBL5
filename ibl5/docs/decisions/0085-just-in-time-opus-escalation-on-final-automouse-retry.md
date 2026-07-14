---
description: Why a genuinely-failed non-Opus automouse plan escalates only its FINAL retry to Opus with the prior attempt's capped failure report, and why environmental (refunded) failures never escalate or write a report.
last_verified: 2026-07-11
owner: A-Jay
---

# ADR-0085: Just-in-time Opus escalation on the final automouse retry

**Status:** Accepted
**Date:** 2026-07-11
**Deciders:** A-Jay

## Context

`bin/automouse-run` runs each queued plan through an attempt loop capped at `MAX_ATTEMPTS=3`. The impl model for a run is resolved once per attempt by `bin/lib/plan-impl-model`, which reads a line-1-anchored `impl_model:` frontmatter field and maps `sonnet`→`claude-sonnet-4-6`, `haiku`→`claude-haiku-4-5`, and everything else (missing/unknown/`opus`) to the safe default `claude-opus-4-8`. With per-plan tier labels bound (L13), a non-Opus model is now the common case for cheap plans.

Two gaps make a non-Opus night brittle:

1. **Same-model retries.** All three attempts run at the same resolved model. When a `sonnet`/`haiku` plan fails for a *genuine* (non-environmental) reason — the model could not produce a correct change — re-running the identical model twice more usually reproduces the same failure, then poison-pills. The strongest available model is never brought to bear before the plan is abandoned for the night.
2. **No failure artifact.** A genuinely failed attempt leaves no structured record of *why* it failed — only its slice of the shared dated `logs/YYYY-MM-DD.log`. There is nothing to hand a retry so it can avoid repeating the same mistake. (`bin/automouse-self-heal` writes only staleness/self-heal sidecars — never a per-attempt failure report; that artifact did not previously exist.)

Environmental failures are already handled separately: `should_impl_env_stop` refunds the attempt (`refund_attempt`) and breaks, so an env-stop never counts toward `MAX_ATTEMPTS`. Any escalation policy must leave that path untouched — an environmental stop is not a model deficiency and must never consume the Opus escalation or write a failure report.

This is nightly-ship-pipeline machinery: a wrong decision here burns whole unattended nights, so the change ships held (`auto_merge: false`, human signoff).

## Decision

On the **final** attempt only (the attempt numbered `MAX_ATTEMPTS`), if the plan's resolved base model is **not** `claude-opus-4-8`, `bin/automouse-run` overrides the impl model to `claude-opus-4-8` for that one attempt — a just-in-time escalation. Earlier attempts run at the plan's declared tier unchanged.

Four rules make this safe:

- **Any non-Opus tier escalates.** Sonnet *and* Haiku plans escalate to Opus on the final attempt. The backlog framed this as "Sonnet-model", but the principle is identical for Haiku — "escalate any non-Opus resolved model on the last retry" is the clean, uniform rule.
- **Trigger is decoupled from the report.** Escalation keys on attempt-count and resolved-model **only** — never on the existence of a failure sidecar. A leaked stale sidecar must not be able to mis-fire an escalation. The prior-attempt failure report is fed to the escalated retry **only if** the sidecar is actually present; its absence degrades gracefully to an un-augmented Opus retry.
- **A genuine failure persists a capped `.failure` report.** When an attempt ends with the plan still queued, no handoff produced, and it was **not** an env-stop (the genuine-failure fall-through), `bin/automouse-run` writes a per-attempt `<plan>.failure` sidecar: a **capped tail** of that attempt's log slice (bounded line/char count). The tail is the reliable baseline because a crashed/stall-killed agent may have written nothing structured itself; a short agent-authored "why I could not complete" note may be preferred *when present*, but the tail must always work without agent cooperation. The report is fed into the escalated retry's prompt via a trailing `PRIOR_FAILURE_REPORT=<path>` context line, which `bin/automouse-prompt-impl` reads when present.
- **The escalation DECISION is a pure helper, separate from model parsing.** The override lives in `bin/automouse-run` via a new pure helper `bin/lib/automouse-escalate-model` (base model + attempt number + `MAX_ATTEMPTS` → escalated-or-base model). `bin/lib/plan-impl-model` keeps its single responsibility (line-1 frontmatter → base model) and its existing test unchanged. The helper unit-tests exactly like `plan-impl-model` (Phase 3).

The `.failure` sidecar shares the lifecycle of the `.attempts` counter: it is evicted at **every** point `.attempts` is evicted (run-success path and `bin/automouse-queue` disposition/eviction points), so a stale report can never feed a bogus prior-failure into an unrelated future run.

## Alternatives Considered

- **Escalate to Opus on *every* failed attempt (not just the last).** Simpler trigger. Rejected: it pays the Opus premium on transient/early failures that the declared tier would clear on retry, defeating the point of per-plan tiering. Just-in-time on the final retry captures the recovery benefit at 1× Opus cost per genuinely-stuck plan.
- **Key escalation off the `.failure` sidecar's existence.** Would fold trigger and report into one check. Rejected: a leaked/stale sidecar (an eviction miss) would then silently mis-fire escalation on an unrelated plan. Decoupling trigger (attempt-count) from augmentation (report-if-present) makes a leak a cosmetic bug, not a behavioral one.
- **Put the override inside `bin/lib/plan-impl-model`.** One fewer file. Rejected: it would break that helper's pure line-1-parser contract and its test (`bin/test-automouse-impl-model`) by injecting attempt-state into a stateless parser. The decision belongs in the orchestrator (`automouse-run`), factored into its own pure, separately-tested helper.
- **Feed the whole log slice to the escalated retry.** Maximal context. Rejected: a multi-hour slice would blow the retry's context window or drown the signal in noise. A capped tail is bounded and reliable.

## Consequences

- Positive: a genuinely-stuck non-Opus plan gets one attempt at the strongest model, primed with the prior failure's context — materially higher odds of clearing before the night is abandoned, without paying Opus on every attempt.
- Positive: the `.failure` artifact is a durable, inspectable record of why an attempt failed (useful beyond escalation, for morning triage).
- Negative: a genuine 3rd-attempt failure now costs one Opus run. Bounded: at most one Opus escalation per plan per night, and only when the plan was already about to poison-pill anyway.
- Negative: one more sidecar in the lifecycle to evict correctly. Mitigated by adding it at every existing `.attempts` eviction site and testing eviction.
- This does **not** invert the "Opus-by-omission" default in `plan-impl-model` — an unlabeled plan still resolves to Opus. The eventual inversion (cheap tier by default, Opus only via escalation) is a token-spend direction tracked in `ibl5/docs/backlog/token-spend-backlog.md` (T1/T11), not part of this change.

## Lineage

Builds on L13 (per-plan impl-model tier binding, PR1 of this stacked pair), which makes a non-Opus resolved model the common case this ADR reacts to. Consumes `bin/lib/plan-impl-model`'s base-model resolution without modifying it. Does not supersede any prior ADR.

## References

- `bin/automouse-run` — attempt loop (`MAX_ATTEMPTS`, `.attempts` read/increment), per-attempt model resolution, the new genuine-failure branch and escalation override.
- `bin/lib/automouse-escalate-model` — the pure escalation-decision helper (Phase 2).
- `bin/lib/plan-impl-model` — the unchanged line-1 base-model parser.
- `bin/automouse-prompt-impl` — reads `PRIOR_FAILURE_REPORT` when present.
- `bin/automouse-queue` — `.failure` sidecar eviction sites (mirrors `.attempts`).
- `bin/test-automouse-escalation` — unit test for the escalation decision (Phase 3).
- `ibl5/docs/backlog/loop-engineering-backlog.md` — backlog item L14.
