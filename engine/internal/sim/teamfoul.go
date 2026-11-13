package sim

// JSB 5.60 team-foul bonus (J27, a-jay85/IBL5-backlog#265). Decompile refs:
// FUN_004d8570 in jsb560_decompiled.c: shooting draw 93469-93480, team-foul
// increment 93515, FT guard 93647, per-period reset 94467-94468.
// Write-up: engine/docs/j27-team-foul-bonus-port.md.

// shootingFoulDrawCut is _DAT_00669ef0: a foul is a shooting foul when the
// uniform draw is strictly above it.
const shootingFoulDrawCut = 0.5

// teamFoulBonusThreshold: a non-shooting foul earns free throws once the
// fouling team's period count, incremented BEFORE the test, exceeds it. The
// 5th team foul of a period is therefore the first bonus foul.
const teamFoulBonusThreshold = 4

// isShootingFoul ports the 93469-93480 draw. forced is the 5.60 late-game
// flag (local_15e); it clears shooting after the draw has been consumed.
func isShootingFoul(draw float64, andOne, threePt, forced bool) bool {
	shooting := draw > shootingFoulDrawCut || andOne || threePt
	if forced { // 5.60 forced flag (local_15e) overrides to non-shooting
		shooting = false
	}
	return shooting
}

// foulAwardsFreeThrows ports the 93647 guard. No one-and-one, no double bonus.
func foulAwardsFreeThrows(shooting bool, teamFouls int, andOne, threePt bool) bool {
	return shooting || teamFouls > teamFoulBonusThreshold || andOne || threePt
}

// resolveFoul charges one defensive foul on a trip with no fouled 3-point
// attempt. Every existing caller keeps this signature; see resolveFoulShot.
func (gs *gameState) resolveFoul(offense, defense *teamState, shooter, defender onCourt, n int, andOne, forced bool, periodIdx int) bool {
	return gs.resolveFoulShot(offense, defense, shooter, defender, n, andOne, false, forced, periodIdx)
}

// resolveFoulShot charges one defensive foul under the team-foul bonus. It
// returns true when free throws were shot (possession then changes as before)
// and false for a below-bonus non-shooting foul: PF charged, no FTs, and the
// caller keeps the ball with the offense. threePt is JSB's local_110 (set at
// 94148 for a fouled missed 3): it forces shooting at 93469-93480 and clears
// the 93647 guard, so a fouled 3 always shoots.
func (gs *gameState) resolveFoulShot(offense, defense *teamState, shooter, defender onCourt, n int, andOne, threePt, forced bool, periodIdx int) bool {
	draw := gs.rng.Float64() // consumed on every foul, forced or not
	shooting := isShootingFoul(draw, andOne, threePt, forced)
	defense.teamFouls++
	if foulAwardsFreeThrows(shooting, defense.teamFouls, andOne, threePt) {
		gs.freeThrows(offense, defense, shooter, defender, n, periodIdx)
		return true
	}
	gs.chargeFoul(defense, defender) // below the bonus: PF only, no FTs
	return false
}
