package sim

import "github.com/a-jay85/IBL5/engine/internal/rng"

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
//   - foul (foulBucketWeight) = the faithful JSB 5.60 asymmetric pair (ADR-0082):
//     HOME (hca>0): deterministic (defQ−5·(5/6)·teamDef)/5 + hca, defense-coupled.
//     AWAY/NEUTRAL (hca<=0): stochastic U[0, 0.6), mean 0.3, no defense coupling.
//     Both arms are multiplied by foulBucketScale (the raw-5.60-units →
//     Go-bucket-basis conversion; see the const). With 2pt at O(10s) the scaled
//     foul bucket settles at a realistic minority share (no whole-team
//     foul-outs, FTA < FGA), so the sim stays non-degenerate.
//
// FAITHFULNESS / STAND-INS: the formula SHAPES (+0xD90 Branch-A, the 0.6 foul
// floor + quality divisor, the per-lineup off/def sums, the ±0.2 HCA) are ported
// exactly. Their numeric inputs are documented VALIDATION-PHASE STAND-INS: the
// per-48 rate inputs D88/DB8 derive from r_fga/r_orb (the bundle carries no season
// GP/FGA sums), D70 stands in for the CEngine team/league FTA-weighted aggregate
// absent from the bundle, and the off/def per-player doubles (teamquality.go) have
// unpinned source offsets. The .rdata scale constants are now pinned
// (COMPOSITE_DOUBLES_TRACE.md §5, 2026-06-02): _DAT_0066d318=0.2 and
// _DAT_0066d310=0.04 form the Branch-B usage target
// in_f0 = player[+0x1E8] × (DRB-rate + AST-rate) × 0.2 × 0.04, which gates the
// +0xD90 Branch-B usage-shrink — still deferred (this port implements only
// Branch-A). All three Branch-B inputs are now identified: player[+0x1E8] is the
// TO (Transition Offense) ODPT rating = bundle r_trans_off (already available);
// the DRB/AST team rates live in the .plr team-summary rows (gp/drb/ast at
// 148/184/188) and would need bundle wiring. _DAT_0066d320=1/3000 (:90985) and
// _DAT_00669ad0=20000 (:6537) were formerly grouped here but are unrelated to the
// shrink. NONE affect this Branch-A cold composite. Numeric corpus calibration of the
// stand-in magnitudes — and JSB's exact ~55% home-edge magnitude — is deferred
// (dev/nightly, no automated optimizer); THIS PR proves only the home-favorable
// SIGN, the invariant PR7a got wrong.

const (
	// +0xD90 Branch-A per-48 rate FALLBACK stand-ins (COMPOSITE_DOUBLES_TRACE.md
	// §1, §4). Used only when the bundle carries no real-life minutes
	// (RealLifeMIN == 0): a player with no prior-season reference, or a production
	// bundle whose PHP builder is not yet wired. Each maps a 0-99 rating to a per-48
	// rate analog: fgaRateScale r_fga 60 → ~18 FGA/48; orbRateScale r_orb 20 → ~3
	// ORB/48; ftaRateScale r_fta 20 → ~6. The PRIMARY path now computes the real
	// per-48-MINUTE rates (stat/MIN)×48 from the bundle's real-life sums — see
	// twoPtBucketWeight. The compressed-rating stand-in is why team offense did not
	// disperse (ADR-0040); these constants survive only as the no-reference fallback.
	// (The stand-in scales target this same ~18-FGA/48 O(10s) magnitude, which is why
	// the real rate must be per-48-MINUTES — per-48-GAMES would be ~55× larger and
	// collapse the foul/FTA mix; see twoPtBucketWeight.)
	fgaRateScale = 0.30
	orbRateScale = 0.15
	ftaRateScale = 0.30

	// d70LeagueScalar carries D70's league-relative factor
	// ((C[+0x6938]×5 − C[+0x68D8]×0.5)/(C[+0x6728]×5), COMPOSITE_DOUBLES_TRACE.md §3),
	// which reads runtime CEngine LEAGUE aggregates absent from the IBL data path
	// (ADR-0040 negative finding 2 — the "loader-populated, not modeled" class, like
	// league_baseline). It is a uniform league scalar, so it degrades to a documented
	// calibrated constant: 1.0 (neutral) here, corpus-tunable on PR2's instrument.
	d70LeagueScalar = 1.0

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

	// foulBucketScale is the bucket-basis conversion for the faithful foul pair
	// (ADR-0082): the pair's pinned magnitudes are in 5.60's raw bucket units,
	// while the Go engine's play-outcome basis is ~8× larger (2pt composite
	// O(10s), ≈16.5 under default ratings). Applied to BOTH arms of
	// foulBucketWeight — including the +hca term and the U[0, foulFloor) away
	// draw — so the pinned home/away structure and ratio are preserved while the
	// bucket competes on the engine's basis. Unscaled, the foul share collapses
	// (~8× fewer fouls): FTA level, Var(lnPPS) (ADR-0055 SOLVED constraint), and
	// the HCA margin all break against the .sco archive. The value is a
	// corpus-calibrated STAND-IN swept against the archive fidelity gates
	// (ADR-0082, same epistemic status as ADR-0061's offQualityConstant); the
	// raw-unit RE of the defender/teamDef magnitudes remains the eventual pin.
	foulBucketScale = 8.6

	// andOneBucketFloor is the verbatim JSB and-one floor (0.03). Ensures the
	// and-one path cannot be zeroed by a negative matchup quality. Unchanged from
	// the faithful #952 basis.
	andOneBucketFloor = 0.03

	// andOneMadeRateScale maps the player's FGP rating to a made-rate proxy for the
	// and-one stand-in (a small minority of scoring plays). Unchanged from #952.
	andOneMadeRateScale = 0.0008

	// branchBTeamScale (_DAT_0066d318 = 0.2 = 1/5) and branchBPlayerScale
	// (_DAT_0066d310 = 0.04 = 1/25) are the two pinned constants forming the JSB
	// Branch-B usage target (COMPOSITE_DOUBLES_TRACE.md §5, direct .rdata read):
	//   usage = player[+0x1E8](TO) × (team DRB-rate + team AST-rate) × 0.2 × 0.04
	// (= × 0.008). The usage is then subtracted-proportionally from the live play-
	// outcome bucket composites (branchBShrink in freeze.go). Gated OFF by default
	// (FreezeConfig.BranchB) — a measurement seam, not a live-engine change.
	branchBTeamScale   = 0.2
	branchBPlayerScale = 0.04
)

// per48Min is the per-48-MINUTE season rate (stat / minutes) × 48 (_DAT_00669ed0 =
// 48.0). The divisor is season minutes by elimination — a games-scale divisor
// collapses the play-outcome mix; see plr.go's offRealLifeMIN note for the .sco
// evidence and the open decompile-identity caveat. Callers guard minutes > 0.
func per48Min(stat, minutes int) float64 {
	return float64(stat) / float64(minutes) * 48.0
}

// twoPtBucketWeight is the recovered +0xD90 Branch-A cold composite
// (jsb560_decompiled.c:91078-91086, COMPOSITE_DOUBLES_TRACE.md §4):
//
//	D90 = D88 − (D88/(D70+D88)) × DB8 × ((D88/(DB8+D88)) × 0.5 + 0.25)
//
// where D88 = per-48 FGA rate, DB8 = per-48 ORB rate, D70 = the FTA-weighted rate.
// The composite formula is unchanged; what changed (ADR-0040, candidate A) is its
// INPUTS. When the bundle carries the real-life minutes (RealLifeMIN > 0) D88/DB8/
// D70 are the FAITHFUL per-48-MINUTE rates (stat/MIN)×48 — the wide team-to-team
// spread 5.60 disperses team offense on, in the same O(10s) magnitude as the
// stand-in (a high-volume player ≈ 27 FGA/48). Absent them (rookie / unwired
// production bundle) it falls back to the compressed rating stand-in (fgaRateScale
// etc.), the behavior this PR preserves byte-for-byte for the no-reference case.
// (Using minutes, not games, is load-bearing: per-48-GAMES would be ~55× larger,
// driving the 2pt bucket to O(100s) and collapsing the foul/FTA play-outcome share
// to ~0 — verified degenerate against the .sco corpus.)
//
// It is net-free: in JSB net enters only via shot_value, so the playoff ×1.25
// multiplier never amplifies this bucket. The composite is O(10s), keeping the
// 0.6-floored foul bucket a realistic minority share and field-goal attempts the
// majority path.
func twoPtBucketWeight(p onCourt) float64 {
	var d88, db8, d70 float64
	if p.RealLifeMIN > 0 {
		d88 = per48Min(p.RealLifeFGA, p.RealLifeMIN)
		db8 = per48Min(p.RealLifeORB, p.RealLifeMIN)
		d70 = per48Min(p.RealLifeFTA, p.RealLifeMIN) * d70LeagueScalar
	} else {
		d88 = floor1(p.FGA) * fgaRateScale
		db8 = floor1(p.ORB) * orbRateScale
		d70 = floor1(p.FTA) * ftaRateScale
	}
	// Guard both composite divisions. The real-rate path can yield d88 == 0 (a
	// player who took no FGs) where the stand-in's floor1 always made d88 > 0; with
	// d88 == 0 the faithful composite limit is d88 (the subtracted term carries d88
	// as a factor), and without this guard makeShare's d88/(db8+d88) is 0/0 = NaN
	// when db8 is also 0 — a NaN that would silently poison the weighted pick.
	if d88 <= 0 || d70+d88 <= 0 {
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

// foulBucketWeight is the faithful JSB 5.60 asymmetric foul bucket (ADR-0082,
// superseding ADR-0061's offQ-divisor stand-in). The two roles use DIFFERENT
// mechanisms, keyed on the game-type-aware HCA delta (NOT on team role):
//
//   - HOME (hca > 0): a deterministic, defense-coupled weight
//     w = (defQ − defQualityCapTeamMult·foulDivisorTeamDefCoef·teamDefBaseline)/defQualityCapTeamMult + hca,
//     i.e. (defQ − 5·(5/6)·1)/5 + 0.2. defQ = defMatchupQuality(defenders) is the
//     compressed + capped team-defense stand-in, so a stronger defense draws MORE
//     home fouls. defMatchupQuality's post-compression range [4.5155, 7.5] holds
//     the raw home weight in [~0.27, ~0.87] (× foulBucketScale on return).
//
//   - AWAY / NEUTRAL (hca <= 0, incl. ASG where hcaDelta == 0): a stochastic
//     U[0, foulFloor) draw with NO defense coupling (mean foulFloor/2 = 0.3,
//     × foulBucketScale on return).
//     BOTH teams take this branch at a neutral (ASG) game type, so the foul path
//     is symmetric there — the property TestSimulate_HomeCourtAdvantage_ASGNeutral
//     locks. The branch keys on `hca`, NOT team role (`isHome`): an isHome-keyed
//     branch would give the home-role team the deterministic ~0.87 weight even at
//     ASG, breaking that symmetry test. In every regular game isHome ⟺ hca > 0, so
//     the corpus/sweep/golden are numerically identical to the isHome variant.
//
// The `if w <= 0` redraw is faithful to the binary's non-positive-weight redraw.
// It is UNREACHABLE through defMatchupQuality (floor 4.5155 keeps w ≥ ~0.27 > 0;
// the threshold is defQ ≤ 3.1667); retained as a faithful guard, its ~2 lines the
// only uncovered addition (package coverage stays well above the 90% floor).
func foulBucketWeight(defenders []onCourt, hca float64, r *rng.RNG) float64 {
	if hca <= 0 {
		// away/neutral: U[0, 0.6)·scale, mean 0.3·scale
		return r.Float64() * foulFloor * foulBucketScale
	}
	defQ := defMatchupQuality(defenders)
	w := (defQ-defQualityCapTeamMult*foulDivisorTeamDefCoef*teamDefBaseline)/defQualityCapTeamMult + hca
	if w <= 0 {
		// faithful redraw (unreachable via defMatchupQuality)
		return r.Float64() * foulFloor * foulBucketScale
	}
	return w * foulBucketScale
}
