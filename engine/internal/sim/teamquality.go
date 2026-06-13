package sim

// Team-quality lineup aggregators — the two functions feeding the foul-bucket
// divisor (00_MASTER_REFERENCE.md L1340, "Team-Quality Aggregation Helpers",
// VERIFIED 2026-05-30). They are distinct from the per-player bucket-weight helpers
// in bucketweights.go.
//
//   - defMatchupQuality (FUN_004e3d90) → fVar11: Σ a per-player defensive-rating
//     stand-in over the 5 defenders, then a ×1.5 universal cap with ceiling
//     teamDefBaseline×5×1.5 (decompile matchup_sub_calc_1_RAW.c: _DAT_00669ea0=5.0,
//     _DAT_00669ac0=1.5). The summation + cap structure is confirmed exact, and defQ
//     stays in the foul-divisor numerator — the intended defense-driven coupling.
//   - offQualityWithHCA (FUN_004e3f80) → fVar12, the foul-bucket DIVISOR: a
//     volume-NEUTRAL constant base (offQualityConstant), reduced by len×hcaDelta when
//     HCA is active, floored at offQualityFloor. NO per-player summation, NO off-side
//     compression. This is the Fork-B carrier fix (ADR-0061, ~/jsb-foulfork-RE-
//     verdict-20260612.md): 5.60's offQ divisor sources from the dead-zero +0xDE0
//     (every write is a =0 init, a 0×x Branch-B scale, or a struct-copied zero), so
//     5.60's offQ ≈ Σ(0) − HCA → a floored constant, volume-neutral. The old Go form
//     summed floor1(OO)·offQualityRatingScale — a +0.62-roster-volume-coupled divisor
//     that injected a foul anti-coupling (engine corr(vol,foulShare) −0.357 vs real
//     +0.161) 5.60 does not have. Replacing the summation with a constant restores the
//     defense-driven foul weight 0.6 + (0.6/const)·(defQ − teamDef·5/6).
//
// FAITHFULNESS: the SHAPE (the def summation + cap, the per-player HCA subtraction
// that shrinks the home divisor, the constant volume-neutral off base) is ported
// exactly. The def per-player summed values are documented STAND-INS — the exact
// per-game player-double source offsets are unpinned (00_MASTER_REFERENCE.md L1340,
// "source offsets validation-phase"). offQualityConstant (the volume-neutral divisor
// base, and therefore the fraction the fixed ±hcaMagnitude subtraction occupies) is
// the GATE-1 home-margin calibration knob that offQualityRatingScale was: it sets the
// size of the home-court margin, tuned to match the corpus home-minus-visitor point
// margin (see the const comment and ADR-0061). The def-side stand-ins keep the foul
// bucket a realistic minority share; their exact magnitudes remain corpus-deferred
// (they shape the foul rate, not the home/away margin).

const (
	// offQualityConstant is the volume-NEUTRAL base of the foul-bucket divisor
	// offQualityWithHCA. Fork-B-faithful to 5.60's dead-zero +0xDE0: 5.60's offQ
	// summation reads a struct-field that is never computed from stats (every write is
	// a =0 init, a 0×x Branch-B scale, or a verbatim struct copy — proven binary-wide,
	// ~/jsb-foulfork-RE-verdict-20260612.md :44-52), so 5.60's offQ ≈ Σ(0) − HCA, a
	// floored constant independent of offensive volume. The old Go form summed
	// floor1(OO)·offQualityRatingScale, coupling the divisor +0.62 to roster volume and
	// injecting a foul-share anti-coupling (engine corr(vol,foulShare) −0.357 vs real
	// +0.161) the real engine does not have. A constant base drops that coupling.
	//
	// GATE-1 CALIBRATION KNOB: this constant sets the home-court margin, the role
	// offQualityRatingScale held — the home divisor shrinks by the fixed len×hcaMagnitude
	// (5×0.2 = 1.0), so a SMALLER constant makes that 1.0 subtraction a larger fraction
	// of the divisor → a LARGER home margin; a larger constant shrinks it. CAUTION: the
	// margin is STEEPLY sensitive to this knob (the documented low-divisor brittleness
	// carried over from offQualityRatingScale). hcaMagnitude (gametype.go = 0.2) is the
	// faithful decompiled constant and is NOT a tuning knob — the home-margin magnitude
	// is reached via this constant's ratio to that fixed 0.2.
	//
	// STAND-IN value, not yet pinned from the binary. The RE proved offQ is volume-
	// NEUTRAL (a constant) but did NOT pin its VALUE (the static decompile shows
	// offQ = Σ(+0xDE0=0) − HCA, which taken literally floors degenerately, so there is
	// an unrecovered base/init term — see ADR-0061). The faithful value needs dynamic RE
	// (x32dbg breakpoint at FUN_004e3f80 during a live possession); that is a committed
	// FOLLOW-UP that will also pin defQ/teamDef and let the synthetic degeneracy guards
	// drop. Until then this is corpus-calibrated, exactly as offQualityRatingScale was.
	//
	// CALIBRATED 2026-06-12 to 1.575 (jsbcalibrate --mode calibrate, ibl5/backups,
	// runs=20 stride=1 seed=20240601; engine/sweep-offq.sh): the SMALLEST constant (=
	// largest home margin) that clears BOTH synthetic degeneracy guards — the foul-mix
	// minority band (foul share 0.249 ≤ 0.25, TestBucketWeights_FoulPathMix) and the
	// full-team foul-out rate (0.063 ≤ 0.08, TestSimulate_FoulOutRate). gt2 home margin
	// engine 3.479 vs sco 4.124 (gap −0.645), gt4 3.594 vs 4.590 (gap −0.995): both
	// outside the ±0.5 GATE-1 target but IMPROVED vs master (gt2 −0.875, gt4 −1.266 at
	// matched config) — a pre-existing HCA undershoot the volume-neutral fix narrows but
	// does not close (closing it needs the true pinned value, x32dbg follow-up). gt2 FTA-
	// dispersion ratio drops to 2.045 (from master 2.573 — GATE-2 improved). Smaller
	// constants (≤1.55) bring gt2 into ±0.5 but trip the foul-out degeneracy guard
	// (1.50 → 0.130) and the minority band — a genuine foul-heavy degeneracy, not a
	// relaxable heuristic, so the band is NOT widened. Lineage to the deleted
	// offQualityRatingScale=0.0565 / offQualityNeutralRatingSum=29.24 survives in prose.
	// See ADR-0061 for the full sweep table.
	offQualityConstant = 1.575

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

	// foulCompress narrows the team-to-team dispersion of the DEFENSIVE quality
	// aggregator toward the corpus league mean (defQualityNeutral), before the cap.
	// compressed = total + (foulCompress−1)×(total − neutral): at 1.0 the exact
	// identity, at <1.0 a mean-preserving narrowing of the spread that drives the
	// foul-bucket divisor term (foul/offQ)×(defQ − teamDef×5/6) — the lead negative-
	// covariance driver (ADR-0043: the foul-only arm is 47.6% of |Cov(lnFGA,lnPPS)|).
	// It no longer acts on the OFF side: offQ is now the volume-neutral constant
	// offQualityConstant, not a per-team summation to compress (ADR-0061 supersedes
	// ADR-0044's off-side compression).
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
// foul-bucket divisor) as a volume-NEUTRAL constant base reduced by HCA. 5.60's offQ
// sums the dead-zero +0xDE0 over the 5 offensive players, so its summation is Σ(0) —
// a floored constant independent of offensive volume (ADR-0061, RE verdict :44-52).
// The Go port therefore starts from the constant offQualityConstant (NOT a per-player
// OO summation, NOT off-side foulCompress) and applies HCA as a fixed per-player
// additive: total = offQualityConstant − len(offense)×hcaDelta. For the home team
// hcaDelta is +hcaMagnitude, so the divisor shrinks by exactly len×hcaMagnitude →
// foul/offQ grows → the home foul bucket grows (the dominant home-favorable
// mechanism); the home/away delta is therefore unscaled and exactly len×hcaMagnitude.
// The result is floored at offQualityFloor to keep the division well-defined on a
// degenerate (large-HCA) lineup.
func offQualityWithHCA(offense []onCourt, hcaDelta float64) float64 {
	total := offQualityConstant
	total -= float64(len(offense)) * hcaDelta
	if total < offQualityFloor {
		return offQualityFloor
	}
	return total
}
