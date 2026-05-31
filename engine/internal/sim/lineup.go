package sim

import "github.com/a-jay85/IBL5/engine/internal/bundle"

// slotDepth returns the player's depth-chart value for the given slot (1=PG …
// 5=C). A value <= 0 means "not in the rotation at this slot".
func slotDepth(p bundle.Player, slot int) int {
	switch slot {
	case slotPG:
		return p.DCPGDepth
	case slotSG:
		return p.DCSGDepth
	case slotSF:
		return p.DCSFDepth
	case slotPF:
		return p.DCPFDepth
	case slotC:
		return p.DCCDepth
	}
	return 0
}

// selectStarters picks the fixed PR3a lineup: per slot, the eligible player with
// the lowest positive dc_*_depth. Each player starts at most one slot (greedy,
// slots resolved PG→C); ties break toward bundle order for determinism. Players
// who win no slot — and any with dc_can_play_in_game == 0 — do not start and end
// the game as DNP rows. A team with fewer than five eligible players simply
// returns fewer starters; the caller tolerates a short lineup.
//
// PR4 replaces this with the real 5-pass selection (dc_bh/di/oi/df/of) and
// substitutions; PR3a is deliberately fixed-starter, no subs.
func selectStarters(players []bundle.Player, teamID int) []onCourt {
	roster := make([]bundle.Player, 0, len(players))
	for _, p := range players {
		if p.TeamID == teamID && p.DCCanPlayInGame != 0 {
			roster = append(roster, p)
		}
	}

	chosen := make(map[int]bool, 5)
	starters := make([]onCourt, 0, 5)
	for slot := slotPG; slot <= slotC; slot++ {
		bestIdx := -1
		bestDepth := 0
		for i, p := range roster {
			if chosen[p.PID] {
				continue
			}
			d := slotDepth(p, slot)
			if d <= 0 {
				continue
			}
			if bestIdx == -1 || d < bestDepth {
				bestIdx, bestDepth = i, d
			}
		}
		if bestIdx == -1 {
			continue // no eligible player for this slot
		}
		p := roster[bestIdx]
		chosen[p.PID] = true
		starters = append(starters, onCourt{
			Player:  p,
			slot:    slot,
			fatigue: fatigueFactor(p.Stamina), // constant in PR3a (energy = base stamina)
		})
	}
	return starters
}
