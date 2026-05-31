package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #1/#8: GameMIN is accumulated on-court time, not static dc_minutes
//
// PR4a/PR3a set a starter's GameMIN to clamp(dc_minutes,0,48) at construction.
// PR4b replaces that: every player starts at 0 and accrues real on-court seconds
// finalized to rounded minutes at game end. A DNP (dc_can_play_in_game == 0)
// never accrues and stays 0.
func TestTeamState_GameMINIsAccumulatedNotStatic(t *testing.T) {
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCMinutes: 32, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 2, TeamID: 7, DCCDepth: 1, DCMinutes: 40, Stamina: 50, DCCanPlayInGame: 0}, // DNP
	}
	tm := newTeamState(players, 7, true)

	// Starter begins at 0 — NOT the old static clamp(dc_minutes)=32.
	if got := tm.box(1).GameMIN; got != 0 {
		t.Errorf("pre-game starter GameMIN = %d, want 0 (no longer seeded from dc_minutes)", got)
	}

	// PID 1 is the only on-court player; accrue 120 possessions × 15s = 1800s.
	for i := 0; i < 120; i++ {
		tm.drainAndRecover(15)
	}
	tm.finalizeMinutes()

	if got := tm.box(1).GameMIN; got != 30 { // round(1800/60) = 30, distinct from dc_minutes 32
		t.Errorf("accumulated GameMIN = %d, want 30 (real on-court time, not dc_minutes 32)", got)
	}
	if got := tm.box(2).GameMIN; got != 0 {
		t.Errorf("DNP GameMIN = %d, want 0", got)
	}
}

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
