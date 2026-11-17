package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// worthyBucket3Inputs is one FUN_004e1ba0 input set: James Worthy's D78
// (TestD78Composite_RealRecords) as the ball handler, team 18's fixture DF0
// (bundle_reallife.json) as the defense, and a negative energy so the
// uncapped e88 initial factor shows.
func worthyBucket3Inputs() bucket3Inputs {
	return bucket3Inputs{
		energy:     -45,
		bhD78:      1.9952761510097965,
		lineupD78:  8.0,
		lineupSum3: 20.0,
		defDF0:     19.21835564053537,
	}
}

// TestBucket3E88_HandComputed: e88 equals the value a python3 copy of the
// FUN_004e1ba0 chain computed from the same inputs.
func TestBucket3E88_HandComputed(t *testing.T) {
	const want = 0.3658004332118299
	got, ok := bucket3E88(worthyBucket3Inputs())
	if !ok {
		t.Fatal("bucket3E88 ok = false, want true")
	}
	if math.Abs(got-want) > 1e-12 {
		t.Errorf("bucket3E88 = %.17g, want %.17g", got, want)
	}
}

// TestBucket3E88Initial_CapBoundary: energy just above 0 caps at exactly 1.0;
// just below 0 returns the uncapped (energy/30 + 100) * 0.01.
func TestBucket3E88Initial_CapBoundary(t *testing.T) {
	if got := bucket3E88Initial(1e-9); got != 1.0 {
		t.Errorf("bucket3E88Initial(1e-9) = %.17g, want 1.0", got)
	}
	const below = 0.9999999999996666
	if got := bucket3E88Initial(-1e-9); math.Abs(got-below) > 1e-15 {
		t.Errorf("bucket3E88Initial(-1e-9) = %.17g, want %.17g", got, below)
	}
	if got := bucket3E88Initial(-45); math.Abs(got-0.985) > 1e-15 {
		t.Errorf("bucket3E88Initial(-45) = %.17g, want 0.985", got)
	}
}

// TestBucket3Clamp_OneSided: below the anchor drops to anchor − 1.0, the
// anchor and anchor + 1.0 pass through, and above anchor + 1.0 is capped.
func TestBucket3Clamp_OneSided(t *testing.T) {
	const anchor = 2.5
	cases := []struct {
		name string
		v    float64
		want float64
	}{
		{"just below anchor", anchor - 1e-9, anchor - 1.0},
		{"at anchor", anchor, anchor},
		{"at anchor+1", anchor + 1.0, anchor + 1.0},
		{"above anchor+1", anchor + 1.0 + 1e-9, anchor + 1.0},
	}
	for _, tc := range cases {
		if got := bucket3Clamp(tc.v, anchor); got != tc.want {
			t.Errorf("%s: bucket3Clamp(%.17g) = %.17g, want %.17g", tc.name, tc.v, got, tc.want)
		}
	}
}

// TestBucket3Weight_FloorRedraw: e90 == 0 redraws U[0, 0.03) with exactly
// one RNG draw; e90 > 0 returns e90 without drawing.
func TestBucket3Weight_FloorRedraw(t *testing.T) {
	r, twin := rng.New(1988), rng.New(1988)
	got := bucket3Weight(0.1, -0.4, 0, false, r)
	want := twin.Float64() * andOneBucketFloor
	if got != want || got < 0 || got >= andOneBucketFloor {
		t.Errorf("e90 == 0 redraw = %.17g, want %.17g in [0, 0.03)", got, want)
	}
	if a, b := r.Float64(), twin.Float64(); a != b {
		t.Error("e90 == 0 redraw consumed other than exactly one draw")
	}

	r, twin = rng.New(7), rng.New(7)
	if got := bucket3Weight(0.3, -0.4, 0, false, r); got != 0.19999999999999998 { // runtime -0.4*0.25 + 0.3
		t.Errorf("e90 > 0 = %.17g, want 0.19999999999999998", got)
	}
	if a, b := r.Float64(), twin.Float64(); a != b {
		t.Error("e90 > 0 advanced the RNG")
	}
}

// TestBucket3Weight_TransitionSkipsHCA: a transition trip ignores hcaRaw.
func TestBucket3Weight_TransitionSkipsHCA(t *testing.T) {
	const (
		e88  = 0.3658004332118299
		want = 0.2658004332118299 // -0.4*0.25 + e88
	)
	if got := bucket3Weight(e88, -0.4, 0.2, false, rng.New(1)); got != want {
		t.Errorf("transition bucket3Weight = %.17g, want %.17g", got, want)
	}
}

// TestBucket3E88_ZeroLineupFallsBack: a zero or NaN lineup D78 sum returns
// ok == false, so the caller keeps the rating path.
func TestBucket3E88_ZeroLineupFallsBack(t *testing.T) {
	for _, d := range []float64{0, math.NaN()} {
		in := worthyBucket3Inputs()
		in.lineupD78 = d
		if _, ok := bucket3E88(in); ok {
			t.Errorf("lineupD78 = %v: ok = true, want false", d)
		}
	}
}
