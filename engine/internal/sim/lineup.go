package sim

import (
	"math"
	"sort"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

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

// posmatch reports whether a player's natural position (its lowest positive
// depth-chart slot) matches the given slot. It is the fallback-path gate of the
// JSB 5-pass: a dc<=0 candidate is only eligible for a slot when its natural
// position is that slot.
//
// On IBL5 data this is provably dead: bestDepthPos only ever returns a slot
// where slotDepth(p, slot) > 0, so it can never equal a slot where the
// candidate's dc is <= 0 — the two conditions are mutually exclusive for all
// inputs. We port the predicate for fidelity (mirroring the always-1.0
// fatigueFactor and the forced-zero dc_bh fields) and document the deadness; a
// stored natural-position field would be needed to make the +48 band reachable.
func posmatch(p bundle.Player, slot int) bool {
	return bestDepthPos(p) == slotName(slot)
}

// qualityScore is the pure JSB 5-pass candidate score for a slot. dc is the
// candidate's depth ordinal at the slot (slotDepth); minutes is dc_minutes; pm
// is whether the candidate's natural position matches the slot (posmatch).
//
//   - Bonus path (dc > 0, no position check):
//     dc<5 && min>=12 → min+192; dc<5 && min<12 → min+144; dc>=5 → min.
//   - Fallback path (dc <= 0, position must match):
//     pm && min>=12 → min+48; pm && min<12 → min; else 0.
//
// The +96 "already in another slot" JSB tier is intentionally omitted: chosen
// starters are removed from the greedy pool, so no candidate is ever "already in
// another slot" during the starter pass.
//
// NOTE: score == 0 does NOT mean "unqualified" — qualityScore(5, 0, _) == 0 for
// a genuinely-eligible deep-bench player. Eligibility is the separate predicate
// dc>0 || posmatch (see candidatesForSlot); callers never filter on score.
func qualityScore(dc, minutes int, pm bool) int {
	if dc > 0 {
		if dc < 5 {
			if minutes >= 12 {
				return minutes + 192
			}
			return minutes + 144
		}
		return minutes // dc >= 5: no band bonus
	}
	// Fallback path (dc <= 0): only position-matching candidates score.
	if pm {
		if minutes >= 12 {
			return minutes + 48
		}
		return minutes
	}
	return 0
}

// dcSortKey maps a candidate's slot depth to its sort key: positive depths sort
// ascending (depth 1 is best), while dc<=0 (fallback candidates) sort last so
// any bonus candidate beats any fallback candidate.
func dcSortKey(dc int) int {
	if dc <= 0 {
		return math.MaxInt
	}
	return dc
}

// slotCandidate is one ranked candidate for a slot during the 5-pass.
type slotCandidate struct {
	player bundle.Player
	idx    int // roster (bundle) order, for deterministic tie-break
	dc     int
	score  int
}

// candidatesForSlot builds the ranked, eligible candidates for one slot from the
// roster, skipping any PID in `taken`. Eligibility is dc>0 (bonus path) or
// posmatch (fallback path); ineligible players are excluded so an unfillable
// slot stays empty (short lineups are preserved). Ranking: dc ASC (dc<=0 last),
// then score DESC, then bundle order ASC.
func candidatesForSlot(roster []bundle.Player, slot int, taken map[int]bool) []slotCandidate {
	cands := make([]slotCandidate, 0, len(roster))
	for i, p := range roster {
		if taken[p.PID] {
			continue
		}
		dc := slotDepth(p, slot)
		pm := posmatch(p, slot)
		if dc <= 0 && !pm {
			continue // ineligible for this slot
		}
		cands = append(cands, slotCandidate{
			player: p,
			idx:    i,
			dc:     dc,
			score:  qualityScore(dc, p.DCMinutes, pm),
		})
	}
	sort.SliceStable(cands, func(a, b int) bool {
		ka, kb := dcSortKey(cands[a].dc), dcSortKey(cands[b].dc)
		if ka != kb {
			return ka < kb // dc ASC primary (dc<=0 sorts last)
		}
		if cands[a].score != cands[b].score {
			return cands[a].score > cands[b].score // score DESC secondary
		}
		return cands[a].idx < cands[b].idx // bundle order tertiary
	})
	return cands
}

// selectStarters runs the real JSB five-pass starter selection. Pass i (PG→C)
// fills slot i with the top-ranked eligible candidate from the team's roster
// (TeamID match, dc_can_play_in_game != 0, not already chosen), ranked by the
// qualityScore + dc-ASC/score-DESC/bundle-order comparator. Each chosen starter
// is removed from the pool so no player fills two slots. Players who win no slot
// — and any with dc_can_play_in_game == 0 — do not start and end the game as DNP
// rows. A team with fewer than five eligible players returns fewer starters; the
// caller tolerates a short lineup.
//
// The 5-pass operates on the depth ordinals dc_pg_depth…dc_c_depth + dc_minutes,
// the live IBL5 depth fields. The JSB-internal dc_bh/di/oi/df/of fields are dead
// on IBL5 data (DepthChartEntryRepository forces them to 0) and are deliberately
// not read; reading them would score every candidate identically. The fallback
// +48 band (dc<=0 + posmatch) is ported for fidelity but is unreachable
// end-to-end, since posmatch implies dc>0 (see posmatch).
//
// PR3a held energy = base stamina (fatigue ≈ 1.0); PR4b adds energy
// drain/recovery, minutes, and substitutions on top of this selection.
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
		cands := candidatesForSlot(roster, slot, chosen)
		if len(cands) == 0 {
			continue // no eligible player for this slot
		}
		p := cands[0].player
		chosen[p.PID] = true
		starters = append(starters, onCourt{
			Player:  p,
			slot:    slot,
			fatigue: fatigueFactor(p.Stamina), // constant in PR3a (energy = base stamina)
		})
	}
	return starters
}

// rankedRotation returns, per slot (index 0=PG … 4=C), the eligible non-starters
// for that slot ordered by the same qualityScore + comparator selectStarters
// uses. It is the ranked rotation source PR4b's substitution logic will consume;
// PR4a computes and tests it, but the game driver does not yet call it. The JSB
// "self-backup when no position-matching non-starter exists" fallback is left to
// PR4b's consuming caller, which decides what to do with an empty list.
func rankedRotation(players []bundle.Player, teamID int, starters []onCourt) [][]onCourt {
	roster := make([]bundle.Player, 0, len(players))
	for _, p := range players {
		if p.TeamID == teamID && p.DCCanPlayInGame != 0 {
			roster = append(roster, p)
		}
	}

	starterPID := make(map[int]bool, len(starters))
	for _, s := range starters {
		starterPID[s.PID] = true
	}

	rotation := make([][]onCourt, slotC) // slotC == 5
	for slot := slotPG; slot <= slotC; slot++ {
		cands := candidatesForSlot(roster, slot, starterPID)
		bench := make([]onCourt, 0, len(cands))
		for _, c := range cands {
			bench = append(bench, onCourt{
				Player:  c.player,
				slot:    slot,
				fatigue: fatigueFactor(c.player.Stamina),
			})
		}
		rotation[slot-1] = bench
	}
	return rotation
}
