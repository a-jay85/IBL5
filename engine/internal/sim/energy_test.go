package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// energyTeam builds a 5-starter team plus one PG backup (depth 2), all Stamina
// 50, for white-box energy tests.
func energyTeam() *teamState {
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 2, TeamID: 7, DCSGDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 3, TeamID: 7, DCSFDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 4, TeamID: 7, DCPFDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 5, TeamID: 7, DCCDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 6, TeamID: 7, DCPGDepth: 2, DCMinutes: 10, Stamina: 50, DCCanPlayInGame: 1}, // bench PG
	}
	return newTeamState(players, 7, true)
}

// --- matrix #3: drain decreases on-court energy by step, accrues minutes ----

func TestDrainAndRecover_OnCourtDrainsAndAccruesMinutes(t *testing.T) {
	tm := energyTeam()
	const step = 15
	tm.drainAndRecover(step)

	// PID 1 is the starting PG (on court): energy 50 → 35, minutes 0 → 15.
	if got := tm.energy[1]; got != 35 {
		t.Errorf("on-court energy = %v, want 35", got)
	}
	if got := tm.minutes[1]; got != 15 {
		t.Errorf("on-court minutes = %v, want 15", got)
	}
	// The on-court entry's live fields are refreshed from the map.
	if tm.players[0].energy != 35 {
		t.Errorf("on-court entry energy = %d, want 35", tm.players[0].energy)
	}
	// Bench PID 6 never accrues minutes.
	if got := tm.minutes[6]; got != 0 {
		t.Errorf("bench minutes = %v, want 0", got)
	}
}

// --- matrix #4: energy goes negative without panic; fatigue clamps ----------

func TestDrainAndRecover_GoesNegativeNoPanic(t *testing.T) {
	tm := energyTeam()
	for i := 0; i < 10; i++ { // 10 × 15 = 150 drained from 50
		tm.drainAndRecover(15)
	}
	if tm.energy[1] >= 0 {
		t.Fatalf("energy = %v, want negative after sustained drain", tm.energy[1])
	}
	// fatigueFactor of negative energy clamps to the energy-0 curve value (1.0).
	if got := fatigueFactor(int(tm.energy[1])); got != 1.0 {
		t.Errorf("fatigueFactor(negative) = %v, want 1.0 (clamped)", got)
	}
	if tm.players[0].fatigue != 1.0 {
		t.Errorf("on-court fatigue = %v, want 1.0", tm.players[0].fatigue)
	}
}

// --- matrix #5: bench recovers by step*recoveryMultiple, capped at Stamina ---

func TestDrainAndRecover_BenchRecoversCapped(t *testing.T) {
	tm := energyTeam()
	tm.energy[6] = 0 // drop the bench PG below its ceiling

	tm.drainAndRecover(15) // +15*3 = 45
	if got := tm.energy[6]; got != 45 {
		t.Errorf("bench energy = %v, want 45", got)
	}
	tm.drainAndRecover(15) // 45+45 = 90, capped at Stamina 50
	if got := tm.energy[6]; got != 50 {
		t.Errorf("bench energy = %v, want 50 (capped at Stamina)", got)
	}
}

// --- matrix #6: benched-then-recovered player returns fresher than benched ---

func TestDrainAndRecover_RecoveryOnReturn(t *testing.T) {
	tm := energyTeam()
	for i := 0; i < 10; i++ {
		tm.drainAndRecover(15) // starting PG drained well negative
	}
	benched := tm.energy[1]
	if benched >= 0 {
		t.Fatalf("setup: PID 1 energy = %v, want negative", benched)
	}
	tm.players = tm.players[1:] // bench PID 1 (drop the PG entry)
	for i := 0; i < 5; i++ {
		tm.drainAndRecover(15) // PID 1 now recovers each possession
	}
	if !(tm.energy[1] > benched) {
		t.Errorf("returned energy %v not strictly greater than benched %v", tm.energy[1], benched)
	}
}

// --- matrix #7: restoreFull sets every PID to its Stamina ceiling -----------

func TestRestoreFull_AllToStaminaCeiling(t *testing.T) {
	tm := energyTeam()
	for i := 0; i < 10; i++ {
		tm.drainAndRecover(15)
	}
	tm.restoreFull()
	for pid, e := range tm.energy {
		if e != 50 {
			t.Errorf("PID %d energy = %v after restoreFull, want 50", pid, e)
		}
	}
	if tm.players[0].energy != 50 || tm.players[0].fatigue != 1.0 {
		t.Errorf("on-court entry not refreshed after restoreFull: %+v", tm.players[0])
	}
}
