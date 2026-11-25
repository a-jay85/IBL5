package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// These tests run selectBallHandlerJSB on hand-built inputs, so they run in CI
// where the private pl-0010 capture is absent. The capture-pinned tests in
// tracepinned_pl0010_test.go check exact values against JSB.

// jsbTestInputs returns a lineup where slot 1 holds 40 of the lineup's 100
// two-point composite. That share clears the 0.3 flag gate and the 0.375 cap.
// Every other slot holds 15, under the gate.
func jsbTestInputs() ballHandlerInputs {
	var in ballHandlerInputs
	for k := range in.Players {
		in.Players[k] = ballHandlerPlayer{
			ID:             11 + k,
			OutsideOff:     50,
			DriveOff:       50,
			PostOff:        50,
			TwoPtComposite: 15,
			Aux1:           10,
			Aux2:           1,
			Aux3:           2,
			Aux4:           3,
		}
		in.PosTwoPtPct[k] = 50
	}
	in.Players[0].TwoPtComposite = 40
	in.OffTeam = 2
	in.WeightGate = 1
	in.PlayType = 1
	for k := range in.FlagsBefore {
		in.FlagsBefore[k] = flagUnwritten
	}
	return in
}

func jsbReplay(t *testing.T, draws ...int) *rng.RNG {
	t.Helper()
	rp, err := rng.NewReplay(draws)
	if err != nil {
		t.Fatalf("replay: %v", err)
	}
	return rp
}

func checkJSBFlags(t *testing.T, got [ballHandlerFlagCount]int, idx, want int) {
	t.Helper()
	for k, v := range got {
		switch {
		case k == idx && v != want:
			t.Errorf("flag %d = %d, want %d", k, v, want)
		case k != idx && v != flagUnwritten:
			t.Errorf("flag %d = %d, want it left at %d", k, v, flagUnwritten)
		}
	}
}

// A pick draw of 0 lands on no slot, because slot 1 needs a draw above 0. The
// routine must roll again. A draw of RawMax lands on slot 5.
func TestSelectBallHandlerJSBRerollsAndSetsTeam2Flag(t *testing.T) {
	rp := jsbReplay(t, 0, 0, rng.RawMax)
	got := selectBallHandlerJSB(jsbTestInputs(), rp)
	if got.Ret != 15 || got.Slot != 5 {
		t.Errorf("ret %d slot %d, want 15 slot 5", got.Ret, got.Slot)
	}
	if got.P6 != 0 || got.P8 != 0 {
		t.Errorf("p6 %d p8 %d, want 0 and 0", got.P6, got.P8)
	}
	if n := len(rp.Draws()); n != 3 {
		t.Errorf("took %d draws, want 3", n)
	}
	// Team 2's slot 1 is flag index 1. A flag draw of 0 is below the 0.4 share.
	checkJSBFlags(t, got.Flags, 1, ballHandlerFlagValue)
}

// Team 1 writes flag index slot+5. A flag draw of RawMax scales to about 0.5,
// above the 0.4 share, so the flag clears to 0.
func TestSelectBallHandlerJSBClearsTeam1FlagWithHomeCourt(t *testing.T) {
	in := jsbTestInputs()
	in.OffTeam = 1
	in.HomeCourt = 1
	in.HomeCourtAdv = 2
	rp := jsbReplay(t, rng.RawMax, rng.RawMax)
	got := selectBallHandlerJSB(in, rp)
	if got.Ret != 15 || got.Slot != 5 {
		t.Errorf("ret %d slot %d, want 15 slot 5", got.Ret, got.Slot)
	}
	if n := len(rp.Draws()); n != 2 {
		t.Errorf("took %d draws, want 2", n)
	}
	checkJSBFlags(t, got.Flags, 6, 0)
}

// Play type 5 rolls p8 before the flags. The team terms sum to 1, so a draw of
// 0 is under the 0.5 limit and sets p8. A draw of RawMax is over it.
func TestSelectBallHandlerJSBPlayType5RollsP8(t *testing.T) {
	for _, tc := range []struct {
		name   string
		p8Draw int
		wantP8 int
	}{
		{"under_limit", 0, 1},
		{"over_limit", rng.RawMax, 0},
	} {
		t.Run(tc.name, func(t *testing.T) {
			in := jsbTestInputs()
			in.PlayType = 5
			in.P8In = 7
			in.WeightGate = 0
			in.TeamRollTerms = [4]float64{0.25, 0.25, 0.25, 0.25}
			in.TeamRollLimit = 0.5
			rp := jsbReplay(t, tc.p8Draw, 0, rng.RawMax)
			got := selectBallHandlerJSB(in, rp)
			if got.P8 != tc.wantP8 {
				t.Errorf("p8 %d, want %d", got.P8, tc.wantP8)
			}
			if got.Ret != 15 || got.Slot != 5 {
				t.Errorf("ret %d slot %d, want 15 slot 5", got.Ret, got.Slot)
			}
			if n := len(rp.Draws()); n != 3 {
				t.Errorf("took %d draws, want 3", n)
			}
		})
	}
}

// Off play type 5 the caller's p8 passes through and selects the p8 weight.
func TestSelectBallHandlerJSBPassesP8Through(t *testing.T) {
	in := jsbTestInputs()
	in.P8In = 1
	rp := jsbReplay(t, 0, rng.RawMax)
	got := selectBallHandlerJSB(in, rp)
	if got.P8 != 1 {
		t.Errorf("p8 %d, want the caller's 1", got.P8)
	}
	if got.Ret != 15 || got.Slot != 5 {
		t.Errorf("ret %d slot %d, want 15 slot 5", got.Ret, got.Slot)
	}
}

// A slot with no player is never picked, so a draw that lands on it rolls
// again.
func TestSelectBallHandlerJSBSkipsEmptySlot(t *testing.T) {
	in := jsbTestInputs()
	in.Players[4].ID = 0
	rp := jsbReplay(t, 0, rng.RawMax, 1)
	got := selectBallHandlerJSB(in, rp)
	if got.Ret != 11 || got.Slot != 1 {
		t.Errorf("ret %d slot %d, want 11 slot 1", got.Ret, got.Slot)
	}
	if n := len(rp.Draws()); n != 3 {
		t.Errorf("took %d draws, want 3", n)
	}
}

func TestSelectBallHandlerJSBPanicsOnPriorHandler(t *testing.T) {
	in := jsbTestInputs()
	in.PrevHandler = 11
	defer func() {
		if recover() == nil {
			t.Error("no panic on a prior handler")
		}
	}()
	selectBallHandlerJSB(in, jsbReplay(t, 0))
}
