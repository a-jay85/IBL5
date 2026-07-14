package calibrate

import (
	"math"
	"sort"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// HomeMarginCalibration is the per-game-type home-court-margin readout: the mean
// home-minus-visitor point margin the engine produced, the same margin from the
// .sco ground truth, and the gap between them. MarginGap is the home-court-
// advantage fidelity signal — PR2 tunes the engine's quality stand-in scales
// until it approaches 0.
//
// This is derived separately from the per-stat bands in CalibrationReport.Buckets
// because the band collector flattens every StatRow into per-(game type, stat)
// pools, discarding the home/visitor PAIRING that a margin requires.
type HomeMarginCalibration struct {
	GameType           int     `json:"game_type"`
	N                  int     `json:"n"`                     // games contributing to this bucket
	EngineHomeMargin   float64 `json:"engine_home_margin"`    // mean(home pts EngineMean − visitor pts EngineMean)
	ScoHomeMargin      float64 `json:"sco_home_margin"`       // mean(home pts ScoVal − visitor pts ScoVal)
	MarginGap          float64 `json:"margin_gap"`            // EngineHomeMargin − ScoHomeMargin
	EngineMarginStdDev float64 `json:"engine_margin_std_dev"` // population std dev of per-game engine (home−visitor) margins
	ScoMarginStdDev    float64 `json:"sco_margin_std_dev"`    // population std dev of per-game .sco (home−visitor) margins
	EngineHomeWinShare float64 `json:"engine_home_win_share"` // fraction of games with engine home margin > 0
	ScoHomeWinShare    float64 `json:"sco_home_win_share"`    // fraction of games with .sco home margin > 0
	WinShareGap        float64 `json:"win_share_gap"`         // EngineHomeWinShare − ScoHomeWinShare
}

// homeMarginAcc accumulates one game type's running sums while CollectHomeMargins
// walks the reports.
type homeMarginAcc struct {
	n           int
	sumEngine   float64
	sumEngineSq float64
	sumSco      float64
	sumScoSq    float64
	engineWins  int
	scoWins     int
}

// CollectHomeMargins derives the per-game-type home-court margin from already-built
// validation reports — no engine run, no corpus walk. For each game it pairs the
// home and visitor "points" rows (matched by TeamID) and accumulates the engine
// and .sco home margins; the means and win-shares are emitted per game type,
// sorted ascending by game type for determinism.
//
// A game contributes nothing when its home and visitor team IDs collide or when
// either side is missing a "points" row — both are degenerate inputs, not real
// matchups. A game type with no contributing games is omitted entirely, so every
// emitted bucket has N >= 1 (the win-share division is divide-by-zero-safe).
func CollectHomeMargins(reports []validate.Report) []HomeMarginCalibration {
	byType := map[bundle.GameType]*homeMarginAcc{}
	for _, rep := range reports {
		for _, g := range rep.Games {
			if g.HomeTeamID == g.VisitorTeamID {
				continue // collision: not a real home/away matchup
			}
			homePts, okHome := pointsFor(g, g.HomeTeamID)
			visPts, okVis := pointsFor(g, g.VisitorTeamID)
			if !okHome || !okVis {
				continue // missing a "points" row on one side
			}
			acc := byType[rep.GameType]
			if acc == nil {
				acc = &homeMarginAcc{}
				byType[rep.GameType] = acc
			}
			engMargin := homePts.EngineMean - visPts.EngineMean
			scoMargin := homePts.ScoVal - visPts.ScoVal
			acc.n++
			acc.sumEngine += engMargin
			acc.sumEngineSq += engMargin * engMargin
			acc.sumSco += scoMargin
			acc.sumScoSq += scoMargin * scoMargin
			if homePts.EngineMean > visPts.EngineMean {
				acc.engineWins++
			}
			if homePts.ScoVal > visPts.ScoVal {
				acc.scoWins++
			}
		}
	}

	gts := make([]bundle.GameType, 0, len(byType))
	for gt := range byType {
		gts = append(gts, gt)
	}
	sort.Slice(gts, func(i, j int) bool { return gts[i] < gts[j] })

	out := make([]HomeMarginCalibration, 0, len(gts))
	for _, gt := range gts {
		acc := byType[gt]
		n := float64(acc.n)
		eng := acc.sumEngine / n
		sco := acc.sumSco / n
		engWin := float64(acc.engineWins) / n
		scoWin := float64(acc.scoWins) / n
		out = append(out, HomeMarginCalibration{
			GameType:           int(gt),
			N:                  acc.n,
			EngineHomeMargin:   eng,
			ScoHomeMargin:      sco,
			MarginGap:          eng - sco,
			EngineMarginStdDev: stdDev(acc.sumEngineSq, acc.sumEngine, acc.n),
			ScoMarginStdDev:    stdDev(acc.sumScoSq, acc.sumSco, acc.n),
			EngineHomeWinShare: engWin,
			ScoHomeWinShare:    scoWin,
			WinShareGap:        engWin - scoWin,
		})
	}
	return out
}

// stdDev returns the POPULATION standard deviation (÷n, not ÷n−1) for a running
// sum / sum-of-squares pair. Population — not sample — for two reasons: (1) it is
// divide-by-zero-safe at n=1 (a game type can have a single contributing game;
// sample ÷(n−1) would divide by zero), and (2) the audit wants the corpus's own
// dispersion, not an inferential estimate of a larger population. Variance is
// clamped to ≥0 before the sqrt: sumSq/n − mean² is exact algebra, but float
// reassociation on a near-zero-variance bucket (all margins equal) can produce a
// tiny negative that would make math.Sqrt return NaN.
func stdDev(sumSq, sum float64, n int) float64 {
	fn := float64(n)
	mean := sum / fn
	variance := sumSq/fn - mean*mean
	if variance < 0 {
		variance = 0
	}
	return math.Sqrt(variance)
}

// pointsFor returns the "points" StatRow for the given team in a game and whether
// it was found. The validation harness emits exactly one "points" row per team
// (compareGame), so the first match is the team's points.
func pointsFor(g validate.GameReport, teamID int) (validate.StatRow, bool) {
	for _, r := range g.Rows {
		if r.Stat == "points" && r.TeamID == teamID {
			return r, true
		}
	}
	return validate.StatRow{}, false
}

// fgaFor returns the "fga" StatRow for the given team in a game and whether it
// was found. NOTE: at this validation-aggregate layer the "fga" stat is ALREADY
// total field-goal attempts (2pt + 3pt summed), on BOTH the engine side
// (TeamStat.FGA = Game2GA + Game3GA) and the .sco side (TwoGA + ThreeGA) — see
// validate/aggregate.go:28-33. The raw .sco 53-byte slot stores 2pt-only
// (memory reference_sco_fgm_is_2pt), but that re-summing happens one layer down;
// here "fga" is total FGA. Do NOT add the "tga" row on top — that double-counts
// threes. The harness emits exactly one "fga" row per team (compareGame).
func fgaFor(g validate.GameReport, teamID int) (validate.StatRow, bool) {
	for _, r := range g.Rows {
		if r.Stat == "fga" && r.TeamID == teamID {
			return r, true
		}
	}
	return validate.StatRow{}, false
}

// ftaFor returns the "fta" StatRow for the given team in a game and whether it
// was found — the free-throw-attempt accessor mirroring fgaFor. "fta" is emitted
// on BOTH the engine side and the .sco side (validate.statNames; aggregate.go),
// so the team-to-team FTA-rate dispersion is directly comparable — the
// independent corpus-calibration target for foulCompress (the foul-bucket
// compression knob, see sim/teamquality.go). The harness emits exactly one "fta"
// row per team (compareGame).
func ftaFor(g validate.GameReport, teamID int) (validate.StatRow, bool) {
	for _, r := range g.Rows {
		if r.Stat == "fta" && r.TeamID == teamID {
			return r, true
		}
	}
	return validate.StatRow{}, false
}
