---
description: RE faithfulness gate (read-only) for the L1 defect — the engine's empty-FGA offensive-rebound continuation loop, the suspected Cov(ORB/POSS,lnPPS) over-coupling carrier behind the residual Var(lnFGA) ~0.000397 over real that budget-blocks the count axis. Static decompile of jumpshot.exe 5.60's rebound chain answers the narrow loop question (does 5.60 decay P(OREB) per trip and/or cap continuations below the engine's maxOffensiveRebounds=8?). VERDICT two-part: FAITHFUL on decay/cap — 5.60's continuation loop is a flat-probability do/while with no per-trip decay and no hard cap (the engine's cap-8 is tighter but unreached, P(8 ORBs)≈1e-3..1e-5), so the engine's flat cap-8 loop matches 5.60; DIVERGENT on the determination formula — 5.60 gates each continuation behind TWO sequential rolls (gate-1 sqrt diminishing-returns team pick FUN_004e22a0, gate-2 linear off/(off+def)*0.5+0.25 retention FUN_004d6f00) and the engine models ONLY gate-2, omitting the sqrt gate-1. NO-GO on the loop-decay/cap hypothesis; L1 carrier positively identified as the dropped sqrt team-determination gate (mean inflation from the dropped multiplicative gate + linear-vs-sqrt curvature over-coupling, separable only by the future dynamic L1 instrument). Status Proposed; no engine code changed. Next /plan reproduces the two-gate structure, not a decay/cap.
last_verified: 2026-06-11
---

# ADR-0057: Rebound-continuation faithfulness gate — L1 loop is faithful; carrier is the dropped sqrt team-determination gate

**Status:** Proposed
**Date:** 2026-06-11

## Context

The JSB-fidelity chain (ADR-0041…0055) closed the make-value and variance axes;
ADR-0055 (PR #1043) landed faithful putback resolution and `Var(lnPPS)` now matches
the real archive. The surviving open defect is **L1** — the engine's empty-FGA
offensive-rebound *continuation* loop, where `Var(lnFGA)` still sits ~0.000397 over
real and the count axis is budget-blocked behind it. ADR-0049's NO-GO branch flagged
the *rebounder-strength channel* as the place to look. Before building an L1
instrument/fix, the engine's continuation loop (flat `orebProbability`, hard cap
`maxOffensiveRebounds = 8`, single off/def roll) must be checked against jumpshot.exe
5.60: **does 5.60 decay P(OREB) across trips within a possession, and/or cap
continuations tighter than 8?** If it does, the engine's flat cap-8 loop lets good
rebounders retry too efficiently and over-couples `Cov(ORB/POSS, lnPPS)`. This ADR
records the read-only static-decompile verdict; full trace +
exact-line citations in `engine/docs/l1-rebound-continuation-faithfulness.md`.

## Decision

**NO-GO on the loop-decay/cap hypothesis: do not add a per-trip P(OREB) decay or a
tighter continuation cap.** The decompile shows 5.60's continuation loop is a
flat-probability `do { …shot…rebound… } while (retain_flag == 1)` (handler
`FUN_004d6f00` resolves one rebound; the loop lives in caller `FUN_004d8570`) with
**no per-trip decay** — gate-2 P is recomputed as `off/(off+def)×0.5 + 0.25` each
trip with no trip index — and **no hard cap** (probabilistic exit only; the engine's
cap-8 is *tighter* but unreached, P(8 consecutive ORBs) ≈ 1e-3…1e-5). The engine's
flat, cap-8, single-roll loop is therefore **faithful** to 5.60 on the decay and cap
axes; adding decay/cap would diverge.

**The L1 carrier is positively identified as the dropped *sqrt
team-determination gate*.** 5.60 gates each continuation behind **two** sequential
rolls — gate-1, a sqrt diminishing-returns team-strength pick (`FUN_004e22a0`)
deciding which team rebounds, then gate-2, the linear retention roll. The engine
models **only gate-2** (`orebProbability` reuses 5.60's gate-2 formula *and constants*
as its sole off/def roll), omitting the sqrt gate-1. The downstream L1 build must
reproduce the **two-gate structure**, gated on a dynamic instrument separating the two
coupling mechanisms (below). This ADR is read-only — **no engine code changed**.

## Alternatives Considered

- **Add a per-trip P(OREB) decay to the continuation loop** — the original L1
  hypothesis (good rebounders retry too efficiently because P never decays). Rejected
  because: 5.60 applies the *same* flat gate-2 formula every trip with no trip-index
  term (decompile 92201-92203); adding decay would make the engine *less* faithful.
- **Cap continuations tighter than `maxOffensiveRebounds = 8`** — tighten the loop
  bound. Rejected because: 5.60 has **no** hard cap at all (probabilistic exit only);
  the engine's cap-8 is already tighter and is effectively unreached, so it is not the
  carrier.
- **Declare "carrier is elsewhere / indeterminate" and stop** — treat the loop as
  matching and punt. Rejected because: the two-gate vs one-gate divergence in the
  rebound-*determination* formula (the dropped sqrt gate-1) *is* the
  `Cov(ORB/POSS, lnPPS)` carrier ADR-0049 pointed at — a positive identification, not
  a dead end.

## Consequences

- Positive: the engine's faithful flat/cap-8 continuation loop is protected — a future
  plan will not regress it by bolting on a non-faithful decay or cap.
- Positive: the L1 search is narrowed from "the continuation loop" to a single,
  buildable target — reproduce the sqrt diminishing-returns team-determination gate
  (`FUN_004e22a0`) ahead of the existing linear retention roll.
- Negative: the mean-inflation (dropped multiplicative gate → engine continues too
  often) vs curvature-over-coupling (linear vs sqrt at matched mean) split is
  **indeterminate from static decompile** — it needs the future L1 instrument to dump
  per-trip gate-1/gate-2 probabilities and the marginal ORB→PPS coupling before the
  fix shape is fixed. This ADR is **Proposed**, not Accepted: it gates, it does not
  build.

## References

- `engine/docs/l1-rebound-continuation-faithfulness.md` — full RE trace, side-by-side
  table, and per-question decompile-line citations (the findings doc this ADR
  records).
- `engine/internal/sim/rebound.go` — engine `orebProbability` (gate-2 formula +
  constants) and `teamReboundStrength` (linear, no sqrt blend).
- `engine/internal/sim/possession.go` — engine continuation loop and
  `maxOffensiveRebounds = 8` cap.
- ADR `ibl5/docs/decisions/0049-possession-count-coupling-channel.md` — the NO-GO branch
  that flagged the rebounder-strength channel as the next L1 suspect.
- ADR `ibl5/docs/decisions/0055-faithful-putback-shot-resolution.md` — the closed make-value /
  `Var(lnPPS)` constraint this work must not regress.
