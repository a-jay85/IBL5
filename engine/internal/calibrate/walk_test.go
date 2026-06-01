package calibrate

import (
	"archive/zip"
	"os"
	"path/filepath"
	"sort"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// makeZip writes a zip at path whose entries are name->contents. Entry names may
// contain slashes (to exercise basename handling / zip-slip safety).
func makeZip(t *testing.T, path string, entries map[string]string) {
	t.Helper()
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	f, err := os.Create(path)
	if err != nil {
		t.Fatal(err)
	}
	defer func() { _ = f.Close() }()
	zw := zip.NewWriter(f)
	for name, body := range entries {
		w, err := zw.Create(name)
		if err != nil {
			t.Fatal(err)
		}
		if _, err := w.Write([]byte(body)); err != nil {
			t.Fatal(err)
		}
	}
	if err := zw.Close(); err != nil {
		t.Fatal(err)
	}
}

// fullTriple is the three members plus a junk file that must NOT be extracted.
func fullTriple() map[string]string {
	return map[string]string{
		"IBL5.plr": "plr-bytes",
		"IBL5.sch": "sch-bytes",
		"IBL5.sco": "sco-bytes",
		"IBL5.trn": "junk-should-not-extract",
	}
}

// recordingValidate is an injected ValidateFunc that captures, per call, the
// game type and the sorted basenames present in the temp dir at call time
// (the dir is deleted after processZip returns, so it must be inspected here).
type recordingValidate struct {
	t        *testing.T
	calls    int
	gameType []bundle.GameType
	contents [][]string
}

func (r *recordingValidate) fn(dir string, runs int, seed uint64, gt bundle.GameType) (validate.Report, error) {
	r.calls++
	r.gameType = append(r.gameType, gt)
	ents, err := os.ReadDir(dir)
	if err != nil {
		r.t.Fatalf("read temp dir: %v", err)
	}
	var names []string
	for _, e := range ents {
		names = append(names, e.Name())
	}
	sort.Strings(names)
	r.contents = append(r.contents, names)
	return validate.Report{GameType: gt, Pass: true}, nil
}

// Row #6: CollectReports extracts ONLY IBL5.{plr,sch,sco} (not the .trn junk),
// runs validate per zip with the inferred game type, and produces one report
// per qualifying zip.
func TestCollectReports_ExtractsTripleAndInfersType(t *testing.T) {
	root := t.TempDir()
	makeZip(t, filepath.Join(root, "02-03", "02-03_08_reg-sim01.zip"), fullTriple())
	makeZip(t, filepath.Join(root, "02-03", "02-03_47_finals.zip"), fullTriple())

	rv := &recordingValidate{t: t}
	reports, skips, err := CollectReports(root, Options{Runs: 1, Validate: rv.fn})
	if err != nil {
		t.Fatalf("CollectReports: %v", err)
	}
	if len(reports) != 2 || rv.calls != 2 {
		t.Fatalf("reports=%d calls=%d, want 2 / 2 (skips=%v)", len(reports), rv.calls, skips)
	}
	// Walk is lexical: reg-sim01 sorts before finals (… _08_ < _47_).
	if rv.gameType[0] != bundle.GameTypeRegular || rv.gameType[1] != bundle.GameTypePlayoff {
		t.Errorf("game types = %v, want [regular playoff]", rv.gameType)
	}
	for i, names := range rv.contents {
		if want := []string{"IBL5.plr", "IBL5.sch", "IBL5.sco"}; !equalStrings(names, want) {
			t.Errorf("call %d temp dir contents = %v, want exactly %v (junk must not extract)", i, names, want)
		}
	}
}

// Row #7 (negative): a non-zip archive (.rar) is recorded as a Skip and the walk
// continues to the readable zip.
func TestCollectReports_SkipsUnsupportedArchive(t *testing.T) {
	root := t.TempDir()
	if err := os.WriteFile(filepath.Join(root, "02-03_21_reg-sim14.rar"), []byte("rar"), 0o644); err != nil {
		t.Fatal(err)
	}
	makeZip(t, filepath.Join(root, "02-03_22_reg-sim15.zip"), fullTriple())

	rv := &recordingValidate{t: t}
	reports, skips, err := CollectReports(root, Options{Runs: 1, Validate: rv.fn})
	if err != nil {
		t.Fatalf("CollectReports: %v", err)
	}
	if len(reports) != 1 {
		t.Fatalf("reports = %d, want 1", len(reports))
	}
	if len(skips) != 1 || filepath.Ext(skips[0].Path) != ".rar" {
		t.Fatalf("skips = %v, want one .rar skip", skips)
	}
}

// Row #9 (negative): a zip missing a triple member is skipped (not validated),
// and the walk continues.
func TestCollectReports_SkipsMissingMember(t *testing.T) {
	root := t.TempDir()
	makeZip(t, filepath.Join(root, "a_reg-sim.zip"), map[string]string{
		"IBL5.plr": "p", "IBL5.sch": "s", // no .sco
	})
	makeZip(t, filepath.Join(root, "b_reg-sim.zip"), fullTriple())

	rv := &recordingValidate{t: t}
	reports, skips, err := CollectReports(root, Options{Runs: 1, Validate: rv.fn})
	if err != nil {
		t.Fatalf("CollectReports: %v", err)
	}
	if len(reports) != 1 || rv.calls != 1 {
		t.Fatalf("reports=%d calls=%d, want 1 / 1", len(reports), rv.calls)
	}
	if len(skips) != 1 || skips[0].Reason == "" {
		t.Fatalf("skips = %v, want one missing-member skip", skips)
	}
}

// Row #8 (security/negative): a zip entry whose name contains a traversal prefix
// is written by BASENAME ONLY, inside destDir — never escaping it.
func TestExtractTriple_ZipSlipSafe(t *testing.T) {
	root := t.TempDir()
	zipPath := filepath.Join(root, "evil_reg-sim.zip")
	makeZip(t, zipPath, map[string]string{
		"../../IBL5.plr": "p", // traversal attempt
		"IBL5.sch":       "s",
		"IBL5.sco":       "c",
	})
	dest := t.TempDir()

	found, err := extractTriple(zipPath, dest)
	if err != nil {
		t.Fatalf("extractTriple: %v", err)
	}
	if !found {
		t.Fatal("expected all three members found (basename match)")
	}
	if _, err := os.Stat(filepath.Join(dest, "IBL5.plr")); err != nil {
		t.Errorf("IBL5.plr should be written inside destDir: %v", err)
	}
	// The traversal target two levels up must NOT exist.
	escaped := filepath.Join(dest, "..", "..", "IBL5.plr")
	if _, err := os.Stat(escaped); err == nil {
		t.Errorf("zip-slip: file escaped to %s", escaped)
	}
}

// Row (sample-stride): with stride 2 over four qualifying zips, only the 1st and
// 3rd are processed.
func TestCollectReports_SampleStride(t *testing.T) {
	root := t.TempDir()
	for _, n := range []string{"a", "b", "c", "d"} {
		makeZip(t, filepath.Join(root, n+"_reg-sim.zip"), fullTriple())
	}
	rv := &recordingValidate{t: t}
	reports, _, err := CollectReports(root, Options{Runs: 1, SampleStride: 2, Validate: rv.fn})
	if err != nil {
		t.Fatalf("CollectReports: %v", err)
	}
	if len(reports) != 2 {
		t.Fatalf("reports = %d, want 2 (every 2nd of 4)", len(reports))
	}
}

// A non-existent root is a hard error, not a silent empty result.
func TestCollectReports_BadRoot(t *testing.T) {
	rv := &recordingValidate{t: t}
	if _, _, err := CollectReports(filepath.Join(t.TempDir(), "nope"), Options{Validate: rv.fn}); err == nil {
		t.Fatal("expected an error for a non-existent root")
	}
}

func equalStrings(a, b []string) bool {
	if len(a) != len(b) {
		return false
	}
	for i := range a {
		if a[i] != b[i] {
			return false
		}
	}
	return true
}
