//go:build archive

// This suite runs the calibration harness over the REAL ~53 GB JSB backup
// archive. It is build-tag gated behind `archive` so it is NEVER compiled by
// `go test ./...` or engine.yml — the corpus is large and not in the repo.
//
// Invoke manually (stride-thin to keep it tractable):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/calibrate -run RealArchive -v
//
// Override JSB_ARCHIVE_RUNS / JSB_ARCHIVE_STRIDE to trade runtime for fidelity.
package calibrate

import (
	"os"
	"strconv"
	"testing"
)

func TestRealArchive_CalibrateEndToEnd(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	runs := envInt("JSB_ARCHIVE_RUNS", 20)
	stride := envInt("JSB_ARCHIVE_STRIDE", 50)

	reports, skips, err := CollectReports(dir, Options{
		Runs:         runs,
		SampleStride: stride,
		Progress:     os.Stderr,
	})
	if err != nil {
		t.Fatalf("CollectReports over real archive: %v", err)
	}
	t.Logf("reports=%d skips=%d (runs=%d stride=%d)", len(reports), len(skips), runs, stride)
	if len(reports) == 0 {
		t.Fatal("expected at least one report from the real archive")
	}

	cal := Calibrate(reports, 0.95)
	if len(cal.Buckets) == 0 {
		t.Fatal("calibration produced no buckets")
	}
	for _, b := range cal.Buckets {
		t.Logf("game_type=%d stats=%d", b.GameType, len(b.Stats))
		if len(b.Stats) == 0 {
			t.Errorf("game_type=%d produced no stat calibrations", b.GameType)
		}
	}
}

func envInt(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil && n > 0 {
			return n
		}
	}
	return def
}
