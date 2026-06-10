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

// OriginFGA is one team's engine mean field-goal attempts per game split by shot
// ORIGIN (ADR-0042 empty-FGA-split diagnostic). Engine-only: real .sco box
// scores carry no origin tag, so this has no .sco counterpart and is never
// compared, banded, or part of the Pass verdict — it is reported, never gated.
//
// The *Made companion fields (ADR-0053) carry the engine mean MADE field goals
// per game by the SAME origin, so a per-origin shooting efficiency (made/attempts)
// is directly observable — this pinpoints WHY the putback origin anti-couples
// (the empty/miss-driven FGA loop). They are engine-only, additive, and NOT
// printed by WriteReport: counting EventShotMake (already emitted by Simulate)
// changes no engine behavior, so the golden fixture stays byte-identical. Do NOT
// conflate this per-origin efficiency readout with the season-aggregate
// EngineCovLnShotsPerPossLnPPS factor (standings.go) — they are distinct.
type OriginFGA struct {
	Initial    float64
	Oreb       float64
	Transition float64
	// Made-FG counts by the same origin (made/attempts = per-origin efficiency).
	InitialMade    float64
	OrebMade       float64
	TransitionMade float64
}

// GameReport is the full comparison for one corpus game: every stat for both
// teams. Pass is true only when every row passes.
type GameReport struct {
	VisitorTeamID int
	HomeTeamID    int
	Date          string
	Pass          bool
	Rows          []StatRow
	// EngineOriginFGA maps each team ID to its engine mean by-origin FGA/game.
	// Engine-only (see OriginFGA); additive, not printed by WriteReport, not part
	// of Pass — feeds the season-aggregate by-origin variance decomposition.
	EngineOriginFGA map[int]OriginFGA
	// EnginePossPerG / ScoPossPerG map each team ID to its mean possessions/game for
	// the ADR-0049 possession-count decomposition — the SAME Dean-Oliver box proxy
	// FGA + 0.44·FTA + TOV − ORB on BOTH sides (ORB IS present on the raw box; only
	// the compared StatRow set collapses to total REB). Using one definition on both
	// sides keeps the cross-side Cov split apples-to-apples: an FGA-derived proxy
	// correlates with FGA by construction, so mixing a true count against it on the
	// other side would bias which factor (count vs shots-per-possession) absorbs the
	// coupling. Matches calibrate/possession_archive_test.go's convention.
	EnginePossPerG map[int]float64
	ScoPossPerG    map[int]float64
	// EnginePossCountPerG is the engine's AUTHORITATIVE possession count (mean
	// EventPossessionStart/game; one per offensive trip — an offensive rebound
	// continues the SAME trip, so it is true possessions). Engine-only DIAGNOSTIC: it
	// validates the box proxy (count ≈ proxy at the level) and the count-vs-proxy gap
	// exposes the shots-per-possession spread the proxy folds away. It is NOT used in
	// the cross-side Cov split (that would re-introduce the count-vs-proxy bias).
	// Read-only — counting an event Simulate already emits changes no engine behavior.
	EnginePossCountPerG map[int]float64
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
