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

	"github.com/a-jay85/IBL5/engine/internal/bundle"
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

	reports, skips, err := CollectSeasonReports(dir, Options{
		Runs:         runs,
		SampleStride: stride,
		Progress:     os.Stderr,
	})
	if err != nil {
		t.Fatalf("CollectSeasonReports over real archive: %v", err)
	}
	t.Logf("reports=%d skips=%d (runs=%d stride=%d)", len(reports), len(skips), runs, stride)
	if len(reports) == 0 {
		t.Fatal("expected at least one report from the real archive")
	}

	// A selected season's finals snapshot may itself contain no playoff games
	// (e.g. its last snapshot is an end-of-season capture taken before the
	// postseason, observed on 95-96), so the stride-thinned sample only WARRANTS
	// a playoff bucket when it actually produced ≥1 playoff game. Gate the
	// assertion on that, so the test cannot false-negative on correct code.
	playoffGamesSampled := 0
	for _, rep := range reports {
		if rep.GameType == bundle.GameTypePlayoff {
			playoffGamesSampled += len(rep.Games)
		}
	}

	cal := Calibrate(reports, 0.95)
	if len(cal.Buckets) == 0 {
		t.Fatal("calibration produced no buckets")
	}
	var sawRegular, sawPlayoff bool
	for _, b := range cal.Buckets {
		t.Logf("game_type=%d stats=%d", b.GameType, len(b.Stats))
		if len(b.Stats) == 0 {
			t.Errorf("game_type=%d produced no stat calibrations", b.GameType)
		}
		switch b.GameType {
		case int(bundle.GameTypeRegular):
			sawRegular = true
		case int(bundle.GameTypePlayoff):
			sawPlayoff = true
		}
	}
	if !sawRegular {
		t.Error("expected a regular (game_type=2) bucket")
	}
	// PR9d: the playoff bucket comes from each selected season's finals snapshot
	// via ValidateUnscheduled. Require a game_type=4 bucket with non-empty bands
	// ONLY when the sample actually contained playoff games (otherwise the
	// stride may have landed only on no-playoff finals snapshots — see above).
	t.Logf("playoff games sampled=%d", playoffGamesSampled)
	if playoffGamesSampled > 0 && !sawPlayoff {
		t.Error("sample contained playoff games but produced no playoff (game_type=4) bucket (PR9d)")
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
