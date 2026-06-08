---
description: Measured verdict on the ADR-0047 prime suspect — the JSB Branch-B usage-shrink (PR 2 of 2). Branch-B is implemented literally behind a freeze toggle (gated OFF, golden byte-stable) and measured over the full backup archive as a 2-config A/B. The engagement instrument confirms a CONFIRMED-ENGAGED measurement (Branch-B ran on 100% of possessions, 89.3M total, zero fallback, mean shrink s=0.64), so the result is a real null, not a never-engaged no-op. The verdict is a REFUTED/NULL: Branch-B does NOT flip the wrong-signed Cov(lnFGA,lnPPS) — it deepens it (−0.00121 → −0.00262) and regresses Var(lnPPS) ≈3.2×. Branch-B ships OFF (no golden regen), recorded null per the ADR-0040-A precedent. The contingent 5th-arm attribution lattice is NOT run (the sign never moved).
last_verified: 2026-06-08
---

# ADR-0048: Branch-B usage-modulation — measured verdict (REFUTED / null)

**Status:** Accepted
**Date:** 2026-06-08

## Context

ADR-0047 (PR #987) RE-traced the volume→shot-COUNT conversion mechanism and named a single
**prime, unmeasured suspect**: the deferred JSB 5.60 `+0xD90`-stage **Branch-B usage-shrink**
— the only bucket-side difference between 5.60 and the Go engine, team-quality-gated by the
same `TransOff` rating that drives the engine's higher-EV transition path, and sitting
*upstream* of the four exhausted freeze-lattice arms (ADR-0043/0045). ADR-0047 deferred PR 2's
exact outcome to measurement: *implement Branch-B behind a freeze toggle and measure whether it
flips the wrong-signed `Cov(lnFGA,lnPPS)`* (engine ≈ −1e-3 vs real ≈ +2.7e-4, the ADR-0042
team-scoring defect).

This ADR records that measurement's verdict. Branch-B is implemented exactly as decompiled
(`COMPOSITE_DOUBLES_TRACE.md` §4, `jsb560_decompiled.c:91072-99`):

```
usage = TransOff × (team DRB-rate + team AST-rate) × 0.2 × 0.04
ΣD    = 2pt + 3pt + foul          (live engine bucket composites; D78/TO and and-one excluded)
s     = (ΣD − usage) / ΣD
each live bucket ×= s              (proportional shrink, then HCA applied on the shrunk composites)
```

gated OFF by `FreezeConfig.BranchB` so `Simulate` stays byte-identical (the golden fixture is
unchanged) and measured via a 2-config season-aggregate A/B (OFF vs ON) over the full
local `.plr` backup archive.

### Team-rate input — a primary-source correction folded in (the load-bearing pin)

The plan's literal `(DRB/GP)×48` over the `.plr` **team-summary row** (gp@148) is **degenerate**:
that row's `GP` is *team-games* (≈ 5 early-season), which over-states the rate ~12× and drives
`usage > ΣD → s ≤ 0` (a cliff, all buckets clamp to 0 — not a proportional shrink). JSB's actual
per-half setup (`COMPOSITE_DOUBLES_TRACE.md` §1/§2) accumulates the rate over a team's **players**
with divisor `ΣGP` (the *sum of player season-GP*). Decoding a real `IBL5.plr` confirmed it:
summing per-player season AST@188 **exactly equals** the team-summary AST, and the faithful divisor
`Σ player-GP` (≈ 50–60 early-season, ≈ 700 full-season) lands `s ∈ (0,1)`. So Phase 2 was pivoted
from "parse the team-summary row" to "sum per-player season GP/DRB/AST" (same offsets, over player
rows) — **more** faithful, matching JSB's accumulation loop. The team rate is therefore
`(Σ_player season_DRB / Σ_player season_GP)×48` and `(Σ_player season_AST / Σ_player season_GP)×44`.

## Decision

**Branch-B is REFUTED as the positive-coupling lever and ships OFF (no golden regen), recorded as
a confirmed-engaged null — exactly the ADR-0040-A (#966) outcome: a null landed without revert.**

### The measurement is non-trivial (engagement instrument)

A null is only interpretable if Branch-B actually ran. The engagement instrument
(`sim.BranchBAccum`, harvested into the committed `*-branchB-on.json` artifact) confirms it did:

| Metric | Value (committed `*-branchB-on.json`, full archive) | Reading |
|--------|-------|---------|
| Branch-B-taken fraction | **1.000** — taken 89,252,070, fallback **0** | engaged on every possession |
| shrink factor `s` | **mean 0.640**, range [−1.349, +0.968] | a real ~36% mean shrink, materially < 1 |

So this is a **CONFIRMED-ENGAGED null**, NOT a never-engaged no-op (which would show `s ≈ 1` /
high fallback and would indicate a unit/ΣD mis-pin to fix, not a verdict). The faithful
`Σ player-GP` divisor is what makes the measurement valid.

### The channel verdict — Cov sign does NOT flip; Var(lnPPS) regresses

Regular-season bucket, engine OFF → ON, committed full-archive A/B
(`calibration-5.60-20260607-branchB-{off,on}.json`, `--selection season`, stride 1, runs 20, real
target in parentheses):

| Term | OFF | ON | real | direction |
|------|-----|-----|------|-----------|
| `Cov(lnFGA,lnPPS)` | −0.001210 | **−0.002624** | +0.000269 | sign NOT flipped — **deepens** the negative |
| `Var(lnFGA)` | 0.001798 | 0.002412 | 0.001330 | widens (worse) |
| `Var(lnPPS)` | 0.001570 | 0.005012 | 0.001451 | **regresses** (≈ 3.2×) |
| `Var(lnPF)` | 0.000948 | 0.002176 | 0.003318 | moves toward real, but only via the regressions above |

> **OFF reproduces the committed baseline.** The OFF `Cov(lnFGA,lnPPS)` = −0.00121 matches the
> ADR-0047 / `calibration-5.60-20260603-channel-decomp.json` baseline (engine ≈ −0.00122, real
> +0.00027) to within the harness's full-schedule-vs-.sco-subset sampling — confirming the Branch-B
> threading is inert when OFF (the row-19 baseline-reproduction check). A coarse stride-12/runs-2
> smoke gave the identical directional verdict.

The pre-registered success criterion (handoff B7) was: **narrow `Var(lnFGA)` toward real WHILE
flipping the `Cov` sign, WITHOUT regressing `Var(lnPPS)`.** Branch-B does the opposite on every axis:
the `Cov` sign does not flip — it **deepens** (−0.00121 → −0.00262, further from real +0.00027) — and
`Var(lnPPS)` regresses ≈ 3.2×. The proportional shrink reduces every live half-court bucket by the
*same* team-quality-gated `s`, which shrinks high-`TransOff` (high-volume) teams' half-court mass MORE
— but because the engine routes transition through an *independent upstream* fast-break gate (not an
in-pick reroute; ADR-0047 / handoff A1), the shaved half-court mass does not flow into make-coupled
transition shots; it renormalizes within the half-court buckets and *amplifies* the existing
wrong-signed volume↔efficiency anti-coupling. **Branch-B is not the missing positive-coupling
pathway.**

### What ships

- **Branch-B lands OFF** (`FreezeConfig.BranchB` zero value). `Simulate` is byte-identical; the
  golden fixture is unchanged; no production behavior changes. The measurement seam (the toggle,
  the calibrate threading, the terse `--mode measure`, the engagement instrument) ships so the
  result is reproducible.
- **The team-rate wiring ships on the backup/calibration path only.** The DB-built bundle leaves
  `bundle.Team.DRBRate/ASTRate` at 0 (Branch-B inert there regardless of the toggle).
- **No golden regen, no revert** — there is nothing to revert (OFF is the pre-PR behavior).

### Out of scope — the contingent 5th-arm attribution lattice is NOT run

The 5th-arm freeze-lattice attribution (Branch-B as a 32-config arm) was pre-registered to run
**only if the sign actually moved** (handoff B6). It did not. Running 32 configs to attribute a
null that is already visible in 2 configs would churn the just-settled #985/#986 control-B band
for no verdict. **Not run.**

## Consequences

1. The ADR-0047 prime suspect is **eliminated**. The wrong-signed `Cov(lnFGA,lnPPS)` is confirmed
   **not** to be carried by Branch-B's usage-shrink. The defect remains "structural, non-arm" — but
   the bucket-side candidate is now closed, narrowing the remaining search to the pace/shot-mix/FT/
   rebound-count coupling the freeze lattice already flagged as the surviving residual (ADR-0045).
2. The faithful **team DRB/AST per-48 rates** are now wired on the backup path and could feed a
   future mechanism without re-deriving them.
3. **Reproduce:** `go test -tags archive ./internal/calibrate -run BranchBUsageModulation` (see the
   committed artifacts); or `jsbcalibrate --mode measure --branchB` for the terse per-game-type
   Cov/Var verdict.

## Reference

- `engine/docs/volume-count-conversion-trace.md` — the ADR-0047 RE trace.
- `~/Downloads/jsb_560/decompiled/COMPOSITE_DOUBLES_TRACE.md` §1/§4/§5 — the Branch-B formula,
  team-rate accumulation, and pinned constants (0.2 / 0.04 / ×48 / ×44).
- `engine/internal/validate/testdata/calibration-5.60-20260607-branchB-{off,on}.json` — the committed
  A/B artifacts (the ON artifact carries the engagement instrument).
- `engine/internal/validate/testdata/calibration-5.60-20260603-channel-decomp.json` — the baseline the
  OFF artifact reproduces (engine `Cov` ≈ −0.00122).
