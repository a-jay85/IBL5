package backup

import (
	"errors"
	"strings"
	"testing"
)

// These tests lock in the two real-corpus .sco quirks discovered when the PR9b
// harness was first run against an actual JSB 5.60 backup (the synthetic PR9a
// fixtures only ever used space padding and fully-padded records):
//   1. trailing padding records are filled with NUL bytes (0x00), not spaces;
//   2. the LAST record omits its trailing padding, ending after the 1,648-byte
//      game-info + slot content.

// A NUL-filled padding record must be skipped like a space-filled one, not
// rejected as a non-numeric field.
func TestReadSco_NullPaddingRecordSkipped(t *testing.T) {
	gi := buildGameInfo(6, 8, 0, 0, [5]int{50}, [5]int{48})
	real := buildRecord(gi, [][]byte{buildSlot(scoSlotData{name: "PLAYER", pid: 11, twoGM: 5, twoGA: 10})})
	nullRec := strings.Repeat("\x00", scoRecordSize)
	data := scoHeader() + real + nullRec

	games, err := ReadSco(strings.NewReader(data))
	if err != nil {
		t.Fatalf("ReadSco with a NUL padding record: %v", err)
	}
	if len(games) != 1 {
		t.Fatalf("games = %d, want 1 (the NUL-filled record must be skipped as padding)", len(games))
	}
}

// The final record may carry only its 1,648-byte content with no trailing
// padding; ReadSco must still decode it rather than report truncation.
func TestReadSco_UnpaddedFinalRecord(t *testing.T) {
	gi := buildGameInfo(6, 8, 0, 0, [5]int{50}, [5]int{48})
	full := buildRecord(gi, [][]byte{buildSlot(scoSlotData{name: "PLAYER", pid: 11, twoGM: 5, twoGA: 10})})
	contentOnly := full[:scoContentSize] // drop the trailing padding
	data := scoHeader() + contentOnly

	games, err := ReadSco(strings.NewReader(data))
	if err != nil {
		t.Fatalf("ReadSco with an unpadded final record: %v", err)
	}
	if len(games) != 1 {
		t.Fatalf("games = %d, want 1 (the unpadded final record must decode)", len(games))
	}
	if games[0].VisitorTeamID != 7 {
		t.Errorf("visitor team = %d, want 7", games[0].VisitorTeamID)
	}
}

// A trailing record shorter than the content size is still a genuine truncation.
func TestReadSco_ShortFinalRecordErrors(t *testing.T) {
	data := scoHeader() + strings.Repeat(" ", scoContentSize-1)
	if _, err := ReadSco(strings.NewReader(data)); !errors.Is(err, ErrShortRecord) {
		t.Fatalf("err = %v, want ErrShortRecord for a sub-content-size tail", err)
	}
}
