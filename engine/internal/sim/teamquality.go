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
	// --mode calibrate, ibl5/backups, ~20 seasons). 0.059 is the value at which the
	// engine's mean home-minus-visitor point margin matches the corpus within ±0.5
	// pts for BOTH regular (gt 2) and playoff (gt 4) games — the HCA-magnitude
	// fidelity target. Lowering the scale grows the home margin (the fixed 1.0 HCA
	// subtraction becomes a larger fraction of the shrinking divisor); raising it
	// shrinks the margin. hcaMagnitude (gametype.go = 0.2) is the faithful decompiled
	// constant and is NOT a tuning knob — the magnitude is reached via this scale's
	// ratio to that fixed 0.2. See bands.go provenance for the calibration run.
	offQualityRatingScale = 0.059

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
)

// defMatchupQuality reimplements def_matchup_quality (FUN_004e3d90 → fVar11): the
// summed per-player defensive-rating stand-in over the 5 defenders, then the ×1.5
// universal cap (ceiling teamDefBaseline×5×1.5). Returns the team defensive value
// that feeds the foul-bucket divisor numerator (defQ − teamDef×5/6).
func defMatchupQuality(defenders []onCourt) float64 {
	var total float64
	for _, p := range defenders {
		total += floor1(p.OD) * defQualityRatingScale
	}
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
	var total float64
	for _, p := range offense {
		total += floor1(p.OO)*offQualityRatingScale - hcaDelta
	}
	if total < offQualityFloor {
		return offQualityFloor
	}
	return total
}
