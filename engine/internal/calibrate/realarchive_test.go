//go:build archive

// This suite runs the calibration harness over the REAL ~53 GB JSB backup
// archive. It is build-tag gated behind `archive` so it is NEVER compiled by
// `go test ./...` or engine.yml — the corpus is large and not in the repo.
//
// Invoke manually (stride-thin to keep it tractable):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/calibrate -run RealArchive -v
//
// Override JSB_ARCHIVE_RUNS / JSB_ARCHIVE_STRIDE to trade runtime for fidelity.
package calibrate

import (
	"os"
	"strconv"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

func TestRealArchive_CalibrateEndToEnd(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

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

	// A selected season's finals snapshot may itself contain no playoff games
	// (e.g. its last snapshot is an end-of-season capture taken before the
	// postseason, observed on 95-96), so the stride-thinned sample only WARRANTS
	// a playoff bucket when it actually produced ≥1 playoff game. Gate the
	// assertion on that, so the test cannot false-negative on correct code.
	playoffGamesSampled := 0
	for _, rep := range reports {
		if rep.GameType == bundle.GameTypePlayoff {
			playoffGamesSampled += len(rep.Games)
		}
	}

	cal := Calibrate(reports, 0.95)
	if len(cal.Buckets) == 0 {
		t.Fatal("calibration produced no buckets")
	}
	var sawRegular, sawPlayoff bool
	for _, b := range cal.Buckets {
		t.Logf("game_type=%d stats=%d", b.GameType, len(b.Stats))
		if len(b.Stats) == 0 {
			t.Errorf("game_type=%d produced no stat calibrations", b.GameType)
		}
		switch b.GameType {
		case int(bundle.GameTypeRegular):
			sawRegular = true
		case int(bundle.GameTypePlayoff):
			sawPlayoff = true
		}
	}
	if !sawRegular {
		t.Error("expected a regular (game_type=2) bucket")
	}
	// PR9d: the playoff bucket comes from each selected season's finals snapshot
	// via ValidateUnscheduled. Require a game_type=4 bucket with non-empty bands
	// ONLY when the sample actually contained playoff games (otherwise the
	// stride may have landed only on no-playoff finals snapshots — see above).
	t.Logf("playoff games sampled=%d", playoffGamesSampled)
	if playoffGamesSampled > 0 && !sawPlayoff {
		t.Error("sample contained playoff games but produced no playoff (game_type=4) bucket (PR9d)")
	}

	// ADR-0042 REPORTED diagnostic (never a gate): the volume→count channel's
	// effect on Engine Var(lnFGA) and Cov(lnFGA,lnPPS) vs the Real targets, plus
	// the engine-only by-origin FGA decomposition. OBSERVED under the retired
	// additive base_time channel (offVolumeScale=0.02, pre-J24):
	// the channel widens Var(lnFGA) and does NOT flip Cov — the empty-FGA source it
	// would need to REPLACE is not isolated (ADR-0042's bounded open item).
	agg := CollectSeasonAggregates(reports)
	for _, fs := range agg.Fidelity {
		t.Logf("FIDELITY gt=%d N=%d | VarLnFGA real=%.5f engine=%.5f | Cov(lnFGA,lnPPS) real=%+.5f engine=%+.5f | VarLnPF real=%.5f engine=%.5f",
			fs.GameType, fs.N, fs.RealVarLnFGA, fs.EngineVarLnFGA, fs.RealCovLnFGALnPPS, fs.EngineCovLnFGALnPPS, fs.RealVarLnPF, fs.EngineVarLnPF)
	}
	for _, od := range agg.FGAOriginDecomp {
		t.Logf("FGA-ORIGIN gt=%d N=%d | VarTotal=%.4f | share initial=%.3f oreb=%.3f transition=%.3f | cov init=%.4f oreb=%.4f trans=%.4f",
			od.GameType, od.N, od.VarTotal, od.ShareInitial, od.ShareOreb, od.ShareTransition, od.CovInitial, od.CovOreb, od.CovTransition)
	}
	// Cov(FGA_origin, PPS) telemetry: Cov(FGA_total,PPS) = Σ_o Cov(FGA_o, PPS)
	// (exact, since FGA_total = Σ FGA_o). NOTE: these magnitudes are dominated by
	// each origin's FGA SIZE (initial ≈ 68% of FGA), so they do NOT isolate which
	// origin is intrinsically empty — reported telemetry only, not a lever pick.
	for _, gt := range []int{2, 4} {
		ci, co, ct, tot, n := covOriginPPS(agg.Seasons, gt)
		if n == 0 {
			continue
		}
		t.Logf("COV(FGA_origin,PPS) gt=%d N=%d | total=%+.5f = init %+.5f + oreb %+.5f + trans %+.5f", gt, n, tot, ci, co, ct)
	}
}

// covOriginPPS computes the within-season-demeaned Cov(FGA_origin, engine PPS)
// for each shot origin across a game type's (season, team) rows. The three sum to
// Cov(FGA_total, PPS); the most-negative origin is the Lever-2 calibration target
// (the FGA source dragging efficiency down). Reported diagnostic only.
func covOriginPPS(seasons []SeasonAggregate, gameType int) (covInit, covOreb, covTrans, covTotal float64, n int) {
	type row struct {
		season                 string
		init, oreb, trans, pps float64
	}
	var rows []row
	sumI := map[string]float64{}
	sumO := map[string]float64{}
	sumT := map[string]float64{}
	sumP := map[string]float64{}
	cnt := map[string]float64{}
	for _, sa := range seasons {
		if sa.GameType != gameType {
			continue
		}
		for _, ts := range sa.Teams {
			if ts.EngineFGAPerG <= 0 {
				continue
			}
			r := row{sa.Label, ts.EngineFGAInitialPerG, ts.EngineFGAOrebPerG, ts.EngineFGATransitionPerG, ts.EnginePointsForPG / ts.EngineFGAPerG}
			rows = append(rows, r)
			sumI[r.season] += r.init
			sumO[r.season] += r.oreb
			sumT[r.season] += r.trans
			sumP[r.season] += r.pps
			cnt[r.season]++
		}
	}
	n = len(rows)
	if n == 0 {
		return 0, 0, 0, 0, 0
	}
	for _, r := range rows {
		c := cnt[r.season]
		rp := r.pps - sumP[r.season]/c
		covInit += (r.init - sumI[r.season]/c) * rp
		covOreb += (r.oreb - sumO[r.season]/c) * rp
		covTrans += (r.trans - sumT[r.season]/c) * rp
	}
	fn := float64(n)
	covInit, covOreb, covTrans = covInit/fn, covOreb/fn, covTrans/fn
	return covInit, covOreb, covTrans, covInit + covOreb + covTrans, n
}

func envInt(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil && n > 0 {
			return n
		}
	}
	return def
}
