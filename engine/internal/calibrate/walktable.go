package calibrate

// Parsers and checks for the two tables in engine/docs/j27-live-outcome-map-port.md
// that record the foul archive walk: "## Archive walk against .sco" and
// "## Foul rates by type against the live trace". The doc is the committed
// record of the walk numbers; walktable_test.go re-checks it on every CI run.

import (
	"fmt"
	"math"
	"regexp"
	"strconv"
	"strings"
)

const (
	walkTableHeading     = "## Archive walk against .sco"
	foulEvidenceHeading  = "## Foul rates by type against the live trace"
	walkTableCells       = 6
	foulEvidenceCells    = 6
	walkDeltaSlackOfSco  = 0.05
	walkLabelSco         = ".sco"
	walkLabelBefore      = "archive BEFORE"
	walkLabelAfter       = "archive AFTER"
	foulVerdictPorted    = "ported"
	foulEvidenceNoneCite = "none"
)

var walkLabels = map[string]bool{walkLabelSco: true, walkLabelBefore: true, walkLabelAfter: true}
var verdicts = map[string]bool{foulVerdictPorted: true, "residual": true, "matched": true}
var citeRE = regexp.MustCompile(`^jsb560_decompiled\.c:\d+(-\d+)?$`)

// WalkRow is one row of the archive walk table, per team-game.
type WalkRow struct{ PF, FTA, TOV, STL, HomeAwayFTA float64 }

// FoulEvidenceRow is one row of the per-type foul evidence table. Trace,
// Before and After are shares of total PF.
type FoulEvidenceRow struct {
	Type                 string
	Trace, Before, After float64
	Cite, Verdict        string
}

// tableUnder returns the body rows of the first pipe table after the exact
// heading line, stopping at the first non-table line. Errors if absent.
func tableUnder(md, heading string) ([][]string, error) {
	lines := strings.Split(md, "\n")
	start := -1
	for i, l := range lines {
		if strings.TrimRight(l, " \r") == heading {
			start = i + 1
			break
		}
	}
	if start < 0 {
		return nil, fmt.Errorf("calibrate: heading %q not found", heading)
	}
	// Skip prose up to the first table line.
	i := start
	for i < len(lines) && !strings.HasPrefix(strings.TrimSpace(lines[i]), "|") {
		if strings.HasPrefix(lines[i], "## ") {
			return nil, fmt.Errorf("calibrate: no table under %q", heading)
		}
		i++
	}
	var rows [][]string
	seenHeader := false
	for ; i < len(lines); i++ {
		l := strings.TrimSpace(lines[i])
		if !strings.HasPrefix(l, "|") {
			break
		}
		cells := strings.Split(strings.Trim(l, "|"), "|")
		for k := range cells {
			cells[k] = strings.TrimSpace(cells[k])
		}
		if !seenHeader {
			seenHeader = true // the column-name row
			continue
		}
		if isSeparatorRow(cells) {
			continue
		}
		rows = append(rows, cells)
	}
	if !seenHeader {
		return nil, fmt.Errorf("calibrate: no table under %q", heading)
	}
	if len(rows) == 0 {
		return nil, fmt.Errorf("calibrate: table under %q has no body rows", heading)
	}
	return rows, nil
}

func isSeparatorRow(cells []string) bool {
	for _, c := range cells {
		if strings.Trim(c, "-: ") != "" {
			return false
		}
	}
	return true
}

func parseCell(label, col, cell string) (float64, error) {
	v, err := strconv.ParseFloat(cell, 64)
	if err != nil {
		return 0, fmt.Errorf("calibrate: row %q column %s: %q is not a number", label, col, cell)
	}
	return v, nil
}

// ParseWalkTable parses the "## Archive walk against .sco" table. Every row
// needs exactly 6 cells and one of the three known labels; all three labels
// are required. Only the .sco row's TOV and STL cells may read n/a (NaN).
func ParseWalkTable(md string) (map[string]WalkRow, error) {
	rows, err := tableUnder(md, walkTableHeading)
	if err != nil {
		return nil, err
	}
	out := make(map[string]WalkRow, len(rows))
	for _, cells := range rows {
		if len(cells) != walkTableCells {
			return nil, fmt.Errorf("calibrate: walk row %v has %d cells, want %d", cells, len(cells), walkTableCells)
		}
		label := cells[0]
		if !walkLabels[label] {
			return nil, fmt.Errorf("calibrate: walk row label %q is not one of .sco, archive BEFORE, archive AFTER", label)
		}
		if _, dup := out[label]; dup {
			return nil, fmt.Errorf("calibrate: walk row %q appears twice", label)
		}
		cols := []string{"PF", "FTA", "TOV", "STL", "home/away FTA"}
		vals := make([]float64, len(cols))
		for k, col := range cols {
			cell := cells[k+1]
			if cell == "n/a" && label == walkLabelSco && (col == "TOV" || col == "STL") {
				vals[k] = math.NaN()
				continue
			}
			v, err := parseCell(label, col, cell)
			if err != nil {
				return nil, err
			}
			vals[k] = v
		}
		out[label] = WalkRow{PF: vals[0], FTA: vals[1], TOV: vals[2], STL: vals[3], HomeAwayFTA: vals[4]}
	}
	for label := range walkLabels {
		if _, ok := out[label]; !ok {
			return nil, fmt.Errorf("calibrate: walk table lacks the %q row", label)
		}
	}
	return out, nil
}

// CheckWalkDelta fails when the AFTER PF or FTA sits farther from .sco than
// BEFORE by more than 5% of the .sco value. TOV and STL are not checked: a
// foul PR need not move them.
func CheckWalkDelta(rows map[string]WalkRow) error {
	sco, before, after := rows[walkLabelSco], rows[walkLabelBefore], rows[walkLabelAfter]
	for _, c := range []struct {
		name               string
		sco, before, after float64
	}{
		{"PF", sco.PF, before.PF, after.PF},
		{"FTA", sco.FTA, before.FTA, after.FTA},
	} {
		if math.Abs(c.after-c.sco) > math.Abs(c.before-c.sco)+0.05*c.sco {
			return fmt.Errorf("calibrate: %s AFTER %.3f is farther from .sco %.3f than BEFORE %.3f plus %.0f%% of .sco",
				c.name, c.after, c.sco, c.before, 100*walkDeltaSlackOfSco)
		}
	}
	return nil
}

// ParseFoulEvidenceTable parses the "## Foul rates by type against the live
// trace" table. Every row needs 6 cells and a known verdict. A ported row must
// cite a decompile line; other rows take a decompile line or none.
func ParseFoulEvidenceTable(md string) ([]FoulEvidenceRow, error) {
	rows, err := tableUnder(md, foulEvidenceHeading)
	if err != nil {
		return nil, err
	}
	out := make([]FoulEvidenceRow, 0, len(rows))
	for _, cells := range rows {
		if len(cells) != foulEvidenceCells {
			return nil, fmt.Errorf("calibrate: evidence row %v has %d cells, want %d", cells, len(cells), foulEvidenceCells)
		}
		r := FoulEvidenceRow{Type: cells[0], Cite: cells[4], Verdict: cells[5]}
		if !verdicts[r.Verdict] {
			return nil, fmt.Errorf("calibrate: evidence row %q verdict %q is not ported, residual or matched", r.Type, r.Verdict)
		}
		for k, dst := range []*float64{&r.Trace, &r.Before, &r.After} {
			v, err := parseCell(r.Type, []string{"Trace share", "BEFORE share", "AFTER share"}[k], cells[k+1])
			if err != nil {
				return nil, err
			}
			*dst = v
		}
		if r.Verdict == foulVerdictPorted {
			if !citeRE.MatchString(r.Cite) {
				return nil, fmt.Errorf("calibrate: ported row %q cites %q, want jsb560_decompiled.c:<line>[-<line>]", r.Type, r.Cite)
			}
		} else if r.Cite != foulEvidenceNoneCite && !citeRE.MatchString(r.Cite) {
			return nil, fmt.Errorf("calibrate: row %q cites %q, want a decompile line or none", r.Type, r.Cite)
		}
		out = append(out, r)
	}
	return out, nil
}

// CheckFoulEvidence fails a ported row whose AFTER share is not strictly
// closer to the trace share than its BEFORE share.
func CheckFoulEvidence(rows []FoulEvidenceRow) error {
	for _, r := range rows {
		if r.Verdict != foulVerdictPorted {
			continue
		}
		if math.Abs(r.After-r.Trace) >= math.Abs(r.Before-r.Trace) {
			return fmt.Errorf("calibrate: ported row %q AFTER share %.3f is not closer to the trace %.3f than BEFORE %.3f",
				r.Type, r.After, r.Trace, r.Before)
		}
	}
	return nil
}
