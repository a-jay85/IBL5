package backup

import (
	"fmt"
	"strings"
	"testing"
)

// plbLine builds one 360-char .plb team line from per-slot dc_minutes (slots
// beyond len(mins) are zero). Each 12-char slot is minutes(2, right-justified)
// followed by 10 chars of of/df/oi/di/bh padding (zeros — the reader ignores
// them).
func plbLine(mins []int) string {
	var b strings.Builder
	for slot := 0; slot < plbSlotsPerTeam; slot++ {
		m := 0
		if slot < len(mins) {
			m = mins[slot]
		}
		fmt.Fprintf(&b, "%2d%s", m, "0000000000")
	}
	return b.String()
}

// Row 1: ReadPlb maps slot->ordinal via (teamid-1)*30+slot+1 across teams.
func TestReadPlb_SlotOrdinalMapping(t *testing.T) {
	data := strings.Join([]string{
		plbLine([]int{40}),       // team 1 (line 0): slot 0 = 40 -> ordinal 1
		plbLine([]int{0, 0, 25}), // team 2 (line 1): slot 2 = 25 -> ordinal 33
	}, "\r\n")
	m, err := ReadPlb(strings.NewReader(data))
	if err != nil {
		t.Fatalf("ReadPlb: %v", err)
	}
	if m[1] != 40 {
		t.Errorf("ordinal 1 (team1 slot0) = %d, want 40", m[1])
	}
	if m[31] != 0 {
		t.Errorf("ordinal 31 (team2 slot0) = %d, want 0", m[31])
	}
	if m[33] != 25 {
		t.Errorf("ordinal 33 (team2 slot2) = %d, want 25", m[33])
	}
}

// Row 2: a line shorter than 360 chars is skipped (no ordinals for that team).
func TestReadPlb_ShortLineSkipped(t *testing.T) {
	data := strings.Join([]string{plbLine([]int{40}), "tooshort"}, "\r\n")
	m, err := ReadPlb(strings.NewReader(data))
	if err != nil {
		t.Fatalf("ReadPlb: %v", err)
	}
	if m[1] != 40 {
		t.Errorf("team1 slot0 = %d, want 40", m[1])
	}
	if _, ok := m[31]; ok {
		t.Errorf("short team-2 line should yield no ordinals, got an entry for ordinal 31")
	}
}

// Row 3: a line index > 27 (teamid > 28) is skipped entirely.
func TestReadPlb_TeamIDOver28Skipped(t *testing.T) {
	lines := make([]string, 29) // lines 0..28 => teams 1..29
	for i := range lines {
		lines[i] = plbLine([]int{i + 1}) // slot0 = i+1, so each team is distinguishable
	}
	m, err := ReadPlb(strings.NewReader(strings.Join(lines, "\r\n")))
	if err != nil {
		t.Fatalf("ReadPlb: %v", err)
	}
	// team 28 (lineIndex 27) slot0 -> ordinal (28-1)*30+1 = 811, value 28.
	if m[811] != 28 {
		t.Errorf("team28 slot0 (ord 811) = %d, want 28", m[811])
	}
	// team 29 (lineIndex 28) must be skipped -> ordinal 841 absent.
	if _, ok := m[841]; ok {
		t.Errorf("team 29 (lineIndex 28) must be skipped, got an entry for ordinal 841")
	}
}

// Row 4: a slot with dc_minutes == 0 still produces a map entry (not skipped),
// since a real player may legitimately be assigned 0.
func TestReadPlb_ZeroMinutesAttaches(t *testing.T) {
	data := plbLine([]int{0, 16}) // slot 0 = 0, slot 1 = 16
	m, err := ReadPlb(strings.NewReader(data))
	if err != nil {
		t.Fatalf("ReadPlb: %v", err)
	}
	v, ok := m[1]
	if !ok {
		t.Fatalf("slot 0 (ord 1) should have a map entry even at 0 minutes")
	}
	if v != 0 {
		t.Errorf("ord 1 = %d, want 0", v)
	}
	if m[2] != 16 {
		t.Errorf("ord 2 = %d, want 16", m[2])
	}
}
