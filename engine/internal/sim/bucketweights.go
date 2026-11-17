package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

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
//   - 3pt (threePtBucketWeight) = the recovered +0xDB0 composite (FUN_004cfa50's
//     stack-built player record; copied into the league record by FUN_00405970 —
//     the same provenance chain as +0xDC8/+0xD70): the player's season 3GA/MIN×48,
//     an f-projected per-48-minute three-point-attempt rate. LIVE, not dead: the J6
//     RE session (2026-07-10) overturned COMPOSITE_DOUBLES_TRACE.md §RESOLUTION's
//     "always 0" finding — FUN_004e1ba0's Σ(de0+db0+d90) play-outcome normalization
//     reads it directly. Faithful when the bundle carries real-life minutes; falls
//     back to the previous derived stand-in (2pt composite × 3pt propensity)
//     otherwise — see threePtBucketWeight.
//   - and-one (andOneBucketWeight) = matchup×0.25 + made-rate, floored to 0.03
//     (the verbatim JSB floor, _DAT…→0.03). A small minority of plays, as in JSB.
//   - foul (foulBucketWeight) = the faithful JSB 5.60 foul bucket with a symmetric
//     two-arm BASE plus the small ±0.2 half-court HCA legs (jsb560_decompiled.c:97116-
//     97173, J15 Phase 5 2026-07-11): a DETERMINISTIC base (2.0 − bh.fatigue)·tovRate(bh)
//     − s·hca (leg B) — NOT a stochastic draw — multiplied by a defQ/offQ coupling
//     factor = 1 + (defQ − baseline)/offQ with offQ = Σ (TOV − s·hca) (leg C), INCREASING
//     in defQ (a steal-gambling defense fouls MORE). The BASE two-arm structure is
//     side-symmetric (Fable-confirmed, SUPERSEDING the ADR-0082 home-deterministic/away-
//     stochastic stand-in); the ±0.2 site-2/site-3 HCA legs are the small RAW delta the
//     R1 rewrite over-symmetrized away and Phase 5 restores. Net foul is anti-home (leg B
//     dominates leg C by ~an order of magnitude, ratio ~0.91) — the home MARGIN is a
//     scoring phenomenon (the 2pt +hcaScaled leg A and e88/e90 and-one leg D,
//     possession.go), not a foul one.
//     The result is multiplied by foulBucketScale (raw-5.60-units → Go-bucket-basis;
//     see the const).
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

	// d70LeagueScalar carries D70's league-relative factor S =
	// (leaguePF48×5 − leagueTOV48×0.5)/(leagueFTA48×5) — asm 4d4380–4d43cd
	// (COMPOSITE_DOUBLES_TRACE.md §3; RE artifact
	// jsb-J6-composite-scales-20260710.md §2). The three inputs are FUN_004385f0
	// league-table means computed from the 5.60 game-install IBL5.plr (records
	// 1–959, non-empty name, MIN>2·GP, n=376): leaguePF48 = 4.294130331651356,
	// leagueTOV48 = 3.3531432843116113 (matches the leagueTOV48 pin in
	// teamquality.go exactly), leagueFTA48 = 6.116607485995505 — giving S =
	// 0.6472241372826754. This is a static pin from the same league table as
	// teamquality.go's leagueTOV48/leagueSTL48, not a runtime CEngine read (ADR-0040
	// negative finding 2 — the "loader-populated, not modeled" class); a per-season
	// dynamic league table is a tracked follow-up, not this change.
	d70LeagueScalar = 0.6472241372826754

	// C+0x6668: league FGA/48 (3GA+2PA), J18 filter, current-season .plr; proven by TestRealLifeFixture_LeagueCalibration
	leagueFGA48 = 25.035101086336688

	// C+0x6938 and C+0x6728: league PF/48 and FTA/48, the J18 pins the
	// d70LeagueScalar comment records at full precision. The bucket-3 chain
	// (FUN_004e1ba0) reads them with leagueFGA48 and leagueTOV48.
	leaguePF48  = 4.294130331651356
	leagueFTA48 = 6.116607485995505

	// +0xD90 Branch-A pinned constants (COMPOSITE_DOUBLES_TRACE.md §5): the 0.5
	// (_DAT_00669ef0) and 0.25 (_DAT_00669f58) factors in the make-share weighting.
	d90MakeShareHalf    = 0.5
	d90MakeShareQuarter = 0.25

	// foulFloor is the CEILING of the :97170 non-positive FLOOR REDRAW ONLY — U[0,
	// foulFloor) — fired when w = base·factor ≤ 0. It is NOT the base (that was the
	// pre-correction C2 error; see foulBucketWeight). Applied SYMMETRICALLY (either
	// side can trigger the redraw; J6/J16).
	foulFloor = 0.6

	// foulBaseFatigueRef (_DAT_00669f38 = 2.0, :97126) is the base coefficient in
	// base = (2.0 − fatigue)·BH_TOV48 — the DETERMINISTIC foul-propensity base (see
	// foulBucketWeight). Named foulBaseFatigueRef because it is the fatigue reference
	// point the ball-handler's live fatigue is subtracted from, not a foul-count.
	foulBaseFatigueRef = 2.0

	// foulDivisorTeamDefCoef = 5/6 (_DAT_0066d3a0 = 0.8333, PE 0x66D3A0, J6 line 95),
	// the coefficient on the league-STL BASELINE subtracted from defQ in the faithful
	// coupling factor = 1 + (defQ − foulDivisorTeamDefCoef·defQualityCapTeamMult·
	// leagueSTL48)/offQ (verbatim :97163). Corrected 2026-07-11 (C1): J6 §5's inline
	// paraphrase "1 − defQ·(5/6)/offQ" was a mis-transcription — the decompile at
	// :97163 is INCREASING in defQ and league-baselined, not a decreasing raw ratio.
	foulDivisorTeamDefCoef = 0.8333333333

	// foulBucketScale is the bucket-basis conversion for the faithful symmetric foul
	// bucket: the 5.60 e80 basis is in raw bucket units, while the Go engine's
	// play-outcome basis is ~8× larger (2pt composite O(10s), ≈16.5 under default
	// ratings). It is the SINGLE calibrated LEVEL dial — the defQ/offQ FORMS and the
	// 5/6 coupling are DERIVED (guarded by faithful_scales_derivation_test.go), and
	// the home/away symmetry (ratio ≈ 1.0) is STRUCTURAL, independent of this scale.
	// Unscaled, the foul share collapses and the FTA level breaks against the .sco
	// archive. This value is the ADR-0082-status corpus-calibrated level. Re-anchored
	// a SECOND time (2026-07-12) after the J18 items 1+3 faithful bucket-basis changes
	// (real 3GA/48 3pt bucket + 2PA-based d88 twoPtBucketWeight) shrank the competing
	// 2pt/3pt bucket mass, which raised the foul bucket's normalized share and pushed
	// engine FTA/g from 21.36 to 24.20 at the then-current 0.47 — breaking the anchor
	// against the same 00-01 .sco target (FTA/g ≈ 21.32) without any change to this
	// dial's own value. FTA saturates sub-linearly in this dial (foul-outs cap
	// minutes), so it was re-found by 1-D search (0.39→20.99, 0.40→21.43, 0.41→21.81,
	// 0.47→24.20), not a linear estimate. Re-anchored to 0.40 → engine FTA 21.43
	// (gap +0.11). The level was re-tuned AFTER the Phase-5 margin lock
	// (hcaSite2BasisScale 2.85, unchanged): the two dials remain only weakly coupled —
	// moving this dial from 0.47 to 0.40 moved the gt=2 home margin gap from +0.459 to
	// +0.470 (delta +0.011), i.e. WITHIN the ~±0.03 Monte-Carlo noise floor of the
	// 20-run harness, so re-anchoring FTA a second time did not disturb the locked
	// margin. (The gt=4 gap moved further, +1.284 → +1.752 — gt=4 was never part of
	// the Phase-6 weak-coupling claim, which is scoped to gt=2; not re-litigated here.)
	// See the J15 program / phase2-derivation.md and the Phase-6 (2026-07-11) history
	// this supersedes for the first re-anchor's story (0.50→22.43, 0.45→20.67,
	// 0.47→21.36). Re-anchored a THIRD time (2026-07-13) after J18 item 2 pinned
	// d70LeagueScalar 1.0→0.6472 (a faithful basis change: it shrank the d70 FTA-rate
	// term inside twoPtBucketWeight, lowering 2pt bucket mass and raising the foul
	// share — combined-master FTA/g read 21.82 at the then-current 0.40). 1-D search
	// on the combined basis: 0.39→21.36, 0.40→21.82 → re-anchored to 0.39 (gap +0.04
	// vs the .sco 21.32 target). The gt=2 home-margin gap moved +0.498 (at 0.40) →
	// +0.491 (at 0.39), delta −0.007, within the ~±0.03 noise floor — the dial stayed
	// side-symmetric and did not disturb the Phase-5 margin lock, consistent with the
	// weak-coupling claim above.
	foulBucketScale = 0.39

	// andOneBucketFloor is the verbatim JSB and-one floor (0.03). Ensures the
	// and-one path cannot be zeroed by a negative matchup quality. Unchanged from
	// the faithful #952 basis.
	andOneBucketFloor = 0.03

	// D78 block memory operands (asm 4d45ff-4d4640). 0x669ef0 (0.5) and
	// 0x669f58 (0.25) reuse d90MakeShareHalf and d90MakeShareQuarter.
	dat66be10 = 0.001 // _DAT_0066be10 = 0.001 (PE read, 0x66d318 = 0.2 calibrated)
	dat66c7d8 = 0.05  // _DAT_0066c7d8 = 0.05 (PE read, 0x66d318 = 0.2 calibrated)
	dat669ae8 = 0.02  // _DAT_00669ae8 = 0.02 (PE read, 0x66d318 = 0.2 calibrated)

	// FUN_004e1ba0 bucket-3 literals with no named engine twin. The first four
	// are the fatigueFactor (state.go) /30, +100, ×0.01 and 1.0-cap slots; the
	// last is the function-local matchup matchedWeight (matchup.go).
	dat66d3a8 = 0.03333333333333333 // _DAT_0066d3a8 = 1/30 (PE read, 0x66d318 = 0.2 calibrated)
	dat669f40 = 100.0               // _DAT_00669f40 = 100.0 (PE read, 0x66d318 = 0.2 calibrated)
	dat669e80 = 0.01                // _DAT_00669e80 = 0.01 (PE read, 0x66d318 = 0.2 calibrated)
	dat669ed8 = 1.0                 // _DAT_00669ed8 = 1.0 (PE read, 0x66d318 = 0.2 calibrated)
	dat669e78 = 0.8                 // _DAT_00669e78 = 0.8 (PE read, 0x66d318 = 0.2 calibrated)

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

// db8Rate is the +0xDB8 per-48 offensive-rebound rate, extracted from
// twoPtBucketWeight: ORB/MIN×48 with real-life minutes, else the rating stand-in.
func db8Rate(p onCourt) float64 {
	if p.RealLifeMIN > 0 {
		return per48Min(p.RealLifeORB, p.RealLifeMIN)
	}
	return floor1(p.ORB) * orbRateScale
}

// d70Rate is the +0xD70 FTA-weighted rate, extracted from twoPtBucketWeight:
// FTA/MIN×48 × d70LeagueScalar with real-life minutes, else the rating stand-in.
func d70Rate(p onCourt) float64 {
	if p.RealLifeMIN > 0 {
		return per48Min(p.RealLifeFTA, p.RealLifeMIN) * d70LeagueScalar
	}
	return floor1(p.FTA) * ftaRateScale
}

// d78Composite is player +0xD78 (asm 4d45af-4d4662):
//
//	d70 − (d70/d90)·db8·(0.5·d88/(db8+d88) + 0.25) − (2P‰·0.001·d88·0.05 + da8·0.02)
//
// with d90 = twoPtBucketWeight and da8 = threePtBucketWeight. Both guards
// (db8+d88 > 0 at 4d45da, d90 > 0 at 4d45eb-4d45fd) send ≤0 or NaN to the
// else-0 store. Rookies (RealLifeMIN == 0) take JSB's else-0 branch: their
// divides yield NaN and fail both guards. When d88+d70 <= 0, JSB skips the
// +0xD90 store and keeps a prior value the trace could not confirm; this port
// returns 0 there.
func d78Composite(p onCourt) float64 {
	if p.RealLifeMIN <= 0 {
		return 0
	}
	d88, db8, d70 := twoPARate(p), db8Rate(p), d70Rate(p)
	if d88+d70 <= 0 {
		return 0 // JSB skips the +0xD90 store here; see the doc comment
	}
	d90 := twoPtBucketWeight(p)
	if !(db8+d88 > 0) || !(d90 > 0) {
		return 0
	}
	m := d88/(db8+d88)*d90MakeShareHalf + d90MakeShareQuarter
	t := float64(p.D60)*dat66be10*d88*dat66c7d8 + threePtBucketWeight(p)*dat669ae8
	return d70 - (d70/d90)*db8*m - t
}

// twoPARate is the +0xD88 per-48 two-point-attempt rate (2PA = FGA − 3GA),
// bucketweights.go:203 / J6 RE. Extracted verbatim from twoPtBucketWeight so the
// same rate can serve as the usage-dominance numerator (usagedominance.go) without
// duplicating the two-branch real-life-minutes vs stand-in computation.
func twoPARate(p onCourt) float64 {
	if p.RealLifeMIN > 0 {
		twoPA := p.RealLifeFGA - p.RealLife3GA
		if twoPA < 0 {
			twoPA = 0 // corrupt record guard: 3GA can never exceed FGA in valid .plr data
		}
		return per48Min(twoPA, p.RealLifeMIN)
	}
	return floor1(p.FGA) * fgaRateScale
}

// twoPtBucketWeight is the recovered +0xD90 Branch-A cold composite
// (jsb560_decompiled.c:91078-91086, COMPOSITE_DOUBLES_TRACE.md §4):
//
//	D90 = D88 − (D88/(D70+D88)) × DB8 × ((D88/(DB8+D88)) × 0.5 + 0.25)
//
// where D88 = per-48 2PA rate (2PA = FGA − 3GA), DB8 = per-48 ORB rate, D70 = the
// FTA-weighted rate. The composite formula is unchanged; what changed (ADR-0040,
// candidate A) is its INPUTS. When the bundle carries the real-life minutes
// (RealLifeMIN > 0) D88/DB8/D70 are the FAITHFUL per-48-MINUTE rates (stat/MIN)×48
// — the wide team-to-team spread 5.60 disperses team offense on, in the same
// O(10s) magnitude as the stand-in (a high-volume player ≈ 27 2PA/48). Absent them
// (rookie / unwired production bundle) it falls back to the compressed rating
// stand-in (fgaRateScale etc.), the behavior this PR preserves byte-for-byte for
// the no-reference case. (Using minutes, not games, is load-bearing: per-48-GAMES
// would be ~55× larger, driving the 2pt bucket to O(100s) and collapsing the
// foul/FTA play-outcome share to ~0 — verified degenerate against the .sco
// corpus.)
//
// 5.60's +0xD88 composite input is the two-point-attempt rate, not total FGA (J6
// RE session 2026-07-10, FUN_004cfa50 stack-record store family — the same
// provenance chain as +0xDB0/+0xDC8/+0xD70), parallel to the league 2PA/48
// baseline at CEngine+0x6638 (assemble.go computeLeagueShotBaseline): both
// subtract 3GA from the combined FGA total to isolate the two-point economy
// 5.60 feeds this bucket.
//
// It is net-free: in JSB net enters only via shot_value, so the playoff ×1.25
// multiplier never amplifies this bucket. The composite is O(10s), keeping the
// 0.6-floored foul bucket a realistic minority share and field-goal attempts the
// majority path.
func twoPtBucketWeight(p onCourt) float64 {
	d88, db8, d70 := twoPARate(p), db8Rate(p), d70Rate(p)
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

// threePtBucketWeight is the recovered +0xDB0 composite (FUN_004cfa50's
// stack-built player record; copied into the league record by FUN_00405970 — the
// same provenance chain as +0xDC8/+0xD70): the player's season 3GA/MIN×48, an
// f-projected per-48-minute three-point-attempt rate. LIVE, not dead: the J6 RE
// session (2026-07-10) overturned COMPOSITE_DOUBLES_TRACE.md §RESOLUTION's "always
// 0" finding — FUN_004e1ba0's Σ(de0+db0+d90) play-outcome normalization reads it
// directly. When the bundle carries real-life minutes (RealLifeMIN > 0) the weight
// is the FAITHFUL per48Min(RealLife3GA, RealLifeMIN) rate — a non-shooter correctly
// gets a zero 3pt bucket, mirroring 5.60. Absent real-life minutes (rookie / unwired
// production bundle) it falls back to the previous derived stand-in (2pt composite ×
// 3pt propensity), preserved byte-for-byte for the no-reference case — the same
// two-branch shape as twoPtBucketWeight.
//
// Legacy-serialized-bundle caveat: a bundle written before this port's rl_3ga field
// existed deserializes RealLife3GA as 0 while RealLifeMIN > 0 carries over, zeroing
// the 3pt bucket for that player. Acceptable: bundles are assembled fresh from .plr
// at runtime rather than persisted across ports — the same caveat the J9
// LeagueShotBaseline field (RealLifeFGA) already carries for an identical
// field-addition case.
func threePtBucketWeight(p onCourt) float64 {
	if p.RealLifeMIN > 0 {
		return per48Min(p.RealLife3GA, p.RealLifeMIN)
	}
	return twoPtBucketWeight(p) * threePtPropensity(p)
}

// andOneBucketWeight is the matchup×0.25 + made-rate stand-in, floored to
// andOneBucketFloor (0.03, the verbatim JSB floor). The mq term (≈−0.02 under
// default ratings) contributes negligible negative weight; the made-rate term
// carries the bucket above the floor. Unchanged from the faithful #952 basis.
func andOneBucketWeight(mq float64, p onCourt) float64 {
	return max(andOneBucketRaw(mq, p), andOneBucketFloor)
}

// bucket3Inputs carries the traced FUN_004e1ba0 inputs.
type bucket3Inputs struct {
	energy     float64 // bh record +0x24
	bhD78      float64 // local_e78 = bh +0xD78
	lineupD78  float64 // dVar5: sum of +0xD78 over the offense five
	lineupSum3 float64 // sum of +0xDE0 + +0xDB0 + +0xD90 over the offense five
	defDF0     float64 // defending team +0xDF0
}

// bucket3E88Initial: min((energy/30 + 100) * 0.01, 1.0). JSB does not clamp
// negative energy, so this does not call fatigueFactor.
func bucket3E88Initial(energy float64) float64 {
	e := (energy*dat66d3a8 + dat669f40) * dat669e80 // _DAT_0066d3a8, _DAT_00669f40, _DAT_00669e80
	if dat669ed8 < e {                              // _DAT_00669ed8
		return dat669ed8
	}
	return e
}

// bucket3Clamp is the one-sided clamp at asm 4e1d90-4e1dc4. A value below the
// anchor drops to anchor − 1.0; a value above anchor + 1.0 is capped there.
func bucket3Clamp(v, anchor float64) float64 {
	if v < anchor {
		return anchor - dat669ed8 // _DAT_00669ed8
	}
	if v > anchor+dat669ed8 { // _DAT_00669ed8
		return anchor + dat669ed8
	}
	return v
}

// bucket3E88 returns ok=false when lineupD78 is not positive (or NaN); JSB
// divides unguarded there (FUN_004e1ba0 :97157), so the caller keeps the
// rating path.
func bucket3E88(in bucket3Inputs) (float64, bool) {
	if !(in.lineupD78 > 0) {
		return 0, false
	}
	// _DAT_0066d318 (branchBTeamScale), _DAT_00669e78 (dat669e78)
	dVar7 := in.lineupSum3 * branchBTeamScale / ((leagueFTA48 + leagueFGA48 + leagueTOV48) * dat669e78)
	// _DAT_00669e78 (dat669e78), _DAT_00669ea0 (defQualityCapTeamMult)
	v := bucket3Clamp(in.defDF0*dat669e78*dVar7, leaguePF48*dat669e78*dVar7*defQualityCapTeamMult)
	return (v / in.lineupD78) * in.bhD78 * bucket3E88Initial(in.energy), true
}

// bucket3Weight: half-court adds the raw signed hca, then e90 = mq*0.25 + e88,
// redrawn U[0, 0.03) when e90 is not positive (asm 4e1e47-4e1e69).
func bucket3Weight(e88, mq, hcaRaw float64, halfCourt bool, r *rng.RNG) float64 {
	if halfCourt {
		e88 += hcaRaw
	}
	e90 := mq*d90MakeShareQuarter + e88 // _DAT_00669f58
	if e90 <= 0 {                       // _DAT_00669f00 = 0.0
		return r.Float64() * andOneBucketFloor // FUN_005c6100(0.03)
	}
	return e90
}

// bucket3InputsFor assembles the FUN_004e1ba0 inputs from the offense five and
// the defending team. It returns false when the defending team carries no DF0
// (a team absent from bundle.Teams, or one with no real-life minutes). Every
// lineup term is a per-player composite; the caller's 3pt weight never enters,
// because the OReb path zeroes it.
func bucket3InputsFor(bh onCourt, offense []onCourt, def *bundle.Team) (bucket3Inputs, bool) {
	if def == nil || def.DF0 == nil {
		return bucket3Inputs{}, false
	}
	in := bucket3Inputs{energy: float64(bh.energy), bhD78: d78Composite(bh), defDF0: *def.DF0}
	for _, p := range offense {
		in.lineupD78 += d78Composite(p)
		in.lineupSum3 += tovRate(p) + threePtBucketWeight(p) + twoPtBucketWeight(p) // +0xDE0 + +0xDB0 + +0xD90
	}
	return in, true
}

// andOneAndFoulWeights computes JSB index 3 (and-one) and then index 4 (foul),
// the FUN_004e1ba0 draw order: each may redraw once from r. Index 3 takes the
// ported e88-e90 chain when the defending team's DF0 and the lineup D78 sum are
// both defined. Otherwise it keeps the rating path (halfCourtAndOneWeight on a
// half-court trip, andOneBucketWeight on transition), which never draws.
func andOneAndFoulWeights(bh onCourt, offense, defenders []onCourt, def *bundle.Team, hca, hcaRaw, hcaScaled, mq float64, halfCourt bool, r *rng.RNG) (andOneW, foulW float64) {
	ported := false
	if in, ok := bucket3InputsFor(bh, offense, def); ok {
		if e88, ok := bucket3E88(in); ok {
			andOneW = bucket3Weight(e88, mq, hcaRaw, halfCourt, r)
			ported = true
		}
	}
	switch {
	case ported:
	case halfCourt:
		andOneW = halfCourtAndOneWeight(mq, bh, hcaScaled)
	default:
		andOneW = andOneBucketWeight(mq, bh)
	}
	foulW = foulBucketWeight(bh, offense, defenders, hca, mq, r)
	return andOneW, foulW
}

// andOneBucketRaw is the unfloored and-one stand-in. The half-court caller adds
// the HCA term to it before applying andOneBucketFloor, the JSB order
// (FUN_004e1ba0 97158-97168: local_e88 takes the HCA addend, local_e90 inherits
// it, and only then is local_e90 floored).
func andOneBucketRaw(mq float64, p onCourt) float64 {
	return mq*0.25 + float64(floor1(p.FGP))*andOneMadeRateScale
}

// halfCourtAndOneWeight is the half-court and-one bucket weight: the HCA term
// joins the raw weight first, then andOneBucketFloor applies to the sum
// (FUN_004e1ba0 97165-97168: local_e90 inherits local_e88's +hca, floored
// after). andOneHalfCourtScale (J27 rate-port stand-in) scales only the raw
// weight; the HCA term carries the home margin and stays unscaled.
func halfCourtAndOneWeight(mq float64, bh onCourt, hcaScaled float64) float64 {
	return max(andOneHalfCourtScale*andOneBucketRaw(mq, bh)+hcaScaled, andOneBucketFloor)
}

// foulBucketWeight is the faithful JSB 5.60 foul bucket: a symmetric two-arm BASE
// plus the small ±0.2 half-court HCA legs (jsb560_decompiled.c:97116-97173, J15
// Phase 5 2026-07-11). It takes the ball handler, the offense AND defense lineups,
// and the per-team hca delta (±0.2 home/away, 0 for ASG/transition):
//
//	base    = (2.0 − bh.fatigue) · tovRate(bh) − s·hca             // :97126/:97160 leg B, DETERMINISTIC, RAW hca
//	defQ    = Σ_{5 defenders} STL/MIN×44 (capped)                  // defQuality (teamquality.go)
//	offQ    = Σ_{offense} (TOV/MIN×48 − s·hca)                     // offQualityWithHCA, leg C, RAW hca
//	factor  = 1 + (defQ − foulDivisorTeamDefCoef·defQualityCapTeamMult·leagueSTL48)/offQ  // :97163
//	w       = base · factor · (1 − mq/(4·leagueTOV48))             // :97163 coupling, :97164 shrink
//	if w <= 0 { w = rng.Float64()·foulFloor }                      // :97170 floor redraw ONLY
//	return w · foulBucketScale
//
// HCA legs B/C use the RAW hca (±0.2) — offQ/base are on the faithful CEngine TOV48
// basis (do NOT apply hcaSite2BasisScale, which is for the O(10s) 2pt bucket). Net
// foul is anti-home: leg B (base −hca, ~6% of the ~3.35 base) dominates leg C (offQ
// −hca, a sub-1% shift on the ~1.09 factor) by ~an order of magnitude (measured ≈9.5×,
// TestBucketWeights_FoulBucketHCALegs_DecompilePin), so home draws slightly FEWER
// fouls (ratio ~0.91). leg C's own sign is conditional on sign(defQ − baseline) — it
// is pro-home only for a strong-steal defense — but the defQ cap bounds it far below
// leg B, so the net is anti-home either way. The home MARGIN is carried by the SCORING
// legs (2pt +hcaScaled leg A, and-one leg D — possession.go), not by fouls. The BASE
// two-arm structure is side-symmetric (Fable-confirmed,
// superseding the ADR-0082 home-deterministic/away-stochastic stand-in); only the
// small ±0.2 delta is home/away-dependent.
//
// The base is DETERMINISTIC (no rng) — the old `U[0, foulFloor)` base was WRONG
// (C2); that draw is now used ONLY by the ≤0 floor-redraw guard below. The coupling
// factor is INCREASING in defQ (a steal-gambling defense fouls MORE, league-
// baselined against foulDivisorTeamDefCoef·defQualityCapTeamMult·leagueSTL48 ≈
// 7.6417) — the old "1 − defQ·(5/6)/offQ" (decreasing, no baseline) was WRONG (C1).
// The `w <= 0 → redraw` guard stays LIVE and REACHABLE, symmetrically: a weak
// coupling (defQ far below baseline relative to offQ) can drive w ≤ 0 on EITHER
// side. offQ > 0 always (floor1 floors TVR at 1, small hca), so the divide is
// guarded; a defensive guard on offQ ≤ 0 returns base only (no factor applied).
//
// The :97164 net-advantage shrink (J18 item 6, ported 2026-07-12) multiplies the
// coupled weight by 1 − mq/(4·leagueTOV48) before the ≤0 redraw check, on both
// param_5 (home/away) paths. Its 5.60 operand is param_6 = FUN_004e3860's return
// (pinned at the :93276-93293 call site: fVar22 → dVar1 → the double arg slot),
// which is matchupQuality — NOT the ODPT netAdvantage, whose O(10s) scale would
// blow past the 4·leagueTOV48 = 13.4126 redraw threshold every possession. With
// matchupQuality's Phase 3/4 aggregates still stubbed to 0 (matchup.go), mq =
// −(FGP·0.2 − 9.9)·0.2 ∈ roughly [−0.5, +0.8], so the shrink is a ±few-% foul-
// share modulation today and matures automatically when those aggregates land.
// The redraw threshold (mq > 13.4126, J16 §4) stays unreachable at stub values —
// exactly 5.60's behavior, where realistic rosters never reach it either.
func foulBucketWeight(bh onCourt, offense, defenders []onCourt, hca, mq float64, r *rng.RNG) float64 {
	// leg B (site-2 e80, decompile :97160): the foul BASE is reduced by the RAW s·hca
	// BEFORE the coupling factor. Home (hca>0) → smaller base → home draws FEWER fouls
	// (anti-home). RAW, not scaled: the base is on the faithful CEngine TOV48 basis.
	base := (foulBaseFatigueRef-bh.fatigue)*tovRate(bh) - hca
	// leg C (site-3 offQ, decompile :97159): each offensive player's offQ term loses
	// the RAW s·hca. Home → smaller offQ → factor moves off 1 toward sign(defQ−baseline)
	// (pro-home only for a strong-steal defense). leg B dominates leg C by ~an order of
	// magnitude, so the net is anti-home (ratio ~0.91) regardless of leg C's sign.
	offQ := offQualityWithHCA(offense, hca)
	// :97164 net-advantage shrink (J18 item 6): straight-line in 5.60, so it applies
	// on the defensive offQ ≤ 0 path too.
	shrink := 1.0 - mq/(4.0*leagueTOV48)
	if offQ <= 0 {
		// divide-by-zero guard (unreachable: floor1 ⇒ offQ > 0); base only, no factor.
		return base * shrink * foulBucketScale
	}
	baseline := foulDivisorTeamDefCoef * defQualityCapTeamMult * leagueSTL48
	factor := 1.0 + (defQuality(defenders)-baseline)/offQ
	w := base * factor * shrink
	if w <= 0 {
		// faithful floor redraw (:97170) — reachable symmetrically.
		return r.Float64() * foulFloor * foulBucketScale
	}
	return w * foulBucketScale
}
