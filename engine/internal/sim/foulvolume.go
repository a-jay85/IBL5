package sim

// JSB 5.60 foul-volume sites (J27, a-jay85/IBL5-backlog#265). Decompile refs:
// FUN_004d8570 in jsb560_decompiled.c: forced-foul gate 92991-93009, forced
// foul dispatch 93458 (LAB_004d9d4d), forced non-shooting clear 93478.
// Write-up: engine/docs/j27-foul-volume-port.md.

const (
	// forcedFoulMinPeriod is the 92995 period guard: FUN_004e4440() (the period
	// getter, +0x4c20) must not be below 4, so Q4 and every overtime qualify.
	forcedFoulMinPeriod = 4

	// forcedFoulClockLimit is the 92992 clock guard: *(param_1+0x4c24) < 0x19,
	// so the gate opens with under 25 seconds left in the period.
	forcedFoulClockLimit = 0x19

	// forcedFoulMinLead and forcedFoulMaxLead are the 92996-92997 margin bounds.
	// uVar20 is team1 score minus team2 score and +0x33e4 is the team with the
	// ball (J17 objdump, 0x4d87c6-0x4d87f1), so the gate opens when the offense
	// leads by 1 to 3. The trailing defense then fouls on purpose.
	forcedFoulMinLead = 1
	forcedFoulMaxLead = 3
)

// forcedFoulFires reports whether the late-game forced-foul gate opens for a
// half-court trip (FUN_004d8570 92991-93003). offenseLead is the offense score
// minus the defense score. When the gate opens, JSB still rolls the ball
// handler's +0x1d8 rating against rand_int(10) (93005-93009) before it sets
// local_15e. lateGameForcing owns that roll.
func forcedFoulFires(period, clock, offenseLead int) bool {
	return period >= forcedFoulMinPeriod &&
		clock < forcedFoulClockLimit &&
		offenseLead >= forcedFoulMinLead &&
		offenseLead <= forcedFoulMaxLead
}
