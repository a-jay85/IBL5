package calibrate

import (
	"math"
	"sort"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// TeamStanding is one team's season tallies within one report (one season
// bucket), engine vs .sco. The points fields are per-game means so engine and
// .sco are directly comparable regardless of games played.
//
// EngineExpectedWins is Σ per-game win-fraction (a home game adds the home
// win-fraction, an away game adds 1−it). It is runs-stable: it converges to
// Σ P(win) as runs grow, rather than rounding each game to a 0/1 win the way a
// single mean-margin sign would (which inflates favorites' records as √N — see
// memory reference_jsb_winshare_runs_artifact). ScoWins is the single .sco
// realization, so |EngineExpectedWins − ScoWins| carries an irreducible
// binomial noise floor of ~√Σp(1−p) ≈ 3–5 wins over an 82-game season even for
// a faithful engine; read the residual percentiles, not a zero floor.
type TeamStanding struct {
	TeamID                int     `json:"team_id"`
	GamesPlayed           int     `json:"games_played"`
	EngineExpectedWins    float64 `json:"engine_expected_wins"`
	ScoWins               int     `json:"sco_wins"`
	EnginePointsForPG     float64 `json:"engine_points_for_pg"`
	ScoPointsForPG        float64 `json:"sco_points_for_pg"`
	EnginePointsAgainstPG float64 `json:"engine_points_against_pg"`
	ScoPointsAgainstPG    float64 `json:"sco_points_against_pg"`
	EnginePointDiffPG     float64 `json:"engine_point_diff_pg"` // PF−PA; runs-stable, the team-level margin_gap analog
	ScoPointDiffPG        float64 `json:"sco_point_diff_pg"`
}

// SeasonAggregate is one report (one season bucket) rolled up per team, plus the
// league pace (mean total points per game across both teams). Teams are sorted
// by TeamID for determinism.
type SeasonAggregate struct {
	Label        string         `json:"label"`
	GameType     int            `json:"game_type"`
	NumGames     int            `json:"num_games"`
	EnginePacePG float64        `json:"engine_pace_pg"`
	ScoPacePG    float64        `json:"sco_pace_pg"`
	Teams        []TeamStanding `json:"teams"`
}

// StandingsResidual is the per-game-type fidelity signal: percentiles of the
// engine-vs-.sco gaps across every (season, team) row. The expected-wins
// residual is runs-stable but never zero (binomial noise, see TeamStanding);
// the point-differential residual is a pure linear mean (runs-stable, no noise
// floor). N is the number of contributing (season, team) rows.
type StandingsResidual struct {
	GameType     int     `json:"game_type"`
	N            int     `json:"n"`
	WinsP50      float64 `json:"wins_resid_p50"`
	WinsP90      float64 `json:"wins_resid_p90"`
	WinsP95      float64 `json:"wins_resid_p95"`
	WinsP99      float64 `json:"wins_resid_p99"`
	PointDiffP50 float64 `json:"point_diff_resid_p50"`
	PointDiffP90 float64 `json:"point_diff_resid_p90"`
	PointDiffP95 float64 `json:"point_diff_resid_p95"`
	PointDiffP99 float64 `json:"point_diff_resid_p99"`
}

// SeasonAggregateReport is the full season-aggregate readout: the per-season
// standings detail plus the per-game-type residual rollup that is the actual
// fidelity signal.
type SeasonAggregateReport struct {
	Seasons   []SeasonAggregate   `json:"seasons"`
	Residuals []StandingsResidual `json:"residuals"`
}

// teamAcc accumulates one team's running season sums while CollectSeasonAggregates
// walks a report's games.
type teamAcc struct {
	gp         int
	engWins    float64
	scoWins    int
	engFor     float64
	engAgainst float64
	scoFor     float64
	scoAgainst float64
}

// residAcc collects one game type's per-(season,team) residuals across reports.
type residAcc struct {
	wins      []float64
	pointDiff []float64
}

// CollectSeasonAggregates derives the team-level season aggregates from
// already-built validation reports — no engine run, no corpus walk (mirrors
// CollectHomeMargins). Each report is one season bucket: its games are rolled up
// per team into wins/points, and the engine-vs-.sco gaps feed the per-game-type
// residual rollup.
//
// A game contributes nothing when its home and visitor team IDs collide or when
// either side is missing a "points" row — both are degenerate inputs, not real
// matchups (same guards as CollectHomeMargins). A report with no contributing
// games yields no SeasonAggregate. A game type with no contributing rows yields
// no StandingsResidual, so every emitted residual bucket has N >= 1.
//
// Intended input is the season collector's one-snapshot-per-bucket reports
// (CollectSeasonReports). Each report becomes one SeasonAggregate, so feeding it
// --selection flat's reports instead double-counts: a season's cumulative .sco
// recurs at 49, 549, … 1148 games across snapshots, over-weighting heavily
// archived seasons in the residual rollup. Same no-dedup property as
// CollectHomeMargins; the committed calibration runs with --selection season.
func CollectSeasonAggregates(reports []validate.Report) SeasonAggregateReport {
	out := SeasonAggregateReport{}
	resid := map[bundle.GameType]*residAcc{}

	for _, rep := range reports {
		acc := map[int]*teamAcc{}
		numGames := 0
		var engPace, scoPace float64

		for _, g := range rep.Games {
			if g.HomeTeamID == g.VisitorTeamID {
				continue // collision: not a real home/away matchup
			}
			homePts, okHome := pointsFor(g, g.HomeTeamID)
			visPts, okVis := pointsFor(g, g.VisitorTeamID)
			if !okHome || !okVis {
				continue // missing a "points" row on one side
			}
			numGames++
			engPace += homePts.EngineMean + visPts.EngineMean
			scoPace += homePts.ScoVal + visPts.ScoVal

			h := team(acc, g.HomeTeamID)
			h.gp++
			h.engWins += g.EngineHomeWinFraction
			h.engFor += homePts.EngineMean
			h.engAgainst += visPts.EngineMean
			h.scoFor += homePts.ScoVal
			h.scoAgainst += visPts.ScoVal
			if homePts.ScoVal > visPts.ScoVal {
				h.scoWins++
			}

			v := team(acc, g.VisitorTeamID)
			v.gp++
			v.engWins += 1 - g.EngineHomeWinFraction
			v.engFor += visPts.EngineMean
			v.engAgainst += homePts.EngineMean
			v.scoFor += visPts.ScoVal
			v.scoAgainst += homePts.ScoVal
			if visPts.ScoVal > homePts.ScoVal {
				v.scoWins++
			}
		}

		if numGames == 0 {
			continue // no validatable matchups in this snapshot
		}

		sa := SeasonAggregate{
			Label:        rep.Label,
			GameType:     int(rep.GameType),
			NumGames:     numGames,
			EnginePacePG: engPace / float64(numGames),
			ScoPacePG:    scoPace / float64(numGames),
		}
		ra := residual(resid, rep.GameType)
		for _, id := range sortedTeamIDs(acc) {
			t := acc[id]
			gp := float64(t.gp)
			ts := TeamStanding{
				TeamID:                id,
				GamesPlayed:           t.gp,
				EngineExpectedWins:    t.engWins,
				ScoWins:               t.scoWins,
				EnginePointsForPG:     t.engFor / gp,
				ScoPointsForPG:        t.scoFor / gp,
				EnginePointsAgainstPG: t.engAgainst / gp,
				ScoPointsAgainstPG:    t.scoAgainst / gp,
				EnginePointDiffPG:     (t.engFor - t.engAgainst) / gp,
				ScoPointDiffPG:        (t.scoFor - t.scoAgainst) / gp,
			}
			sa.Teams = append(sa.Teams, ts)
			ra.wins = append(ra.wins, math.Abs(ts.EngineExpectedWins-float64(ts.ScoWins)))
			ra.pointDiff = append(ra.pointDiff, math.Abs(ts.EnginePointDiffPG-ts.ScoPointDiffPG))
		}
		out.Seasons = append(out.Seasons, sa)
	}

	for _, gt := range sortedResidGameTypes(resid) {
		ra := resid[gt]
		sort.Float64s(ra.wins)
		sort.Float64s(ra.pointDiff)
		out.Residuals = append(out.Residuals, StandingsResidual{
			GameType:     int(gt),
			N:            len(ra.wins),
			WinsP50:      percentile(ra.wins, 0.50),
			WinsP90:      percentile(ra.wins, 0.90),
			WinsP95:      percentile(ra.wins, 0.95),
			WinsP99:      percentile(ra.wins, 0.99),
			PointDiffP50: percentile(ra.pointDiff, 0.50),
			PointDiffP90: percentile(ra.pointDiff, 0.90),
			PointDiffP95: percentile(ra.pointDiff, 0.95),
			PointDiffP99: percentile(ra.pointDiff, 0.99),
		})
	}
	return out
}

// team returns the accumulator for id, creating it on first sight.
func team(acc map[int]*teamAcc, id int) *teamAcc {
	t := acc[id]
	if t == nil {
		t = &teamAcc{}
		acc[id] = t
	}
	return t
}

// residual returns the residual accumulator for gt, creating it on first sight.
func residual(resid map[bundle.GameType]*residAcc, gt bundle.GameType) *residAcc {
	ra := resid[gt]
	if ra == nil {
		ra = &residAcc{}
		resid[gt] = ra
	}
	return ra
}

// sortedTeamIDs returns the team IDs in ascending order for deterministic output.
func sortedTeamIDs(acc map[int]*teamAcc) []int {
	ids := make([]int, 0, len(acc))
	for id := range acc {
		ids = append(ids, id)
	}
	sort.Ints(ids)
	return ids
}

// sortedResidGameTypes returns the residual game types in ascending order.
func sortedResidGameTypes(resid map[bundle.GameType]*residAcc) []bundle.GameType {
	gts := make([]bundle.GameType, 0, len(resid))
	for gt := range resid {
		gts = append(gts, gt)
	}
	sort.Slice(gts, func(i, j int) bool { return gts[i] < gts[j] })
	return gts
}
