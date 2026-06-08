package backup

import (
	"errors"
	"fmt"
	"strings"
	"testing"
)

// plrField writes value v left-justified at literal offset off into buf. The
// offsets here are restated independently of plr.go's named constants so the
// test cross-checks the offset map rather than echoing it.
func plrField(buf []byte, off int, v string) {
	copy(buf[off:], v)
}

// newPlrRecord builds a 607-byte player record with the identity + a handful of
// ratings populated at their literal offsets. Unset fields stay spaces (→ 0).
func newPlrRecord(ordinal, pid, tid, age int, name, pos string, ftp, oo, td int) string {
	buf := []byte(strings.Repeat(" ", plrRecordSize))
	plrField(buf, 0, itoaPad(ordinal, 4)) // ordinal
	plrField(buf, 4, name)                // name (left-justified; reader trims)
	plrField(buf, 36, itoaPad(age, 2))    // age
	plrField(buf, 38, itoaPad(pid, 6))    // pid
	plrField(buf, 44, itoaPad(tid, 2))    // tid
	plrField(buf, 50, pos)                // position
	plrField(buf, 137, "1")               // canPlayInGame
	plrField(buf, 564, itoaPad(ftp, 3))   // ratingFTP
	plrField(buf, 591, itoaPad(oo, 2))    // ratingOO
	plrField(buf, 605, itoaPad(td, 2))    // ratingTD
	return string(buf)
}

// itoaPad renders v right-justified in a width-w field, matching the .plr
// space-padded right-justified integer convention.
func itoaPad(v, w int) string {
	return fmt.Sprintf("%*d", w, v)
}

// Row #5: ReadPlr parses fixed-width CRLF records into the expected players and
// slices identity + ratings at the transcribed offsets.
func TestReadPlr_ParsesFields(t *testing.T) {
	r1 := newPlrRecord(1, 2421, 1, 32, "Magic Johnson", "PG", 86, 6, 8)
	r2 := newPlrRecord(2, 5259, 2, 38, "Dwyane Wade", "SG", 73, 7, 7)
	data := r1 + "\r\n" + r2 + "\r\n"

	players, err := ReadPlr(strings.NewReader(data))
	if err != nil {
		t.Fatalf("ReadPlr: %v", err)
	}
	if len(players) != 2 {
		t.Fatalf("player count = %d, want 2", len(players))
	}
	p := players[0]
	if p.PID != 2421 || p.Name != "Magic Johnson" || p.Pos != "PG" || p.TeamID != 1 || p.Age != 32 {
		t.Errorf("player0 identity = %+v", p)
	}
	if p.RatingFTP != 86 || p.RatingOO != 6 || p.RatingTD != 8 {
		t.Errorf("player0 ratings: FTP=%d OO=%d TD=%d, want 86/6/8", p.RatingFTP, p.RatingOO, p.RatingTD)
	}
	if p.CanPlayInGame != 1 {
		t.Errorf("CanPlayInGame = %d, want 1", p.CanPlayInGame)
	}
	if players[1].PID != 5259 || players[1].Name != "Dwyane Wade" {
		t.Errorf("player1 = %+v", players[1])
	}
}

// Row #5 (encoding): a CP1252 accented name decodes to UTF-8.
func TestReadPlr_CP1252Name(t *testing.T) {
	// 0xE9 is é in CP1252/Latin-1.
	name := "L\xf3pez" // ó
	rec := newPlrRecord(1, 100, 1, 25, name, "SF", 70, 5, 5)
	players, err := ReadPlr(strings.NewReader(rec + "\r\n"))
	if err != nil {
		t.Fatalf("ReadPlr: %v", err)
	}
	if players[0].Name != "López" {
		t.Errorf("name = %q, want %q", players[0].Name, "López")
	}
}

// Row #6: a non-numeric value in a numeric field yields ErrBadField; a
// too-short line (< 200 bytes) is skipped, not errored.
func TestReadPlr_BadFieldAndShortLine(t *testing.T) {
	rec := []byte(newPlrRecord(1, 100, 1, 25, "Bad Guy", "PG", 70, 5, 5))
	copy(rec[591:593], "XY") // ratingOO non-numeric
	short := "   1 short line under two hundred bytes"
	data := short + "\r\n" + string(rec) + "\r\n"

	_, err := ReadPlr(strings.NewReader(data))
	if !errors.Is(err, ErrBadField) {
		t.Fatalf("err = %v, want ErrBadField", err)
	}
	if !strings.Contains(err.Error(), "591") {
		t.Errorf("error should name offset 591: %v", err)
	}

	// The short line alone parses to zero players, no error.
	players, err := ReadPlr(strings.NewReader(short + "\r\n"))
	if err != nil {
		t.Fatalf("short-only ReadPlr: %v", err)
	}
	if len(players) != 0 {
		t.Errorf("short-only players = %d, want 0", len(players))
	}
}

// Row #7: version-stability. Phase 0 verified the .plr column offsets are
// IDENTICAL across JSB 5.60 and 5.99 (no version byte exists), so a record laid
// out at these offsets parses regardless of which version produced it. This
// asserts that documented finding: a "5.99-style" record (same offsets,
// different data) reads correctly with no version gate.
func TestReadPlr_VersionStableOffsets(t *testing.T) {
	rec599 := newPlrRecord(1, 7, 1, 29, "Aaron McKie", "PG", 0, 7, 7)
	players, err := ReadPlr(strings.NewReader(rec599 + "\r\n"))
	if err != nil {
		t.Fatalf("ReadPlr: %v", err)
	}
	if len(players) != 1 || players[0].PID != 7 || players[0].Name != "Aaron McKie" {
		t.Fatalf("5.99-layout record parsed as %+v", players)
	}
	if players[0].RatingFTP != 0 || players[0].RatingTD != 7 {
		t.Errorf("ratings FTP=%d TD=%d, want 0/7", players[0].RatingFTP, players[0].RatingTD)
	}
}

// Row #8: empty input and blank/CRLF-only input both yield an empty slice with
// no panic and no spurious player.
func TestReadPlr_EmptyAndBlank(t *testing.T) {
	for name, in := range map[string]string{
		"empty":     "",
		"crlf only": "\r\n\r\n\r\n",
		"spaces":    strings.Repeat(" ", 50) + "\r\n",
	} {
		players, err := ReadPlr(strings.NewReader(in))
		if err != nil {
			t.Errorf("%s: err = %v", name, err)
		}
		if len(players) != 0 {
			t.Errorf("%s: players = %d, want 0", name, len(players))
		}
	}
}

// Row 2: ReadPlr parses the static real-life / previous-season block into
// RealLifeMIN/FGA/FTA/ORB. Offsets restated literally (56/64/72/84) so the test
// cross-checks the map rather than echoing plr.go's constants.
func TestReadPlr_RealLifeBlock(t *testing.T) {
	buf := []byte(newPlrRecord(1, 100, 1, 25, "Volume Shooter", "SG", 75, 6, 5))
	plrField(buf, 56, itoaPad(2520, 4)) // realLifeMIN (the per-48 rate divisor)
	plrField(buf, 64, itoaPad(1400, 4)) // realLifeFGA (total FG attempts)
	plrField(buf, 72, itoaPad(360, 4))  // realLifeFTA
	plrField(buf, 84, itoaPad(120, 4))  // realLifeORB

	players, err := ReadPlr(strings.NewReader(string(buf) + "\r\n"))
	if err != nil {
		t.Fatalf("ReadPlr: %v", err)
	}
	p := players[0]
	if p.RealLifeMIN != 2520 || p.RealLifeFGA != 1400 || p.RealLifeFTA != 360 || p.RealLifeORB != 120 {
		t.Errorf("real-life block = MIN%d FGA%d FTA%d ORB%d, want 2520/1400/360/120",
			p.RealLifeMIN, p.RealLifeFGA, p.RealLifeFTA, p.RealLifeORB)
	}
}

// Row 3: ReadPlr parses the per-player in-season GP/DRB/AST (the Branch-B team-rate
// inputs) at the transcribed offsets 148/184/188, independently of plr.go's constants.
func TestReadPlr_SeasonBlock(t *testing.T) {
	buf := []byte(newPlrRecord(1, 100, 1, 25, "Rebounder", "C", 70, 5, 5))
	plrField(buf, 148, itoaPad(70, 4))  // season GP
	plrField(buf, 184, itoaPad(420, 4)) // season DRB
	plrField(buf, 188, itoaPad(210, 4)) // season AST

	players, err := ReadPlr(strings.NewReader(string(buf) + "\r\n"))
	if err != nil {
		t.Fatalf("ReadPlr: %v", err)
	}
	p := players[0]
	if p.SeasonGP != 70 || p.SeasonDRB != 420 || p.SeasonAST != 210 {
		t.Errorf("season block = GP%d DRB%d AST%d, want 70/420/210", p.SeasonGP, p.SeasonDRB, p.SeasonAST)
	}
}

// Row 5 (negative-path): a non-numeric season field yields ErrBadField naming the
// offset — the same guard every numeric field uses, now over the season block.
func TestReadPlr_SeasonBadField(t *testing.T) {
	buf := []byte(newPlrRecord(1, 100, 1, 25, "Bad Season", "PG", 70, 5, 5))
	copy(buf[184:188], "ZZZZ") // season DRB non-numeric

	_, err := ReadPlr(strings.NewReader(string(buf) + "\r\n"))
	if !errors.Is(err, ErrBadField) {
		t.Fatalf("err = %v, want ErrBadField", err)
	}
	if !strings.Contains(err.Error(), "184") {
		t.Errorf("error should name offset 184: %v", err)
	}
}

// Row 3: a non-numeric real-life field yields ErrBadField naming the offset
// (boundary — the same guard every numeric field uses, now over the new block).
func TestReadPlr_RealLifeBadField(t *testing.T) {
	buf := []byte(newPlrRecord(1, 100, 1, 25, "Bad RL", "PG", 70, 5, 5))
	plrField(buf, 56, itoaPad(2520, 4))
	copy(buf[64:68], "XXXX") // realLifeFGA non-numeric

	_, err := ReadPlr(strings.NewReader(string(buf) + "\r\n"))
	if !errors.Is(err, ErrBadField) {
		t.Fatalf("err = %v, want ErrBadField", err)
	}
	if !strings.Contains(err.Error(), "64") {
		t.Errorf("error should name offset 64: %v", err)
	}
}
