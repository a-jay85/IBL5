package calibrate

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// validateWithArms (ADR-0053): without an arm it is a single passthrough whose
// sim.Options carries only the Branch-B toggle; with an arm it is a per-bucket
// two-pass — a harvest pass (Accum set, arm off) then a frozen pass (arm on, Means
// sourced from the harvest accum), both at the SAME seed. A fresh accum is allocated
// per closure invocation (per bucket), never shared across buckets.
func TestValidateWithArms(t *testing.T) {
	type call struct {
		seed uint64
		opts sim.Options
	}
	newFn := func(calls *[]call) func(string, int, uint64, bundle.GameType, sim.Options) (validate.Report, error) {
		return func(_ string, _ int, seed uint64, _ bundle.GameType, o sim.Options) (validate.Report, error) {
			*calls = append(*calls, call{seed: seed, opts: o})
			return validate.Report{}, nil
		}
	}

	t.Run("no arm — single Branch-B passthrough", func(t *testing.T) {
		var calls []call
		fn := validateWithArms(Options{BranchB: true}, newFn(&calls))
		if _, err := fn("dir", 5, 99, bundle.GameTypeRegular); err != nil {
			t.Fatalf("fn: %v", err)
		}
		if len(calls) != 1 {
			t.Fatalf("calls=%d, want 1 (no two-pass without an arm)", len(calls))
		}
		o := calls[0].opts
		if !o.Freeze.BranchB || o.Freeze.MakePutback || o.Freeze.MakePutbackHalf || o.Accum != nil {
			t.Errorf("opts=%+v, want BranchB only", o.Freeze)
		}
	})

	t.Run("MakePutback — harvest then frozen, same seed", func(t *testing.T) {
		var calls []call
		fn := validateWithArms(Options{MakePutback: true}, newFn(&calls))
		if _, err := fn("dir", 5, 99, bundle.GameTypeRegular); err != nil {
			t.Fatalf("fn: %v", err)
		}
		if len(calls) != 2 {
			t.Fatalf("calls=%d, want 2 (harvest + frozen)", len(calls))
		}
		harvest, frozen := calls[0], calls[1]
		if harvest.opts.Accum == nil || harvest.opts.Freeze.MakePutback {
			t.Errorf("harvest opts=%+v, want Accum set and arm OFF", harvest.opts)
		}
		// The frozen pass turns the arm on and sources Means from the SAME harvest accum
		// (acc.Means()), so a future refactor cannot silently feed it a different mean.
		if !frozen.opts.Freeze.MakePutback {
			t.Errorf("frozen arm off, want MakePutback on: %+v", frozen.opts.Freeze)
		}
		if frozen.opts.Freeze.Means != harvest.opts.Accum.Means() {
			t.Errorf("frozen Means=%+v, want the harvest accum's Means()=%+v", frozen.opts.Freeze.Means, harvest.opts.Accum.Means())
		}
		if harvest.seed != frozen.seed {
			t.Errorf("seeds differ: harvest=%d frozen=%d (must match so the arm perturbs the harvested games)", harvest.seed, frozen.seed)
		}
	})
}

// okCount is a CountScoFunc stub reporting a complete season (1148 games / 28
// teams → medGP≈82) for any path, so the public CollectSeasonReports tests that
// build placeholder "sco-bytes" zips clear the medGP floor without a real .sco.
func okCount(string) (int, int, error) { return 1148, 28, nil }

// countMap is a CountScoFunc stub returning per-path (games, teams) from a map;
// an unknown path returns (0, 0) (medGP 0 → below the floor).
func countMap(m map[string][2]int) CountScoFunc {
	return func(p string) (int, int, error) {
		if gt, ok := m[p]; ok {
			return gt[0], gt[1], nil
		}
		return 0, 0, nil
	}
}

// neutralTempDir returns a temp dir whose path contains none of the substrings
// inferGameType keys on ("finals"/"playoff"/"olympics"). t.TempDir() embeds the
// test name, so a test named for the playoff bucket would otherwise poison every
// snapshot's inferred game type via its own path. Production archive roots are
// clean, so this only matters for trigger-worded test names.
func neutralTempDir(t *testing.T) string {
	t.Helper()
	dir, err := os.MkdirTemp("", "jsbcal-test-")
	if err != nil {
		t.Fatalf("mkdir temp: %v", err)
	}
	t.Cleanup(func() { _ = os.RemoveAll(dir) })
	return dir
}

// groupSeasons collects EVERY regular-type snapshot per season (in lexical
// order) as a candidate, and the last snapshot overall (the finals) for the
// playoff bucket. The most-complete candidate is chosen later, in
// collectSeasonReports. Olympics are flagged+skipped (out of scope).
func TestGroupSeasons_CollectsRegularCandidates(t *testing.T) {
	root := "/b"
	zips := []string{
		"/b/02-03/02-03_08_reg-sim01.zip",
		"/b/02-03/02-03_41_reg-sim35.zip",
		"/b/02-03/02-03_47_finals.zip", // playoff snapshot — the finals (last overall)
		"/b/88-89/88-89_30_reg-sim20.zip",
	}
	seasons, skips := groupSeasons(zips, root)
	if len(skips) != 0 {
		t.Fatalf("unexpected skips: %v", skips)
	}
	if len(seasons) != 2 {
		t.Fatalf("seasons = %d, want 2", len(seasons))
	}
	// Sorted by name: 02-03 then 88-89. Both reg-sim snapshots are regular
	// candidates (the finals is NOT); the finals snapshot is the playoff bucket.
	wantCands := []string{"/b/02-03/02-03_08_reg-sim01.zip", "/b/02-03/02-03_41_reg-sim35.zip"}
	if s := seasons[0]; s.name != "02-03" ||
		!equalStrings(s.regularCandidates, wantCands) ||
		s.finalsZip != "/b/02-03/02-03_47_finals.zip" {
		t.Errorf("02-03 selection wrong: %+v", s)
	}
	// 88-89 has no finals snapshot: finalsZip == its only regular snapshot (which,
	// being regular-typed, forms no playoff bucket in collectSeasonReports).
	if s := seasons[1]; !equalStrings(s.regularCandidates, []string{"/b/88-89/88-89_30_reg-sim20.zip"}) ||
		s.finalsZip != "/b/88-89/88-89_30_reg-sim20.zip" {
		t.Errorf("88-89 selection wrong: %+v", s)
	}
}

// Row #2 (post-impl): selection picks the candidate whose .sco holds the MOST
// games — the cumulative-completeness signal — regardless of path sort order.
// Here the 1148-game candidate sorts in the MIDDLE (reg-sim15), between a
// 549-game first (reg-sim05) and a 49-game last (reg-sim85), so a max-games
// pick is observably different from both a first- and a last-by-path pick. This
// is the fix for the lexical-last bug locked by the pre-impl characterization.
func TestCollectSeasonReports_SelectsMostCompleteRegular(t *testing.T) {
	root := neutralTempDir(t)
	first := filepath.Join(root, "06-07", "06-07_10_reg-sim05.zip")  // sorts first; 549 games
	middle := filepath.Join(root, "06-07", "06-07_25_reg-sim15.zip") // sorts middle; 1148 games (max)
	last := filepath.Join(root, "06-07", "06-07_95_reg-sim85.zip")   // sorts last; 49 games
	for _, p := range []string{first, middle, last} {
		makeZip(t, p, fullTriple())
	}

	var progress bytes.Buffer
	rv := &recordingValidate{t: t}
	reports, skips, err := CollectSeasonReports(root, Options{
		Runs:     1,
		Validate: rv.fn,
		Progress: &progress,
		CountSco: countMap(map[string][2]int{
			first:  {549, 28},
			middle: {1148, 28},
			last:   {49, 28},
		}),
	})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reports) != 1 || rv.calls != 1 {
		t.Fatalf("reports=%d calls=%d, want 1 / 1 (skips=%v)", len(reports), rv.calls, skips)
	}
	out := progress.String()
	if !strings.Contains(out, "regular "+middle+" ") {
		t.Errorf("progress did not select the max-games (middle) candidate:\n%s", out)
	}
	if strings.Contains(out, first) || strings.Contains(out, last) {
		t.Errorf("progress selected a non-max candidate:\n%s", out)
	}
}

// Row (negative): Olympics snapshots are flagged and recorded as Skips (not yet
// supported — national-team rosters), never selected as a regular/finals snapshot.
func TestGroupSeasons_OlympicsSkipped(t *testing.T) {
	root := "/b"
	zips := []string{"/b/olympics/2003/oly_a.zip", "/b/olympics/2003/oly_b.zip"}
	seasons, skips := groupSeasons(zips, root)
	if len(skips) != 2 {
		t.Fatalf("olympics skips = %d, want 2", len(skips))
	}
	if len(seasons) != 1 || !seasons[0].olympics || len(seasons[0].regularCandidates) != 0 || seasons[0].finalsZip != "" {
		t.Fatalf("olympics season wrong: %+v", seasons)
	}
}

// recordingValidate (defined in walk_test.go) records each validate call.

// Row: collectSeasonReports validates one regular report per season (the
// most-complete regular snapshot) under GameTypeRegular.
func TestCollectSeasonReports_RegularPerSeason(t *testing.T) {
	root := neutralTempDir(t)
	makeZip(t, filepath.Join(root, "02-03", "02-03_41_reg-sim35.zip"), fullTriple())
	makeZip(t, filepath.Join(root, "88-89", "88-89_30_reg-sim20.zip"), fullTriple())

	rv := &recordingValidate{t: t}
	reports, skips, err := CollectSeasonReports(root, Options{Runs: 1, Validate: rv.fn, CountSco: okCount})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reports) != 2 || rv.calls != 2 {
		t.Fatalf("reports=%d calls=%d, want 2 / 2 (skips=%v)", len(reports), rv.calls, skips)
	}
	for i, gt := range rv.gameType {
		if gt != bundle.GameTypeRegular {
			t.Errorf("call %d game type = %d, want regular", i, int(gt))
		}
	}
}

// Row (negative): a season with no regular-type snapshot is skipped.
func TestCollectSeasonReports_NoRegularSnapshotSkips(t *testing.T) {
	seasons := []season{{name: "x", regularCandidates: nil}}
	rvReg := &recordingValidate{t: t}
	rvPof := &recordingValidate{t: t}
	reports, skips := collectSeasonReports(seasons, Options{Runs: 1}, rvReg.fn, rvPof.fn, okCount)
	if len(reports) != 0 || rvReg.calls != 0 || rvPof.calls != 0 || len(skips) != 1 {
		t.Fatalf("want zero reports/calls and one skip; reports=%d reg=%d pof=%d skips=%v",
			len(reports), rvReg.calls, rvPof.calls, skips)
	}
}

// Row #7: a season with a distinct finals snapshot emits TWO reports — a
// regular (type 2) via the scheduled path and a playoff (type 4) via the
// unscheduled path, the latter routed the finals snapshot.
func TestCollectSeasonReports_RegularAndPlayoffBuckets(t *testing.T) {
	root := neutralTempDir(t)
	makeZip(t, filepath.Join(root, "02-03", "02-03_41_reg-sim35.zip"), fullTriple())
	makeZip(t, filepath.Join(root, "02-03", "02-03_47_finals.zip"), fullTriple())

	rvReg := &recordingValidate{t: t}
	rvPof := &recordingValidate{t: t}
	reports, skips, err := CollectSeasonReports(root, Options{
		Runs: 1, Validate: rvReg.fn, ValidateUnscheduled: rvPof.fn, CountSco: okCount,
	})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reports) != 2 {
		t.Fatalf("reports = %d, want 2 (regular + playoff) (skips=%v)", len(reports), skips)
	}
	if rvReg.calls != 1 || rvPof.calls != 1 {
		t.Fatalf("calls reg=%d pof=%d, want 1 / 1", rvReg.calls, rvPof.calls)
	}
	if rvReg.gameType[0] != bundle.GameTypeRegular {
		t.Errorf("regular call game type = %d, want regular (2)", int(rvReg.gameType[0]))
	}
	if rvPof.gameType[0] != bundle.GameTypePlayoff {
		t.Errorf("playoff call game type = %d, want playoff (4)", int(rvPof.gameType[0]))
	}
	// Reports are appended regular-then-playoff per season.
	if reports[0].GameType != bundle.GameTypeRegular || reports[1].GameType != bundle.GameTypePlayoff {
		t.Errorf("report game types = [%d %d], want [2 4]", int(reports[0].GameType), int(reports[1].GameType))
	}
}

// Row #8 (negative/boundary): a season whose finals == last-regular snapshot
// (no playoffs captured) emits only the regular report — the unscheduled path
// is never invoked.
func TestCollectSeasonReports_NoFinalsEmitsOnlyRegular(t *testing.T) {
	root := neutralTempDir(t)
	makeZip(t, filepath.Join(root, "02-03", "02-03_41_reg-sim35.zip"), fullTriple())

	rvReg := &recordingValidate{t: t}
	rvPof := &recordingValidate{t: t}
	reports, skips, err := CollectSeasonReports(root, Options{
		Runs: 1, Validate: rvReg.fn, ValidateUnscheduled: rvPof.fn, CountSco: okCount,
	})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reports) != 1 || rvReg.calls != 1 || rvPof.calls != 0 {
		t.Fatalf("reports=%d reg=%d pof=%d, want 1 / 1 / 0 (skips=%v)", len(reports), rvReg.calls, rvPof.calls, skips)
	}
}

// Row (sample-stride): with stride 2 over four seasons, only the 1st and 3rd
// are processed.
func TestCollectSeasonReports_SampleStride(t *testing.T) {
	root := neutralTempDir(t)
	for _, n := range []string{"a", "b", "c", "d"} {
		makeZip(t, filepath.Join(root, n, n+"_reg-sim.zip"), fullTriple())
	}
	rv := &recordingValidate{t: t}
	reports, _, err := CollectSeasonReports(root, Options{Runs: 1, SampleStride: 2, Validate: rv.fn, CountSco: okCount})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reports) != 2 {
		t.Fatalf("reports = %d, want 2 (every 2nd of 4)", len(reports))
	}
}

// Row #6: the season collector stamps each report's Label — the season name for
// the regular bucket, "<name> (playoffs)" for the playoff bucket.
func TestCollectSeasonReports_StampsLabel(t *testing.T) {
	root := neutralTempDir(t)
	makeZip(t, filepath.Join(root, "02-03", "02-03_41_reg-sim35.zip"), fullTriple())
	makeZip(t, filepath.Join(root, "02-03", "02-03_47_finals.zip"), fullTriple())

	rvReg := &recordingValidate{t: t}
	rvPof := &recordingValidate{t: t}
	reports, _, err := CollectSeasonReports(root, Options{
		Runs: 1, Validate: rvReg.fn, ValidateUnscheduled: rvPof.fn, CountSco: okCount,
	})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reports) != 2 {
		t.Fatalf("reports = %d, want 2", len(reports))
	}
	if reports[0].Label != "02-03" {
		t.Errorf("regular label = %q, want %q", reports[0].Label, "02-03")
	}
	if reports[1].Label != "02-03 (playoffs)" {
		t.Errorf("playoff label = %q, want %q", reports[1].Label, "02-03 (playoffs)")
	}
}

// Rows #3 & #4: the medGP sanity floor. A season whose best regular candidate has
// proxy medGP (2*games/teams) below minSeasonMedianGP (70) is skipped with a Skip
// and produces NO report; a complete season (medGP≈82) is kept. The boundary is
// exact: 70.0 is kept (not < 70), 69.9 is skipped.
func TestCollectSeasonReports_MedGPFloor(t *testing.T) {
	cases := []struct {
		name       string
		games      int
		teams      int
		wantReport bool
	}{
		{"below-floor", 49, 28, false},          // medGP 3.5  — row #3 (49 games / 28 teams)
		{"just-below-boundary", 979, 28, false}, // medGP 69.93 — just under the floor
		{"at-boundary", 980, 28, true},          // medGP 70.0  — exactly at the floor (kept)
		{"above-floor", 1148, 28, true},         // medGP 82    — row #4 (complete season)
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			root := neutralTempDir(t)
			zipPath := filepath.Join(root, "06-07", "06-07_50_reg-sim40.zip")
			makeZip(t, zipPath, fullTriple())
			seasons := []season{{name: "06-07", regularCandidates: []string{zipPath}}}

			rvReg := &recordingValidate{t: t}
			rvPof := &recordingValidate{t: t}
			reports, skips := collectSeasonReports(
				seasons, Options{Runs: 1}, rvReg.fn, rvPof.fn,
				countMap(map[string][2]int{zipPath: {tc.games, tc.teams}}),
			)
			if tc.wantReport {
				if len(reports) != 1 || rvReg.calls != 1 {
					t.Fatalf("reports=%d calls=%d, want 1/1 (medGP above floor) skips=%v", len(reports), rvReg.calls, skips)
				}
				return
			}
			// Below the floor: no report, no validate call, and a Skip naming the season.
			if len(reports) != 0 || rvReg.calls != 0 {
				t.Fatalf("reports=%d calls=%d, want 0/0 (medGP below floor)", len(reports), rvReg.calls)
			}
			if len(skips) != 1 || skips[0].Path != "06-07" || !strings.Contains(skips[0].Reason, "incomplete") {
				t.Fatalf("want one incomplete-season skip for 06-07, got %v", skips)
			}
		})
	}
}
