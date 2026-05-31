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
// stats zero), a PID index for stat accumulation, the eligible-roster candidate
// pool for substitutions, and the live energy/minutes maps (seeded to each
// eligible player's Stamina ceiling / 0 seconds). GameMIN is now accumulated
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

// box returns the mutable box-score row for a PID (nil if not on the team).
func (t *teamState) box(pid int) *result.PlayerBox { return t.byPID[pid] }

// playerBoxes returns the team's player rows in bundle order.
func (t *teamState) playerBoxes() []result.PlayerBox {
	out := make([]result.PlayerBox, len(t.boxes))
	for i, b := range t.boxes {
		out[i] = *b
	}
	return out
}

// teamBox rolls the player rows up into team totals and lays the running
// quarter scores into Q1–Q4 (+ OT). GameSTL/GameBLK/GameAST stay 0 here because
// no player row ever sets them (steal/block attribution is PR3b; assists are
// commentary-only, master-reference L1098).
func (t *teamState) teamBox() result.TeamBox {
	tb := result.TeamBox{TeamID: t.teamID, IsHome: t.isHome, OT: []int{}}
	for _, b := range t.boxes {
		tb.Game2GM += b.Game2GM
		tb.Game2GA += b.Game2GA
		tb.GameFTM += b.GameFTM
		tb.GameFTA += b.GameFTA
		tb.Game3GM += b.Game3GM
		tb.Game3GA += b.Game3GA
		tb.GameORB += b.GameORB
		tb.GameDRB += b.GameDRB
		tb.GameAST += b.GameAST
		tb.GameSTL += b.GameSTL
		tb.GameTOV += b.GameTOV
		tb.GameBLK += b.GameBLK
		tb.GamePF += b.GamePF
	}
	for i, pts := range t.quarters {
		switch i {
		case 0:
			tb.Q1 = pts
		case 1:
			tb.Q2 = pts
		case 2:
			tb.Q3 = pts
		case 3:
			tb.Q4 = pts
		default:
			tb.OT = append(tb.OT, pts)
		}
	}
	return tb
}
