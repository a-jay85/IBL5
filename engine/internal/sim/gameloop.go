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
// derived possession length. It returns the full event stream and box scores
// (visitor team first), the count of fast-break possessions that fired, and the
// two live teamStates — the latter two are internal observability for tests (the
// live quarter tally lets the conservation test cross-check the event-derived
// box), not part of the result contract.
func simGame(b bundle.Bundle, g bundle.Game, r *rng.RNG) (result.GameResult, int, *teamState, *teamState) {
	visitor := newTeamState(b.Players, g.VisitorTeamID, false)
	home := newTeamState(b.Players, g.HomeTeamID, true)

	gs := &gameState{rng: r, madeFG: map[int]int{}}

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
		gs.transitionShotRate = 0 // Stage-3 decay resets per period ("within a period")
		pending := false
		for gs.clock > 0 {
			// Dead-ball substitution sweep for both teams before the possession
			// (foul-out / foul-trouble / fatigue). Zero RNG — see checkSubstitutions.
			checkSubstitutions(offense, period, gs.clock, gs.emit)
			checkSubstitutions(defense, period, gs.clock, gs.emit)

			pending = possession(gs, offense, defense, period-1, pending)

			// Both fives were on the floor: drain on-court energy + accrue minutes,
			// recover the benches.
			offense.drainAndRecover(step)
			defense.drainAndRecover(step)

			offense, defense = defense, offense
			gs.clock -= step
		}
		gs.emit(result.Event{Kind: result.EventPeriodBoundary, Period: period, Clock: 0})
	}

	for period := 1; period <= regulationPeriods; period++ {
		if period == 3 { // halftime: full energy restore for both teams
			visitor.restoreFull()
			home.restoreFull()
		}
		playPeriod(period, quarterSeconds)
	}
	for ot := 1; ot <= maxOvertime && visitor.score == home.score; ot++ {
		playPeriod(regulationPeriods+ot, otSeconds)
	}

	visitor.finalizeMinutes()
	home.finalizeMinutes()

	// The box score is derived purely from the event stream, joined with each
	// team's roster metadata (PID/Pos set at construction, GameMIN by
	// finalizeMinutes). The live teamState.boxes carry only that metadata now.
	playerBoxes, teamBoxes := aggregateBoxes(gs.events, rosterMetaOf(visitor), rosterMetaOf(home))

	gr := result.GameResult{
		Date:          g.Date,
		HomeTeamID:    g.HomeTeamID,
		VisitorTeamID: g.VisitorTeamID,
		GameOfThatDay: 1,
		SimGameType:   int(g.GameType),
		Events:        gs.events,
		PlayerBoxes:   playerBoxes,
		TeamBoxes:     teamBoxes,
	}
	return gr, gs.transitions, visitor, home
}

// rosterMetaOf snapshots a team's roster metadata (identity, position, finalized
// minutes) in bundle order for the box aggregator.
func rosterMetaOf(t *teamState) rosterMeta {
	rm := rosterMeta{teamID: t.teamID, isHome: t.isHome, players: make([]playerMeta, 0, len(t.boxes))}
	for _, b := range t.boxes {
		rm.players = append(rm.players, playerMeta{PID: b.PID, Pos: b.Pos, GameMIN: b.GameMIN})
	}
	return rm
}
