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
//   - Injury (marked by maybeInjure): permanent removal, same swap-or-play-short
//     behavior as foul-out. Highest priority; an injured player never stays.
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

	// Backups may not be anyone currently on court, already fouled out, or
	// injured; as the sweep brings players in, they too become unavailable.
	taken := make(map[int]bool, len(t.players)+len(t.fouledOut)+len(t.injured))
	for pid := range t.fouledOut {
		taken[pid] = true
	}
	for pid := range t.injured {
		taken[pid] = true // an injured player must never re-enter as a backup
	}
	for i := range t.players {
		taken[t.players[i].PID] = true
	}

	next := make([]onCourt, 0, len(t.players))
	for i := range t.players {
		out := t.players[i]
		pf := t.fouls[out.PID]

		// Injury and foul-out are both permanent removals and rank highest; an
		// injured player (already marked by maybeInjure) is never re-added to next.
		inj := t.injured[out.PID]
		foulOut := !inj && pf > foulOutLimit
		foulTrouble := !inj && !foulOut && pf >= threshold
		fatigued := !inj && !foulOut && !foulTrouble && t.energy[out.PID] < 0

		if foulOut {
			t.fouledOut[out.PID] = true
		} else if !inj && !foulTrouble && !fatigued {
			next = append(next, out) // no trigger: stays on court
			continue
		}

		var in onCourt
		var ok bool
		if fatigued {
			// PR4b: fatigue subs pick the highest-TransOff strictly-fresher backup,
			// not the DC/qualityScore top-ranked one, to concentrate minutes on
			// higher-TransOff players (closes the J24 minute-allocation gap). The
			// strictly-fresher gate now lives inside bestFatigueBackup.
			in, ok = t.bestFatigueBackup(out.slot, taken, t.energy[out.PID])
		} else {
			in, ok = t.bestBackup(out.slot, taken)
		}
		if !ok {
			if foulOut || inj {
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

// bestFatigueBackup returns the eligible backup a *fatigue* sub should bring in:
// among slot candidates strictly fresher than the outgoing player (live energy >
// outEnergy), the one with the highest r_trans_off. candidatesForSlot supplies
// the eligible pool already ranked by the PR4a DC/qualityScore comparator, so a
// TransOff tie deterministically keeps the earlier (better-ranked) candidate via
// the strict `>` compare — no RNG, no new tie-break. ok is false when no strictly
// fresher eligible backup exists (fatigue subs require a strictly fresher option;
// same freshness gate the old inline check enforced). Unlike bestBackup, this does
// NOT restrict itself to the top-ranked candidate: it scans the whole strictly-
// fresher pool so a high-TransOff, lower-DC bench player can win the minutes the
// real binary concentrates on higher-TransOff players (see J24 PR4b RE artifact).
func (t *teamState) bestFatigueBackup(slot int, taken map[int]bool, outEnergy float64) (onCourt, bool) {
	cands := candidatesForSlot(t.roster, slot, taken)
	best := -1
	for i := range cands {
		if t.energy[cands[i].player.PID] <= outEnergy {
			continue // not strictly fresher than the outgoing player
		}
		if best < 0 || cands[i].player.TransOff > cands[best].player.TransOff {
			best = i
		}
	}
	if best < 0 {
		return onCourt{}, false
	}
	in := onCourt{Player: cands[best].player, slot: slot}
	t.refreshOnCourt(&in)
	return in, true
}
