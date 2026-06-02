package backup

import (
	"fmt"
	"io"
	"strconv"
	"strings"
)

// .plb depth-chart layout, transcribed from
// ibl5/classes/JsbParser/PlbFileParser.php (the authoritative reader). Each line
// is one team; each 12-char slot is one roster ordinal's depth-chart entry:
//
//	dc_minutes(2) | dc_of(2) | dc_df(2) | dc_oi(2) | dc_di(2) | dc_bh(2)
//
// Only dc_minutes feeds the engine — the of/df/oi/di/bh fields are dead on IBL5
// data and the sim deliberately never reads them (see internal/sim/lineup.go),
// so they are not parsed.
const (
	plbTeamsPerFile = 32
	plbSlotsPerTeam = 30
	plbCharsPerSlot = 12
	// A line shorter than this cannot carry 30 full slots, so it is blank/short
	// padding and skipped — mirrors PlbFileParser::MIN_LINE_LENGTH (30 * 12).
	plbMinLineLen = plbSlotsPerTeam * plbCharsPerSlot // 360
	// Only teams 1..28 are real IBL franchises; lines beyond that are skipped,
	// mirroring PlbImporter's `$teamid > 28` guard.
	plbMaxTeamID = 28
)

// ReadPlb reads a backup .plb depth-chart file from r and returns a map of
// player ordinal -> dc_minutes (the GM-assigned per-player target minutes). The
// ordinal is derived positionally: ordinal = (teamID-1)*30 + slotIndex + 1, with
// teamID = lineIndex + 1 — matching PlrParser/PlrOrdinalMap.php, so the result
// keys join directly onto PlrPlayer.Ordinal in ToBundle.
//
// Records are CRLF-separated (matching ReadPlr's convention). A short line
// (< 360 chars) or a line for teamID > 28 is skipped. Every parsed slot yields a
// map entry, including dc_minutes == 0 (a real player may legitimately be
// assigned 0). The minutes field is a LENIENT cast (empty/non-numeric -> 0),
// like PHP's (int)substr: this is a best-effort signal, not an identity field,
// so a malformed slot degrades to 0 minutes rather than failing an otherwise-
// valid snapshot. The only error is an io.ReadAll failure.
func ReadPlb(r io.Reader) (map[int]int, error) {
	data, err := io.ReadAll(r)
	if err != nil {
		return nil, fmt.Errorf("backup: read .plb: %w", err)
	}
	lines := strings.Split(string(data), "\r\n")

	out := make(map[int]int)
	limit := len(lines)
	if limit > plbTeamsPerFile {
		limit = plbTeamsPerFile
	}
	for lineIndex := 0; lineIndex < limit; lineIndex++ {
		line := lines[lineIndex]
		if len(line) < plbMinLineLen {
			continue // blank / short padding row
		}
		teamID := lineIndex + 1
		if teamID > plbMaxTeamID {
			continue
		}
		for slot := 0; slot < plbSlotsPerTeam; slot++ {
			off := slot * plbCharsPerSlot
			ordinal := (teamID-1)*plbSlotsPerTeam + slot + 1
			out[ordinal] = plbInt(line, off, 2)
		}
	}
	return out, nil
}

// plbInt slices a 2-char minutes field, trims padding, and parses it leniently:
// an empty or non-numeric field yields 0 (matching PHP's (int)substr cast).
// Unlike plrInt it never errors — a malformed depth-chart slot must not fail the
// whole snapshot, since dc_minutes is a best-effort signal, not identity.
func plbInt(line string, off, width int) int {
	s := strings.TrimSpace(plrSlice(line, off, width))
	if s == "" {
		return 0
	}
	v, err := strconv.Atoi(s)
	if err != nil {
		return 0
	}
	return v
}
