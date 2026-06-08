//go:build archive

// Branch-B usage-modulation A/B over the REAL ~53 GB JSB backup archive (ADR-0048,
// PR 2 of ADR-0047). Build-tag gated behind `archive` so it is NEVER compiled by
// `go test ./...` or engine.yml. This is the SIGN-FIRST 2-config measurement: run the
// season-aggregate channel decomposition with Branch-B OFF and ON and compare the
// engine Cov(lnFGA,lnPPS) sign (baseline ≈ −1e-3, real ≈ +2.7e-4). It fails fast on the
// sign — it does NOT run the 5th-arm attribution lattice (that is a contingent follow-on
// only if the sign actually moves, handoff B6).
//
// Invoke manually (run in the background; do not poll — ~106 min at stride 1, runs 20):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run BranchBUsageModulation -v -timeout 6h
package calibrate

import (
	"encoding/json"
	"fmt"
	"math"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// branchBConfigArtifact is one config's committed measurement output: the full
// season-aggregate channel decomposition plus, for the ON config, the engagement
// instrument (Branch-B-taken fraction + s distribution) that proves the measurement
// was non-trivial — distinguishing a real null from a never-engaged no-op.
type branchBConfigArtifact struct {
	Config     string                `json:"config"` // "off" | "on"
	Runs       int                   `json:"runs"`
	Stride     int                   `json:"stride"`
	Seed       uint64                `json:"seed"`
	Engagement *sim.BranchBAccum     `json:"engagement,omitempty"`
	Aggregate  SeasonAggregateReport `json:"aggregate"`
}

// regularCov returns the regular-bucket (game type 2) engine and real
// Cov(lnFGA,lnPPS) and Var(lnFGA) from a season-aggregate report, or zeros if the
// regular bucket is absent.
func regularFidelity(agg SeasonAggregateReport) (f FidelitySummary, ok bool) {
	for _, fd := range agg.Fidelity {
		if fd.GameType == int(bundle.GameTypeRegular) {
			return fd, true
		}
	}
	return FidelitySummary{}, false
}

func writeBranchBArtifact(t *testing.T, art branchBConfigArtifact) {
	t.Helper()
	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-branchB-%s.json", time.Now().Format("20060102"), art.Config))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal %s artifact: %v", art.Config, err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)
}

func TestRealArchive_BranchBUsageModulation(t *testing.T) {
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

	base := func(branchB bool, acc *sim.BranchBAccum) Options {
		return Options{
			Runs:         runs,
			SampleStride: stride,
			Seed:         seed,
			BranchB:      branchB,
			BranchBAccum: acc,
			Progress:     os.Stderr,
		}
	}

	// --- Config OFF: the baseline season-aggregate channel decomposition.
	repsOff, skipsOff, err := CollectSeasonReports(dir, base(false, nil))
	if err != nil {
		t.Fatalf("CollectSeasonReports OFF: %v", err)
	}
	if len(repsOff) == 0 {
		t.Fatalf("no OFF reports produced (skips=%d) — cannot measure", len(skipsOff))
	}
	aggOff := CollectSeasonAggregates(repsOff)
	writeBranchBArtifact(t, branchBConfigArtifact{Config: "off", Runs: runs, Stride: stride, Seed: seed, Aggregate: aggOff})

	// --- Config ON: same walk with Branch-B enabled + the engagement accumulator.
	acc := &sim.BranchBAccum{}
	repsOn, skipsOn, err := CollectSeasonReports(dir, base(true, acc))
	if err != nil {
		t.Fatalf("CollectSeasonReports ON: %v", err)
	}
	if len(repsOn) == 0 {
		t.Fatalf("no ON reports produced (skips=%d) — cannot measure", len(skipsOn))
	}
	aggOn := CollectSeasonAggregates(repsOn)
	writeBranchBArtifact(t, branchBConfigArtifact{Config: "on", Runs: runs, Stride: stride, Seed: seed, Engagement: acc, Aggregate: aggOn})

	fidOff, okOff := regularFidelity(aggOff)
	fidOn, okOn := regularFidelity(aggOn)
	if !okOff || !okOn {
		t.Fatal("regular (game type 2) fidelity bucket missing in OFF or ON aggregate")
	}

	// --- Finiteness: every channel term must be finite under both configs (a degenerate
	// rate season must not poison the decompose into NaN/Inf).
	for _, v := range []float64{
		fidOff.EngineCovLnFGALnPPS, fidOff.EngineVarLnFGA, fidOff.EngineVarLnPPS, fidOff.EngineVarLnPF,
		fidOn.EngineCovLnFGALnPPS, fidOn.EngineVarLnFGA, fidOn.EngineVarLnPPS, fidOn.EngineVarLnPF,
	} {
		if math.IsNaN(v) || math.IsInf(v, 0) {
			t.Fatalf("non-finite channel term in decompose: %v", v)
		}
	}

	// --- Engagement instrument: a valid measurement requires Branch-B to have actually
	// run on a non-trivial fraction of possessions. All-fallback (s≈1) means it never
	// engaged — a unit/ΣD mis-pin, NOT a null — and the comparison below is meaningless.
	total := acc.Taken + acc.Fallback
	if total == 0 {
		t.Fatal("Branch-B never reached the shrink site under ON — no engagement to measure")
	}
	takenFrac := float64(acc.Taken) / float64(total)
	t.Logf("ENGAGEMENT: taken=%d fallback=%d taken_frac=%.3f  s[mean=%.3f min=%.3f max=%.3f]",
		acc.Taken, acc.Fallback, takenFrac, acc.MeanS(), acc.MinS, acc.MaxS)
	if acc.Taken == 0 || acc.MeanS() >= 0.999 {
		t.Errorf("Branch-B engagement trivial (taken=%d meanS=%.4f) — likely a unit/ΣD mis-pin, NOT a null; FIX and re-run before recording a verdict", acc.Taken, acc.MeanS())
	}

	// --- Verdict readout (INTERPRETED in ADR-0048 against the pre-registered criterion;
	// the test records the numbers and does not force-rank a winner). Success = the Cov
	// sign FLIPS (engine OFF ≈ −1e-3 → ON ≥ 0, toward real ≈ +2.7e-4) WHILE Var(lnFGA)
	// narrows toward real (0.00133) WITHOUT regressing Var(lnPPS).
	t.Logf("REGULAR CHANNEL (engine):")
	t.Logf("  Cov(lnFGA,lnPPS): OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnFGALnPPS, fidOn.EngineCovLnFGALnPPS, fidOn.RealCovLnFGALnPPS)
	t.Logf("  Var(lnFGA):       OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnFGA, fidOn.EngineVarLnFGA, fidOn.RealVarLnFGA)
	t.Logf("  Var(lnPPS):       OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnPPS, fidOn.EngineVarLnPPS, fidOn.RealVarLnPPS)
	t.Logf("  Var(lnPF):        OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnPF, fidOn.EngineVarLnPF, fidOn.RealVarLnPF)
	covFlipped := fidOff.EngineCovLnFGALnPPS < 0 && fidOn.EngineCovLnFGALnPPS >= 0
	ppsRegressed := math.Abs(fidOn.EngineVarLnPPS-fidOn.RealVarLnPPS) > math.Abs(fidOff.EngineVarLnPPS-fidOff.RealVarLnPPS)
	t.Logf("VERDICT SIGNALS: cov_sign_flipped=%v  pps_regressed=%v  (ship ON only if flipped AND not regressed; else land OFF with a recorded null — ADR-0040-A precedent)", covFlipped, ppsRegressed)
}
