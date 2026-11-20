package calibrate

import (
	"math"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

const walkTableHead = "## Archive walk against .sco\n\nProse line.\n\n" +
	"| Run | PF | FTA | TOV | STL | home/away FTA |\n" +
	"|-----|----|-----|-----|-----|---------------|\n"

const walkTableGood = walkTableHead +
	"| .sco | 17.116 | 18.587 | n/a | n/a | 1.101 |\n" +
	"| archive BEFORE | 18.089 | 19.983 | 14.662 | 7.203 | 1.104 |\n" +
	"| archive AFTER | 18.089 | 19.983 | 14.662 | 7.203 | 1.104 |\n" +
	"\n## Next section\n"

func TestParseWalkTable_NearMiss(t *testing.T) {
	rows, err := ParseWalkTable(walkTableGood)
	if err != nil {
		t.Fatalf("good table: %v", err)
	}
	if got := rows["archive BEFORE"].PF; got != 18.089 {
		t.Errorf("archive BEFORE PF = %v, want 18.089", got)
	}
	if !math.IsNaN(rows[".sco"].STL) || !math.IsNaN(rows[".sco"].TOV) {
		t.Errorf(".sco n/a cells = %v / %v, want NaN", rows[".sco"].TOV, rows[".sco"].STL)
	}

	bad := map[string]string{
		"extra stride row": strings.Replace(walkTableGood, "\n\n## Next",
			"\n| archive AFTER (stride 50) | 18.0 | 19.9 | 14.6 | 7.2 | 1.1 |\n\n## Next", 1),
		"pending cell": strings.Replace(walkTableGood,
			"| archive AFTER | 18.089 |", "| archive AFTER | pending |", 1),
		"two values in a cell": strings.Replace(walkTableGood,
			"| archive AFTER | 18.089 |", "| archive AFTER | 16.8 / 17.1 |", 1),
		"n/a outside .sco": strings.Replace(walkTableGood,
			"| archive BEFORE | 18.089 | 19.983 | 14.662 |", "| archive BEFORE | 18.089 | 19.983 | n/a |", 1),
		"n/a in .sco PF": strings.Replace(walkTableGood,
			"| .sco | 17.116 |", "| .sco | n/a |", 1),
		"missing AFTER row": strings.Replace(walkTableGood,
			"| archive AFTER | 18.089 | 19.983 | 14.662 | 7.203 | 1.104 |\n", "", 1),
		"five cells": strings.Replace(walkTableGood,
			"| archive AFTER | 18.089 | 19.983 | 14.662 | 7.203 | 1.104 |", "| archive AFTER | 18.089 | 19.983 | 14.662 | 7.203 |", 1),
		"heading absent": strings.Replace(walkTableGood,
			"## Archive walk against .sco", "## Archive walk vs .sco", 1),
	}
	for name, md := range bad {
		t.Run(name, func(t *testing.T) {
			if _, err := ParseWalkTable(md); err == nil {
				t.Fatal("want error, got nil")
			}
		})
	}
}

func TestCheckWalkDelta(t *testing.T) {
	base := func(afterPF, afterFTA float64) map[string]WalkRow {
		return map[string]WalkRow{
			".sco":           {PF: 20, FTA: 20},
			"archive BEFORE": {PF: 18, FTA: 18},
			"archive AFTER":  {PF: afterPF, FTA: afterFTA},
		}
	}
	cases := []struct {
		name              string
		afterPF, afterFTA float64
		wantErr           bool
	}{
		{"after closer", 19, 19, false},
		{"after unchanged", 18, 18, false},
		// BEFORE is 2 off; the slack is 0.05*20 = 1, so 3 off is the edge.
		{"pf at slack edge", 17, 18, false},
		{"pf past slack", 16.9, 18, true},
		{"pf past slack above sco", 23.1, 18, true},
		{"fta past slack", 18, 16.9, true},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			err := CheckWalkDelta(base(c.afterPF, c.afterFTA))
			if (err != nil) != c.wantErr {
				t.Fatalf("CheckWalkDelta err = %v, wantErr %v", err, c.wantErr)
			}
		})
	}
}

func readOutcomeMapDoc(t *testing.T) string {
	t.Helper()
	md, err := os.ReadFile(filepath.Join("..", "..", "docs", "j27-live-outcome-map-port.md"))
	if err != nil {
		t.Fatalf("read j27 doc: %v", err)
	}
	return string(md)
}

func TestArchiveWalkTable_Committed(t *testing.T) {
	rows, err := ParseWalkTable(readOutcomeMapDoc(t))
	if err != nil {
		t.Fatalf("ParseWalkTable: %v", err)
	}
	if err := CheckWalkDelta(rows); err != nil {
		t.Fatalf("CheckWalkDelta: %v", err)
	}
}

const evidenceHead = "## Foul rates by type against the live trace\n\n" +
	"| Type | Trace share | BEFORE share | AFTER share | Decompile line | Verdict |\n" +
	"|------|-------------|--------------|-------------|----------------|---------|\n"

const evidenceGood = evidenceHead +
	"| non-shooting | 0.386 | 0.396 | 0.390 | jsb560_decompiled.c:93469-93480 | ported |\n" +
	"| shooting-two | 0.388 | 0.387 | 0.387 | jsb560_decompiled.c:93469 | matched |\n" +
	"| one-FT | 0.024 | 0.000 | 0.000 | none | residual |\n"

func TestParseFoulEvidenceTable_NearMiss(t *testing.T) {
	rows, err := ParseFoulEvidenceTable(evidenceGood)
	if err != nil {
		t.Fatalf("good table: %v", err)
	}
	if len(rows) != 3 || rows[0].Verdict != "ported" || rows[0].After != 0.390 {
		t.Fatalf("rows = %+v", rows)
	}

	bad := map[string]string{
		"ported cite in prose form": strings.Replace(evidenceGood,
			"jsb560_decompiled.c:93469-93480 | ported", "jsb560_decompiled.c line 93469 | ported", 1),
		"ported cite none": strings.Replace(evidenceGood,
			"jsb560_decompiled.c:93469-93480 | ported", "none | ported", 1),
		"unknown verdict": strings.Replace(evidenceGood, "| matched |", "| partial |", 1),
		"pending share":   strings.Replace(evidenceGood, "| 0.390 |", "| pending |", 1),
		"five cells":      strings.Replace(evidenceGood, "| none | residual |", "| residual |", 1),
	}
	for name, md := range bad {
		t.Run(name, func(t *testing.T) {
			if _, err := ParseFoulEvidenceTable(md); err == nil {
				t.Fatal("want error, got nil")
			}
		})
	}
}

func TestCheckFoulEvidence(t *testing.T) {
	cases := []struct {
		name    string
		row     FoulEvidenceRow
		wantErr bool
	}{
		{"ported closer", FoulEvidenceRow{Trace: 0.4, Before: 0.3, After: 0.35, Verdict: "ported"}, false},
		{"ported unchanged", FoulEvidenceRow{Trace: 0.4, Before: 0.3, After: 0.3, Verdict: "ported"}, true},
		{"ported overshoots", FoulEvidenceRow{Trace: 0.4, Before: 0.3, After: 0.55, Verdict: "ported"}, true},
		{"matched unchanged", FoulEvidenceRow{Trace: 0.4, Before: 0.39, After: 0.39, Verdict: "matched"}, false},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			err := CheckFoulEvidence([]FoulEvidenceRow{c.row})
			if (err != nil) != c.wantErr {
				t.Fatalf("CheckFoulEvidence err = %v, wantErr %v", err, c.wantErr)
			}
		})
	}
}

func TestFoulEvidenceTable_Committed(t *testing.T) {
	rows, err := ParseFoulEvidenceTable(readOutcomeMapDoc(t))
	if err != nil {
		t.Fatalf("ParseFoulEvidenceTable: %v", err)
	}
	if err := CheckFoulEvidence(rows); err != nil {
		t.Fatalf("CheckFoulEvidence: %v", err)
	}
}
