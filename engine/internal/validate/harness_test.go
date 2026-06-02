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

	gr := compareGame(bundle.GameTypeRegular, 7, 3, "10-01", visSco, homeSco, visMean, homeMean)
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

// Characterization (Row #1): the bands→game-type-keyed refactor must not change
// observable behavior. ValidateCorpus stamped with GameTypeRegular must route
// every stat to the regular band table — i.e. each StatRow.Tolerance equals the
// tolerance recomputed from bandFor(GameTypeRegular, stat) at that row's engine
// mean. A threading bug (wrong/empty game-type table) would produce a different
// tolerance and fail here.
func TestValidateCorpus_RegularBandsCharacterization(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	buildCorpus(t, dir, true, testRuns, seed)

	rep, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus: %v", err)
	}
	if len(rep.Games) == 0 {
		t.Fatal("expected at least one validated game")
	}
	for _, g := range rep.Games {
		for _, r := range g.Rows {
			want := toleranceFor(bandFor(bundle.GameTypeRegular, r.Stat), r.EngineMean)
			if r.Tolerance != want {
				t.Errorf("team=%d stat=%s tolerance=%.4f, want %.4f (regular band table)",
					r.TeamID, r.Stat, r.Tolerance, want)
			}
		}
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

// Row #2: ValidateUnscheduled simulates an unmatched .sco game between two
// rostered franchises (7 vs 3) under the given game type — the matchup is
// synthesized from the .sco's own team IDs, and the game lands in Report.Games
// (NOT Unmatched, NOT Excluded). The matched (scheduled) game is skipped.
// Pass is intentionally NOT asserted: to be unmatched the .sco scores must
// differ from the only rostered matchup's .sch scores, so the synthesized game
// is points-out-of-band by construction (advisor guidance).
func TestValidateUnscheduled_SimulatesUnmatchedGame(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	extra := scoGameSpec{
		visTID: 7, homeTID: 3, visScore: 77, homeScore: 77,
		visBoxes:  []scoBoxSpec{{pid: 1, name: "VIS"}},
		homeBoxes: []scoBoxSpec{{pid: 2, name: "HOME"}},
	}
	buildCorpus(t, dir, true, testRuns, seed, extra)

	rep, err := ValidateUnscheduled(dir, testRuns, seed, bundle.GameTypePlayoff)
	if err != nil {
		t.Fatalf("ValidateUnscheduled: %v", err)
	}
	if len(rep.Games) != 1 {
		t.Fatalf("games = %d, want 1 (the unmatched game, simulated)", len(rep.Games))
	}
	if len(rep.Unmatched) != 0 || len(rep.Excluded) != 0 {
		t.Fatalf("unmatched=%d excluded=%d, want 0 / 0", len(rep.Unmatched), len(rep.Excluded))
	}
	if rep.GameType != bundle.GameTypePlayoff {
		t.Errorf("report game type = %d, want playoff (4)", int(rep.GameType))
	}
	g := rep.Games[0]
	if g.VisitorTeamID != 7 || g.HomeTeamID != 3 || g.Date != "10-01" {
		t.Errorf("synthesized game = visitor=%d home=%d date=%q, want 7/3/10-01", g.VisitorTeamID, g.HomeTeamID, g.Date)
	}
}

// Row #3: ValidateUnscheduled is deterministic — two runs over the same corpus
// yield an identical Report and identical rendered bytes.
func TestValidateUnscheduled_Deterministic(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	extra := scoGameSpec{
		visTID: 7, homeTID: 3, visScore: 77, homeScore: 77,
		visBoxes:  []scoBoxSpec{{pid: 1, name: "VIS"}},
		homeBoxes: []scoBoxSpec{{pid: 2, name: "HOME"}},
	}
	buildCorpus(t, dir, true, testRuns, seed, extra)

	rep1, err := ValidateUnscheduled(dir, testRuns, seed, bundle.GameTypePlayoff)
	if err != nil {
		t.Fatalf("ValidateUnscheduled: %v", err)
	}
	rep2, err := ValidateUnscheduled(dir, testRuns, seed, bundle.GameTypePlayoff)
	if err != nil {
		t.Fatalf("ValidateUnscheduled (2nd): %v", err)
	}
	if !reflect.DeepEqual(rep1, rep2) {
		t.Error("two ValidateUnscheduled runs produced different Reports (non-deterministic)")
	}
	var b1, b2 bytes.Buffer
	WriteReport(&b1, rep1)
	WriteReport(&b2, rep2)
	if b1.String() != b2.String() {
		t.Error("two WriteReport outputs differ (non-deterministic)")
	}
}

// Row #4 (negative): the sim-validity guard. An unmatched .sco game whose team
// ID has no .plr roster (team 11, absent from starterSpecs) is recorded in
// Report.Excluded with a reason and never simulated. The box slot is filled so
// ReadSco does not drop the record as padding (advisor guidance).
func TestValidateUnscheduled_RosterlessTeamExcluded(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	rosterless := scoGameSpec{
		visTID: 11, homeTID: 3, visScore: 88, homeScore: 88,
		visBoxes:  []scoBoxSpec{{pid: 9, name: "GHOST"}},
		homeBoxes: []scoBoxSpec{{pid: 2, name: "HOME"}},
	}
	buildCorpus(t, dir, true, testRuns, seed, rosterless)

	rep, err := ValidateUnscheduled(dir, testRuns, seed, bundle.GameTypePlayoff)
	if err != nil {
		t.Fatalf("ValidateUnscheduled: %v", err)
	}
	if len(rep.Games) != 0 {
		t.Fatalf("games = %d, want 0 (the rosterless game must not be simulated)", len(rep.Games))
	}
	if len(rep.Excluded) != 1 {
		t.Fatalf("excluded = %d, want 1", len(rep.Excluded))
	}
	if e := rep.Excluded[0]; e.VisitorTeamID != 11 || e.HomeTeamID != 3 || e.Reason == "" {
		t.Errorf("excluded game = %+v, want visitor=11 home=3 with a reason", e)
	}

	// The exclusion is surfaced in the rendered report, not dropped.
	var buf bytes.Buffer
	WriteReport(&buf, rep)
	if !strings.Contains(buf.String(), "EXCLUDED") || !strings.Contains(buf.String(), "excluded)") {
		t.Errorf("report should render an EXCLUDED line and excluded count:\n%s", buf.String())
	}
}

// Row #5 (boundary): a corpus whose only .sco game IS scheduled (matches the
// .sch) has no unmatched games, so ValidateUnscheduled returns zero Games, zero
// Excluded, and Pass true — it never touches ValidateCorpus's domain.
func TestValidateUnscheduled_NoUnmatchedGames(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	buildCorpus(t, dir, true, testRuns, seed) // only the matched game, no extras

	rep, err := ValidateUnscheduled(dir, testRuns, seed, bundle.GameTypePlayoff)
	if err != nil {
		t.Fatalf("ValidateUnscheduled: %v", err)
	}
	if len(rep.Games) != 0 || len(rep.Excluded) != 0 {
		t.Fatalf("games=%d excluded=%d, want 0 / 0", len(rep.Games), len(rep.Excluded))
	}
	if !rep.Pass {
		t.Error("a corpus with no unmatched games must PASS (nothing failed)")
	}
}

// ValidateUnscheduled shares ValidateCorpus's guards: runs<=0 is an error, and
// an empty corpus dir yields ErrNoCorpus (never a panic or a vacuous PASS).
func TestValidateUnscheduled_GuardsAndEmptyDir(t *testing.T) {
	dir := t.TempDir()
	buildCorpus(t, dir, true, testRuns, 1)
	if _, err := ValidateUnscheduled(dir, 0, 1, bundle.GameTypePlayoff); err == nil {
		t.Error("runs=0 should be an error")
	}
	if _, err := ValidateUnscheduled(t.TempDir(), testRuns, 1, bundle.GameTypePlayoff); !errors.Is(err, ErrNoCorpus) {
		t.Fatalf("empty dir error = %v, want ErrNoCorpus", err)
	}
}

// writePlb writes a minimal valid .plb (one 360-char line) at path. Content is
// irrelevant to findTriples (which groups by extension) and parses to all-zero
// minutes for ReadPlb; the valid length keeps it from being skipped as padding.
func writePlb(t *testing.T, path string) {
	t.Helper()
	if err := os.WriteFile(path, []byte(strings.Repeat("0", 360)), 0o644); err != nil {
		t.Fatalf("write .plb: %v", err)
	}
}

// Row 8: findTriples returns a complete triple with plb=="" when no .plb is
// present, and picks up the .plb path once one sharing the stem is added.
func TestFindTriples_PlbOptional(t *testing.T) {
	dir := t.TempDir()
	buildCorpus(t, dir, true, testRuns, 1)

	triples, err := findTriples(dir)
	if err != nil {
		t.Fatalf("findTriples: %v", err)
	}
	if len(triples) != 1 {
		t.Fatalf("triples = %d, want 1", len(triples))
	}
	if triples[0].plb != "" {
		t.Errorf("plb = %q, want empty (no .plb yet)", triples[0].plb)
	}

	writePlb(t, dir+"/synth.plb")
	triples, err = findTriples(dir)
	if err != nil {
		t.Fatalf("findTriples after .plb: %v", err)
	}
	if len(triples) != 1 {
		t.Fatalf("triples = %d, want 1", len(triples))
	}
	if triples[0].plb == "" {
		t.Error("plb path empty after writing synth.plb")
	}
}

// Row 9: a snapshot missing its .plb is reported in Report.MissingPlb (rendered
// as a MISSING_PLB line) and validation still runs with zero minutes — the
// missing depth chart does NOT force the report to FAIL.
func TestValidateCorpus_MissingPlbReported(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(2000)
	buildCorpus(t, dir, true, testRuns, seed) // in-band, no .plb

	rep, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus: %v", err)
	}
	if len(rep.MissingPlb) != 1 || rep.MissingPlb[0] != "synth" {
		t.Fatalf("MissingPlb = %v, want [synth]", rep.MissingPlb)
	}
	if !rep.Pass {
		t.Error("a missing .plb must NOT force FAIL (in-band corpus should still pass)")
	}
	var buf bytes.Buffer
	WriteReport(&buf, rep)
	if !strings.Contains(buf.String(), "MISSING_PLB stem=synth") {
		t.Errorf("report should render a MISSING_PLB line:\n%s", buf.String())
	}
}
