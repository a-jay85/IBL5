package sim

import "github.com/a-jay85/IBL5/engine/internal/result"

// JSB 5.60 shooting fouls on 3-point attempts (J27, a-jay85/IBL5-backlog#1838).
// FUN_004d8570 in jsb560_decompiled.c: made-3 four-point roll 94054-94060
// (_DAT_00669e80), missed unblocked 3 roll 94102-94150 (local_110 at 94148,
// cleared at function entry 93309).
// Write-up: engine/docs/j27-foul-volume-2-port.md.

// threePtFoulCut is _DAT_00669e80: a 3-point attempt draws a shooting foul
// when the uniform draw is strictly below it.
const threePtFoulCut = 0.01

type threePtResult int

const (
	threePtMissed threePtResult = iota
	threePtMade
	threePtFouled // fouled missed 3: no FGA booked, 3 FTs shot
)

func threePtFoulFires(draw float64) bool { return draw < threePtFoulCut }

// threePtShot resolves one 3-point attempt with JSB's 1% shooting-foul rolls.
// It rolls the make itself so a fouled miss books no FGA (JSB writes no
// attempt stat on the 94147-94148 branch). On an unfouled miss the event order
// stays attempt, miss, then block, as shotAttempt plus creditBlock emitted it.
func (gs *gameState) threePtShot(offense, defense *teamState, shooter, defender onCourt, sv float64, origin result.ShotOrigin, periodIdx int) threePtResult {
	shot := result.Event{
		Kind: result.EventShotAttempt, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: result.ShotThree, Origin: origin,
	}
	if rollMake(sv, fatigueFactor(shooter.Stamina), gs.rng) {
		gs.emit(shot)
		gs.creditMadeFieldGoal(offense, shooter, result.ShotThree, origin, periodIdx)
		if threePtFoulFires(gs.rng.Float64()) { // 94054-94060: four-point play
			gs.resolveFoulShot(offense, defense, shooter, defender, 1, true, false, false, periodIdx)
		}
		return threePtMade
	}
	blocked := gs.rng.Float64() < blockProbability(defender, gs.madeFG[shooter.PID])
	if !blocked && threePtFoulFires(gs.rng.Float64()) { // 94102-94150: fouled missed 3
		gs.resolveFoulShot(offense, defense, shooter, defender, 3, false, true, false, periodIdx)
		return threePtFouled
	}
	gs.emit(shot)
	miss := shot
	miss.Kind = result.EventShotMiss
	gs.emit(miss)
	if blocked {
		gs.emit(result.Event{
			Kind: result.EventBlock, Period: gs.period, Clock: gs.clock,
			TeamID: offense.teamID, PlayerID: shooter.PID, DefenderID: defender.PID,
		})
	}
	return threePtMissed
}
