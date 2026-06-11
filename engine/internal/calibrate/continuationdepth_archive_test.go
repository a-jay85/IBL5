//go:build archive

// Continuation-chain / ORB-intensity measurement artifact over the REAL ~53 GB JSB
// backup archive (ADR-0055 lineage — the MEASURE half of a measure-then-build
// program; the eventual decay/cap FIX is OUT OF SCOPE and gets its own ADR). This
// walks the season-aggregate channel decomposition ONCE (no engine behavior toggle)
// and writes a committed anchor carrying:
//
//   - Part A — the ORB-intensity channel ORB/POSS: mean intensity (level),
//     Var(ORB/POSS), and Cov(ORB/POSS, lnPPS), engine vs real, on the SAME
//     Dean-Oliver proxy POSS the possession split uses.
//   - Part B — the engine-only continuation-depth distribution P(k=0/1/2/≥3) per
//     game type plus the EXACT mean and Var (from Σk/Σk², never the capped buckets).
//
// Like the possession-coupling instrument there is NO behavior change: every number
// counts events Simulate already emits (EventPossessionStart, EventRebound{offensive})
// or reads box fields already present, so the golden fixture stays byte-identical
// with no freeze flag.
//
// Build-tag gated behind `archive` so it is NEVER compiled by `go test ./...` or
// engine.yml. Invoke manually (run in the background; do not poll):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run ContinuationDepth -v -timeout 6h
package calibrate

import (
	"encoding/json"
	"fmt"
	"math"
	"os"
	"path/filepath"
	"sort"
	"testing"
	"time"
)

// contDepthArtifact is the committed anchor: the run config plus the full
// season-aggregate report (whose fidelity block now carries the Part A ORB-intensity
// terms and whose ContinuationDepth array carries the Part B histogram).
type contDepthArtifact struct {
	Generated string                `json:"generated"`
	Runs      int                   `json:"runs"`
	Stride    int                   `json:"stride"`
	Seed      uint64                `json:"seed"`
	Aggregate SeasonAggregateReport `json:"aggregate"`
}

// regularContinuationDepth returns the regular (game type 2) Part B histogram entry.
func regularContinuationDepth(agg SeasonAggregateReport) (ContinuationDepth, bool) {
	for _, cd := range agg.ContinuationDepth {
		if cd.GameType == 2 {
			return cd, true
		}
	}
	return ContinuationDepth{}, false
}

func TestRealArchive_ContinuationDepth(t *testing.T) {
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

	art := contDepthArtifact{
		Generated: time.Now().Format(time.RFC3339),
		Runs:      runs, Stride: stride, Seed: seed,
		Aggregate: agg,
	}
	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-continuation-depth.json", time.Now().Format("20060102")))
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
	cd, ok := regularContinuationDepth(agg)
	if !ok {
		t.Fatal("regular (game type 2) continuation-depth bucket missing")
	}

	// Finiteness: no Part A or Part B term may be NaN/Inf on real data.
	for name, v := range map[string]float64{
		"real_oreb_intensity": fid.RealOrebIntensity, "engine_oreb_intensity": fid.EngineOrebIntensity,
		"real_var_oreb_intensity": fid.RealVarOrebIntensity, "engine_var_oreb_intensity": fid.EngineVarOrebIntensity,
		"real_cov_oreb_intensity_ln_pps": fid.RealCovOrebIntensityLnPPS, "engine_cov_oreb_intensity_ln_pps": fid.EngineCovOrebIntensityLnPPS,
		"cont_depth_mean": cd.Mean, "cont_depth_var": cd.Var,
		"cont_depth_p0": cd.P0, "cont_depth_p1": cd.P1, "cont_depth_p2": cd.P2, "cont_depth_p3plus": cd.P3Plus,
	} {
		if math.IsNaN(v) || math.IsInf(v, 0) {
			t.Fatalf("non-finite term %s = %v", name, v)
		}
	}

	// Non-degenerate: the corpus must actually exercise ORB-intensity spread.
	if fid.EngineVarOrebIntensity <= 0 {
		t.Errorf("Var(ORB/POSS) must be > 0 on a multi-team corpus: engine %v", fid.EngineVarOrebIntensity)
	}
	// A variance is never negative; the pooled Var from Σk/Σk² must stay ≥ 0.
	if cd.Var < 0 {
		t.Errorf("ContinuationDepth.Var = %v, want ≥ 0 (Σk²/N − mean²)", cd.Var)
	}
	// The histogram is a probability distribution: P0..P3Plus sum to 1.0.
	if psum := cd.P0 + cd.P1 + cd.P2 + cd.P3Plus; math.Abs(psum-1.0) > 1e-9 {
		t.Errorf("P0..P3Plus = %v, want sum 1.0", psum)
	}

	// ── Part A verdict (INTERPRETED in the trace doc; the test records, never ranks).
	t.Logf("REGULAR CHANNEL — ORB-intensity ORB/POSS (engine vs real):")
	t.Logf("  mean ORB/POSS:            engine=%.5f real=%.5f", fid.EngineOrebIntensity, fid.RealOrebIntensity)
	t.Logf("  Var(ORB/POSS):            engine=%.6f real=%.6f", fid.EngineVarOrebIntensity, fid.RealVarOrebIntensity)
	t.Logf("  Cov(ORB/POSS, lnPPS):     engine=%+.6f real=%+.6f", fid.EngineCovOrebIntensityLnPPS, fid.RealCovOrebIntensityLnPPS)
	t.Logf("VERDICT: engine Cov strongly negative + real ≈0 ⇒ continuation INTENSITY over-couples to")
	t.Logf("         inefficiency (a later decay/cap fix is viable); engine≈real ⇒ faithful (terminal lean).")

	// ── Part B read #1 — reconciliation (LOOSE, directional only). Part B's Mean uses
	// the authoritative EventPossessionStart count as denominator; Part A's intensity
	// uses the Dean-Oliver proxy. count≠proxy is the ADR-0049 premise, so a small gap
	// is expected — flag only a >~15% divergence as a units bug, never a 1e-9 identity.
	t.Logf("REGULAR CHANNEL — continuation depth (engine-only, %d possessions pooled):", cd.N)
	t.Logf("  P(k): k0=%.4f k1=%.4f k2=%.4f k≥3=%.4f", cd.P0, cd.P1, cd.P2, cd.P3Plus)
	t.Logf("  mean k=%.4f  Var k=%.4f (EXACT, from Σk/Σk² — NOT the capped buckets)", cd.Mean, cd.Var)
	if fid.EngineOrebIntensity > 0 {
		ratio := cd.Mean / fid.EngineOrebIntensity
		t.Logf("  read #1 reconciliation: meanK(count-segmented)=%.4f vs ORB/POSS(proxy)=%.4f  ratio=%.3f",
			cd.Mean, fid.EngineOrebIntensity, ratio)
		if d := math.Abs(ratio - 1.0); d > 0.15 {
			t.Logf("  ⚠ read #1 divergence %.1f%% > 15%% — INVESTIGATE a units bug in the count→aggregate path", d*100)
		} else {
			t.Logf("  read #1 within 15%% — count and proxy reconcile (expected count≠proxy gap)")
		}
	}

	// ── Part B read #2 — geometric-implied histogram from the engine's own mean. A
	// memoryless per-trip P(OREB) would give a geometric tail p=mean/(1+mean),
	// P(k)=(1−p)·pᵏ. A FATTER realized k≥2 tail ⇒ per-trip continuation probability
	// DECAYS (later putbacks rarer); UNIFORM inflation ⇒ a cap/floor artifact.
	if cd.Mean > 0 {
		p := cd.Mean / (1 + cd.Mean)
		geo := func(k int) float64 { return (1 - p) * math.Pow(p, float64(k)) }
		geo3plus := 1 - (geo(0) + geo(1) + geo(2))
		t.Logf("  read #2 geometric-implied (p=%.4f): k0=%.4f k1=%.4f k2=%.4f k≥3=%.4f",
			p, geo(0), geo(1), geo(2), geo3plus)
		t.Logf("            realized − geometric:    k0=%+.4f k1=%+.4f k2=%+.4f k≥3=%+.4f",
			cd.P0-geo(0), cd.P1-geo(1), cd.P2-geo(2), cd.P3Plus-geo3plus)
	}

	// ── Part B read #3 — PPS-tercile tail split. Do LOW-PPS (inefficient) teams own
	// the k≥2 continuation tail? That makes the anti-coupling visible: split the
	// per-team continuation tallies by engine PPS (PF/FGA) tercile over the regular
	// bucket and log each tercile's mean k and P(k≥3).
	type ptRow struct {
		pps                    float64
		n, sumK, sumK2, b3plus float64
	}
	var rows []ptRow
	for _, sa := range agg.Seasons {
		if sa.GameType != 2 {
			continue
		}
		for _, ts := range sa.Teams {
			if ts.EngineFGAPerG <= 0 || ts.EngineContDepthN <= 0 {
				continue
			}
			rows = append(rows, ptRow{
				pps:    ts.EnginePointsForPG / ts.EngineFGAPerG,
				n:      ts.EngineContDepthN,
				sumK:   ts.EngineContDepthSumK,
				sumK2:  ts.EngineContDepthSumK2,
				b3plus: ts.EngineContDepthB3Plus,
			})
		}
	}
	sort.Slice(rows, func(i, j int) bool { return rows[i].pps < rows[j].pps })
	if len(rows) >= 3 {
		third := len(rows) / 3
		tercile := func(lo, hi int, label string) {
			var n, sumK, b3 float64
			for _, r := range rows[lo:hi] {
				n += r.n
				sumK += r.sumK
				b3 += r.b3plus
			}
			if n > 0 {
				t.Logf("  read #3 %s-PPS tercile (n=%d teams): mean k=%.4f  P(k≥3)=%.4f", label, hi-lo, sumK/n, b3/n)
			}
		}
		tercile(0, third, "low")
		tercile(third, 2*third, "mid")
		tercile(2*third, len(rows), "high")
		t.Logf("VERDICT: if LOW-PPS teams own the larger mean k / P(k≥3), continuation intensity")
		t.Logf("         carries the volume↔inefficiency anti-coupling (decay/cap fix targets it).")
	}
}
