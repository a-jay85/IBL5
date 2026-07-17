package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// The roster-dependent teamBaseTime tests retired with the additive stand-in
// (J24 Phase 0/1: 5.60's base_time is constant — the composite ratio is dead
// code, u = 0; see the tempo.go const block). What remains to lock here is the
// possession-step mapping itself; the constant-center resolution is covered by
// options_test.go (resolveBaseTimeMid).
//
// J24 Phase 2 retired the DETERMINISTIC round-half-up(pt) mapping in favor of a
// per-possession jittered draw (FUN_004e42e0 half-court step class — see
// possessionTime's docblock in tempo.go). The jitter is the first stochastic
// clock mechanic this engine ports; TestPossessionTime_FallbackBounds below
// only locks the surviving deterministic parts (the pt derivation + the [1,24]
// out-of-range fallback), and the new distribution tests lock the stochastic
// jitter/redraw behavior statistically.

// TestPossessionTime_FallbackBounds locks the (2.0−factor) pt form and the
// 24.0 out-of-range fallback that feed the jittered draw — deterministic
// upstream of the RNG. It does not (and cannot) pin an exact step: it fixes
// the RNG draw to 0.0 (r.Float64() returns a deterministic value, but this
// test only needs the pt derivation, not a specific jittered outcome) by
// checking pt indirectly through the returned step's plausible bounds.
func TestPossessionTime_FallbackBounds(t *testing.T) {
	r := rng.New(1)
	// In-range base_time: pt = 14.0, so step = round-half-up(7.0 + U[0,14)),
	// landing in [7,21] pre-redraw (redraw only fires on the pt==14 exact hit,
	// widening the floor to 3).
	if got := possessionTime(14.0, r); got < 3 || got > 21 {
		t.Fatalf("in-range possessionTime(14.0, r) = %d, want in [3,21]", got)
	}
	// Over-range base_time resets pt to the JSB fallback of 24: step =
	// round-half-up(12.0 + U[0,24)), landing in [12,36] pre-redraw (redraw
	// widens the floor to 3 on the pt==24 exact hit).
	if got := possessionTime(25.0, r); got < 3 || got > 36 {
		t.Fatalf("over-range possessionTime(25.0, r) = %d, want in [3,36]", got)
	}
	// Under-range base_time likewise resets pt to 24.
	if got := possessionTime(0.5, r); got < 3 || got > 36 {
		t.Fatalf("under-range possessionTime(0.5, r) = %d, want in [3,36]", got)
	}
}

// TestPossessionTime_JitterMeanPreservation locks the jittered draw's central
// tendency: round-half-up(pt/2 + U[0,pt)) has expectation pt (U[0,pt)'s mean is
// pt/2, so pt/2 + pt/2 = pt), and the rare {3..23} redraw on the trunc(pt) hit
// does not materially move that mean at 100000+ draws. baseTime=13.65 is a
// representative in-range base_time (the pre-J24-Phase-5 center) — not the
// shipped baseTimeMid (17.7, tempo.go); the shipped center's mean is separately
// pinned by TestPossessionStepDistributionPin_Current (possession_pace_pin_test.go).
func TestPossessionTime_JitterMeanPreservation(t *testing.T) {
	r := rng.New(1)
	const n = 100000
	const baseTime = 13.65
	var sum float64
	for i := 0; i < n; i++ {
		sum += float64(possessionTime(baseTime, r))
	}
	mean := sum / n
	if math.Abs(mean-baseTime) > 0.5 {
		t.Errorf("jittered step mean = %.4f, want %.2f ± 0.5", mean, baseTime)
	}
}

// TestPossessionTime_JitterSupportAndRedraw locks the support of the jittered
// draw at baseTime=13.65 (pt=13.65, so the pre-redraw step is
// round-half-up(6.825 + U[0,13.65)) ∈ {7..20}, and the trunc(pt)=13 hit
// redraws into {3..23}) and confirms the redraw branch actually fires: both a
// value below the pre-redraw floor of 7 (only reachable via redraw) and a
// value in the ordinary {7..20} range must appear in the sample.
func TestPossessionTime_JitterSupportAndRedraw(t *testing.T) {
	r := rng.New(2)
	const n = 100000
	const baseTime = 13.65
	sawBelowFloor := false // only reachable via the {3..23} redraw
	sawOrdinary := false
	for i := 0; i < n; i++ {
		step := possessionTime(baseTime, r)
		if step < 3 || step > 23 {
			t.Fatalf("possessionTime(%.2f, r) = %d, want in [3,23]", baseTime, step)
		}
		if step < 7 {
			sawBelowFloor = true
		}
		if step >= 7 && step <= 20 {
			sawOrdinary = true
		}
	}
	if !sawBelowFloor {
		t.Errorf("never observed a step < 7 across %d draws — redraw branch never fired", n)
	}
	if !sawOrdinary {
		t.Errorf("never observed a step in [7,20] across %d draws", n)
	}
}

// TestPossessionTime_JitterRedrawEvidence locks that the trunc(pt)=13 bucket
// loses mass to the redraw: without a redraw, round-half-up(pt/2 + U[0,pt))
// lands on exactly trunc(pt) for a rounding-bucket share roughly comparable to
// its neighbor 14's share (both interior buckets of a near-uniform jitter).
// Because every trunc(pt) hit gets redrawn away, share(13) must sit well below
// share(14).
func TestPossessionTime_JitterRedrawEvidence(t *testing.T) {
	r := rng.New(3)
	const n = 200000
	const baseTime = 13.65
	counts := map[int]int{}
	for i := 0; i < n; i++ {
		counts[possessionTime(baseTime, r)]++
	}
	share13 := float64(counts[13]) / n
	share14 := float64(counts[14]) / n
	if share13 >= share14-0.02 {
		t.Errorf("redraw evidence missing: share(13) = %.4f, share(14) = %.4f — want share(13) < share(14) - 0.02", share13, share14)
	}
}

// TestPossessionTime_ProgressBoundary locks that possessionTime always
// terminates the gameloop's `for gs.clock > 0` loop: step is always >= 1
// across a spread of base_time values including both in-range and
// out-of-range (fallback-triggering) inputs.
func TestPossessionTime_ProgressBoundary(t *testing.T) {
	r := rng.New(4)
	const n = 10000
	for _, baseTime := range []float64{0.5, 13.0, 13.65, 16.0, 25.0} {
		for i := 0; i < n; i++ {
			step := possessionTime(baseTime, r)
			if step < 1 {
				t.Fatalf("possessionTime(%.2f, r) = %d, want >= 1 (loop progress)", baseTime, step)
			}
			// Out-of-range base_time clamps pt to 24: round-half-up(12 +
			// U[0,24)) ∈ {12..36}, and the trunc(pt)=24 redraw widens the
			// floor to {3..23} — so the full support at the fallback is
			// [3,36].
			if (baseTime == 0.5 || baseTime == 25.0) && step > 36 {
				t.Fatalf("possessionTime(%.2f, r) = %d, want <= 36 at the 24.0 fallback", baseTime, step)
			}
		}
	}
}
