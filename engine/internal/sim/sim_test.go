package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
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
		OO: 6, DriveOff: 5, PO: 5, OD: 5, DD: 5, PD: 5, TD: 5, TransOff: 7,
		FGP: fgp, FTP: 75, TGA: 25, FGA: 60, FTA: 20, ORB: 20, DRB: 35,
		STL: 30, TVR: 40, BLK: 20, Foul: 30,
		Stamina: 50, Clutch: 5, Consistency: 5,
		DCMinutes: 32, DCCanPlayInGame: 1,
	}
	setSlot(&p, slot)
	return p
}

// oc wraps a bundle.Player as an on-court player at the given slot, with live
// energy seeded to base stamina (the tip-off rest value).
func oc(slot int, p bundle.Player) onCourt {
	return onCourt{Player: p, slot: slot, energy: p.Stamina, fatigue: fatigueFactor(p.Stamina)}
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

// rotationBundle is a full-game fixture with a real bench: each team has a
// starter + one backup (depth 2) at PG/SG/SF/PF, and a single high-foul center
// with NO backup. Low Stamina (40) drives fatigue subs at PG-PF; the un-backed,
// foul-prone center accumulates personal fouls until it fouls out (it can never
// be pulled for foul-trouble, having no replacement). Home shoots better so the
// game resolves to a winner.
func rotationBundle() bundle.Bundle {
	var players []bundle.Player
	pid := 100
	mk := func(team, slot, fgp, depth, foul int) bundle.Player {
		pid++
		p := mkPlayer(pid, team, slot, fgp)
		setDepthAt(&p, slot, depth)
		p.Foul = foul
		p.Stamina = 40
		return p
	}
	for _, tm := range []struct{ id, fgp int }{{7, 44}, {3, 52}} {
		for slot := slotPG; slot <= slotPF; slot++ {
			players = append(players, mk(tm.id, slot, tm.fgp, 1, 40)) // starter
			players = append(players, mk(tm.id, slot, tm.fgp, 2, 40)) // backup
		}
		players = append(players, mk(tm.id, slotC, tm.fgp, 1, 99)) // lone, foul-prone C
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

// setDepthAt sets a player's depth ordinal at the given slot (setSlot only sets
// depth 1; this allows bench depths).
func setDepthAt(p *bundle.Player, slot, depth int) {
	switch slot {
	case slotPG:
		p.DCPGDepth = depth
	case slotSG:
		p.DCSGDepth = depth
	case slotSF:
		p.DCSFDepth = depth
	case slotPF:
		p.DCPFDepth = depth
	case slotC:
		p.DCCDepth = depth
	}
}

// --- matrix #15: substitutions fire over a full rotation game ---------------

func TestSimulate_SubstitutionsFire(t *testing.T) {
	res := Simulate(rotationBundle(), 1988)
	subs := 0
	for _, e := range res.Games[0].Events {
		if e.Kind == result.EventSubstitution {
			subs++
		}
	}
	if subs == 0 {
		t.Error("no EventSubstitution fired over a full rotation game")
	}
	if subs%2 != 0 {
		t.Errorf("substitution events = %d, want even (each swap is an out+in pair)", subs)
	}
}

// --- matrix #16: minutes conservation (iron-man richBundle, no subs) ---------
//
// In richBundle every team has exactly 5 eligible players, so no substitution or
// foul-out can occur: all 10 players are on court for every possession of the
// game. Each therefore accrues `step` seconds per game-possession, so GameMIN ==
// round(totalPossessions × step / 60) for every player. The expected value is
// derived from the ACTUAL possession count and the engine's own step, never an
// assumed number.
func TestSimulate_MinutesConservation(t *testing.T) {
	b := richBundle()
	v := newTeamState(b.Players, 7, false)
	h := newTeamState(b.Players, 3, true)
	step := possessionTime((teamBaseTime(v.players) + teamBaseTime(h.players)) / 2.0)

	res := Simulate(b, 1988)
	g := res.Games[0]
	totalPoss := 0
	for _, e := range g.Events {
		if e.Kind == result.EventPossessionStart {
			totalPoss++
		}
	}
	wantMin := int(math.Round(float64(totalPoss*step) / 60.0))

	for _, pb := range g.PlayerBoxes {
		if pb.GameMIN != wantMin {
			t.Errorf("player %d GameMIN = %d, want %d (round(%d poss × %d step / 60))",
				pb.PID, pb.GameMIN, wantMin, totalPoss, step)
		}
	}
}

// --- matrix #17: a fouled-out player stops accruing minutes -----------------
//
// rotationBundle's lone, foul-prone center has no backup, so it can never be
// pulled for foul-trouble — it plays until it fouls out, then is removed for
// good. Its minutes must stop short of a player who played the whole game.
func TestSimulate_FoulOutStopsMinutes(t *testing.T) {
	res := Simulate(rotationBundle(), 1988)
	g := res.Games[0]

	maxMin := 0
	var fouledOut []result.PlayerBox
	for _, pb := range g.PlayerBoxes {
		if pb.GameMIN > maxMin {
			maxMin = pb.GameMIN
		}
		if pb.GamePF >= 6 {
			fouledOut = append(fouledOut, pb)
		}
	}
	if len(fouledOut) == 0 {
		t.Fatal("no player fouled out in the rotation game (fixture/seed no longer forces a foul-out)")
	}
	for _, pb := range fouledOut {
		if pb.GameMIN == 0 {
			t.Errorf("fouled-out player %d has 0 minutes (never played?)", pb.PID)
		}
		if pb.GameMIN >= maxMin {
			t.Errorf("fouled-out player %d GameMIN = %d, want < whole-game max %d (minutes did not stop)",
				pb.PID, pb.GameMIN, maxMin)
		}
	}
}

// --- matrix #18: bench players who entered have minutes ---------------------
//
// rotationBundle dresses 9 players per team (18 total) but starts only 5 per
// team (10). If more than 10 players have GameMIN > 0, bench players entered via
// substitution and accrued time.
func TestSimulate_BenchPlayersEnter(t *testing.T) {
	res := Simulate(rotationBundle(), 1988)
	played := 0
	for _, pb := range res.Games[0].PlayerBoxes {
		if pb.GameMIN > 0 {
			played++
		}
	}
	if played <= 10 {
		t.Errorf("players with minutes = %d, want > 10 (bench entered)", played)
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

// --- matrix #20/#21: steal & block attribution over a full game -------------

func TestSimulate_StealBlockCreditedToDefenders(t *testing.T) {
	b := richBundle()
	teamByPID := map[int]int{}
	for _, p := range b.Players {
		teamByPID[p.PID] = p.TeamID
	}
	res := Simulate(b, 1988)
	g := res.Games[0]

	var totalSTL, totalBLK int
	for _, pb := range g.PlayerBoxes {
		totalSTL += pb.GameSTL
		totalBLK += pb.GameBLK
	}
	if totalSTL == 0 {
		t.Error("total STL == 0 over a full game")
	}
	if totalBLK == 0 {
		t.Error("total BLK == 0 over a full game")
	}

	// Every steal/block event credits a player on the team that is NOT on offense
	// (the event's TeamID is the offense), i.e. a defender.
	steals, blocks := 0, 0
	for _, e := range g.Events {
		switch e.Kind {
		case result.EventSteal:
			steals++
			if teamByPID[e.DefenderID] == e.TeamID {
				t.Errorf("steal credited to offensive player %d (team %d)", e.DefenderID, e.TeamID)
			}
		case result.EventBlock:
			blocks++
			if teamByPID[e.DefenderID] == e.TeamID {
				t.Errorf("block credited to offensive player %d (team %d)", e.DefenderID, e.TeamID)
			}
		}
	}
	if steals == 0 || blocks == 0 {
		t.Errorf("event stream had %d steals, %d blocks; want both > 0", steals, blocks)
	}
}

// --- matrix #22: transition possessions occur over a full game ---------------
//
// Fast-break possessions leave no distinct marker in the (frozen) result
// contract, so the count is observed through simGame's internal return. The
// guarantee that a transition is never a 3pt attempt is proven structurally by
// TestRunTransitionPossession_NeverThreePoint (#10).
func TestSimulate_TransitionsOccur(t *testing.T) {
	b := richBundle()
	_, transitions, _, _ := simGame(b, b.Schedule[0], rng.New(1988))
	if transitions == 0 {
		t.Error("no fast-break possessions fired over a full game")
	}
}

// --- matrix #11: event-derived box conserves against the live tally ----------
//
// The box score is now derived purely from the event stream (aggregateBoxes),
// while the live teamState.quarters tally is still accumulated for in-game
// clutch/OT decisions. This proves the two agree end-to-end: the output TeamBox
// quarters (event-derived) equal the live tally bucketed Q1–Q4/OT, and the usual
// invariants hold (quarters == points, team == Σ player rows, made ≤ attempted,
// no negatives).
func TestSimulate_EventDerivedConservation(t *testing.T) {
	b := richBundle()
	teamByPID := map[int]int{}
	for _, p := range b.Players {
		teamByPID[p.PID] = p.TeamID
	}
	gr, _, visitor, home := simGame(b, b.Schedule[0], rng.New(1988))

	for ti, tb := range gr.TeamBoxes {
		live := visitor
		teamID := gr.VisitorTeamID
		if ti == 1 {
			live, teamID = home, gr.HomeTeamID
		}

		// Event-derived quarters must equal the live possession-time tally exactly,
		// including overtime periods.
		wantQ := live.quarters
		gotQ := append([]int{tb.Q1, tb.Q2, tb.Q3, tb.Q4}, tb.OT...)
		for len(gotQ) > 0 && gotQ[len(gotQ)-1] == 0 && len(gotQ) > len(wantQ) {
			gotQ = gotQ[:len(gotQ)-1] // trim trailing unreached regulation quarters
		}
		if len(gotQ) < len(wantQ) {
			t.Fatalf("team %d: event-derived quarters %v shorter than live tally %v", teamID, gotQ, wantQ)
		}
		for i, want := range wantQ {
			if gotQ[i] != want {
				t.Errorf("team %d Q%d: event-derived %d != live tally %d", teamID, i+1, gotQ[i], want)
			}
		}
		for i := len(wantQ); i < len(gotQ); i++ {
			if gotQ[i] != 0 {
				t.Errorf("team %d: event-derived quarter %d = %d past live tally (want 0)", teamID, i+1, gotQ[i])
			}
		}

		// team == Σ player rows; made ≤ attempted; no negatives.
		var sum result.TeamBox
		for _, pb := range gr.PlayerBoxes {
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
			sum.GameSTL += pb.GameSTL
			sum.GameBLK += pb.GameBLK
			sum.GameTOV += pb.GameTOV
			sum.GamePF += pb.GamePF
			sum.GameORB += pb.GameORB
			sum.GameDRB += pb.GameDRB
		}
		if sum.Game2GM != tb.Game2GM || sum.Game3GM != tb.Game3GM || sum.GameFTM != tb.GameFTM ||
			sum.Game2GA != tb.Game2GA || sum.Game3GA != tb.Game3GA || sum.GameFTA != tb.GameFTA ||
			sum.GameSTL != tb.GameSTL || sum.GameBLK != tb.GameBLK || sum.GameTOV != tb.GameTOV ||
			sum.GamePF != tb.GamePF || sum.GameORB != tb.GameORB || sum.GameDRB != tb.GameDRB {
			t.Errorf("team %d: team box != sum of player rows", teamID)
		}

		// quarters == points.
		pts := 2*tb.Game2GM + 3*tb.Game3GM + tb.GameFTM
		q := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
		for _, ot := range tb.OT {
			q += ot
		}
		if q != pts {
			t.Errorf("team %d: quarters %d != points %d", teamID, q, pts)
		}
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
