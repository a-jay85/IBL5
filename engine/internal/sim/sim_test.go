package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// --- shared test helpers (package sim) -------------------------------------

// setSlot marks a player as first-string at the given lineup slot.
func setSlot(p *bundle.Player, slot int) {
	switch slot {
	case slotPG:
		p.DCPGDepth = 1
	case slotSG:
		p.DCSGDepth = 1
	case slotSF:
		p.DCSFDepth = 1
	case slotPF:
		p.DCPFDepth = 1
	case slotC:
		p.DCCDepth = 1
	}
}

// mkPlayer builds a fully-rated, playable starter at the given slot. Tests
// override individual fields on the returned value as needed.
func mkPlayer(pid, team, slot, fgp int) bundle.Player {
	p := bundle.Player{
		PID: pid, TeamID: team,
		OO: 6, DriveOff: 5, PO: 5, OD: 5, DD: 5, PD: 5, TD: 5,
		FGP: fgp, FTP: 75, TGA: 25, FGA: 60, ORB: 20, DRB: 35,
		STL: 30, TVR: 40, BLK: 20, Foul: 30,
		Stamina: 50, Clutch: 5, Consistency: 5,
		DCMinutes: 32, DCCanPlayInGame: 1,
	}
	setSlot(&p, slot)
	return p
}

// oc wraps a bundle.Player as an on-court starter at the given slot, with the
// PR3a constant-energy fatigue.
func oc(slot int, p bundle.Player) onCourt {
	return onCourt{Player: p, slot: slot, fatigue: fatigueFactor(p.Stamina)}
}

// richBundle is an asymmetric, fully-rated two-team fixture (5 starters each)
// used by full-game tests. The home team shoots better so games resolve to a
// winner rather than persistently tying.
func richBundle() bundle.Bundle {
	var players []bundle.Player
	pid := 100
	for _, tm := range []struct {
		id  int
		fgp int
	}{{7, 46}, {3, 50}} {
		for slot := slotPG; slot <= slotC; slot++ {
			pid++
			players = append(players, mkPlayer(pid, tm.id, slot, tm.fgp))
		}
	}
	return bundle.Bundle{
		Seed:    1988,
		Teams:   []bundle.Team{{TeamID: 3, Name: "Heat"}, {TeamID: 7, Name: "Lakers"}},
		Players: players,
		Schedule: []bundle.Game{
			{HomeTeamID: 3, VisitorTeamID: 7, Date: "1988-11-04", GameType: bundle.GameTypeRegular},
		},
	}
}

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

// --- matrix #1: structural output contract ---------------------------------

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
	hasBoundary := false
	for _, e := range g.Events {
		if e.Kind == result.EventPeriodBoundary {
			hasBoundary = true
			break
		}
	}
	if !hasBoundary {
		t.Error("event stream has no period boundary")
	}

	dnp := findBox(g.PlayerBoxes, 102)
	if dnp == nil {
		t.Fatal("missing player box for DNP player 102")
	}
	if dnp.GameMIN != 0 {
		t.Errorf("DNP player GameMIN = %d, want 0", dnp.GameMIN)
	}
	// DNP row must be all-zero.
	if *dnp != (result.PlayerBox{PID: 102, Pos: "C"}) {
		t.Errorf("DNP row should be all-zero, got %+v", *dnp)
	}

	active := findBox(g.PlayerBoxes, 101)
	if active == nil || active.GameMIN == 0 {
		t.Errorf("active player 101 should have minutes, got %+v", active)
	}
}

// --- matrix #2: quarter points sum to real total ---------------------------

func TestSimulate_QuarterPointsSumToTotal(t *testing.T) {
	res := Simulate(testBundle(), 42)
	for _, tb := range res.Games[0].TeamBoxes {
		pts := 2*tb.Game2GM + 3*tb.Game3GM + tb.GameFTM
		q := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
		for _, ot := range tb.OT {
			q += ot
		}
		if q != pts {
			t.Errorf("team %d: quarters sum to %d, total points %d", tb.TeamID, q, pts)
		}
	}
}

// --- matrix #23: full-game termination -------------------------------------

func TestSimulate_TerminatesWithWinner(t *testing.T) {
	for _, seed := range []uint64{1, 2, 42, 1988, 99999} {
		res := Simulate(richBundle(), seed)
		g := res.Games[0]
		v, h := g.TeamBoxes[0], g.TeamBoxes[1]
		vp := 2*v.Game2GM + 3*v.Game3GM + v.GameFTM
		hp := 2*h.Game2GM + 3*h.Game3GM + h.GameFTM
		if vp == hp {
			t.Errorf("seed %d: game tied %d-%d (no winner after max OT)", seed, vp, hp)
		}
		starts := 0
		for _, e := range g.Events {
			if e.Kind == result.EventPossessionStart {
				starts++
			}
		}
		if starts == 0 || starts > 600 {
			t.Errorf("seed %d: possession count %d out of expected bounds", seed, starts)
		}
	}
}

// --- matrix #24: full-game internal consistency ----------------------------

func TestSimulate_InternalConsistency(t *testing.T) {
	b := richBundle()
	teamByPID := map[int]int{}
	for _, p := range b.Players {
		teamByPID[p.PID] = p.TeamID
	}
	res := Simulate(b, 1988)
	g := res.Games[0]

	for ti, tb := range g.TeamBoxes {
		// Recompute team totals from the player rows for this team.
		var sum result.TeamBox
		teamID := g.VisitorTeamID
		if ti == 1 {
			teamID = g.HomeTeamID
		}
		for _, pb := range g.PlayerBoxes {
			if teamByPID[pb.PID] != teamID {
				continue
			}
			if pb.Game2GM > pb.Game2GA || pb.Game3GM > pb.Game3GA || pb.GameFTM > pb.GameFTA {
				t.Errorf("player %d: made > attempted (%+v)", pb.PID, pb)
			}
			if anyNegative(pb) {
				t.Errorf("player %d: negative stat (%+v)", pb.PID, pb)
			}
			sum.Game2GM += pb.Game2GM
			sum.Game2GA += pb.Game2GA
			sum.Game3GM += pb.Game3GM
			sum.Game3GA += pb.Game3GA
			sum.GameFTM += pb.GameFTM
			sum.GameFTA += pb.GameFTA
		}
		if sum.Game2GM != tb.Game2GM || sum.Game3GM != tb.Game3GM || sum.GameFTM != tb.GameFTM ||
			sum.Game2GA != tb.Game2GA || sum.Game3GA != tb.Game3GA || sum.GameFTA != tb.GameFTA {
			t.Errorf("team %d: team box != sum of player rows", tb.TeamID)
		}

		if tb.Game2GM > tb.Game2GA || tb.Game3GM > tb.Game3GA || tb.GameFTM > tb.GameFTA {
			t.Errorf("team %d: made > attempted (%+v)", tb.TeamID, tb)
		}
		pts := 2*tb.Game2GM + 3*tb.Game3GM + tb.GameFTM
		q := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
		for _, ot := range tb.OT {
			q += ot
		}
		if q != pts {
			t.Errorf("team %d: quarters %d != points %d", tb.TeamID, q, pts)
		}
	}
}

// --- matrix #25: full-game balance -----------------------------------------

func TestSimulate_PossessionBalance(t *testing.T) {
	res := Simulate(richBundle(), 1988)
	g := res.Games[0]
	byTeam := map[int]int{}
	for _, e := range g.Events {
		if e.Kind == result.EventPossessionStart {
			byTeam[e.TeamID]++
		}
	}
	v, h := byTeam[g.VisitorTeamID], byTeam[g.HomeTeamID]
	diff := v - h
	if diff < 0 {
		diff = -diff
	}
	// Strict alternation should keep the two within a small margin.
	if diff > (v+h)/10+2 {
		t.Errorf("possession imbalance: visitor %d, home %d", v, h)
	}
}

func anyNegative(b result.PlayerBox) bool {
	return b.GameMIN < 0 || b.Game2GM < 0 || b.Game2GA < 0 || b.Game3GM < 0 || b.Game3GA < 0 ||
		b.GameFTM < 0 || b.GameFTA < 0 || b.GameORB < 0 || b.GameDRB < 0 || b.GameAST < 0 ||
		b.GameSTL < 0 || b.GameTOV < 0 || b.GameBLK < 0 || b.GamePF < 0
}

func findBox(boxes []result.PlayerBox, pid int) *result.PlayerBox {
	for i := range boxes {
		if boxes[i].PID == pid {
			return &boxes[i]
		}
	}
	return nil
}
