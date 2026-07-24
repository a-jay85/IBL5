//go:build archive

// J24 fast-class arming-share diagnostic over the REAL ~53 GB JSB backup archive.
//
// Measures what fraction of possessions route through each J24 step class
// (DRBPushClass = merged code-7 {2,3,4}s gated survivors from steal- AND
// DRB-sourced fast breaks per §1d; HalfCourt = half-court jitter). No assertion
// failure — the test logs shares and writes a dated artifact for human
// interpretation. Gate-1 recent-era drift band: DRBPushSharePct ∈ [11.97, 12.54]
// @216.58 poss/g (supersedes the all-era 209.2-denominated [12.94, 13.41]).
//
// Master reads 12.4142% (seed SD 0.0210, SE 0.0079, n=7 disjoint seed blocks;
// matched 98-zip recent-era 05-08 corpus, stride 1, commit 77b9be48b, 2026-07-24)
// — INSIDE the band but −0.73 SE BELOW ADR-0090's ≥12.42 re-open bar, so the
// cut-over HOLD stands. This supersedes ADR-0088's 12.37%.
//
// The matched 98-zip corpus is canonical here BECAUSE it reproduces ADR-0088's
// config; the current 100-zip corpus gives a seed-paired 12.4277, which clears
// 12.42 but is still below ADR-0094's √2-shrink floor 12.4216. Verdict unchanged
// on either corpus — see the multiseed artifact for both arms.
//
// ⚠ THIS ARTIFACT DOES NOT RECORD ITS CORPUS (backlog J26). A single run's
// drb_push_share_pct is meaningless without the era + zip set it was measured on,
// and the numbers above are a 7-seed MEAN — one run will not reproduce them
// (observed seed SD 0.0210pp; runs=4 consumes seed..seed+3, so replication seeds
// must be spaced ≥4 apart or they share draws). Corpus, seed blocks, floors, and
// the bisect attribution live out-of-band in
// internal/validate/testdata/calibration-5.60-20260724-fastclass-share-matched-multiseed.json.
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
	DRBPushClass      int     `json:"drb_push_class"`
	HalfCourt         int     `json:"half_court"`
	DRBPushSharePct   float64 `json:"drb_push_share_pct"`
	HalfCourtSharePct float64 `json:"half_court_share_pct"`
	// J24 gate-1 share: DRBPushSharePct is the single band-comparable code-7 share
	// (gated {2,3,4}s survivors, steal- AND DRB-sourced merged per §1d). Recent-era
	// between-season drift band [11.97, 12.54] @216.58 poss/g (per-season chunks
	// 04-08 = [11.97, 12.47, 12.54, 12.53]). Master 12.4142% (7-seed mean, matched
	// 98-zip corpus, 77b9be48b) is INSIDE — WITHIN-NOISE, NOT a clean GO (-0.006pp,
	// -0.73 SE, under ADR-0090's ≥12.42 re-open bar). Supersedes ADR-0088's 12.37%
	// and the mis-denominated all-era 209.2 floor [12.94, 13.41]. NOTE: this struct
	// records no era/corpus field (backlog J26) — a bare artifact is uninterpretable.
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
		DRBPushClass:      total.DRBPushClass,
		HalfCourt:         total.HalfCourt,
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
	t.Logf("  DRB-push-class share: %.2f%% (%d possessions)",
		art.DRBPushSharePct, total.DRBPushClass)
	t.Logf("  half-court share:     %.2f%% (%d possessions)",
		art.HalfCourtSharePct, total.HalfCourt)
	t.Logf("  corpus: %s (%d snapshots) — READ THIS: the band below is recent-era 05-08; it does NOT apply to any other corpus", dir, snapshots)
	t.Logf("  J24 gate-1 band: DRBPushSharePct recent-era drift band [11.97, 12.54]%% @216.58 poss/g (merged code-7 share; superseded floor 12.94)")
	t.Logf("  master reference: 12.4142%% (seed SD 0.0210, SE 0.0079, n=7; matched 98-zip corpus, stride 1, 77b9be48b) — INSIDE the band, but −0.73 SE BELOW ADR-0090's ≥12.42 re-open bar; HOLD stands. Supersedes ADR-0088's 12.37%%.")
	t.Logf("  ⚠ one run is NOT comparable to that mean (seed SD 0.0210pp) and this artifact records no corpus — see calibration-5.60-20260724-fastclass-share-matched-multiseed.json")
}
