package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

func testBundle() bundle.Bundle {
	return bundle.Bundle{
		Seed:  42,
		Teams: []bundle.Team{{TeamID: 3, Name: "Heat"}, {TeamID: 7, Name: "Lakers"}},
		Players: []bundle.Player{
			{PID: 101, TeamID: 3, DCMinutes: 34, DCPGDepth: 1, DCCanPlayInGame: 1},
			{PID: 102, TeamID: 3, DCMinutes: 0, DCCDepth: 1, DCCanPlayInGame: 0}, // DNP
			{PID: 201, TeamID: 7, DCMinutes: 30, DCSGDepth: 1, DCCanPlayInGame: 1},
		},
		Schedule: []bundle.Game{
			{HomeTeamID: 3, VisitorTeamID: 7, Date: "1988-11-04", GameType: bundle.GameTypeRegular},
		},
	}
}

// #10 — the placeholder sim produces structurally-valid output for a valid
// bundle: one game, exactly two team boxes (visitor first), a player box per
// rostered player, a non-empty event stream, and a DNP row expressed as
// GameMIN == 0.
func TestSimulate_StructurallyValid(t *testing.T) {
	res := Simulate(testBundle(), 42)

	if res.Seed != 42 {
		t.Errorf("seed = %d, want 42", res.Seed)
	}
	if len(res.Games) != 1 {
		t.Fatalf("games = %d, want 1", len(res.Games))
	}
	g := res.Games[0]

	if len(g.TeamBoxes) != 2 {
		t.Fatalf("team boxes = %d, want 2", len(g.TeamBoxes))
	}
	if g.TeamBoxes[0].TeamID != g.VisitorTeamID || g.TeamBoxes[0].IsHome {
		t.Errorf("team box order: first box should be the visitor (team %d), got %+v", g.VisitorTeamID, g.TeamBoxes[0])
	}
	if len(g.PlayerBoxes) != 3 {
		t.Fatalf("player boxes = %d, want 3 (all rostered players incl. DNP)", len(g.PlayerBoxes))
	}
	if len(g.Events) == 0 {
		t.Error("event stream is empty")
	}

	// The dc_can_play_in_game == 0 player must be a DNP row (GameMIN == 0).
	dnp := findBox(g.PlayerBoxes, 102)
	if dnp == nil {
		t.Fatal("missing player box for DNP player 102")
	}
	if dnp.GameMIN != 0 {
		t.Errorf("DNP player GameMIN = %d, want 0", dnp.GameMIN)
	}

	// An active player must have minutes and at least one event-bearing slot.
	active := findBox(g.PlayerBoxes, 101)
	if active == nil || active.GameMIN == 0 {
		t.Errorf("active player 101 should have minutes, got %+v", active)
	}
}

// A team's quarter points must sum to its total points (placeholder invariant).
func TestSimulate_QuarterPointsSumToTotal(t *testing.T) {
	res := Simulate(testBundle(), 42)
	for _, tb := range res.Games[0].TeamBoxes {
		pts := 2*tb.Game2GM + tb.GameFTM + 3*tb.Game3GM
		q := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
		for _, ot := range tb.OT {
			q += ot
		}
		if q != pts {
			t.Errorf("team %d: quarters sum to %d, total points %d", tb.TeamID, q, pts)
		}
	}
}

func findBox(boxes []result.PlayerBox, pid int) *result.PlayerBox {
	for i := range boxes {
		if boxes[i].PID == pid {
			return &boxes[i]
		}
	}
	return nil
}
