package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #1/#2: box derivation rules (PR3b contract) ---------------------
//
// PR3a asserted STL == BLK == AST == 0. PR3b credits steals and blocks, so the
// contract changes: AST stays 0 (commentary-only, L1098), while total STL and
// total BLK are now positive and every credit lands on a DEFENDER (the team not
// on offense for that play), verified through the event stream.
func TestBoxDerivation_DeferredStats(t *testing.T) {
	b := richBundle()
	teamByPID := map[int]int{}
	for _, p := range b.Players {
		teamByPID[p.PID] = p.TeamID
	}
	res := Simulate(b, 1988)
	g := res.Games[0]

	var totalPF, totalFTA, totalSTL, totalBLK int
	for _, pb := range g.PlayerBoxes {
		if pb.GameAST != 0 { // assists are commentary-only (L1098), still 0
			t.Errorf("player %d: GameAST must be 0, got %d", pb.PID, pb.GameAST)
		}
		totalPF += pb.GamePF
		totalFTA += pb.GameFTA
		totalSTL += pb.GameSTL
		totalBLK += pb.GameBLK
	}
	if totalPF == 0 {
		t.Error("no personal fouls charged across the game")
	}
	if totalFTA == 0 {
		t.Error("no free-throw attempts across the game")
	}
	if totalSTL == 0 {
		t.Error("no steals credited across the game")
	}
	if totalBLK == 0 {
		t.Error("no blocks credited across the game")
	}

	// Every steal/block event must credit the DEFENDER (a player on the team that
	// is NOT the offense, identified by the event's TeamID = offense).
	for _, e := range g.Events {
		switch e.Kind {
		case result.EventSteal, result.EventBlock:
			if teamByPID[e.DefenderID] == e.TeamID {
				t.Errorf("%s credited DefenderID %d on the offensive team %d (must be a defender)",
					e.Kind, e.DefenderID, e.TeamID)
			}
		}
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
