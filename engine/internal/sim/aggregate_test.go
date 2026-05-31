package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// twoTeamMeta builds visitor (team 7: PIDs 1,2,3) and home (team 3: PIDs 4,5,6)
// roster metadata for the aggregator, each player at a stub position with 20 min.
func twoTeamMeta() (rosterMeta, rosterMeta) {
	visitor := rosterMeta{teamID: 7, isHome: false, players: []playerMeta{
		{PID: 1, Pos: "PG", GameMIN: 20}, {PID: 2, Pos: "SG", GameMIN: 20}, {PID: 3, Pos: "SF", GameMIN: 20},
	}}
	home := rosterMeta{teamID: 3, isHome: true, players: []playerMeta{
		{PID: 4, Pos: "PG", GameMIN: 20}, {PID: 5, Pos: "SG", GameMIN: 20}, {PID: 6, Pos: "SF", GameMIN: 20},
	}}
	return visitor, home
}

func boxOf(boxes []result.PlayerBox, pid int) *result.PlayerBox {
	for i := range boxes {
		if boxes[i].PID == pid {
			return &boxes[i]
		}
	}
	return nil
}

// --- matrix #4: every event kind maps to the right field / ID ---------------

func TestAggregate_EventKindMapping(t *testing.T) {
	visitor, home := twoTeamMeta()
	// Visitor (team 7) on offense; PID 1 the ball-handler, defenders are home PIDs.
	events := []result.Event{
		{Kind: result.EventShotAttempt, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		{Kind: result.EventShotMake, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		{Kind: result.EventShotAttempt, Period: 1, TeamID: 7, PlayerID: 2, ShotType: result.ShotThree},
		{Kind: result.EventShotMake, Period: 1, TeamID: 7, PlayerID: 2, ShotType: result.ShotThree},
		{Kind: result.EventRebound, Period: 1, TeamID: 7, PlayerID: 3, OffensiveRebound: true},
		{Kind: result.EventRebound, Period: 1, TeamID: 3, PlayerID: 4, OffensiveRebound: false},
		{Kind: result.EventTurnover, Period: 1, TeamID: 7, PlayerID: 1},
		{Kind: result.EventFoul, Period: 1, TeamID: 3, PlayerID: 5},
		{Kind: result.EventSteal, Period: 1, TeamID: 7, PlayerID: 1, DefenderID: 4},
		{Kind: result.EventBlock, Period: 1, TeamID: 7, PlayerID: 1, DefenderID: 6},
		{Kind: result.EventFreeThrow, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotFreeThrow, FTAttempts: 2, FTMade: 2},
	}
	boxes, _ := aggregateBoxes(events, visitor, home)

	p1 := boxOf(boxes, 1)
	if p1.Game2GA != 1 || p1.Game2GM != 1 {
		t.Errorf("PID1 2pt: GA=%d GM=%d, want 1/1", p1.Game2GA, p1.Game2GM)
	}
	if p1.GameTOV != 1 {
		t.Errorf("PID1 TOV = %d, want 1", p1.GameTOV)
	}
	if p1.GameFTA != 2 || p1.GameFTM != 2 {
		t.Errorf("PID1 FT: FTA=%d FTM=%d, want 2/2", p1.GameFTA, p1.GameFTM)
	}
	if p2 := boxOf(boxes, 2); p2.Game3GA != 1 || p2.Game3GM != 1 {
		t.Errorf("PID2 3pt: GA=%d GM=%d, want 1/1", p2.Game3GA, p2.Game3GM)
	}
	if p3 := boxOf(boxes, 3); p3.GameORB != 1 || p3.GameDRB != 0 {
		t.Errorf("PID3 ORB/DRB = %d/%d, want 1/0", p3.GameORB, p3.GameDRB)
	}
	if p4 := boxOf(boxes, 4); p4.GameDRB != 1 || p4.GameSTL != 1 {
		t.Errorf("PID4 (defender) DRB/STL = %d/%d, want 1/1", p4.GameDRB, p4.GameSTL)
	}
	if p5 := boxOf(boxes, 5); p5.GamePF != 1 {
		t.Errorf("PID5 (fouler) PF = %d, want 1", p5.GamePF)
	}
	if p6 := boxOf(boxes, 6); p6.GameBLK != 1 {
		t.Errorf("PID6 (blocker) BLK = %d, want 1", p6.GameBLK)
	}
	// AST is always 0.
	for _, b := range boxes {
		if b.GameAST != 0 {
			t.Errorf("PID%d AST = %d, want 0 (commentary-only)", b.PID, b.GameAST)
		}
	}
	// Output order is visitor then home, in roster order.
	wantOrder := []int{1, 2, 3, 4, 5, 6}
	for i, w := range wantOrder {
		if boxes[i].PID != w {
			t.Errorf("box[%d] PID = %d, want %d (visitor-first roster order)", i, boxes[i].PID, w)
		}
	}
}

// --- matrix #5: a missed shot yields an attempt without a make ---------------

func TestAggregate_MissIsAttemptWithoutMake(t *testing.T) {
	visitor, home := twoTeamMeta()
	events := []result.Event{
		{Kind: result.EventShotAttempt, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		{Kind: result.EventShotMiss, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
	}
	boxes, _ := aggregateBoxes(events, visitor, home)
	p1 := boxOf(boxes, 1)
	if p1.Game2GA != 1 {
		t.Errorf("Game2GA = %d, want 1", p1.Game2GA)
	}
	if p1.Game2GM != 0 {
		t.Errorf("Game2GM = %d, want 0 (a miss is not a make)", p1.Game2GM)
	}
}

// --- matrix #6: a DNP player (no events) is an all-zero row with Pos/GameMIN --

func TestAggregate_DNPAllZeroRow(t *testing.T) {
	visitor := rosterMeta{teamID: 7, players: []playerMeta{{PID: 1, Pos: "PG", GameMIN: 30}, {PID: 9, Pos: "C", GameMIN: 0}}}
	home := rosterMeta{teamID: 3, isHome: true, players: []playerMeta{{PID: 4, Pos: "PG", GameMIN: 30}}}
	events := []result.Event{
		{Kind: result.EventShotAttempt, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
	}
	boxes, _ := aggregateBoxes(events, visitor, home)

	dnp := boxOf(boxes, 9)
	if dnp == nil {
		t.Fatal("DNP player 9 missing from output")
	}
	if *dnp != (result.PlayerBox{PID: 9, Pos: "C", GameMIN: 0}) {
		t.Errorf("DNP row should be all-zero with Pos/GameMIN, got %+v", *dnp)
	}
}

// --- matrix #7: an and-one aggregates to make + FT + points ------------------

func TestAggregate_AndOne(t *testing.T) {
	visitor, home := twoTeamMeta()
	events := []result.Event{
		{Kind: result.EventShotAttempt, Period: 2, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		{Kind: result.EventShotMake, Period: 2, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		{Kind: result.EventFreeThrow, Period: 2, TeamID: 7, PlayerID: 1, ShotType: result.ShotFreeThrow, FTAttempts: 1, FTMade: 1},
	}
	boxes, teams := aggregateBoxes(events, visitor, home)
	p1 := boxOf(boxes, 1)
	if p1.Game2GM != 1 || p1.Game2GA != 1 || p1.GameFTM != 1 || p1.GameFTA != 1 {
		t.Errorf("and-one row = %+v, want 2GM/2GA/FTM/FTA = 1/1/1/1", *p1)
	}
	// Visitor scored 2 (FG) + 1 (FT) = 3 in Q2.
	vis := teams[0]
	if vis.Q2 != 3 {
		t.Errorf("visitor Q2 = %d, want 3 (and-one)", vis.Q2)
	}
	pts := 2*vis.Game2GM + 3*vis.Game3GM + vis.GameFTM
	if pts != 3 {
		t.Errorf("visitor points = %d, want 3", pts)
	}
}

// --- matrix #8: steal/block credit the defender on the non-offense team ------

func TestAggregate_StealBlockCreditDefenderTeam(t *testing.T) {
	visitor, home := twoTeamMeta()
	// Offense is visitor (team 7); defenders 4 and 6 are on home (team 3).
	events := []result.Event{
		{Kind: result.EventSteal, Period: 1, TeamID: 7, PlayerID: 1, DefenderID: 4},
		{Kind: result.EventBlock, Period: 1, TeamID: 7, PlayerID: 2, DefenderID: 6},
	}
	boxes, teams := aggregateBoxes(events, visitor, home)

	// Credits land on the home defenders, not the visitor offense.
	if boxOf(boxes, 4).GameSTL != 1 || boxOf(boxes, 6).GameBLK != 1 {
		t.Errorf("defender credits wrong: STL(4)=%d BLK(6)=%d", boxOf(boxes, 4).GameSTL, boxOf(boxes, 6).GameBLK)
	}
	for _, pid := range []int{1, 2, 3} { // visitor offense gets nothing
		if b := boxOf(boxes, pid); b.GameSTL != 0 || b.GameBLK != 0 {
			t.Errorf("offense PID%d wrongly credited STL=%d BLK=%d", pid, b.GameSTL, b.GameBLK)
		}
	}
	// Team rollups: home (defense) carries the STL/BLK; visitor carries none.
	if teams[0].GameSTL != 0 || teams[0].GameBLK != 0 {
		t.Errorf("visitor team STL/BLK = %d/%d, want 0/0", teams[0].GameSTL, teams[0].GameBLK)
	}
	if teams[1].GameSTL != 1 || teams[1].GameBLK != 1 {
		t.Errorf("home team STL/BLK = %d/%d, want 1/1", teams[1].GameSTL, teams[1].GameBLK)
	}
}

// --- matrix #9: GameFTM never exceeds GameFTA (1-of-2 trip) ------------------

func TestAggregate_FTMNeverExceedsFTA(t *testing.T) {
	visitor, home := twoTeamMeta()
	events := []result.Event{
		{Kind: result.EventFreeThrow, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotFreeThrow, FTAttempts: 2, FTMade: 1},
	}
	boxes, _ := aggregateBoxes(events, visitor, home)
	p1 := boxOf(boxes, 1)
	if p1.GameFTA != 2 || p1.GameFTM != 1 {
		t.Errorf("FT 1-of-2: FTA=%d FTM=%d, want 2/1", p1.GameFTA, p1.GameFTM)
	}
	if p1.GameFTM > p1.GameFTA {
		t.Errorf("FTM %d > FTA %d", p1.GameFTM, p1.GameFTA)
	}
}

// --- matrix #10: team rollup == Σ player rows; quarters bucket by period -----

func TestAggregate_TeamRollupAndQuarterBucketing(t *testing.T) {
	visitor, home := twoTeamMeta()
	events := []result.Event{
		// Q1: PID1 makes a 2 (2 pts).
		{Kind: result.EventShotAttempt, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		{Kind: result.EventShotMake, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		// Q3: PID2 makes a 3 (3 pts) — Q2 is unscored, must read 0.
		{Kind: result.EventShotAttempt, Period: 3, TeamID: 7, PlayerID: 2, ShotType: result.ShotThree},
		{Kind: result.EventShotMake, Period: 3, TeamID: 7, PlayerID: 2, ShotType: result.ShotThree},
		// OT (period 5): PID1 hits 2 FTs (2 pts).
		{Kind: result.EventFreeThrow, Period: 5, TeamID: 7, PlayerID: 1, ShotType: result.ShotFreeThrow, FTAttempts: 2, FTMade: 2},
	}
	boxes, teams := aggregateBoxes(events, visitor, home)

	vis := teams[0]
	if vis.Q1 != 2 || vis.Q2 != 0 || vis.Q3 != 3 || vis.Q4 != 0 {
		t.Errorf("visitor quarters = %d/%d/%d/%d, want 2/0/3/0", vis.Q1, vis.Q2, vis.Q3, vis.Q4)
	}
	if len(vis.OT) != 1 || vis.OT[0] != 2 {
		t.Errorf("visitor OT = %v, want [2]", vis.OT)
	}
	// Team totals == Σ player rows.
	var sum result.TeamBox
	for _, pid := range []int{1, 2, 3} {
		b := boxOf(boxes, pid)
		sum.Game2GM += b.Game2GM
		sum.Game3GM += b.Game3GM
		sum.GameFTM += b.GameFTM
	}
	if sum.Game2GM != vis.Game2GM || sum.Game3GM != vis.Game3GM || sum.GameFTM != vis.GameFTM {
		t.Errorf("team rollup != Σ player rows: got %+v vs sum %+v", vis, sum)
	}
	// quarters sum to points.
	q := vis.Q1 + vis.Q2 + vis.Q3 + vis.Q4
	for _, ot := range vis.OT {
		q += ot
	}
	if pts := 2*vis.Game2GM + 3*vis.Game3GM + vis.GameFTM; q != pts {
		t.Errorf("quarters %d != points %d", q, pts)
	}
	// Home team scored nothing: empty (non-nil) OT, zero quarters.
	if home := teams[1]; home.Q1 != 0 || len(home.OT) != 0 {
		t.Errorf("home should be scoreless: %+v", home)
	}
}

// --- 0-made free throw still extends the quarter slice ------------------------
//
// The live path calls addPeriodPoints on every FT trip (even 0-made), so a trip
// whose only scoring event in a period misses both still "reaches" that period.
// The aggregator must mirror this or the OT slice length drifts from the golden.
func TestAggregate_ZeroMadeFreeThrowReachesPeriod(t *testing.T) {
	visitor, home := twoTeamMeta()
	events := []result.Event{
		{Kind: result.EventShotAttempt, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		{Kind: result.EventShotMake, Period: 1, TeamID: 7, PlayerID: 1, ShotType: result.ShotTwoPoint},
		// OT with a 0-made trip: 0 points, but the OT period is reached.
		{Kind: result.EventFreeThrow, Period: 5, TeamID: 7, PlayerID: 1, ShotType: result.ShotFreeThrow, FTAttempts: 2, FTMade: 0},
	}
	_, teams := aggregateBoxes(events, visitor, home)
	vis := teams[0]
	if len(vis.OT) != 1 || vis.OT[0] != 0 {
		t.Errorf("visitor OT = %v, want [0] (0-made trip still reaches the period)", vis.OT)
	}
}
