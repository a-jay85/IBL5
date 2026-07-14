//go:build archive

// Playoff-margin overshoot audit (J21) over the REAL ~53 GB JSB backup archive.
// Extends CollectHomeMargins' dispersion output (engine + .sco per-game margin
// std dev) and measures the gt=4 (playoff) home-margin distribution against the
// gt=2 (regular) baseline AND the .sco playoff ground truth, to adjudicate the
// Fable-flagged gt=4 margin overshoot (backlog J21).
//
// AUDIT-ONLY: this PR changes NO engine sim code. playoffNetMultiplier stays
// 1.25. The verdict (overshoot vs. not) is RECORDED here and in the backlog,
// never acted on with a sim fix in this PR.
//
// Build-tag gated behind `archive` so it is NEVER compiled by `go test ./...` or
// engine.yml. Invoke manually (run in the background; do not poll):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_RUNS=20 JSB_ARCHIVE_STRIDE=1 \
//	  go test -tags archive ./internal/calibrate -run PlayoffMargin -v -timeout 6h
package calibrate

import (
	"math"
	"os"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// findMargin returns the per-game-type HomeMarginCalibration bucket, or ok=false.
func findMargin(ms []HomeMarginCalibration, gt bundle.GameType) (HomeMarginCalibration, bool) {
	for _, m := range ms {
		if m.GameType == int(gt) {
			return m, true
		}
	}
	return HomeMarginCalibration{}, false
}

// RECORDED BASELINE — run of record 2026-07-14, config runs=20 stride=1 seed=20240601:
//
//	gt2 (N=19843): engine margin=+3.892 sd=7.909 | sco margin=+4.124 sd=17.519
//	gt4 (N=1009):  engine margin=+4.306 sd=7.188 | sco margin=+4.590 sd=16.061
//	OVERSHOOT A/B: gt4−gt2 |MarginGap| delta=+0.051 pts (thr 1.00); dispersion-ratio delta=−0.004 (thr 0.15)
//	VERDICT: NOT confirmed — no playoff-specific margin overshoot. See backlog J21.
//
// Notable (informs the verdict, does NOT flip it): engine margin sd (~7–8) is
// roughly HALF the .sco sd (~16–17), and gt4 engine mean (4.306) runs COOLER than
// .sco (4.590) — the engine UNDER-disperses and runs cooler, the opposite of the
// Fable-flagged "runs hotter." That under-dispersion is GLOBAL (present at gt2
// too), not a gt4-specific lever, so the A/B verdict is correctly NO overshoot.
//
// Tolerance-band pins are calibrated to the committed DEFAULT run config
// (runs=20 stride=1 seed=20240601) and enforced only at that config; an env
// override changing runs/stride/seed logs the numbers but skips the hard pins,
// because a coarser stride legitimately shifts the engine mean.
const (
	// Filled from the run of record (2026-07-14, runs=20 stride=1 seed=20240601).
	pinGt2EngineMargin = 3.892150884
	pinGt2EngineStdDev = 7.908589026
	pinGt4EngineMargin = 4.306442022
	pinGt4EngineStdDev = 7.188251835
	pinGt2ScoMargin    = 4.124225168
	pinGt2ScoStdDev    = 17.519076628
	pinGt4ScoMargin    = 4.589692765
	pinGt4ScoStdDev    = 16.060593045

	marginPinTol    = 0.75 // engine-side band (pts): absorbs float reassociation, not distribution shifts
	minPlayoffGames = 20   // dispersion floor: never compute stddev on 1–2 playoff games

	// Overshoot trigger (Design Decisions): gt=4 engine-vs-sco discrepancy
	// materially exceeding gt=2's on EITHER axis ⇒ overshoot confirmed.
	marginOvershootThreshold = 1.0  // pts: gt4 |MarginGap| − gt2 |MarginGap|
	dispOvershootThreshold   = 0.15 // engine÷sco sd ratio: gt4 − gt2
)

func TestRealArchive_PlayoffMargin(t *testing.T) {
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
	atDefaults := runs == 20 && stride == 1 && seed == 20240601

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

	margins := CollectHomeMargins(reps)

	gt2, ok2 := findMargin(margins, bundle.GameTypeRegular)
	if !ok2 {
		t.Fatal("regular (game type 2) margin bucket missing — cannot A/B")
	}
	gt4, ok4 := findMargin(margins, bundle.GameTypePlayoff)
	if !ok4 {
		t.Fatal("playoff (game type 4) margin bucket missing — no playoff snapshot in corpus; audit cannot run")
	}

	// Non-degenerate: dispersion must never be computed on a handful of games.
	if gt4.N < minPlayoffGames {
		t.Fatalf("playoff bucket N=%d below floor %d — too few games for a dispersion audit", gt4.N, minPlayoffGames)
	}

	// Finiteness: no margin/stddev may be NaN/Inf on real data.
	for name, v := range map[string]float64{
		"gt2_engine_margin": gt2.EngineHomeMargin, "gt2_engine_sd": gt2.EngineMarginStdDev,
		"gt2_sco_margin": gt2.ScoHomeMargin, "gt2_sco_sd": gt2.ScoMarginStdDev,
		"gt4_engine_margin": gt4.EngineHomeMargin, "gt4_engine_sd": gt4.EngineMarginStdDev,
		"gt4_sco_margin": gt4.ScoHomeMargin, "gt4_sco_sd": gt4.ScoMarginStdDev,
	} {
		if math.IsNaN(v) || math.IsInf(v, 0) {
			t.Fatalf("non-finite margin term %s = %v", name, v)
		}
	}

	// Full readout — every {engine,sco}×{mean,stddev}×{gt2,gt4} + MarginGap, so the
	// verdict is auditable whichever way it fires.
	// %.9f so the recorded values are auditable at pin precision (sco pins band
	// ±1e-6; a coarser readout could not be re-pinned against them).
	t.Logf("REGULAR (gt2)  N=%d  engine margin=%+.9f sd=%.9f | sco margin=%+.9f sd=%.9f | gap=%+.9f",
		gt2.N, gt2.EngineHomeMargin, gt2.EngineMarginStdDev, gt2.ScoHomeMargin, gt2.ScoMarginStdDev, gt2.MarginGap)
	t.Logf("PLAYOFF (gt4)  N=%d  engine margin=%+.9f sd=%.9f | sco margin=%+.9f sd=%.9f | gap=%+.9f",
		gt4.N, gt4.EngineHomeMargin, gt4.EngineMarginStdDev, gt4.ScoHomeMargin, gt4.ScoMarginStdDev, gt4.MarginGap)

	// Overshoot A/B (engine-vs-sco, gt4 relative to gt2). dispRatio = engine sd ÷
	// sco sd (>1 = engine over-disperses vs the ground truth).
	dispRatio := func(m HomeMarginCalibration) float64 {
		if m.ScoMarginStdDev == 0 {
			return math.NaN()
		}
		return m.EngineMarginStdDev / m.ScoMarginStdDev
	}
	marginOvershoot := math.Abs(gt4.MarginGap) - math.Abs(gt2.MarginGap)
	dispOvershoot := dispRatio(gt4) - dispRatio(gt2)
	t.Logf("OVERSHOOT A/B: gt4−gt2 |MarginGap| delta=%+.3f pts (threshold %.2f); dispersion-ratio delta=%+.3f (threshold %.2f)",
		marginOvershoot, marginOvershootThreshold, dispOvershoot, dispOvershootThreshold)
	if marginOvershoot > marginOvershootThreshold || dispOvershoot > dispOvershootThreshold {
		t.Logf("VERDICT: OVERSHOOT CONFIRMED — record numbers + follow-on fix entry in backlog J21.")
	} else {
		t.Logf("VERDICT: NO overshoot beyond threshold — record no-overshoot verdict in backlog J21, add no new entry.")
	}

	// Tolerance-band pins — enforced only at the committed default config so a
	// coarser env-override smoke logs without failing on a legitimately shifted mean.
	if !atDefaults {
		t.Logf("non-default run config (runs=%d stride=%d seed=%d) — hard pins skipped, numbers logged only", runs, stride, seed)
		return
	}
	assertPin := func(name string, got, want, tol float64) {
		if math.Abs(got-want) > tol {
			t.Errorf("%s = %.3f, pinned %.3f ±%.2f — distribution shifted; re-adjudicate J21, do NOT blindly re-pin", name, got, want, tol)
		}
	}
	// Engine side: band absorbs float reassociation only.
	assertPin("gt2 engine margin", gt2.EngineHomeMargin, pinGt2EngineMargin, marginPinTol)
	assertPin("gt2 engine sd", gt2.EngineMarginStdDev, pinGt2EngineStdDev, marginPinTol)
	assertPin("gt4 engine margin", gt4.EngineHomeMargin, pinGt4EngineMargin, marginPinTol)
	assertPin("gt4 engine sd", gt4.EngineMarginStdDev, pinGt4EngineStdDev, marginPinTol)
	// Sco side: seed-independent ground truth → pin exact (float-only tol).
	assertPin("gt2 sco margin", gt2.ScoHomeMargin, pinGt2ScoMargin, 1e-6)
	assertPin("gt2 sco sd", gt2.ScoMarginStdDev, pinGt2ScoStdDev, 1e-6)
	assertPin("gt4 sco margin", gt4.ScoHomeMargin, pinGt4ScoMargin, 1e-6)
	assertPin("gt4 sco sd", gt4.ScoMarginStdDev, pinGt4ScoStdDev, 1e-6)
}
