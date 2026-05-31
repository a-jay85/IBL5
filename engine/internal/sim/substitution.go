package sim

import "github.com/a-jay85/IBL5/engine/internal/result"

// foulOutLimit is the 6th personal foul: PF strictly greater than 5 means the
// player has fouled out and must leave permanently.
const foulOutLimit = 5

// foulThreshold is the personal-foul count at which a player is pulled for foul
// trouble in the given period/clock phase. It ramps through the game (a Q1 foul
// is treated more cautiously per remaining minutes) and is read as clock-
// remaining in Q4: the final five minutes (clock ≤ 300) and all overtime hold
// players until the hard 6th-foul limit.
//
// Q1→2, Q2→3, Q3→4, Q4 early (clock>300)→5, Q4 late (clock≤300)/OT→6.
func foulThreshold(period, clock int) int {
	switch period {
	case 1:
		return 2
	case 2:
		return 3
	case 3:
		return 4
	case 4:
		if clock > 300 {
			return 5
		}
		return 6
	default: // overtime
		return 6
	}
}

// checkSubstitutions runs the dead-ball substitution sweep for one team. It is
// deterministic and draws ZERO RNG — triggers (foul-out, foul-trouble, energy)
// and backup ranking (the PR4a qualityScore comparator via candidatesForSlot,
// plus a live-energy compare for fatigue subs) use no gs.rng. A single stray
// draw here would shift the entire downstream sequence and break TestDeterminism;
// this is a hard invariant, enforced by the rng-free signature.
//
// Per on-court player, in slot order, the first matching trigger fires:
//   - Foul-out (PF > 5): permanent removal. Swap in the best eligible backup at
//     the slot if one exists; otherwise the player is removed and the team plays
//     short (the possession loop already tolerates < 5).
//   - Foul-trouble (PF ≥ phase threshold): swap if a backup exists, else stay.
//   - Energy (energy < 0): swap only for a strictly fresher backup, else stay.
//
// A player subbed out (or any already-on-court player) is not reconsidered as a
// backup within the same sweep, so a single dead ball never thrashes a slot.
func checkSubstitutions(t *teamState, period, clock int, emit func(result.Event)) {
	threshold := foulThreshold(period, clock)

	// Backups may not be anyone currently on court or already fouled out; as the
	// sweep brings players in, they too become unavailable.
	taken := make(map[int]bool, len(t.players)+len(t.fouledOut))
	for pid := range t.fouledOut {
		taken[pid] = true
	}
	for i := range t.players {
		taken[t.players[i].PID] = true
	}

	next := make([]onCourt, 0, len(t.players))
	for i := range t.players {
		out := t.players[i]
		pf := t.fouls[out.PID]

		foulOut := pf > foulOutLimit
		foulTrouble := !foulOut && pf >= threshold
		fatigued := !foulOut && !foulTrouble && t.energy[out.PID] < 0

		if foulOut {
			t.fouledOut[out.PID] = true
		} else if !foulTrouble && !fatigued {
			next = append(next, out) // no trigger: stays on court
			continue
		}

		in, ok := t.bestBackup(out.slot, taken)
		if ok && fatigued && t.energy[in.PID] <= t.energy[out.PID] {
			ok = false // fatigue sub needs a strictly fresher option
		}
		if !ok {
			if foulOut {
				continue // removed; team plays short (no replacement available)
			}
			next = append(next, out) // foul-trouble/fatigue with no backup: stay
			continue
		}

		taken[in.PID] = true
		emit(result.Event{Kind: result.EventSubstitution, Period: period, Clock: clock, TeamID: t.teamID, PlayerID: out.PID})
		emit(result.Event{Kind: result.EventSubstitution, Period: period, Clock: clock, TeamID: t.teamID, PlayerID: in.PID})
		next = append(next, in)
	}
	t.players = next
}

// bestBackup returns the top-ranked eligible backup for a slot — the PR4a
// qualityScore/comparator winner among roster players not in `taken` — built as
// an on-court entry with its current (recovered) live energy and fatigue. ok is
// false when no eligible backup exists at the slot.
func (t *teamState) bestBackup(slot int, taken map[int]bool) (onCourt, bool) {
	cands := candidatesForSlot(t.roster, slot, taken)
	if len(cands) == 0 {
		return onCourt{}, false
	}
	in := onCourt{Player: cands[0].player, slot: slot}
	t.refreshOnCourt(&in)
	return in, true
}
