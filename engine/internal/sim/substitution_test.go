package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// subTeam builds a 5-starter team with one PG backup (PID 11, depth 2). Starter
// PG is PID 1 (depth 1).
func subTeam() *teamState {
	players := []bundle.Player{
		{PID: 1, TeamID: 7, DCPGDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 11, TeamID: 7, DCPGDepth: 2, DCMinutes: 20, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 2, TeamID: 7, DCSGDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 3, TeamID: 7, DCSFDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 4, TeamID: 7, DCPFDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
		{PID: 5, TeamID: 7, DCCDepth: 1, DCMinutes: 30, Stamina: 50, DCCanPlayInGame: 1},
	}
	return newTeamState(players, 7, true)
}

func onCourtPIDs(t *teamState) map[int]bool {
	m := make(map[int]bool, len(t.players))
	for _, oc := range t.players {
		m[oc.PID] = true
	}
	return m
}

// --- matrix #9: foulThreshold phase table + Q4 / OT boundary ----------------

func TestFoulThreshold_PhaseTable(t *testing.T) {
	cases := []struct {
		period, clock, want int
	}{
		{1, 720, 2},
		{2, 720, 3},
		{3, 720, 4},
		{4, 301, 5}, // Q4 with > 5:00 left
		{4, 300, 6}, // final 5:00 → hold to 6th foul
		{4, 0, 6},
		{5, 300, 6}, // overtime
	}
	for _, c := range cases {
		if got := foulThreshold(c.period, c.clock); got != c.want {
			t.Errorf("foulThreshold(%d, %d) = %d, want %d", c.period, c.clock, got, c.want)
		}
	}
}

// --- matrix #10: foul-out emits two adjacent substitution events ------------

func TestCheckSubstitutions_FoulOutEmitsOutThenIn(t *testing.T) {
	tm := subTeam()
	tm.fouls[1] = 6 // PG over the foul-out limit (live tally drives subs; box PF is event-derived)

	var events []result.Event
	checkSubstitutions(tm, 1, 600, func(e result.Event) { events = append(events, e) })

	if len(events) != 2 {
		t.Fatalf("events = %d, want exactly 2", len(events))
	}
	for i, e := range events {
		if e.Kind != result.EventSubstitution || e.TeamID != 7 {
			t.Errorf("event %d = %+v, want EventSubstitution TeamID 7", i, e)
		}
	}
	if events[0].PlayerID != 1 {
		t.Errorf("first event PlayerID = %d, want 1 (outgoing)", events[0].PlayerID)
	}
	if events[1].PlayerID != 11 {
		t.Errorf("second event PlayerID = %d, want 11 (incoming)", events[1].PlayerID)
	}
	if !tm.fouledOut[1] {
		t.Error("PID 1 should be marked fouled out")
	}
	on := onCourtPIDs(tm)
	if on[1] || !on[11] {
		t.Errorf("after foul-out: on-court = %v, want 11 in / 1 out", on)
	}
}

// --- matrix #11: fouled-out player never re-enters --------------------------

func TestCheckSubstitutions_FoulOutPermanent(t *testing.T) {
	tm := subTeam()
	tm.fouls[1] = 6
	checkSubstitutions(tm, 1, 600, func(result.Event) {}) // 1 out, 11 in

	// Now foul out the replacement too. No other PG backup exists (1 is barred),
	// so 11 is removed and the team plays short — 1 must NOT return.
	tm.fouls[11] = 6
	checkSubstitutions(tm, 1, 600, func(result.Event) {})

	on := onCourtPIDs(tm)
	if on[1] {
		t.Error("fouled-out PID 1 re-entered the lineup")
	}
	if on[11] {
		t.Error("fouled-out PID 11 still on court")
	}
	if !tm.fouledOut[1] || !tm.fouledOut[11] {
		t.Error("both PG players should be marked fouled out")
	}
}

// --- matrix #12: foul-out with no backup removes player, no panic -----------

func TestCheckSubstitutions_FoulOutNoBackupPlaysShort(t *testing.T) {
	tm := subTeam()
	tm.fouls[5] = 6 // C has no backup

	before := len(tm.players)
	var events []result.Event
	checkSubstitutions(tm, 1, 600, func(e result.Event) { events = append(events, e) })

	if len(tm.players) != before-1 {
		t.Errorf("players = %d, want %d (C removed, no replacement)", len(tm.players), before-1)
	}
	if onCourtPIDs(tm)[5] {
		t.Error("fouled-out C (PID 5) still on court")
	}
	if len(events) != 0 {
		t.Errorf("events = %d, want 0 (no replacement to swap in)", len(events))
	}
}

// --- matrix #13: fatigued player with no fresher backup stays on court ------

func TestCheckSubstitutions_FatiguedNoFresherBackupStays(t *testing.T) {
	tm := subTeam()
	tm.energy[1] = -5   // starting PG is fatigued (energy < 0)
	tm.energy[11] = -10 // only backup is even more tired

	var events []result.Event
	checkSubstitutions(tm, 1, 600, func(e result.Event) { events = append(events, e) })

	if !onCourtPIDs(tm)[1] {
		t.Error("fatigued PID 1 should stay — no strictly fresher backup")
	}
	if len(events) != 0 {
		t.Errorf("events = %d, want 0 (no swap)", len(events))
	}
}

// A fresher backup DOES trigger a fatigue swap (complements #13).
func TestCheckSubstitutions_FatiguedFresherBackupSwaps(t *testing.T) {
	tm := subTeam()
	tm.energy[1] = -5  // starting PG fatigued
	tm.energy[11] = 40 // backup is fresher

	var events []result.Event
	checkSubstitutions(tm, 1, 600, func(e result.Event) { events = append(events, e) })

	on := onCourtPIDs(tm)
	if on[1] || !on[11] {
		t.Errorf("fatigue swap failed: on-court = %v, want 11 in / 1 out", on)
	}
	if len(events) != 2 {
		t.Errorf("events = %d, want 2 (out + in)", len(events))
	}
}
