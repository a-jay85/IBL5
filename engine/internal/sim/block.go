package sim

import "github.com/a-jay85/IBL5/engine/internal/result"

const (
	// blockFraction is the pre-modifier ceiling for P(block): a fully-rated blocker
	// against a cold shooter starts here, before the front-contest reduction and
	// the per-made-FG penalty bring it down (so the attainable peak is slightly
	// under this value). It also reads as the rough share of 2pt/3pt misses that
	// are blocked. Documented validation-phase stand-in.
	blockFraction = 0.06

	// blockRatingScale maps a BLK rating to the [0, blockFraction] base: a rating
	// at or above this value contests at the ceiling. Documented stand-in (the
	// real per-game block-rate double does not exist before validation).
	blockRatingScale = 30.0

	// blockDirectionMod is the front-contest factor: base − base×0.04
	// (00_MASTER_REFERENCE.md L1396). The behind-contest ×0.04 easy path is
	// unmodeled in PR3b — there is no shot-direction data yet, so every contest is
	// treated as the harder front contest. Documented.
	blockDirectionMod = 0.04

	// blockMadeFGPenalty is the per-made-FG reduction: shooters who have already
	// made shots this game are harder to block (L1397), floored so P(block) ≥ 0.
	blockMadeFGPenalty = 0.001
)

// blockProbability returns P(this defender blocks this missed shot), in
// [0, blockFraction]. base = blockFraction × min(1, BLK/scale) × fatigue, reduced
// by the front-contest factor and by blockMadeFGPenalty per field goal the
// shooter has already made (shooterMadeFG = Game2GM + Game3GM so far), then
// floored at 0 so it can never go negative.
func blockProbability(defender onCourt, shooterMadeFG int) float64 {
	ratio := float64(defender.BLK) / blockRatingScale
	if ratio > 1 {
		ratio = 1
	}
	if ratio < 0 {
		ratio = 0
	}
	base := blockFraction * ratio * defender.fatigue
	base -= base * blockDirectionMod // front contest (harder)
	base -= blockMadeFGPenalty * float64(shooterMadeFG)
	if base < 0 {
		base = 0
	}
	return base
}

// creditBlock rolls for a block on a missed field goal. On a block it credits
// GameBLK to the contesting DEFENDER and emits EventBlock (TeamID = offense,
// PlayerID = shooter, DefenderID = blocker). It never changes make/miss and
// never touches the rebound path — the miss still flows to the rebound phase
// unchanged. The caller must only invoke this on a 2pt/3pt miss (never a free
// throw).
func (gs *gameState) creditBlock(offense, defense *teamState, shooter, defender onCourt) {
	sb := offense.box(shooter.PID)
	madeFG := sb.Game2GM + sb.Game3GM
	if gs.rng.Float64() < blockProbability(defender, madeFG) {
		defense.box(defender.PID).GameBLK++
		gs.emit(result.Event{
			Kind: result.EventBlock, Period: gs.period, Clock: gs.clock,
			TeamID: offense.teamID, PlayerID: shooter.PID, DefenderID: defender.PID,
		})
	}
}
