package backup

import (
	"errors"
	"fmt"
	"io"
	"strconv"
	"strings"
)

// .sch compact schedule format, transcribed from
// ibl5/classes/JsbParser/SchFileParser.php. The file is exactly 80,000 bytes:
// 8,000 game slots of 10 bytes ([teams:4][scores:6]), grouped 16 slots per
// calendar date. The date is derived from the slot's date group (Oct = group 0,
// 31 day-slots per month); the record itself carries no game type.
const (
	schFileSize     = 80000
	schRecordSize   = 10
	schTeamsField   = 4
	schScoresField  = 6
	schSlotsPerDate = 16
	schDaysPerMonth = 31
	schMaxMonthOff  = 12
)

const schEmptyRecord = "0   0     "

// ErrBadSize reports a .sch input whose total length is not exactly 80,000
// bytes, mirroring SchFileParser's strict size check.
var ErrBadSize = errors.New("backup: invalid .sch size")

// SchGame is one schedule slot. Played is true once either score is positive
// (the slot has been simulated). The .sch carries no game type, so the bundle
// assembler stamps one; see ToBundle.
type SchGame struct {
	VisitorTeamID int
	HomeTeamID    int
	Month         int
	Day           int
	VisitorScore  int
	HomeScore     int
	Played        bool
}

// ReadSch reads a backup .sch schedule from r. It requires exactly 80,000
// bytes (else ErrBadSize), iterates the 8,000 slots, skips empty slots and
// invalid dates (matching SchFileParser), and derives Month/Day from each
// slot's date group. A non-numeric value where digits are required yields
// ErrBadField — never a panic.
func ReadSch(r io.Reader) ([]SchGame, error) {
	data, err := io.ReadAll(r)
	if err != nil {
		return nil, fmt.Errorf("backup: read .sch: %w", err)
	}
	if len(data) != schFileSize {
		return nil, fmt.Errorf("%w: expected %d bytes, got %d", ErrBadSize, schFileSize, len(data))
	}
	s := string(data)

	games := make([]SchGame, 0)
	bytesPerDate := schSlotsPerDate * schRecordSize
	for dateSlot := 0; dateSlot < schFileSize/bytesPerDate; dateSlot++ {
		if dateSlot/schDaysPerMonth >= schMaxMonthOff {
			continue
		}
		month, day, ok := dateSlotToMonthDay(dateSlot)
		if !ok {
			continue // invalid calendar date (e.g. Nov 31, Feb 30)
		}
		for gameIndex := 0; gameIndex < schSlotsPerDate; gameIndex++ {
			off := (dateSlot*schSlotsPerDate + gameIndex) * schRecordSize
			record := s[off : off+schRecordSize]
			if record == schEmptyRecord {
				continue
			}
			vis, home, vScore, hScore, ok, err := parseSchRecord(record)
			if err != nil {
				return nil, err
			}
			if !ok {
				continue
			}
			games = append(games, SchGame{
				VisitorTeamID: vis,
				HomeTeamID:    home,
				Month:         month,
				Day:           day,
				VisitorScore:  vScore,
				HomeScore:     hScore,
				Played:        vScore > 0 || hScore > 0,
			})
		}
	}
	return games, nil
}

// parseSchRecord decodes a single 10-byte slot. ok is false when the slot is
// empty/unschedulable (teams field too short, or a non-positive team ID),
// mirroring SchFileParser::parseGameRecord's null returns.
func parseSchRecord(record string) (vis, home, vScore, hScore int, ok bool, err error) {
	if record == schEmptyRecord {
		return 0, 0, 0, 0, false, nil
	}
	teamsField := strings.TrimRight(record[:schTeamsField], " ")
	scoresField := strings.TrimRight(record[schTeamsField:schTeamsField+schScoresField], " ")

	if len(teamsField) < 2 {
		return 0, 0, 0, 0, false, nil
	}
	// Home is always the last 2 chars (zero-padded); visitor is the remainder.
	home, err = atoiField(teamsField[len(teamsField)-2:])
	if err != nil {
		return 0, 0, 0, 0, false, err
	}
	vis, err = atoiField(teamsField[:len(teamsField)-2])
	if err != nil {
		return 0, 0, 0, 0, false, err
	}
	if vis <= 0 || home <= 0 {
		return 0, 0, 0, 0, false, nil
	}

	// Unplayed games store the scores field as a bare "0".
	if scoresField == "0" {
		return vis, home, 0, 0, true, nil
	}
	if len(scoresField) < 4 {
		return 0, 0, 0, 0, false, nil
	}
	hScore, err = atoiField(scoresField[len(scoresField)-3:])
	if err != nil {
		return 0, 0, 0, 0, false, err
	}
	vScore, err = atoiField(scoresField[:len(scoresField)-3])
	if err != nil {
		return 0, 0, 0, 0, false, err
	}
	return vis, home, vScore, hScore, true, nil
}

// dateSlotToMonthDay converts a date group to a calendar month/day, returning
// ok=false for an invalid date. Offset 0 = October; the leap year 2000 is used
// so Feb 29 validates (matching SchFileParser::dateSlotToMonthDay's checkdate).
func dateSlotToMonthDay(dateSlot int) (month, day int, ok bool) {
	monthOffset := dateSlot / schDaysPerMonth
	if monthOffset >= schMaxMonthOff {
		return 0, 0, false
	}
	month = ((monthOffset+9)%12 + 1)
	day = dateSlot%schDaysPerMonth + 1
	if day > daysInMonth2000(month) {
		return 0, 0, false
	}
	return month, day, true
}

// daysInMonth2000 returns the day count for a month in the leap year 2000.
func daysInMonth2000(month int) int {
	switch month {
	case 2:
		return 29
	case 4, 6, 9, 11:
		return 30
	default:
		return 31
	}
}

// atoiField trims a digit field and parses it; blank is 0, non-numeric is
// ErrBadField. Shared with the .plr/.sco readers' parsing convention.
func atoiField(s string) (int, error) {
	t := strings.TrimSpace(s)
	if t == "" {
		return 0, nil
	}
	v, err := strconv.Atoi(t)
	if err != nil {
		return 0, fmt.Errorf("%w: %q", ErrBadField, t)
	}
	return v, nil
}
