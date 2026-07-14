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
// defensive-only stand-in this replaces. The neutral reference points are the real dev-DB per-starter
// minutes-weighted composite means (offense 161.2 ± 13.8, defense 23.8 ± 1.4;
// 28 teams, 2026-06); they center a league-average roster at baseTimeMid.
const (
	// offVolumeScale: seconds the base_time shortens per unit of offensive volume
	// composite above neutral — the channel's strength, and the primary
	// corpus-calibration knob.
	//
	// CALIBRATED CONSERVATIVELY to 0.02 (2026-06, ibl5/backups, realarchive
	// diagnostic). The orientation is correct — archive roster
	// corr(volume composite, FGP) = +0.55 (TestRealArchive_VolumeFGPCoupling),
	// confirming the trace §3.1 assumption that high-volume teams are more
	// efficient. But an offVolumeScale=0/0.02/0.04/0.055 sweep at fixed
	// stride/runs shows the channel MONOTONICALLY widens engine Var(lnFGA) and
	// deepens the (still-negative) Cov(lnFGA,lnPPS) — it ADDS a dispersion source
	// rather than REPLACING one (ADR-0042's requirement), because the offsetting
	// empty-FGA reduction (the "which within-possession source carries the
	// miss-driven FGA" split) is NOT isolated — it remains ADR-0042's bounded,
	// sim-instrumentation open item, and tuning a constant against the size-
	// dominated by-origin decomposition would be porting a guessed lever. 0.02 is
	// the largest scale whose marginal effect on Var(lnFGA)/Cov stays within corpus
	// sampling noise vs the scale=0 reference, so the channel ships present,
	// directionally faithful, and fully instrumented (origin tags +
	// decomposeByOrigin) without regressing the corpus. Raising it toward real
	// Var(lnPF) is deferred until the empty-FGA source is isolated.
	//
	// LEVER-2 RE-TEST (2026-06-03, ADR-0044): the Lever-2 pair proposed RAISING
	// this concurrently with foulCompress (the idea: foulCompress cuts the
	// negative-Cov foul-arm dispersion, freeing room to add make-coupled volume
	// dispersion here). NOTE (J15, 2026-07-11): foulCompress was the pre-faithful
	// foul-dispersion dial; the faithful foul bucket + foulBucketScale supersede it,
	// but this re-test's conclusion (raising offVolumeScale games metrics) is
	// unchanged. The archive sweep REFUTED the raise on its OWN target:
	// after foulCompress=0.45, EngineVarLnFGA (0.00265 gt2) is still ABOVE real
	// (0.00141) — foulCompress narrows it but never undershoots, so there is no
	// room to refill. A 0.02→0.14 sweep (fc=0.45) only widens VarLnFGA further
	// from real (0.00265→0.00392) and does NOT improve Cov (−0.00176→−0.00189) —
	// raising it would be tuning toward the emergent Cov flip at the expense of
	// VarLnFGA→real, the metric-gaming Constraint 1/ADR-0041 forbid. So it stays
	// at the ADR-0042 minimal-presence floor 0.02.
	offVolumeScale = 0.02
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
// teamBaseTime uses the package-const offVolumeScale — the thin wrapper so existing
// callers and tempo_test.go are unchanged.
func teamBaseTime(starters []onCourt) float64 {
	return teamBaseTimeWith(starters, offVolumeScale)
}

// teamBaseTimeWith is teamBaseTime with the offensive-volume scale supplied by the
// caller — the ADR-0054 possession-count dispersion sweep seam. scale==offVolumeScale
// reproduces teamBaseTime byte-for-byte (so a nil Options.OffVolumeScale is
// golden-stable). Pure function: no Options/config in scope.
func teamBaseTimeWith(starters []onCourt, scale float64) float64 {
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
	bt := baseTimeMid - scale*(offAvg-offVolumeNeutral) + defRatingScale*(defAvg-defRatingNeutral)
	if bt < baseTimeLow {
		bt = baseTimeLow
	}
	if bt > baseTimeHigh {
		bt = baseTimeHigh
	}
	return bt
}

// possessionTime is the integer seconds one possession removes from the game
// clock: (2.0 − factor) × base_time, truncated. At factor 1.0 it equals base_time.
//
// TRUNCATION RETAINED — round-half-up deferred to J22 (ADR-0085). 5.60 rounds this
// step HALF-UP, it does NOT truncate: FUN_004e42e0 (the possession-clock update,
// jsb560_decompiled.c:98406-98418) truncates possession_time via __ftol then adds
// 1 when the fractional part ≥ 0.5 (`_DAT_00669ef0` = 0.5, confirmed from the raw
// .rdata bytes 0x3fe0000000000000). So Go's int() truncation here IS a confirmed
// infidelity. But the J21 archive A/B (ADR-0085) showed the faithful round-half-up,
// shipped ALONE, does NOT flip the wrong-signed Cov(lnPOSS,lnPPS) (Δ within
// sampling noise) and REGRESSES mean pace: it lengthens the central baseTimeMid =
// 14.5 step to 15, dropping mean possessions from ~101.9 (trunc) to ~97.6 vs real
// ~104.6. Truncation's downward bias was accidentally MASKING a base_time-
// generation miscalibration (engine center 14.5s vs real effective ~13.8s = 1440/
// 104.6 — base_time ~0.7s too slow). The faithful fix is round-half-up COUPLED with a base_time
// re-centering (offVolumeNeutral), landing both the step rule and the mean pace
// correctly — that coupled change is J22. Until then truncation stays: it is the
// mean-closer of the two imperfect states. See ADR-0085 for the RE evidence, the
// four-term A/B, and the mean-regression finding.
func possessionTime(baseTime float64) int {
	pt := (2.0 - tempoFactor) * baseTime
	if pt < 1.0 || pt > 24.0 {
		pt = 24.0 // JSB out-of-range fallback
	}
	return int(pt)
}
