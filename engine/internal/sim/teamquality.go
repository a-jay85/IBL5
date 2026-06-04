package sim

// Team-quality lineup aggregators — the two sibling functions JSB sums over the
// 5-man lineup to feed the foul-bucket divisor (00_MASTER_REFERENCE.md L1340,
// "Team-Quality Aggregation Helpers", VERIFIED 2026-05-30). They are distinct
// from the per-player bucket-weight helpers in bucketweights.go.
//
//   - defMatchupQuality (FUN_004e3d90) → fVar11: Σ a per-player defensive-rating
//     stand-in over the 5 defenders, then a ×1.5 universal cap with ceiling
//     teamDefBaseline×5×1.5 (decompile matchup_sub_calc_1_RAW.c: _DAT_00669ea0=5.0,
//     _DAT_00669ac0=1.5). The summation + cap structure is confirmed exact.
//   - offQualityWithHCA (FUN_004e3f80) → fVar12, the foul-bucket DIVISOR: Σ a
//     per-player offensive-rating stand-in over the 5 offensive players, each term
//     reduced by hcaDelta when HCA is active. The decompiled function is a plain
//     summation with NO cap (matchup_sub_calc_2_RAW.c: `dVar2 = local_5c − (team×2−3)
//     ×0.2`, accumulated); the master-reference gloss claiming a ×1.5 cap on this
//     function is the loose part — only def_matchup_quality is capped.
//
// FAITHFULNESS: the SHAPE (per-lineup summation, the def cap, the per-player HCA
// subtraction that shrinks the home divisor) is ported exactly. The per-player
// summed values are documented STAND-INS — the exact per-game player-double source
// offsets are unpinned (00_MASTER_REFERENCE.md L1340, "source offsets validation-
// phase"). offQualityRatingScale (the divisor's per-player slope, and therefore the
// fraction the fixed ±hcaMagnitude subtraction occupies) is now CALIBRATED against
// the real 5.60 .sco archive: it sets the size of the home-court margin, which was
// tuned to match the corpus home-minus-visitor point margin (see the const comment
// and bands.go provenance). The def-side stand-ins keep the foul bucket a realistic
// minority share; their exact magnitudes remain corpus-deferred (they shape the
// foul rate, not the home/away margin).

const (
	// offQualityRatingScale maps a player's outside-offense rating (OO) to the
	// O(1)-per-player offensive-quality stand-in summed by offQualityWithHCA. It must
	// be small so the 5-man Σ is O(few): the fixed ±0.2/player HCA subtraction (5×0.2
	// = ±1.0 across the lineup) is then a meaningful fraction of the divisor — the
	// faithful-O(1)-basis property COMPOSITE_DOUBLES_TRACE.md requires for HCA to land
	// correctly-signed. The cost (documented) is brittleness at LOW ratings: for an
	// average OO below ≈4 the home divisor Σ−1.0 hits offQualityFloor, so the HCA
	// magnitude saturates and inflates for poor offensive teams. That is a magnitude
	// artifact, not a sign error (the home-favorable SIGN holds at every rating, and
	// FTA stays < FGA on any realistically-rated roster — only an ALL-zero-rated
	// lineup degenerates, which real rosters never are).
	//
	// CALIBRATED 2026-06-02 against the real 5.60 .sco archive (jsbcalibrate
	// --mode calibrate, ibl5/backups, ~20 seasons) as 0.059 — the value at which the
	// engine's mean home-minus-visitor point margin matched the corpus within ±0.5
	// pts. RE-TUNED 2026-06-04 to 0.0565 (ADR-0044, Lever-2): foulCompress=0.45 net-
	// weakens the home foul advantage, regressing the gt-2 margin to −0.70 (stride=1),
	// so the scale steps down one notch to restore it to −0.30 (back in ±0.5,
	// ≈ the pre-foulCompress −0.35 baseline). Lowering the scale grows the home margin
	// (the fixed 1.0 HCA subtraction becomes a larger fraction of the shrinking
	// divisor); raising it shrinks the margin. CAUTION: the margin is steeply
	// sensitive here (0.059→0.052 swings gt-2 from −0.70 to +0.79 — the documented
	// low-rating brittleness), so the step is small and gt-4 (pre-existing out of band
	// at master) is not chased. This scale ALSO sets the offQ divisor that scales the
	// FTA dispersion, so it is non-orthogonal with foulCompress on (margin, FTADisp)
	// — see ADR-0044. hcaMagnitude (gametype.go = 0.2) is the faithful decompiled
	// constant and is NOT a tuning knob — the magnitude is reached via this scale's
	// ratio to that fixed 0.2. See bands.go provenance for the calibration run.
	offQualityRatingScale = 0.0565

	// offQualityFloor is the ε floor on the offQuality divisor: it guarantees the
	// foul-bucket division (foul/offQ) can never divide by zero or flip sign even on
	// a pathological or low-rated lineup whose summed stand-in plus the −1.0 home HCA
	// would go non-positive.
	offQualityFloor = 0.25

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

	// foulCompress narrows the team-to-team dispersion of the two quality
	// aggregators toward the corpus league mean (offQualityNeutral/defQualityNeutral),
	// before HCA (off) and before the cap (def). compressed = total + (foulCompress−1)
	// ×(total − neutral): at 1.0 the exact identity (current behavior), at <1.0 a
	// mean-preserving narrowing of the spread that drives the foul-bucket divisor
	// term (foul/offQ)×(defQ − teamDef×5/6) — the lead negative-covariance driver
	// (ADR-0043: the foul-only arm is 47.6% of |Cov(lnFGA,lnPPS)|).
	//
	// CALIBRATED against the corpus team-level FTA-rate dispersion (Constraint 1):
	// the value at which the engine's FTADispersionRatio (calibrate.FidelitySummary,
	// stdev(engine FTA/g)/stdev(sco FTA/g)) approaches 1.0. The baseline ratio is
	// ≈2.9 (gt 2) — the engine's team-to-team FTA spread is far too WIDE, so
	// foulCompress < 1.0 narrows it toward real. It is NOT tuned toward the emergent
	// Cov(lnFGA,lnPPS) sign (that would be the metric-gaming ADR-0041 forbids); the
	// covariance is the emergent acceptance readout, never a knob. See ADR-0044.
	foulCompress = 0.45

	// offQualityNeutralRatingSum is the corpus league-mean rating-space offensive
	// sum Σ floor1(OO) over a team's faithful five-pass starters, derived from the
	// real .sco archive (TestDeriveQualityNeutrals, neutral_archive_test.go) — the
	// .sco analog of how offVolumeNeutral=161 was derived from real per-starter
	// composite means. offQualityNeutral is then this sum in QUALITY space, so it
	// co-varies automatically when offQualityRatingScale is re-tuned (Step 5 /
	// Constraint 2) and the compression stays mean-preserving without a separate
	// re-derivation.
	// DERIVED 2026-06-03 from the .sco archive (TestDeriveQualityNeutrals, 10 seasons
	// 88-89…06-07, 269 team-snapshots): mean Σfloor1(OO) over five-pass starters.
	offQualityNeutralRatingSum = 29.24
	// offQualityNeutral is the league-mean neutral-HCA offensive-quality value the
	// off-side compression pulls toward (mean-preserving reference). Quality space =
	// rating sum × scale, so it tracks offQualityRatingScale.
	offQualityNeutral = offQualityNeutralRatingSum * offQualityRatingScale

	// defQualityNeutral is the corpus league-mean PRE-CAP defensive total
	// Σ floor1(OD)×defQualityRatingScale over a team's starters (the space the
	// def-side compression is applied in, before the ×1.5 cap). DERIVED 2026-06-03
	// from the same archive sample (mean pre-cap total = 8.21). defQualityRatingScale
	// is NOT re-tuned, so this is stored directly. NOTE the cap binds for ~78% of
	// teams (logged by the harness): the post-cap def output is therefore already
	// near-constant at the 7.5 ceiling, so the def-side compression is largely inert
	// (it only pulls the below-cap minority up toward the cap) — the foul-bucket
	// dispersion the lever narrows comes mainly through the uncapped offQ divisor.
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

// offQualityWithHCA reimplements off_quality_with_hca (FUN_004e3f80 → fVar12, the
// foul-bucket divisor): the summed per-player offensive-rating stand-in over the 5
// offensive players, each term reduced by hcaDelta. For the home team hcaDelta is
// +hcaMagnitude, so every term shrinks → the divisor shrinks → foul/offQ grows →
// the home foul bucket grows (the dominant home-favorable mechanism). The result is
// floored at offQualityFloor to keep the division well-defined. The decompiled
// function has no cap — it is a plain summation.
func offQualityWithHCA(offense []onCourt, hcaDelta float64) float64 {
	var quality float64
	for _, p := range offense {
		quality += floor1(p.OO) * offQualityRatingScale
	}
	// Compress the quality spread toward the corpus league mean (foulCompress),
	// THEN apply HCA as a fixed per-player additive. Keeping HCA outside the
	// compression means the #955-calibrated ±hcaMagnitude/player home/away delta is
	// never scaled by foulCompress — the home divisor still shrinks by exactly
	// len(offense)×hcaMagnitude regardless of the compression factor.
	total := compressQuality(quality, offQualityNeutral, foulCompress)
	total -= float64(len(offense)) * hcaDelta
	if total < offQualityFloor {
		return offQualityFloor
	}
	return total
}
