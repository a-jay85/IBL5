package sim

// recoveryMultiple is how fast a benched player recovers energy relative to the
// rate an on-court player drains it. JSB's drain is linear seconds-on-court; the
// off-court recovery runs at 3× so a rotation player who sits a few possessions
// returns fresher than when they left (00_MASTER_REFERENCE.md energy model).
const recoveryMultiple = 3

// refreshOnCourt syncs an on-court entry's live energy/fatigue from the team's
// energy map. fatigue = fatigueFactor(energy); under the committed curve this is
// 1.0 for any energy ≥ 0 and clamps to 1.0 for negative energy too, so it is
// behaviorally inert today but correct the moment the curve is repaired.
func (t *teamState) refreshOnCourt(oc *onCourt) {
	e := int(t.energy[oc.PID])
	oc.energy = e
	oc.fatigue = fatigueFactor(e)
}

// drainAndRecover applies one possession's worth of energy change and minutes.
// Every on-court player loses `step` energy (unfloored — energy may go negative)
// and accrues `step` seconds of playing time; every other eligible (bench)
// player recovers `step × recoveryMultiple` energy, capped at their Stamina
// ceiling. On-court entries' live energy/fatigue are then refreshed.
//
// Both teams' on-court fives are on the floor for every possession, so simGame
// calls this for offense and defense each trip.
func (t *teamState) drainAndRecover(step int) {
	onCourt := make(map[int]bool, len(t.players))
	for i := range t.players {
		pid := t.players[i].PID
		onCourt[pid] = true
		t.energy[pid] -= float64(step)
		t.minutes[pid] += float64(step)
	}
	for pid := range t.energy {
		if onCourt[pid] {
			continue
		}
		e := t.energy[pid] + float64(step*recoveryMultiple)
		if ceiling := float64(t.playerByPID[pid].Stamina); e > ceiling {
			e = ceiling
		}
		t.energy[pid] = e
	}
	for i := range t.players {
		t.refreshOnCourt(&t.players[i])
	}
}

// restoreFull resets every eligible player to their Stamina ceiling regardless
// of on/off court — the halftime full-energy restore, applied at the start of
// period 3.
func (t *teamState) restoreFull() {
	for pid := range t.energy {
		t.energy[pid] = float64(t.playerByPID[pid].Stamina)
	}
	for i := range t.players {
		t.refreshOnCourt(&t.players[i])
	}
}
