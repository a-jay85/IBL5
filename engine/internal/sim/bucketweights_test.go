package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// pathCounts rolls selectOutcome n times (turnover disabled via turnoverDefValue=0)
// and returns the selection frequency for each path.
func pathCounts(in outcomeInputs, n int, seed uint64) map[outcomeCode]int {
	r := rng.New(seed)
	counts := map[outcomeCode]int{}
	for i := 0; i < n; i++ {
		counts[selectOutcome(in, false, false, false, r)]++
	}
	return counts
}

// assembleInputs reproduces the possession.go bucket assembly for a representative
// symmetric-fixture ball-handler/lineup at the given HCA delta (turnover disabled
// so path-selection tests are not diluted by the independent turnover override).
//
// Source: a slotPG starter (mkPlayer defaults: FGA=60, ORB=20, FTA=20, FGP=48,
// OO=6) with five-man away (team 7) defense.
func assembleInputs(foulWeight, hca float64) outcomeInputs {
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 48))
	defenders := fiveStarters(7)
	mq := matchupQuality(bh, defenders, [6]float64{})
	return outcomeInputs{
		twoPtWeight:      twoPtBucketWeight(bh) + hca,
		threePtWeight:    threePtBucketWeight(bh),
		andOneWeight:     andOneBucketWeight(mq, bh),
		foulOnlyWeight:   foulWeight,
		turnoverDefValue: 0,
	}
}

// --- characterization — assembled foul-path mix is side-symmetric ------------
//
// The faithful bucket is side-symmetric (J6/J16): the foul WEIGHT is identical for
// the home and away assemblies (no hca inside the bucket). The only home/away
// difference is the ±hca on the 2pt bucket, which shifts the foul SHARE slightly
// (home's larger 2pt denominator makes foul a marginally smaller share). This test
// confirms home and away foul shares are NEAR-EQUAL — the property that yields a
// ≈1.0 home/away FTA ratio, superseding the old home>away asymmetry (ADR-0082).
// After the Phase-6 re-anchor (foulBucketScale=0.39), the foul share is again a
// 2pt-dominated realistic minority (~9%), so this test asserts BOTH the structural
// side-symmetry AND the re-anchored LEVEL band (phase2-derivation.md).
func TestBucketWeights_FoulPathMix(t *testing.T) {
	const n = 200_000
	const seed = uint64(1988)

	off := fiveStarters(3)
	def := fiveStarters(7)
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 48))
	// Analytic foul weight: DETERMINISTIC base (2−fatigue)·tovRate(bh) [fatigue≡1.0
	// under the engine curve ⇒ base = tovRate(bh)] × the C1-corrected coupling factor
	// = 1 + (defQ − foulDivisorTeamDefCoef·defQualityCapTeamMult·leagueSTL48)/offQ
	// (:97163, increasing in defQ) × scale. Identical for both sides — the bucket has
	// no home/away term.
	base := (foulBaseFatigueRef - bh.fatigue) * tovRate(bh)
	baseline := foulDivisorTeamDefCoef * defQualityCapTeamMult * leagueSTL48
	factor := 1.0 + (defQuality(def)-baseline)/offQuality(off)
	meanFoul := base * factor * foulBucketScale
	homeIn := assembleInputs(meanFoul, hcaMagnitude)
	awayIn := assembleInputs(meanFoul, -hcaMagnitude)

	homeCounts := pathCounts(homeIn, n, seed)
	awayCounts := pathCounts(awayIn, n, seed)
	homeFoul := float64(homeCounts[outcomeFoulOnly]) / n
	awayFoul := float64(awayCounts[outcomeFoulOnly]) / n

	t.Logf("assembled path mix (n=%d, seed %d): factor=%.4f meanFoul=%.4f", n, seed, factor, meanFoul)
	t.Logf("  home foul-frac=%.4f  away foul-frac=%.4f  (home−away=%+.4f)", homeFoul, awayFoul, homeFoul-awayFoul)

	// LEVEL (restored after the Phase-6 re-anchor of foulBucketScale to 0.50): at the
	// re-anchored scale the foul path is a realistic MINORITY share — observed home
	// foul-frac ≈ 0.090 at this fixture (the 2pt composite 16.47 dominates foul 2.13).
	// The band guards the LEVEL against scale drift: the pre-C2 8.6 gave a degenerate
	// ~0.9 share; an unscaled bucket, ~0.01. A minority in [0.03, 0.15] is the faithful
	// regime this dial was re-anchored to.
	if homeFoul < 0.03 || homeFoul > 0.15 {
		t.Errorf("home foul share = %.4f, want a realistic minority in [0.03, 0.15] (level re-anchored, foulBucketScale=0.39)", homeFoul)
	}
	// Symmetry: home/away foul shares differ ONLY through the ±hca on the 2pt bucket,
	// so they are near-equal (the discriminator property). NOT the old home>away arm.
	if math.Abs(homeFoul-awayFoul) > 0.03 {
		t.Errorf("home/away foul share differ by %.4f (home %.4f, away %.4f) — expected near-equal (symmetric bucket)",
			math.Abs(homeFoul-awayFoul), homeFoul, awayFoul)
	}
}

// --- matrix #4: 2pt bucket = the recovered +0xD90 Branch-A composite ---------
//
// Verifies twoPtBucketWeight matches the hand-computed +0xD90 Branch-A formula at
// known per-48 rate inputs, and that the composite is O(10s) so the 0.6-floored
// foul bucket stays a minority share (2pt dominant).
func TestBucketWeights_TwoPtComposite(t *testing.T) {
	p := oc(slotPG, mkPlayer(1, 3, slotPG, 48)) // FGA=60, ORB=20, FTA=20

	// Hand-compute D90 = D88 − (D88/(D70+D88))·DB8·((D88/(DB8+D88))·0.5 + 0.25).
	d88 := 60.0 * fgaRateScale // 18.0
	db8 := 20.0 * orbRateScale // 3.0
	d70 := 20.0 * ftaRateScale // 6.0
	makeShare := (d88/(db8+d88))*d90MakeShareHalf + d90MakeShareQuarter
	want := d88 - (d88/(d70+d88))*db8*makeShare // ≈ 16.4732

	if got := twoPtBucketWeight(p); math.Abs(got-want) > 1e-9 {
		t.Errorf("twoPtBucketWeight = %.6f, want recovered D90 = %.6f", got, want)
	}

	// The foul weight matches the hand-recomputed faithful formula AND — restored after
	// the Phase-6 re-anchor (foulBucketScale=0.39) — the 2pt composite DOMINATES it
	// (≈16.47 vs ≈2.13 at this fixture), the minority-foul-share invariant the original
	// +0xD90 characterization pinned.
	off := fiveStarters(3)
	def := fiveStarters(7)
	base := (foulBaseFatigueRef - p.fatigue) * tovRate(p)
	baseline := foulDivisorTeamDefCoef * defQualityCapTeamMult * leagueSTL48
	factor := 1.0 + (defQuality(def)-baseline)/offQuality(off)
	wantFoul := base * factor * foulBucketScale
	foul := foulBucketWeight(p, off, def, 0, 0, rng.New(1))
	if math.Abs(foul-wantFoul) > 1e-9 {
		t.Errorf("foulBucketWeight = %.6f, want base·factor·scale = %.6f", foul, wantFoul)
	}
	if twoPtBucketWeight(p) <= foul {
		t.Errorf("2pt composite %.4f must dominate the foul bucket %.4f (minority-foul-share invariant)", twoPtBucketWeight(p), foul)
	}
	t.Logf("2pt composite=%.4f foul=%.4f 3pt=%.4f", twoPtBucketWeight(p), foul, threePtBucketWeight(p))
}

// --- faithful side-symmetric foul bucket: base·(1 + (defQ − baseline)/offQ) --
//
// Verifies the weight equals the hand-computed base·factor·scale (base is now
// DETERMINISTIC — the old U[0,foulFloor) draw was the C2 error, only the ≤0 floor
// redraw still draws); that it has NO home/away term (identical for either side);
// that the deterministic value is finite and non-negative; and — the property the
// old asymmetric arm lacked — that the faithful `w <= 0` redraw is REACHABLE
// symmetrically (weak-STL defenders vs a low-TOV offense drive factor ≤ 0, per the
// C1-corrected INCREASING-in-defQ coupling).
func TestBucketWeights_FoulDivisor(t *testing.T) {
	off := fiveStarters(3)
	def := fiveStarters(7)
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 48))

	// Formula: base·factor·scale. base = (2−fatigue)·tovRate(bh), DETERMINISTIC (no
	// rng draw at all when factor > 0 — the redraw guard below is never reached).
	base := (foulBaseFatigueRef - bh.fatigue) * tovRate(bh)
	baseline := foulDivisorTeamDefCoef * defQualityCapTeamMult * leagueSTL48
	factor := 1.0 + (defQuality(def)-baseline)/offQuality(off)
	if factor <= 0 {
		t.Fatalf("test setup: balanced-matchup factor %.4f should be > 0 (deterministic path, no redraw)", factor)
	}
	want := base * factor * foulBucketScale
	if got := foulBucketWeight(bh, off, def, 0, 0, rng.New(1)); math.Abs(got-want) > 1e-9 {
		t.Errorf("foulBucketWeight = %.6f, want base·factor·scale = %.6f (factor=%.4f)", got, want, factor)
	}

	// Base-symmetry: called with hca=0 (the symmetric/transition case), the weight
	// depends only on the (bh, offense, defense) inputs — the same seed yields the same
	// value. (The ±0.2 half-court HCA legs are exercised separately; here hca=0 isolates
	// the symmetric base. Structural, not a knob.)
	if a, b := foulBucketWeight(bh, off, def, 0, 0, rng.New(42)), foulBucketWeight(bh, off, def, 0, 0, rng.New(42)); a != b {
		t.Errorf("weight not lineup-deterministic: %v vs %v", a, b)
	}

	// Deterministic (factor > 0): repeated calls on a SHARED rng never advance it —
	// the base/factor path draws no randomness — so every call returns the identical
	// finite, non-negative value.
	r := rng.New(1988)
	for i := 0; i < 1_000; i++ {
		got := foulBucketWeight(bh, off, def, 0, 0, r)
		if math.IsNaN(got) || math.IsInf(got, 0) || got < 0 {
			t.Fatalf("draw #%d non-finite/negative: %v", i, got)
		}
		if math.Abs(got-want) > 1e-9 {
			t.Fatalf("draw #%d = %.6f, want the deterministic %.6f (factor>0 path must not consume rng)", i, got, want)
		}
	}

	// Redraw REACHABLE, symmetrically: WEAK-STL defenders (low defQ, far below the
	// league-baselined threshold) meeting a LOW-TOV offense (small offQ, which
	// amplifies (defQ−baseline)/offQ) drives factor deeply negative — the
	// C1-corrected coupling is INCREASING in defQ, so it is a WEAK defense (not a
	// strong one) that now triggers the redraw. Result stays finite, non-negative,
	// and bounded by the floor·scale ceiling (:97170).
	weakDef := fiveStarters(7)
	for i := range weakDef {
		weakDef[i].STL = 1
	}
	loOff := fiveStarters(3)
	for i := range loOff {
		loOff[i].TVR = 1
	}
	redrawFactor := 1.0 + (defQuality(weakDef)-baseline)/offQuality(loOff)
	if redrawFactor > 0 {
		t.Fatalf("test setup: weak-STL/low-TOV factor %.4f should be ≤ 0 (redraw must be reachable)", redrawFactor)
	}
	ceil := foulFloor * foulBucketScale
	rr := rng.New(5)
	for i := 0; i < 1000; i++ {
		got := foulBucketWeight(loOff[0], loOff, weakDef, 0, 0, rr)
		if math.IsNaN(got) || math.IsInf(got, 0) || got < 0 || got >= ceil {
			t.Fatalf("redraw #%d out of [0, %.2f): %v", i, ceil, got)
		}
	}
	t.Logf("factor(balanced)=%.4f  factor(redraw case)=%.4f  ceil=%.2f", factor, redrawFactor, ceil)
}

// --- decompile-arithmetic pin: the ±0.2 half-court HCA legs (B and C) ---------
//
// The faithfulness ORACLE for the foul bucket is the decompile arithmetic
// (jsb560_decompiled.c FUN_004e1ba0 :97126/:97159-97163), NOT the .sco aggregate
// home/away FTA split (which conflates the per-possession bucket with emergent,
// unmodeled home-lead-driven late-game fouling). This pin computes local_e80 by
// hand from the decompile block for concrete HOME (hca=+0.2), AWAY (hca=−0.2), and
// SYMMETRIC (hca=0) inputs and asserts foulBucketWeight reproduces each — isolating
// port-faithfulness from that aggregate noise (the paired-comparator rule, one
// level down). It also MEASURES the leg-B-vs-leg-C balance rather than asserting an
// a-priori ratio: the two half-court HCA legs are
//   - leg B (:97160, local_e80 -= dVar5): the foul BASE loses s·hca (RAW).
//   - leg C (:97159, fVar12 = FUN_004e3f80 computed WITH −s·hca per offensive
//     player when param_5==1): the offQ DENOMINATOR loses 5·s·hca, pushing the
//     coupling factor AWAY from 1 in the direction of sign(defQ − baseline). Leg C
//     is thus pro-home ONLY when the defense's steal-quality exceeds the league
//     baseline (defQ > baseline); at or below it (as with this fixture) leg C is
//     itself anti-home. Its magnitude is bounded FAR below leg B — the defQ cap
//     (defQualityCapMultiplier·defQualityCapTeamMult·leagueSTL48 ≈ 13.76) keeps
//     (defQ − baseline) too small for leg C to ever rival leg B.
//
// Leg B (a ~6% shift on the ~3.35 base) dominates leg C (a sub-1% shift on the
// ~1.09 factor) by roughly an order of magnitude (measured ≈9.5× here), so the NET
// is anti-home REGARDLESS of leg C's sign: the home foul weight is LOWER than the
// away weight. This is faithful to the decompile; the real .sco pro-home FTA split
// is an emergent effect this per-possession bucket does not (and is not meant to)
// reproduce.
func TestBucketWeights_FoulBucketHCALegs_DecompilePin(t *testing.T) {
	off := fiveStarters(3)
	def := fiveStarters(7)
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 48))
	const hca = hcaMagnitude // +0.2 home / −0.2 away (raw, in-basis for legs B/C)

	// Hand-compute local_e80 exactly as the decompile does, for an explicit hca:
	//   base   = (2.0 − fatigue)·tovRate(bh) − s·hca              (:97126 + :97160 leg B)
	//   offQ   = Σ (tovRate − s·hca)                              (:97159 leg C, param_5==1)
	//   factor = 1 + (defQ − baseline)/offQ                       (:97163)
	//   w      = base·factor·foulBucketScale
	baseline := foulDivisorTeamDefCoef * defQualityCapTeamMult * leagueSTL48
	defQ := defQuality(def)
	wantE80 := func(h float64) float64 {
		base := (foulBaseFatigueRef-bh.fatigue)*tovRate(bh) - h
		offQ := offQualityWithHCA(off, h)
		factor := 1.0 + (defQ-baseline)/offQ
		return base * factor * foulBucketScale
	}

	// The port must reproduce the hand-computed e80 for each side (factor > 0 here,
	// so no floor redraw — foulBucketWeight is deterministic and rng is unused).
	for _, tc := range []struct {
		name string
		h    float64
	}{{"symmetric", 0}, {"home", +hca}, {"away", -hca}} {
		want := wantE80(tc.h)
		got := foulBucketWeight(bh, off, def, tc.h, 0, rng.New(1))
		if math.Abs(got-want) > 1e-9 {
			t.Errorf("%s: foulBucketWeight = %.9f, want hand-computed e80 = %.9f", tc.name, got, want)
		}
	}

	wSym := wantE80(0)
	wHome := wantE80(+hca)
	wAway := wantE80(-hca)

	// NET direction: leg B dominates ⇒ the home foul weight is LOWER (anti-home),
	// and the symmetric case sits between the two sides.
	if !(wHome < wSym && wSym < wAway) {
		t.Errorf("expected anti-home ordering wHome < wSym < wAway, got %.6f / %.6f / %.6f", wHome, wSym, wAway)
	}

	// Decompose leg B (base −hca, factor held at the symmetric factor0) vs leg C
	// (offQ −5·hca, base held at base0) to MEASURE the balance the comments claim.
	base0 := (foulBaseFatigueRef - bh.fatigue) * tovRate(bh)
	offQ0 := offQualityWithHCA(off, 0)
	factor0 := 1.0 + (defQ-baseline)/offQ0
	legBOnly := (base0-hca)*factor0*foulBucketScale - base0*factor0*foulBucketScale
	factorCHome := 1.0 + (defQ-baseline)/offQualityWithHCA(off, hca)
	legCOnly := base0*factorCHome*foulBucketScale - base0*factor0*foulBucketScale
	ratio := math.Abs(legBOnly) / math.Abs(legCOnly)
	t.Logf("HCA legs (home side): legB(base)=%+.5f legC(offQ)=%+.5f |B|/|C|=%.1f  net=wHome−wSym=%+.5f  ratio wHome/wAway=%.4f",
		legBOnly, legCOnly, ratio, wHome-wSym, wHome/wAway)

	// leg B (base −hca) is UNCONDITIONALLY anti-home (negative). Leg C's sign is
	// conditional on sign(defQ − baseline) — here defQ < baseline so leg C is also
	// anti-home; it is pro-home only for a strong-steal defense. What is invariant is
	// that leg B dominates leg C by a wide margin (the defQ cap bounds |C|), so the
	// net anti-home ordering above holds regardless of leg C's sign.
	if legBOnly >= 0 {
		t.Errorf("leg B (foul base −hca) must be anti-home (negative), got %+.5f", legBOnly)
	}
	if ratio < 3.0 {
		t.Errorf("leg B should dominate leg C by a wide margin, got |B|/|C| = %.1f", ratio)
	}
}

// --- decompile-arithmetic pin: the :97164 net-advantage shrink (J18 item 6) ----
//
// 5.60 multiplies the coupled foul weight by 1 − param_6/(4·leagueTOV48) after the
// :97163 coupling factor and before the :97170 ≤0 redraw check, where param_6 is
// FUN_004e3860's return (= matchupQuality; the :93276-93293 call site pins the
// provenance). This pin asserts the exact multiplicative identity, that mq=0
// recovers the pre-port weight, and that a large positive mq past the redraw
// threshold 4·leagueTOV48 = 13.4126 (J16 §4) drives the weight through the
// :97170 floor redraw — the reachability 5.60 has and realistic rosters never hit.
func TestBucketWeights_FoulNetAdvantageShrink_DecompilePin(t *testing.T) {
	off := fiveStarters(3)
	def := fiveStarters(7)
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 48))

	w0 := foulBucketWeight(bh, off, def, 0, 0, rng.New(1))
	if w0 <= 0 {
		t.Fatalf("test setup: mq=0 weight %.6f should be > 0 (deterministic path)", w0)
	}

	// Exact identity across the realistic mq range (matchupQuality with Phase 3/4
	// stubbed spans ~[−0.5, +0.8]; include wider values short of the threshold).
	threshold := 4.0 * leagueTOV48 // 13.4126
	for _, mq := range []float64{-0.5, -0.02, 0.18, 0.8, 5.0, 13.0} {
		want := w0 * (1.0 - mq/threshold)
		if got := foulBucketWeight(bh, off, def, 0, mq, rng.New(1)); math.Abs(got-want) > 1e-9 {
			t.Errorf("mq=%.2f: foulBucketWeight = %.9f, want w0·(1 − mq/%.4f) = %.9f", mq, got, threshold, want)
		}
	}

	// Past the threshold the shrink goes negative and the :97170 floor redraw fires:
	// results are U[0, foulFloor)·scale, consuming the rng.
	ceil := foulFloor * foulBucketScale
	r := rng.New(5)
	for i := 0; i < 1000; i++ {
		got := foulBucketWeight(bh, off, def, 0, threshold+1.0, r)
		if math.IsNaN(got) || math.IsInf(got, 0) || got < 0 || got >= ceil {
			t.Fatalf("redraw #%d out of [0, %.2f): %v", i, ceil, got)
		}
	}
	t.Logf("w0=%.6f threshold=4·leagueTOV48=%.4f (mq beyond it → floor redraw)", w0, threshold)
}

// --- matrix #9: direction — EV(foul) > EV(2pt) from outcome realizations

// TestBucketWeights_FoulEVExceedsTwoPt locks the expected-value ordering so the
// rescale cannot accidentally invert the HCA story. EV is computed from outcome
// REALIZATIONS (make-rates), NOT from bucket weights.
//
//	EV(foul path)  = 2 FT attempts × FT make-rate ≈ 2 × 0.75 = 1.5 points
//	EV(2pt path)   = 2pt attempt × 2pt make-rate × 2pts ≈ 0.45 × 2 = 0.9 points
//
// The ordering is determined by the rule set (FT draws 2 shots; a 2pt attempt can
// miss), not by this PR — the test LOCKS it against future regression.
func TestBucketWeights_FoulEVExceedsTwoPt(t *testing.T) {
	// Representative make-rates from richBundle ratings (FTP=75, FGP=50):
	//   FT make-rate: FTP=75 → shotValueFT=750‰ → P(make) = 750/1000 = 0.75
	//   2pt make-rate: sv2 ≈ 452 → P(make) ≈ 452/1000 = 0.452
	const ftMakeRate = 0.75     // shotValueFT(FTP=75) = 750‰
	const twoPtMakeRate = 0.452 // shotValue2pt at FGP=50, net≈1

	evFoul := ftMakeRate * 2   // 2 FT attempts × P(make) × 1pt each = expected pts
	ev2pt := twoPtMakeRate * 2 // P(make) × 2pts

	if evFoul <= ev2pt {
		t.Errorf("EV(foul)=%.3f ≤ EV(2pt)=%.3f: outcome EV ordering inverted", evFoul, ev2pt)
	}
	t.Logf("EV(foul)=%.3f EV(2pt)=%.3f — foul is the higher-EV path (locks HCA directionality)", evFoul, ev2pt)
}

// --- ADR-0040 (A): real per-48 volume rates replace the rating stand-ins -------

// Row 8 (characterization): with no real-life minutes (RealLifeMIN==0) the bucket
// equals the current stand-in composite, byte-for-byte — the no-reference fallback
// the change preserves. Setting the sums but leaving MIN==0 must NOT engage the real
// path (MIN is the gate and divisor).
func TestBucketWeights_RealLifeFallbackUnchanged(t *testing.T) {
	p := oc(slotPG, mkPlayer(1, 3, slotPG, 48)) // RealLifeMIN==0 → fallback

	d88 := floor1(p.FGA) * fgaRateScale
	db8 := floor1(p.ORB) * orbRateScale
	d70 := floor1(p.FTA) * ftaRateScale
	makeShare := (d88/(db8+d88))*d90MakeShareHalf + d90MakeShareQuarter
	want := d88 - (d88/(d70+d88))*db8*makeShare

	if got := twoPtBucketWeight(p); math.Abs(got-want) > 1e-9 {
		t.Errorf("fallback weight = %.6f, want stand-in composite = %.6f", got, want)
	}

	// Sums present but MIN==0 → still the fallback (MIN gates the real path).
	p2 := p
	p2.RealLifeFGA = 9999
	if got := twoPtBucketWeight(p2); math.Abs(got-want) > 1e-9 {
		t.Errorf("MIN==0 with sums set engaged the real path: got %.6f, want %.6f", got, want)
	}
}

// Row 9: with real-life minutes the bucket equals the hand-computed +0xD90 over the
// faithful per-48-MINUTE rates (stat/MIN)×48 (D70 scaled by d70LeagueScalar). D88 is
// the 2PA rate (2PA = FGA − 3GA); RealLife3GA is left at its zero default here, so
// twoPA == FGA and the magnitude lands in the O(10s) stand-in regime (d88 ≈ 25.6),
// not the O(100s) the per-48-games divisor would give. Row 15 below exercises the
// 3GA > 0 case where twoPA diverges from FGA.
func TestBucketWeights_RealLifeComposite(t *testing.T) {
	pl := mkPlayer(1, 3, slotPG, 48)
	pl.RealLifeMIN = 2400 // ~34 min/game over 70 games
	pl.RealLifeFGA = 1280 // twoPA = 1280-0 = 1280; d88 = 1280/2400*48 = 25.6
	pl.RealLife3GA = 0    // no threes → twoPA == FGA (see Row 15 for 3GA > 0)
	pl.RealLifeORB = 160  // db8 = 160/2400*48  = 3.2
	pl.RealLifeFTA = 320  // d70 = 320/2400*48  = 6.4 (×1.0)
	p := oc(slotPG, pl)

	twoPA := pl.RealLifeFGA - pl.RealLife3GA
	d88 := per48Min(twoPA, 2400)
	db8 := per48Min(160, 2400)
	d70 := per48Min(320, 2400) * d70LeagueScalar
	makeShare := (d88/(db8+d88))*d90MakeShareHalf + d90MakeShareQuarter
	want := d88 - (d88/(d70+d88))*db8*makeShare

	if got := twoPtBucketWeight(p); math.Abs(got-want) > 1e-9 {
		t.Errorf("real-rate composite = %.6f, want %.6f", got, want)
	}
	if want > 50 {
		t.Errorf("real-rate 2pt bucket = %.2f is O(100s) — the per-48-minute scale must stay O(10s)", want)
	}
}

// Row 10: two players with IDENTICAL FGA ratings but DIFFERENT real-life 2PA rates
// (RealLife3GA left at its zero default, so twoPA == FGA here) produce different
// 2pt weights — the volume signal the compressed rating stand-in flattened away
// (the dispersion mechanism, ADR-0040). Both also differ from the rating-only
// stand-in.
func TestBucketWeights_RealRateDisperses(t *testing.T) {
	lo := mkPlayer(1, 3, slotPG, 48) // FGA rating 60
	lo.RealLifeMIN, lo.RealLifeFGA, lo.RealLifeFTA, lo.RealLifeORB = 2400, 800, 200, 80
	hi := mkPlayer(2, 3, slotPG, 48) // SAME FGA rating 60
	hi.RealLifeMIN, hi.RealLifeFGA, hi.RealLifeFTA, hi.RealLifeORB = 2400, 1600, 200, 80

	wLo := twoPtBucketWeight(oc(slotPG, lo))
	wHi := twoPtBucketWeight(oc(slotPG, hi))
	if !(wHi > wLo) {
		t.Errorf("higher real FGA rate should give a larger 2pt weight: hi=%.4f lo=%.4f", wHi, wLo)
	}

	standin := twoPtBucketWeight(oc(slotPG, mkPlayer(3, 3, slotPG, 48))) // RealLifeMIN==0
	if math.Abs(wLo-standin) < 1e-9 {
		t.Errorf("real-rate weight %.4f indistinguishable from compressed stand-in %.4f", wLo, standin)
	}
}

// Row 11 (boundary): RealLifeMIN>0 with FGA==0 ∧ ORB==0 (played, only ever shot FTs)
// must yield a finite weight, not the 0/0 NaN the real-rate path reopens (the guard).
// The faithful limiting value is d88 == 0 (twoPA == FGA-3GA == 0-0 == 0 here). Rows
// 16/17 below exercise the two OTHER routes to a zero twoPA: an all-three shooter
// and a corrupt 3GA>FGA record.
func TestBucketWeights_RealLifeZeroFGA(t *testing.T) {
	pl := mkPlayer(1, 3, slotPG, 48)
	pl.RealLifeMIN, pl.RealLifeFGA, pl.RealLifeORB, pl.RealLifeFTA = 1200, 0, 0, 300
	got := twoPtBucketWeight(oc(slotPG, pl))

	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Fatalf("FGA==0 ∧ ORB==0 produced a non-finite weight: %v", got)
	}
	if got != 0 {
		t.Errorf("zero-FGA limit = %v, want 0 (d88)", got)
	}
}

// --- J18 item 3: twoPtBucketWeight's D88 is the 2PA rate, not total FGA ------

// Row 15: with RealLife3GA > 0 the faithful d88 is per-48 TWO-point-attempt rate
// (2PA = FGA − 3GA), not per-48 total FGA. The case is chosen so the two bases
// diverge (280 of 1280 attempts are threes), pinning the exact faithful value and
// guarding against a regression to the pre-J18-item-3 total-FGA basis.
func TestBucketWeights_RealLifeTwoPAExcludesThrees(t *testing.T) {
	pl := mkPlayer(1, 3, slotPG, 48)
	pl.RealLifeMIN = 2400
	pl.RealLifeFGA = 1280
	pl.RealLife3GA = 280 // twoPA = 1280-280 = 1000, NOT 1280 (the old total-FGA basis)
	pl.RealLifeORB = 160
	pl.RealLifeFTA = 320
	p := oc(slotPG, pl)

	twoPA := pl.RealLifeFGA - pl.RealLife3GA
	d88 := per48Min(twoPA, 2400) // = 20.0
	db8 := per48Min(160, 2400)
	d70 := per48Min(320, 2400) * d70LeagueScalar
	makeShare := (d88/(db8+d88))*d90MakeShareHalf + d90MakeShareQuarter
	want := d88 - (d88/(d70+d88))*db8*makeShare

	got := twoPtBucketWeight(p)
	if math.Abs(got-want) > 1e-9 {
		t.Errorf("2PA-basis composite = %.6f, want %.6f", got, want)
	}

	// Guard against a regression to the old total-FGA basis: computing d88 straight
	// from FGA (ignoring 3GA) gives a materially different composite.
	oldD88 := per48Min(pl.RealLifeFGA, 2400)
	oldMakeShare := (oldD88/(db8+oldD88))*d90MakeShareHalf + d90MakeShareQuarter
	oldWant := oldD88 - (oldD88/(d70+oldD88))*db8*oldMakeShare
	if math.Abs(got-oldWant) < 1e-6 {
		t.Errorf("composite %.6f matches the old total-FGA basis %.6f — 3GA subtraction not applied", got, oldWant)
	}
}

// Row 16: a pure three-point shooter (FGA == 3GA, every real-life attempt a three)
// with real-life minutes yields twoPA == 0, so the faithful d88 == 0 and the
// guarded composite returns exactly 0 — the same zero-d88 limit as Row 11's
// empty-box-score case, now reached via the 2PA subtraction rather than FGA itself
// being 0.
func TestBucketWeights_RealLifePureThreePointShooterZeroTwoPt(t *testing.T) {
	pl := mkPlayer(1, 3, slotPG, 48)
	pl.RealLifeMIN, pl.RealLifeFGA, pl.RealLife3GA = 1500, 400, 400
	pl.RealLifeORB, pl.RealLifeFTA = 50, 100
	got := twoPtBucketWeight(oc(slotPG, pl))

	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Fatalf("FGA==3GA produced a non-finite weight: %v", got)
	}
	if got != 0 {
		t.Errorf("pure-3pt-shooter (twoPA==0) 2pt bucket = %v, want exactly 0", got)
	}
}

// Row 17 (corrupt-record guard): RealLife3GA > RealLifeFGA cannot occur in valid
// .plr data (3PA is a subset of total FGA), but a corrupt record must not produce
// a negative twoPA — the guard floors it to 0, giving the same zero-d88 limit as
// Row 16 rather than a negative weight that would poison the weighted pick.
func TestBucketWeights_RealLifeThreeGAExceedsFGAGuard(t *testing.T) {
	pl := mkPlayer(1, 3, slotPG, 48)
	pl.RealLifeMIN, pl.RealLifeFGA, pl.RealLife3GA = 1500, 300, 400 // corrupt: 3GA > FGA
	pl.RealLifeORB, pl.RealLifeFTA = 50, 100
	got := twoPtBucketWeight(oc(slotPG, pl))

	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Fatalf("3GA>FGA produced a non-finite weight: %v", got)
	}
	if got < 0 {
		t.Errorf("3GA>FGA (corrupt record) produced a negative weight: %v, want >= 0 (twoPA floored to 0)", got)
	}
	if got != 0 {
		t.Errorf("3GA>FGA guard = %v, want exactly 0 (twoPA floored to 0 == d88 limit)", got)
	}
}

// --- J18 item 1: threePtBucketWeight faithful to the recovered +0xDB0 composite --

// Row 12: with real-life minutes, threePtBucketWeight equals the faithful
// per48Min(RealLife3GA, RealLifeMIN) rate directly — the recovered +0xDB0 composite
// (FUN_004cfa50 stack-record store, copied by FUN_00405970), NOT the derived
// 2pt-composite × propensity stand-in.
func TestBucketWeights_RealLifeThreePt(t *testing.T) {
	pl := mkPlayer(1, 3, slotPG, 48)
	pl.RealLifeMIN = 2000
	pl.RealLife3GA = 200 // per48Min(200, 2000) = 4.8
	p := oc(slotPG, pl)

	want := per48Min(200, 2000)
	if got := threePtBucketWeight(p); math.Abs(got-want) > 1e-9 {
		t.Errorf("threePtBucketWeight = %.6f, want faithful per48Min(RealLife3GA, RealLifeMIN) = %.6f", got, want)
	}
	if standin := twoPtBucketWeight(p) * threePtPropensity(p); math.Abs(want-standin) < 1e-9 {
		t.Fatalf("test setup: faithful rate %.6f coincides with the derived stand-in %.6f — case does not discriminate", want, standin)
	}
}

// Row 13 (boundary): RealLifeMIN>0 with RealLife3GA==0 (played real minutes, never
// attempted a three) must yield an EXACT zero 3pt bucket — faithful to 5.60, which
// gives a non-shooter a zero +0xDB0 weight, not a small propensity-derived residual.
func TestBucketWeights_RealLifeThreePtZeroAttempts(t *testing.T) {
	pl := mkPlayer(1, 3, slotPG, 48)
	pl.RealLifeMIN, pl.RealLife3GA = 1800, 0
	got := threePtBucketWeight(oc(slotPG, pl))

	if got != 0 {
		t.Errorf("non-shooter (RealLife3GA==0) 3pt bucket = %v, want exactly 0 (faithful to 5.60)", got)
	}
}

// Row 14 (unchanged stand-in): with no real-life minutes (RealLifeMIN==0),
// threePtBucketWeight still equals the previous derived stand-in — 2pt composite ×
// 3pt propensity — byte-for-byte, the no-reference fallback this port preserves.
func TestBucketWeights_ThreePtFallbackUnchanged(t *testing.T) {
	p := oc(slotPG, mkPlayer(1, 3, slotPG, 48)) // RealLifeMIN==0 → fallback

	want := twoPtBucketWeight(p) * threePtPropensity(p)
	if got := threePtBucketWeight(p); math.Abs(got-want) > 1e-9 {
		t.Errorf("fallback threePtBucketWeight = %.6f, want twoPtBucketWeight×threePtPropensity = %.6f", got, want)
	}

	// Sums present but MIN==0 → still the fallback (MIN gates the real path, same as
	// twoPtBucketWeight's real-rate gate).
	p2 := p
	p2.RealLife3GA = 9999
	if got := threePtBucketWeight(p2); math.Abs(got-want) > 1e-9 {
		t.Errorf("MIN==0 with RealLife3GA set engaged the real path: got %.6f, want %.6f", got, want)
	}
}
