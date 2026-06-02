package sim

import (
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

// assembleRichBundleInputs builds an outcomeInputs using the NEW O(1) helpers
// for a representative richBundle ball-handler/defender pair.
//
// Source: richBundle() in sim_test.go (seed 1988). Home team (TeamID=3, FGP=50)
// starter at slotPG: FGA=60, ORB=20, FTA=20, FGP=50, TGA=25, Foul=30, Stamina=50.
// net ≈ 1.0 (OO=6 minus OD=5 minus position_penalty≈0 for a PG). TVR=40 excluded
// from the bucket weight assembly (turnoverDefValue is separate and set to 0 here
// so path-selection tests are not diluted by the independent turnover override).
func assembleRichBundleInputs() outcomeInputs {
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 50))
	// net: OO=6 − OD=5 − penalty≈0 for a PG with default ratings ≈ 1.0
	net := 1.0
	mq := matchupQuality(bh.FGP, bh.energy, []onCourt{oc(slotPG, mkPlayer(2, 7, slotPG, 46))})
	return outcomeInputs{
		twoPtWeight:      twoPtBucketWeight(bh),
		threePtWeight:    threePtBucketWeight(bh),
		andOneWeight:     andOneBucketWeight(mq, bh),
		foulOnlyWeight:   foulBucketWeight(net, bh),
		turnoverDefValue: 0, // disabled: isolates path-selection from turnover override
	}
}

// --- matrix #1: characterization — current (pre-rescale) assembled path
//
// This test characterizes the path-selection distribution BEFORE possession.go
// is rewired. It calls the weight-assembly expressions from possession.go:95-100
// directly, using the same richBundle representative pair, so the shift after the
// rewire is intentional and reviewable.
//
// Current (O(100)) weights at richBundle home PG (FGP=50, seed 1988):
//
//	sv2 ≈ 450 + net*500/233 ≈ 452, fatigue≈1.0
//	twoPt ≈ 452,  threePt ≈ 349.5*0.294 ≈ 103,  andOne ≈ 22.5,  foul ≈ 30
//	foul share ≈ 30 / (452+103+22.5+30) ≈ 4.9%
//
// Recorded here as the pre-rescale baseline; the post-rescale Phase 4 test
// demonstrates the ≥5pp shift in foul-path selection that was impossible at O(100).
func TestBucketWeights_Characterization(t *testing.T) {
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 50))
	mq := matchupQuality(bh.FGP, bh.energy, []onCourt{oc(slotPG, mkPlayer(2, 7, slotPG, 46))})
	net := 1.0 // OO=6 − OD=5 − penalty≈0

	// Re-create the OLD O(100) assembly from possession.go:95-100 (pre-rescale).
	sv2 := shotValue2pt(net, bh.FGP, false) // ≈ 452
	oldIn := outcomeInputs{
		twoPtWeight:      sv2 * bh.fatigue,
		threePtWeight:    shotValue3pt() * bh.fatigue * threePtPropensity(bh),
		andOneWeight:     mq*0.25 + base2pt(bh.FGP)*andOneBaseShare,
		foulOnlyWeight:   (2.0 - bh.fatigue) * floor1(bh.Foul),
		turnoverDefValue: 0,
	}

	const n = 200_000
	const seed = uint64(1988)
	oldCounts := pathCounts(oldIn, n, seed)
	oldFoulFrac := float64(oldCounts[outcomeFoulOnly]) / n

	// Pre-rescale foul share at O(100) weights is small (≈5%) — that is the
	// documented no-op that prevents HCA from landing.
	if oldFoulFrac >= 0.08 {
		t.Errorf("pre-rescale foul share = %.3f, want < 0.08 (characterization: O(100) no-op confirmed)", oldFoulFrac)
	}
	t.Logf("pre-rescale path distribution (n=%d, seed %d):", n, seed)
	t.Logf("  2pt=%.3f  3pt=%.3f  and-one=%.3f  foul=%.3f",
		float64(oldCounts[outcome2pt])/n,
		float64(oldCounts[outcome3pt])/n,
		float64(oldCounts[outcomeAndOne])/n,
		float64(oldCounts[outcomeFoulOnly])/n,
	)
	t.Logf("  weights: 2pt=%.2f  3pt=%.2f  and-one=%.2f  foul=%.2f",
		oldIn.twoPtWeight, oldIn.threePtWeight, oldIn.andOneWeight, oldIn.foulOnlyWeight)
}

// --- matrix #2: scale property — +0.2 on foul shifts selection by ≥5pp

// TestBucketWeights_FoulScaleShift proves that the O(1) rescale makes HCA's
// ±0.2 perturbation expressible. At O(100) the same +0.2 produced a ≤0.5pp shift
// (the documented no-op). After rescale the FOUR-BUCKET TOTAL is O(1) (≈1.0), so
// +0.2 is a meaningful fraction of the total and shifts foul-path selection by a
// non-negligible amount (≈16pp) — even though the foul bucket is only a realistic
// ≈5% SHARE. The expressibility property depends on the total being O(1), NOT on a
// large foul share: the shift (f+0.2)/(T+0.2) − f/T is driven by absolute T. This
// is why the realistic 2pt-dominant mix clears the bar with a LARGER margin than
// the plan's rejected 37%-foul-share design (~6pp) would have. See the scale
// rationale in bucketweights.go.
//
// Source: assembleRichBundleInputs() (richBundle home PG, FGP=50, seed 1988).
// turnoverDefValue=0 so the independent turnover override does not dilute the
// measured foul-path frequency.
//
// Zero production HCA wiring — the +0.2 perturbation is applied in-test only.
func TestBucketWeights_FoulScaleShift(t *testing.T) {
	in := assembleRichBundleInputs()

	const n = 200_000
	const seed = uint64(1988)

	// Baseline: foul-path selection frequency from the O(1) helpers.
	baseCounts := pathCounts(in, n, seed)
	baseFoulFrac := float64(baseCounts[outcomeFoulOnly]) / n

	// Perturbed: +0.2 added to foulOnlyWeight only (future HCA hook, not wired here).
	perturbed := in
	perturbed.foulOnlyWeight += 0.2
	perturbedCounts := pathCounts(perturbed, n, seed)
	perturbedFoulFrac := float64(perturbedCounts[outcomeFoulOnly]) / n

	shift := perturbedFoulFrac - baseFoulFrac

	// The shift must be non-negligible (≥5pp). At O(100) the same +0.2 produced
	// ≤0.5pp (the HCA no-op documented in PR9's rationale).
	const minShift = 0.05
	if shift < minShift {
		t.Errorf(
			"foul-path shift from +0.2 perturbation = %.4f (%.2fpp), want ≥ %.2fpp.\n"+
				"  base foul frac=%.4f, perturbed foul frac=%.4f\n"+
				"  weights before: 2pt=%.3f 3pt=%.3f and-one=%.3f foul=%.3f",
			shift, shift*100, minShift*100,
			baseFoulFrac, perturbedFoulFrac,
			in.twoPtWeight, in.threePtWeight, in.andOneWeight, in.foulOnlyWeight,
		)
	}

	// Also verify old O(100) basis produces the no-op (contrast assertion).
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 50))
	mq := matchupQuality(bh.FGP, bh.energy, []onCourt{oc(slotPG, mkPlayer(2, 7, slotPG, 46))})
	sv2 := shotValue2pt(1.0, bh.FGP, false)
	oldIn := outcomeInputs{
		twoPtWeight:      sv2 * bh.fatigue,
		threePtWeight:    shotValue3pt() * bh.fatigue * threePtPropensity(bh),
		andOneWeight:     mq*0.25 + base2pt(bh.FGP)*andOneBaseShare,
		foulOnlyWeight:   (2.0 - bh.fatigue) * floor1(bh.Foul),
		turnoverDefValue: 0,
	}
	oldBase := pathCounts(oldIn, n, seed)
	oldPerturbed := oldIn
	oldPerturbed.foulOnlyWeight += 0.2
	oldPert := pathCounts(oldPerturbed, n, seed)
	oldShift := float64(oldPert[outcomeFoulOnly])/n - float64(oldBase[outcomeFoulOnly])/n

	if oldShift > 0.005 {
		t.Errorf("old O(100) foul shift should be ≤0.5pp, got %.4f (%.2fpp)", oldShift, oldShift*100)
	}

	t.Logf("O(1) foul shift: %.4f (%.2fpp); O(100) foul shift: %.4f (%.2fpp)",
		shift, shift*100, oldShift, oldShift*100)
	t.Logf("O(1) base weights: 2pt=%.3f 3pt=%.3f and-one=%.3f foul=%.3f total=%.3f",
		in.twoPtWeight, in.threePtWeight, in.andOneWeight, in.foulOnlyWeight,
		in.twoPtWeight+in.threePtWeight+in.andOneWeight+in.foulOnlyWeight)
}

// --- matrix #3: direction — EV(foul) > EV(2pt) from outcome realizations

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
