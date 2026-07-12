package sim

// Team-quality lineup aggregators for the faithful JSB 5.60 foul bucket (J6/J16,
// 2026-07-10; site-3 HCA leg restored 2026-07-11, J15 Phase 5). The 5.60 foul
// weight e80 is coupled to a defense/offense quality factor — NOT the ADR-0082
// asymmetric two-arm stand-in it supersedes:
//
//	factor = 1 + (defQ − baseline)/offQ                       // :97163
//	defQ = Σ_{5 defenders}  STL/MIN×44   (player +0xDD0, LIVE, capped 1.5·5·leagueSTL48)
//	offQ = Σ_{5 offense}     TOV/MIN×48   (player +0xDE0, LIVE)
//
// BASE-STRUCTURE SYMMETRY vs. the SMALL HCA LEG — these coexist:
//   - The two-arm BASE structure IS side-symmetric (Fable-confirmed, refuting the
//     ADR-0082 home-deterministic/away-stochastic stand-in): both defQ and the base
//     read only lineup ratings.
//   - BUT the small ±0.2 site-3 HCA delta is REAL (decompile :97159, param_5==1):
//     each offensive player's offQ term is reduced by s·hca at half-court — the
//     R1 rewrite over-symmetrized by stripping it. offQualityWithHCA restores it.
//     Home (hca>0) → smaller offQ → the coupling factor moves AWAY from 1 in the
//     direction of sign(defQ − baseline): leg C is pro-home only for a strong-steal
//     defense (defQ > baseline), anti-home otherwise. This is the SECONDARY foul leg;
//     the defQ cap bounds |leg C| far below leg B (foul base −hca, bucketweights.go),
//     which dominates it by ~an order of magnitude (measured ≈9.5× on the default
//     fixture, TestBucketWeights_FoulBucketHCALegs_DecompilePin), so the NET foul
//     bucket is anti-home (ratio ~0.91) regardless of leg C's sign. offQuality (no-hca)
//     is the transition/symmetric case; offQualityWithHCA is the half-court live path.
//     See bucketweights.go foulBucketWeight for the coupling + faithful redraw.
//
// STAND-INS (bundle carries no rl_stl/rl_tov counting sums — only RealLifeMIN/FGA/
// FTA/ORB): defQ/offQ use the 0-99 STL/TVR RATINGS as per-48 rate stand-ins, mapped
// through ratingRefScale to the real league per-48 means (leagueSTL48, leagueTOV48).
// Wiring real rl_stl/rl_tov is a production-bundle follow-on (Out of Scope, J6). Only
// the defQ/offQ RATIO drives the shrink (both scales cancel a common factor at a
// balanced matchup — see faithful_scales_derivation_test.go); the foul LEVEL is set
// by the single foulBucketScale dial (bucketweights.go).

const (
	// leagueTOV48 is the real 5.60 league per-48 turnover mean (CEngine[+0x68D8],
	// J16 line 67 = 3.353143). offQuality's rating stand-in is normalized to reproduce
	// this mean, so a rating-ratingRefScale offense sums to 5·leagueTOV48.
	leagueTOV48 = 3.353143

	// leagueSTL48 is the real 5.60 league per-48 steal mean. J does not pin it (J16
	// pins only TOV); it is a documented real-basketball anchor: real STL:TOV ≈ 0.547
	// (steals-per-48 ≈ 1.83 vs turnovers-per-48 ≈ 3.35), the ratio that sets the
	// balanced-matchup shrink 1 − (leagueSTL48/leagueTOV48)·(5/6) ≈ 0.545. Its ABSOLUTE
	// value is not load-bearing (the cap below rarely binds; the RATIO drives the
	// shrink; the level is foulBucketScale) — the STL:TOV ratio is the grounded input.
	leagueSTL48 = 1.834

	// ratingRefScale (50 = 0-99 rating mid-scale) anchors the rating→per-48 stand-in
	// map: a mid-scale rating maps to the league per-48 mean. It CANCELS in the
	// defQ/offQ ratio for any balanced matchup, so it affects only the (rarely-binding)
	// cap and the absolute magnitude — never the shrink or the home/away symmetry.
	ratingRefScale = 50.0

	// stlComposite44 (+0xDD0 = STL/MIN×44, J6 line 51/93, PE 0x66D328) and
	// tovDivisor48 (+0xDE0 = TOV/MIN×48, J6 line 52, PE 0x669ED0) are the faithful
	// per-player composite forms. In the RATING stand-in they are folded into the
	// pinned league means (leagueSTL48 already carries the ×44; leagueTOV48 the /48),
	// so they are documented provenance for those means — asserted by the recompute
	// test — not re-applied per player.
	stlComposite44 = 44.0
	tovDivisor48   = 48.0

	// defQualityCapTeamMult (5.0, _DAT_00669ea0) and defQualityCapMultiplier (1.5,
	// _DAT_00669ac0) form the defQ cap ceiling defQualityCapMultiplier·defQualityCapTeamMult·leagueSTL48
	// (matchup_sub_calc_1_RAW.c, J6 line 94). With realistic ratings the cap rarely
	// binds; it is ported for faithfulness and as a boundary guard against an extreme
	// (all-max-STL) lineup driving the shrink past its redraw threshold.
	defQualityCapTeamMult   = 5.0
	defQualityCapMultiplier = 1.5
)

// stlRate maps a defender's 0-99 STL rating to a per-48 steal-rate stand-in (the
// +0xDD0 STL/MIN×44 composite, whose league mean is leagueSTL48). floor1 floors the
// rating at 1, so stlRate > 0 always.
func stlRate(p onCourt) float64 {
	return floor1(p.STL) / ratingRefScale * leagueSTL48
}

// tovRate maps an offensive player's 0-99 TVR rating to a per-48 turnover-rate
// stand-in (the +0xDE0 TOV/MIN×48 composite, whose league mean is leagueTOV48). NO
// home/away term (J16 §3). floor1 floors at 1, so tovRate > 0 and offQuality > 0.
func tovRate(p onCourt) float64 {
	return floor1(p.TVR) / ratingRefScale * leagueTOV48
}

// defQuality reimplements the 5.60 defensive composite defQ = Σ_{5 defenders}
// STL/MIN×44 (rating stand-in), capped at defQualityCapMultiplier·defQualityCapTeamMult·leagueSTL48.
// A steal-generating (disciplined) defense yields a LARGER defQ, which shrinks the
// opponent's foul bucket harder (fewer offensive fouls drawn) — the faithful sign.
func defQuality(defenders []onCourt) float64 {
	var total float64
	for _, p := range defenders {
		total += stlRate(p)
	}
	ceiling := defQualityCapMultiplier * defQualityCapTeamMult * leagueSTL48
	if total > ceiling {
		return ceiling
	}
	return total
}

// offQuality reimplements the 5.60 offensive composite offQ = Σ_{offense} TOV/MIN×48
// (rating stand-in). It is the coupling DENOMINATOR: a turnover-prone offense (large
// offQ) couples its own foul bucket to defQ LESS. This is the hca=0 case — the
// transition/symmetric path; the half-court live path uses offQualityWithHCA.
func offQuality(offense []onCourt) float64 {
	return offQualityWithHCA(offense, 0)
}

// offQualityWithHCA is offQuality with the site-3 HCA leg (leg C) applied: each
// offensive player's TOV-rate term is reduced by the per-team hca delta (decompile
// :97159, fVar12 = offQ computed WITH the −s·hca per player when param_5==1 — the
// half-court path). Home (hca>0) → smaller offQ → the coupling factor moves away from
// 1 in the direction of sign(defQ − baseline) (pro-home only for a strong-steal
// defense; net foul is still anti-home either way because leg B dominates — see
// bucketweights.go). Uses the RAW hca (±0.2): offQuality is on
// the faithful CEngine leagueTOV48 basis, so the decompile's raw 0.2 is in-basis (do
// NOT apply hcaSite2BasisScale here — that basis-scale is for the O(10s) 2pt bucket
// only). floor1 floors TVR at 1, so with the small hca the sum stays > 0 for any
// realistic lineup; the /offQ divide is additionally guarded in foulBucketWeight.
func offQualityWithHCA(offense []onCourt, hca float64) float64 {
	var total float64
	for _, p := range offense {
		total += tovRate(p) - hca
	}
	return total
}
