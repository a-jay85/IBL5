//go:build archive

// Possession-count coupling artifact over the REAL ~53 GB JSB backup archive
// (ADR-0049, PR 1 of 2 — the instrument PR). This walks the season-aggregate
// channel decomposition ONCE (Branch-B OFF, the shipped baseline) and writes the
// committed anchor artifact carrying the new possession-count terms: Var(lnPOSS),
// the POSS-vs-shots-per-possession split of Cov(lnFGA,lnPPS), and the dispersion
// ratio. Unlike the Branch-B A/B (ADR-0048) there is NO engine behavior toggle —
// the engine POSS count is the authoritative EventPossessionStart tally of an
// event Simulate already emits, and the .sco side is the true-possession proxy
// FGA+0.44·FTA+TOV−ORB; counting an existing event is not a behavior change, so
// the golden fixture stays byte-identical with no freeze flag (cleaner than #1004).
//
// Build-tag gated behind `archive` so it is NEVER compiled by `go test ./...` or
// engine.yml. Invoke manually (run in the background; do not poll):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run PossessionCoupling -v -timeout 6h
//
// A coarser smoke (e.g. RUNS=4 STRIDE=4) gives the same directional verdict for a
// few-minute artifact; the committed config is recorded in the artifact's header.
package calibrate

import (
	"encoding/json"
	"fmt"
	"math"
	"os"
	"path/filepath"
	"testing"
	"time"
)

// possCouplingArtifact is the committed anchor: the run config plus the full
// season-aggregate report (whose fidelity block now carries the ADR-0049 POSS
// terms). No engagement instrument — there is no behavior toggle to instrument.
type possCouplingArtifact struct {
	Generated string                `json:"generated"`
	Runs      int                   `json:"runs"`
	Stride    int                   `json:"stride"`
	Seed      uint64                `json:"seed"`
	Aggregate SeasonAggregateReport `json:"aggregate"`
}

// RECORDED BASELINE (current engine, gt2, archive 20-run of record, 2026-07-13):
//
//	Cov(lnFGA,lnPPS) total  = engine -0.000364   real +0.000269   (wrong sign)
//	  possession-count term = engine -0.000184   real +0.000241   (89% of real total)
//	  shots-per-poss term   = engine -0.000180   real +0.000027   (real ≈ 0)
//	Var(lnPOSS) = engine 0.000254  real 0.000721   (engine under-disperses pace ~2.8x)
//
// FINDING (J20 within-possession restructure, Phase 3 — mechanism VOID, not implemented):
// The dominant wrong-signed term is the possession-COUNT channel (89% of the real
// positive Cov). Possession count is set by gameloop.go's `clock / avg(ball-time)` —
// fixed up front from tempo ratings; offensive rebounds continue a possession without
// adding to the count. So NO within-possession putback lever can move Cov(lnPOSS,lnPPS):
// the carrier is cross-team tempo/pace dispersion, a separate subsystem. Per-origin
// putback share (12.58% vs J4 12.65%) and efficiency (eFG 0.608 vs 0.622) are already
// J4-faithful — there was nothing to re-weight. The real carrier is a pace-generation
// plan, not this one. These numbers are characterization of record, not a J20 target.
//
// J24 UPDATE (fast-class mix port, Phase 5 NO-GO — 2026-07-17, archive smoke
// runs=4 stride=4, seed 20240601, baseTimeMid re-centered 13.65 → 17.7):
//
//	Cov(lnPOSS,lnPPS) = engine -0.000055   real +0.000241   (still negative;
//	  was -0.000184 pre-port — moved toward real, did NOT flip)
//	Var(lnPOSS)       = engine 0.000270    real 0.000721    (unchanged vs the
//	  0.000254 pre-port record — the mix added pace classes, NOT dispersion)
//	mean pace 104.25 poss/g @ 17.7 (restored; 132.14 @ the old 13.65)
//
// The steal {0,1,2}s / DRB-push {2,3,4}s / half-court-jitter step classes are
// ported (sim/tempo.go, gameloop.go), but the engine ARMS them at ~29% of
// possessions vs real ~11.5%, so the provisional center sits at 17.7 instead of
// the faithful 16.0, and the dispersion/Cov carriers remain unidentified. See
// the tempo.go NO-GO block and ADR-0085 for the residual RE sub-steps.
func TestRealArchive_PossessionCoupling(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	runs := envInt("JSB_ARCHIVE_RUNS", 20)
	stride := envInt("JSB_ARCHIVE_STRIDE", 1)
	seed := uint64(envInt("JSB_ARCHIVE_SEED", 20240601))

	reps, skips, err := CollectSeasonReports(dir, Options{
		Runs:         runs,
		SampleStride: stride,
		Seed:         seed,
		Progress:     os.Stderr,
	})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reps) == 0 {
		t.Fatalf("no reports produced (skips=%d) — cannot measure", len(skips))
	}
	agg := CollectSeasonAggregates(reps)

	art := possCouplingArtifact{
		Generated: time.Now().Format(time.RFC3339),
		Runs:      runs, Stride: stride, Seed: seed,
		Aggregate: agg,
	}
	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-possession-coupling.json", time.Now().Format("20060102")))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	fid, ok := regularFidelity(agg)
	if !ok {
		t.Fatal("regular (game type 2) fidelity bucket missing")
	}

	// Finiteness: no POSS term may be NaN/Inf on real data (a degenerate season must
	// not poison the decompose).
	for name, v := range map[string]float64{
		"engine_var_ln_poss": fid.EngineVarLnPoss, "engine_cov_poss": fid.EngineCovLnPossLnPPS,
		"engine_cov_spp": fid.EngineCovLnShotsPerPossLnPPS, "real_var_ln_poss": fid.RealVarLnPoss,
		"real_cov_poss": fid.RealCovLnPossLnPPS, "real_cov_spp": fid.RealCovLnShotsPerPossLnPPS,
		"poss_dispersion_ratio": fid.PossDispersionRatio,
	} {
		if math.IsNaN(v) || math.IsInf(v, 0) {
			t.Fatalf("non-finite POSS term %s = %v", name, v)
		}
	}

	// Non-degenerate: the corpus must actually exercise possession-count spread.
	if fid.EngineVarLnPoss <= 0 || fid.RealVarLnPoss <= 0 {
		t.Errorf("Var(lnPOSS) must be > 0 on a multi-team corpus: eng %v real %v", fid.EngineVarLnPoss, fid.RealVarLnPoss)
	}

	// Identity on REAL data: the two split terms close to the headline Cov(lnFGA,lnPPS).
	engSum := fid.EngineCovLnPossLnPPS + fid.EngineCovLnShotsPerPossLnPPS
	realSum := fid.RealCovLnPossLnPPS + fid.RealCovLnShotsPerPossLnPPS
	if d := math.Abs(engSum - fid.EngineCovLnFGALnPPS); d > 1e-9 {
		t.Errorf("engine POSS split does not close on real data: %v != %v (Δ %v)", engSum, fid.EngineCovLnFGALnPPS, d)
	}
	if d := math.Abs(realSum - fid.RealCovLnFGALnPPS); d > 1e-9 {
		t.Errorf("real POSS split does not close on real data: %v != %v (Δ %v)", realSum, fid.RealCovLnFGALnPPS, d)
	}

	// Verdict readout (INTERPRETED in ADR-0049 / the trace doc; the test records the
	// numbers and does not force-rank). Localizes which factor carries the wrong sign.
	t.Logf("REGULAR CHANNEL — possession-count split (engine vs real):")
	t.Logf("  Cov(lnFGA,lnPPS):  engine=%+.6f real=%+.6f", fid.EngineCovLnFGALnPPS, fid.RealCovLnFGALnPPS)
	t.Logf("  └ Cov(lnPOSS,lnPPS):       engine=%+.6f real=%+.6f", fid.EngineCovLnPossLnPPS, fid.RealCovLnPossLnPPS)
	t.Logf("  └ Cov(lnFGA/POSS,lnPPS):   engine=%+.6f real=%+.6f", fid.EngineCovLnShotsPerPossLnPPS, fid.RealCovLnShotsPerPossLnPPS)
	t.Logf("  Var(lnPOSS): engine=%.6f real=%.6f  poss_dispersion=%.2f", fid.EngineVarLnPoss, fid.RealVarLnPoss, fid.PossDispersionRatio)
	// Budget-mirror term (ADR-0054/J21): widening pace dispersion propagates into
	// FGA dispersion via Var(lnFGA) ≈ Var(lnPOSS) + Var(ln(FGA/POSS)) + 2·Cov. The
	// engine value must stay ≤ real (headroom, not overshoot) — the 4th A/B gate term.
	t.Logf("  Var(lnFGA):  engine=%.6f real=%.6f  (budget-mirror ceiling — engine must stay ≤ real)", fid.EngineVarLnFGA, fid.RealVarLnFGA)
	t.Logf("VERDICT: the factor whose Cov term carries the wrong sign (engine − vs real +) is where the ADR-0049 build PR must intervene.")

	// Cheap cross-check (advisor): the per-team engine proxy mean should land near the
	// known archive level (≈99–106 true poss/team, ADR-0045), and the authoritative
	// count should reconcile with it (count ≈ proxy validates the proxy; the gap is
	// the shots-per-possession spread the proxy folds away). A wild value flags a units
	// bug in the count→aggregate path.
	var sumProxy, sumCount, n float64
	for _, sa := range agg.Seasons {
		if sa.GameType != 2 {
			continue
		}
		for _, ts := range sa.Teams {
			if ts.EnginePossPerG > 0 {
				sumProxy += ts.EnginePossPerG
				sumCount += ts.EnginePossCountPerG
				n++
			}
		}
	}
	if n > 0 {
		t.Logf("SANITY: mean engine POSS/team — proxy=%.1f count=%.1f (expect ~95–110, count≈proxy; reconciles with possession_archive_test)", sumProxy/n, sumCount/n)
	}
}
