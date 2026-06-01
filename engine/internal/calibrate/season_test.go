package calibrate

import (
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// Row: groupSeasons picks each season's last regular-type snapshot by lexical
// (sim-step) order, and flags+skips Olympics (out of scope).
func TestGroupSeasons_SelectsLastRegular(t *testing.T) {
	root := "/b"
	zips := []string{
		"/b/02-03/02-03_08_reg-sim01.zip",
		"/b/02-03/02-03_41_reg-sim35.zip",
		"/b/02-03/02-03_47_finals.zip", // playoff snapshot — ignored for the regular bucket
		"/b/88-89/88-89_30_reg-sim20.zip",
	}
	seasons, skips := groupSeasons(zips, root)
	if len(skips) != 0 {
		t.Fatalf("unexpected skips: %v", skips)
	}
	if len(seasons) != 2 {
		t.Fatalf("seasons = %d, want 2", len(seasons))
	}
	// Sorted by name: 02-03 then 88-89. The last regular snapshot wins; the
	// finals (playoff) snapshot is not selected.
	if s := seasons[0]; s.name != "02-03" || s.regularZip != "/b/02-03/02-03_41_reg-sim35.zip" {
		t.Errorf("02-03 regular selection wrong: %+v", s)
	}
	if s := seasons[1]; s.regularZip != "/b/88-89/88-89_30_reg-sim20.zip" {
		t.Errorf("88-89 regular selection wrong: %+v", s)
	}
}

// Row (negative): Olympics snapshots are flagged and recorded as Skips (not yet
// supported — unscheduled games), never selected as a regular snapshot.
func TestGroupSeasons_OlympicsSkipped(t *testing.T) {
	root := "/b"
	zips := []string{"/b/olympics/2003/oly_a.zip", "/b/olympics/2003/oly_b.zip"}
	seasons, skips := groupSeasons(zips, root)
	if len(skips) != 2 {
		t.Fatalf("olympics skips = %d, want 2", len(skips))
	}
	if len(seasons) != 1 || !seasons[0].olympics || seasons[0].regularZip != "" {
		t.Fatalf("olympics season wrong: %+v", seasons)
	}
}

// recordingValidate (defined in walk_test.go) records each validate call.

// Row: collectSeasonReports validates one regular report per season (the last
// regular snapshot) under GameTypeRegular.
func TestCollectSeasonReports_RegularPerSeason(t *testing.T) {
	root := t.TempDir()
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
	rv := &recordingValidate{t: t}
	reports, skips := collectSeasonReports(seasons, Options{Runs: 1}, rv.fn)
	if len(reports) != 0 || rv.calls != 0 || len(skips) != 1 {
		t.Fatalf("want zero reports/calls and one skip; reports=%d calls=%d skips=%v", len(reports), rv.calls, skips)
	}
}

// Row (sample-stride): with stride 2 over four seasons, only the 1st and 3rd
// are processed.
func TestCollectSeasonReports_SampleStride(t *testing.T) {
	root := t.TempDir()
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
