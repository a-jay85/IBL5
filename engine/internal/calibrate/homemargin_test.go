package calibrate

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// ptsGame builds a one-game GameReport carrying a "points" row for each side,
// tagged with the side's team ID — the shape CollectHomeMargins pairs on.
func ptsGame(homeID, visID int, homeEngine, homeSco, visEngine, visSco float64) validate.GameReport {
	return validate.GameReport{
		HomeTeamID:    homeID,
		VisitorTeamID: visID,
		Rows: []validate.StatRow{
			{TeamID: visID, Stat: "points", ScoVal: visSco, EngineMean: visEngine},
			{TeamID: homeID, Stat: "points", ScoVal: homeSco, EngineMean: homeEngine},
		},
	}
}

func marginReport(gt bundle.GameType, games ...validate.GameReport) validate.Report {
	return validate.Report{GameType: gt, Games: games}
}

// Row #1: the margin and win-share are derived from the paired home/visitor
// "points" rows. Home Engine=108/Sco=110 (TeamID 7), visitor Engine=101/Sco=100
// (TeamID 3) → engine margin +7, sco margin +10, gap −3, both home-wins.
func TestCollectHomeMargins_DerivesMargin(t *testing.T) {
	got := CollectHomeMargins([]validate.Report{
		marginReport(bundle.GameTypeRegular, ptsGame(7, 3, 108, 110, 101, 100)),
	})
	if len(got) != 1 {
		t.Fatalf("len = %d, want 1: %+v", len(got), got)
	}
	m := got[0]
	if m.GameType != int(bundle.GameTypeRegular) {
		t.Errorf("GameType = %d, want %d", m.GameType, int(bundle.GameTypeRegular))
	}
	if m.N != 1 {
		t.Errorf("N = %d, want 1", m.N)
	}
	if m.EngineHomeMargin != 7 {
		t.Errorf("EngineHomeMargin = %v, want 7", m.EngineHomeMargin)
	}
	if m.ScoHomeMargin != 10 {
		t.Errorf("ScoHomeMargin = %v, want 10", m.ScoHomeMargin)
	}
	if m.MarginGap != -3 {
		t.Errorf("MarginGap = %v, want -3", m.MarginGap)
	}
	if m.EngineHomeWinShare != 1 || m.ScoHomeWinShare != 1 {
		t.Errorf("win shares = (%v, %v), want (1, 1)", m.EngineHomeWinShare, m.ScoHomeWinShare)
	}
	if m.WinShareGap != 0 {
		t.Errorf("WinShareGap = %v, want 0", m.WinShareGap)
	}
	if m.EngineMarginStdDev != 0 || m.ScoMarginStdDev != 0 {
		t.Errorf("std devs = (%v, %v), want (0, 0) at N=1", m.EngineMarginStdDev, m.ScoMarginStdDev)
	}
}

// Row #2 (sign/zero boundary): when the engine home margin agrees with the .sco
// home margin (both +5), MarginGap is ≈0 — the no-HCA-error case PR2 tunes
// toward.
func TestCollectHomeMargins_ZeroGapWhenEngineAgreesWithSco(t *testing.T) {
	got := CollectHomeMargins([]validate.Report{
		marginReport(bundle.GameTypeRegular, ptsGame(7, 3, 105, 105, 100, 100)),
	})
	if len(got) != 1 {
		t.Fatalf("len = %d, want 1", len(got))
	}
	if got[0].EngineHomeMargin != 5 || got[0].ScoHomeMargin != 5 {
		t.Fatalf("margins = (%v, %v), want (5, 5)", got[0].EngineHomeMargin, got[0].ScoHomeMargin)
	}
	if math.Abs(got[0].MarginGap) > 1e-9 {
		t.Errorf("MarginGap = %v, want ≈0", got[0].MarginGap)
	}
}

// Row #3 (N=0 / collision boundary): a game whose home and visitor team IDs
// collide is not a real matchup → skipped, bucket omitted, no panic, empty slice.
func TestCollectHomeMargins_OmitsEmptyBucket(t *testing.T) {
	got := CollectHomeMargins([]validate.Report{
		marginReport(bundle.GameTypeRegular, ptsGame(5, 5, 100, 100, 90, 90)),
	})
	if len(got) != 0 {
		t.Errorf("len = %d, want 0 (collision game skipped, bucket omitted): %+v", len(got), got)
	}
}

// Row #4 (defensive boundary): a game missing a "points" row contributes nothing
// — no panic, bucket omitted when it is the only game.
func TestCollectHomeMargins_SkipsGameMissingPointsRow(t *testing.T) {
	noPoints := validate.GameReport{
		HomeTeamID:    7,
		VisitorTeamID: 3,
		Rows: []validate.StatRow{
			{TeamID: 7, Stat: "reb", ScoVal: 40, EngineMean: 41},
			{TeamID: 3, Stat: "reb", ScoVal: 38, EngineMean: 37},
		},
	}
	got := CollectHomeMargins([]validate.Report{marginReport(bundle.GameTypeRegular, noPoints)})
	if len(got) != 0 {
		t.Errorf("len = %d, want 0 (no points row → skipped): %+v", len(got), got)
	}
}

// Row #5 (ordering determinism): buckets are emitted ascending by game type —
// Regular (2) before Playoff (4) — regardless of report order.
func TestCollectHomeMargins_SortedByGameType(t *testing.T) {
	got := CollectHomeMargins([]validate.Report{
		marginReport(bundle.GameTypePlayoff, ptsGame(7, 3, 100, 100, 95, 95)),
		marginReport(bundle.GameTypeRegular, ptsGame(7, 3, 100, 100, 95, 95)),
	})
	if len(got) != 2 {
		t.Fatalf("len = %d, want 2: %+v", len(got), got)
	}
	if got[0].GameType != int(bundle.GameTypeRegular) || got[1].GameType != int(bundle.GameTypePlayoff) {
		t.Errorf("order = [%d, %d], want [%d, %d]",
			got[0].GameType, got[1].GameType, int(bundle.GameTypeRegular), int(bundle.GameTypePlayoff))
	}
}

// Row #6 (aggregation + win-share fraction): two games with engine margins +10
// and −2 → mean EngineHomeMargin 4, N 2; one home-win and one home-loss →
// EngineHomeWinShare 0.5.
func TestCollectHomeMargins_AggregatesAcrossGames(t *testing.T) {
	got := CollectHomeMargins([]validate.Report{
		marginReport(bundle.GameTypeRegular,
			ptsGame(7, 3, 60, 60, 50, 50), // +10, home win
			ptsGame(7, 3, 48, 48, 50, 50), // −2,  home loss
		),
	})
	if len(got) != 1 {
		t.Fatalf("len = %d, want 1", len(got))
	}
	m := got[0]
	if m.N != 2 {
		t.Errorf("N = %d, want 2", m.N)
	}
	if m.EngineHomeMargin != 4 {
		t.Errorf("EngineHomeMargin = %v, want 4 (mean of +10 and −2)", m.EngineHomeMargin)
	}
	if math.Abs(m.EngineHomeWinShare-0.5) > 1e-9 {
		t.Errorf("EngineHomeWinShare = %v, want 0.5 (one win of two)", m.EngineHomeWinShare)
	}
	if math.Abs(m.EngineMarginStdDev-6) > 1e-9 {
		t.Errorf("EngineMarginStdDev = %v, want 6 (population sd of +10 and −2)", m.EngineMarginStdDev)
	}
	if math.Abs(m.ScoMarginStdDev-6) > 1e-9 {
		t.Errorf("ScoMarginStdDev = %v, want 6 (population sd of +10 and −2)", m.ScoMarginStdDev)
	}
}

// Row #7 (clamp guard / NaN boundary): two games with IDENTICAL margins →
// population variance is exactly 0; the sumSq/n − mean² clamp must keep the std
// dev at 0, never NaN from a float-negative variance.
func TestCollectHomeMargins_StdDevZeroForIdenticalMargins(t *testing.T) {
	got := CollectHomeMargins([]validate.Report{
		marginReport(bundle.GameTypeRegular,
			ptsGame(7, 3, 105, 105, 100, 100), // +5 on both sides
			ptsGame(7, 3, 105, 105, 100, 100), // +5 on both sides
		),
	})
	if len(got) != 1 {
		t.Fatalf("len = %d, want 1", len(got))
	}
	m := got[0]
	if math.IsNaN(m.EngineMarginStdDev) || m.EngineMarginStdDev != 0 {
		t.Errorf("EngineMarginStdDev = %v, want exactly 0 (identical margins, no NaN)", m.EngineMarginStdDev)
	}
	if math.IsNaN(m.ScoMarginStdDev) || m.ScoMarginStdDev != 0 {
		t.Errorf("ScoMarginStdDev = %v, want exactly 0 (identical margins, no NaN)", m.ScoMarginStdDev)
	}
}
