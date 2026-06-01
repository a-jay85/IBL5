package backup

import (
	"errors"
	"fmt"
	"strings"
	"testing"
)

// Slot/record builders restate the .sco offsets independently of sco.go's
// constants, so the tests cross-check the offset map.
func putRJ(buf []byte, off, w, v int) { copy(buf[off:], fmt.Sprintf("%*d", w, v)) }
func putStr(buf []byte, off int, s string) {
	copy(buf[off:], s)
}

type scoSlotData struct {
	name, pos                                                         string
	pid, min, twoGM, twoGA, ftm, fta, threeGM, threeGA, orb, drb, ast int
	stl, tov, blk, pf                                                 int
}

func buildSlot(s scoSlotData) []byte {
	b := []byte(strings.Repeat(" ", 53))
	putStr(b, 0, s.name)
	putStr(b, 16, s.pos)
	putRJ(b, 18, 6, s.pid)
	putRJ(b, 24, 2, s.min)
	putRJ(b, 26, 2, s.twoGM)
	putRJ(b, 28, 3, s.twoGA)
	putRJ(b, 31, 2, s.ftm)
	putRJ(b, 33, 2, s.fta)
	putRJ(b, 35, 2, s.threeGM)
	putRJ(b, 37, 2, s.threeGA)
	putRJ(b, 39, 2, s.orb)
	putRJ(b, 41, 2, s.drb)
	putRJ(b, 43, 2, s.ast)
	putRJ(b, 45, 2, s.stl)
	putRJ(b, 47, 2, s.tov)
	putRJ(b, 49, 2, s.blk)
	putRJ(b, 51, 2, s.pf)
	return b
}

func buildGameInfo(visTIDRaw, homeTIDRaw, monthRaw, dayRaw int, visQ, homeQ [5]int) []byte {
	b := []byte(strings.Repeat(" ", 58))
	putRJ(b, 0, 2, monthRaw)
	putRJ(b, 2, 2, dayRaw)
	putRJ(b, 6, 2, visTIDRaw)
	putRJ(b, 8, 2, homeTIDRaw)
	for i, q := range visQ {
		putRJ(b, 28+i*3, 3, q)
	}
	for i, q := range homeQ {
		putRJ(b, 43+i*3, 3, q)
	}
	return b
}

// buildRecord assembles a 2,000-byte record from game info and up to 30 slots
// (missing trailing slots stay blank = empty).
func buildRecord(gi []byte, slots [][]byte) string {
	b := []byte(strings.Repeat(" ", 2000))
	copy(b[0:], gi)
	for i, s := range slots {
		copy(b[58+i*53:], s)
	}
	return string(b)
}

func scoHeader() string { return strings.Repeat(" ", scoHeaderSize) }

// Row #2: ReadSco parses a multi-game corpus (with a padding record between the
// two real games) into the right game/box counts, scores, per-stat values, and
// 2-point-only semantics (Σ 2*TwoGM + FTM + 3*ThreeGM == the header score).
func TestReadSco_ParsesGames(t *testing.T) {
	// Game A: visitor team raw 23 (-> 24), home raw 8 (-> 9), Nov 2.
	visPlayer := scoSlotData{name: "Vis One", pos: "PG", pid: 6328, min: 30, twoGM: 2, twoGA: 5, ftm: 5, fta: 6, threeGM: 2, threeGA: 4, orb: 1, drb: 2, ast: 3, stl: 1, tov: 2, blk: 0, pf: 3}  // 2*2+5+3*2 = 15
	homePlayer := scoSlotData{name: "Home One", pos: "C", pid: 4159, min: 33, twoGM: 3, twoGA: 7, ftm: 2, fta: 2, threeGM: 1, threeGA: 3, orb: 2, drb: 5, ast: 1, stl: 0, tov: 1, blk: 1, pf: 4} // 2*3+2+3 = 11
	visTotal := scoSlotData{name: "Vis Team", pid: 0, twoGM: 2, ftm: 5, threeGM: 2}
	homeTotal := scoSlotData{name: "Home Team", pid: 0, twoGM: 3, ftm: 2, threeGM: 1}

	slots := make([][]byte, 30)
	for i := range slots {
		slots[i] = []byte(strings.Repeat(" ", 53))
	}
	slots[0] = buildSlot(visPlayer)
	slots[14] = buildSlot(visTotal)   // visitor team total
	slots[15] = buildSlot(homePlayer) // first home slot
	slots[29] = buildSlot(homeTotal)  // home team total
	giA := buildGameInfo(23, 8, 1, 1, [5]int{4, 4, 4, 3, 0}, [5]int{3, 3, 3, 2, 0})
	gameA := buildRecord(giA, slots)

	padding := buildRecord(buildGameInfo(0, 0, 0, 0, [5]int{}, [5]int{}), make([][]byte, 30))

	// Game B: distinct teams, simple single visitor player.
	bVis := scoSlotData{name: "B Vis", pos: "SF", pid: 100, min: 20, twoGM: 5, ftm: 0, threeGM: 0} // 10
	slotsB := make([][]byte, 30)
	for i := range slotsB {
		slotsB[i] = []byte(strings.Repeat(" ", 53))
	}
	slotsB[0] = buildSlot(bVis)
	giB := buildGameInfo(4, 6, 1, 2, [5]int{2, 2, 3, 3, 0}, [5]int{0, 0, 0, 0, 0})
	gameB := buildRecord(giB, slotsB)

	data := scoHeader() + gameA + padding + gameB

	games, err := ReadSco(strings.NewReader(data))
	if err != nil {
		t.Fatalf("ReadSco: %v", err)
	}
	if len(games) != 2 {
		t.Fatalf("game count = %d, want 2 (padding record must be skipped)", len(games))
	}

	g := games[0]
	if g.VisitorTeamID != 24 || g.HomeTeamID != 9 {
		t.Errorf("game0 teams = %d/%d, want 24/9", g.VisitorTeamID, g.HomeTeamID)
	}
	if g.VisitorScore != 15 || g.HomeScore != 11 {
		t.Errorf("game0 score = %d-%d, want 15-11", g.VisitorScore, g.HomeScore)
	}
	if g.Date != "11-02" {
		t.Errorf("game0 date = %q, want 11-02", g.Date)
	}
	if g.GameType != 0 {
		t.Errorf("game0 GameType = %d, want 0 (.sco has no game type)", g.GameType)
	}
	// 4 non-empty slots: visitor player + visitor total + home player + home total.
	if len(g.Boxes) != 4 {
		t.Fatalf("game0 boxes = %d, want 4", len(g.Boxes))
	}

	var vp *ScoBox
	visPts, homePts := 0, 0
	for i := range g.Boxes {
		b := &g.Boxes[i]
		if b.PlayerID == 6328 {
			vp = b
		}
		if b.PlayerID != 0 { // exclude team totals
			pts := 2*b.TwoGM + b.FTM + 3*b.ThreeGM
			if b.TeamID == g.VisitorTeamID {
				visPts += pts
			} else {
				homePts += pts
			}
		}
	}
	if vp == nil {
		t.Fatal("visitor player pid 6328 not found")
	}
	if vp.TeamID != 24 || vp.Min != 30 || vp.TwoGM != 2 || vp.TwoGA != 5 || vp.FTM != 5 || vp.ThreeGM != 2 || vp.PF != 3 {
		t.Errorf("visitor player box = %+v", *vp)
	}
	// 2-point-only reconciliation: player points must equal the header score.
	if visPts != g.VisitorScore || homePts != g.HomeScore {
		t.Errorf("reconstructed pts vis=%d home=%d, want %d/%d", visPts, homePts, g.VisitorScore, g.HomeScore)
	}

	if games[1].VisitorTeamID != 5 || games[1].HomeTeamID != 7 || games[1].VisitorScore != 10 {
		t.Errorf("game1 = %+v", games[1])
	}
}

// Row #3: a non-numeric slot field yields ErrBadField; a truncated final record
// yields ErrShortRecord. Neither panics.
func TestReadSco_Malformed(t *testing.T) {
	slots := make([][]byte, 30)
	for i := range slots {
		slots[i] = []byte(strings.Repeat(" ", 53))
	}
	bad := buildSlot(scoSlotData{name: "Bad One", pid: 7})
	copy(bad[26:28], "XY") // non-numeric 2GM
	slots[0] = bad
	rec := buildRecord(buildGameInfo(1, 2, 1, 1, [5]int{}, [5]int{}), slots)

	_, err := ReadSco(strings.NewReader(scoHeader() + rec))
	if !errors.Is(err, ErrBadField) {
		t.Errorf("bad field: err = %v, want ErrBadField", err)
	}

	// Truncated: header + a partial record.
	_, err = ReadSco(strings.NewReader(scoHeader() + strings.Repeat(" ", 1500)))
	if !errors.Is(err, ErrShortRecord) {
		t.Errorf("truncated: err = %v, want ErrShortRecord", err)
	}

	// Header itself too short.
	_, err = ReadSco(strings.NewReader("not a real sco"))
	if !errors.Is(err, ErrShortRecord) {
		t.Errorf("short header: err = %v, want ErrShortRecord", err)
	}
}

// Row #4: header-only input and an all-padding record both yield an empty slice
// with no panic and no spurious game.
func TestReadSco_EmptyAndPadding(t *testing.T) {
	games, err := ReadSco(strings.NewReader(scoHeader()))
	if err != nil {
		t.Fatalf("header-only: %v", err)
	}
	if len(games) != 0 {
		t.Errorf("header-only games = %d, want 0", len(games))
	}

	padding := buildRecord(buildGameInfo(0, 0, 0, 0, [5]int{}, [5]int{}), make([][]byte, 30))
	games, err = ReadSco(strings.NewReader(scoHeader() + padding + padding))
	if err != nil {
		t.Fatalf("padding: %v", err)
	}
	if len(games) != 0 {
		t.Errorf("all-padding games = %d, want 0", len(games))
	}
}
