package calibrate

import (
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
	EngineHomeWinShare float64 `json:"engine_home_win_share"` // fraction of games with engine home margin > 0
	ScoHomeWinShare    float64 `json:"sco_home_win_share"`    // fraction of games with .sco home margin > 0
	WinShareGap        float64 `json:"win_share_gap"`         // EngineHomeWinShare − ScoHomeWinShare
}

// homeMarginAcc accumulates one game type's running sums while CollectHomeMargins
// walks the reports.
type homeMarginAcc struct {
	n          int
	sumEngine  float64
	sumSco     float64
	engineWins int
	scoWins    int
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
			acc.n++
			acc.sumEngine += homePts.EngineMean - visPts.EngineMean
			acc.sumSco += homePts.ScoVal - visPts.ScoVal
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
			EngineHomeWinShare: engWin,
			ScoHomeWinShare:    scoWin,
			WinShareGap:        engWin - scoWin,
		})
	}
	return out
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
