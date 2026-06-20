//go:build archive

// Faithful-gate1-ORB-continuation A/B over the REAL JSB backup archive (ADR-0058).
// Build-tag gated behind `archive` so it is NEVER compiled by `go test ./...` or
// engine.yml. It measures the downstream full-sim FidelitySummary delta between the
// faithful sqrt gate-1 ORB-continuation roll (shipped ON by default since ADR-0058)
// and the old linear gate-2 path (restored by --unfaithfulOreb). The GATE is the ORB
// intensity (ORB/POSS): the ON walk must reproduce real ≈0.158, distinctly better than
// the gate-2 deficit (engine ORB/POSS ~23% high, ADR-0056).
//
// INVERTED POLARITY vs the freeze arms: the faithful resolution is the DEFAULT live
// engine, so:
//   - ON  walk = a ZERO-Options walk (faithful gate-1 = production), artifact "...-on.json".
//   - OFF walk = Options{UnfaithfulOreb: true} (RESTORES the old linear gate-2 path),
//     artifact "...-off.json".
//
// Neither walk consumes FreezeMeans (UnfaithfulOreb is a derived-value transform, not a
// league-mean substitution), so BOTH are single-pass self-reference walks — no per-season
// harvest pass.
//
// DISTINCT from the merged read-only instrument (GateContAccum / accumulateGateCont):
// the instrument measures the isolated per-resolution gate-1/gate-2/product decomposition;
// this A/B measures the downstream full-sim FidelitySummary delta between default-on
// (gate-1) and --unfaithfulOreb (gate-2). Keep both.
//
// The verdict is INTERPRETED in ADR-0060 against the pre-registered gate; the test
// records the numbers and logs the verdict SIGNALS without force-ranking a winner.
//
// Invoke manually (≈8 min/walk at stride 1 runs 20 per reference_archive_ab_runtime;
// two walks ≈16-40 min — inline-able):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run OrebGate1Faithful -v -timeout 6h
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

// orebGate1FaithfulConfigArtifact is one config's committed measurement output: the full
// season-aggregate channel decomposition. Config is "on" (faithful/zero-Options) or
// "off" (UnfaithfulOreb = the old linear gate-2 baseline).
type orebGate1FaithfulConfigArtifact struct {
	Config    string                `json:"config"`
	Runs      int                   `json:"runs"`
	Stride    int                   `json:"stride"`
	Seed      uint64                `json:"seed"`
	Aggregate SeasonAggregateReport `json:"aggregate"`
}

func writeOrebGate1FaithfulArtifact(t *testing.T, art orebGate1FaithfulConfigArtifact, slug string) {
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

func TestRealArchive_OrebGate1FaithfulResolution(t *testing.T) {
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

	// cfg "on" → zero-Options faithful gate-1 (production); "off" → UnfaithfulOreb (the
	// old linear gate-2 path).
	base := func(cfg string) Options {
		o := Options{Runs: runs, SampleStride: stride, Seed: seed, Progress: os.Stderr}
		if cfg == "off" {
			o.UnfaithfulOreb = true
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
		writeOrebGate1FaithfulArtifact(t, orebGate1FaithfulConfigArtifact{
			Config: cfg, Runs: runs, Stride: stride, Seed: seed, Aggregate: agg,
		}, "oreb-gate1-faithful-"+cfg)
		fid, ok := regularFidelity(agg)
		if !ok {
			t.Fatalf("regular (game type 2) fidelity bucket missing in %s aggregate", cfg)
		}
		return fid
	}

	fidOff := regularFid("off") // old linear gate-2 (UnfaithfulOreb)
	fidOn := regularFid("on")   // faithful gate-1 (production)

	// Finiteness: every channel term must be finite under both configs.
	for _, f := range []FidelitySummary{fidOff, fidOn} {
		for _, v := range []float64{
			f.EngineOrebIntensity, f.EngineVarLnPPS, f.EngineVarLnFGA,
			f.EngineCovLnFGALnPPS, f.EngineCovLnPossLnPPS,
		} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Fatalf("non-finite channel term in decompose: %v", v)
			}
		}
	}

	// Verdict readout (INTERPRETED in ADR-0060).
	t.Logf("=== OREB-GATE1-FAITHFUL A/B (regular bucket, engine) ===")
	t.Logf("  GATE     ORB/POSS:            OFF=%.5f ON=%.5f  (real=%.5f)", fidOff.EngineOrebIntensity, fidOn.EngineOrebIntensity, fidOn.RealOrebIntensity)
	t.Logf("  TRIPWIRE Var(lnPPS):          OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnPPS, fidOn.EngineVarLnPPS, fidOn.RealVarLnPPS)
	t.Logf("  REPORTED Var(lnFGA):          OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnFGA, fidOn.EngineVarLnFGA, fidOn.RealVarLnFGA)
	t.Logf("  REPORTED Cov(lnFGA,lnPPS):    OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnFGALnPPS, fidOn.EngineCovLnFGALnPPS, fidOn.RealCovLnFGALnPPS)
	t.Logf("  REPORTED Cov(lnPOSS,lnPPS):   OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnPossLnPPS, fidOn.EngineCovLnPossLnPPS, fidOn.RealCovLnPossLnPPS)
	t.Logf("  Cov(ORB/POSS,lnPPS):          OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovOrebIntensityLnPPS, fidOn.EngineCovOrebIntensityLnPPS, fidOn.RealCovOrebIntensityLnPPS)

	// Verdict signals (the gate arithmetic is applied in ADR-0060, not here).
	orbMovedTowardReal := math.Abs(fidOn.EngineOrebIntensity-fidOn.RealOrebIntensity) <
		math.Abs(fidOff.EngineOrebIntensity-fidOff.RealOrebIntensity)
	ppsRegressed := math.Abs(fidOn.EngineVarLnPPS-fidOn.RealVarLnPPS) > math.Abs(fidOff.EngineVarLnPPS-fidOff.RealVarLnPPS)
	t.Logf("  VERDICT SIGNALS: orb_moved_toward_real=%v  pps_regressed=%v", orbMovedTowardReal, ppsRegressed)
	t.Logf("  GATE (ADR-0058): ON ORB/POSS matches real, distinctly better than OFF's gate-2 deficit. TRIPWIRE: investigate any Var(lnPPS) movement. REPORTED: Var(lnFGA)/headline/count axis — not gated.")
}
