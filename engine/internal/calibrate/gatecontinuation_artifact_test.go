package calibrate

import (
	"encoding/json"
	"math"
	"os"
	"path/filepath"
	"sort"
	"testing"
)

// gateArtifactProbe is a minimal view of the committed gate-continuation artifact for
// the always-on (non-archive) sanity guard. It deliberately does NOT reuse the
// archive-tagged gateContArtifact type (which is not compiled in the default build);
// SeasonAggregateReport IS in the default build, so the fidelity block is checked too.
type gateArtifactProbe struct {
	AnchorG1  float64 `json:"anchor_mean_g1"`
	EngineORB float64 `json:"engine_orb_per_poss"`
	RealORB   float64 `json:"real_orb_per_poss"`
	Sweep     []struct {
		Baseline      float64 `json:"baseline"`
		MeanG1        float64 `json:"mean_g1"`
		MeanG2        float64 `json:"mean_g2"`
		MeanProd      float64 `json:"mean_prod"`
		ReductionFrac float64 `json:"reduction_frac"`
		VarG1         float64 `json:"var_g1"`
		CovG2LnPPS    float64 `json:"cov_g2_ln_pps"`
		CovProdLnPPS  float64 `json:"cov_prod_ln_pps"`
	} `json:"sweep"`
	Aggregate SeasonAggregateReport `json:"aggregate"`
}

// TestGateContinuationArtifact_Sane is the always-on guard that the committed
// gate-continuation artifact (ADR-0058) stays valid: every sweep point finite,
// MeanProd ≤ MeanG2 (gate-1 reduces), reductionFrac ∈ [0,1], the ORB anchor present,
// and the regular-bucket fidelity gate fields populated + finite. It GLOBS for the
// artifact and SKIPS when none is committed yet (the archive walk generates it), so CI
// is green before the first walk; once committed it is checked on every default build.
func TestGateContinuationArtifact_Sane(t *testing.T) {
	matches, err := filepath.Glob(filepath.Join("..", "validate", "testdata", "calibration-5.60-*-gate-continuation.json"))
	if err != nil {
		t.Fatalf("glob: %v", err)
	}
	if len(matches) == 0 {
		t.Skip("no committed gate-continuation artifact yet (run the archive walk to generate it)")
	}
	sort.Strings(matches)
	path := matches[len(matches)-1] // newest by date-stamped filename

	blob, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("read %s: %v", path, err)
	}
	var art gateArtifactProbe
	if err := json.Unmarshal(blob, &art); err != nil {
		t.Fatalf("unmarshal %s: %v", path, err)
	}

	if art.AnchorG1 <= 0 || math.IsNaN(art.AnchorG1) || math.IsInf(art.AnchorG1, 0) {
		t.Errorf("anchor_mean_g1 = %v, want finite > 0 (real/engine ORB ratio)", art.AnchorG1)
	}
	if len(art.Sweep) < 2 {
		t.Errorf("sweep has %d points, want ≥ 2 (primary + baseline sensitivity)", len(art.Sweep))
	}
	for i, p := range art.Sweep {
		for name, v := range map[string]float64{
			"mean_g1": p.MeanG1, "mean_g2": p.MeanG2, "mean_prod": p.MeanProd,
			"reduction_frac": p.ReductionFrac, "var_g1": p.VarG1,
			"cov_g2": p.CovG2LnPPS, "cov_prod": p.CovProdLnPPS,
		} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Errorf("sweep[%d] (baseline %.0f): %s non-finite (%v)", i, p.Baseline, name, v)
			}
		}
		if p.MeanProd > p.MeanG2+1e-9 {
			t.Errorf("sweep[%d] (baseline %.0f): mean_prod %.4f > mean_g2 %.4f (gate-1 must reduce)", i, p.Baseline, p.MeanProd, p.MeanG2)
		}
		if p.ReductionFrac < 0 || p.ReductionFrac > 1 {
			t.Errorf("sweep[%d] (baseline %.0f): reduction_frac %.4f out of [0,1]", i, p.Baseline, p.ReductionFrac)
		}
	}

	// Regular-bucket (game type 2) fidelity gate fields: populated + finite.
	var reg *FidelitySummary
	for i := range art.Aggregate.Fidelity {
		if art.Aggregate.Fidelity[i].GameType == 2 {
			reg = &art.Aggregate.Fidelity[i]
			break
		}
	}
	if reg == nil {
		t.Fatal("artifact aggregate has no regular (game type 2) fidelity bucket")
	}
	if reg.GateMeanG2 <= 0 {
		t.Errorf("regular GateMeanG2 = %v, want > 0", reg.GateMeanG2)
	}
	if reg.GateVarG1 <= 0 {
		t.Errorf("regular GateVarG1 = %v, want > 0 (curvature read needs gate-1 variance)", reg.GateVarG1)
	}
	for name, v := range map[string]float64{
		"GateMeanG1": reg.GateMeanG1, "GateMeanProd": reg.GateMeanProd,
		"GateMeanReductionFrac": reg.GateMeanReductionFrac,
		"GateCovG2LnPPS":        reg.GateCovG2LnPPS, "GateCovProdLnPPS": reg.GateCovProdLnPPS,
	} {
		if math.IsNaN(v) || math.IsInf(v, 0) {
			t.Errorf("regular %s non-finite (%v)", name, v)
		}
	}
}
