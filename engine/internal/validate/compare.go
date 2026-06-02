package validate

import (
	"fmt"
	"math"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// StatRow is the comparison of one stat for one team in one game.
type StatRow struct {
	TeamID     int
	Stat       string
	ScoVal     float64 // the single .sco ground-truth observation
	EngineMean float64 // observed engine mean across the N seeded runs
	Tolerance  float64 // the band actually applied = max(absFloor, relPct×mean)
	Pass       bool
	Detail     string
}

// GameReport is the full comparison for one corpus game: every stat for both
// teams. Pass is true only when every row passes.
type GameReport struct {
	VisitorTeamID int
	HomeTeamID    int
	Date          string
	Pass          bool
	Rows          []StatRow
	// EngineHomeWinFraction is the fraction of seeded runs in which the home team
	// outscored the visitor (a tied run counts 0.5). It is a runs-stable estimate
	// of P(home win) — unlike a single mean-margin sign, which rounds every game
	// to a 0/1 win and inflates favorites' records as √N (see memory
	// reference_jsb_winshare_runs_artifact). Consumed by the season-aggregate
	// collector; it is not part of the Pass verdict and is not printed by
	// WriteReport (keeping the text report byte-identical).
	EngineHomeWinFraction float64
}

// compareStat decides whether a single .sco value is within tolerance of the
// observed engine mean. The band is max(absFloor, relPct×mean): the relative
// term dominates for large means, the floor for small ones. The edge is
// INCLUSIVE — a value exactly at the band boundary passes.
func compareStat(name string, scoVal, engineMean float64, b Band) (pass bool, detail string) {
	tol := b.AbsFloor
	if rel := b.RelPct * math.Abs(engineMean); rel > tol {
		tol = rel
	}
	diff := math.Abs(scoVal - engineMean)
	pass = diff <= tol
	verdict := "PASS"
	if !pass {
		verdict = "FAIL"
	}
	detail = fmt.Sprintf("%-6s %s sco=%.0f mean=%.2f |diff|=%.2f tol=%.2f",
		name, verdict, scoVal, engineMean, diff, tol)
	return pass, detail
}

// toleranceFor recomputes the applied band for reporting (mirrors compareStat).
func toleranceFor(b Band, engineMean float64) float64 {
	tol := b.AbsFloor
	if rel := b.RelPct * math.Abs(engineMean); rel > tol {
		tol = rel
	}
	return tol
}

// compareGame compares one .sco game's per-team ground truth against the
// observed engine means for the same matchup. visMean/homeMean are keyed by
// statNames (from mean()); visSco/homeSco are the .sco-derived TeamStats. Rows
// are emitted in a fixed order — visitor stats then home stats, each in
// statNames order — so the report is deterministic. gameType selects the band
// table, since regular/playoff/all-star tolerances differ.
func compareGame(gameType bundle.GameType, visitorTeamID, homeTeamID int, date string, visSco, homeSco TeamStat, visMean, homeMean map[string]float64) GameReport {
	gr := GameReport{
		VisitorTeamID: visitorTeamID,
		HomeTeamID:    homeTeamID,
		Date:          date,
		Pass:          true,
	}
	for _, side := range []struct {
		teamID int
		sco    TeamStat
		means  map[string]float64
	}{
		{visitorTeamID, visSco, visMean},
		{homeTeamID, homeSco, homeMean},
	} {
		for _, name := range statNames {
			b := bandFor(gameType, name)
			scoVal := float64(side.sco.value(name))
			engineMean := side.means[name]
			pass, detail := compareStat(name, scoVal, engineMean, b)
			gr.Rows = append(gr.Rows, StatRow{
				TeamID:     side.teamID,
				Stat:       name,
				ScoVal:     scoVal,
				EngineMean: engineMean,
				Tolerance:  toleranceFor(b, engineMean),
				Pass:       pass,
				Detail:     detail,
			})
			if !pass {
				gr.Pass = false
			}
		}
	}
	return gr
}
