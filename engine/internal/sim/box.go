package sim

import (
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

// newTeamState builds a team's live state: its fixed starters, a box-score row
// for every rostered player in bundle order (DNP rows carry GameMIN == 0 with
// all stats zero), and a PID index for stat accumulation. Starter minutes come
// from dc_minutes (clamped 0..48).
func newTeamState(allPlayers []bundle.Player, teamID int, isHome bool) *teamState {
	starters := selectStarters(allPlayers, teamID)
	starterSlot := make(map[int]int, len(starters))
	for _, s := range starters {
		starterSlot[s.PID] = s.slot
	}

	t := &teamState{
		teamID:   teamID,
		isHome:   isHome,
		players:  starters,
		byPID:    make(map[int]*result.PlayerBox),
		quarters: make([]int, 0, 4),
	}
	for _, p := range allPlayers {
		if p.TeamID != teamID {
			continue
		}
		box := &result.PlayerBox{PID: p.PID}
		if slot, ok := starterSlot[p.PID]; ok {
			box.Pos = slotName(slot)
			min := p.DCMinutes
			if min < 0 {
				min = 0
			}
			if min > 48 {
				min = 48
			}
			box.GameMIN = min
		} else {
			box.Pos = bestDepthPos(p) // DNP/bench: GameMIN stays 0
		}
		t.boxes = append(t.boxes, box)
		t.byPID[p.PID] = box
	}
	return t
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
