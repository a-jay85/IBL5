package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// lateGameState builds a gameState over a replay source for lateGameForcing.
func lateGameState(t *testing.T, period, clock int, values []int) (*gameState, *rng.RNG) {
	t.Helper()
	r, err := rng.NewReplay(values)
	if err != nil {
		t.Fatalf("NewReplay: %v", err)
	}
	return &gameState{rng: r, period: period, clock: clock}, r
}

// handler returns an on-court ball handler with the given drive-offense rating.
func handler(driveOff int) onCourt {
	return onCourt{Player: bundle.Player{DriveOff: driveOff}}
}

// --- matrix #1: the no-draw paths ------------------------------------------

// Mechanism 1 returns before the crunch block. Without that early return the
// forced-foul window (period 4, clock 3, offense up by the minimum lead) would
// roll one draw.
func TestLateGameForcingClockExpiryNoDraw(t *testing.T) {
	gs, r := lateGameState(t, 4, 3, []int{0})
	forcedMake, shotClock := gs.lateGameForcing(forcedFoulMinLead, handler(0))
	if !shotClock {
		t.Errorf("shotClock = false, want true at clock 3")
	}
	if forcedMake {
		t.Errorf("forcedMake = true, want false on clock expiry")
	}
	if n := len(r.Draws()); n != 0 {
		t.Errorf("clock expiry consumed %d draws, want 0", n)
	}
}

func TestLateGameForcingTrailingByThreeNoDraw(t *testing.T) {
	gs, r := lateGameState(t, 4, 20, []int{0})
	forcedMake, shotClock := gs.lateGameForcing(-3, handler(0))
	if !shotClock {
		t.Errorf("shotClock = false, want true when trailing by 3")
	}
	if forcedMake {
		t.Errorf("forcedMake = true, want false when trailing by 3")
	}
	if n := len(r.Draws()); n != 0 {
		t.Errorf("trailing by 3 consumed %d draws, want 0", n)
	}
}

func TestLateGameForcingOutsideWindowNoDraw(t *testing.T) {
	lead := forcedFoulMinLead
	cases := []struct {
		name      string
		period    int
		clock     int
		scoreDiff int
	}{
		{"third period", 3, 20, lead},
		{"clock at the 25 boundary", 4, 25, lead},
		{"margin outside the window", 4, 24, 0},
	}
	if forcedFoulFires(4, 24, 0) {
		t.Fatalf("precondition: forcedFoulFires(4, 24, 0) fires, so the margin case is not outside the window")
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			gs, r := lateGameState(t, tc.period, tc.clock, []int{0})
			forcedMake, shotClock := gs.lateGameForcing(tc.scoreDiff, handler(0))
			if forcedMake || shotClock {
				t.Errorf("flags = (forcedMake %v, shotClock %v), want both false", forcedMake, shotClock)
			}
			if n := len(r.Draws()); n != 0 {
				t.Errorf("consumed %d draws, want 0", n)
			}
		})
	}
}

// --- matrix #2: the forced-make path takes one draw ------------------------

// Raw 0 rolls 1 under both the old raw%10+1 replay path and JSB's scaling, so
// DriveOff 0 fires (0 < 1) and DriveOff 1 does not (1 < 1 is false).
func TestLateGameForcingForcedMakeOneDraw(t *testing.T) {
	if !forcedFoulFires(4, 20, forcedFoulMinLead) {
		t.Fatalf("precondition: forced-foul window closed")
	}
	cases := []struct {
		name     string
		driveOff int
		want     bool
	}{
		{"DriveOff 0 fires", 0, true},
		{"DriveOff 1 does not fire", 1, false},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			gs, r := lateGameState(t, 4, 20, []int{0})
			forcedMake, shotClock := gs.lateGameForcing(forcedFoulMinLead, handler(tc.driveOff))
			if forcedMake != tc.want {
				t.Errorf("forcedMake = %v, want %v", forcedMake, tc.want)
			}
			if shotClock {
				t.Errorf("shotClock = true, want false on the forced-make path")
			}
			if n := len(r.Draws()); n != 1 {
				t.Errorf("forced-make path consumed %d draws, want 1", n)
			}
		})
	}
}

// --- matrix #3: randInt copies JSB's scaling on one raw draw ---------------

// 3276*10/32768 = 0.99976 truncates to 0; 3277*10/32768 = 1.00006 truncates
// to 1. A `r.Raw()%bound + 1` port gives 7 for raw 3276, 8 for raw 32767, and
// panics at bound 0.
func TestRandIntBoundaries(t *testing.T) {
	cases := []struct {
		bound, raw, want int
	}{
		{10, 0, 1},
		{10, 3276, 1},
		{10, 3277, 2},
		{10, 32767, 10},
		{1, 32767, 1},
		{0, 32767, 1},
	}
	for _, tc := range cases {
		r, err := rng.NewReplay([]int{tc.raw})
		if err != nil {
			t.Fatalf("NewReplay: %v", err)
		}
		if got := randInt(r, tc.bound); got != tc.want {
			t.Errorf("randInt(raw %d, bound %d) = %d, want %d", tc.raw, tc.bound, got, tc.want)
		}
		draws := r.Draws()
		if len(draws) != 1 {
			t.Fatalf("randInt(raw %d, bound %d) consumed %d draws, want 1", tc.raw, tc.bound, len(draws))
		}
		if draws[0].API != "raw" || draws[0].Value != tc.raw {
			t.Errorf("draw = {API %q, Value %d}, want {API \"raw\", Value %d}", draws[0].API, draws[0].Value, tc.raw)
		}
	}
}

// --- matrix #4: the forced-make roll goes through randInt ------------------

// Raw 32767 rolls 10 under JSB's scaling, so DriveOff 8 fires. The old
// `gs.rng.IntN(doForcedMakeMax)+1` replay path rolls 32767%10+1 = 8, and
// 8 < 8 is false, with draw API intn.
func TestLateGameForcingForcedMakeUsesRandInt(t *testing.T) {
	gs, r := lateGameState(t, 4, 20, []int{32767})
	forcedMake, _ := gs.lateGameForcing(forcedFoulMinLead, handler(8))
	if !forcedMake {
		t.Errorf("forcedMake = false, want true for DriveOff 8 against roll 10")
	}
	draws := r.Draws()
	if len(draws) != 1 {
		t.Fatalf("consumed %d draws, want 1", len(draws))
	}
	if draws[0].API != "raw" {
		t.Errorf("draw API = %q, want \"raw\"", draws[0].API)
	}
}
