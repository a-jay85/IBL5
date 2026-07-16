//go:build archive

// baseTimeMid mean-pace re-center sweep over the REAL ~53 GB JSB backup archive
// (J23, the coupled round-half-up + base-time re-center fix — ADR-0085). Build-tag
// gated behind `archive` so it is NEVER compiled by `go test ./...` or engine.yml.
//
// J21's A/B (ADR-0085) showed the 5.60-faithful round-half-up step rule, shipped
// ALONE, regresses mean pace: it lengthens the central baseTimeMid = 14.5 step to
// 15s, dropping mean possessions from ~101.9 (trunc) to ~97.6 vs real ~104.6.
// Truncation's downward bias had been masking a too-slow base_time center (real
// effective center ≈ 1440 / 104.6 ≈ 13.77s). J23 ships round-half-up COUPLED with
// a baseTimeMid re-center; this sweep measures candidate centers WITH round-half-up
// already live (it landed in Phase 1, before this instrument) so every value
// reflects the final step rule.
//
// PRIMARY metric: league mean pace — the authoritative EventPossessionStart tally
// (TeamStanding.EnginePossCountPerG), averaged across the regular-bucket (game
// type 2) team standings, judged against real ~104.6 poss/g. The Dean-Oliver proxy
// EnginePossPerG is logged as a secondary cross-check.
//
// SECONDARY: the four FidelitySummary gate terms are logged per config as a
// preview of the possessioncoupling_archive_test.go gate — Cov(lnPOSS,lnPPS)
// (must trend from −0.000184 toward real +0.000241), Var(lnPOSS) (toward 0.000721
// without overshoot), Cov(ln(FGA/POSS),lnPPS), and Var(lnFGA) (must stay ≤ real
// 0.001330).
//
// The verdict is INTERPRETED (plan J23 Phase 3) against the rubric logged at the
// end; the test records the numbers without force-ranking a winner.
//
// Invoke manually (run in the background; do not poll — ~106 min/config at stride 1,
// runs 20; four configs ≈ 7h):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run BaseTimeMidSweep -v -timeout 10h
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
)

// baseTimeMidConfigArtifact is one swept center's committed measurement output: the
// full season-aggregate channel decomposition at that baseTimeMid value.
type baseTimeMidConfigArtifact struct {
	BaseTimeMid float64               `json:"base_time_mid"`
	Runs        int                   `json:"runs"`
	Stride      int                   `json:"stride"`
	Seed        uint64                `json:"seed"`
	Aggregate   SeasonAggregateReport `json:"aggregate"`
}

func writeBaseTimeMidArtifact(t *testing.T, art baseTimeMidConfigArtifact, slug string) {
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

// regularMeanPace averages the authoritative EventPossessionStart pace
// (EnginePossCountPerG) and the Dean-Oliver proxy (EnginePossPerG) over every
// regular-bucket (game type 2) team standing with games played, giving the league
// mean pace the J23 re-center is judged on.
func regularMeanPace(agg SeasonAggregateReport) (countPace, proxyPace float64, n int) {
	var sumCount, sumProxy float64
	for _, s := range agg.Seasons {
		if s.GameType != int(bundle.GameTypeRegular) {
			continue
		}
		for _, ts := range s.Teams {
			if ts.EnginePossCountPerG <= 0 {
				continue // team with no possession-tracked games — not a pace row
			}
			sumCount += ts.EnginePossCountPerG
			sumProxy += ts.EnginePossPerG
			n++
		}
	}
	if n == 0 {
		return 0, 0, 0
	}
	return sumCount / float64(n), sumProxy / float64(n), n
}

func TestRealArchive_BaseTimeMidSweep(t *testing.T) {
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

	type midResult struct {
		fid       FidelitySummary
		countPace float64
		proxyPace float64
		rows      int
	}

	// midFid runs one center's walk, emits its artifact, and returns the regular
	// (game type 2) fidelity summary plus the league mean pace — the verdict bucket.
	midFid := func(mid float64) midResult {
		m := mid // copy before taking address (per-config pointer)
		o := Options{Runs: runs, SampleStride: stride, Seed: seed, BaseTimeMid: &m, Progress: os.Stderr}
		slug := fmt.Sprintf("baseTimeMid-%.2f", mid)
		reps, skips, err := CollectSeasonReports(dir, o)
		if err != nil {
			t.Fatalf("CollectSeasonReports %s: %v", slug, err)
		}
		if len(reps) == 0 {
			t.Fatalf("no reports for %s (skips=%d) — cannot measure", slug, len(skips))
		}
		agg := CollectSeasonAggregates(reps)
		writeBaseTimeMidArtifact(t, baseTimeMidConfigArtifact{
			BaseTimeMid: mid, Runs: runs, Stride: stride, Seed: seed, Aggregate: agg,
		}, slug)
		fid, ok := regularFidelity(agg)
		if !ok {
			t.Fatalf("regular (game type 2) fidelity bucket missing in %s aggregate", slug)
		}
		countPace, proxyPace, rows := regularMeanPace(agg)
		if rows == 0 {
			t.Fatalf("no regular-bucket pace rows in %s aggregate — cannot judge mean pace", slug)
		}
		return midResult{fid: fid, countPace: countPace, proxyPace: proxyPace, rows: rows}
	}

	// Coarse bracket straddling the real effective center (1440 / 104.6 ≈ 13.77s)
	// and below today's 14.5. Phase 3 narrows this if the closest config still
	// misses ~104.6.
	mids := []float64{13.4, 13.6, 13.8, 14.0}
	results := make(map[float64]midResult, len(mids))
	for _, mid := range mids {
		results[mid] = midFid(mid)
	}

	// Finiteness: every count-split term plus the mean pace must be finite under all
	// mids (a degenerate season must not silently poison the calibration).
	for mid, r := range results {
		for _, v := range []float64{
			r.fid.EngineVarLnPoss, r.fid.EngineCovLnPossLnPPS,
			r.fid.EngineCovLnShotsPerPossLnPPS, r.fid.EngineVarLnFGA,
			r.countPace, r.proxyPace,
		} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Fatalf("non-finite term at baseTimeMid %.2f: %v", mid, v)
			}
		}
	}

	const (
		realMeanPace = 104.6    // real league mean possessions per game
		realVarPoss  = 0.000721 // real Var(lnPOSS) — approach, don't overshoot
		realCovPoss  = 0.000241 // real Cov(lnPOSS,lnPPS) — sign target
		realVarFGA   = 0.001330 // real Var(lnFGA) — dispersion-budget ceiling
	)

	for _, mid := range mids {
		r := results[mid]
		t.Logf("=== baseTimeMid = %.2f (regular bucket, engine, %d team rows) ===", mid, r.rows)
		t.Logf("  PRIMARY mean pace (EnginePossCountPerG): %.2f poss/g  (real=%.1f, |dist|=%.2f)",
			r.countPace, realMeanPace, math.Abs(r.countPace-realMeanPace))
		t.Logf("  proxy pace (EnginePossPerG, Dean-Oliver): %.2f poss/g", r.proxyPace)
		t.Logf("  Cov(lnPOSS,lnPPS):        %+.6f  (real=%+.6f — sign target)", r.fid.EngineCovLnPossLnPPS, realCovPoss)
		t.Logf("  Var(lnPOSS):              %.6f  (real=%.6f — approach, no overshoot)", r.fid.EngineVarLnPoss, realVarPoss)
		t.Logf("  Cov(ln(FGA/POSS),lnPPS):  %+.6f  (real=%+.6f)", r.fid.EngineCovLnShotsPerPossLnPPS, r.fid.RealCovLnShotsPerPossLnPPS)
		t.Logf("  Var(lnFGA) [ceiling]:     %.6f  (real=%.6f — must stay ≤)", r.fid.EngineVarLnFGA, realVarFGA)
	}
	t.Logf("RUBRIC (J23 Phase 3): select the baseTimeMid whose PRIMARY mean pace is closest to "+
		"~%.1f poss/g AND whose four-term preview does not overshoot (Var(lnPOSS) not past %.6f, "+
		"Var(lnFGA) not above %.6f). If the closest config still misses, narrow the bracket "+
		"(monotone: lower mid ⇒ shorter steps ⇒ more possessions) and re-run. The human "+
		"interprets; the test records.", realMeanPace, realVarPoss, realVarFGA)
}
