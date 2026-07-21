package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// TestLateGameForcing verifies lateGameForcing truth table across Mechanism 1,
// 2A, 2B, and negative/boundary cases (J17 plan verification matrix rows 2–5).
func TestLateGameForcing(t *testing.T) {
	cases := []struct {
		name       string
		period     int
		clock      int
		scoreDiff  int
		driveOff   int
		wantForced bool
		wantClock  bool
	}{
		// driveOff is a small-integer JSB rating (~[0,13]); Mechanism 2B compares it
		// against rng.IntN(doForcedMakeMax=10)+1 ∈ [1,10]. Rows use in-scale values.
		// (DriveOff is irrelevant for Mechanism 1/2A — those set shotClock only.)

		// Mechanism 1: clock < 4, fires regardless of period or margin.
		{"mech1 clock<4 Q4 scoreDiff=0", 4, 3, 0, 8, false, true},
		{"mech1 clock<4 Q3 period irrelevant", 3, 2, 0, 8, false, true},
		{"mech1 beats 2A: clock<4 scoreDiff=-3", 4, 3, -3, 8, false, true},

		// Mechanism 2A: Q4+, clock < 25, scoreDiff == -3 → shotClock.
		{"2A: Q4 clock=24 scoreDiff=-3", 4, 24, -3, 8, false, true},
		{"2A: OT period=5 clock=20 scoreDiff=-3", 5, 20, -3, 8, false, true},

		// Mechanism 2B: Q4+, clock < 25, scoreDiff ∈ [1,3]. DriveOff=0 always fires
		// (0 < rng.IntN(10)+1 ∈ [1,10] is always true).
		{"2B: leading +1 DriveOff=0 always forced", 4, 20, 1, 0, true, false},
		{"2B: leading +3 DriveOff=0 always forced", 4, 20, 3, 0, true, false},
		// DriveOff=10 (== bound): rng.IntN(10)+1 ∈ [1,10], so 10 < x is never true —
		// forcedMake never fires. This anchors doForcedMakeMax=10 (a higher bound
		// could exceed 10 and fire). See possession.go const provenance / RE artifact.
		{"2B: leading +3 DriveOff=10 (==bound) never fires", 4, 20, 3, 10, false, false},
		{"2B: leading +2 DriveOff=13 (>bound) never fires", 4, 20, 2, 13, false, false},

		// Boundary: clock=25 is NOT < 25, so mechanisms 2A/2B do not fire.
		{"boundary clock=25 no fire", 4, 25, -3, 8, false, false},
		// Boundary: clock=24 is < 25, so 2A fires.
		{"boundary clock=24 fires", 4, 24, -3, 8, false, true},

		// Negative cases: out-of-range scoreDiff, wrong period, tied.
		{"neg scoreDiff=-4 out of range", 4, 20, -4, 8, false, false},
		{"neg scoreDiff=4 out of range", 4, 20, 4, 0, false, false},
		{"neg Q3 trailing -3", 3, 20, -3, 8, false, false},
		{"neg tied scoreDiff=0", 4, 20, 0, 8, false, false},
		{"leading +4 outside 1-3", 4, 20, 4, 0, false, false},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			gs := &gameState{rng: rng.New(0), period: tc.period, clock: tc.clock}
			bh := onCourt{Player: bundle.Player{DriveOff: tc.driveOff}}
			gotForced, gotClock := gs.lateGameForcing(tc.scoreDiff, bh)
			if gotForced != tc.wantForced || gotClock != tc.wantClock {
				t.Errorf("lateGameForcing(scoreDiff=%d, driveOff=%d) period=%d clock=%d = (forcedMake=%v, shotClock=%v), want (%v, %v)",
					tc.scoreDiff, tc.driveOff, tc.period, tc.clock,
					gotForced, gotClock, tc.wantForced, tc.wantClock)
			}
		})
	}
}
