---
description: ADR-0049 PR-2 secondary axis — the possession-COUNT half of the wrong-signed Cov(lnFGA,lnPPS) split. ADR-0053 closed the shots-per-possession make-value lever as a null, so the deferred count-factor defect (Var(lnPOSS) engine 0.000288 vs real 0.000721, ~2.5× too narrow) became the lead. This PR ships a parameterized offVolumeScale override seam (sim.Options.OffVolumeScale *float64, nil ⇒ package const, golden byte-identical) and a full-archive sweep of offVolumeScale ∈ {0, 0.02, 0.04, 0.06} measured on the ADR-0049 count split. VERDICT — measured NULL, Var(lnFGA)-budget-blocked: the additive volume→count channel widens Var(lnPOSS) monotonically (0.000011→0.000288→0.000575→0.000851, crossing real near scale≈0.05) but at a 1.69× total-Var(lnFGA) penalty, so no scale widens Var(lnPOSS) toward real WITHOUT regressing total Var(lnFGA) (already past real at the shipped 0.02). The budget math is decisive and generator-independent: at scale 0 the shots-per-poss factor alone consumes Var(lnFGA)=0.001220 of a real 0.001330, leaving only 0.000110 headroom against a 0.000710 Var(lnPOSS) gap; fitting both would require a count↔shots-per-poss cross-term of ≈ −0.0003 (corr ≈ −0.32), the opposite sign of what the empty-FGA retry loop produces — so neither the additive form nor a faithful off/def-ratio base_time can fit. The count axis is BLOCKED BEHIND the shots-per-possession over-dispersion (the empty-FGA loop, ADR-0042/0053) — which is also the 72% headline driver — proving the work order: fix the empty-FGA loop first, then the count budget opens. The offVolumeScale override seam ships OFF (nil ⇒ const, golden unchanged) as a permanent measurement scaffold; offVolumeScale stays 0.02 (the ADR-0042 directional-faithful minimum). Cov(lnPOSS,lnPPS) stays WATCH-ONLY (tautology); the headline-Cov flip was never a success target here.
last_verified: 2026-06-10
---

# ADR-0054: Possession-count dispersion — the count half of the ADR-0049 split (measure-then-build)

**Status:** Accepted
**Date:** 2026-06-10

## Context

ADR-0049 split the engine's wrong-signed headline `Cov(lnFGA,lnPPS)` along the exact
identity `lnFGA = lnPOSS + ln(FGA/POSS)` into a possession-**COUNT** factor and a
**shots-per-possession** factor. ADR-0053 (PR-2 lead) built and measured the
shots-per-possession make-value decoupling arms and recorded a **null**: routing
putback `OriginOffReb` 2pt make-value to the league mean does not move the
shots-per-possession anti-coupling (the dominant ~72% of the headline). With the
make-value lever spent, the **deferred secondary axis — the possession-count factor —
became the lead** (ADR-0049 consequence #2, trace §B.2).

**The robust target** is the count under-dispersion: `Var(lnPOSS)` engine **0.000288**
vs real **0.000721** (~2.5× too narrow; sign-independent, no noise-floor dependence).
The engine flattens team-to-team possession count.

**The load-bearing constraint** is the same identity, in variance form:

```
Var(lnFGA) ≈ Var(lnPOSS) + Var(ln(FGA/POSS)) + 2·Cov(lnPOSS, ln(FGA/POSS))
```

`Var(lnFGA)` is already at/over real at the shipped engine config, and ~89% of it is
the shots-per-possession factor (the unfixed empty-FGA loop, ADR-0042). So widening
`Var(lnPOSS)` toward real, in isolation, pushes total `Var(lnFGA)` **further** from
real — which ADR-0041's "optimize total Var, never metric-game" treats as a
regression. Hence the **success criterion: widen `Var(lnPOSS)` toward real WITHOUT
regressing total `Var(lnFGA)`.** The headline `Cov(lnFGA,lnPPS)` flip is explicitly
NOT a success target here (it is blocked by the unfixed 72% shots-per-poss
anti-coupling, which is faithful to 5.60). `Cov(lnPOSS,lnPPS)` (engine −0.000337 vs
real +0.000241) is **watch-only** — a tautology, since real shots-per-poss ≈ 0 ⟹ real
count ≈ total, so the real side's positive count covariance is just the *absent*
shots-per-poss coupling reflected onto the count factor.

The `offVolumeScale` knob (`engine/internal/sim/tempo.go`) is the existing lever that
couples a team's offensive volume → `base_time` → possession count (the ADR-0042
volume→count channel). It was capped at 0.02 **only on `Var(lnFGA)`/headline grounds**
and was NEVER judged on `Var(lnPOSS)` — that metric did not exist until ADR-0049. So
the first question is empirical: re-measure `offVolumeScale` on the count split.

## Decision

Measure-then-build, the ADR-0048/0053 cadence. This PR ships a **parameterized
`offVolumeScale` override seam** and the full-archive sweep that measures it; the
verdict (below) selects the build.

- **The seam:** `sim.Options.OffVolumeScale *float64` (a pointer, so the valid sweep
  value `0` — which disables the channel — is distinguishable from "use the package
  const"). `nil ⇒ the const`, so a zero `Options` is byte-identical to `Simulate` and
  the golden master is untouched. Resolved in `simGameWith` via `resolveOffVolumeScale`
  and passed to a new pure `teamBaseTimeWith(players, scale)`; `teamBaseTime` is kept
  as a thin const-wrapper so every existing caller is unchanged. Threaded through
  `calibrate.Options` → `validateWithArms` exactly as the BranchB/MakePutback arms are.
- **The measurement:** an `archive`-tagged sweep test
  (`offvolumescale_sweep_archive_test.go`) runs `offVolumeScale ∈ {0, 0.02, 0.04,
  0.06}` over the full 705-zip backup archive (runs 20, stride 1) and emits one
  committed artifact per scale, decomposed on the ADR-0049 count split.

## The gate (machine-checkable)

The implementing agent applies this rubric to the measured regular-bucket deltas
(reference = `scale=0.02`, today's shipped const; `RealVarPoss = 0.000721`):

- **(a) retune the const** iff some scale `s*` BOTH widens `Var(lnPOSS)` toward real
  (closer than the 0.02 reference) AND keeps total `|Var(lnFGA) − real|` ≤ the
  reference's (no regression). → change the const, regenerate golden.
- **(b) build the faithful off/def-ratio `base_time` seam** iff (a) fails AND the
  additive form's `Var(lnPOSS)` is ceiling-limited (saturates/clamps below real, or
  widens only by regressing `Var(lnFGA)`), so a structurally different generator is the
  candidate. → new gated seam, OFF by default.
- **(c) documented null** iff `Var(lnPOSS)` cannot move toward real under any scale
  without regressing total `Var(lnFGA)`, AND there is no evidence an additive-form
  ceiling is the limiter. → no code change beyond the Phase-1 seam.

## Results — measured verdict

Full-archive sweep (705 zips, runs 20, stride 1, seed 20240601; ~31 min total).
Regular bucket (game type 2), engine vs real:

| `offVolumeScale` | `Var(lnPOSS)` | total `Var(lnFGA)` | `poss_dispersion_ratio` | `Cov(lnPOSS,lnPPS)` (watch) |
|---|---|---|---|---|
| 0.00 | 0.000011 | 0.001220 | 0.119 | −0.000113 |
| **0.02** (shipped) | 0.000288 | 0.001798 | 0.794 | −0.000337 |
| 0.04 | 0.000575 | 0.002291 | 1.085 | −0.000456 |
| 0.06 | 0.000851 | 0.002639 | 1.340 | −0.000493 |
| **real** | **0.000721** | **0.001330** | — | **+0.000241** |

**Corpus validity cross-check (no re-run needed despite the 31-min-vs-7h-estimate
runtime):** the `scale=0.02` readout reproduces ADR-0049's committed engine
`Var(lnPOSS) = 0.00028801…` and `Cov(lnPOSS,lnPPS) = −0.00033674…` to all printed
digits. That simultaneously (i) confirms the whole archive was processed (a truncated
walk would not land the exact committed value) and (ii) proves the `nil`/`0.02` seam is
behaviorally identical to the live engine — the golden-byte guarantee, observed on the
real corpus.

**Verdict — a NULL on the gate.** `success = false` at every scale. The two objectives
are in direct opposition along the scale axis:

- `Var(lnPOSS)` widens **monotonically** (0.000011 → 0.000288 → 0.000575 → 0.000851),
  crossing real (0.000721) near `scale ≈ 0.05`. It is NOT ceiling-limited.
- total `Var(lnFGA)` widens monotonically too (0.001220 → 0.001798 → 0.002291 →
  0.002639) and is **already past real (0.001330) at the shipped 0.02**. Best
  `Var(lnFGA)` is at `scale=0`; best `Var(lnPOSS)` is at `scale=0.06`. They cannot be
  satisfied together.

So branch (b)'s **second disjunct did fire** — `Var(lnPOSS)` "widens only by
regressing `Var(lnFGA)`." Choosing (c) over (b) is therefore a budget-math call (below),
not "the (b) trigger didn't trip": building and measuring a ratio-form `base_time` is a
near-zero-prior spike given the headroom arithmetic, so it is deliberately not built.

### Diagnosis — why no `base_time` generator can fit (the budget-headroom math)

The null is generator-**independent**, and the proof is quantitative, not "same
downstream path":

1. **Headroom.** At `scale=0`, `Var(lnPOSS) ≈ 0`, so `Var(lnFGA) = 0.001220` is the
   shots-per-possession factor **alone**. Real `Var(lnFGA) = 0.001330` leaves only
   **0.000110** of `Var(lnFGA)` headroom for any possession-count dispersion.
2. **The gap dwarfs the headroom.** Closing the `Var(lnPOSS)` gap requires adding
   **0.000710** (0.000011 → 0.000721). Even a *perfect, zero-cross-term* injection
   overshoots: `0.001220 + 0.000710 = 0.00193 = 1.45×` real `Var(lnFGA)`.
3. **The required cross-term has the wrong sign.** To hold total `Var(lnFGA)` at real
   *while* `Var(lnPOSS) = 0.000721` would require `2·Cov(lnPOSS, ln(FGA/POSS)) ≈
   −0.0006`, i.e. a count↔shots-per-poss correlation of **≈ −0.32** — possession-rich
   teams taking *fewer* shots per possession. The engine couples them the **opposite**
   way: the empty-FGA retry loop makes inefficient teams take *more* shots per
   possession, and the volume→count channel gives high-rate teams *more* possessions.
   The additive form's own cross-term is in fact **positive** — across `scale 0→0.06`,
   `Var(lnFGA)` grew 0.001419 while `Var(lnPOSS)` grew only 0.000840, a **1.69×**
   penalty, not the 1:1 of a zero cross-term. A faithful off/def-ratio `base_time`
   (5.60's `FUN_004e4150`) is still a `base_time → possession-count` generator; it
   would change *how* count maps from team quality but cannot manufacture the −0.32
   count↔shots-per-poss anti-correlation the budget demands while the empty-FGA loop
   pushes the other way.

**The count axis is blocked behind the shots-per-possession factor.** The
`Var(lnFGA)` budget is consumed by the empty-FGA over-dispersion (ADR-0042/0053); there
is no room to add the legitimately-needed `Var(lnPOSS)` until that factor is narrowed.

### Decision

Ship the `offVolumeScale` override seam **OFF by default** (`nil ⇒ const`, golden
byte-identical) as a permanent measurement scaffold, exactly as ADR-0048 (Branch-B) and
ADR-0053 (MakePutback) shipped their refuted arms. `offVolumeScale` **stays 0.02** — the
ADR-0042 directional-faithful minimum (high-volume teams *are* more efficient,
`corr(volume composite, FGP) = +0.55`); the sweep does not motivate retuning it, since
lowering it toward the `scale=0` `Var(lnFGA)` optimum would zero the volume→count
channel (`Var(lnPOSS) → 0.000011`, `poss_dispersion 0.12`), and raising it trades
`Var(lnFGA)` for `Var(lnPOSS)` against the budget. The four
`calibration-5.60-20260610-offVolumeScale-*.json` artifacts are the durable evidence.

## Consequences

1. **The work order is now proven, and it is the valuable half of this null:** the
   possession-count dispersion fix is **blocked behind** the shots-per-possession
   over-dispersion (the empty-FGA loop). Fixing the empty-FGA loop both **frees the
   `Var(lnFGA)` budget** for the count axis AND is the **72% driver of the wrong-signed
   headline** `Cov(lnFGA,lnPPS)`. So the next lead is **not** another count attempt — it
   is back to the empty-FGA loop (ADR-0042's miss→ORB→retry volume artifact), now with
   the dependency ordering established: shots-per-poss first, count second.
2. The `offVolumeScale` override seam ships as a reusable measurement scaffold; any
   future count-axis re-measurement (e.g. after the empty-FGA loop is narrowed) can
   re-run the sweep without new plumbing.
3. ADR-0049's count instrument is corroborated independently: the `scale=0.02` sweep
   reproduces its committed `Var(lnPOSS)`/`Cov(lnPOSS,lnPPS)` exactly.

## Out of Scope

- **The faithful off/def-ratio `base_time` form** — not built. The budget-headroom math
  (corr ≈ −0.32 required, opposite the empty-FGA sign) makes it a near-zero-prior spike;
  it would face the identical `Var(lnFGA)` budget constraint a generator cannot escape.
- **Retuning `offVolumeScale`** — stays at the ADR-0042 directional-faithful 0.02.
- **The count covariance `Cov(lnPOSS,lnPPS)`** — watch-only (a tautology), reported and
  never optimized.
- **The headline `Cov(lnFGA,lnPPS)` flip** — not a success target here; blocked by the
  unfixed shots-per-poss anti-coupling (faithful to 5.60).
- Removing the ORB-continuation loop (faithful to 5.60), lowering
  `maxOffensiveRebounds` (level, not dispersion), retrying Branch-B (ADR-0048 null),
  fast-break-as-offense, and make-value decoupling (ADR-0053 null) — all previously
  ruled out.
- **No security surface** — single Go module under `engine/`, no SQL/HTTP/auth/render.
