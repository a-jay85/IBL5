# TSI → Rating Progression Analysis Results (Revised)

**Date:** 2026-03-13
**Data:** 6,035 year-over-year transitions, 1,377 players, 19 seasons (ibl_hist 1989-2007)

## Critical Context

TSI values and initial player ratings are **both manually set by humans** at player creation. The correlation between high TSI and high starting ratings is a confound (humans give stars both), NOT a mechanism. TSI does not determine starting ratings.

## Key Finding: Composite TSI Sum Drives Progression

The **composite TSI (T+S+I sum, range 3–15)** is the true predictor of rating progression. Individual attributes (T, S, I) do NOT have strong independent effects — when one is held constant, the other's effect becomes noisy. The earlier apparent "Talent → FGP" and "Skill → near-peak" findings were largely driven by the mild inter-correlation of the three attributes (humans assign similar levels).

### Development Phase (far from peak, controlled for starting FGP 40-55)

| TSI Band | Δ FGP/yr | Δ FTP/yr | Δ AST/yr | Δ STL/yr | n |
|----------|---------|---------|---------|---------|---|
| 3-6 (low) | -0.81 | -1.43 | -2.69 | -0.57 | 134 |
| 7-9 | -0.32 | -0.72 | -1.07 | -0.35 | 633 |
| 10-12 | +0.28 | -0.12 | -0.12 | +0.37 | 1304 |
| 13-15 (high) | +0.45 | +0.01 | +0.51 | +0.93 | 325 |

All ratings monotonic. Cross-validated across early and late eras.

### Near Peak (±2 years, controlled for starting FGP 40-55)

| TSI Band | Δ FGP/yr | Δ FTP/yr | n |
|----------|---------|---------|---|
| 3-6 | -2.33 | -4.86 | 21 |
| 7-9 | -2.02 | -2.99 | 129 |
| 10-12 | -0.97 | -1.43 | 630 |
| 13-15 | -0.35 | -0.59 | 224 |

8x less FTP decline for high vs low composite TSI.

## Additional Findings

- **Peak age is independent of TSI** (~28.0 for all levels) — validates peak-based controls
- **All three attributes contribute equally** — when properly isolated, T/S/I each show ~0.45–0.51 FGP spread. Earlier "Intangibles has no effect" was an artifact of asymmetric inter-correlation (INT↔Talent ≈ 0, INT↔Skill = strong, Talent↔Skill = moderate)
- **Individual T/S/I differentiation is weak** — within-sum breakdown shows minimal difference

## For Custom Sim Engine

Use `tsi_sum = T + S + I` as a single progression modifier. No need to differentiate T/S/I for progression mechanics. See full analysis: `~/.claude/plans/TSI_PROGRESSION_ANALYSIS.md`
