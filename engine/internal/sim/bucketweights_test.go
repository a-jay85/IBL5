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
	mq := matchupQuality(bh.FGP, bh.energy, defenders)
	return outcomeInputs{
		twoPtWeight:      twoPtBucketWeight(bh) + hca,
		threePtWeight:    threePtBucketWeight(bh),
		andOneWeight:     andOneBucketWeight(mq, bh),
		foulOnlyWeight:   foulWeight,
		turnoverDefValue: 0,
	}
}

// --- matrix #2: characterization — assembled foul-path mix, home vs away -----
//
// Records the post-change path-selection distribution on the symmetric pair, so
// the magnitude shift from #952 is reviewable, and confirms the two structural
// invariants the design rests on: (a) the foul path is a realistic minority share
// (2pt-dominant, non-degenerate), and (b) the home assembly selects the foul path
// MORE than the away assembly — the faithful HCA mechanism at the bucket level.
func TestBucketWeights_FoulPathMix(t *testing.T) {
	const n = 200_000
	const seed = uint64(1988)

	def := fiveStarters(7)
	homeFoulW := foulBucketWeight(def, hcaMagnitude, rng.New(seed)) // deterministic home ~0.87·scale
	awayFoulW := foulFloor / 2 * foulBucketScale                    // U[0, 0.6·scale) analytic mean = 0.3·scale
	homeIn := assembleInputs(homeFoulW, hcaMagnitude)
	awayIn := assembleInputs(awayFoulW, -hcaMagnitude)

	homeCounts := pathCounts(homeIn, n, seed)
	awayCounts := pathCounts(awayIn, n, seed)
	homeFoul := float64(homeCounts[outcomeFoulOnly]) / n
	awayFoul := float64(awayCounts[outcomeFoulOnly]) / n

	t.Logf("assembled path mix (n=%d, seed %d):", n, seed)
	t.Logf("  home weights: 2pt=%.3f 3pt=%.3f and-one=%.3f foul=%.3f",
		homeIn.twoPtWeight, homeIn.threePtWeight, homeIn.andOneWeight, homeIn.foulOnlyWeight)
	t.Logf("  home foul-frac=%.4f  away foul-frac=%.4f  (home−away=%+.4f)", homeFoul, awayFoul, homeFoul-awayFoul)

	// Realistic minority: the foul path must be a small share (2pt-dominant), the
	// non-degeneracy property the faithful pair preserves. The home deterministic
	// weight (~0.87·scale) and the away mean (0.3·scale) keep the home foul share
	// inside the Part-6 acceptance band (ADR-0082); the upper bound is the foul-out
	// degeneracy ceiling (TestSimulate_FoulOutRate) and is NEVER raised. NOT widened.
	if homeFoul < 0.02 || homeFoul > 0.25 {
		t.Errorf("home foul share = %.4f, want a realistic minority in [0.02, 0.25]", homeFoul)
	}
	// HCA: home selects the (higher-EV) foul path more than away.
	if homeFoul <= awayFoul {
		t.Errorf("home foul-frac %.4f ≤ away %.4f — HCA not home-favorable at the bucket level", homeFoul, awayFoul)
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

	// 2pt must dominate the foul bucket (foul is a realistic minority). Use the home
	// deterministic weight (~0.87), the largest foul weight, for the strongest test.
	def := fiveStarters(7)
	foul := foulBucketWeight(def, hcaMagnitude, rng.New(1))
	if twoPtBucketWeight(p) <= foul {
		t.Errorf("2pt bucket %.3f not dominant over foul %.3f", twoPtBucketWeight(p), foul)
	}
	t.Logf("2pt composite=%.4f foul(home)=%.4f 3pt=%.4f", twoPtBucketWeight(p), foul, threePtBucketWeight(p))
}

// --- matrix #5: faithful asymmetric foul bucket = home deterministic, away U[0,0.6) --
//
// Verifies the HOME weight equals the hand-computed defense-coupled formula; the
// AWAY/NEUTRAL weight is a finite stochastic draw in [0, foulFloor); the NEUTRAL
// (hca==0) path matches the away distribution (the ASG-symmetry precondition); the
// home MEAN exceeds the away mean (bucket-level HCA direction); and the faithful
// redraw guard is unreachable — even an empty defense yields a positive home weight.
func TestBucketWeights_FoulDivisor(t *testing.T) {
	def := fiveStarters(7)

	// Home (hca>0): deterministic ((defQ − 5·(5/6)·teamDef)/5 + hca)·scale.
	defQ := defMatchupQuality(def)
	wantHome := ((defQ-defQualityCapTeamMult*foulDivisorTeamDefCoef*teamDefBaseline)/defQualityCapTeamMult + hcaMagnitude) * foulBucketScale
	if got := foulBucketWeight(def, hcaMagnitude, rng.New(1)); math.Abs(got-wantHome) > 1e-9 {
		t.Errorf("home foulBucketWeight = %.6f, want deterministic %.6f", got, wantHome)
	}

	// Away/neutral: stochastic U[0, foulFloor·scale). Every draw finite and in range.
	awayCeil := foulFloor * foulBucketScale
	r := rng.New(1988)
	for i := 0; i < 10_000; i++ {
		got := foulBucketWeight(def, -hcaMagnitude, r)
		if math.IsNaN(got) || math.IsInf(got, 0) || got < 0 || got >= awayCeil {
			t.Fatalf("away draw #%d out of [0, %.2f): %v", i, awayCeil, got)
		}
	}
	// Neutral (ASG, hca==0) takes the SAME stochastic path — the ASG-symmetry precondition.
	if got := foulBucketWeight(def, 0, rng.New(7)); got < 0 || got >= awayCeil {
		t.Errorf("neutral (hca==0) not on the stochastic [0, %.2f) path: %v", awayCeil, got)
	}

	// Direction: home mean > away mean over a large sample.
	rh, ra := rng.New(3), rng.New(4)
	const m = 50_000
	var homeSum, awaySum float64
	for i := 0; i < m; i++ {
		homeSum += foulBucketWeight(def, hcaMagnitude, rh)
		awaySum += foulBucketWeight(def, -hcaMagnitude, ra)
	}
	if homeSum/m <= awaySum/m {
		t.Errorf("home mean %.4f ≤ away mean %.4f — HCA not home-favorable at the bucket level", homeSum/m, awaySum/m)
	}

	// Redraw guard unreachable: an empty defense sits at defMatchupQuality's 4.5155
	// floor, so the home weight is still positive and finite (`w <= 0` never fires).
	minW := foulBucketWeight([]onCourt{}, hcaMagnitude, rng.New(5))
	if math.IsNaN(minW) || math.IsInf(minW, 0) || minW <= 0 {
		t.Errorf("home weight at the defMatchupQuality floor = %v, want positive finite (redraw must stay unreachable)", minW)
	}
	t.Logf("home(det)=%.4f away-mean=%.4f floor-home=%.4f (want home>away, away∈[0,%.2f))", wantHome, awaySum/m, minW, awayCeil)
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
// faithful per-48-MINUTE rates (stat/MIN)×48 (D70 scaled by d70LeagueScalar). The
// magnitude lands in the O(10s) stand-in regime (d88 ≈ 25.6), not the O(100s) the
// per-48-games divisor would give.
func TestBucketWeights_RealLifeComposite(t *testing.T) {
	pl := mkPlayer(1, 3, slotPG, 48)
	pl.RealLifeMIN = 2400 // ~34 min/game over 70 games
	pl.RealLifeFGA = 1280 // d88 = 1280/2400*48 = 25.6
	pl.RealLifeORB = 160  // db8 = 160/2400*48  = 3.2
	pl.RealLifeFTA = 320  // d70 = 320/2400*48  = 6.4 (×1.0)
	p := oc(slotPG, pl)

	d88 := per48Min(1280, 2400)
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

// Row 10: two players with IDENTICAL FGA ratings but DIFFERENT real-life FGA rates
// produce different 2pt weights — the volume signal the compressed rating stand-in
// flattened away (the dispersion mechanism, ADR-0040). Both also differ from the
// rating-only stand-in.
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
// The faithful limiting value is d88 == 0.
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
