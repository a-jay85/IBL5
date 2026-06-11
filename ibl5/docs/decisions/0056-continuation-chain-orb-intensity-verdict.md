---
description: Verdict from the L1 continuation-chain / ORB-intensity instrument (#1045). The instrument RULES OUT ORB-continuation intensity as the home of the engine's wrong-signed Cov(lnFGA,lnPPS): Cov(ORB/POSS,lnPPS) is engine −0.000151 vs real −0.000115 (same sign, near-equal magnitude — the channel is faithful), so the residual shots-per-possession anti-coupling (Cov(ln(FGA/POSS),lnPPS) engine −0.000800 vs real +0.000027) does NOT live in the offensive-rebound loop. A decay/cap fix aimed at the ORB↔inefficiency coupling would be misdirected. The instrument DID surface a separate, same-units defect: engine ORB/POSS level ~23% too high (0.194 vs 0.158) and Var(ORB/POSS) ~2.3× too compressed (0.000084 vs 0.000190) — a rebound level/dispersion gap, not a coupling-sign gap, with read #2 showing the engine's continuation tail decays faster than memoryless (realized k≥3 0.0022 vs geometric 0.0041). Decision: NO ORB decay/cap-for-coupling PR; the coupling search stays on the broader shots-per-possession structure (initial vs transition shot-frequency↔efficiency) and the still-open count axis (Var(lnPOSS) ~2.5× narrow). Any ORB level/dispersion fix is independently warranted but must be scoped as a rebound-fidelity fix with its own faithfulness gate, never sold as the coupling fix.
last_verified: 2026-06-10
---

# ADR-0056: Continuation-chain / ORB-intensity verdict — ORB-continuation ruled out as the coupling culprit

**Status:** Accepted
**Date:** 2026-06-10

## Context

ADR-0049 split the engine's wrong-signed `Cov(lnFGA,lnPPS)` via the exact identity
`lnFGA = lnPOSS + ln(FGA/POSS)` and localized the dominant share to a **shots-per-possession
anti-coupling** the real league lacks — the empty/miss-driven FGA loop (ADR-0042). ADR-0055
(#1043) then closed the `Var(lnPPS)` over-dispersion (faithful putback shot resolution), but a
re-baseline on landed master showed it closed only ~8% of the **shots-per-possession channel**
gap (`Cov(ln(FGA/POSS),lnPPS)` −0.000873 → −0.000800); the make-value levers (variance
ADR-0053, form ADR-0055) are exhausted.

The surviving suspect was the loop's **frequency/depth** structure. The offensive-rebound
continuation chain is the obvious candidate: a putback after an ORB is a low-efficiency extra
shot in the same trip, so if the engine over-produces ORB continuations and couples them to
inefficiency, that would manufacture exactly the wrong-signed `Cov(ln(FGA/POSS),lnPPS)`.

PR #1045 built the **L1 continuation-chain / ORB-intensity instrument** (read-only, no engine
behavior change, golden byte-identical) to answer the localizing question the program was
built around: **does the residual anti-coupling live in continuation INTENSITY (too many ORBs,
coupled to inefficiency — fixable by a later decay/cap PR), or is it intrinsic to the broader
shot model (terminal for this channel)?**

This ADR records the **measured verdict**. The instrument PR shipped no ADR by design (a
read-only measurement extension fires no ADR trigger); the verdict it produced is the decision
this ADR captures.

## Decision

**The instrument RULES OUT ORB-continuation intensity as the home of the wrong-signed
covariance. The ORB-intensity↔efficiency coupling is faithful; the residual shots-per-possession
anti-coupling does NOT live in the offensive-rebound loop. No ORB decay/cap-for-coupling PR
follows. A separate ORB level/dispersion defect was surfaced and is independently — but
distinctly — actionable.**

### The measured verdict (regular bucket, committed artifact — runs=20, stride=1)

`engine/internal/validate/testdata/calibration-5.60-20260610-continuation-depth.json`
(fidelity block, game type 2):

**Part A — ORB-intensity channel `ORB/POSS`, on the SAME Dean-Oliver proxy as the poss split:**

| term | engine | real (.sco) | reading |
|------|--------|-------------|---------|
| `Cov(ORB/POSS, lnPPS)` | **−0.000151** | **−0.000115** | **same sign, ~1.3× magnitude — FAITHFUL, not the wrong-signed culprit** |
| mean `ORB/POSS` (level) | 0.19414 | 0.15750 | engine **~23% too high** — a real LEVEL defect |
| `Var(ORB/POSS)` | 0.000084 | 0.000190 | engine **~2.3× too compressed** — a real DISPERSION defect |

**Part B — engine-only continuation-depth distribution (49,302 possessions pooled):**

| k (offensive-rebound continuations per trip) | P(k) | geometric-implied (p=0.1597) |
|---|---|---|
| 0 | 0.8313 | 0.8403 |
| 1 | 0.1499 | 0.1342 |
| 2 | 0.0167 | 0.0214 |
| ≥3 | **0.0022** | **0.0041** |

mean k = 0.1900, Var k = 0.2023 (exact, from Σk/Σk² — not the capped buckets).

Three reads off Part B (interpretive `t.Logf`, not gated):
- **Read #1 (reconciliation):** count-segmented mean k (0.1900) vs proxy ORB/POSS (0.1941),
  ratio 0.979 — within the 15% loose band, so the authoritative `EventPossessionStart`
  segmentation and the Dean-Oliver proxy reconcile (the expected count≠proxy gap, ADR-0049).
- **Read #2 (tail shape):** the realized k≥3 tail (0.0022) is **thinner** than a memoryless
  geometric tail (0.0041) and k=1 is **fatter** (0.1499 vs 0.1342) ⇒ the engine's per-trip
  continuation probability **decays** (deep chains rarer than memoryless), it is not a
  uniformly inflated cap/floor.
- **Read #3 (PPS-tercile tail split):** low-PPS teams own only marginally more continuation
  (mean k 0.1950 / P(k≥3) 0.0024) than high-PPS teams (0.1855 / 0.0021) — a **weak**
  cross-team intensity↔efficiency gradient, consistent with Part A's faithful covariance.

### The verdict — answers the localizing question with a NEGATIVE result

1. **PRIMARY (the load-bearing negative): ORB-continuation is NOT the covariance culprit.**
   `Cov(ORB/POSS, lnPPS)` is engine −0.000151 vs real −0.000115 — the **same sign** and only
   ~1.3× the real magnitude. The engine's continuation↔inefficiency coupling tracks reality;
   the channel is faithful. The headline residual lives in `Cov(ln(FGA/POSS), lnPPS)`
   (engine −0.000800 vs real +0.000027), and the ORB-rebound loop does **not** account for its
   wrong sign. A decay/cap fix aimed at the ORB↔inefficiency coupling would target a faithful
   channel — **misdirected.** (Units caveat: `ORB/POSS` is a raw ratio, the headline channel is
   log; they are not additively subtractable. The robust, unit-safe claim is the **sign and
   relative magnitude** — engine's ORB-intensity covariance is same-signed as real and not the
   wrong-signed anti-coupling.) This is the **terminal lean** for the ORB-continuation channel
   the instrument was built to test.

2. **SECONDARY (a real, same-units, trusted but DISTINCT defect): ORB level + dispersion.**
   Engine `ORB/POSS` is ~23% too high (0.194 vs 0.158) and `Var(ORB/POSS)` is ~2.3× too
   compressed (0.000084 vs 0.000190) — both same-units engine-vs-real gaps that need no sign to
   clear a noise floor, so they are trusted. Read #2 sharpens it: the engine over-produces
   shallow (k=1) putbacks while under-producing deep chains. This is a **rebound-fidelity**
   defect (level + team-to-team spread), **not** the coupling-sign defect, and must not be
   conflated with it.

3. **REPORTED, not load-bearing:** the weak PPS-tercile gradient (read #3) corroborates #1 — if
   ORB-continuation drove the anti-coupling, low-PPS teams would own a much heavier tail; they
   do not.

## Consequences

1. **No ORB decay/cap-for-coupling PR.** The plan that produced #1045 tentatively reserved a
   decay/cap FIX as the likely next PR; the verdict **cancels that** for the coupling question —
   the ORB-intensity covariance is faithful. The measure-then-build loop for the
   ORB-continuation hypothesis closes with a localized **negative** result.
2. **The coupling search stays where ADR-0049 put it.** The wrong-signed
   `Cov(ln(FGA/POSS), lnPPS)` (engine −0.000800 vs real +0.000027) is now narrowed **by
   exclusion** to the rest of the shots-per-possession structure — initial vs transition
   shot-frequency↔efficiency — and the still-open **count axis** (`Var(lnPOSS)` engine 0.000285
   vs real 0.000721, ~2.5× too narrow; `Cov(lnPOSS,lnPPS)` engine −0.000319 vs real +0.000241,
   the ADR-0049 addendum's noise-floor-clearing count-covariance gap). The next **L2
   instrument**, if pursued, should split that residual now that ORB-continuation is ruled out.
3. **An ORB level/dispersion fix is independently warranted but separately scoped.** The ~23%
   level excess and ~2.3× dispersion compression are a genuine rebound-fidelity gap worth
   closing, but any such PR must carry its **own** ADR + faithfulness gate (it is a behavior
   change) and must be sold as a rebound-fidelity fix — **never** as the coupling fix, which it
   is not.
4. The instrument itself (Part A `decomposeOrebIntensity` channel + Part B `ContinuationDepth`
   histogram) is wired, committed, and reusable for the L2 split without re-deriving it.

## Reproduce

```
cd engine
go test ./internal/validate ./internal/calibrate   # unit (incl. decompose + accumulator + golden)
go test ./internal/sim -run Golden                 # byte-stability (no -update)
JSB_ARCHIVE_DIR=<dir> go test -tags archive ./internal/calibrate -run 'ContinuationDepth|PossessionCoupling'   # regenerate the artifacts
```

## Reference

- `ibl5/docs/decisions/0049-possession-count-coupling-channel.md` — the possession-count split this verdict extends (and its 2026-06-10 noise-floor addendum).
- `ibl5/docs/decisions/0054-possession-count-dispersion.md` — the parked count-dispersion axis (`Var(lnPOSS)`).
- `ibl5/docs/decisions/0055-faithful-putback-shot-resolution.md` — the `Var(lnPPS)` constraint a future fix must not regress.
- `ibl5/docs/decisions/0042-team-scoring-coupling-mechanism.md` — the original wrong-signed `Cov(lnFGA,lnPPS)` finding.
- `engine/internal/validate/testdata/calibration-5.60-20260610-continuation-depth.json` — the committed verdict anchor (Part A + Part B).
- `engine/docs/possession-count-coupling-trace.md` — the ADR-0049 RE trace this measurement builds on.
