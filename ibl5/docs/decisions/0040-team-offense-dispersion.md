---
description: 5.60 disperses team offense through per-48 shot-VOLUME rates from season counting-stat sums, not the ODPT offense ratings; the faithful fix is to source the real rate inputs (the static real-life .plr block), not reweight a make-value knob.
last_verified: 2026-06-03
---

# ADR-0040: Team-offense dispersion is sourced from real shot-volume rates, not a make-value knob

**Status:** Accepted
**Date:** 2026-06-03

## Context

The 2026-06-02 season-aggregate calibration proved the native Go sim engine
(ADR-0035) cannot cut over: team scoring does not track offensive ratings
(PF corr = 0.04), team strength is compressed to ~⅓ of real dispersion, and scoring
runs ~23.5 ppg/team low (memory `reference_jsb_season_aggregate_verdict`). Under the
overriding fidelity-to-5.60 constraint, the question was **not** "what constant
raises dispersion" but "what does jumpshot 5.60 actually do to disperse team
offense, and did we port it." A reverse-engineering faithfulness audit
(`engine/docs/team-offense-dispersion-audit.md`) answered it:

1. **Team offense quality (OO/DriveOff/PO) reaches scoring through three channels
   that cannot disperse team scoring.** Ball-handler selection
   (`ballhandler.go`) weights by `rating/(teamTotal − rating)` — **scale-invariant**,
   so the absolute level of a team's offense ratings never changes how much it
   shoots, only the within-lineup split. Shot-type selection (`shotselect.go`)
   routes play type, and all types share one make-value base. The net make-value
   term (`netadvantage.go` → `shotdecision.go`) is weak (≈ ±17‰ against a ~450‰ base)
   and defense-canceling. **5.60 itself does not make team scoring track ODPT
   offense** — so PF↔offense-rating ≈ 0 is largely *faithful*, not purely a bug.

2. **5.60 disperses team offense through per-48 shot-VOLUME rates.** The live 2pt
   bucket weight (+0xD90 Branch-A, `bucketweights.go` ← `jsb560_decompiled.c:91078`)
   is built from D88 = `(ΣFGA/ΣGP)×48`, DB8 = `(ΣORB/ΣGP)×48`, D70 = an FTA-weighted
   rate — **season counting-stat aggregates**, not ratings. The Go port ships the
   formula faithfully but feeds it **compressed 0-99 rating stand-ins**
   (`r_fga × 0.30`, etc.). A narrow rating spread substituted for a wide volume-rate
   spread is the mechanical origin of the ~⅓-dispersion symptom.

3. **The real inputs exist in the IBL data path.** The `.plr` static
   "Real-Life / Previous Season Stats" block (`JSB_FILE_FORMATS.md:122-140`:
   `realLifeGP/FGA/FTA/ORB/DRB/AST`) is explicitly "the reference stats used by the
   engine when simulating games." Being static, it also moots the cold-start
   divide-by-zero a live-season-totals source would hit. The bundle simply does not
   carry these sums today (`bundle.go` is ratings-only).

4. **The make-value path is already faithful.** 5.60's `shot_value`
   (`00_MASTER_REFERENCE.md:1048`: `(net × 0.5/baseline)×1000 + base_2pt`) is
   identical to the port's `net × 500/233 + fgp×9`. Reweighting `net` would fabricate
   a mechanism 5.60 does not have.

## Decision

The faithful team-offense-dispersion mechanism is the **+0xD90 Branch-A per-48
shot-VOLUME composite fed by real season counting-stat rates**, not the ODPT offense
ratings and not the make-value `net` term. The eventual model PR inherits this scoped
direction:

- **(A) Source real per-48 rate aggregates into the bundle — PRIMARY, FAITHFUL.**
  Wire the static real-life `.plr` counting-stat sums into the bundle and compute
  D88/DB8 (and D70's per-player part) as `(Σstat/ΣGP) × 48`, replacing the
  `bucketweights.go` rating stand-ins. The model PR must confirm the loader maps the
  real-life block (not the live season block) into the rate composites.
- **(B) Port the +0xD90 Branch-B usage-shrink — SECONDARY, CONDITIONAL.** No longer
  RE-blocked (its two gating constants `_DAT_0066d318 = 0.2`, `_DAT_0066d310 = 0.04`
  are pinned; `r_trans_off` + team DRB/AST rates are identified). But it is a usage
  *cap*, not a dispersion amplifier, and reads the same season aggregates as (A).
  Port **after** (A) and confirm empirically — its dispersion effect is an open
  question, measured on PR2's level/dispersion instrument
  (`jsb-calibrate-level-dispersion-metrics`).
- **(C) Reweight `net` in `shot_value` — REJECTED, INVENTED.** 5.60's make value is
  net-light by design and the port already matches it exactly.

D70's league-relative scalar (`((C[+0x6938]×5 − C[+0x68D8]×0.5)/(C[+0x6728]×5))`)
reads runtime CEngine league aggregates absent from the per-player IBL data — a
documented permanent partial gap (the "loader-populated, not modeled" class, like
uniform stamina and `league_baseline`). (A) carries it as a calibrated league
constant; it cannot be faithfully sourced.

This ADR ships **no engine code**; it records the direction so the model PR is not a
re-litigation.

## Alternatives Considered

- **Reweight `net` / tune `offQualityRatingScale` to raise dispersion now** —
  Rejected: 5.60 does not express team-offense dispersion through make value (§4 of
  the audit); this is a `#957`-style guess at a mechanism instead of porting 5.60's.
- **Port Branch-B first as the dispersion fix** — Rejected as primary: Branch-B is a
  usage *shrink* that reads no offense-quality rating and depends on the same season
  aggregates as (A); it cannot be exercised faithfully before (A) and is unproven as
  a dispersion driver.
- **Treat PF↔offense-rating ≈ 0 as the defect to fix** — Rejected: the audit shows
  5.60 routes ODPT offense into who-shoots / which-type, not team scoring, so strong
  correlation is an unfaithful target. The faithful defect is dispersion *magnitude*
  via real volume rates.
- **Source the live in-game season-totals block (`.plr` 144-207)** — Rejected:
  divide-by-zero before game 1 (ΣGP = 0) and contradicts `JSB_FILE_FORMATS.md:124` +
  `reference_jsb_season_rating_stability` (rates constant all season). The static
  real-life block is the source.

## Consequences

- Positive: the model PR inherits a settled, evidence-grounded direction (source
  real volume rates), with the two invented/unavailable paths (C-reweight,
  D70-league-scalar) recorded as rejected/gapped — no rediscovery.
- Positive: the fix is faithful by construction (it ports 5.60's actual inputs), and
  is expected to narrow the scoring *level* deficit too where the same compressed
  volume input depresses attempts.
- Negative: (A) requires bundle-contract and PHP bundle-builder changes (new season
  counting-stat fields) plus a loader-mapping confirmation — a larger surface than a
  one-line constant tune.
- Negative: D70's league scalar remains a calibrated constant, not a sourced input —
  a bounded, documented permanent gap.

## References

- `engine/docs/team-offense-dispersion-audit.md` — the full RE evidence (§1-§5).
- `ibl5/docs/decisions/0035-native-go-sim-engine.md` — the engine this scopes within.
- `ibl5/docs/decisions/0036-engine-defers-hca-pending-bucket-ev-calibration.md` — the
  prior bucket-scale RE that this audit's volume-rate finding builds on.
- `engine/internal/sim/bucketweights.go` — the +0xD90 Branch-A composite + stand-ins.
- `engine/internal/sim/ballhandler.go`, `shotselect.go`, `netadvantage.go`,
  `shotdecision.go` — the four offense-quality channels (§1, §4).
- `engine/internal/bundle/bundle.go` — the bundle contract (A) extends.
- `ibl5/docs/JSB_FILE_FORMATS.md` — `.plr` real-life + season stat blocks.
- The JSB decompile (`COMPOSITE_DOUBLES_TRACE.md`, `00_MASTER_REFERENCE.md`) lives
  outside this repo (`~/Downloads/jsb_560/decompiled/`).
- Memories: `reference_jsb_season_aggregate_verdict`, `reference_play_outcome_buckets`,
  `reference_jsb_hca_pr7a_blocked`, `reference_jsb_season_rating_stability`,
  `reference_jsb_rdata_static_read`.
