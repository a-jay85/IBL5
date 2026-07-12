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
	// archive. This value is the ADR-0082-status corpus-calibrated level, re-anchored
	// (Phase 6, 2026-07-11) for the C2-corrected DETERMINISTIC base (2−fatigue)·TOV48
	// — which is far larger than the old U[0,foulFloor) draw, so the pre-C2 8.6 gave a
	// degenerate ~102 FTA/g. Empirically re-anchored on the archive harness to real
	// FTA/g ≈ 21.32 (the 00-01 sample's paired .sco value): 0.47 → engine FTA 21.36
	// (gap +0.04). FTA saturates sub-linearly in this dial (foul-outs cap minutes), so
	// it was found by 1-D search (0.50→22.43, 0.45→20.67, 0.47→21.36), not the linear
	// estimate. The level was tuned AFTER the Phase-5 margin lock (hcaSite2BasisScale
	// 2.85): the two dials are only weakly coupled — sweeping this dial moved the gt=2
	// home margin non-monotonically (3.332/3.304/3.287 for 0.50/0.45/0.47), i.e. WITHIN
	// the ~±0.03 Monte-Carlo noise floor of the 20-run harness, so re-anchoring FTA did
	// not disturb the locked margin. See the J15 program / phase2-derivation.md.
	foulBucketScale = 0.47

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
	var d88, db8, d70 float64
	if p.RealLifeMIN > 0 {
		twoPA := p.RealLifeFGA - p.RealLife3GA
		if twoPA < 0 {
			twoPA = 0 // corrupt record guard: 3GA can never exceed FGA in valid .plr data
		}
		d88 = per48Min(twoPA, p.RealLifeMIN)
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
	w := mq*0.25 + float64(floor1(p.FGP))*andOneMadeRateScale
	if w < andOneBucketFloor {
		return andOneBucketFloor
	}
	return w
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
//	w       = base · factor
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
// DOCUMENTED DIVERGENCE (J18 item 6, found at ratification 2026-07-12): 5.60
// additionally multiplies e80 ×= 1 − param_6/(4·leagueTOV48) (the J16-pinned
// net-advantage shrink, after the coupling and before the ≤0 redraw check, on
// BOTH param_5 paths) — unported here; the Go netAdvantage feeds shot_value
// only. So this redraw fires only on factor ≤ 0, rarer than in 5.60 (where
// param_6 > 13.41 occasionally triggers it). Porting it changes foul share
// globally and re-opens the level/margin anchors — it gets its own A/B'd PR
// (ADR-0084 Decision 2).
func foulBucketWeight(bh onCourt, offense, defenders []onCourt, hca float64, r *rng.RNG) float64 {
	// leg B (site-2 e80, decompile :97160): the foul BASE is reduced by the RAW s·hca
	// BEFORE the coupling factor. Home (hca>0) → smaller base → home draws FEWER fouls
	// (anti-home). RAW, not scaled: the base is on the faithful CEngine TOV48 basis.
	base := (foulBaseFatigueRef-bh.fatigue)*tovRate(bh) - hca
	// leg C (site-3 offQ, decompile :97159): each offensive player's offQ term loses
	// the RAW s·hca. Home → smaller offQ → factor moves off 1 toward sign(defQ−baseline)
	// (pro-home only for a strong-steal defense). leg B dominates leg C by ~an order of
	// magnitude, so the net is anti-home (ratio ~0.91) regardless of leg C's sign.
	offQ := offQualityWithHCA(offense, hca)
	if offQ <= 0 {
		// divide-by-zero guard (unreachable: floor1 ⇒ offQ > 0); base only, no factor.
		return base * foulBucketScale
	}
	baseline := foulDivisorTeamDefCoef * defQualityCapTeamMult * leagueSTL48
	factor := 1.0 + (defQuality(defenders)-baseline)/offQ
	w := base * factor
	if w <= 0 {
		// faithful floor redraw (:97170) — reachable symmetrically.
		return r.Float64() * foulFloor * foulBucketScale
	}
	return w * foulBucketScale
}
