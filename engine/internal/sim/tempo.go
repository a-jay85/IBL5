package sim

// tempoFactor is the JSB "Gameplay Adjustment Factor" (CEngine+0x63b8). IBL runs
// it at 1.0 (confirmed from IBL5.lge), so possession_time == base_time — neutral
// NBA pace. See 00_MASTER_REFERENCE.md "Gameplay Adjustment Factor / Tempo".
const tempoFactor = 1.0

// base_time clamp bounds (00_MASTER_REFERENCE.md: hard-clamped to [13.0, 16.0]).
const (
	baseTimeLow  = 13.0
	baseTimeHigh = 16.0
	baseTimeMid  = (baseTimeLow + baseTimeHigh) / 2.0 // 14.5 — a neutral team's pace
)

// teamBaseTime constants — the volume-rate → shot-COUNT channel (ADR-0042).
//
// FUN_004e4150 (00_MASTER_REFERENCE.md L685-705, re-verified 2026-05-30) confirms
// base_time is a clamped ratio of team OFFENSIVE/DEFENSIVE per-game stat
// aggregates; the original port kept ONLY the defensive side, so a team's
// shot-volume rates never reached its shot COUNT and team FGA varied (wrongly) by
// misses, not makes (ADR-0041/0042). This restores the offensive side: a higher
// offensive volume composite SHORTENS base_time → more possessions → more FGA,
// make-coupled (volume rates corr +0.265 with FGP), so FGA tracks scoring.
//
// FAITHFULNESS: only the [13,16] clamp, the (2.0−factor) form (possessionTime),
// and the 24.0 fallback are confirmed; the exact per-game stat-offset inputs are
// "validation-phase." So this additive offensive-minus-defensive form and its two
// scales are CORPUS-CALIBRATED STAND-INS for the ratio — exactly like the
// defensive-only stand-in this replaces and offQualityRatingScale in
// teamquality.go. The neutral reference points are the real dev-DB per-starter
// minutes-weighted composite means (offense 161.2 ± 13.8, defense 23.8 ± 1.4;
// 28 teams, 2026-06); they center a league-average roster at baseTimeMid.
const (
	// offVolumeScale: seconds the base_time shortens per unit of offensive volume
	// composite above neutral. Sized so the real offensive spread (sd ≈ 13.8, full
	// range ≈ ±28) maps across most of [13,16] without the bulk of teams
	// saturating — the volume→count channel's strength. The DOMINANT term, and the
	// primary corpus-calibration knob (raise to widen the FGA channel, lower to
	// narrow it toward real Var(lnFGA)).
	offVolumeScale = 0.055
	// defRatingScale: seconds base_time LENGTHENS per unit of defensive composite
	// above neutral — a stronger defense slows the pace, as in the original port.
	// Kept minor (def sd ≈ 1.4 → ≈ ±0.12s, the trace's ~0.8% defense-pace term);
	// offense, not defense, drives the count channel.
	defRatingScale = 0.083
	// offVolumeNeutral / defRatingNeutral: the real per-starter composite means
	// (dev DB, minutes-weighted) at which a roster lands exactly at baseTimeMid.
	// Validation-phase stand-in reference points.
	offVolumeNeutral = 161.0
	defRatingNeutral = 24.0
)

// teamBaseTime derives a per-team base possession length in [13,16] seconds from
// the team's offensive volume composite (Σ starters' r_fga+r_3ga+r_fta, the
// volume→count channel) and defensive ODPT composite (Σ OD+DD+PD+TD). Higher
// offensive volume → shorter (faster) base_time; stronger defense → longer
// (slower). Additive stand-in for FUN_004e4150's offensive/defensive ratio (see
// the const block). No division is used, so a degenerate (e.g. all-zero-rated)
// lineup clamps into [13,16] rather than producing NaN/Inf.
func teamBaseTime(starters []onCourt) float64 {
	if len(starters) == 0 {
		return baseTimeLow
	}
	var offSum, defSum float64
	for _, p := range starters {
		offSum += float64(p.FGA + p.TGA + p.FTA)     // r_fga+r_3ga+r_fta volume rates
		defSum += float64(p.OD + p.DD + p.PD + p.TD) // 1-9 ODPT scale → 0..36
	}
	n := float64(len(starters))
	offAvg := offSum / n
	defAvg := defSum / n
	bt := baseTimeMid - offVolumeScale*(offAvg-offVolumeNeutral) + defRatingScale*(defAvg-defRatingNeutral)
	if bt < baseTimeLow {
		bt = baseTimeLow
	}
	if bt > baseTimeHigh {
		bt = baseTimeHigh
	}
	return bt
}

// possessionTime is the seconds one possession removes from the game clock:
// (2.0 − factor) × base_time. At factor 1.0 it equals base_time.
func possessionTime(baseTime float64) int {
	pt := (2.0 - tempoFactor) * baseTime
	if pt < 1.0 || pt > 24.0 {
		pt = 24.0 // JSB out-of-range fallback
	}
	return int(pt)
}
