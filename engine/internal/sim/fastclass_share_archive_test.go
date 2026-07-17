//go:build archive

// J24 fast-class arming-share diagnostic over the REAL ~53 GB JSB backup archive.
//
// Measures what fraction of possessions route through each J24 step class
// (steal-transition {0,1,2}s, DRB-push {2,3,4}s, half-court jitter). This is
// the instrument for closing the J24 Phase 5 NO-GO residual: the engine arms
// fast classes at ~29% of possessions vs real ~11.5% (~24 transition markers /
// ~209 possessions per game). No assertion failure — the test logs shares and
// writes a dated artifact for human interpretation.
//
// Reuses listZipsP0, readSnapshotP0, envIntP0 from
// possessionclock_baseline_archive_test.go (same package sim, same build tag).
// Do NOT redefine them — duplicate symbol error under -tags archive.
//
// Invoke manually (run in the background; do not poll):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_STRIDE=100 JSB_ARCHIVE_RUNS=4 \
//	  go test -tags archive ./internal/sim \
//	  -run TestFastClassArmingShareBaseline -v -timeout 300s
//
// STRIDE=100 gives a fast smoke (~minutes); STRIDE=1 is the full pass (~hours).
// Without JSB_ARCHIVE_DIR set (or the dir absent), the test skips — always 0 on CI.
package sim

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/a-jay85/IBL5/engine/internal/backup"
)

// fastClassShareArtifact is the committed diagnostic output from one archive pass.
type fastClassShareArtifact struct {
	Generated         string  `json:"generated"`
	Stride            int     `json:"stride"`
	Runs              int     `json:"runs"`
	Seed              uint64  `json:"seed"`
	Snapshots         int     `json:"snapshots"`
	TotalPossessions  int     `json:"total_possessions"`
	StealClass        int     `json:"steal_class"`
	DRBPushClass      int     `json:"drb_push_class"`
	HalfCourt         int     `json:"half_court"`
	StealSharePct     float64 `json:"steal_share_pct"`
	DRBPushSharePct   float64 `json:"drb_push_share_pct"`
	HalfCourtSharePct float64 `json:"half_court_share_pct"`
	// J24 residual: StealSharePct + DRBPushSharePct should approach ~11.5%.
	// Current engine (J24 Phase 5 NO-GO): ~29% — arming-share gap to close.
}

func TestFastClassArmingShareBaseline(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}
	stride := envIntP0("JSB_ARCHIVE_STRIDE", 100)
	if stride < 1 {
		stride = 1
	}
	runs := envIntP0("JSB_ARCHIVE_RUNS", 4)
	seed := uint64(envIntP0("JSB_ARCHIVE_SEED", 20240601))

	zips, err := listZipsP0(dir)
	if err != nil {
		t.Fatalf("list zips: %v", err)
	}
	if len(zips) == 0 {
		t.Skipf("no .zip snapshots under %q", dir)
	}

	var total FastClassAccum
	snapshots := 0

	for i := 0; i < len(zips); i += stride {
		players, sched, ok := readSnapshotP0(zips[i])
		if !ok {
			continue
		}
		b, err := backup.ToBundle(players, sched, backup.AssembleOptions{})
		if err != nil {
			continue
		}
		for run := 0; run < runs; run++ {
			acc := &FastClassAccum{}
			if _, err := SimulateWith(b, seed+uint64(run), Options{FastClassAccum: acc}); err != nil {
				continue
			}
			total.StealClass += acc.StealClass
			total.DRBPushClass += acc.DRBPushClass
			total.HalfCourt += acc.HalfCourt
			total.TotalPossessions += acc.TotalPossessions
		}
		snapshots++
	}

	if total.TotalPossessions == 0 {
		t.Fatal("no possessions counted over the archive pass — cannot measure class shares")
	}
	tot := float64(total.TotalPossessions)
	art := fastClassShareArtifact{
		Generated:         time.Now().Format(time.RFC3339),
		Stride:            stride,
		Runs:              runs,
		Seed:              seed,
		Snapshots:         snapshots,
		TotalPossessions:  total.TotalPossessions,
		StealClass:        total.StealClass,
		DRBPushClass:      total.DRBPushClass,
		HalfCourt:         total.HalfCourt,
		StealSharePct:     100 * float64(total.StealClass) / tot,
		DRBPushSharePct:   100 * float64(total.DRBPushClass) / tot,
		HalfCourtSharePct: 100 * float64(total.HalfCourt) / tot,
	}

	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-fastclass-share.json", time.Now().Format("20060102")))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	t.Logf("J24 FAST-CLASS ARMING-SHARE BASELINE (%d snapshots, %d runs, stride %d):",
		snapshots, runs, stride)
	t.Logf("  steal-class share:    %.2f%% (%d possessions)",
		art.StealSharePct, total.StealClass)
	t.Logf("  DRB-push-class share: %.2f%% (%d possessions)",
		art.DRBPushSharePct, total.DRBPushClass)
	t.Logf("  half-court share:     %.2f%% (%d possessions)",
		art.HalfCourtSharePct, total.HalfCourt)
	t.Logf("  J24 residual target: steal+DRB-push ≈ 11.5%% (current engine ~29%% — arming-share gap)")
}
