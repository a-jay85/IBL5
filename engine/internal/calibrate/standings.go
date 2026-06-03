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

// FidelitySummary is the per-game-type LEVEL + DISPERSION readout: the three
// orthogonal axes the cutover verdict turned on (memory
// reference_jsb_season_aggregate_verdict), pooled across every clean (season,
// team) row of one game type. Where StandingsResidual reports the SIZE of the
// per-team gaps, this reports their SHAPE — absolute scoring level, whether
// ratings drive scoring at all, and whether the engine compresses the
// team-to-team spread:
//
//   - LevelGapPF        mean(engine_PF_pg − sco_PF_pg): absolute scoring level
//     (era-flatness). Negative ⇒ the engine under-scores the league.
//   - PFCorr            Pearson r(engine_PF_pg, sco_PF_pg): FLAT OFFENSE — r≈0
//     means team ratings don't drive scoring.
//   - PFDispersionRatio stdev(engine_PF_pg) / stdev(sco_PF_pg): COMPRESSION —
//     <1 means the engine flattens the team-to-team scoring spread.
//   - Wins* / PointDiff* the same corr + dispersion on expected-wins vs .sco
//     wins (standings ranking + spread) and on PF−PA per game (margin).
//
// EVERY metric here is runs-stable. LevelGapPF and the PF / point-diff stats are
// linear means of EngineMean; Wins* is built on EngineExpectedWins = Σ P(win),
// which converges as runs grow rather than rounding each game to a 0/1 win. This
// is the OPPOSITE of a home win-SHARE, whose value inflates as √N with the run
// count and so is deliberately NOT reported here (see TeamStanding and memory
// reference_jsb_winshare_runs_artifact). A future reader must not "fix" the
// expected-wins metric into a win-share — that reintroduces the √N artifact.
//
// On a degenerate input (a single team, or a constant column — either side's
// stdev is 0) corr and dispersion_ratio are defined as 0, never NaN/Inf. N is
// the number of contributing (season, team) rows.
type FidelitySummary struct {
	GameType                 int     `json:"game_type"`
	N                        int     `json:"n"`
	LevelGapPF               float64 `json:"level_gap_pf"`
	PFCorr                   float64 `json:"pf_corr"`
	PFDispersionRatio        float64 `json:"pf_dispersion_ratio"`
	WinsCorr                 float64 `json:"wins_corr"`
	WinsDispersionRatio      float64 `json:"wins_dispersion_ratio"`
	PointDiffCorr            float64 `json:"point_diff_corr"`
	PointDiffDispersionRatio float64 `json:"point_diff_dispersion_ratio"`
}

// SeasonAggregateReport is the full season-aggregate readout: the per-season
// standings detail, the per-game-type residual rollup (gap SIZE), and the
// per-game-type fidelity summary (gap SHAPE — level/correlation/dispersion).
type SeasonAggregateReport struct {
	Seasons   []SeasonAggregate   `json:"seasons"`
	Residuals []StandingsResidual `json:"residuals"`
	Fidelity  []FidelitySummary   `json:"fidelity"`
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
	out.Fidelity = collectFidelitySummaries(out.Seasons)
	return out
}

// fidAcc pools one game type's paired engine/.sco per-team series across the
// already-rolled-up season aggregates.
type fidAcc struct {
	engPF, scoPF     []float64
	engWins, scoWins []float64
	engDiff, scoDiff []float64
}

// collectFidelitySummaries pools every (season, team) row by game type and
// computes the level + dispersion fidelity metrics. It walks the BUILT
// SeasonAggregates (the rolled-up standings), not the raw reports, so it adds no
// sim cost and reuses TeamStanding's already-per-game-normalized fields. A game
// type with no team rows yields no FidelitySummary; summaries are emitted in
// ascending game-type order for deterministic output.
func collectFidelitySummaries(seasons []SeasonAggregate) []FidelitySummary {
	byType := map[bundle.GameType]*fidAcc{}
	for _, sa := range seasons {
		gt := bundle.GameType(sa.GameType)
		fa := byType[gt]
		if fa == nil {
			fa = &fidAcc{}
			byType[gt] = fa
		}
		for _, ts := range sa.Teams {
			fa.engPF = append(fa.engPF, ts.EnginePointsForPG)
			fa.scoPF = append(fa.scoPF, ts.ScoPointsForPG)
			fa.engWins = append(fa.engWins, ts.EngineExpectedWins)
			fa.scoWins = append(fa.scoWins, float64(ts.ScoWins))
			fa.engDiff = append(fa.engDiff, ts.EnginePointDiffPG)
			fa.scoDiff = append(fa.scoDiff, ts.ScoPointDiffPG)
		}
	}

	var out []FidelitySummary
	for _, gt := range sortedFidGameTypes(byType) {
		fa := byType[gt]
		// LevelGapPF = mean(engPF − scoPF) = mean(engPF) − mean(scoPF) exactly
		// (same N), the absolute scoring-level gap.
		out = append(out, FidelitySummary{
			GameType:                 int(gt),
			N:                        len(fa.engPF),
			LevelGapPF:               mean(fa.engPF) - mean(fa.scoPF),
			PFCorr:                   pearson(fa.engPF, fa.scoPF),
			PFDispersionRatio:        dispersionRatio(fa.engPF, fa.scoPF),
			WinsCorr:                 pearson(fa.engWins, fa.scoWins),
			WinsDispersionRatio:      dispersionRatio(fa.engWins, fa.scoWins),
			PointDiffCorr:            pearson(fa.engDiff, fa.scoDiff),
			PointDiffDispersionRatio: dispersionRatio(fa.engDiff, fa.scoDiff),
		})
	}
	return out
}

// sortedFidGameTypes returns the fidelity game types in ascending order.
func sortedFidGameTypes(byType map[bundle.GameType]*fidAcc) []bundle.GameType {
	gts := make([]bundle.GameType, 0, len(byType))
	for gt := range byType {
		gts = append(gts, gt)
	}
	sort.Slice(gts, func(i, j int) bool { return gts[i] < gts[j] })
	return gts
}

// mean returns the arithmetic mean of xs, or 0 for an empty slice.
func mean(xs []float64) float64 {
	if len(xs) == 0 {
		return 0
	}
	var s float64
	for _, x := range xs {
		s += x
	}
	return s / float64(len(xs))
}

// pstdev returns the POPULATION standard deviation of xs (divisor N), or 0 for
// an empty slice. Population is correct here: the pooled rows are the whole set
// being described, not a sample of a larger one — and the N cancels in
// dispersionRatio anyway, so the sample-vs-population choice is immaterial to
// the ratio it feeds.
func pstdev(xs []float64) float64 {
	n := len(xs)
	if n == 0 {
		return 0
	}
	m := mean(xs)
	var ss float64
	for _, x := range xs {
		d := x - m
		ss += d * d
	}
	return math.Sqrt(ss / float64(n))
}

// pearson returns the Pearson correlation r between paired series xs and ys. It
// returns 0 — defined, never NaN/Inf — when the series are empty, length-
// mismatched, or either side has zero variance (a constant column or a single
// point), the degenerate cases the fidelity readout must tolerate.
func pearson(xs, ys []float64) float64 {
	n := len(xs)
	if n == 0 || n != len(ys) {
		return 0
	}
	mx, my := mean(xs), mean(ys)
	var sxy, sxx, syy float64
	for i := 0; i < n; i++ {
		dx := xs[i] - mx
		dy := ys[i] - my
		sxy += dx * dy
		sxx += dx * dx
		syy += dy * dy
	}
	if sxx == 0 || syy == 0 {
		return 0
	}
	return sxy / math.Sqrt(sxx*syy)
}

// dispersionRatio returns stdev(engine) / stdev(sco) — the spread-compression
// signal (<1 ⇒ the engine flattens the spread). It returns 0, never a
// divide-by-zero, when the .sco side has zero spread (a single team / constant
// column).
func dispersionRatio(engine, sco []float64) float64 {
	ss := pstdev(sco)
	if ss == 0 {
		return 0
	}
	return pstdev(engine) / ss
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
