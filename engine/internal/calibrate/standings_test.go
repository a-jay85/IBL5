package calibrate

import (
	"math"
	"reflect"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// wfGame builds a one-game GameReport with a "points" row per side (tagged with
// the side's team ID, the shape CollectSeasonAggregates pairs on) plus the home
// win-fraction the season-aggregate layer consumes.
func wfGame(homeID, visID int, winFrac, homeEng, homeSco, visEng, visSco float64) validate.GameReport {
	return validate.GameReport{
		HomeTeamID:            homeID,
		VisitorTeamID:         visID,
		EngineHomeWinFraction: winFrac,
		Rows: []validate.StatRow{
			{TeamID: visID, Stat: "points", ScoVal: visSco, EngineMean: visEng},
			{TeamID: homeID, Stat: "points", ScoVal: homeSco, EngineMean: homeEng},
		},
	}
}

func aggReport(label string, gt bundle.GameType, games ...validate.GameReport) validate.Report {
	return validate.Report{Label: label, GameType: gt, Games: games}
}

// standingFor returns the TeamStanding for teamID within a SeasonAggregate.
func standingFor(t *testing.T, sa SeasonAggregate, teamID int) TeamStanding {
	t.Helper()
	for _, ts := range sa.Teams {
		if ts.TeamID == teamID {
			return ts
		}
	}
	t.Fatalf("no standing for team %d in %+v", teamID, sa.Teams)
	return TeamStanding{}
}

// Row #7: two reciprocal games (team 1 beats team 2 home and away) roll up to
// the hand-computed per-team wins, points, point-differential, and league pace.
func TestCollectSeasonAggregates_RollsUpPerTeam(t *testing.T) {
	// Game A: home 1 vs vis 2, winFrac 1.0, eng 110/100, sco 108/99.
	// Game B: home 2 vs vis 1, winFrac 0.0, eng 95/105, sco 96/107.
	rep := aggReport("s1", bundle.GameTypeRegular,
		wfGame(1, 2, 1.0, 110, 108, 100, 99),
		wfGame(2, 1, 0.0, 95, 96, 105, 107),
	)
	got := CollectSeasonAggregates([]validate.Report{rep})

	if len(got.Seasons) != 1 {
		t.Fatalf("Seasons len = %d, want 1: %+v", len(got.Seasons), got.Seasons)
	}
	sa := got.Seasons[0]
	if sa.Label != "s1" || sa.GameType != int(bundle.GameTypeRegular) || sa.NumGames != 2 {
		t.Errorf("season header wrong: %+v", sa)
	}
	if sa.EnginePacePG != 205 || sa.ScoPacePG != 205 {
		t.Errorf("pace = eng %v / sco %v, want 205 / 205", sa.EnginePacePG, sa.ScoPacePG)
	}
	// Teams sorted by ID → [1, 2].
	if len(sa.Teams) != 2 || sa.Teams[0].TeamID != 1 || sa.Teams[1].TeamID != 2 {
		t.Fatalf("teams not sorted by ID: %+v", sa.Teams)
	}

	t1 := standingFor(t, sa, 1)
	if t1.GamesPlayed != 2 || t1.EngineExpectedWins != 2.0 || t1.ScoWins != 2 {
		t.Errorf("team1 record = gp %d exp %v sco %d, want 2 / 2.0 / 2", t1.GamesPlayed, t1.EngineExpectedWins, t1.ScoWins)
	}
	if t1.EnginePointsForPG != 107.5 || t1.EnginePointsAgainstPG != 97.5 || t1.EnginePointDiffPG != 10 {
		t.Errorf("team1 engine pts = for %v against %v diff %v, want 107.5 / 97.5 / 10", t1.EnginePointsForPG, t1.EnginePointsAgainstPG, t1.EnginePointDiffPG)
	}
	if t1.ScoPointsForPG != 107.5 || t1.ScoPointsAgainstPG != 97.5 || t1.ScoPointDiffPG != 10 {
		t.Errorf("team1 sco pts = for %v against %v diff %v, want 107.5 / 97.5 / 10", t1.ScoPointsForPG, t1.ScoPointsAgainstPG, t1.ScoPointDiffPG)
	}

	t2 := standingFor(t, sa, 2)
	if t2.EngineExpectedWins != 0.0 || t2.ScoWins != 0 || t2.EnginePointDiffPG != -10 {
		t.Errorf("team2 = exp %v sco %d diff %v, want 0.0 / 0 / -10", t2.EngineExpectedWins, t2.ScoWins, t2.EnginePointDiffPG)
	}

	// Engine matches .sco exactly → zero residuals.
	if len(got.Residuals) != 1 || got.Residuals[0].GameType != int(bundle.GameTypeRegular) {
		t.Fatalf("residuals = %+v, want one regular bucket", got.Residuals)
	}
	r := got.Residuals[0]
	if r.N != 2 || r.WinsP90 != 0 || r.PointDiffP90 != 0 {
		t.Errorf("residual = %+v, want N=2 and zero gaps", r)
	}
}

// Row #8 (the core fix): a 0.6 home win-fraction contributes 0.6 to the home
// team's expected wins and 0.4 to the visitor's — NOT 1.0/0.0 the way a
// mean-margin sign would round it.
func TestCollectSeasonAggregates_ExpectedWinsIsFractionalNotRounded(t *testing.T) {
	rep := aggReport("s1", bundle.GameTypeRegular,
		wfGame(1, 2, 0.6, 104, 104, 100, 100),
	)
	got := CollectSeasonAggregates([]validate.Report{rep})
	sa := got.Seasons[0]
	if w := standingFor(t, sa, 1).EngineExpectedWins; math.Abs(w-0.6) > 1e-9 {
		t.Errorf("home expected wins = %v, want 0.6 (fractional, not rounded to 1.0)", w)
	}
	if w := standingFor(t, sa, 2).EngineExpectedWins; math.Abs(w-0.4) > 1e-9 {
		t.Errorf("visitor expected wins = %v, want 0.4 (fractional, not rounded to 0.0)", w)
	}
}

// Row #9 (negative — the readout surfaces a real gap): when the engine's
// expected wins diverge far from the .sco wins, the wins residual is large, not
// a vacuous zero. Team 1 goes 4-0 in the .sco but the engine gives each game a
// coin-flip win-fraction → expected wins 2.0, residual 2.0.
func TestCollectSeasonAggregates_DetectsWinGap(t *testing.T) {
	rep := aggReport("s1", bundle.GameTypeRegular,
		wfGame(1, 2, 0.5, 100, 101, 100, 99),
		wfGame(1, 2, 0.5, 100, 101, 100, 99),
		wfGame(1, 2, 0.5, 100, 101, 100, 99),
		wfGame(1, 2, 0.5, 100, 101, 100, 99),
	)
	got := CollectSeasonAggregates([]validate.Report{rep})
	if w := standingFor(t, got.Seasons[0], 1).EngineExpectedWins; math.Abs(w-2.0) > 1e-9 {
		t.Fatalf("team1 expected wins = %v, want 2.0", w)
	}
	if standingFor(t, got.Seasons[0], 1).ScoWins != 4 {
		t.Fatalf("team1 sco wins = %d, want 4", standingFor(t, got.Seasons[0], 1).ScoWins)
	}
	if got.Residuals[0].WinsP90 < 1.5 {
		t.Errorf("WinsP90 = %v, want a large (≥1.5) residual surfacing the gap", got.Residuals[0].WinsP90)
	}
}

// Row #10 (negative — guards): collisions and games missing a points row are
// skipped; a report with no validatable game yields no SeasonAggregate; empty
// input yields an empty report (no panic, no divide-by-zero).
func TestCollectSeasonAggregates_Guards(t *testing.T) {
	t.Run("empty input", func(t *testing.T) {
		got := CollectSeasonAggregates(nil)
		if len(got.Seasons) != 0 || len(got.Residuals) != 0 {
			t.Errorf("empty input → %+v, want empty report", got)
		}
	})

	t.Run("collision-only report yields no season", func(t *testing.T) {
		rep := aggReport("s1", bundle.GameTypeRegular, wfGame(5, 5, 1.0, 100, 100, 90, 90))
		got := CollectSeasonAggregates([]validate.Report{rep})
		if len(got.Seasons) != 0 || len(got.Residuals) != 0 {
			t.Errorf("collision-only → %+v, want no season/residual", got)
		}
	})

	t.Run("missing points row is skipped", func(t *testing.T) {
		// One valid game and one game with no "points" rows; only the valid one counts.
		bad := validate.GameReport{HomeTeamID: 1, VisitorTeamID: 2} // no Rows
		rep := aggReport("s1", bundle.GameTypeRegular,
			wfGame(1, 2, 1.0, 110, 110, 100, 100),
			bad,
		)
		got := CollectSeasonAggregates([]validate.Report{rep})
		if len(got.Seasons) != 1 || got.Seasons[0].NumGames != 1 {
			t.Fatalf("NumGames = %+v, want exactly the one valid game", got.Seasons)
		}
		if standingFor(t, got.Seasons[0], 1).GamesPlayed != 1 {
			t.Errorf("team1 GP = %d, want 1 (bad game skipped)", standingFor(t, got.Seasons[0], 1).GamesPlayed)
		}
	})
}

// Row #11: the same reports always produce an identical report (teams sorted by
// ID, residuals sorted by game type), regardless of map iteration order.
func TestCollectSeasonAggregates_Deterministic(t *testing.T) {
	reports := []validate.Report{
		aggReport("playoffs", bundle.GameTypePlayoff, wfGame(3, 1, 0.7, 99, 100, 95, 92)),
		aggReport("reg", bundle.GameTypeRegular,
			wfGame(2, 4, 0.55, 101, 103, 98, 97),
			wfGame(4, 2, 0.45, 100, 99, 102, 105),
		),
	}
	a := CollectSeasonAggregates(reports)
	b := CollectSeasonAggregates(reports)
	if !reflect.DeepEqual(a, b) {
		t.Errorf("non-deterministic output:\n a=%+v\n b=%+v", a, b)
	}
	// Residuals sorted ascending by game type (regular 2 before playoff 4).
	if len(a.Residuals) != 2 || a.Residuals[0].GameType != int(bundle.GameTypeRegular) || a.Residuals[1].GameType != int(bundle.GameTypePlayoff) {
		t.Errorf("residuals not sorted by game type: %+v", a.Residuals)
	}
}
