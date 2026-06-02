package sim

// Play-outcome bucket-weight helpers — the faithful JSB 5.60 basis recovered in
// COMPOSITE_DOUBLES_TRACE.md (2nd-pass RESOLUTION) and 00_MASTER_REFERENCE.md
// (L1340 team-quality helpers, L653-690 HCA sites). Each helper is decoupled from
// the per-mille make-roll path (shotdecision.go): the buckets pick the shot PATH;
// shot_decision later rolls make/miss inside the 2pt/3pt paths.
//
// FAITHFUL STRUCTURE (this PR, landing HCA on top of #952's O(1) rescale):
//
//   - 2pt (twoPtBucketWeight) = the recovered +0xD90 Branch-A cold composite
//     (jsb560_decompiled.c:91078-91086): an offensive-rate composite, O(10s). This
//     is the dominant, field-goal-heavy path. Net-free (net lives only in
//     shot_value, not the bucket — so the playoff ×1.25 multiplier never amplifies
//     the bucket).
//   - 3pt (threePtBucketWeight) = the 2pt composite × the player's 3pt propensity.
//     JSB's +0xDB0 is DEAD (always 0) and 3pt is decided upstream in the ball-
//     handler stage (COMPOSITE_DOUBLES_TRACE.md §RESOLUTION); the Go engine folds
//     3pt into the play-outcome pick at the propensity rate — functionally
//     equivalent, kept on the same O(10s) basis as 2pt so 3pt does not vanish.
//   - and-one (andOneBucketWeight) = matchup×0.25 + made-rate, floored to 0.03
//     (the verbatim JSB floor, _DAT…→0.03). A small minority of plays, as in JSB.
//   - foul (foulBucketWeight) = the 0.6 FLOOR (the +0xDE0 composite is dead/always
//     0; the bucket floors to 0.6), modulated by the team-quality divisor
//     foul = (foul/offQ)×(defQ − teamDef×5/6) + foul, then the site-2 HCA nudge
//     foul −= hcaDelta. offQ (offQualityWithHCA, teamquality.go) shrinks for the
//     home team, growing foul/offQ — the dominant home-favorable mechanism. With
//     2pt at O(10s) the 0.6-floored foul bucket settles at a realistic minority
//     share (no whole-team foul-outs, FTA < FGA), so the sim stays non-degenerate.
//
// FAITHFULNESS / STAND-INS: the formula SHAPES (+0xD90 Branch-A, the 0.6 foul
// floor + quality divisor, the per-lineup off/def sums, the ±0.2 HCA) are ported
// exactly. Their numeric inputs are documented VALIDATION-PHASE STAND-INS: the
// per-48 rate inputs D88/DB8 derive from r_fga/r_orb (the bundle carries no season
// GP/FGA sums), D70 stands in for the CEngine team/league FTA-weighted aggregate
// absent from the bundle, and the off/def per-player doubles (teamquality.go) have
// unpinned source offsets. The four unresolved .rdata scale constants
// (_DAT_0066d318/d310/d320/00669ad0) gate only the +0xD90 Branch-B usage-shrink
// (deferred), NOT this Branch-A cold composite. Numeric corpus calibration of the
// stand-in magnitudes — and JSB's exact ~55% home-edge magnitude — is deferred
// (dev/nightly, no automated optimizer); THIS PR proves only the home-favorable
// SIGN, the invariant PR7a got wrong.

const (
	// +0xD90 Branch-A per-48 rate stand-ins (COMPOSITE_DOUBLES_TRACE.md §1, §4).
	// Each maps a 0-99 rating to a per-48 rate analog; the bundle has no season
	// GP/FGA sums so these are documented stand-ins for the recovered season-rate
	// doubles. fgaRateScale: r_fga 60 → ~18 FGA/48; orbRateScale: r_orb 20 → ~3
	// ORB/48; ftaRateScale: the team-relative FTA-weighted D70 stand-in, r_fta 20 →
	// ~6 (the real D70 reads CEngine team/league aggregates absent from the bundle).
	fgaRateScale = 0.30
	orbRateScale = 0.15
	ftaRateScale = 0.30

	// +0xD90 Branch-A pinned constants (COMPOSITE_DOUBLES_TRACE.md §5): the 0.5
	// (_DAT_00669ef0) and 0.25 (_DAT_00669f58) factors in the make-share weighting.
	d90MakeShareHalf    = 0.5
	d90MakeShareQuarter = 0.25

	// foulFloor is the foul bucket's 0.6 floor (COMPOSITE_DOUBLES_TRACE.md
	// §RESOLUTION: the +0xDE0 composite is dead/always-0, so the foul bucket floors
	// to 0.6 before the quality-divisor + HCA adjustments).
	foulFloor = 0.6

	// foulDivisorTeamDefCoef = 5/6 (_DAT_0066d3a0 = 0.8333), the coefficient on the
	// team-defense baseline in the foul divisor numerator (defQ − teamDef×5/6).
	foulDivisorTeamDefCoef = 0.8333333333

	// andOneBucketFloor is the verbatim JSB and-one floor (0.03). Ensures the
	// and-one path cannot be zeroed by a negative matchup quality. Unchanged from
	// the faithful #952 basis.
	andOneBucketFloor = 0.03

	// andOneMadeRateScale maps the player's FGP rating to a made-rate proxy for the
	// and-one stand-in (a small minority of scoring plays). Unchanged from #952.
	andOneMadeRateScale = 0.0008
)

// twoPtBucketWeight is the recovered +0xD90 Branch-A cold composite
// (jsb560_decompiled.c:91078-91086, COMPOSITE_DOUBLES_TRACE.md §4):
//
//	D90 = D88 − (D88/(D70+D88)) × DB8 × ((D88/(DB8+D88)) × 0.5 + 0.25)
//
// where D88 = per-48 FGA rate, DB8 = per-48 ORB rate, D70 = the team-relative
// FTA-weighted rate (documented stand-ins, see the rate-scale constants above).
// It is net-free: in JSB net enters only via shot_value, so the playoff ×1.25
// multiplier never amplifies this bucket. The composite is O(10s), keeping the
// 0.6-floored foul bucket a realistic minority share and field-goal attempts the
// majority path.
func twoPtBucketWeight(p onCourt) float64 {
	d88 := floor1(p.FGA) * fgaRateScale
	db8 := floor1(p.ORB) * orbRateScale
	d70 := floor1(p.FTA) * ftaRateScale
	if d70+d88 <= 0 {
		return d88
	}
	makeShare := (d88/(db8+d88))*d90MakeShareHalf + d90MakeShareQuarter
	return d88 - (d88/(d70+d88))*db8*makeShare
}

// threePtBucketWeight keeps the 3pt path on the same O(10s) basis as the 2pt
// composite, scaled by the player's 3pt propensity. JSB's +0xDB0 is dead and 3pt
// is gated upstream in the ball-handler stage; folding it into the play-outcome
// pick at the propensity rate is functionally equivalent (COMPOSITE_DOUBLES_
// TRACE.md §RESOLUTION). Scaling off the 2pt composite (rather than a fixed O(1)
// constant) prevents 3pt attempts from vanishing now that 2pt is O(10s).
func threePtBucketWeight(p onCourt) float64 {
	return twoPtBucketWeight(p) * threePtPropensity(p)
}

// andOneBucketWeight is the matchup×0.25 + made-rate stand-in, floored to
// andOneBucketFloor (0.03, the verbatim JSB floor). The mq term (≈−0.02 under
// default ratings) contributes negligible negative weight; the made-rate term
// carries the bucket above the floor. Unchanged from the faithful #952 basis.
func andOneBucketWeight(mq float64, p onCourt) float64 {
	w := mq*0.25 + float64(floor1(p.FGP))*andOneMadeRateScale
	if w < andOneBucketFloor {
		return andOneBucketFloor
	}
	return w
}

// foulBucketWeight is the recovered faithful foul bucket (COMPOSITE_DOUBLES_
// TRACE.md §RESOLUTION, 00_MASTER_REFERENCE.md L1340): the 0.6 floor, modulated by
// the team-quality divisor and the site-2 HCA nudge:
//
//	foul = 0.6
//	foul = (foul / offQ) × (defQ − teamDef×5/6) + foul   // site-3 divisor
//	foul −= hcaDelta                                       // site-2 nudge
//
// offQ = offQualityWithHCA(offense, hcaDelta) is the foul-bucket divisor; it
// shrinks for the home team (each player's term reduced by +0.2), so foul/offQ —
// and thus the home foul bucket — GROWS. That multiplicative divisor growth is the
// dominant home-favorable term; it dominates the (anti-home) additive site-2 nudge
// (foul −= +0.2 for home), which is near-negligible against the divisor-adjusted
// bucket. This is the net-home-favorable composition PR7a got wrong (it shipped
// site-2 alone, anti-home). defQ = defMatchupQuality(defenders). offQualityFloor
// (teamquality.go) guards the division. The faithful divisor replaces #952's
// net×scale foul stand-in — so the foul bucket no longer reads net and the playoff
// ×1.25 multiplier no longer leaks into it. weight() clamps any negative result to
// 0.
func foulBucketWeight(offense, defenders []onCourt, hcaDelta float64) float64 {
	foul := foulFloor
	offQ := offQualityWithHCA(offense, hcaDelta)
	defQ := defMatchupQuality(defenders)
	foul = (foul/offQ)*(defQ-teamDefBaseline*foulDivisorTeamDefCoef) + foul
	foul -= hcaDelta
	return foul
}
