//go:build archive

// Shots-per-possession decoupling A/B over the REAL ~53 GB JSB backup archive
// (ADR-0053, PR-2 of ADR-0049). Build-tag gated behind `archive` so it is NEVER
// compiled by `go test ./...` or engine.yml. It measures the ADR-0053 putback
// make-value decoupling arms (MakePutback full / MakePutbackHalf blend), each routing
// OriginOffReb 2pt make-value to the per-season league mean to strip the team-quality
// variance feeding the putback efficiency↔volume coupling — the surviving suspect in
// the engine's wrong-signed Cov(ln(FGA/POSS),lnPPS) (ADR-0049 localization).
//
// Unlike the Branch-B A/B, the arm consumes FreezeMeans.MakeVal2pt, so the ON walks run
// a PER-SEASON-BUCKET two-pass internally (validateWithArms in season.go): a harvest
// pass populates the mean, then the frozen pass measures. The OFF walk is the separate
// self-reference baseline (a zero-Options walk, byte-identical to the live engine).
//
// The verdict is INTERPRETED in ADR-0053 against the pre-registered gate; the test
// records the numbers and the verdict SIGNALS without force-ranking a winner. Per the
// gate arithmetic a documented null is the LIKELY outcome — the factor may move while
// the headline Cov(lnFGA,lnPPS) stays negative, gated on the out-of-scope count residual
// Cov(lnPOSS,lnPPS). The test logs BOTH the factor and the headline so the ADR can tell
// "mechanism worked, headline gated on the separately-scoped count axis" from
// "mechanism failed."
//
// Invoke manually (run in the background; do not poll — ~106 min/config at stride 1,
// runs 20; three configs):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run MakePutback -v -timeout 6h
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

// makePutbackConfigArtifact is one config's committed measurement output: the full
// season-aggregate channel decomposition. Config is "off" | "on"; Arm names the arm
// ("" for OFF, "makePutback" | "makePutbackHalf" for the ON configs).
type makePutbackConfigArtifact struct {
	Config    string                `json:"config"`
	Arm       string                `json:"arm,omitempty"`
	Runs      int                   `json:"runs"`
	Stride    int                   `json:"stride"`
	Seed      uint64                `json:"seed"`
	Aggregate SeasonAggregateReport `json:"aggregate"`
}

func writeMakePutbackArtifact(t *testing.T, art makePutbackConfigArtifact, slug string) {
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

func TestRealArchive_MakePutbackDecoupling(t *testing.T) {
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

	base := func(arm string) Options {
		o := Options{Runs: runs, SampleStride: stride, Seed: seed, Progress: os.Stderr}
		switch arm {
		case "makePutback":
			o.MakePutback = true
		case "makePutbackHalf":
			o.MakePutbackHalf = true
		}
		return o
	}

	// regularFid runs one config's walk, emits its artifact, and returns the regular
	// (game type 2) fidelity summary — the verdict bucket.
	regularFid := func(arm, slug string) FidelitySummary {
		reps, skips, err := CollectSeasonReports(dir, base(arm))
		if err != nil {
			t.Fatalf("CollectSeasonReports %s: %v", slug, err)
		}
		if len(reps) == 0 {
			t.Fatalf("no reports for %s (skips=%d) — cannot measure", slug, len(skips))
		}
		agg := CollectSeasonAggregates(reps)
		cfg := "on"
		if arm == "" {
			cfg = "off"
		}
		writeMakePutbackArtifact(t, makePutbackConfigArtifact{
			Config: cfg, Arm: arm, Runs: runs, Stride: stride, Seed: seed, Aggregate: agg,
		}, slug)
		fid, ok := regularFidelity(agg)
		if !ok {
			t.Fatalf("regular (game type 2) fidelity bucket missing in %s aggregate", slug)
		}
		return fid
	}

	fidOff := regularFid("", "makePutback-off")
	fidFull := regularFid("makePutback", "makePutback-on")
	fidHalf := regularFid("makePutbackHalf", "makePutbackHalf-on")

	// Finiteness: every channel term must be finite under all three configs (a
	// degenerate rate season must not poison the decompose into NaN/Inf).
	for _, f := range []FidelitySummary{fidOff, fidFull, fidHalf} {
		for _, v := range []float64{
			f.EngineCovLnFGALnPPS, f.EngineCovLnShotsPerPossLnPPS, f.EngineCovLnPossLnPPS,
			f.EngineVarLnFGA, f.EngineVarLnPPS, f.EngineVarLnPF,
		} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Fatalf("non-finite channel term in decompose: %v", v)
			}
		}
	}

	// Verdict readout per arm (INTERPRETED in ADR-0053). The gate keys on the HEADLINE
	// Cov(lnFGA,lnPPS) sign flip; the FACTOR Cov(ln(FGA/POSS),lnPPS) movement is logged
	// so a "factor moved, headline still gated on the OOS count residual" null is
	// distinguishable from a "mechanism failed" null.
	for _, c := range []struct {
		arm string
		fid FidelitySummary
	}{{"makePutback", fidFull}, {"makePutbackHalf", fidHalf}} {
		f := c.fid
		t.Logf("=== ARM %s (regular bucket, engine) ===", c.arm)
		t.Logf("  HEADLINE Cov(lnFGA,lnPPS):        OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnFGALnPPS, f.EngineCovLnFGALnPPS, f.RealCovLnFGALnPPS)
		t.Logf("  FACTOR   Cov(ln(FGA/POSS),lnPPS): OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnShotsPerPossLnPPS, f.EngineCovLnShotsPerPossLnPPS, f.RealCovLnShotsPerPossLnPPS)
		t.Logf("  residual Cov(lnPOSS,lnPPS):       OFF=%+.6f ON=%+.6f  (real=%+.6f)", fidOff.EngineCovLnPossLnPPS, f.EngineCovLnPossLnPPS, f.RealCovLnPossLnPPS)
		t.Logf("  Var(lnFGA): OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnFGA, f.EngineVarLnFGA, f.RealVarLnFGA)
		t.Logf("  Var(lnPPS): OFF=%.6f ON=%.6f  (real=%.6f)", fidOff.EngineVarLnPPS, f.EngineVarLnPPS, f.RealVarLnPPS)

		covHeadlineFlipped := fidOff.EngineCovLnFGALnPPS < 0 && f.EngineCovLnFGALnPPS >= 0
		covFactorFlipped := fidOff.EngineCovLnShotsPerPossLnPPS < 0 && f.EngineCovLnShotsPerPossLnPPS >= 0
		ppsRegressed := math.Abs(f.EngineVarLnPPS-f.RealVarLnPPS) > math.Abs(fidOff.EngineVarLnPPS-fidOff.RealVarLnPPS)
		varFGANarrowed := math.Abs(f.EngineVarLnFGA-f.RealVarLnFGA) < math.Abs(fidOff.EngineVarLnFGA-fidOff.RealVarLnFGA)
		t.Logf("  VERDICT SIGNALS: headline_flipped=%v  factor_flipped=%v  pps_regressed=%v  var_fga_narrowed=%v",
			covHeadlineFlipped, covFactorFlipped, ppsRegressed, varFGANarrowed)
		t.Logf("  GATE (promote ON only if): headline_flipped AND NOT pps_regressed AND var_fga_narrowed; else record a measured null (ADR-0048 Branch-B precedent)")
	}
}
