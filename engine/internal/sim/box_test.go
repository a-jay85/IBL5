package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #22: box derivation rules --------------------------------------

func TestBoxDerivation_DeferredStats(t *testing.T) {
	res := Simulate(richBundle(), 1988)
	g := res.Games[0]

	var totalPF, totalFTA int
	for _, pb := range g.PlayerBoxes {
		// Steal/block attribution is PR3b; assists are commentary-only (L1098).
		if pb.GameSTL != 0 || pb.GameBLK != 0 || pb.GameAST != 0 {
			t.Errorf("player %d: STL/BLK/AST must be 0 in PR3a, got %d/%d/%d",
				pb.PID, pb.GameSTL, pb.GameBLK, pb.GameAST)
		}
		totalPF += pb.GamePF
		totalFTA += pb.GameFTA
	}
	if totalPF == 0 {
		t.Error("no personal fouls charged across the game")
	}
	if totalFTA == 0 {
		t.Error("no free-throw attempts across the game")
	}
}

func TestFreeThrows_ChargesDefenderAndShooter(t *testing.T) {
	b := richBundle()
	offense := newTeamState(b.Players, 7, false)
	defense := newTeamState(b.Players, 3, true)
	shooter := offense.players[0]
	defender := defense.players[0]
	gs := &gameState{rng: rng.New(1), period: 1, clock: 600}

	gs.freeThrows(offense, defense, shooter, defender, 2, 0)

	if got := defense.box(defender.PID).GamePF; got != 1 {
		t.Errorf("defender PF = %d, want 1", got)
	}
	sb := offense.box(shooter.PID)
	if sb.GameFTA != 2 {
		t.Errorf("shooter FTA = %d, want 2", sb.GameFTA)
	}
	if sb.GameFTM < 0 || sb.GameFTM > 2 {
		t.Errorf("shooter FTM = %d, want 0..2", sb.GameFTM)
	}
	// Points scored equal makes.
	if offense.score != sb.GameFTM {
		t.Errorf("score %d != FTM %d", offense.score, sb.GameFTM)
	}
}
