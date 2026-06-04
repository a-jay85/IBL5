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
	// FGA-per-game = total field-goal attempts (2pt+3pt, see fgaFor) per game.
	// These feed the volume/efficiency dispersion decomposition (memory-grounded:
	// reference_sco_fgm_is_2pt notes the 2pt-only fact is the raw slot only). 0
	// when the snapshot carried no "fga" rows for the team.
	EngineFGAPerG float64 `json:"engine_fga_per_g"`
	ScoFGAPerG    float64 `json:"sco_fga_per_g"`
	// FTA-per-game = total free-throw attempts per game, engine vs .sco. The
	// foul-bucket compression target: foulCompress (sim/teamquality.go) narrows the
	// team-to-team quality spread that drives the foul rate, so its independent
	// corpus target is the FTA-rate dispersion (FTADispersionRatio → 1.0). 0 when
	// the snapshot carried no "fta" rows for the team (same both-sides guard as FGA).
	EngineFTAPerG float64 `json:"engine_fta_per_g"`
	ScoFTAPerG    float64 `json:"sco_fta_per_g"`
	// Engine-only by-origin FGA/game (ADR-0042 empty-FGA split). No .sco
	// counterpart (real box scores carry no origin tag); reported, never gated.
	// The three sum to EngineFGAPerG (every attempt has exactly one origin).
	EngineFGAInitialPerG    float64 `json:"engine_fga_initial_per_g"`
	EngineFGAOrebPerG       float64 `json:"engine_fga_oreb_per_g"`
	EngineFGATransitionPerG float64 `json:"engine_fga_transition_per_g"`
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
// The volume/efficiency block answers WHICH CHANNEL drives — and which the
// engine compresses — for team scoring, decomposing PF = FGA × PPS (PPS :=
// PF/FGA, points per total field-goal attempt). The GAP ratios are the
// PFDispersionRatio analog on each channel; the Real* TARGET terms decompose the
// REAL (.sco) scoring spread itself:
//
//   - VolumeDispersionRatio     stdev(engine FGA/g) / stdev(sco FGA/g): GAP — is
//     the engine's team-to-team SHOT-VOLUME spread compressed?
//   - EfficiencyDispersionRatio stdev(engine PPS) / stdev(sco PPS): GAP — is the
//     engine's team-to-team EFFICIENCY spread compressed?
//   - Real{VarLnPF,VarLnFGA,VarLnPPS,CovLnFGALnPPS} and the parallel
//     Engine{...} block: the exact log-variance identity
//     Var(lnPF)=Var(lnFGA)+Var(lnPPS)+2·Cov on the .sco side and the engine side,
//     each demeaned WITHIN season (so cross-era pace shifts do not leak into the
//     volume share). These eight terms are the TARGET-vs-GAP decomposition. From
//     them a reviewer derives the artifact-free, shared-term-free headlines in the
//     DIRECTLY-OBSERVED variables lnPF, lnFGA (PPS is a derived quotient, lnPPS =
//     lnPF − lnFGA, so it shares the FGA term — do NOT headline the PPS
//     correlation):
//     Cov(lnPF,lnFGA)        = VarLnFGA + CovLnFGALnPPS
//     volume share of scoring = Cov(lnPF,lnFGA) / VarLnPF
//     scoring-on-volume slope = Cov(lnPF,lnFGA) / VarLnFGA  ("+10% shots ⇒ +N% pts")
//     The diagnostic finding (2026-06-03 corpus): the engine deviates on THREE
//     axes — Engine VarLnFGA ≈2.6× Real and VarLnPPS ≈2.0× Real (both marginals
//     too WIDE) AND the covariance is wrong-signed (real + / engine −), the
//     cancellation that COMPRESSES total scoring (slope real ≈1.20, engine ≈0.24).
//     Matching real dispersion needs the positive covariance AND narrower
//     marginals (covariance alone overshoots ~2.3×); the slope is a diagnostic, not
//     a target. A volume-marginal fix (ADR-0040 candidate A) pushes the wrong axis
//     and was null on dispersion (over-dispersion pre-dates A).
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
	// Volume/efficiency channel decomposition (see the type doc). GAP ratios use
	// the same raw pooling as PFDispersionRatio; the Real* TARGET terms are
	// within-season demeaned. All 0 (never NaN/Inf) on degenerate input.
	VolumeDispersionRatio     float64 `json:"volume_dispersion_ratio"`
	EfficiencyDispersionRatio float64 `json:"efficiency_dispersion_ratio"`
	// FTADispersionRatio = stdev(engine FTA/g) / stdev(sco FTA/g): the GAP analog
	// on the free-throw-volume channel — is the engine's team-to-team FTA spread
	// compressed (<1) or too wide (>1)? This is foulCompress's INDEPENDENT
	// calibration target (Constraint 1): foulCompress is tuned so this ratio → 1.0,
	// never tuned toward the emergent Cov(lnFGA,lnPPS) sign. 0 (never NaN/Inf) on
	// degenerate input, exactly like VolumeDispersionRatio.
	FTADispersionRatio  float64 `json:"fta_dispersion_ratio"`
	RealVarLnPF         float64 `json:"real_var_ln_pf"`
	RealVarLnFGA        float64 `json:"real_var_ln_fga"`
	RealVarLnPPS        float64 `json:"real_var_ln_pps"`
	RealCovLnFGALnPPS   float64 `json:"real_cov_ln_fga_ln_pps"`
	EngineVarLnPF       float64 `json:"engine_var_ln_pf"`
	EngineVarLnFGA      float64 `json:"engine_var_ln_fga"`
	EngineVarLnPPS      float64 `json:"engine_var_ln_pps"`
	EngineCovLnFGALnPPS float64 `json:"engine_cov_ln_fga_ln_pps"`
}

// SeasonAggregateReport is the full season-aggregate readout: the per-season
// standings detail, the per-game-type residual rollup (gap SIZE), and the
// per-game-type fidelity summary (gap SHAPE — level/correlation/dispersion).
type SeasonAggregateReport struct {
	Seasons   []SeasonAggregate   `json:"seasons"`
	Residuals []StandingsResidual `json:"residuals"`
	Fidelity  []FidelitySummary   `json:"fidelity"`
	// FGAOriginDecomp is the engine-only ADR-0042 empty-FGA-split diagnostic: per
	// game type, how each shot ORIGIN (initial / oreb-continuation / transition)
	// contributes to the team-to-team FGA variance. Reported, never gated (no .sco
	// side); the dominant origin is the Lever-2 calibration target.
	FGAOriginDecomp []OriginDecomp `json:"fga_origin_decomp"`
}

// OriginDecomp is one game type's by-origin FGA-variance attribution (see
// decomposeByOrigin). CovInitial+CovOreb+CovTransition == VarTotal; the *Share
// fields are each contribution as a fraction of VarTotal (0 when VarTotal==0),
// the legible "which origin drives the FGA spread" readout.
type OriginDecomp struct {
	GameType        int     `json:"game_type"`
	N               int     `json:"n"`
	VarTotal        float64 `json:"var_total_fga"`
	CovInitial      float64 `json:"cov_initial"`
	CovOreb         float64 `json:"cov_oreb"`
	CovTransition   float64 `json:"cov_transition"`
	ShareInitial    float64 `json:"share_initial"`
	ShareOreb       float64 `json:"share_oreb"`
	ShareTransition float64 `json:"share_transition"`
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
	engFGA     float64 // Σ total FGA (2pt+3pt) over fgaGP games, engine side
	scoFGA     float64 // Σ total FGA over fgaGP games, .sco side
	fgaGP      int     // games where BOTH teams had an "fga" row (FGA divisor)
	engFTA     float64 // Σ total FTA over ftaGP games, engine side
	scoFTA     float64 // Σ total FTA over ftaGP games, .sco side
	ftaGP      int     // games where BOTH teams had an "fta" row (FTA divisor)
	// Engine-only by-origin FGA sums over gp games (the ADR-0042 empty-FGA split;
	// no .sco counterpart). Divided by gp for the per-game means in TeamStanding.
	engFGAInit, engFGAOreb, engFGATrans float64
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

			// Volume: accumulate total FGA only when BOTH sides carry an "fga"
			// row, mirroring the both-sides points guard. In the real pipeline
			// compareGame always emits "fga" alongside "points", so fgaGP == gp;
			// the guard keeps the FGA-per-game divisor correct if it ever does not.
			homeFGA, okHF := fgaFor(g, g.HomeTeamID)
			visFGA, okVF := fgaFor(g, g.VisitorTeamID)
			hasFGA := okHF && okVF

			// FTA accumulates on the same both-sides guard as FGA — the foulCompress
			// dispersion target (FTADispersionRatio).
			homeFTA, okHT := ftaFor(g, g.HomeTeamID)
			visFTA, okVT := ftaFor(g, g.VisitorTeamID)
			hasFTA := okHT && okVT

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

			if hasFGA {
				h.engFGA += homeFGA.EngineMean
				h.scoFGA += homeFGA.ScoVal
				h.fgaGP++
				v.engFGA += visFGA.EngineMean
				v.scoFGA += visFGA.ScoVal
				v.fgaGP++
			}

			if hasFTA {
				h.engFTA += homeFTA.EngineMean
				h.scoFTA += homeFTA.ScoVal
				h.ftaGP++
				v.engFTA += visFTA.EngineMean
				v.scoFTA += visFTA.ScoVal
				v.ftaGP++
			}

			// Engine-only by-origin FGA (reported, never gated). Indexing a nil
			// EngineOriginFGA map yields the zero OriginFGA — safe.
			ho := g.EngineOriginFGA[g.HomeTeamID]
			h.engFGAInit += ho.Initial
			h.engFGAOreb += ho.Oreb
			h.engFGATrans += ho.Transition
			vo := g.EngineOriginFGA[g.VisitorTeamID]
			v.engFGAInit += vo.Initial
			v.engFGAOreb += vo.Oreb
			v.engFGATrans += vo.Transition
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
				TeamID:                  id,
				GamesPlayed:             t.gp,
				EngineExpectedWins:      t.engWins,
				ScoWins:                 t.scoWins,
				EnginePointsForPG:       t.engFor / gp,
				ScoPointsForPG:          t.scoFor / gp,
				EnginePointsAgainstPG:   t.engAgainst / gp,
				ScoPointsAgainstPG:      t.scoAgainst / gp,
				EnginePointDiffPG:       (t.engFor - t.engAgainst) / gp,
				ScoPointDiffPG:          (t.scoFor - t.scoAgainst) / gp,
				EngineFGAPerG:           perGame(t.engFGA, t.fgaGP),
				ScoFGAPerG:              perGame(t.scoFGA, t.fgaGP),
				EngineFTAPerG:           perGame(t.engFTA, t.ftaGP),
				ScoFTAPerG:              perGame(t.scoFTA, t.ftaGP),
				EngineFGAInitialPerG:    perGame(t.engFGAInit, t.gp),
				EngineFGAOrebPerG:       perGame(t.engFGAOreb, t.gp),
				EngineFGATransitionPerG: perGame(t.engFGATrans, t.gp),
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
	out.FGAOriginDecomp = collectOriginDecomp(out.Seasons)
	return out
}

// collectOriginDecomp pools every (season, team) row's engine by-origin FGA/game
// by game type and runs the by-origin variance attribution (decomposeByOrigin).
// Engine-only; emitted in ascending game-type order for deterministic output.
func collectOriginDecomp(seasons []SeasonAggregate) []OriginDecomp {
	byType := map[bundle.GameType][]originRow{}
	for _, sa := range seasons {
		gt := bundle.GameType(sa.GameType)
		for _, ts := range sa.Teams {
			byType[gt] = append(byType[gt], originRow{
				season:     sa.Label,
				fgaInitial: ts.EngineFGAInitialPerG,
				fgaOreb:    ts.EngineFGAOrebPerG,
				fgaT:       ts.EngineFGATransitionPerG,
			})
		}
	}
	gts := make([]bundle.GameType, 0, len(byType))
	for gt := range byType {
		gts = append(gts, gt)
	}
	sort.Slice(gts, func(i, j int) bool { return gts[i] < gts[j] })
	var out []OriginDecomp
	for _, gt := range gts {
		rows := byType[gt]
		varTotal, ci, co, ct := decomposeByOrigin(rows)
		out = append(out, OriginDecomp{
			GameType:        int(gt),
			N:               len(rows),
			VarTotal:        varTotal,
			CovInitial:      ci,
			CovOreb:         co,
			CovTransition:   ct,
			ShareInitial:    originShare(ci, varTotal),
			ShareOreb:       originShare(co, varTotal),
			ShareTransition: originShare(ct, varTotal),
		})
	}
	return out
}

// originShare returns c/total, or 0 when total == 0 (never a divide-by-zero).
func originShare(c, total float64) float64 {
	if total == 0 {
		return 0
	}
	return c / total
}

// fidAcc pools one game type's paired engine/.sco per-team series across the
// already-rolled-up season aggregates.
type fidAcc struct {
	engPF, scoPF     []float64
	engWins, scoWins []float64
	engDiff, scoDiff []float64
	engFGA, scoFGA   []float64 // per-team total-FGA/g, parallel to engPF/scoPF
	engFTA, scoFTA   []float64 // per-team total-FTA/g, parallel to engPF/scoPF
	season           []string  // per-row season label, for within-season demean
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
			fa.engFGA = append(fa.engFGA, ts.EngineFGAPerG)
			fa.scoFGA = append(fa.scoFGA, ts.ScoFGAPerG)
			fa.engFTA = append(fa.engFTA, ts.EngineFTAPerG)
			fa.scoFTA = append(fa.scoFTA, ts.ScoFTAPerG)
			fa.season = append(fa.season, sa.Label)
		}
	}

	var out []FidelitySummary
	for _, gt := range sortedFidGameTypes(byType) {
		fa := byType[gt]
		// GAP: engine-vs-sco efficiency (PPS=PF/FGA) spread. PPS rows where FGA<=0
		// are dropped (no divide-by-zero); the paired engine/sco PPS stay aligned.
		engPPS, scoPPS := pairedPPS(fa)
		// TARGET vs GAP: within-season-demeaned log-variance decomposition of the
		// REAL (.sco) scoring spread and, in parallel, the ENGINE one. The two
		// covariances are the lever: real volume↔efficiency reinforce (+), the
		// engine anti-couples them (−), collapsing total scoring despite wider
		// marginals.
		varPF, varFGA, varPPS, cov := decomposeLogVariance(decompRows(fa, false))
		eVarPF, eVarFGA, eVarPPS, eCov := decomposeLogVariance(decompRows(fa, true))
		// LevelGapPF = mean(engPF − scoPF) = mean(engPF) − mean(scoPF) exactly
		// (same N), the absolute scoring-level gap.
		out = append(out, FidelitySummary{
			GameType:                  int(gt),
			N:                         len(fa.engPF),
			LevelGapPF:                mean(fa.engPF) - mean(fa.scoPF),
			PFCorr:                    pearson(fa.engPF, fa.scoPF),
			PFDispersionRatio:         dispersionRatio(fa.engPF, fa.scoPF),
			WinsCorr:                  pearson(fa.engWins, fa.scoWins),
			WinsDispersionRatio:       dispersionRatio(fa.engWins, fa.scoWins),
			PointDiffCorr:             pearson(fa.engDiff, fa.scoDiff),
			PointDiffDispersionRatio:  dispersionRatio(fa.engDiff, fa.scoDiff),
			VolumeDispersionRatio:     dispersionRatio(fa.engFGA, fa.scoFGA),
			EfficiencyDispersionRatio: dispersionRatio(engPPS, scoPPS),
			FTADispersionRatio:        dispersionRatio(fa.engFTA, fa.scoFTA),
			RealVarLnPF:               varPF,
			RealVarLnFGA:              varFGA,
			RealVarLnPPS:              varPPS,
			RealCovLnFGALnPPS:         cov,
			EngineVarLnPF:             eVarPF,
			EngineVarLnFGA:            eVarFGA,
			EngineVarLnPPS:            eVarPPS,
			EngineCovLnFGALnPPS:       eCov,
		})
	}
	return out
}

// pairedPPS builds the engine/.sco points-per-FGA series, aligned and dropping
// any row where EITHER side's FGA is non-positive (PPS undefined). Keeping the
// pair aligned means dispersionRatio compares the same teams on both sides.
func pairedPPS(fa *fidAcc) (eng, sco []float64) {
	for i := range fa.scoFGA {
		if fa.engFGA[i] <= 0 || fa.scoFGA[i] <= 0 {
			continue
		}
		eng = append(eng, fa.engPF[i]/fa.engFGA[i])
		sco = append(sco, fa.scoPF[i]/fa.scoFGA[i])
	}
	return eng, sco
}

// decompRows packages the per-(season,team) PF/FGA pairs for the within-season
// log-variance decomposition — the engine side when useEngine, else the .sco
// (real) side.
func decompRows(fa *fidAcc, useEngine bool) []decompRow {
	rows := make([]decompRow, len(fa.scoPF))
	for i := range fa.scoPF {
		if useEngine {
			rows[i] = decompRow{season: fa.season[i], pf: fa.engPF[i], fga: fa.engFGA[i]}
		} else {
			rows[i] = decompRow{season: fa.season[i], pf: fa.scoPF[i], fga: fa.scoFGA[i]}
		}
	}
	return rows
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

// perGame returns sum/games, or 0 when games == 0 (no contributing games — never
// a divide-by-zero).
func perGame(sum float64, games int) float64 {
	if games == 0 {
		return 0
	}
	return sum / float64(games)
}

// decompRow is one (season, team) scoring observation for the log-variance
// decomposition: total points and total FGA per game, tagged by season.
type decompRow struct {
	season  string
	pf, fga float64
}

// decomposeLogVariance splits the real team-scoring spread into a VOLUME term
// and an EFFICIENCY term via the EXACT identity
//
//	Var(lnPF) = Var(lnFGA) + Var(lnPPS) + 2·Cov(lnFGA, lnPPS),   PPS := PF/FGA
//
// which holds pointwise because lnPF = lnFGA + lnPPS, so the returned terms close
// to floating tolerance by construction (the test asserts this). lnPPS is taken
// as lnPF − lnFGA, never recomputed from a separate PF/FGA division, so the
// identity cannot drift.
//
// Each of lnPF/lnFGA/lnPPS is DEMEANED WITHIN its season before the variance is
// taken: the corpus spans 24→26→28-team eras whose league pace differs, and raw
// pooling would bank that cross-era level shift as VOLUME variance and bias the
// verdict toward volume. Within-season residuals are globally mean-zero (each
// season's residuals sum to 0), so the divisor-N sums below are population
// variances/covariance over the pooled residuals.
//
// Rows with pf<=0 or fga<=0 are dropped (ln undefined) — no NaN/Inf. Empty input,
// or a single-team season (its residuals are all exactly 0), yields 0 variance,
// never NaN.
func decomposeLogVariance(rows []decompRow) (varPF, varFGA, varPPS, cov float64) {
	type logRow struct {
		season             string
		lnPF, lnFGA, lnPPS float64
	}
	valid := make([]logRow, 0, len(rows))
	for _, r := range rows {
		if r.pf <= 0 || r.fga <= 0 {
			continue // ln undefined — skip, never NaN/Inf
		}
		lnPF := math.Log(r.pf)
		lnFGA := math.Log(r.fga)
		valid = append(valid, logRow{r.season, lnPF, lnFGA, lnPF - lnFGA})
	}
	n := float64(len(valid))
	if n == 0 {
		return 0, 0, 0, 0
	}

	// Per-season sums → per-season means for the within-season demean.
	sumPF := map[string]float64{}
	sumFGA := map[string]float64{}
	sumPPS := map[string]float64{}
	cnt := map[string]float64{}
	for _, v := range valid {
		sumPF[v.season] += v.lnPF
		sumFGA[v.season] += v.lnFGA
		sumPPS[v.season] += v.lnPPS
		cnt[v.season]++
	}

	var ssPF, ssFGA, ssPPS, sCov float64
	for _, v := range valid {
		c := cnt[v.season]
		rPF := v.lnPF - sumPF[v.season]/c
		rFGA := v.lnFGA - sumFGA[v.season]/c
		rPPS := v.lnPPS - sumPPS[v.season]/c
		ssPF += rPF * rPF
		ssFGA += rFGA * rFGA
		ssPPS += rPPS * rPPS
		sCov += rFGA * rPPS
	}
	return ssPF / n, ssFGA / n, ssPPS / n, sCov / n
}

// originRow is one (season, team) engine FGA-per-game observation split by shot
// ORIGIN (initial / oreb-continuation / transition). Engine-only — real .sco box
// scores carry no origin tag.
type originRow struct {
	season                    string
	fgaInitial, fgaOreb, fgaT float64
}

// decomposeByOrigin attributes the within-season cross-team variance of total
// engine FGA-per-game to its three shot ORIGINS via the exact covariance identity
//
//	Var(FGA_total) = Σ_o Cov(FGA_o, FGA_total),   FGA_total = Σ_o FGA_o
//
// so covInitial + covOreb + covTransition == varTotal to float tolerance (the
// test asserts this). Each contribution is the within-season-demeaned covariance
// of that origin's per-game FGA with the team total: the larger it is, the more
// that origin's cross-team variation drives the team-to-team FGA spread — the
// ADR-0042 empty-FGA-split diagnostic, whose dominant origin is the Lever-2
// calibration target. The total is taken as the SUM of the three components (not
// a separate field) so the identity holds by construction even if a caller's
// "total" ever disagreed with its parts. Within-season demeaned to match
// decomposeLogVariance's era handling. Empty input, or a single-team season
// (residuals all 0), yields zeros — never NaN/Inf.
func decomposeByOrigin(rows []originRow) (varTotal, covInitial, covOreb, covTransition float64) {
	n := float64(len(rows))
	if n == 0 {
		return 0, 0, 0, 0
	}
	type acc struct{ init, oreb, trans, total, cnt float64 }
	sums := map[string]*acc{}
	for _, r := range rows {
		a := sums[r.season]
		if a == nil {
			a = &acc{}
			sums[r.season] = a
		}
		a.init += r.fgaInitial
		a.oreb += r.fgaOreb
		a.trans += r.fgaT
		a.total += r.fgaInitial + r.fgaOreb + r.fgaT
		a.cnt++
	}
	for _, r := range rows {
		a := sums[r.season]
		c := a.cnt
		rt := (r.fgaInitial + r.fgaOreb + r.fgaT) - a.total/c
		ri := r.fgaInitial - a.init/c
		ro := r.fgaOreb - a.oreb/c
		rtr := r.fgaT - a.trans/c
		varTotal += rt * rt
		covInitial += ri * rt
		covOreb += ro * rt
		covTransition += rtr * rt
	}
	return varTotal / n, covInitial / n, covOreb / n, covTransition / n
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
