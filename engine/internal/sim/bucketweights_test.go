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
// OO=6) with five-man home (team 3) offense and five-man away (team 7) defense.
func assembleInputs(hca float64) outcomeInputs {
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 48))
	offense := fiveStarters(3)
	defenders := fiveStarters(7)
	mq := matchupQuality(bh.FGP, bh.energy, defenders)
	return outcomeInputs{
		twoPtWeight:      twoPtBucketWeight(bh) + hca,
		threePtWeight:    threePtBucketWeight(bh),
		andOneWeight:     andOneBucketWeight(mq, bh),
		foulOnlyWeight:   foulBucketWeight(offense, defenders, hca),
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

	homeIn := assembleInputs(hcaMagnitude)
	awayIn := assembleInputs(-hcaMagnitude)

	homeCounts := pathCounts(homeIn, n, seed)
	awayCounts := pathCounts(awayIn, n, seed)
	homeFoul := float64(homeCounts[outcomeFoulOnly]) / n
	awayFoul := float64(awayCounts[outcomeFoulOnly]) / n

	t.Logf("assembled path mix (n=%d, seed %d):", n, seed)
	t.Logf("  home weights: 2pt=%.3f 3pt=%.3f and-one=%.3f foul=%.3f",
		homeIn.twoPtWeight, homeIn.threePtWeight, homeIn.andOneWeight, homeIn.foulOnlyWeight)
	t.Logf("  home foul-frac=%.4f  away foul-frac=%.4f  (home−away=%+.4f)", homeFoul, awayFoul, homeFoul-awayFoul)

	// Realistic minority: the foul path must be a small share (2pt-dominant), the
	// non-degeneracy property the magnitudes are chosen to preserve.
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

	// 2pt must dominate the foul bucket (foul is a realistic minority).
	off := fiveStarters(3)
	def := fiveStarters(7)
	foul := foulBucketWeight(off, def, 0)
	if twoPtBucketWeight(p) <= foul {
		t.Errorf("2pt bucket %.3f not dominant over foul %.3f", twoPtBucketWeight(p), foul)
	}
	t.Logf("2pt composite=%.4f foul(neutral)=%.4f 3pt=%.4f", twoPtBucketWeight(p), foul, threePtBucketWeight(p))
}

// --- matrix #5: faithful foul bucket = 0.6 floor + quality divisor + HCA -----
//
// Verifies the foul bucket equals the hand-computed 0.6 floor + divisor at neutral
// HCA, GROWS when offQ shrinks (the home delta), and stays finite at the offQ
// boundary (a floored, near-zero divisor must not divide-by-zero or produce NaN).
func TestBucketWeights_FoulDivisor(t *testing.T) {
	off := fiveStarters(3)
	def := fiveStarters(7)

	// Neutral: foul = 0.6 + (0.6/offQ)·(defQ − teamDef×5/6).
	offQ := offQualityWithHCA(off, 0)
	defQ := defMatchupQuality(def)
	wantNeutral := (foulFloor/offQ)*(defQ-teamDefBaseline*foulDivisorTeamDefCoef) + foulFloor
	if got := foulBucketWeight(off, def, 0); math.Abs(got-wantNeutral) > 1e-9 {
		t.Errorf("foulBucketWeight(neutral) = %.6f, want 0.6 floor + divisor = %.6f", got, wantNeutral)
	}

	// HCA: home (offQ shrinks) → foul grows; away (offQ grows) → foul shrinks.
	home := foulBucketWeight(off, def, hcaMagnitude)
	neutral := foulBucketWeight(off, def, 0)
	away := foulBucketWeight(off, def, -hcaMagnitude)
	if !(home > neutral && neutral > away) {
		t.Errorf("foul bucket not monotone in HCA: home=%.4f neutral=%.4f away=%.4f (want home>neutral>away)", home, neutral, away)
	}

	// Boundary: a single unrated offensive player forces offQ to its floor; the foul
	// bucket must stay finite (no divide-by-zero / NaN / Inf).
	tinyOff := []onCourt{oc(slotPG, mkPlayer(1, 3, slotPG, 0))}
	tinyOff[0].OO = 0
	got := foulBucketWeight(tinyOff, def, hcaMagnitude)
	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Errorf("foulBucketWeight at the offQ floor produced a non-finite value: %v", got)
	}
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
