package validate

import (
	"bytes"
	"errors"
	"os"
	"reflect"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

const testRuns = 8 // small N keeps `go test ./...` fast (advisor guidance)

// Row #5 (unit): compareGame reports FAIL when any single stat is out of band,
// proving the harness can actually fail — a harness that always passes proves
// nothing.
func TestCompareGame_FailsOnOutOfBandStat(t *testing.T) {
	visMean := map[string]float64{"points": 100, "fgm": 40, "reb": 45}
	homeMean := map[string]float64{"points": 100, "fgm": 40, "reb": 45}
	// Visitor points wildly off; everything else matches.
	visSco := TeamStat{Points: 200, FGM: 40, REB: 45}
	homeSco := TeamStat{Points: 100, FGM: 40, REB: 45}

	gr := compareGame(7, 3, "10-01", visSco, homeSco, visMean, homeMean)
	if gr.Pass {
		t.Fatal("compareGame should FAIL when visitor points are far out of band")
	}
	var sawFail bool
	for _, r := range gr.Rows {
		if r.TeamID == 7 && r.Stat == "points" && !r.Pass {
			sawFail = true
		}
	}
	if !sawFail {
		t.Error("expected the visitor points row to be marked FAIL")
	}
}

// Row #9 (unit): matchSchedule pairs a .sco game to the schedule entry sharing
// (visitor, home, visitorScore, homeScore), consuming each entry once, and
// returns -1 when no entry matches.
func TestMatchSchedule(t *testing.T) {
	sched := []backup.SchGame{
		{VisitorTeamID: 7, HomeTeamID: 3, VisitorScore: 100, HomeScore: 98},
		{VisitorTeamID: 7, HomeTeamID: 3, VisitorScore: 100, HomeScore: 98}, // dup matchup, different game
	}
	consumed := make([]bool, len(sched))

	sg := backup.ScoGame{VisitorTeamID: 7, HomeTeamID: 3, VisitorScore: 100, HomeScore: 98}
	if i := matchSchedule(sched, consumed, sg); i != 0 {
		t.Fatalf("first match = %d, want 0", i)
	}
	consumed[0] = true
	if i := matchSchedule(sched, consumed, sg); i != 1 {
		t.Fatalf("second match = %d, want 1 (first consumed)", i)
	}
	consumed[1] = true
	if i := matchSchedule(sched, consumed, sg); i != -1 {
		t.Fatalf("third match = %d, want -1 (all consumed)", i)
	}

	noMatch := backup.ScoGame{VisitorTeamID: 7, HomeTeamID: 3, VisitorScore: 77, HomeScore: 77}
	if i := matchSchedule(sched, make([]bool, len(sched)), noMatch); i != -1 {
		t.Fatalf("unmatched scores = %d, want -1", i)
	}
}

// Row #6 + happy path: ValidateCorpus on the in-band synthetic corpus passes,
// and is deterministic — same (dir, runs, seed, gameType) yields an identical
// Report and identical report bytes across two runs.
func TestValidateCorpus_InBandPassesAndDeterministic(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	buildCorpus(t, dir, true, testRuns, seed)

	rep1, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus: %v", err)
	}
	if !rep1.Pass {
		for _, g := range rep1.Games {
			for _, r := range g.Rows {
				if !r.Pass {
					t.Logf("FAIL row: %s", r.Detail)
				}
			}
		}
		t.Fatal("in-band corpus should PASS")
	}
	if len(rep1.Games) != 1 || len(rep1.Unmatched) != 0 {
		t.Fatalf("games=%d unmatched=%d, want 1 / 0", len(rep1.Games), len(rep1.Unmatched))
	}

	rep2, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus (2nd): %v", err)
	}
	if !reflect.DeepEqual(rep1, rep2) {
		t.Error("two ValidateCorpus runs produced different Reports (non-deterministic)")
	}
	var b1, b2 bytes.Buffer
	WriteReport(&b1, rep1)
	WriteReport(&b2, rep2)
	if b1.String() != b2.String() {
		t.Error("two WriteReport outputs differ (non-deterministic)")
	}
}

// Row #5 (corpus): the out-of-band synthetic corpus drives ValidateCorpus to a
// FAILing Report (box stats zeroed → far outside the bands).
func TestValidateCorpus_OutOfBandFails(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	buildCorpus(t, dir, false, testRuns, seed)

	rep, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus: %v", err)
	}
	if rep.Pass {
		t.Fatal("out-of-band corpus should FAIL")
	}
}

// Row #7 (negative): an empty corpus dir, and a dir with no complete triple,
// both yield ErrNoCorpus — never a panic or a vacuous PASS.
func TestValidateCorpus_EmptyDir(t *testing.T) {
	if _, err := ValidateCorpus(t.TempDir(), testRuns, 1, bundle.GameTypeRegular); !errors.Is(err, ErrNoCorpus) {
		t.Fatalf("empty dir error = %v, want ErrNoCorpus", err)
	}

	// A .plr with no sibling .sch/.sco is not a complete triple.
	dir := t.TempDir()
	writePlr(t, dir+"/lonely.plr", starterSpecs())
	if _, err := ValidateCorpus(dir, testRuns, 1, bundle.GameTypeRegular); !errors.Is(err, ErrNoCorpus) {
		t.Fatalf("incomplete triple error = %v, want ErrNoCorpus", err)
	}
}

// Row #8 (negative): a corpus whose .plr has a malformed numeric field surfaces
// the PR9a typed reader error (ErrBadField), not a panic or a silent mis-parse.
func TestValidateCorpus_MalformedPlr(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	buildCorpus(t, dir, true, testRuns, seed)

	plr, _, _ := corpusPaths(dir, "synth")
	data, err := os.ReadFile(plr)
	if err != nil {
		t.Fatal(err)
	}
	// Corrupt the 6-byte pid field (offset 38) of the first record with letters.
	copy(data[38:44], []byte("XXXXXX"))
	if err := os.WriteFile(plr, data, 0o644); err != nil {
		t.Fatal(err)
	}

	_, err = ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if !errors.Is(err, backup.ErrBadField) {
		t.Fatalf("malformed .plr error = %v, want ErrBadField", err)
	}
}

// Row #9 (corpus): a .sco game with no matching schedule entry is REPORTED in
// Unmatched (not silently dropped) and forces the Report to FAIL.
func TestValidateCorpus_UnmatchedScoGame(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	// One matched game + one extra .sco game whose scores match no .sch entry.
	extra := scoGameSpec{
		visTID: 7, homeTID: 3, visScore: 77, homeScore: 77,
		visBoxes:  []scoBoxSpec{{pid: 1, name: "VIS"}},
		homeBoxes: []scoBoxSpec{{pid: 2, name: "HOME"}},
	}
	buildCorpus(t, dir, true, testRuns, seed, extra)

	rep, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus: %v", err)
	}
	if len(rep.Unmatched) != 1 {
		t.Fatalf("unmatched = %d, want 1", len(rep.Unmatched))
	}
	if rep.Pass {
		t.Error("a corpus with an unmatched .sco game must FAIL")
	}
	if rep.Unmatched[0].VisitorScore != 77 {
		t.Errorf("unmatched visitorScore = %d, want 77", rep.Unmatched[0].VisitorScore)
	}

	// The unmatched game must be surfaced in the rendered report, not dropped.
	var buf bytes.Buffer
	WriteReport(&buf, rep)
	if !strings.Contains(buf.String(), "UNMATCHED") || !strings.Contains(buf.String(), "RESULT: FAIL") {
		t.Errorf("report should render an UNMATCHED line and a FAIL result:\n%s", buf.String())
	}
}
