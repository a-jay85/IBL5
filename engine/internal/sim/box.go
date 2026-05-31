package sim

import (
	"math"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// slotName maps a lineup slot to its position string.
func slotName(slot int) string {
	switch slot {
	case slotPG:
		return "PG"
	case slotSG:
		return "SG"
	case slotSF:
		return "SF"
	case slotPF:
		return "PF"
	case slotC:
		return "C"
	}
	return ""
}

// bestDepthPos returns the position string of a player's lowest positive
// depth-chart slot — used for the Pos field of non-starters (DNP/bench).
func bestDepthPos(p bundle.Player) string {
	best, bestDepth := "", 0
	for slot := slotPG; slot <= slotC; slot++ {
		d := slotDepth(p, slot)
		if d <= 0 {
			continue
		}
		if best == "" || d < bestDepth {
			best, bestDepth = slotName(slot), d
		}
	}
	return best
}

// newTeamState builds a team's live state: its starters, a box-score row for
// every rostered player in bundle order (DNP rows carry GameMIN == 0 with all
// stats zero), a PID index over those rows (roster-metadata lookups; stats are
// event-derived by aggregateBoxes), the eligible-roster candidate pool for
// substitutions, and the live energy/minutes maps (seeded to each eligible
// player's Stamina ceiling / 0 seconds). GameMIN is now accumulated
// on-court time finalized at game end (see finalizeMinutes) — every player,
// starter or bench, begins at 0; only seconds actually spent on court count.
func newTeamState(allPlayers []bundle.Player, teamID int, isHome bool) *teamState {
	starters := selectStarters(allPlayers, teamID)
	starterSlot := make(map[int]int, len(starters))
	for _, s := range starters {
		starterSlot[s.PID] = s.slot
	}

	t := &teamState{
		teamID:      teamID,
		isHome:      isHome,
		players:     starters,
		byPID:       make(map[int]*result.PlayerBox),
		playerByPID: make(map[int]bundle.Player),
		energy:      make(map[int]float64),
		minutes:     make(map[int]float64),
		fouledOut:   make(map[int]bool),
		fouls:       make(map[int]int),
		quarters:    make([]int, 0, 4),
	}
	for _, p := range allPlayers {
		if p.TeamID != teamID {
			continue
		}
		box := &result.PlayerBox{PID: p.PID}
		if slot, ok := starterSlot[p.PID]; ok {
			box.Pos = slotName(slot)
		} else {
			box.Pos = bestDepthPos(p) // DNP/bench
		}
		t.boxes = append(t.boxes, box)
		t.byPID[p.PID] = box

		// Eligible players (can play) form the substitution candidate pool and
		// get live energy/minutes tracking seeded at their Stamina / 0.
		if p.DCCanPlayInGame != 0 {
			t.roster = append(t.roster, p)
			t.playerByPID[p.PID] = p
			t.energy[p.PID] = float64(p.Stamina)
			t.minutes[p.PID] = 0
		}
	}

	// Starters' live energy/fatigue come from the seeded map (energy = Stamina at
	// tip-off; fatigue ≈ 1.0 under the current curve).
	for i := range t.players {
		t.refreshOnCourt(&t.players[i])
	}
	return t
}

// finalizeMinutes converts each eligible player's accumulated on-court seconds
// into GameMIN (rounded minutes). DNP players never accrued, so they stay at 0.
func (t *teamState) finalizeMinutes() {
	for _, box := range t.boxes {
		box.GameMIN = int(math.Round(t.minutes[box.PID] / 60.0))
	}
}

// box returns the box-score row for a PID (nil if not on the team). After PR5 the
// row carries only roster metadata (PID/Pos/GameMIN); its stat counters are
// derived from the event stream by aggregateBoxes. Tests still assert through it.
func (t *teamState) box(pid int) *result.PlayerBox { return t.byPID[pid] }
