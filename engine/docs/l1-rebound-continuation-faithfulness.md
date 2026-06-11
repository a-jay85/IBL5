---
description: RE faithfulness gate for the L1 defect (the engine's empty-FGA offensive-rebound continuation loop, the suspected Cov(ORB/POSS,lnPPS) over-coupling carrier behind the residual Var(lnFGA) ~0.000397 over real). Static decompile of jumpshot.exe 5.60's rebound chain (FUN_004d8570 possession loop, FUN_004d6f00 rebound handler, FUN_004e22a0 team_rebound_strength, FUN_004ed110 callee) answers whether 5.60 decays P(OREB) per trip and/or caps continuations below the engine's maxOffensiveRebounds=8. VERDICT (two-part): FAITHFUL on the decay/cap axes — 5.60's continuation loop is a flat-probability do/while with NO per-trip decay and NO hard cap (probabilistic exit only; the engine's cap-8 is tighter but effectively unreached, P(8 ORBs)≈1e-3..1e-5), so the engine's flat cap-8 loop matches 5.60; but DIVERGENT on the determination-formula axis — 5.60 gates each continuation behind TWO sequential rolls (gate-1 = sqrt diminishing-returns team pick FUN_004e22a0; gate-2 = linear off/(off+def)*0.5+0.25 retention roll FUN_004d6f00), and the engine models ONLY gate-2 (orebProbability uses 5.60's gate-2 formula AND constants as its sole off/def roll), omitting the sqrt gate-1 entirely. NO-GO on the loop-decay/cap hypothesis; the L1 carrier is POSITIVELY identified as the dropped sqrt team-determination gate (mean-inflation + linear-vs-sqrt curvature over-coupling). Next /plan reproduces the two-gate structure, not a decay/cap. Read-only — no engine code touched.
last_verified: 2026-06-11
---

# L1 rebound-continuation faithfulness gate — RE trace (read-only)

> **Type:** reverse-engineering faithfulness audit, static decompile only. **No
> engine behavior change, no instrument, no sweep** — this gate is the precondition
> for the *future* L1 instrument/fix plan. The deliverable is the located carrier +
> a GO/NO-GO discriminator for that downstream build. Durable decision: **ADR-0057**.
>
> **Question.** The JSB-fidelity chain (ADR-0041…0055) closed the make-value and
> variance axes; the surviving open defect is **L1**, the engine's empty-FGA
> offensive-rebound *continuation* loop — `Var(lnFGA)` still sits ~0.000397 over
> real and the count axis is budget-blocked behind it. Suspected carrier:
> `Cov(ORB/POSS, lnPPS)` over-coupling — good-rebounding teams retrying *too
> efficiently*. This gate asks the narrow loop-structure question: **does
> jumpshot.exe 5.60 (a) decay P(OREB) across successive offensive rebounds within
> one possession, and/or (b) cap continuations below the engine's
> `maxOffensiveRebounds = 8`?**

## Verdict (two-part)

**NO-GO on the loop-decay/cap hypothesis — but the L1 carrier is positively
identified, not "elsewhere".**

1. **Decay/cap axes — FAITHFUL (engine matches 5.60).** 5.60's continuation loop is
   a flat-probability `do { …shot…rebound… } while (retain_flag == 1)` with **no
   per-trip decay term** and **no hard cap**. The engine's flat-probability,
   un-decayed `orebProbability` reproduces the per-trip probability shape, and its
   `maxOffensiveRebounds = 8` cap is *tighter* than 5.60 (which has none) but
   **effectively unreached** — P(8 consecutive offensive rebounds) ≈ (0.25–0.75)^8 ≈
   1e-3…1e-5. So neither a missing decay nor a too-loose cap explains the L1 defect.

2. **Determination-formula axis — DIVERGENT (the actual carrier).** 5.60 gates each
   continuation behind **two sequential rolls**: **gate-1** = a *sqrt
   diminishing-returns* blended team-strength pick (`FUN_004e22a0`) deciding which
   team rebounds, then **gate-2** = the linear `off/(off+def)×0.5 + 0.25` retention
   roll (`FUN_004d6f00`). The engine models **only gate-2** — `orebProbability`
   (`rebound.go:52-66`) uses 5.60's gate-2 formula *and its exact constants* (×0.5,
   +0.25) as its **sole** off/def roll (`possession.go:301`), and omits the sqrt
   gate-1 entirely. The dropped non-linear gate is the suspected
   `Cov(ORB/POSS, lnPPS)` carrier.

**So the headline answer is NO-decays / NO-caps-tighter — the engine's continuation
loop is faithful on the axes this gate set out to test — and the over-coupling lives
in the rebound-*determination* formula (the rebounder-strength channel the ADR-0049
NO-GO branch told us to look at next), not in the continuation loop's decay/cap.**

## The two competing structures, side by side

| Axis | jumpshot.exe 5.60 | Engine (`rebound.go` / `possession.go`) | Match? |
|---|---|---|---|
| Continuation loop | `do {…} while (local_15c == 1)` — retain flag (decompile 93051→94379) | `for trip := 0; trip <= 8` (`possession.go:124`) | structurally equivalent |
| Hard cap | **none** — probabilistic exit only | `maxOffensiveRebounds = 8` ⇒ ≤9 attempts (`possession.go:7,124`) | engine tighter, but cap unreached → behaviorally equal |
| Per-trip decay | **none** — P recomputed flat each trip (decompile 92201-92203, no trip index) | **none** — `orebProbability(off,def)` has no trip arg (`rebound.go:52`) | **match** |
| Team-determination gate (gate-1) | sqrt diminishing-returns blend + `off≤def` cap, roll∈[0,100) (decompile 97352-97405) | **absent** | **DIVERGENT** |
| Retention gate (gate-2) | `off/(off+def)×0.5 + 0.25`, roll∈[0,1) (decompile 92200-92205) | `ratio×0.5 + 0.25`, identical constants (`rebound.go:58`) | **match (formula + constants)** |
| Rolls gating one continuation | 2 (gate-1 team pick, gate-2 retention) + a 0.06 commentary draw | 1 (`gs.rng.Float64() < orebProb`, `possession.go:301`) | engine collapses 2→1 |
| Clamp on gate-2 P | `[0.25, 0.75]` natural (ratio∈[0,1]) | explicit `[0.25,0.75]` (`rebound.go:59-64`) — redundant, harmless | match |

## Findings — per question, with exact decompile citations

All decompile line numbers are `jsb560_decompiled.c:<line>`. The continuation loop
lives in the possession resolver `FUN_004d8570` (header `jsb560_decompiled.c:92784`),
**not** inside the rebound handler `FUN_004d6f00` — a correction to the plan's
premise (see "Plan-premise correction" below).

### Q1 — Loop bound / cap

- The continuation loop is the `do { … } while ((char)local_15c == '\x01');` at
  `jsb560_decompiled.c:93051` (loop top) → `jsb560_decompiled.c:94379` (loop
  condition). `local_15c` is the **offensive-retain flag**: cleared to 0 at
  `jsb560_decompiled.c:93048` before the loop, and set **only** from the rebound
  handler's return at the four call sites `93828`, `93863`, `94019`, `94144`
  (e.g. `local_15c = …CONCAT31(local_15c._1_3_, uVar9)` where `uVar9 =
  FUN_004d6f00()`).
- **There is no counted index and no hard cap.** No `maxOffensiveRebounds = 8`
  analogue exists: the loop has no iteration counter compared to a constant, and the
  exit (`94379`) tests *only* the retain flag. The loop is
  **unbounded-with-probabilistic-exit** — it continues exactly while the offensive
  team keeps winning the board (gate-1) *and* passing the retention roll (gate-2);
  termination is guaranteed by the geometric tail of those probabilities, not by a
  bound.
- The `+0xBC` counter incremented on the offensive-retain branch
  (`in_stack_000000bc + 1` at `jsb560_decompiled.c:92232`) is a **box-score OREB
  stat**, not a loop bound — nothing reads it back as a cap.
- **Two nearby constants that look like caps but are NOT** (dispositioned, do not
  confuse with continuation bounds):
  - `if (0x18 < *(int *)(param_1 + 0x4c24)) goto …` (`jsb560_decompiled.c:93012`,
    `93016`, `93025`) — `+0x4C24` is the **game clock, seconds remaining**
    (master-ref `00_MASTER_REFERENCE.md` line 154); `0x18 = 24` is a shot-clock
    comparison **outside** the continuation loop, not a continuation count.
  - `do { … } while ((*(int *)(param_1 + 0x4c74) == 1) && (local_130 == 5));`
    (`jsb560_decompiled.c:93281-93296`) — `+0x4C74` is the **retry-on-steal flag**
    (master-ref line 160), a *type-5-turnover* retry loop, **not** the ORB
    continuation.

### Q2 — Per-trip decay

- **No per-trip decay.** Each iteration of the continuation loop re-reads the
  current ball handler `local_164 = *(param_1 + 0x4c1c)`
  (`jsb560_decompiled.c:93054`) — the rebounder the handler stored at
  `jsb560_decompiled.c:92205` — and recomputes shot type and rebound strengths fresh
  from that handler. There is no trip-index variable in scope and no
  counter-indexed table.
- The gate-2 retention probability is computed at `jsb560_decompiled.c:92201-92203`
  as `(off / (def + off)) × _DAT_00669ef0 + _DAT_00669f58` =
  `off/(off+def) × 0.5 + 0.25` (constants `_DAT_00669ef0 = 0.5`, `_DAT_00669f58 =
  0.25`, master-ref line 1385). **No trip multiplier, no subtraction, no decay
  term.** P is recomputed identically each trip — exactly matching the engine.
- The only state that *could* hide a decay is the team-strength sum in
  `FUN_004e22a0`: `Σ(reb_rating × energy-fatigue)` over 5 starters
  (`jsb560_decompiled.c:97352-97369` offensive loop, `97372-97389` defensive loop).
  The `energy-fatigue` factor is **per-possession state**, not re-decremented inside
  the continuation loop, so it introduces no *per-trip* (within-possession) decay.
  Confirmed: no aliased/indirect decay channel.

### Q3 — The two `rand_double` draws in the handler (master-ref L791: handler = 2, FUN_004e22a0 = 1)

The handler `FUN_004d6f00` (def `jsb560_decompiled.c:92131`) issues its rolls via
`FUN_005c6100(…, hi-dword-of-scale)`:

1. **FUN_004e22a0's single roll — gate-1, "which team".** Called at
   `jsb560_decompiled.c:92162`; its `rand_double(0,100)` is at
   `jsb560_decompiled.c:97403` (`FUN_005c6100(0, 0x40590000)`, `0x40590000` = hi
   dword of `100.0`). The sqrt-blended team value is compared to the roll at
   `jsb560_decompiled.c:97405` (`if ((float10)local_c < fVar7) iVar3 = iVar6;`),
   returning which team rebounds. This is the gate the engine **omits**.
2. **Handler draw A — the 0.06 commentary gate.** `fVar6 = FUN_005c6100(…,
   0x3ff00000)` at `jsb560_decompiled.c:92178` (`0x3ff00000` = hi dword of `1.0`),
   compared to `_DAT_0066d348 = 0.06` in both branches (`jsb560_decompiled.c:92182`
   offensive, `92238` defensive). This selects the **tip-in / putback commentary**
   play-by-play path (master-ref line 1385) — it does not decay or cap anything.
3. **Handler draw B — gate-2, the retention roll.** `fVar6 = FUN_005c6100(…,
   0x3ff00000)` at `jsb560_decompiled.c:92200`, compared at `92201-92203` to
   `off/(off+def)×0.5 + 0.25`. On pass it sets the retain flag
   (`param_1 = …CONCAT31(…,1)`, `jsb560_decompiled.c:92204`) and the new ball handler
   (`*(param_1 + 0x4c1c) = rebounder`, `jsb560_decompiled.c:92205`). **The retain
   flag is set ONLY here** — verified: it is the only assignment of the low byte to
   1 inside the handler, so a continuation requires *both* gate-1 = offense *and*
   gate-2 pass. The handler's `FUN_004ed110` callee (`jsb560_decompiled.c:92225`) and
   the `+0xBC` OREB stat increment (`92232`) run in the offensive branch regardless
   of draw-B (they record the board); only the *continuation* is gated by draw-B.

**Engine omission:** the engine's `rebound()` rolls a **single** `Float64` against
`orebProb` (`possession.go:301`) — that single roll corresponds to gate-2's formula.
The engine has no analogue of gate-1's sqrt team-pick roll, nor of the 0.06
commentary draw (cosmetic, correctly ignored).

### FUN_004ed110 (handler callee at `jsb560_decompiled.c:92225`)

Read at `jsb560_decompiled.c:103456-103560`. It is invoked *after* the retain flag
and new ball handler are already set (`92204-92205`), inside the offensive-rebound
branch. It carries **no cap, decay, or termination state** relevant to Q1/Q2 — it is
downstream bookkeeping/announcer output for the recorded offensive rebound, not a
loop control. Confirmed it does not gate or bound the continuation.

## Step 3 — Engine side, confirmed

`rebound.go:52-66` (`orebProbability`) is flat: `P = off/(off+def)×0.5 + 0.25`,
clamped `[0.25, 0.75]`, **no trip argument** — recomputed identically each trip.
`possession.go:124` runs `for trip := 0; trip <= maxOffensiveRebounds; trip++` with
`maxOffensiveRebounds = 8` (`possession.go:7`) ⇒ ≤9 attempts/possession, each a
**single** ORB/DRB roll (`possession.go:301`, `gs.rng.Float64() < gs.orebProb(...)`)
plus a weighted rebounder pick (`selectRebounder`, `rebound.go:86-110`). The
ADR-0043 freeze path (`gs.orebProb` wrapper, `possession.go:299-301`) substitutes a
league-mean P when the ORB arm is frozen but does **not** alter the loop's cap or add
decay — it is a diagnostic substitution, not a structural change. No nuance the
plan's grep missed alters Q1/Q2: the engine is flat-P, cap-8, single-roll, exactly as
summarized.

## Plan-premise correction (decompile wins)

The plan labeled `FUN_004d6f00` as "the trip/continuation loop (196 lines)". The
decompile shows `FUN_004d6f00` is the **single-rebound resolver** (off/def
determination + retention roll + PBP text); it contains **no loop**. The
continuation loop is the `do/while` in its caller `FUN_004d8570`
(`jsb560_decompiled.c:93051→94379`). This does not change the verdict — it sharpens
*where* the (absent) cap/decay would live, and confirms there is none. Reconciled
with `00_MASTER_REFERENCE.md` lines 1383-1385 and 4310, which already document the
two formulas as **sequential** (sqrt team-pick → linear retention) and agree with the
decompile; no master-ref/decompile disagreement found.

## Discriminator for the downstream L1 fix decision

**NO-GO on the loop-decay/cap hypothesis.** The engine's flat, cap-8 continuation
loop is faithful to 5.60's flat, uncapped loop on the decay and cap axes; adding a
per-trip decay or a tighter cap would *diverge* from 5.60, not converge. Do **not**
build a decay/cap fix.

**Carrier positively identified — the dropped sqrt team-determination gate
(`FUN_004e22a0`, the rebounder-strength channel).** The engine collapses 5.60's two
sequential gates into gate-2 alone. Two mechanisms follow, and **static decompile
cannot separate them** — both must be stated:

- **Mean inflation (dropped multiplicative gate).** 5.60's P(continuation) =
  P_sqrt(offense wins board) × P_linear(retention) — verified multiplicative, since
  the retain flag is set only inside the gate-2 pass branch
  (`jsb560_decompiled.c:92204`, nested under gate-1 = offense at `92181`). The engine
  applies gate-2's formula *and constants* as its **only** roll, so the engine's
  P(offensive continuation) is the *single*-gate probability — strictly larger than
  the *product*. The engine continues too often ⇒ inflated ORB/empty-FGA volume
  (consistent with the residual `Var(lnFGA)` over real).
- **Curvature over-coupling (linear vs sqrt).** Even at a matched mean, gate-1's
  `sqrt(adv) + adv + baseline` form (`jsb560_decompiled.c:97396-97402`, with the
  `off≤def` cap at `97393-97395`) *compresses* a strong rebounding team's advantage,
  whereas the engine's linear `off/(off+def)` lets ORB strength convert to outcomes
  proportionally. So the engine over-couples ORB-strength → continuation → points —
  exactly the `Cov(ORB/POSS, lnPPS)` symptom ("good rebounders retry too
  efficiently").

**This split is INDETERMINATE from static decompile** — the relative size of the
mean-inflation vs curvature-coupling contributions needs dynamic evidence. The
resolving measurement (mirroring the CEngine runtime-value method already in the
chain): the future L1 instrument dumps, per trip, the realized gate-1 / gate-2
probabilities and the marginal ORB→PPS coupling, comparing engine vs an archive walk;
whether the carrier is dominated by the dropped gate (mean) or the curvature
(coupling) decides the fix's shape. An x32dbg breakpoint on `FUN_004e22a0` /
`FUN_004d6f00` dumping `local_c`/`local_14`/the two rolls per trip would pin the live
gate-1 value distribution if the static form is doubted.

**Recommended next step:** a fresh `/plan` for an L1 instrument/fix that **reproduces
5.60's two-gate determination structure** — the sqrt diminishing-returns team pick
(`FUN_004e22a0`, gate-1) feeding the existing linear retention roll (gate-2) — **not**
a per-trip decay and **not** a tighter continuation cap. Gate that build on the
instrument confirming the mean-vs-curvature split above. Durable record: **ADR-0057**.
