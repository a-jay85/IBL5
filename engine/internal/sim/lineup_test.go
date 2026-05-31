package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// --- matrix #3: starter selection + constant-energy fatigue ----------------

func TestSelectStarters_LowestPositiveDepthPerSlot(t *testing.T) {
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 2, Stamina: 50, DCCanPlayInGame: 1}, // backup PG
		{PID: 2, TeamID: 7, DCPGDepth: 1, Stamina: 50, DCCanPlayInGame: 1}, // starting PG
		{PID: 3, TeamID: 7, DCSGDepth: 1, Stamina: 0, DCCanPlayInGame: 1},
		{PID: 4, TeamID: 7, DCSFDepth: 1, Stamina: 99, DCCanPlayInGame: 1},
		{PID: 5, TeamID: 7, DCPFDepth: 1, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 6, TeamID: 7, DCCDepth: 1, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 7, TeamID: 7, DCCDepth: 3, Stamina: 50, DCCanPlayInGame: 0},   // DNP flag
		{PID: 99, TeamID: 3, DCPGDepth: 1, Stamina: 50, DCCanPlayInGame: 1}, // other team
	}
	starters := selectStarters(players, 7)
	if len(starters) != 5 {
		t.Fatalf("starters = %d, want 5", len(starters))
	}
	wantPID := map[int]int{slotPG: 2, slotSG: 3, slotSF: 4, slotPF: 5, slotC: 6}
	for _, s := range starters {
		if wantPID[s.slot] != s.PID {
			t.Errorf("slot %d: starter PID = %d, want %d", s.slot, s.PID, wantPID[s.slot])
		}
		// Constant energy (= base stamina) → fatigue is exactly 1.0 for all.
		if s.fatigue != 1.0 {
			t.Errorf("PID %d: fatigue = %v, want 1.0", s.PID, s.fatigue)
		}
	}
	// The depth-2 PG and the can't-play player must not be starters.
	for _, s := range starters {
		if s.PID == 1 || s.PID == 7 {
			t.Errorf("PID %d should not start", s.PID)
		}
	}
}

// --- matrix #4: boundary — equal/missing depths, < 5 eligible --------------

func TestSelectStarters_Boundaries(t *testing.T) {
	// Two players tie at PG depth 1: the bundle-order-first one wins, the other
	// is not double-assigned.
	tie := []bundle.Player{
		{PID: 10, TeamID: 7, DCPGDepth: 1, DCCanPlayInGame: 1},
		{PID: 11, TeamID: 7, DCPGDepth: 1, DCCanPlayInGame: 1},
		{PID: 12, TeamID: 7, DCSGDepth: 1, DCCanPlayInGame: 1},
	}
	starters := selectStarters(tie, 7)
	if len(starters) != 2 { // only PG + SG slots fillable → < 5 eligible
		t.Fatalf("starters = %d, want 2 (short lineup tolerated)", len(starters))
	}
	if starters[0].slot != slotPG || starters[0].PID != 10 {
		t.Errorf("PG starter = PID %d (slot %d), want PID 10", starters[0].PID, starters[0].slot)
	}
	for _, s := range starters {
		if s.PID == 11 {
			t.Error("PID 11 should not start (tie loser, no other open slot)")
		}
	}

	// Empty roster must not panic.
	if got := selectStarters(nil, 7); len(got) != 0 {
		t.Errorf("empty roster starters = %d, want 0", len(got))
	}
}
