//go:build archive

// Possession-count dispersion sweep over the REAL ~53 GB JSB backup archive
// (ADR-0054, the count half of the ADR-0049 Cov split). Build-tag gated behind
// `archive` so it is NEVER compiled by `go test ./...` or engine.yml.
//
// ADR-0053 closed the shots-per-possession make-value lever as a null; the surviving
// count-side defect is Var(lnPOSS) — the engine UNDER-disperses team-to-team
// possession count (engine 0.000288 vs real 0.000721, ~2.5× too narrow). The
// offVolumeScale knob (tempo.go) couples a team's offensive volume → base_time →
// possession count, but it was capped at 0.02 ONLY on Var(lnFGA)/headline grounds and
// was NEVER judged on Var(lnPOSS) (the metric didn't exist until ADR-0049). This sweep
// re-measures offVolumeScale ∈ {0, 0.02, 0.04, 0.06} on the count split.
//
// SUCCESS CRITERION (ADR-0054): widen Var(lnPOSS) toward real WITHOUT regressing total
// Var(lnFGA). The load-bearing constraint is the identity
// Var(lnFGA) ≈ Var(lnPOSS) + Var(ln(FGA/POSS)) + 2·Cov: engine Var(lnFGA) is ALREADY
// ~1.9× too WIDE (0.00265 vs real 0.00141), so widening Var(lnPOSS) in isolation pushes
// total Var(lnFGA) further from real — a regression. The headline Cov(lnFGA,lnPPS) flip
// is NOT a success target (blocked by the unfixed 72% shots-per-poss anti-coupling), and
// Cov(lnPOSS,lnPPS) is WATCH-ONLY (a tautology: real shots-per-poss≈0 ⟹ real count≈total).
//
// The verdict is INTERPRETED in ADR-0054 against this criterion; the test records the
// numbers and the RUBRIC SIGNALS per scale without force-ranking a winner. The
// reference is scale=0.02 (today's shipped const value).
//
// Invoke manually (run in the background; do not poll — ~106 min/config at stride 1,
// runs 20; four configs ≈ 7.1h):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run OffVolumeScaleSweep -v -timeout 10h
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

// offVolumeScaleConfigArtifact is one swept scale's committed measurement output: the
// full season-aggregate channel decomposition at that offVolumeScale value.
type offVolumeScaleConfigArtifact struct {
	Scale     float64               `json:"scale"`
	Runs      int                   `json:"runs"`
	Stride    int                   `json:"stride"`
	Seed      uint64                `json:"seed"`
	Aggregate SeasonAggregateReport `json:"aggregate"`
}

func writeOffVolumeScaleArtifact(t *testing.T, art offVolumeScaleConfigArtifact, slug string) {
	t.Helper()
	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-%s.json", time.Now().Format("20060102"), slug))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal %s artifact: %v", slug, err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)
}

func TestRealArchive_OffVolumeScaleSweep(t *testing.T) {
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

	// scaleFid runs one scale's walk, emits its artifact, and returns the regular
	// (game type 2) fidelity summary — the verdict bucket.
	scaleFid := func(scale float64) FidelitySummary {
		s := scale // copy before taking address (per-config pointer)
		o := Options{Runs: runs, SampleStride: stride, Seed: seed, OffVolumeScale: &s, Progress: os.Stderr}
		slug := fmt.Sprintf("offVolumeScale-%.2f", scale)
		reps, skips, err := CollectSeasonReports(dir, o)
		if err != nil {
			t.Fatalf("CollectSeasonReports %s: %v", slug, err)
		}
		if len(reps) == 0 {
			t.Fatalf("no reports for %s (skips=%d) — cannot measure", slug, len(skips))
		}
		agg := CollectSeasonAggregates(reps)
		writeOffVolumeScaleArtifact(t, offVolumeScaleConfigArtifact{
			Scale: scale, Runs: runs, Stride: stride, Seed: seed, Aggregate: agg,
		}, slug)
		fid, ok := regularFidelity(agg)
		if !ok {
			t.Fatalf("regular (game type 2) fidelity bucket missing in %s aggregate", slug)
		}
		return fid
	}

	scales := []float64{0, 0.02, 0.04, 0.06}
	fids := make(map[float64]FidelitySummary, len(scales))
	for _, scale := range scales {
		fids[scale] = scaleFid(scale)
	}

	// Finiteness: every count-split term must be finite under all scales (a degenerate
	// rate season must not poison the decompose into NaN/Inf).
	for scale, f := range fids {
		for _, v := range []float64{
			f.EngineVarLnPoss, f.EngineCovLnPossLnPPS, f.EngineCovLnShotsPerPossLnPPS, f.EngineVarLnFGA,
		} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Fatalf("non-finite count-split term at scale %.2f: %v", scale, v)
			}
		}
	}

	// Reference = scale=0.02 (today's shipped const value). The success criterion keys
	// on whether a swept scale widens Var(lnPOSS) toward real WITHOUT total Var(lnFGA)
	// regressing vs this reference.
	ref := fids[0.02]
	const realVarPoss = 0.000721
	refVarFGADist := math.Abs(ref.EngineVarLnFGA - ref.RealVarLnFGA)
	refVarPossDist := math.Abs(ref.EngineVarLnPoss - realVarPoss)

	for _, scale := range scales {
		f := fids[scale]
		t.Logf("=== offVolumeScale = %.2f (regular bucket, engine) ===", scale)
		t.Logf("  Var(lnPOSS):              %.6f  (real=%.6f, ref@0.02=%.6f)", f.EngineVarLnPoss, realVarPoss, ref.EngineVarLnPoss)
		t.Logf("  Var(lnFGA) [TOTAL]:       %.6f  (real=%.6f, ref@0.02=%.6f)", f.EngineVarLnFGA, f.RealVarLnFGA, ref.EngineVarLnFGA)
		t.Logf("  poss_dispersion_ratio:    %.4f", f.PossDispersionRatio)
		t.Logf("  Cov(lnPOSS,lnPPS) [WATCH]: %+.6f  (real=%+.6f)", f.EngineCovLnPossLnPPS, f.RealCovLnPossLnPPS)
		t.Logf("  Cov(ln(FGA/POSS),lnPPS):   %+.6f  (real=%+.6f)", f.EngineCovLnShotsPerPossLnPPS, f.RealCovLnShotsPerPossLnPPS)

		varPossWidenedTowardReal := math.Abs(f.EngineVarLnPoss-realVarPoss) < refVarPossDist
		totalVarFGANotRegressed := math.Abs(f.EngineVarLnFGA-f.RealVarLnFGA) <= refVarFGADist
		// The success criterion is the conjunction: widen Var(lnPOSS) toward real AND
		// keep total Var(lnFGA) from regressing vs the 0.02 reference.
		t.Logf("  RUBRIC SIGNALS: varPoss_widened_toward_real=%v  total_varFGA_not_regressed=%v  success=%v",
			varPossWidenedTowardReal, totalVarFGANotRegressed, varPossWidenedTowardReal && totalVarFGANotRegressed)
	}
	t.Logf("RUBRIC (ADR-0054): branch(a) retune-const iff some scale has success=true; " +
		"else branch(b) ratio-form iff Var(lnPOSS) saturates/clamps below real (additive-ceiling limiter); " +
		"else branch(c) documented null (Var(lnFGA)-budget-blocked). Cov(lnPOSS,lnPPS) is WATCH-ONLY.")
}
