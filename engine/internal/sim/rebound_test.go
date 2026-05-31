package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #20: defender weighting, OREB floor/ceil, rebounder weighting --

func TestOrebProbability_FloorCeilEqual(t *testing.T) {
	if got := orebProbability(0, 100); math.Abs(got-0.25) > 1e-9 {
		t.Errorf("weak offense OREB = %v, want 0.25 floor", got)
	}
	if got := orebProbability(100, 0); math.Abs(got-0.75) > 1e-9 {
		t.Errorf("dominant offense OREB = %v, want 0.75 ceil", got)
	}
	if got := orebProbability(50, 50); math.Abs(got-0.5) > 1e-9 {
		t.Errorf("equal OREB = %v, want 0.50", got)
	}
}

func TestSelectDefender_FavorsHigherRating(t *testing.T) {
	d := &teamState{players: []onCourt{
		oc(slotPG, bundle.Player{PID: 1, OD: 2, Stamina: 50}),
		oc(slotSG, bundle.Player{PID: 2, OD: 9, Stamina: 50}), // strongest perimeter D
		oc(slotSF, bundle.Player{PID: 3, OD: 2, Stamina: 50}),
	}}
	r := rng.New(13)
	counts := map[int]int{}
	for i := 0; i < 6000; i++ {
		counts[selectDefender(d, playOutside, r).PID]++
	}
	if counts[2] <= counts[1] || counts[2] <= counts[3] {
		t.Errorf("higher OD defender not favored: %v", counts)
	}
}

func TestSelectRebounder_FavorsHigherRating(t *testing.T) {
	tm := &teamState{players: []onCourt{
		oc(slotPG, bundle.Player{PID: 1, DRB: 10, Stamina: 50}),
		oc(slotC, bundle.Player{PID: 2, DRB: 90, Stamina: 50}), // dominant rebounder
		oc(slotSF, bundle.Player{PID: 3, DRB: 10, Stamina: 50}),
	}}
	r := rng.New(17)
	counts := map[int]int{}
	for i := 0; i < 6000; i++ {
		counts[selectRebounder(tm, false, r).PID]++
	}
	if counts[2] <= counts[1] || counts[2] <= counts[3] {
		t.Errorf("dominant rebounder not favored: %v", counts)
	}
}

// --- matrix #21: boundary — equal off/def → .50; all-1s no divide-by-zero --

func TestRebound_Boundaries(t *testing.T) {
	// Equal-zero strengths must not divide by zero and must give .50.
	if got := orebProbability(0, 0); math.Abs(got-0.5) > 1e-9 {
		t.Errorf("zero/zero OREB = %v, want 0.50", got)
	}
	// All-zero rebound ratings: ratings floor to 1, so selection still works.
	tm := &teamState{players: []onCourt{
		oc(slotPG, bundle.Player{PID: 1}),
		oc(slotC, bundle.Player{PID: 2}),
	}}
	r := rng.New(19)
	for i := 0; i < 1000; i++ {
		got := selectRebounder(tm, true, r)
		if got.PID != 1 && got.PID != 2 {
			t.Fatalf("invalid rebounder PID %d", got.PID)
		}
	}
}
