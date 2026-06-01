package backup

import (
	"errors"
	"fmt"
	"io"
	"strconv"
	"strings"
)

// .sco record framing, transcribed from ibl5/classes/JsbParser/ScoFileParser.php.
// The file opens with a 1,000,000-byte metadata header (skipped), after which
// each completed game is a contiguous 2,000-byte record: a 58-byte game-info
// header + 30 player/team slots of 53 bytes + trailing padding.
const (
	scoHeaderSize   = 1_000_000 // metadata header, skipped wholesale
	scoRecordSize   = 2000
	scoGameInfoSize = 58
	scoSlotSize     = 53
	scoSlotCount    = 30
	scoVisitorSlots = 15 // slots 0..14 visitor, 15..29 home
)

// Game-info sub-offsets, from Boxscore::fillGameInfo. Team IDs and the date are
// stored 0-based and the PHP adds the constants below; we mirror that so the
// .sco team IDs agree with the 1-indexed .sch/bundle IDs (load-bearing for the
// PR9b join between .sco ground truth and the .sch-driven sim).
const (
	giMonth      = 0  // width 2, raw + 10 = calendar month base (Oct=10)
	giDay        = 2  // width 2, raw + 1
	giVisitorTID = 6  // width 2, raw + 1
	giHomeTID    = 8  // width 2, raw + 1
	giVisitorQ1  = 28 // five visitor quarter scores, width 3 each (28,31,34,37,40)
	giHomeQ1     = 43 // five home quarter scores, width 3 each (43,46,49,52,55)
)

// Player/team slot sub-offsets, from PlayerStats::fillFromBoxscoreInfoLine. The
// "field goal" slots are 2-POINT-ONLY makes/attempts: the PHP writes them
// straight into game_2gm/game_2ga with no fgm-3gm subtraction, and a real-corpus
// reconciliation confirms Σ(2*TwoGM + FTM + 3*ThreeGM) == the header score. The
// engine likewise emits 2-point-only makes, so TwoGM compares to the engine's
// 2GM directly — there is NO total-field-goal derivation.
const (
	slotName    = 0  // width 16, CP1252
	slotPos     = 16 // width 2
	slotPID     = 18 // width 6 — 0 = team-total row
	slotMin     = 24 // width 2
	slotTwoGM   = 26 // width 2 — game_2gm (2-point makes)
	slotTwoGA   = 28 // width 3 — game_2ga (2-point attempts)
	slotFTM     = 31 // width 2
	slotFTA     = 33 // width 2
	slotThreeGM = 35 // width 2
	slotThreeGA = 37 // width 2
	slotORB     = 39 // width 2
	slotDRB     = 41 // width 2
	slotAST     = 43 // width 2
	slotSTL     = 45 // width 2
	slotTOV     = 47 // width 2
	slotBLK     = 49 // width 2
	slotPF      = 51 // width 2
)

// ErrShortRecord reports a .sco input that ends mid-record (truncated header or
// a trailing partial 2,000-byte record). It names the offending record index.
var ErrShortRecord = errors.New("backup: truncated .sco record")

// ScoBox is one slot's stat line from a .sco game. TwoGM/TwoGA are 2-point-only
// (game_2gm/game_2ga), directly comparable to the engine's emitted 2GM/2GA. A
// team-total row has PlayerID == 0; TeamID is the visitor team for slots 0..14
// and the home team for slots 15..29.
type ScoBox struct {
	TeamID   int
	PlayerID int
	Pos      string
	Name     string
	Min      int
	TwoGM    int
	TwoGA    int
	FTM      int
	FTA      int
	ThreeGM  int
	ThreeGA  int
	ORB      int
	DRB      int
	AST      int
	STL      int
	TOV      int
	BLK      int
	PF       int
}

// ScoGame is one completed game decoded from a .sco record. GameType has no
// source in the 58-byte game-info header (the .sco does not store it), so it is
// always 0; it exists only to keep the ground-truth struct shape stable for
// PR9b. VisitorScore/HomeScore are the summed quarter scores.
type ScoGame struct {
	VisitorTeamID int
	HomeTeamID    int
	VisitorScore  int
	HomeScore     int
	Date          string
	GameType      int
	Boxes         []ScoBox
}

// ReadSco reads a backup .sco box-score corpus from r. It skips the
// 1,000,000-byte header, then decodes each 2,000-byte record. Records with no
// non-empty player slot are padding and are skipped (mirroring the PHP
// gameLinesProcessed > 0 gate), so the real sparse corpus does not emit phantom
// games. A truncated header or trailing partial record yields ErrShortRecord; a
// non-numeric slot field yields ErrBadField — never a panic.
func ReadSco(r io.Reader) ([]ScoGame, error) {
	data, err := io.ReadAll(r)
	if err != nil {
		return nil, fmt.Errorf("backup: read .sco: %w", err)
	}
	if len(data) < scoHeaderSize {
		return nil, fmt.Errorf("%w: data %d bytes shorter than the %d-byte header", ErrShortRecord, len(data), scoHeaderSize)
	}

	games := make([]ScoGame, 0)
	recIdx := 0
	for off := scoHeaderSize; off < len(data); off, recIdx = off+scoRecordSize, recIdx+1 {
		if off+scoRecordSize > len(data) {
			return nil, fmt.Errorf("%w: record %d at offset %d has only %d of %d bytes", ErrShortRecord, recIdx, off, len(data)-off, scoRecordSize)
		}
		rec := string(data[off : off+scoRecordSize])
		game, ok, err := decodeScoRecord(rec, recIdx)
		if err != nil {
			return nil, err
		}
		if ok {
			games = append(games, game)
		}
	}
	return games, nil
}

// decodeScoRecord decodes one 2,000-byte record. ok is false for a padding
// record (no non-empty slot), in which case game is the zero value.
func decodeScoRecord(rec string, recIdx int) (ScoGame, bool, error) {
	gi := rec[:scoGameInfoSize]

	visTID, err := scoInt(gi, giVisitorTID, 2, recIdx)
	if err != nil {
		return ScoGame{}, false, err
	}
	homeTID, err := scoInt(gi, giHomeTID, 2, recIdx)
	if err != nil {
		return ScoGame{}, false, err
	}
	monthRaw, err := scoInt(gi, giMonth, 2, recIdx)
	if err != nil {
		return ScoGame{}, false, err
	}
	dayRaw, err := scoInt(gi, giDay, 2, recIdx)
	if err != nil {
		return ScoGame{}, false, err
	}

	visScore, err := scoQuarterSum(gi, giVisitorQ1, recIdx)
	if err != nil {
		return ScoGame{}, false, err
	}
	homeScore, err := scoQuarterSum(gi, giHomeQ1, recIdx)
	if err != nil {
		return ScoGame{}, false, err
	}

	boxes := make([]ScoBox, 0, scoSlotCount)
	for i := 0; i < scoSlotCount; i++ {
		base := scoGameInfoSize + i*scoSlotSize
		slot := rec[base : base+scoSlotSize]
		name := decodeCP1252(scoSlice(slot, slotName, 16))
		if name == "" {
			continue // empty bench slot
		}
		box, err := decodeScoSlot(slot, name, i, visTID, homeTID, recIdx)
		if err != nil {
			return ScoGame{}, false, err
		}
		boxes = append(boxes, box)
	}
	if len(boxes) == 0 {
		return ScoGame{}, false, nil // padding record
	}

	// Calendar month: raw + 10, wrapping once past December (Oct=10…Sep=9). This
	// mirrors only the regular-season branch of Boxscore::fillGameInfo; the PHP's
	// playoff/HEAT/preseason month hacks need season context the .sco does not
	// carry, so the Date is a best-effort regular-season label and may be wrong
	// for playoff/HEAT records (which the .sco does not mark). Date is an opaque
	// validation label — PR9b joins games on team IDs + score, never by parsing
	// this string — so a mislabeled playoff date does not affect the harness.
	// The load-bearing fields (team IDs, scores, box stats) are always correct.
	month := monthRaw + 10
	if month > 12 {
		month -= 12
	}
	return ScoGame{
		VisitorTeamID: visTID + 1,
		HomeTeamID:    homeTID + 1,
		VisitorScore:  visScore,
		HomeScore:     homeScore,
		Date:          fmt.Sprintf("%02d-%02d", month, dayRaw+1),
		Boxes:         boxes,
	}, true, nil
}

func decodeScoSlot(slot, name string, slotIdx, visTID, homeTID, recIdx int) (ScoBox, error) {
	box := ScoBox{Name: name, Pos: strings.TrimSpace(scoSlice(slot, slotPos, 2))}
	if slotIdx < scoVisitorSlots {
		box.TeamID = visTID + 1
	} else {
		box.TeamID = homeTID + 1
	}
	fields := []struct {
		dst *int
		off int
		w   int
	}{
		{&box.PlayerID, slotPID, 6}, {&box.Min, slotMin, 2},
		{&box.TwoGM, slotTwoGM, 2}, {&box.TwoGA, slotTwoGA, 3},
		{&box.FTM, slotFTM, 2}, {&box.FTA, slotFTA, 2},
		{&box.ThreeGM, slotThreeGM, 2}, {&box.ThreeGA, slotThreeGA, 2},
		{&box.ORB, slotORB, 2}, {&box.DRB, slotDRB, 2},
		{&box.AST, slotAST, 2}, {&box.STL, slotSTL, 2},
		{&box.TOV, slotTOV, 2}, {&box.BLK, slotBLK, 2}, {&box.PF, slotPF, 2},
	}
	for _, f := range fields {
		v, err := scoInt(slot, f.off, f.w, recIdx)
		if err != nil {
			return ScoBox{}, err
		}
		*f.dst = v
	}
	return box, nil
}

func scoQuarterSum(gi string, firstOff, recIdx int) (int, error) {
	total := 0
	for q := 0; q < 5; q++ { // Q1..Q4 + OT
		v, err := scoInt(gi, firstOff+q*3, 3, recIdx)
		if err != nil {
			return 0, err
		}
		total += v
	}
	return total, nil
}

func scoSlice(s string, off, width int) string {
	if off >= len(s) {
		return ""
	}
	end := off + width
	if end > len(s) {
		end = len(s)
	}
	return s[off:end]
}

func scoInt(s string, off, width, recIdx int) (int, error) {
	t := strings.TrimSpace(scoSlice(s, off, width))
	if t == "" {
		return 0, nil
	}
	v, err := strconv.Atoi(t)
	if err != nil {
		return 0, fmt.Errorf("%w: record %d offset %d width %d = %q", ErrBadField, recIdx, off, width, t)
	}
	return v, nil
}
