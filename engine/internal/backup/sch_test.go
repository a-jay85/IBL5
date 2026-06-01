package backup

import (
	"errors"
	"strings"
	"testing"
)

// putSlot writes a 10-byte schedule record at game slot index n into buf.
func putSlot(buf []byte, n int, record string) {
	copy(buf[n*10:], record)
}

// Row #9: ReadSch parses a synthetic 80,000-byte schedule. Games live in date
// group 0 (October, day 1); visitor/home split on the trailing 2 digits, scores
// on the trailing 3; empty slots are skipped; played follows score > 0. A wrong
// total size yields ErrBadSize.
func TestReadSch_Parses(t *testing.T) {
	buf := []byte(strings.Repeat(" ", schFileSize))

	// Game 0: visitor 24, home 09, scores 132 / 131 (played).
	putSlot(buf, 0, "2409132131")
	// Game 1: visitor 5, home 07, unplayed (scores field is a bare "0").
	putSlot(buf, 1, "507 0     ")
	// Game 2: visitor 12, home 03, scores 99 / 101 (played).
	putSlot(buf, 2, "120399101 ")
	// Game 3: an explicit empty slot — must be skipped.
	putSlot(buf, 3, schEmptyRecord)

	games, err := ReadSch(strings.NewReader(string(buf)))
	if err != nil {
		t.Fatalf("ReadSch: %v", err)
	}
	if len(games) != 3 {
		t.Fatalf("games = %d, want 3 (empty slot skipped)", len(games))
	}

	g0 := games[0]
	if g0.VisitorTeamID != 24 || g0.HomeTeamID != 9 {
		t.Errorf("game0 teams = %d/%d, want 24/9", g0.VisitorTeamID, g0.HomeTeamID)
	}
	if g0.Month != 10 || g0.Day != 1 {
		t.Errorf("game0 date = %d/%d, want Oct 1 (10/1)", g0.Month, g0.Day)
	}
	if g0.VisitorScore != 132 || g0.HomeScore != 131 || !g0.Played {
		t.Errorf("game0 score = %d-%d played=%v, want 132-131 played", g0.VisitorScore, g0.HomeScore, g0.Played)
	}

	g1 := games[1]
	if g1.VisitorTeamID != 5 || g1.HomeTeamID != 7 || g1.VisitorScore != 0 || g1.HomeScore != 0 || g1.Played {
		t.Errorf("game1 = %+v, want vis5 home7 unplayed", g1)
	}

	g2 := games[2]
	if g2.VisitorTeamID != 12 || g2.HomeTeamID != 3 || g2.VisitorScore != 99 || g2.HomeScore != 101 || !g2.Played {
		t.Errorf("game2 = %+v, want vis12 home3 99-101 played", g2)
	}
}

func TestReadSch_BadSize(t *testing.T) {
	_, err := ReadSch(strings.NewReader(strings.Repeat(" ", schFileSize-1)))
	if !errors.Is(err, ErrBadSize) {
		t.Errorf("err = %v, want ErrBadSize", err)
	}
}
