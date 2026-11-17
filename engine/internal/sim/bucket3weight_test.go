package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
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

// team18FiveIDs are team 18's five highest-minute players in the real-life
// fixture, ball handler (James Worthy) first.
var team18FiveIDs = []int{6322, 5930, 6348, 4512, 5965}

// realLifeFive returns the fixture's players with the given PIDs, in order, as
// on-court players at zero energy and zero fatigue.
func realLifeFive(t *testing.T, b bundle.Bundle, pids []int) []onCourt {
	t.Helper()
	out := make([]onCourt, 0, len(pids))
	for _, pid := range pids {
		found := false
		for _, p := range b.Players {
			if p.PID == pid {
				out = append(out, onCourt{Player: p})
				found = true
				break
			}
		}
		if !found {
			t.Fatalf("fixture has no player %d", pid)
		}
	}
	return out
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

// team18VsTeam2 returns team 18's real-life five (offense), team 2's five
// (defenders) and team 2's bundle entry from the real-life fixture.
func team18VsTeam2(t *testing.T) (off, defenders []onCourt, def *bundle.Team) {
	t.Helper()
	b := loadRealLifeFixture(t)
	off = realLifeFive(t, b, team18FiveIDs)
	for _, p := range b.Players {
		if p.TeamID == 2 && p.RealLifeMIN > 0 && len(defenders) < 5 {
			defenders = append(defenders, onCourt{Player: p})
		}
	}
	def = teamOf(b, 2)
	if def == nil || def.DF0 == nil || len(defenders) != 5 {
		t.Fatal("fixture team 2 is missing, has no DF0, or has fewer than five players")
	}
	return off, defenders, def
}

// TestBucket3InputsFor_OffenseFiveFromComposites: the lineup sums come from the
// offense five's own composites (tov + 3pt + 2pt for lineupSum3), never from
// the caller's 3pt weight, and an absent DF0 returns false.
func TestBucket3InputsFor_OffenseFiveFromComposites(t *testing.T) {
	off, _, def := team18VsTeam2(t)
	in, ok := bucket3InputsFor(off[0], off, def)
	if !ok {
		t.Fatal("bucket3InputsFor ok = false, want true")
	}
	const (
		wantSum3 = 134.77303082121773
		wantD78  = 18.778034007237718
	)
	if math.Abs(in.lineupSum3-wantSum3) > 1e-9 {
		t.Errorf("lineupSum3 = %.17g, want %.17g", in.lineupSum3, wantSum3)
	}
	if math.Abs(in.lineupD78-wantD78) > 1e-9 {
		t.Errorf("lineupD78 = %.17g, want %.17g", in.lineupD78, wantD78)
	}
	if in.bhD78 != d78Composite(off[0]) || in.defDF0 != *def.DF0 {
		t.Errorf("bhD78/defDF0 = %.17g/%.17g, want %.17g/%.17g", in.bhD78, in.defDF0, d78Composite(off[0]), *def.DF0)
	}

	noDF0 := *def
	noDF0.DF0 = nil
	if _, ok := bucket3InputsFor(off[0], off, &noDF0); ok {
		t.Error("DF0 == nil: ok = true, want false")
	}
	if _, ok := bucket3InputsFor(off[0], off, nil); ok {
		t.Error("def == nil: ok = true, want false")
	}
}

// TestAndOneAndFoulWeights_DrawOrder: with both weights forced to redraw,
// index 3 (and-one, ×0.03) takes the first draw and index 4 (foul, ×0.6)
// the second.
func TestAndOneAndFoulWeights_DrawOrder(t *testing.T) {
	off, defenders, def := team18VsTeam2(t)
	// mq = 1000 drives the foul shrink negative (w <= 0); hcaRaw = −1e4 on the
	// half-court leg drives e90 negative.
	r, twin := rng.New(1988), rng.New(1988)
	andOneW, foulW := andOneAndFoulWeights(off[0], off, defenders, def, 0, -1e4, 0, 1000, true, r)
	if want := twin.Float64() * andOneBucketFloor; andOneW != want {
		t.Errorf("andOneW = %.17g, want first draw ×0.03 = %.17g", andOneW, want)
	}
	if want := twin.Float64() * foulFloor; foulW != want {
		t.Errorf("foulW = %.17g, want second draw ×0.6 = %.17g", foulW, want)
	}
	if a, b := r.Float64(), twin.Float64(); a != b {
		t.Error("andOneAndFoulWeights consumed other than exactly two draws")
	}
}

// TestAndOneAndFoulWeights_FallbackMatchesOldPath: with no DF0 the and-one
// weight is the rating path, and the RNG advances exactly as a bare
// foulBucketWeight call does.
func TestAndOneAndFoulWeights_FallbackMatchesOldPath(t *testing.T) {
	off, defenders, def := team18VsTeam2(t)
	noDF0 := *def
	noDF0.DF0 = nil
	const mq, hca, hcaScaled = 0.1, 0.2, 0.5
	for _, halfCourt := range []bool{true, false} {
		r, twin := rng.New(42), rng.New(42)
		andOneW, foulW := andOneAndFoulWeights(off[0], off, defenders, &noDF0, hca, hca, hcaScaled, mq, halfCourt, r)
		wantAndOne := andOneBucketWeight(mq, off[0])
		if halfCourt {
			wantAndOne = halfCourtAndOneWeight(mq, off[0], hcaScaled)
		}
		if andOneW != wantAndOne {
			t.Errorf("halfCourt=%v: andOneW = %.17g, want rating path %.17g", halfCourt, andOneW, wantAndOne)
		}
		if want := foulBucketWeight(off[0], off, defenders, hca, mq, twin); foulW != want {
			t.Errorf("halfCourt=%v: foulW = %.17g, want %.17g", halfCourt, foulW, want)
		}
		if a, b := r.Float64(), twin.Float64(); a != b {
			t.Errorf("halfCourt=%v: RNG advanced differently from a bare foulBucketWeight", halfCourt)
		}
	}
}

// TestAndOneAndFoulWeights_HalfCourtUsesRawHCA: the half-court and-one adds
// the raw hca (0.2), never the scaled one (0.5).
func TestAndOneAndFoulWeights_HalfCourtUsesRawHCA(t *testing.T) {
	off, defenders, def := team18VsTeam2(t)
	const hcaRaw, hcaScaled = 0.2, 0.5
	half, _ := andOneAndFoulWeights(off[0], off, defenders, def, 0, hcaRaw, hcaScaled, 0, true, rng.New(3))
	trans, _ := andOneAndFoulWeights(off[0], off, defenders, def, 0, hcaRaw, hcaScaled, 0, false, rng.New(3))
	if d := half - trans; math.Abs(d-hcaRaw) > 1e-12 {
		t.Errorf("half-court − transition and-one = %.17g, want hcaRaw %.17g", d, hcaRaw)
	}
}
