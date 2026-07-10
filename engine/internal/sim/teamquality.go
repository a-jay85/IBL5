package sim

// Team-quality lineup aggregators. After ADR-0082 the foul bucket no longer uses
// an offensive-quality divisor: only defMatchupQuality survives here, feeding the
// faithful home foul weight in bucketweights.go.
//
//   - defMatchupQuality (FUN_004e3d90) → fVar11: Σ a per-player defensive-rating
//     stand-in over the defenders, narrowed toward the corpus league mean by
//     foulCompress, then a ×1.5 universal cap (ceiling teamDefBaseline×5×1.5).
//     Its output range is [4.5155, 7.5]; it is the sole team-quality input to the
//     home foul weight (defQ − 5·(5/6)·teamDef)/5 + hca.
//
// The offensive-quality divisor (the two constants + function from ADR-0061) is
// DELETED: 5.60's home foul bucket is defense-coupled directly, and its away bucket
// is a defense-independent U[0, 0.6) draw (bucketweights.go), so there is no
// off-side quality term to port. The def per-player summed values remain documented
// corpus-deferred STAND-INS (exact per-game player-double source offsets unpinned).

const (
	// defQualityRatingScale maps a defender's outside-defense rating (OD) to the
	// per-player defensive-quality stand-in summed by defMatchupQuality. Documented
	// stand-in; chosen with teamDefBaseline so the divisor numerator
	// (defQ − teamDefBaseline×foulDivisorTeamDefCoef) is large enough that the
	// multiplicative home/away divisor difference dominates the (anti-home) ±0.2
	// site-2 foul nudge — the sign-robustness condition.
	defQualityRatingScale = 0.25

	// defQualityCapTeamMult (_DAT_00669ea0 = 5.0) and defQualityCapMultiplier
	// (_DAT_00669ac0 = 1.5) form the def-quality cap ceiling teamDefBaseline×5×1.5
	// (matchup_sub_calc_1_RAW.c). With realistic ratings the cap rarely binds; it is
	// ported for faithfulness and as a boundary guard against an extreme lineup.
	defQualityCapTeamMult   = 5.0
	defQualityCapMultiplier = 1.5

	// teamDefBaseline is the team-defense baseline (CEngine[+0x68A8] stand-in) that
	// sets both the def-quality cap ceiling and the foul-divisor numerator subtrahend
	// teamDefBaseline×foulDivisorTeamDefCoef. Documented stand-in.
	teamDefBaseline = 1.0

	// foulCompress narrows the team-to-team dispersion of the DEFENSIVE quality
	// aggregator toward the corpus league mean (defQualityNeutral), before the cap.
	// compressed = total + (foulCompress−1)×(total − neutral): at 1.0 the exact
	// identity, at <1.0 a mean-preserving narrowing of the spread that drives the
	// foul-bucket divisor term (defQ − teamDef×5/6) in the home weight formula —
	// the lead negative-covariance driver (ADR-0043: foul-only arm 47.6% of |Cov|).
	// After ADR-0082, foulCompress acts on the DEF side only (the deleted off-quality
	// divisor constants and ADR-0061's off-side compression no longer exist; see header).
	//
	// CALIBRATED against the corpus team-level FTA-rate dispersion (Constraint 1):
	// the value at which the engine's FTADispersionRatio (calibrate.FidelitySummary,
	// stdev(engine FTA/g)/stdev(sco FTA/g)) approaches 1.0. The baseline ratio is
	// ≈2.9 (gt 2) — the engine's team-to-team FTA spread is far too WIDE, so
	// foulCompress < 1.0 narrows it toward real. It is NOT tuned toward the emergent
	// Cov(lnFGA,lnPPS) sign (that would be the metric-gaming ADR-0041 forbids); the
	// covariance is the emergent acceptance readout, never a knob. See ADR-0044.
	foulCompress = 0.45

	// defQualityNeutral is the corpus league-mean PRE-CAP defensive total
	// Σ floor1(OD)×defQualityRatingScale over a team's starters (the space the
	// def-side compression is applied in, before the ×1.5 cap). DERIVED 2026-06-03
	// from the same archive sample (mean pre-cap total = 8.21). defQualityRatingScale
	// is NOT re-tuned, so this is stored directly. NOTE the cap binds for ~78% of
	// teams (logged by the harness): the post-cap def output is therefore already
	// near-constant at the 7.5 ceiling, so the def-side compression is largely inert
	// (it only pulls the below-cap minority up toward the cap) — the foul-bucket
	// dispersion the lever narrows comes mainly through the uncapped def-quality range.
	defQualityNeutral = 8.21
)

// compressQuality narrows total toward neutral by foulCompress (a corpus-calibrated
// factor in (0,1]): total + (factor−1)×(total − neutral). Written in this form so
// factor == 1.0 is the EXACT floating-point identity (the (factor−1) term is 0×x =
// 0, and total + 0.0 == total for any finite total), regardless of neutral — the
// byte-stable identity the matrix-row-6 test locks. factor < 1.0 pulls total toward
// neutral (mean-preserving when neutral is the true league mean); a team exactly AT
// neutral is unchanged by any factor.
func compressQuality(total, neutral, factor float64) float64 {
	return total + (factor-1.0)*(total-neutral)
}

// defMatchupQuality reimplements def_matchup_quality (FUN_004e3d90 → fVar11): the
// summed per-player defensive-rating stand-in over the 5 defenders, then the ×1.5
// universal cap (ceiling teamDefBaseline×5×1.5). Returns the team defensive value
// that feeds the foul-bucket divisor numerator (defQ − teamDef×5/6).
func defMatchupQuality(defenders []onCourt) float64 {
	var total float64
	for _, p := range defenders {
		total += floor1(p.OD) * defQualityRatingScale
	}
	// Narrow the team-to-team defensive-quality spread toward the corpus league
	// mean BEFORE the cap (so the cap stays the same boundary guard). At
	// foulCompress == 1.0 this is the exact identity.
	total = compressQuality(total, defQualityNeutral, foulCompress)
	ceiling := teamDefBaseline * defQualityCapTeamMult * defQualityCapMultiplier
	if total > ceiling {
		return ceiling
	}
	return total
}
