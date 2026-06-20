package validate

import (
	"bytes"
	"errors"
	"math"
	"os"
	"reflect"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/sim"
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

// Row #17 (characterization): ValidateCorpusWith(zero sim.Options{}) yields a Report
// byte-identical to ValidateCorpus — the OFF default of the Options passthrough leaves
// the existing calibration unchanged (SimulateWith(.,zero opts) == Simulate).
func TestValidateCorpusWith_OffDefaultUnchanged(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	buildCorpus(t, dir, true, testRuns, seed)

	base, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus: %v", err)
	}
	off, err := ValidateCorpusWith(dir, testRuns, seed, bundle.GameTypeRegular, sim.Options{})
	if err != nil {
		t.Fatalf("ValidateCorpusWith: %v", err)
	}
	if !reflect.DeepEqual(base, off) {
		t.Error("ValidateCorpusWith(zero Options) diverged from ValidateCorpus — OFF default not inert")
	}
}

// Row #4: validateGame stamps each GameReport with the home win-fraction over
// the seeded runs — a real value in [0,1]. Determinism across runs is already
// covered by the reflect.DeepEqual report comparison above (the field is part
// of the report); here we assert it is populated and well-formed.
func TestValidateCorpus_PopulatesHomeWinFraction(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	buildCorpus(t, dir, true, testRuns, seed)

	rep, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus: %v", err)
	}
	if len(rep.Games) != 1 {
		t.Fatalf("games = %d, want 1", len(rep.Games))
	}
	wf := rep.Games[0].EngineHomeWinFraction
	if wf < 0 || wf > 1 {
		t.Errorf("EngineHomeWinFraction = %v, want a fraction in [0,1]", wf)
	}
}

// Row #3: validateGame threads the ORB-intensity numerator and the engine-only
// continuation-depth tallies onto every GameReport. ScoORBPerG is the raw .sco box
// ORB — the synth corpus parks all rebounds in the ORB slot, so it equals the "reb"
// row's ScoVal exactly. EngineORBPerG is the engine mean ORB: a component of the
// engine's total rebounds and the SAME value possProxy subtracts. The
// continuation-depth buckets are populated for both teams and sum to N.
func TestValidateCorpus_PopulatesOrbAndContinuationDepth(t *testing.T) {
	dir := t.TempDir()
	const seed = uint64(1000)
	buildCorpus(t, dir, true, testRuns, seed)

	rep, err := ValidateCorpus(dir, testRuns, seed, bundle.GameTypeRegular)
	if err != nil {
		t.Fatalf("ValidateCorpus: %v", err)
	}
	if len(rep.Games) != 1 {
		t.Fatalf("games = %d, want 1", len(rep.Games))
	}
	g := rep.Games[0]
	if g.EngineORBPerG == nil || g.ScoORBPerG == nil || g.EngineContinuationDepth == nil {
		t.Fatalf("maps not populated: orbEng=%v orbSco=%v depth=%v", g.EngineORBPerG, g.ScoORBPerG, g.EngineContinuationDepth)
	}
	rebRow := func(id int) StatRow {
		for _, r := range g.Rows {
			if r.TeamID == id && r.Stat == "reb" {
				return r
			}
		}
		t.Fatalf("no reb row for team %d", id)
		return StatRow{}
	}
	for _, id := range []int{g.VisitorTeamID, g.HomeTeamID} {
		reb := rebRow(id)
		// The synth .sco parks all rebounds in ORB, so ScoORBPerG == the reb row exactly.
		if g.ScoORBPerG[id] != reb.ScoVal {
			t.Errorf("team %d ScoORBPerG = %v, want %v (raw .sco box ORB = the reb row)", id, g.ScoORBPerG[id], reb.ScoVal)
		}
		// Engine ORB is a component of engine total rebounds (the −ORB possProxy term):
		// finite and within [0, total reb].
		orb := g.EngineORBPerG[id]
		if math.IsNaN(orb) || math.IsInf(orb, 0) || orb < 0 || orb > reb.EngineMean+1e-9 {
			t.Errorf("team %d EngineORBPerG = %v, want a finite value in [0, reb=%v]", id, orb, reb.EngineMean)
		}
		// Continuation-depth tallies present and self-consistent: ΣBk == N (every
		// possession lands in exactly one bucket).
		d, ok := g.EngineContinuationDepth[id]
		if !ok {
			t.Errorf("team %d missing from EngineContinuationDepth", id)
			continue
		}
		if bsum := d.B0 + d.B1 + d.B2 + d.B3Plus; math.Abs(bsum-d.N) > 1e-9 {
			t.Errorf("team %d continuation buckets sum %v != N %v", id, bsum, d.N)
		}
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

// accumulatePossessions (ADR-0049) is the read-only possession-count instrument.
// Happy path: it tallies exactly the EventPossessionStart events per team.
func TestAccumulatePossessions(t *testing.T) {
	events := []result.Event{
		{Kind: result.EventPossessionStart, TeamID: 3},
		{Kind: result.EventShotAttempt, TeamID: 3, Origin: result.OriginInitial},
		{Kind: result.EventPossessionStart, TeamID: 7},
		{Kind: result.EventPossessionStart, TeamID: 3},
		{Kind: result.EventRebound, TeamID: 7, OffensiveRebound: true},
		{Kind: result.EventPossessionStart, TeamID: 3},
	}
	got := map[int]int{}
	accumulatePossessions(got, events)
	if got[3] != 3 || got[7] != 1 {
		t.Fatalf("possession counts = %v, want map[3:3 7:1]", got)
	}
	if len(got) != 2 {
		t.Errorf("unexpected extra team keys: %v", got)
	}
}

// possProxy (ADR-0049) is the Dean-Oliver true-possession estimate
// FGA + 0.44·FTA + TOV − ORB, run identically on both the engine and .sco box. The
// −ORB term is load-bearing: an offensive rebound extends a possession, so it must
// NOT inflate the count.
func TestPossProxy(t *testing.T) {
	ts := TeamStat{FGA: 88, FTA: 25, TOV: 13, ORB: 11}
	want := 88.0 + 0.44*25.0 + 13.0 - 11.0
	if got := possProxy(ts); got != want {
		t.Errorf("possProxy = %v, want %v (FGA+0.44·FTA+TOV−ORB)", got, want)
	}
	// ORB is subtracted, not ignored: a team with more ORB has FEWER true possessions
	// for the same shooting line.
	more := possProxy(TeamStat{FGA: 88, FTA: 25, TOV: 13, ORB: 20})
	if more >= want {
		t.Errorf("more ORB must lower the count: orb20=%v orb11=%v", more, want)
	}
}

// Boundary: a slice with no possession-starts (only other event kinds) and an
// empty slice both leave the counter untouched — only EventPossessionStart
// contributes, never a spurious team key.
func TestAccumulatePossessions_NoStarts(t *testing.T) {
	noStarts := []result.Event{
		{Kind: result.EventShotAttempt, TeamID: 3, Origin: result.OriginInitial},
		{Kind: result.EventRebound, TeamID: 7, OffensiveRebound: true},
		{Kind: result.EventFoul, TeamID: 3},
		{Kind: result.EventTurnover, TeamID: 7},
	}
	got := map[int]int{}
	accumulatePossessions(got, noStarts)
	if len(got) != 0 {
		t.Errorf("non-possession-start events must add no keys, got %v", got)
	}
	accumulatePossessions(got, nil)
	if len(got) != 0 {
		t.Errorf("empty event slice must add no keys, got %v", got)
	}
}

// accumulateOriginFGA (ADR-0053) tallies BOTH attempts (EventShotAttempt) and makes
// (EventShotMake) by shot origin, so a per-origin shooting efficiency is observable.
func TestAccumulateOriginFGA(t *testing.T) {
	events := []result.Event{
		{Kind: result.EventShotAttempt, TeamID: 3, Origin: result.OriginInitial},
		{Kind: result.EventShotMake, TeamID: 3, Origin: result.OriginInitial},
		{Kind: result.EventShotAttempt, TeamID: 3, Origin: result.OriginInitial}, // a miss (no make event)
		{Kind: result.EventShotAttempt, TeamID: 3, Origin: result.OriginOffReb},
		{Kind: result.EventShotMake, TeamID: 3, Origin: result.OriginOffReb},
		{Kind: result.EventShotAttempt, TeamID: 7, Origin: result.OriginTransition},
		{Kind: result.EventShotMake, TeamID: 7, Origin: result.OriginTransition},
	}
	got := map[int]*OriginFGA{}
	accumulateOriginFGA(got, events)
	o3 := got[3]
	if o3 == nil || o3.Initial != 2 || o3.InitialMade != 1 || o3.Oreb != 1 || o3.OrebMade != 1 {
		t.Fatalf("team 3 = %+v, want Initial=2 InitialMade=1 Oreb=1 OrebMade=1", o3)
	}
	if o7 := got[7]; o7 == nil || o7.Transition != 1 || o7.TransitionMade != 1 {
		t.Fatalf("team 7 = %+v, want Transition=1 TransitionMade=1", o7)
	}
}

// Boundary — the empty-FGA-loop signature: OriginOffReb attempts with ZERO makes
// (every putback misses) yields OrebMade==0 while Oreb>0. This is exactly the
// efficiency↔volume coupling the ADR-0053 decoupling arm targets.
func TestAccumulateOriginFGA_PutbackMissesOnly(t *testing.T) {
	events := []result.Event{
		{Kind: result.EventShotAttempt, TeamID: 5, Origin: result.OriginOffReb},
		{Kind: result.EventShotMiss, TeamID: 5, Origin: result.OriginOffReb},
		{Kind: result.EventShotAttempt, TeamID: 5, Origin: result.OriginOffReb},
		{Kind: result.EventShotMiss, TeamID: 5, Origin: result.OriginOffReb},
	}
	got := map[int]*OriginFGA{}
	accumulateOriginFGA(got, events)
	if o := got[5]; o == nil || o.Oreb != 2 || o.OrebMade != 0 {
		t.Fatalf("team 5 = %+v, want Oreb=2 OrebMade=0 (empty-loop signature)", got[5])
	}
}

// accumulateContinuationDepth (Part B continuation-chain instrument) segments the
// event stream into possessions and folds each possession's offensive-rebound depth
// k into the team that OPENED it. The fixture interleaves TWO teams so a curTeam
// misattribution would be caught, and exercises every bucket including k≥3:
//
//	poss A — team 3, k=0 (a bare initial attempt)
//	poss B — team 7, k=1 (one ORB; the following OriginOffReb putback ATTEMPT must
//	         NOT increment depth — only the rebound event does)
//	poss C — team 3, k=2 (two ORBs; an interleaved DEFENSIVE rebound must NOT count)
//	poss D — team 3, k=3 (three ORBs; ends at slice end — the trailing possession is
//	         still folded)
func TestAccumulateContinuationDepth(t *testing.T) {
	events := []result.Event{
		// poss A — team 3, k=0
		{Kind: result.EventPossessionStart, TeamID: 3},
		{Kind: result.EventShotAttempt, TeamID: 3, Origin: result.OriginInitial},
		// poss B — team 7, k=1
		{Kind: result.EventPossessionStart, TeamID: 7},
		{Kind: result.EventRebound, TeamID: 7, OffensiveRebound: true},
		{Kind: result.EventShotAttempt, TeamID: 7, Origin: result.OriginOffReb}, // putback attempt: NOT a continuation
		// poss C — team 3, k=2
		{Kind: result.EventPossessionStart, TeamID: 3},
		{Kind: result.EventRebound, TeamID: 3, OffensiveRebound: true},
		{Kind: result.EventShotMiss, TeamID: 3, Origin: result.OriginOffReb},
		{Kind: result.EventRebound, TeamID: 3, OffensiveRebound: true},
		{Kind: result.EventRebound, TeamID: 7, OffensiveRebound: false}, // defensive: NOT a continuation
		// poss D — team 3, k=3 (slice-end fold)
		{Kind: result.EventPossessionStart, TeamID: 3},
		{Kind: result.EventRebound, TeamID: 3, OffensiveRebound: true},
		{Kind: result.EventRebound, TeamID: 3, OffensiveRebound: true},
		{Kind: result.EventRebound, TeamID: 3, OffensiveRebound: true},
	}
	got := map[int]*depthAcc{}
	accumulateContinuationDepth(got, events)

	if len(got) != 2 {
		t.Fatalf("team keys = %v, want exactly {3, 7}", got)
	}
	// team 3: possessions k = {0, 2, 3} → n=3, Σk=5, Σk²=0+4+9=13, buckets b0=1,b2=1,b3=1.
	a3 := got[3]
	if a3.n != 3 || a3.sumK != 5 || a3.sumK2 != 13 {
		t.Errorf("team 3 moments = n%d sumK%v sumK2%v, want n3 sumK5 sumK2 13", a3.n, a3.sumK, a3.sumK2)
	}
	if a3.b0 != 1 || a3.b1 != 0 || a3.b2 != 1 || a3.b3plus != 1 {
		t.Errorf("team 3 buckets = b0%d b1%d b2%d b3plus%d, want 1/0/1/1", a3.b0, a3.b1, a3.b2, a3.b3plus)
	}
	// team 7: a single k=1 possession → b1 holds it (NOT b2 — the putback attempt did
	// not increment depth).
	a7 := got[7]
	if a7.n != 1 || a7.sumK != 1 || a7.sumK2 != 1 {
		t.Errorf("team 7 moments = n%d sumK%v sumK2%v, want n1 sumK1 sumK2 1", a7.n, a7.sumK, a7.sumK2)
	}
	if a7.b1 != 1 || a7.b2 != 0 {
		t.Errorf("team 7 buckets = b1%d b2%d, want b1=1 b2=0 (the OriginOffReb attempt is not a continuation)", a7.b1, a7.b2)
	}
	// The Σk² invariant: exact mean = Σk/n and Var = Σk²/n − mean² (NEVER from buckets).
	mean3 := a3.sumK / float64(a3.n)
	var3 := a3.sumK2/float64(a3.n) - mean3*mean3
	if math.Abs(mean3-5.0/3.0) > 1e-12 || math.Abs(var3-14.0/9.0) > 1e-12 {
		t.Errorf("team 3 mean=%v var=%v, want 5/3 and 14/9 (Σk²/n − mean²)", mean3, var3)
	}
}

// Boundary: an empty / nil event slice produces no team keys (no spurious
// accumulators), exactly like accumulatePossessions.
func TestAccumulateContinuationDepth_Empty(t *testing.T) {
	got := map[int]*depthAcc{}
	accumulateContinuationDepth(got, nil)
	accumulateContinuationDepth(got, []result.Event{})
	if len(got) != 0 {
		t.Errorf("empty/nil slices must add no keys, got %v", got)
	}
	// A stream with offensive rebounds but NO possession start opens no possession,
	// so nothing is folded (the rebounds belong to no trip).
	accumulateContinuationDepth(got, []result.Event{
		{Kind: result.EventRebound, TeamID: 3, OffensiveRebound: true},
	})
	if len(got) != 0 {
		t.Errorf("rebounds with no possession start must add no keys, got %v", got)
	}
}

// TestSimulateGameMeans_GateCont — simulateGameMeans surfaces the L1 gate-1
// counterfactual per team (ADR-0057/0058): both teams populated, all fields finite,
// MeanProd ≤ MeanG2 (gate-1 reduces), N>0.
func TestSimulateGameMeans_GateCont(t *testing.T) {
	dir := t.TempDir()
	plr, sch, _ := corpusPaths(dir, "synth")
	writePlr(t, plr, starterSpecs())
	writeSch(t, sch, []schSpec{{vis: 7, home: 3, vScore: 1, hScore: 1}})
	b := assembleSynthBundle(t, plr, sch)

	_, _, _, _, _, _, _, _, gateContPerG := simulateGameMeans(b, b.Schedule[0], testRuns, 1, sim.Options{})
	for _, id := range []int{7, 3} {
		gc, ok := gateContPerG[id]
		if !ok {
			t.Errorf("team %d missing from EngineGateCont", id)
			continue
		}
		for name, v := range map[string]float64{
			"N": gc.N, "MeanG1": gc.MeanG1, "MeanG2": gc.MeanG2, "MeanProd": gc.MeanProd,
			"MeanOffStr": gc.MeanOffStr, "MeanDefStr": gc.MeanDefStr,
		} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Errorf("team %d: %s non-finite (%v)", id, name, v)
			}
		}
		if gc.N <= 0 {
			t.Errorf("team %d: N=%v, want > 0", id, gc.N)
		}
		if gc.MeanProd > gc.MeanG2+1e-9 {
			t.Errorf("team %d: MeanProd %v > MeanG2 %v (gate-1 must reduce)", id, gc.MeanProd, gc.MeanG2)
		}
	}

	// NEGATIVE/edge: a matchup whose home team has no roster never resolves a
	// possession, so no gate samples are recorded — no panic, no NaN, no spurious keys.
	edge := bundle.Game{VisitorTeamID: 7, HomeTeamID: 99999, Date: "1988-11-04", GameType: bundle.GameTypeRegular}
	_, _, _, _, _, _, _, _, edgeGate := simulateGameMeans(b, edge, testRuns, 1, sim.Options{})
	if len(edgeGate) != 0 {
		t.Errorf("rosterless matchup should record no gate samples, got %v", edgeGate)
	}
}
