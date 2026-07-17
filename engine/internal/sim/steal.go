package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// Steal-driven turnover model (ADR-0045). JSB 5.60 turnovers are overwhelmingly
// steal-driven (00_MASTER_REFERENCE.md "Steal Probability"): the independent
// +0xDF8 roll is the [2,5] dc-minutes energy param so it fires only ~0.1%/poss
// (see outcome.go / energyCeiling). The dominant source is a per-possession steal
// roll weighted by OFFENSIVE carelessness (the ball-handler's TVR, oriented so a
// higher rating = better ball security = fewer turnovers) × DEFENSIVE steal
// pressure (the defenders' Σ STL, the JSB param×1.5 threshold side). A successful
// steal IS the turnover: the victim keeps the GameTOV and the credited defender
// gets the GameSTL.
const (
	// stealTurnoverScale converts carelessness × steal-pressure into a
	// per-possession turnover probability, calibrated so league TO/team ≈ 14.5
	// against the .sco archive (internal/calibrate/possession_archive_test.go). A
	// documented validation-phase stand-in in the same class as
	// foulBucketScale — the carelessness and pressure SHAPES are faithful (TVR
	// orientation + defensive STL); this scalar pins only the LEVEL.
	stealTurnoverScale = 2.75e-5

	// carelessnessBase orients the ball-handler's TVR rating (0-99, higher = better
	// ball security) into a carelessness weight: a max-rated handler is the least
	// careless. Floored at 0 for an over-max rating.
	carelessnessBase = 100.0

	// maxTurnoverProb caps the per-possession turnover probability strictly below 1
	// (a pathological careless×pressure product can never force a guaranteed
	// turnover, which would let a roll of exactly 0 deadlock the level calibration).
	maxTurnoverProb = 0.9
)

// turnoverCarelessness maps a ball-handler's TVR rating to offensive carelessness:
// higher TVR → lower carelessness → fewer turnovers (the orientation the pre-0045
// engine had backwards). Floored at 0 (a TVR over carelessnessBase is simply the
// least careless, never negative).
func turnoverCarelessness(tvr int) float64 {
	c := carelessnessBase - float64(tvr)
	if c < 0 {
		return 0
	}
	return c
}

// teamStealPressure is the defense's aggregate steal pressure: Σ over defenders of
// STL × fatigue (the JSB param×1.5 cap side). Higher-STL defenses force more
// turnovers — the defensive-quality coupling the ADR-0042 audit found missing.
// Zero when every defender is unrated (→ no steal-driven turnover and no
// divide-by-zero downstream). fatigue is 1.0 under the current curve.
func teamStealPressure(defense *teamState) float64 {
	var p float64
	for _, d := range defense.players {
		if w := float64(d.STL) * d.fatigue; w > 0 {
			p += w
		}
	}
	return p
}

// stealTurnover rolls the dominant, steal-driven turnover for one trip. On a steal
// it emits EventTurnover (the victim keeps GameTOV), runs the per-turnover injury
// check, credits a defender via selectStealer (EventSteal → the stealer's GameSTL),
// and returns true (turnover occurred; fast-break pending for the defense). The
// probability routes through gs.turnoverProb so the freeze lattice can collapse its
// cross-team variance (freeze.go, the TVR arm) for the ADR-0045 Cov re-run. The
// gs.rng.Float64() roll is drawn unconditionally so live and frozen passes consume
// the RNG identically.
func (gs *gameState) stealTurnover(offense, defense *teamState, victim onCourt) bool {
	prob := gs.turnoverProb(turnoverCarelessness(victim.TVR), teamStealPressure(defense))
	if gs.rng.Float64() >= prob {
		return false
	}
	gs.emit(result.Event{
		Kind: result.EventTurnover, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: victim.PID,
	})
	gs.maybeInjure(offense, victim) // per-turnover injury check on the committer
	stealer, _ := selectStealer(defense, gs.rng)
	gs.emit(result.Event{
		Kind: result.EventSteal, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: victim.PID, DefenderID: stealer.PID,
	})
	return true
}

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
