package calibrate

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

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

// Row #6: groupSeasons picks each season's last regular-type snapshot for the
// regular bucket AND its last snapshot overall (the finals) for the playoff
// bucket; the finals selection is distinct from the regular one when a playoff
// snapshot exists. Olympics are flagged+skipped (out of scope).
func TestGroupSeasons_SelectsLastRegular(t *testing.T) {
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
	// Sorted by name: 02-03 then 88-89. The last regular snapshot is the regular
	// bucket; the finals snapshot is the (distinct) playoff bucket.
	if s := seasons[0]; s.name != "02-03" ||
		s.regularZip != "/b/02-03/02-03_41_reg-sim35.zip" ||
		s.finalsZip != "/b/02-03/02-03_47_finals.zip" {
		t.Errorf("02-03 selection wrong: %+v", s)
	}
	// 88-89 has no finals snapshot: finalsZip == regularZip, so no playoff bucket.
	if s := seasons[1]; s.regularZip != "/b/88-89/88-89_30_reg-sim20.zip" ||
		s.finalsZip != s.regularZip {
		t.Errorf("88-89 selection wrong (expected no distinct finals): %+v", s)
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
	if len(seasons) != 1 || !seasons[0].olympics || seasons[0].regularZip != "" || seasons[0].finalsZip != "" {
		t.Fatalf("olympics season wrong: %+v", seasons)
	}
}

// recordingValidate (defined in walk_test.go) records each validate call.

// Row: collectSeasonReports validates one regular report per season (the last
// regular snapshot) under GameTypeRegular.
func TestCollectSeasonReports_RegularPerSeason(t *testing.T) {
	root := neutralTempDir(t)
	makeZip(t, filepath.Join(root, "02-03", "02-03_41_reg-sim35.zip"), fullTriple())
	makeZip(t, filepath.Join(root, "88-89", "88-89_30_reg-sim20.zip"), fullTriple())

	rv := &recordingValidate{t: t}
	reports, skips, err := CollectSeasonReports(root, Options{Runs: 1, Validate: rv.fn})
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
	seasons := []season{{name: "x", regularZip: ""}}
	rvReg := &recordingValidate{t: t}
	rvPof := &recordingValidate{t: t}
	reports, skips := collectSeasonReports(seasons, Options{Runs: 1}, rvReg.fn, rvPof.fn)
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
		Runs: 1, Validate: rvReg.fn, ValidateUnscheduled: rvPof.fn,
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
		Runs: 1, Validate: rvReg.fn, ValidateUnscheduled: rvPof.fn,
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
	reports, _, err := CollectSeasonReports(root, Options{Runs: 1, SampleStride: 2, Validate: rv.fn})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reports) != 2 {
		t.Fatalf("reports = %d, want 2 (every 2nd of 4)", len(reports))
	}
}
