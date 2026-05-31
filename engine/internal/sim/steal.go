package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// stealFraction is the share of turnovers that are forced steals (the rest are
// unforced: the victim keeps the GameTOV and no defender is credited a steal).
// 00_MASTER_REFERENCE.md L1385-1390 selects the victim by offensive carelessness
// and only "if under, a steal occurs"; PR3b has the victim already (the ball
// handler who turned it over), so this fraction stands in for the steal-vs-
// unforced split until the per-game turnover/steal aggregates exist (validation
// phase). Documented stand-in.
const stealFraction = 0.55

// selectStealer picks which defender is credited a steal, by weighted random
// over STL_rating × fatigue (a careless turnover is taken by an active, ball-
// hawking defender). The second return is false when every defender has a zero
// weight (an all-unrated roster): the caller still gets players[0] but the pick
// was a fallback, never a divide-by-zero. Mirrors selectDefender/selectRebounder.
func selectStealer(defense *teamState, r *rng.RNG) (onCourt, bool) {
	if len(defense.players) == 0 {
		return onCourt{}, false
	}
	weights := make([]float64, len(defense.players))
	var sum float64
	for i, p := range defense.players {
		w := float64(p.STL) * p.fatigue
		if w < 0 {
			w = 0
		}
		weights[i] = w
		sum += w
	}
	if sum <= 0 {
		return defense.players[0], false
	}
	roll := r.Float64() * sum
	var acc float64
	for i, w := range weights {
		acc += w
		if roll <= acc {
			return defense.players[i], true
		}
	}
	return defense.players[len(defense.players)-1], true
}

// creditSteal resolves whether a turnover (already credited to the victim) was a
// forced steal. On a steal it credits GameSTL to the stealing DEFENDER and emits
// EventSteal (TeamID = offense, PlayerID = victim, DefenderID = stealer), then
// returns true so the caller sets the fast-break pending flag. On an unforced
// turnover it returns false and credits no stealer. It never alters the victim's
// GameTOV (the caller credited it).
func (gs *gameState) creditSteal(offense, defense *teamState, victim onCourt) bool {
	if gs.rng.Float64() >= stealFraction {
		return false // unforced turnover: no stealer
	}
	stealer, _ := selectStealer(defense, gs.rng)
	defense.box(stealer.PID).GameSTL++
	gs.emit(result.Event{
		Kind: result.EventSteal, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: victim.PID, DefenderID: stealer.PID,
	})
	return true
}
