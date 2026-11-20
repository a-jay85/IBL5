package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// The live JSB 5.60 code-4 turnover path (live trace
// ~/claude-plans/_reports/2026-11-16-jsb-tov-trace.md). A code-4 pick is always a
// turnover. One draw decides whether it is an offensive foul. The rest go
// through the steal split ported from FUN_004e2fc0 (00_MASTER_REFERENCE.md
// "Steal Probability"), which splits turnovers into steals and plain turnovers
// and never generates one.

// offensiveFoulOnTurnover is the share of code-4 turnovers that become offensive fouls.
const offensiveFoulOnTurnover = 0.1 // const 0.1 at 0x4da7a0, live trace 2026-11-16

// stealCapLeagueSTL48 is the league STL/48 the steal cap reads at runtime,
// 2.043 in the live trace of the steal roll (row league STL/48, see
// engine/docs/j27-live-outcome-map-port.md Residuals). JSB keeps one league
// STL/48 value; the foul-bucket baseline still reads leagueSTL48 (1.834)
// until that file's owner moves it, so the two stay separate here.
const stealCapLeagueSTL48 = 2.043

// stealTermSum is the uncapped steal total from FUN_004e2fc0: Σ over defenders of
// stlRate (+0xDD0, STL/MIN×44) × fatigue.
func stealTermSum(defense *teamState) float64 {
	var total float64
	for _, d := range defense.players {
		total += stlRate(d) * d.fatigue
	}
	return total
}

// stealPool is the defense's steal pool from FUN_004e2fc0: stealTermSum capped at
// defQualityCapMultiplier·defQualityCapTeamMult·stealCapLeagueSTL48 (the
// def_matchup_quality ceiling, 0x4e3222).
func stealPool(defense *teamState) float64 {
	total := stealTermSum(defense)
	ceiling := defQualityCapMultiplier * defQualityCapTeamMult * stealCapLeagueSTL48
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
// Draw order: the offensive-foul draw, then the steal roll. On a steal the
// stealer pick reuses the steal roll and draws nothing. An offensive foul charges bh a turnover and a personal foul
// tagged FoulSiteOffensiveTurnover (feeding foul-out like any other foul), counts
// toward the offense's team fouls, and shoots no free throws. JSB picks the
// stealer from the same steal roll, and so does this port (pickStealerFromRoll).
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
	stealer, _ := pickStealerFromRoll(defense, roll)
	gs.emit(result.Event{
		Kind: result.EventSteal, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: bh.PID, DefenderID: stealer.PID,
	})
	observePathEnd(code, pathEndSteal)
	return true
}

// pickStealerFromRoll credits the steal from the steal roll itself, the way
// FUN_004e2fc0 does: defenders own consecutive half-open bins (prev, cum] of
// width stlRate × fatigue in lineup order, and the bin holding roll wins. When
// the uncapped total passes the cap, JSB scales every bin edge by
// stealPool/total, so the bins split the capped pool in proportion to each
// defender's term. A zero-width bin never matches. No bin matching (roll beyond
// the bins, or roll == 0 on a zero first bin) returns the last defender with
// false; an empty defense returns the zero onCourt with false. It draws no RNG.
func pickStealerFromRoll(defense *teamState, roll float64) (onCourt, bool) {
	if len(defense.players) == 0 {
		return onCourt{}, false
	}
	scale := 1.0
	if total, pool := stealTermSum(defense), stealPool(defense); total > pool {
		scale = pool / total
	}
	var raw, prev float64
	for _, d := range defense.players {
		raw += stlRate(d) * d.fatigue
		cum := raw * scale
		if roll > prev && roll <= cum {
			return d, true
		}
		prev = cum
	}
	return defense.players[len(defense.players)-1], false
}
