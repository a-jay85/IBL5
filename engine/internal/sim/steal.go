package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// The live JSB 5.60 code-4 turnover path (live trace
// ~/claude-plans/_reports/2026-11-16-jsb-tov-trace.md). A code-4 pick is always a
// turnover. One draw decides whether it is an offensive foul. The rest go
// through the steal split ported from FUN_004e2fc0 (00_MASTER_REFERENCE.md
// "Steal Probability"), which splits turnovers into steals and plain turnovers
// and never generates one.

// offensiveFoulOnTurnover is the share of code-4 turnovers that become offensive fouls.
const offensiveFoulOnTurnover = 0.1 // const 0.1 at 0x4da7a0, live trace 2026-11-16

// stealPool is the defense's steal pool from FUN_004e2fc0: Σ over defenders of
// stlRate (+0xDD0, STL/MIN×44) × fatigue, capped at
// defQualityCapMultiplier·defQualityCapTeamMult·leagueSTL48 (the def_matchup_quality
// ceiling, 0x4e3222).
func stealPool(defense *teamState) float64 {
	var total float64
	for _, d := range defense.players {
		total += stlRate(d) * d.fatigue
	}
	ceiling := defQualityCapMultiplier * defQualityCapTeamMult * leagueSTL48
	if total > ceiling {
		return ceiling
	}
	return total
}

// turnoverPool is the offense's turnover pool from FUN_004e2fc0: Σ over the
// offense of (2.0 − fatigue) × tovRate (TOV/MIN×48). It is the steal roll's range.
func turnoverPool(offense *teamState) float64 {
	var total float64
	for _, p := range offense.players {
		total += (foulBaseFatigueRef - p.fatigue) * tovRate(p)
	}
	return total
}

// resolveTurnoverPath runs the code-4 turnover path for ball handler bh and
// reports whether it ended in a steal (which arms the defense's fast break).
// Draw order: the offensive-foul draw, then the steal roll, then the stealer
// pick on a steal. An offensive foul charges bh a turnover and a personal foul
// tagged FoulSiteOffensiveTurnover (feeding foul-out like any other foul), counts
// toward the offense's team fouls, and shoots no free throws. JSB picks the
// stealer from the same steal roll; this port picks it with selectStealer.
func (gs *gameState) resolveTurnoverPath(offense, defense *teamState, bh onCourt, code outcomeCode) bool {
	gs.emit(result.Event{
		Kind: result.EventTurnover, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: bh.PID,
	})
	offensiveFoul := gs.rng.Float64() < offensiveFoulOnTurnover
	gs.maybeInjure(offense, bh) // per-turnover injury check on the committer
	if offensiveFoul {
		offense.teamFouls++
		gs.chargeFoul(offense, bh, result.FoulSiteOffensiveTurnover)
		observePathEnd(code, pathEndOffensiveFoul)
		return false
	}
	roll := gs.rng.Float64() * turnoverPool(offense)
	if roll >= stealPool(defense) {
		observePathEnd(code, pathEndTurnover)
		return false
	}
	stealer, _ := selectStealer(defense, gs.rng)
	gs.emit(result.Event{
		Kind: result.EventSteal, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: bh.PID, DefenderID: stealer.PID,
	})
	observePathEnd(code, pathEndSteal)
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
