package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #5: ball-handler share + slot→ODPT mapping ---------------------

func TestBallHandlerRating_SlotMapping(t *testing.T) {
	p := bundle.Player{OO: 7, DriveOff: 8, PO: 9}
	cases := map[int]int{slotPG: 8, slotSG: 7, slotSF: 7, slotPF: 9, slotC: 9}
	for slot, want := range cases {
		if got := ballHandlerRating(oc(slot, p)); got != want {
			t.Errorf("slot %d: rating = %d, want %d", slot, got, want)
		}
	}
}

func TestBallHandlerShare_Formula(t *testing.T) {
	// share = rating / (team_total − rating)
	if got := ballHandlerShare(4, 10); math.Abs(got-4.0/6.0) > 1e-9 {
		t.Errorf("share = %v, want %v", got, 4.0/6.0)
	}
	// Denominator guard: a sole/dominant handler cannot divide by zero.
	if got := ballHandlerShare(5, 5); got != 5.0 {
		t.Errorf("guarded share = %v, want 5.0", got)
	}
}

// --- matrix #6: all-1s ratings — no divide-by-zero / crash -----------------

func TestSelectBallHandler_AllOnes(t *testing.T) {
	tm := &teamState{}
	for slot := slotPG; slot <= slotC; slot++ {
		p := bundle.Player{OO: 1, DriveOff: 1, PO: 1}
		tm.players = append(tm.players, oc(slot, p))
	}
	r := rng.New(7)
	for i := 0; i < 1000; i++ {
		got := selectBallHandler(tm, r) // must not panic / NaN
		if got.slot < slotPG || got.slot > slotC {
			t.Fatalf("invalid handler slot %d", got.slot)
		}
	}
}

func TestSelectBallHandler_FavorsHigherShare(t *testing.T) {
	// One dominant outside scorer at SG should be picked far more often.
	tm := &teamState{}
	tm.players = append(tm.players, oc(slotPG, bundle.Player{PID: 1, DriveOff: 2, OO: 2, PO: 2}))
	tm.players = append(tm.players, oc(slotSG, bundle.Player{PID: 2, OO: 9, DriveOff: 2, PO: 2}))
	tm.players = append(tm.players, oc(slotSF, bundle.Player{PID: 3, OO: 2, DriveOff: 2, PO: 2}))
	r := rng.New(11)
	counts := map[int]int{}
	for i := 0; i < 5000; i++ {
		counts[selectBallHandler(tm, r).PID]++
	}
	if counts[2] <= counts[1] || counts[2] <= counts[3] {
		t.Errorf("dominant handler not favored: %v", counts)
	}
}
