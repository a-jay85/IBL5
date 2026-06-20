//go:build archive

// L1 gate-1 counterfactual instrument over the REAL ~53 GB JSB backup archive
// (ADR-0057/0058 — the MEASURE half; the eventual two-gate FIX is OUT OF SCOPE and
// gets its own ADR/plan). ADR-0057 located the L1 carrier as the dropped sqrt
// team-determination gate (gate-1, FUN_004e22a0) and left the mean-inflation vs
// curvature-over-coupling split indeterminate from static decompile. This walk MEASURES
// that split dynamically: at every offensive-rebound resolution the sim computes, read-
// only, the live gate-2 (orebProbability), the counterfactual gate-1 sqrt team-pick, and
// their product, keyed by offensive team (golden stays byte-identical — no rng draw).
//
// RECONCILIATION ANCHOR (the validity gate): .sco IS JSB 5.60 output, so a FAITHFUL
// gate-1, multiplied onto the engine's gate-2, must reproduce 5.60's OWN ORB level. The
// per-possession ratio real_ORB/engine_ORB (ADR-0056's 0.158/0.196 ≈ 0.81) is an
// APPROXIMATE LOWER BOUND on the required mean gate-1 — the geometric continuation chain
// (E[ORB]≈p/(1−p)) means the faithful gate-1 is HIGHER still (~0.88). NOTE the per-
// RESOLUTION reductionFrac this instrument reports is NOT 1:1 with the per-POSSESSION ORB
// reduction (different denominators, chain-amplified) — meanG1 vs the anchor is the
// cleaner comparison. The gate-1 baseline term reads two LOADER-POPULATED globals
// (+0x6818/+0x6848, master-ref 186-187) whose runtime values are UNPINNED in static
// decompile, so this walk SWEEPS the baseline and reports whether the transcribed gate-1
// can reach the anchor at ANY plausible value (it does not — see ADR-0058).
//
// Build-tag gated behind `archive` so it is NEVER compiled by `go test ./...` or
// engine.yml. Invoke manually (run in the background; do not poll — a full walk is
// ~8 min at runs=20/stride=1, so the primary + 4-point sweep ≈ 40 min):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run GateContinuation -v -timeout 6h
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

// gateSweepPoint is one baseline's regular-bucket gate discriminator readout. baseline
// is the gate-1 league-baseline override (nil-walk records the bundle-derived value as
// NaN-free 0 is avoided by recording it separately on the artifact).
type gateSweepPoint struct {
	Baseline       float64 `json:"baseline"`
	MeanG1         float64 `json:"mean_g1"`
	MeanG2         float64 `json:"mean_g2"`
	MeanProd       float64 `json:"mean_prod"`
	ReductionFrac  float64 `json:"reduction_frac"`
	VarG1          float64 `json:"var_g1"`
	CovG2LnPPS     float64 `json:"cov_g2_ln_pps"`
	CovProdLnPPS   float64 `json:"cov_prod_ln_pps"`
	CurvatureDelta float64 `json:"curvature_delta"` // covG2 − covProd, the curvature channel
}

// gateContArtifact is the committed anchor: the run config, the primary (bundle-derived
// baseline) season-aggregate report whose fidelity block now carries the gate-1
// discriminator fields, the ORB reconciliation anchor, and the baseline sweep.
type gateContArtifact struct {
	Generated string                `json:"generated"`
	Runs      int                   `json:"runs"`
	Stride    int                   `json:"stride"`
	Seed      uint64                `json:"seed"`
	EngineORB float64               `json:"engine_orb_per_poss"` // ADR-0056 ORB/POSS, engine
	RealORB   float64               `json:"real_orb_per_poss"`   // ADR-0056 ORB/POSS, real (.sco)
	AnchorG1  float64               `json:"anchor_mean_g1"`      // real/engine ORB ratio — an APPROXIMATE LOWER BOUND on the faithful mean gate-1 (true ~0.88, chain-amplified)
	Sweep     []gateSweepPoint      `json:"sweep"`               // baseline sensitivity sweep
	Aggregate SeasonAggregateReport `json:"aggregate"`           // primary (bundle-derived baseline) walk
}

// gateWalk runs one baseline's archive walk and returns its regular (game type 2)
// fidelity summary plus the full aggregate (used only for the primary committed walk).
func gateWalk(t *testing.T, dir string, runs, stride int, seed uint64, baseline *float64) (FidelitySummary, SeasonAggregateReport) {
	t.Helper()
	reps, skips, err := CollectSeasonReports(dir, Options{
		Runs: runs, SampleStride: stride, Seed: seed, GateBaseline: baseline, Progress: os.Stderr,
	})
	if err != nil {
		t.Fatalf("CollectSeasonReports: %v", err)
	}
	if len(reps) == 0 {
		t.Fatalf("no reports produced (skips=%d) — cannot measure", len(skips))
	}
	agg := CollectSeasonAggregates(reps)
	fid, ok := regularFidelity(agg)
	if !ok {
		t.Fatal("regular (game type 2) fidelity bucket missing")
	}
	return fid, agg
}

func TestRealArchive_GateContinuation(t *testing.T) {
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

	// Primary walk: the bundle-derived gate-1 baseline (GateBaseline nil), the best
	// static estimate available without the x32dbg loader-constant pin. Its aggregate is
	// the committed artifact; its regular fidelity is the first sweep readout.
	primFid, agg := gateWalk(t, dir, runs, stride, seed, nil)

	anchorG1 := 0.0
	if primFid.EngineOrebIntensity > 0 {
		anchorG1 = primFid.RealOrebIntensity / primFid.EngineOrebIntensity // faithful gate-1's target mean
	}

	sweep := []gateSweepPoint{point(0, primFid)} // baseline 0 sentinel = the bundle-derived primary walk
	// Baseline sensitivity sweep: brackets the share×100 range. Demonstrates whether the
	// transcribed gate-1 can reach the ORB anchor (anchorG1) at any plausible baseline.
	for _, b := range []float64{40, 60, 80, 95} {
		bv := b
		fid, _ := gateWalk(t, dir, runs, stride, seed, &bv)
		sweep = append(sweep, point(b, fid))
	}

	art := gateContArtifact{
		Generated: time.Now().Format(time.RFC3339),
		Runs:      runs, Stride: stride, Seed: seed,
		EngineORB: primFid.EngineOrebIntensity, RealORB: primFid.RealOrebIntensity, AnchorG1: anchorG1,
		Sweep:     sweep,
		Aggregate: agg,
	}
	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-gate-continuation.json", time.Now().Format("20060102")))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	// Finiteness: no gate term may be NaN/Inf on real data, at any baseline.
	for _, p := range sweep {
		for name, v := range map[string]float64{
			"mean_g1": p.MeanG1, "mean_g2": p.MeanG2, "mean_prod": p.MeanProd,
			"reduction_frac": p.ReductionFrac, "var_g1": p.VarG1,
			"cov_g2": p.CovG2LnPPS, "cov_prod": p.CovProdLnPPS,
		} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Fatalf("non-finite gate term %s = %v at baseline %.0f", name, v, p.Baseline)
			}
		}
		// gate-1 ∈ [0,1] ⇒ product ≤ gate-2 (the dropped gate only ever REDUCES continuation).
		if p.MeanProd > p.MeanG2+1e-9 {
			t.Errorf("baseline %.0f: meanProd %.4f > meanG2 %.4f (gate-1 must reduce, not amplify)", p.Baseline, p.MeanProd, p.MeanG2)
		}
		if p.ReductionFrac < 0 || p.ReductionFrac > 1 {
			t.Errorf("baseline %.0f: reductionFrac %.4f out of [0,1]", p.Baseline, p.ReductionFrac)
		}
	}
	// varG1 must be > 0 on a multi-team corpus, else the curvature read (covG2−covProd) is
	// just a scaled copy of the mean channel and cannot be interpreted.
	if primFid.GateVarG1 <= 0 {
		t.Errorf("Var(gate-1) must be > 0 on a multi-team corpus: %v", primFid.GateVarG1)
	}

	// ── Verdict (INTERPRETED in ADR-0058; the test records, never ranks).
	t.Logf("RECONCILIATION ANCHOR: engine ORB/POSS=%.5f real=%.5f ⇒ faithful mean gate-1 ≥ %.4f (LOWER bound; true ~0.88 after the continuation chain)",
		primFid.EngineOrebIntensity, primFid.RealOrebIntensity, anchorG1)
	for _, p := range sweep {
		label := fmt.Sprintf("baseline=%.0f", p.Baseline)
		if p.Baseline == 0 {
			label = "baseline=bundle-derived (primary)"
		}
		t.Logf("  %s: meanG1=%.4f reductionFrac=%.4f varG1=%.6f  curvature(covG2−covProd)=%+.6f",
			label, p.MeanG1, p.ReductionFrac, p.VarG1, p.CurvatureDelta)
	}
	t.Logf("VERDICT KEYS — (1) does any baseline reach meanG1≈%.2f (anchor)? if it SATURATES below, the", anchorG1)
	t.Logf("  transcribed gate-1 magnitude is NOT recoverable from static constants (x32dbg pin needed).")
	t.Logf("  (2) is curvature(covG2−covProd)≈0 across baselines? if so the sqrt gate-1's curvature is NOT")
	t.Logf("  the L1 carrier (mean-magnitude is), corroborating ADR-0056's faithful ORB-intensity coupling.")
}

// point builds a sweep readout from a regular fidelity summary at the given baseline.
func point(baseline float64, f FidelitySummary) gateSweepPoint {
	return gateSweepPoint{
		Baseline:       baseline,
		MeanG1:         f.GateMeanG1,
		MeanG2:         f.GateMeanG2,
		MeanProd:       f.GateMeanProd,
		ReductionFrac:  f.GateMeanReductionFrac,
		VarG1:          f.GateVarG1,
		CovG2LnPPS:     f.GateCovG2LnPPS,
		CovProdLnPPS:   f.GateCovProdLnPPS,
		CurvatureDelta: f.GateCovG2LnPPS - f.GateCovProdLnPPS,
	}
}
