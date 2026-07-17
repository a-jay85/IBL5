//go:build archive

// Faithful-putback-resolution A/B over the REAL ~53 GB JSB backup archive
// (ADR-0055). Build-tag gated behind `archive` so it is NEVER compiled by
// `go test ./...` or engine.yml. It measures whether the faithful putback
// resolution (net-free boosted make-value + 3pt suppression, shipped ON by
// default) shrinks the engine's wrong-signed Cov(ln(FGA/POSS),lnPPS) toward
// real ≈0 — the suspected carrier of the empty-FGA shots-per-possession
// anti-coupling (ADR-0049 localization).
//
// INVERTED POLARITY vs makeputback_archive_test.go: the faithful resolution is
// the DEFAULT live engine, so:
//   - ON  walk = a ZERO-Options walk (faithful = production), artifact "...-on.json".
//   - OFF walk = Options{UnfaithfulPutback: true} (RESTORES master's old net-coupled,
//     3pt-reachable putback), artifact "...-off.json".
//
// Neither walk consumes FreezeMeans (UnfaithfulPutback is a derived-value transform,
// not a league-mean substitution), so BOTH are single-pass self-reference walks — no
// per-season harvest pass (unlike the MakePutback A/B).
//
// The verdict is INTERPRETED in ADR-0055 against the pre-registered gate; the test
// records the numbers and logs the verdict SIGNALS without force-ranking a winner.
// A NULL (faithful form does NOT move the factor) is a legitimate measured result
// that re-opens the RE audit — DO NOT tune to force the metric.
//
// Invoke manually (≈8 min/walk at stride 1 runs 20 per reference_archive_ab_runtime;
// two walks ≈16 min — inline-able, optionally backgrounded):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run PutbackFaithful -v -timeout 2h
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

// putbackFaithfulConfigArtifact is one config's committed measurement output: the
// full season-aggregate channel decomposition. Config is "on" (faithful/zero-Options)
// or "off" (UnfaithfulPutback = master baseline).
type putbackFaithfulConfigArtifact struct {
	Config    string                `json:"config"`
	Runs      int                   `json:"runs"`
	Stride    int                   `json:"stride"`
	Seed      uint64                `json:"seed"`
	Aggregate SeasonAggregateReport `json:"aggregate"`
}

func writePutbackFaithfulArtifact(t *testing.T, art putbackFaithfulConfigArtifact, slug string) {
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

func TestRealArchive_PutbackFaithfulResolution(t *testing.T) {
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

	// cfg "on" → zero-Options faithful (production); "off" → UnfaithfulPutback (master).
	base := func(cfg string) Options {
		o := Options{Runs: runs, SampleStride: stride, Seed: seed, Progress: os.Stderr}
		if cfg == "off" {
			o.UnfaithfulPutback = true
		}
		return o
	}

	// regularFid runs one config's walk, emits its artifact, and returns the regular
	// (game type 2) fidelity summary — the verdict bucket.
	regularFid := func(cfg string) FidelitySummary {
		reps, skips, err := CollectSeasonReports(dir, base(cfg))
		if err != nil {
			t.Fatalf("CollectSeasonReports %s: %v", cfg, err)
		}
		if len(reps) == 0 {
			t.Fatalf("no reports for %s (skips=%d) — cannot measure", cfg, len(skips))
		}
		agg := CollectSeasonAggregates(reps)
		writePutbackFaithfulArtifact(t, putbackFaithfulConfigArtifact{
			Config: cfg, Runs: runs, Stride: stride, Seed: seed, Aggregate: agg,
		}, "putback-faithful-"+cfg)
		fid, ok := regularFidelity(agg)
		if !ok {
			t.Fatalf("regular (game type 2) fidelity bucket missing in %s aggregate", cfg)
		}
		return fid
	}

	fidOff := regularFid("off") // master baseline (UnfaithfulPutback)
	fidOn := regularFid("on")   // faithful (production)

	// Finiteness: every channel term must be finite under both configs (a degenerate
	// rate season must not poison the decompose into NaN/Inf).
	for _, f := range []FidelitySummary{fidOff, fidOn} {
		for _, v := range []float64{
			f.EngineCovLnFGALnPPS, f.EngineCovLnShotsPerPossLnPPS, f.EngineCovLnPossLnPPS,
			f.EngineVarLnFGA, f.EngineVarLnPPS, f.EngineVarLnPF,
		} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Fatalf("non-finite channel term in decompose: %v", v)
			}
		}
	}

	// Verdict readout (INTERPRETED in ADR-0055). The FACTOR Cov(ln(FGA/POSS),lnPPS) is
	// the pre-registered target: it should move from engine ≈−0.00087 toward real ≈0.
	// The HEADLINE Cov(lnFGA,lnPPS) and Var(lnPPS) are logged for the SUCCESS/PARTIAL/NULL
	// branches; the count residual Cov(lnPOSS,lnPPS) is logged because the headline is
	// gated on it (out of scope — ADR-0054).
	t.Logf("=== PUTBACK-FAITHFUL A/B (regular bucket, engine) ===")
	t.Logf("  FACTOR   Cov(ln(FGA/POSS),lnPPS): OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnShotsPerPossLnPPS, fidOn.EngineCovLnShotsPerPossLnPPS, fidOn.RealCovLnShotsPerPossLnPPS)
	t.Logf("  HEADLINE Cov(lnFGA,lnPPS):        OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnFGALnPPS, fidOn.EngineCovLnFGALnPPS, fidOn.RealCovLnFGALnPPS)
	t.Logf("  residual Cov(lnPOSS,lnPPS):       OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnPossLnPPS, fidOn.EngineCovLnPossLnPPS, fidOn.RealCovLnPossLnPPS)
	t.Logf("  Var(lnFGA): OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnFGA, fidOn.EngineVarLnFGA, fidOn.RealVarLnFGA)
	t.Logf("  Var(lnPPS): OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnPPS, fidOn.EngineVarLnPPS, fidOn.RealVarLnPPS)

	// Verdict signals (the gate arithmetic is applied in ADR-0055, not here).
	factorMovedTowardReal := math.Abs(fidOn.EngineCovLnShotsPerPossLnPPS-fidOn.RealCovLnShotsPerPossLnPPS) <
		math.Abs(fidOff.EngineCovLnShotsPerPossLnPPS-fidOff.RealCovLnShotsPerPossLnPPS)
	headlineMovedTowardPlus := fidOn.EngineCovLnFGALnPPS > fidOff.EngineCovLnFGALnPPS
	ppsRegressed := math.Abs(fidOn.EngineVarLnPPS-fidOn.RealVarLnPPS) > math.Abs(fidOff.EngineVarLnPPS-fidOff.RealVarLnPPS)
	varFGANarrowed := math.Abs(fidOn.EngineVarLnFGA-fidOn.RealVarLnFGA) < math.Abs(fidOff.EngineVarLnFGA-fidOff.RealVarLnFGA)
	t.Logf("  VERDICT SIGNALS: factor_moved_toward_real=%v  headline_moved_toward_plus=%v  pps_regressed=%v  var_fga_narrowed=%v",
		factorMovedTowardReal, headlineMovedTowardPlus, ppsRegressed, varFGANarrowed)
	t.Logf("  GATE (ADR-0055): SUCCESS = factor_moved_toward_real AND NOT pps_regressed; PARTIAL = factor moved but pps_regressed (re-measure the possession-count axis); NULL = factor did NOT move (re-open RE audit, do NOT tune)")
}
