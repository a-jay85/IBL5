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

// regSeason builds a one-game-type SeasonAggregate from explicit per-team rows,
// so the fidelity-metric tests control the exact PF/wins/point-diff inputs.
func regSeason(teams ...TeamStanding) SeasonAggregate {
	return SeasonAggregate{GameType: int(bundle.GameTypeRegular), Teams: teams}
}

// assertAllFinite fails if any fidelity metric is NaN or ±Inf.
func assertAllFinite(t *testing.T, fs FidelitySummary) {
	t.Helper()
	for name, v := range map[string]float64{
		"level_gap_pf": fs.LevelGapPF, "pf_corr": fs.PFCorr, "pf_dispersion_ratio": fs.PFDispersionRatio,
		"wins_corr": fs.WinsCorr, "wins_dispersion_ratio": fs.WinsDispersionRatio,
		"point_diff_corr": fs.PointDiffCorr, "point_diff_dispersion_ratio": fs.PointDiffDispersionRatio,
	} {
		if math.IsNaN(v) || math.IsInf(v, 0) {
			t.Errorf("%s = %v, want a finite number (no NaN/Inf)", name, v)
		}
	}
}

// Row #1: the flat-offense signature. Engine PF is the SAME for every team while
// the .sco PF varies across the league → ratings don't drive engine scoring, so
// pf_corr is ≈0 (and the engine's zero spread gives a dispersion ratio of 0).
func TestCollectFidelitySummaries_FlatOffenseYieldsZeroPFCorr(t *testing.T) {
	sa := regSeason(
		TeamStanding{TeamID: 1, EnginePointsForPG: 100, ScoPointsForPG: 90},
		TeamStanding{TeamID: 2, EnginePointsForPG: 100, ScoPointsForPG: 100},
		TeamStanding{TeamID: 3, EnginePointsForPG: 100, ScoPointsForPG: 110},
		TeamStanding{TeamID: 4, EnginePointsForPG: 100, ScoPointsForPG: 120},
	)
	fs := collectFidelitySummaries([]SeasonAggregate{sa})
	if len(fs) != 1 {
		t.Fatalf("summaries = %+v, want one game-type entry", fs)
	}
	if math.Abs(fs[0].PFCorr) > 1e-9 {
		t.Errorf("PFCorr = %v, want ≈0 (flat offense: engine PF constant across teams)", fs[0].PFCorr)
	}
	if fs[0].PFDispersionRatio != 0 {
		t.Errorf("PFDispersionRatio = %v, want 0 (engine PF has zero spread)", fs[0].PFDispersionRatio)
	}
}

// Row #2: pf_dispersion_ratio is stdev(engine)/stdev(sco) — <1 when the engine
// compresses the spread, exactly 1 when the spreads match (independent of level).
func TestCollectFidelitySummaries_PFDispersionRatio(t *testing.T) {
	// engine {99,101} pop-stdev 1; sco {98,102} pop-stdev 2 → ratio 0.5.
	compressed := collectFidelitySummaries([]SeasonAggregate{regSeason(
		TeamStanding{TeamID: 1, EnginePointsForPG: 99, ScoPointsForPG: 98},
		TeamStanding{TeamID: 2, EnginePointsForPG: 101, ScoPointsForPG: 102},
	)})
	if math.Abs(compressed[0].PFDispersionRatio-0.5) > 1e-9 {
		t.Errorf("compressed ratio = %v, want 0.5 (engine spread half of sco)", compressed[0].PFDispersionRatio)
	}
	// engine {95,105} pop-stdev 5; sco {93,103} pop-stdev 5 → ratio 1, even
	// though the LEVEL differs (engine mean 100 vs sco mean 98).
	equal := collectFidelitySummaries([]SeasonAggregate{regSeason(
		TeamStanding{TeamID: 1, EnginePointsForPG: 95, ScoPointsForPG: 93},
		TeamStanding{TeamID: 2, EnginePointsForPG: 105, ScoPointsForPG: 103},
	)})
	if math.Abs(equal[0].PFDispersionRatio-1) > 1e-9 {
		t.Errorf("equal-spread ratio = %v, want 1", equal[0].PFDispersionRatio)
	}
}

// Row #3: level_gap_pf is the hand-computed mean(engine_PF − sco_PF).
func TestCollectFidelitySummaries_LevelGapPF(t *testing.T) {
	// mean(engine) = 98, mean(sco) = 115 → level gap −17 (engine under-scores).
	fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
		TeamStanding{TeamID: 1, EnginePointsForPG: 100, ScoPointsForPG: 110},
		TeamStanding{TeamID: 2, EnginePointsForPG: 96, ScoPointsForPG: 120},
	)})
	if math.Abs(fs[0].LevelGapPF-(-17)) > 1e-9 {
		t.Errorf("LevelGapPF = %v, want -17 (mean engine 98 − mean sco 115)", fs[0].LevelGapPF)
	}
}

// Row #4 (boundary): degenerate inputs — a single team, or a constant .sco
// column — yield corr=0 and dispersion_ratio=0, never NaN/Inf/divide-by-zero.
// level_gap stays defined (it's a plain mean). Empty input yields no summary.
func TestCollectFidelitySummaries_DegenerateInputsAreDefined(t *testing.T) {
	t.Run("single team", func(t *testing.T) {
		fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
			TeamStanding{TeamID: 1, EnginePointsForPG: 100, ScoPointsForPG: 105, EngineExpectedWins: 41, ScoWins: 50, EnginePointDiffPG: 2, ScoPointDiffPG: 5},
		)})
		assertAllFinite(t, fs[0])
		if fs[0].PFCorr != 0 || fs[0].PFDispersionRatio != 0 {
			t.Errorf("single-team PF corr/ratio = %v/%v, want 0/0", fs[0].PFCorr, fs[0].PFDispersionRatio)
		}
		if fs[0].WinsCorr != 0 || fs[0].PointDiffDispersionRatio != 0 {
			t.Errorf("single-team wins/point-diff corr/ratio = %v/%v, want 0/0", fs[0].WinsCorr, fs[0].PointDiffDispersionRatio)
		}
		if fs[0].LevelGapPF != -5 {
			t.Errorf("LevelGapPF = %v, want -5 (still defined for one team)", fs[0].LevelGapPF)
		}
	})
	t.Run("constant sco column", func(t *testing.T) {
		fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
			TeamStanding{TeamID: 1, EnginePointsForPG: 98, ScoPointsForPG: 100},
			TeamStanding{TeamID: 2, EnginePointsForPG: 102, ScoPointsForPG: 100},
		)})
		assertAllFinite(t, fs[0])
		if fs[0].PFDispersionRatio != 0 {
			t.Errorf("PFDispersionRatio = %v, want 0 (sco zero variance, no divide-by-zero)", fs[0].PFDispersionRatio)
		}
		if fs[0].PFCorr != 0 {
			t.Errorf("PFCorr = %v, want 0 (sco zero variance, no NaN)", fs[0].PFCorr)
		}
	})
	t.Run("empty input", func(t *testing.T) {
		if fs := collectFidelitySummaries(nil); len(fs) != 0 {
			t.Errorf("empty input → %+v, want no summaries", fs)
		}
	})
}

// replicateSeasons returns k back-to-back copies of each season — the pooling /
// more-runs analog for the runs-stability test. collectFidelitySummaries only
// reads Teams, so sharing the underlying slice across copies is safe.
func replicateSeasons(seasons []SeasonAggregate, k int) []SeasonAggregate {
	var out []SeasonAggregate
	for i := 0; i < k; i++ {
		out = append(out, seasons...)
	}
	return out
}

// sumExpectedWins is an EXTENSIVE Σ (a win-share-style total) used only to
// contrast against the intensive fidelity metrics: it grows with the pool size,
// they do not.
func sumExpectedWins(seasons []SeasonAggregate) float64 {
	var s float64
	for _, sa := range seasons {
		for _, ts := range sa.Teams {
			s += ts.EngineExpectedWins
		}
	}
	return s
}

// Row #5 (runs-stability): at this pure-function layer "runs-stable" means the
// metrics are INTENSIVE — pooling more identically-distributed rows (the analog
// of averaging an engine stat over more runs) leaves level_gap and the
// dispersion ratios unchanged. That is exactly the property separating them from
// a home win-SHARE, an EXTENSIVE sum that inflates as √N with the run count
// (memory reference_jsb_winshare_runs_artifact). Below, the raw Σ expected-wins
// grows 10× with the pool while the dispersion ratio and level gap hold — so a
// future regression that swapped an intensive metric for an extensive sum (e.g.
// "fixing" expected-wins into a win-share) would fail here. The literal
// runs=1-vs-runs=50 comparison over real data lives in matrix row #7 (the
// real-archive run) and the committed calibration artifact.
func TestCollectFidelitySummaries_RunsStableIntensiveNotInflating(t *testing.T) {
	base := []SeasonAggregate{regSeason(
		TeamStanding{TeamID: 1, EnginePointsForPG: 96, ScoPointsForPG: 92, EngineExpectedWins: 30, ScoWins: 28, EnginePointDiffPG: -4, ScoPointDiffPG: -8},
		TeamStanding{TeamID: 2, EnginePointsForPG: 104, ScoPointsForPG: 112, EngineExpectedWins: 52, ScoWins: 55, EnginePointDiffPG: 4, ScoPointDiffPG: 8},
	)}
	pooled := replicateSeasons(base, 10)

	small := collectFidelitySummaries(base)[0]
	big := collectFidelitySummaries(pooled)[0]

	if big.N != 10*small.N {
		t.Fatalf("pooled N = %d, want 10× base N %d (pooling must really scale the data)", big.N, small.N)
	}
	if math.Abs(big.LevelGapPF-small.LevelGapPF) > 1e-9 {
		t.Errorf("LevelGapPF drifted under pooling: %v vs %v (not runs-stable)", big.LevelGapPF, small.LevelGapPF)
	}
	if math.Abs(big.PFDispersionRatio-small.PFDispersionRatio) > 1e-9 {
		t.Errorf("PFDispersionRatio drifted under pooling: %v vs %v (not runs-stable)", big.PFDispersionRatio, small.PFDispersionRatio)
	}
	if math.Abs(big.WinsDispersionRatio-small.WinsDispersionRatio) > 1e-9 {
		t.Errorf("WinsDispersionRatio drifted under pooling: %v vs %v (not runs-stable)", big.WinsDispersionRatio, small.WinsDispersionRatio)
	}
	// The contrast that makes the above non-vacuous: an EXTENSIVE statistic over
	// the same data DID grow 10×, so the intensive metrics' invariance is real.
	if math.Abs(sumExpectedWins(pooled)-10*sumExpectedWins(base)) > 1e-9 {
		t.Fatalf("Σ expected-wins not 10× — the pooling did not actually scale the data, so the invariance proves nothing")
	}
}

// ─── Volume / efficiency channel decomposition (matrix rows 1-6) ───────────────

// fgaRows returns two "fga" StatRows (visitor then home) to append to a wfGame.
func fgaRows(visID, homeID int, visE, visS, homeE, homeS float64) []validate.StatRow {
	return []validate.StatRow{
		{TeamID: visID, Stat: "fga", ScoVal: visS, EngineMean: visE},
		{TeamID: homeID, Stat: "fga", ScoVal: homeS, EngineMean: homeE},
	}
}

// Row #1 (pre-impl characterization): the additive FGA extension does not perturb
// any existing TeamStanding / FidelitySummary field, and the new FGA fields
// default to 0 when a snapshot carries no "fga" rows (the wfGame shape).
func TestCollectSeasonAggregates_ExistingFieldsUnchangedByFGAExtension(t *testing.T) {
	rep := aggReport("s1", bundle.GameTypeRegular,
		wfGame(1, 2, 1.0, 110, 108, 100, 99),
		wfGame(2, 1, 0.0, 95, 96, 105, 107),
	)
	got := CollectSeasonAggregates([]validate.Report{rep})
	t1 := standingFor(t, got.Seasons[0], 1)
	// Existing fields keep their hand-computed values (cf. RollsUpPerTeam).
	if t1.EnginePointsForPG != 107.5 || t1.EnginePointsAgainstPG != 97.5 || t1.EnginePointDiffPG != 10 || t1.ScoPointDiffPG != 10 {
		t.Errorf("existing fields perturbed by FGA extension: %+v", t1)
	}
	// New fields default to 0 with no "fga" rows present.
	if t1.EngineFGAPerG != 0 || t1.ScoFGAPerG != 0 {
		t.Errorf("FGA fields = %v/%v, want 0 (no fga rows in fixture)", t1.EngineFGAPerG, t1.ScoFGAPerG)
	}
	fs := collectFidelitySummaries(got.Seasons)
	if fs[0].PFDispersionRatio == 0 && fs[0].LevelGapPF == 0 {
		t.Fatalf("existing fidelity metrics vanished: %+v", fs[0])
	}
	if fs[0].VolumeDispersionRatio != 0 || fs[0].EfficiencyDispersionRatio != 0 ||
		fs[0].RealVarLnPF != 0 || fs[0].RealVarLnFGA != 0 || fs[0].RealVarLnPPS != 0 || fs[0].RealCovLnFGALnPPS != 0 {
		t.Errorf("FGA-derived metrics = %+v, want all 0 with no fga data", fs[0])
	}
}

// Row #2: fgaFor returns the team's "fga" row (already total FG, see fgaFor doc)
// and false when absent; the both-sides guard accumulates volume only for games
// where BOTH teams have an "fga" row, and divides by that fgaGP, not gp.
func TestFgaFor_AndBothSidesGuard(t *testing.T) {
	t.Run("fgaFor lookup", func(t *testing.T) {
		g := validate.GameReport{HomeTeamID: 1, VisitorTeamID: 2, Rows: []validate.StatRow{
			{TeamID: 1, Stat: "points", ScoVal: 100},
			{TeamID: 1, Stat: "fga", ScoVal: 88, EngineMean: 85},
			{TeamID: 2, Stat: "points", ScoVal: 95},
		}}
		if r, ok := fgaFor(g, 1); !ok || r.ScoVal != 88 || r.EngineMean != 85 {
			t.Errorf("fgaFor(1) = %+v ok=%v, want ScoVal 88 / EngineMean 85", r, ok)
		}
		if _, ok := fgaFor(g, 2); ok {
			t.Errorf("fgaFor(2) ok=true, want false (no fga row for team 2)")
		}
	})

	t.Run("both-sides guard + fgaGP divisor", func(t *testing.T) {
		// g1 has fga on both sides; g2 (also a played game) has fga on home only.
		// Both count for points (gp=2) but only g1 contributes volume → the divisor
		// is fgaGP=1, so ScoFGAPerG = 88, NOT (88+200)/2 and NOT 88/2.
		g1 := wfGame(1, 2, 1.0, 110, 108, 100, 99)
		g1.Rows = append(g1.Rows, fgaRows(2, 1, 80, 80, 88, 88)...)
		g2 := wfGame(1, 2, 1.0, 110, 108, 100, 99)
		g2.Rows = append(g2.Rows, validate.StatRow{TeamID: 1, Stat: "fga", ScoVal: 200, EngineMean: 200})
		got := CollectSeasonAggregates([]validate.Report{aggReport("s1", bundle.GameTypeRegular, g1, g2)})
		t1 := standingFor(t, got.Seasons[0], 1)
		if t1.GamesPlayed != 2 {
			t.Fatalf("gp = %d, want 2 (both games played)", t1.GamesPlayed)
		}
		if t1.ScoFGAPerG != 88 {
			t.Errorf("ScoFGAPerG = %v, want 88 (only the both-sides game, divided by fgaGP=1)", t1.ScoFGAPerG)
		}
	})
}

// Row #3: volume_dispersion_ratio and efficiency_dispersion_ratio are the
// PFDispersionRatio analog on each channel — 0.5 when the engine spread is half
// the .sco spread.
func TestCollectFidelitySummaries_VolumeAndEfficiencyDispersion(t *testing.T) {
	t.Run("volume", func(t *testing.T) {
		// engine FGA {99,101} pop-stdev 1; sco FGA {98,102} pop-stdev 2 → 0.5.
		fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
			TeamStanding{TeamID: 1, EngineFGAPerG: 99, ScoFGAPerG: 98, EnginePointsForPG: 100, ScoPointsForPG: 100},
			TeamStanding{TeamID: 2, EngineFGAPerG: 101, ScoFGAPerG: 102, EnginePointsForPG: 100, ScoPointsForPG: 100},
		)})
		if math.Abs(fs[0].VolumeDispersionRatio-0.5) > 1e-9 {
			t.Errorf("VolumeDispersionRatio = %v, want 0.5", fs[0].VolumeDispersionRatio)
		}
	})
	t.Run("efficiency", func(t *testing.T) {
		// FGA=100 for all → PPS=PF/100. engine PF {99,101}→PPS stdev 0.01; sco PF
		// {98,102}→PPS stdev 0.02 → ratio 0.5 (PPS, not raw PF).
		fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
			TeamStanding{TeamID: 1, EngineFGAPerG: 100, ScoFGAPerG: 100, EnginePointsForPG: 99, ScoPointsForPG: 98},
			TeamStanding{TeamID: 2, EngineFGAPerG: 100, ScoFGAPerG: 100, EnginePointsForPG: 101, ScoPointsForPG: 102},
		)})
		if math.Abs(fs[0].EfficiencyDispersionRatio-0.5) > 1e-9 {
			t.Errorf("EfficiencyDispersionRatio = %v, want 0.5", fs[0].EfficiencyDispersionRatio)
		}
	})
}

// Row #4: decomposeLogVariance closes the identity Var(lnPF)=Var(lnFGA)+
// Var(lnPPS)+2·Cov to floating tolerance, and within-season demeaning yields a
// SMALLER volume variance than raw pooling on a cross-era-pace-shifted corpus
// (the era level shift leaks into volume only when not demeaned).
func TestDecomposeLogVariance_IdentityAndWithinSeasonDemean(t *testing.T) {
	// Season A is a low-pace era (~80 FGA), season B a high-pace era (~100 FGA);
	// each season's internal spread is small relative to the cross-era gap.
	rows := []decompRow{
		{season: "A", pf: 80, fga: 80}, {season: "A", pf: 88, fga: 84},
		{season: "B", pf: 104, fga: 100}, {season: "B", pf: 112, fga: 104},
	}
	varPF, varFGA, varPPS, cov := decomposeLogVariance(rows)
	if math.Abs(varPF-(varFGA+varPPS+2*cov)) > 1e-9 {
		t.Errorf("identity broken: varPF %v != varFGA %v + varPPS %v + 2·cov %v", varPF, varFGA, varPPS, cov)
	}
	if varFGA < 0 || varPPS < 0 {
		t.Errorf("variances must be non-negative: varFGA %v varPPS %v", varFGA, varPPS)
	}
	// Raw pooling: drop the season tags (all one bucket) → the era shift is banked
	// as volume variance.
	pooled := make([]decompRow, len(rows))
	for i, r := range rows {
		pooled[i] = decompRow{season: "", pf: r.pf, fga: r.fga}
	}
	_, rawVarFGA, _, _ := decomposeLogVariance(pooled)
	if rawVarFGA <= varFGA {
		t.Errorf("raw-pooled varFGA %v should EXCEED within-season varFGA %v (cross-era pace leaks into volume when not demeaned)", rawVarFGA, varFGA)
	}
}

// Row #4b (the lever): the engine-side decomposition closes its own identity AND
// the instrument detects the volume→scoring COUPLING SIGN — the axis the audit
// turns on. The fixture gives the real (.sco) side a POSITIVE coupling (more
// shots → more points, super-proportional) and the engine side a NEGATIVE one
// (more shots → fewer points); the derived Cov(lnPF,lnFGA) = VarLnFGA +
// CovLnFGALnPPS (stated in the directly-observed lnPF/lnFGA, no shared-term
// artifact) must flip sign between the two sides.
func TestCollectFidelitySummaries_EngineSideCouplingSign(t *testing.T) {
	fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
		// sco: FGA 80/90/100 → PF 80/99/120  (positive coupling, slope > 1)
		// eng: FGA 80/90/100 → PF 100/95/90   (negative coupling)
		TeamStanding{TeamID: 1, ScoFGAPerG: 80, ScoPointsForPG: 80, EngineFGAPerG: 80, EnginePointsForPG: 100},
		TeamStanding{TeamID: 2, ScoFGAPerG: 90, ScoPointsForPG: 99, EngineFGAPerG: 90, EnginePointsForPG: 95},
		TeamStanding{TeamID: 3, ScoFGAPerG: 100, ScoPointsForPG: 120, EngineFGAPerG: 100, EnginePointsForPG: 90},
	)})[0]
	if math.Abs(fs.RealVarLnPF-(fs.RealVarLnFGA+fs.RealVarLnPPS+2*fs.RealCovLnFGALnPPS)) > 1e-9 {
		t.Errorf("real identity broken: %+v", fs)
	}
	if math.Abs(fs.EngineVarLnPF-(fs.EngineVarLnFGA+fs.EngineVarLnPPS+2*fs.EngineCovLnFGALnPPS)) > 1e-9 {
		t.Errorf("engine identity broken: %+v", fs)
	}
	realCovPFFGA := fs.RealVarLnFGA + fs.RealCovLnFGALnPPS    // = Cov(lnPF, lnFGA)
	engCovPFFGA := fs.EngineVarLnFGA + fs.EngineCovLnFGALnPPS // = Cov(lnPF, lnFGA)
	if realCovPFFGA <= 0 {
		t.Errorf("real Cov(lnPF,lnFGA) = %v, want > 0 (shots and scoring reinforce)", realCovPFFGA)
	}
	if engCovPFFGA >= 0 {
		t.Errorf("engine Cov(lnPF,lnFGA) = %v, want < 0 (engine anti-couples shots and scoring)", engCovPFFGA)
	}
}

// Row #5 (boundary — the double-count trap): the volume channel reads the "fga"
// row AS-IS (already total FG at this layer, see fgaFor) and must NOT add the
// "tga" row. A game with fga=90 and tga=30 → volume 90, never 120 (fga+tga,
// double-counting threes) and never 60. Guards the next dev who "fixes" the
// instrument per the raw-slot 2pt-only memory by summing tga (memory
// reference_sco_fgm_is_2pt — that fact is the raw slot only).
func TestVolume_ReadsFgaRowAsIs_NeverAddsTga(t *testing.T) {
	g := wfGame(1, 2, 1.0, 110, 108, 100, 99)
	g.Rows = append(g.Rows,
		validate.StatRow{TeamID: 1, Stat: "fga", ScoVal: 90, EngineMean: 90},
		validate.StatRow{TeamID: 2, Stat: "fga", ScoVal: 90, EngineMean: 90},
		validate.StatRow{TeamID: 1, Stat: "tga", ScoVal: 30, EngineMean: 30},
		validate.StatRow{TeamID: 2, Stat: "tga", ScoVal: 30, EngineMean: 30},
	)
	got := CollectSeasonAggregates([]validate.Report{aggReport("s1", bundle.GameTypeRegular, g)})
	t1 := standingFor(t, got.Seasons[0], 1)
	if t1.ScoFGAPerG != 90 {
		t.Errorf("ScoFGAPerG = %v, want 90 (fga row as-is; NOT 120 by adding tga, NOT 60)", t1.ScoFGAPerG)
	}
	if t1.EngineFGAPerG != 90 {
		t.Errorf("EngineFGAPerG = %v, want 90 (fga row as-is)", t1.EngineFGAPerG)
	}
}

// Row #6 (negative/boundary): a zero-FGA team yields finite, identity-closing
// decomposition terms (the row is dropped, no NaN/Inf/divide-by-zero), the
// efficiency ratio skips it, and a single-team season demeans to exactly 0
// variance.
func TestDecomposeAndPPS_DegenerateInputsAreFinite(t *testing.T) {
	t.Run("zero-FGA row dropped, terms finite + identity holds", func(t *testing.T) {
		varPF, varFGA, varPPS, cov := decomposeLogVariance([]decompRow{
			{season: "A", pf: 100, fga: 0}, // dropped: ln(0) undefined
			{season: "A", pf: 100, fga: 90},
			{season: "A", pf: 110, fga: 95},
		})
		for name, v := range map[string]float64{"varPF": varPF, "varFGA": varFGA, "varPPS": varPPS, "cov": cov} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Errorf("%s = %v, want finite", name, v)
			}
		}
		if math.Abs(varPF-(varFGA+varPPS+2*cov)) > 1e-9 {
			t.Errorf("identity broken on surviving rows")
		}
	})
	t.Run("efficiency ratio skips zero-FGA team, finite", func(t *testing.T) {
		fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
			TeamStanding{TeamID: 1, EngineFGAPerG: 0, ScoFGAPerG: 0, EnginePointsForPG: 100, ScoPointsForPG: 100},
			TeamStanding{TeamID: 2, EngineFGAPerG: 100, ScoFGAPerG: 100, EnginePointsForPG: 99, ScoPointsForPG: 98},
			TeamStanding{TeamID: 3, EngineFGAPerG: 100, ScoFGAPerG: 100, EnginePointsForPG: 101, ScoPointsForPG: 102},
		)})
		if e := fs[0].EfficiencyDispersionRatio; math.IsNaN(e) || math.IsInf(e, 0) {
			t.Errorf("EfficiencyDispersionRatio = %v, want finite (zero-FGA team skipped)", e)
		}
	})
	t.Run("single-team season → zero variance", func(t *testing.T) {
		v1, v2, v3, c := decomposeLogVariance([]decompRow{{season: "A", pf: 100, fga: 90}})
		if v1 != 0 || v2 != 0 || v3 != 0 || c != 0 {
			t.Errorf("single-row decomposition = %v/%v/%v/%v, want all 0 (within-season residual is 0)", v1, v2, v3, c)
		}
	})
	t.Run("empty input → zero", func(t *testing.T) {
		v1, v2, v3, c := decomposeLogVariance(nil)
		if v1 != 0 || v2 != 0 || v3 != 0 || c != 0 {
			t.Errorf("empty decomposition = %v/%v/%v/%v, want all 0", v1, v2, v3, c)
		}
	})
}

// ─── FTA-rate dispersion (the foulCompress calibration target, matrix 13-14) ────

// ftaRows returns two "fta" StatRows (visitor then home) to append to a wfGame,
// mirroring fgaRows.
func ftaRows(visID, homeID int, visE, visS, homeE, homeS float64) []validate.StatRow {
	return []validate.StatRow{
		{TeamID: visID, Stat: "fta", ScoVal: visS, EngineMean: visE},
		{TeamID: homeID, Stat: "fta", ScoVal: homeS, EngineMean: homeE},
	}
}

// Row #13a: the additive FTA extension does not perturb any existing
// TeamStanding / FidelitySummary field (including the FGA block from the prior
// extension), and the new FTA fields default to 0 when a snapshot carries no
// "fta" rows.
func TestCollectSeasonAggregates_ExistingFieldsUnchangedByFTAExtension(t *testing.T) {
	rep := aggReport("s1", bundle.GameTypeRegular,
		wfGame(1, 2, 1.0, 110, 108, 100, 99),
		wfGame(2, 1, 0.0, 95, 96, 105, 107),
	)
	got := CollectSeasonAggregates([]validate.Report{rep})
	t1 := standingFor(t, got.Seasons[0], 1)
	// Existing fields keep their hand-computed values (cf. RollsUpPerTeam).
	if t1.EnginePointsForPG != 107.5 || t1.EnginePointsAgainstPG != 97.5 || t1.EnginePointDiffPG != 10 || t1.ScoPointDiffPG != 10 {
		t.Errorf("existing fields perturbed by FTA extension: %+v", t1)
	}
	// Both FGA (prior extension) and FTA (this one) default to 0 with no rows.
	if t1.EngineFGAPerG != 0 || t1.ScoFGAPerG != 0 || t1.EngineFTAPerG != 0 || t1.ScoFTAPerG != 0 {
		t.Errorf("volume fields = FGA %v/%v FTA %v/%v, want all 0 (no rows in fixture)", t1.EngineFGAPerG, t1.ScoFGAPerG, t1.EngineFTAPerG, t1.ScoFTAPerG)
	}
	fs := collectFidelitySummaries(got.Seasons)
	if fs[0].PFDispersionRatio == 0 && fs[0].LevelGapPF == 0 {
		t.Fatalf("existing fidelity metrics vanished: %+v", fs[0])
	}
	if fs[0].FTADispersionRatio != 0 {
		t.Errorf("FTADispersionRatio = %v, want 0 with no fta data", fs[0].FTADispersionRatio)
	}
}

// Row #13b: fta_dispersion_ratio is the VolumeDispersionRatio analog on the
// free-throw channel — 0.5 when the engine FTA spread is half the .sco spread,
// independent of the FGA channel; and it stays 0 (never NaN/Inf) on a single-team
// / constant column.
func TestCollectFidelitySummaries_FTADispersion(t *testing.T) {
	t.Run("ratio", func(t *testing.T) {
		// engine FTA {99,101} pop-stdev 1; sco FTA {98,102} pop-stdev 2 → 0.5. FGA
		// set non-degenerate so the row is otherwise ordinary; FTA is independent.
		fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
			TeamStanding{TeamID: 1, EngineFTAPerG: 99, ScoFTAPerG: 98, EngineFGAPerG: 100, ScoFGAPerG: 100, EnginePointsForPG: 100, ScoPointsForPG: 100},
			TeamStanding{TeamID: 2, EngineFTAPerG: 101, ScoFTAPerG: 102, EngineFGAPerG: 100, ScoFGAPerG: 100, EnginePointsForPG: 100, ScoPointsForPG: 100},
		)})
		if math.Abs(fs[0].FTADispersionRatio-0.5) > 1e-9 {
			t.Errorf("FTADispersionRatio = %v, want 0.5", fs[0].FTADispersionRatio)
		}
	})
	t.Run("degenerate single team → 0, finite", func(t *testing.T) {
		fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
			TeamStanding{TeamID: 1, EngineFTAPerG: 25, ScoFTAPerG: 22, EnginePointsForPG: 100, ScoPointsForPG: 100},
		)})
		if e := fs[0].FTADispersionRatio; math.IsNaN(e) || math.IsInf(e, 0) || e != 0 {
			t.Errorf("single-team FTADispersionRatio = %v, want 0 (sco zero spread)", e)
		}
	})
	t.Run("constant sco column → 0", func(t *testing.T) {
		fs := collectFidelitySummaries([]SeasonAggregate{regSeason(
			TeamStanding{TeamID: 1, EngineFTAPerG: 20, ScoFTAPerG: 24, EnginePointsForPG: 100, ScoPointsForPG: 100},
			TeamStanding{TeamID: 2, EngineFTAPerG: 28, ScoFTAPerG: 24, EnginePointsForPG: 100, ScoPointsForPG: 100},
		)})
		if fs[0].FTADispersionRatio != 0 {
			t.Errorf("FTADispersionRatio = %v, want 0 (sco constant, no divide-by-zero)", fs[0].FTADispersionRatio)
		}
	})
}

// Row #14: ftaFor returns the team's "fta" row and false when absent; the
// both-sides guard accumulates FTA only for games where BOTH teams have an "fta"
// row, divided by that ftaGP (not gp) — mirroring fgaFor / the FGA guard.
func TestFtaFor_AndBothSidesGuard(t *testing.T) {
	t.Run("ftaFor lookup", func(t *testing.T) {
		g := validate.GameReport{HomeTeamID: 1, VisitorTeamID: 2, Rows: []validate.StatRow{
			{TeamID: 1, Stat: "points", ScoVal: 100},
			{TeamID: 1, Stat: "fta", ScoVal: 24, EngineMean: 22},
			{TeamID: 2, Stat: "points", ScoVal: 95},
		}}
		if r, ok := ftaFor(g, 1); !ok || r.ScoVal != 24 || r.EngineMean != 22 {
			t.Errorf("ftaFor(1) = %+v ok=%v, want ScoVal 24 / EngineMean 22", r, ok)
		}
		if _, ok := ftaFor(g, 2); ok {
			t.Errorf("ftaFor(2) ok=true, want false (no fta row for team 2)")
		}
	})

	t.Run("both-sides guard + ftaGP divisor", func(t *testing.T) {
		// g1 has fta on both sides; g2 (also played) has fta on home only. Both
		// count for points (gp=2) but only g1 contributes FTA → divisor ftaGP=1, so
		// ScoFTAPerG = 24, NOT (24+50)/2 and NOT 24/2.
		g1 := wfGame(1, 2, 1.0, 110, 108, 100, 99)
		g1.Rows = append(g1.Rows, ftaRows(2, 1, 20, 20, 24, 24)...)
		g2 := wfGame(1, 2, 1.0, 110, 108, 100, 99)
		g2.Rows = append(g2.Rows, validate.StatRow{TeamID: 1, Stat: "fta", ScoVal: 50, EngineMean: 50})
		got := CollectSeasonAggregates([]validate.Report{aggReport("s1", bundle.GameTypeRegular, g1, g2)})
		t1 := standingFor(t, got.Seasons[0], 1)
		if t1.GamesPlayed != 2 {
			t.Fatalf("gp = %d, want 2 (both games played)", t1.GamesPlayed)
		}
		if t1.ScoFTAPerG != 24 {
			t.Errorf("ScoFTAPerG = %v, want 24 (only the both-sides game, divided by ftaGP=1)", t1.ScoFTAPerG)
		}
	})
}
