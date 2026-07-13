//go:build archive

// Baseline measurement over the REAL JSB backup archive (mirrors
// realarchive_test.go's walk exactly: same JSB_ARCHIVE_DIR env/default, same
// t.Skipf when absent, same JSB_ARCHIVE_RUNS/JSB_ARCHIVE_STRIDE knobs). This
// suite takes NO calibration action — it only measures and logs three
// baseline numbers from UNMODIFIED code, prefixed `MEASURE ` for easy
// grepping:
//
//   - fta_per_g:          league-wide mean team FTA/g, engine vs .sco.
//   - home_margin:        per-game-type home-court margin, engine vs .sco.
//   - home_away_fta_split: mean ENGINE FTA for the home side vs the away
//     (visitor) side across every real matchup, and their ratio, logged
//     alongside the REAL .sco split (…_SCO). The faithful per-possession foul
//     bucket is mildly ANTI-home (ratio ≈ 0.91): leg B (foul base −hca)
//     dominates leg C (offQ −hca) by ~an order of magnitude (decompile
//     :97160/:97159; TestBucketWeights_FoulBucketHCALegs_DecompilePin). The REAL
//     .sco split is PRO-home (ratio ≈ 1.14) because of emergent, home-lead-driven
//     late-game fouling this per-possession bucket does not model — so the two
//     ratios sit on OPPOSITE sides of 1.0 BY DESIGN. This line is a monitored
//     baseline, not a pass/fail gate; faithfulness is pinned against the decompile
//     arithmetic, not this aggregate.
//
// Invoke manually:
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/calibrate -run TestMeasureBaseline_Archive -v
package calibrate

import (
	"os"
	"testing"
)

func TestMeasureBaseline_Archive(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	// Same knobs as realarchive_test.go (envInt is defined there, same
	// package + build tag) — a modest, deterministic sample kept fast.
	runs := envInt("JSB_ARCHIVE_RUNS", 20)
	stride := envInt("JSB_ARCHIVE_STRIDE", 50)

	reports, skips, err := CollectSeasonReports(dir, Options{
		Runs:         runs,
		SampleStride: stride,
		Progress:     os.Stderr,
	})
	if err != nil {
		t.Fatalf("CollectSeasonReports over real archive: %v", err)
	}
	t.Logf("reports=%d skips=%d (runs=%d stride=%d)", len(reports), len(skips), runs, stride)
	if len(reports) == 0 {
		t.Fatal("expected at least one report from the real archive")
	}

	// --- fta_per_g: league-wide mean team FTA/g, engine vs .sco -----------
	agg := CollectSeasonAggregates(reports)
	var sumEngFTA, sumScoFTA float64
	var nFTA int
	for _, sa := range agg.Seasons {
		for _, ts := range sa.Teams {
			// Both-sides-present guard, mirroring pairedPPS's engFGA<=0 ||
			// scoFGA<=0 skip: a team-row with no "fta" data collapses to 0
			// via perGame's zero-games guard, not a real 0 FTA/g.
			if ts.EngineFTAPerG <= 0 || ts.ScoFTAPerG <= 0 {
				continue
			}
			sumEngFTA += ts.EngineFTAPerG
			sumScoFTA += ts.ScoFTAPerG
			nFTA++
		}
	}
	var engFTAPerG, scoFTAPerG float64
	if nFTA > 0 {
		engFTAPerG = sumEngFTA / float64(nFTA)
		scoFTAPerG = sumScoFTA / float64(nFTA)
	}
	t.Logf("MEASURE fta_per_g engine=%.2f sco=%.2f", engFTAPerG, scoFTAPerG)

	// --- home_margin: per-game-type, straight from CollectHomeMargins -----
	margins := CollectHomeMargins(reports)
	for _, m := range margins {
		t.Logf("MEASURE home_margin gt=%d engine=%.3f sco=%.3f gap=%+.3f",
			m.GameType, m.EngineHomeMargin, m.ScoHomeMargin, m.MarginGap)
	}

	// --- home_away_fta_split: THE KEY LINE ---------------------------------
	// Mirrors CollectHomeMargins' walk exactly (same collision skip guard),
	// but accumulates FTA via ftaFor instead of points via pointsFor, and
	// reads the ENGINE value (EngineMean) on both sides — the same accessor
	// CollectHomeMargins uses for its engine margin — never ScoVal.
	var sumHomeFTA, sumAwayFTA float64       // ENGINE split
	var scoSumHomeFTA, scoSumAwayFTA float64 // REAL (.sco) split — the sign the engine must match
	var nSplit int
	for _, rep := range reports {
		for _, g := range rep.Games {
			if g.HomeTeamID == g.VisitorTeamID {
				continue // collision: not a real home/away matchup
			}
			homeFTA, okHome := ftaFor(g, g.HomeTeamID)
			visFTA, okVis := ftaFor(g, g.VisitorTeamID)
			if !okHome || !okVis {
				continue // missing an "fta" row on one side
			}
			sumHomeFTA += homeFTA.EngineMean
			sumAwayFTA += visFTA.EngineMean
			scoSumHomeFTA += homeFTA.ScoVal
			scoSumAwayFTA += visFTA.ScoVal
			nSplit++
		}
	}
	var homeFTA, awayFTA, ratio float64
	var scoHomeFTA, scoAwayFTA, scoRatio float64
	if nSplit > 0 {
		homeFTA = sumHomeFTA / float64(nSplit)
		awayFTA = sumAwayFTA / float64(nSplit)
		scoHomeFTA = scoSumHomeFTA / float64(nSplit)
		scoAwayFTA = scoSumAwayFTA / float64(nSplit)
	}
	if awayFTA != 0 {
		ratio = homeFTA / awayFTA
	}
	if scoAwayFTA != 0 {
		scoRatio = scoHomeFTA / scoAwayFTA
	}
	t.Logf("MEASURE home_away_fta_split home=%.2f away=%.2f ratio=%.3f n=%d",
		homeFTA, awayFTA, ratio, nSplit)
	// The REAL sco split is the sign gate for the anti-home foul legs (J15 Phase 5):
	// the engine ratio must land on the same side of 1.0 as this sco ratio.
	t.Logf("MEASURE home_away_fta_split_SCO home=%.2f away=%.2f ratio=%.3f n=%d",
		scoHomeFTA, scoAwayFTA, scoRatio, nSplit)

	// Sanity: the split must have found at least one paired game, else the
	// ratio above is a meaningless 0/0 — fail loudly rather than silently
	// reporting a false baseline.
	if nSplit == 0 {
		t.Fatal("home_away_fta_split found no paired home/away games with fta rows")
	}
}
