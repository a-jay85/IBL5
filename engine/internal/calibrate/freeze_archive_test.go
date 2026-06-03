//go:build archive

// Freeze-lattice empty-FGA source-isolation diagnostic over the REAL ~53 GB JSB
// backup archive (ADR-0043). Build-tag gated behind `archive` so it is NEVER
// compiled by `go test ./...` or engine.yml.
//
// Invoke manually (tune runs/stride for runtime vs pooled-row count; the verdict
// needs enough seasons that the pooled cross-team Cov is stable — stride 1 pools
// every season):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run FreezeLatticeAttribution -v -timeout 6h
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

func TestRealArchive_FreezeLatticeAttribution(t *testing.T) {
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

	rep, skips, err := CollectFreezeAttribution(dir, Options{
		Runs:         runs,
		SampleStride: stride,
		Progress:     os.Stderr,
	}, seed)
	if err != nil {
		t.Fatalf("CollectFreezeAttribution: %v", err)
	}
	t.Logf("seasons=%d runs=%d stride=%d skips=%d", rep.NumSeasons, runs, stride, len(skips))
	if rep.NumSeasons == 0 {
		t.Fatal("no seasons produced — cannot attribute")
	}
	if n := rep.Configs[0].NumRows; n < 28 {
		t.Logf("WARNING: only %d pooled team rows in the baseline — Cov may be unstable; lower JSB_ARCHIVE_STRIDE", n)
	}

	// Emit the committed attribution artifact (calibration-5.60-* prefix — the
	// tracked reference naming; calibration-season-aggregate-* is gitignored).
	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-freeze-attribution.json", time.Now().Format("20060102")))
	blob, err := json.MarshalIndent(rep, "", "  ")
	if err != nil {
		t.Fatalf("marshal report: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	// --- Control A: the no-freeze config reproduces a NEGATIVE engine
	// Cov(lnFGA,lnPPS) of order ~1e-3. It is a SANITY BAND, not a literal: the
	// absolute value shifts with runs/stride/season-selection and the harness sims
	// the full schedule (not the .sco-matched subset), so no exact constant is
	// expected. The no-freeze config is the self-reference every Δ is measured
	// against.
	base := rep.BaselineCovLnFGALnPPS
	if base >= 0 {
		t.Errorf("Control A: baseline Cov(lnFGA,lnPPS) = %.6f, want NEGATIVE (the ADR-0042 wrong sign)", base)
	}
	if ab := math.Abs(base); ab < 1e-5 || ab > 0.05 {
		t.Errorf("Control A: baseline |Cov| = %.6f outside the ~1e-3 sanity band [1e-5, 5e-2]", ab)
	}

	// --- Control B: freezing ALL four arms reduces |Cov| relative to baseline (the
	// arms collectively remove covariance, never add it). The RESIDUAL that survives
	// is the non-arm (pace / shot-mix / FT / rebound-count) covariance — REPORTED,
	// not asserted to zero. A large residual is itself a verdict (defect outside the
	// four arms → the "no single dominant lever" terminal branch).
	allFrozen := rep.AllFrozenCovLnFGALnPPS
	if math.Abs(allFrozen) > math.Abs(base)+1e-9 {
		t.Errorf("Control B: all-frozen |Cov| = %.6f exceeds baseline |Cov| = %.6f (arms must not ADD covariance)", math.Abs(allFrozen), math.Abs(base))
	}
	t.Logf("CONTROLS: baseline Cov=%.6f all-frozen Cov=%.6f residual frac of baseline=%.3f",
		base, allFrozen, rep.ResidualFracOfBaseline)

	// --- Attribution readout for the ADR (verdict is interpreted in the ADR against
	// the pre-registered criterion; the test does not force-rank a winner).
	for _, a := range rep.Arms {
		t.Logf("arm %-4s: collapseFrac(|Cov|)=%+.3f  ΔVar(lnFGA)=%+.6f  ΔCov(lnFGA,lnPF)=%+.6f  ΔCov(lnFGA,lnPPS)=%+.6f",
			a.Arm, a.CovPPSCollapseFrac, a.DVarLnFGA, a.DCovLnFGALnPF, a.DCovLnFGALnPPS)
	}
	for _, m := range rep.MechPanel {
		t.Logf("mech %-18s: Cov(rate,lnFGA)=%+.6f  Cov(rate,lnPPS)=%+.6f", m.Mech, m.CovWithLnFGA, m.CovWithLnPPS)
	}
}
