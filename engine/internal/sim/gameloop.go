package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

const (
	quarterSeconds    = 720 // 12:00 regulation quarter
	otSeconds         = 300 // 5:00 overtime period
	regulationPeriods = 4
	maxOvertime       = 20 // hard ceiling so a tied game always terminates
)

// simGame plays one scheduled game: four regulation quarters plus overtime
// while tied, alternating possessions and decrementing the clock by a tempo-
// derived possession length. It returns the full event stream and box scores,
// visitor team first.
func simGame(b bundle.Bundle, g bundle.Game, r *rng.RNG) result.GameResult {
	visitor := newTeamState(b.Players, g.VisitorTeamID, false)
	home := newTeamState(b.Players, g.HomeTeamID, true)

	gs := &gameState{rng: r}

	// Possession length is constant per game in PR3a (factor 1.0, no per-game
	// stat aggregates yet): average the two teams' base times.
	baseTime := (teamBaseTime(visitor.players) + teamBaseTime(home.players)) / 2.0
	step := possessionTime(baseTime)

	// Tip-off winner starts on offense; possessions strictly alternate.
	offense, defense := visitor, home
	if r.IntN(2) == 1 {
		offense, defense = home, visitor
	}

	playPeriod := func(period, seconds int) {
		gs.period = period
		gs.clock = seconds
		for gs.clock > 0 {
			possession(gs, offense, defense, period-1)
			offense, defense = defense, offense
			gs.clock -= step
		}
		gs.emit(result.Event{Kind: result.EventPeriodBoundary, Period: period, Clock: 0})
	}

	for period := 1; period <= regulationPeriods; period++ {
		playPeriod(period, quarterSeconds)
	}
	for ot := 1; ot <= maxOvertime && visitor.score == home.score; ot++ {
		playPeriod(regulationPeriods+ot, otSeconds)
	}

	gr := result.GameResult{
		Date:          g.Date,
		HomeTeamID:    g.HomeTeamID,
		VisitorTeamID: g.VisitorTeamID,
		GameOfThatDay: 1,
		SimGameType:   int(g.GameType),
		Events:        gs.events,
		PlayerBoxes:   append(visitor.playerBoxes(), home.playerBoxes()...),
		TeamBoxes:     []result.TeamBox{visitor.teamBox(), home.teamBox()},
	}
	return gr
}
