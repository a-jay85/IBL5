package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// twoTeams builds offense/defense states from richBundle (team 7 visitor, team 3
// home), each with five fully-rated starters.
func twoTeams() (*teamState, *teamState) {
	b := richBundle()
	return newTeamState(b.Players, 7, false), newTeamState(b.Players, 3, true)
}

// --- matrix #3: steal credits a defender; victim keeps the turnover ----------

func TestCreditSteal_CreditsDefenderVictimKeepsTOV(t *testing.T) {
	// Find a seed whose first Float64 falls under stealFraction so creditSteal
	// resolves to a steal (the probabilities cannot be forced to an exact value).
	for seed := uint64(1); seed < 200; seed++ {
		offense, defense := twoTeams()
		victim := offense.players[0]
		offense.box(victim.PID).GameTOV++ // caller credits the TOV first
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}

		before := len(gs.events)
		if !gs.creditSteal(offense, defense, victim) {
			continue // this seed was an unforced turnover; try the next
		}

		// Victim retains the turnover; no steal is credited to the victim.
		if got := offense.box(victim.PID).GameTOV; got != 1 {
			t.Fatalf("victim GameTOV = %d, want 1 (kept)", got)
		}
		if offense.box(victim.PID).GameSTL != 0 {
			t.Fatalf("victim must not be credited a steal")
		}

		// Exactly one EventSteal, crediting a defender.
		var steals []result.Event
		for _, e := range gs.events[before:] {
			if e.Kind == result.EventSteal {
				steals = append(steals, e)
			}
		}
		if len(steals) != 1 {
			t.Fatalf("expected 1 EventSteal, got %d", len(steals))
		}
		ev := steals[0]
		if ev.TeamID != offense.teamID {
			t.Errorf("EventSteal TeamID = %d, want offense %d", ev.TeamID, offense.teamID)
		}
		if ev.PlayerID != victim.PID {
			t.Errorf("EventSteal PlayerID = %d, want victim %d", ev.PlayerID, victim.PID)
		}
		// DefenderID is the stealer and must be on the defending team with a STL credit.
		if defense.box(ev.DefenderID) == nil {
			t.Fatalf("DefenderID %d is not on the defending team", ev.DefenderID)
		}
		if defense.box(ev.DefenderID).GameSTL != 1 {
			t.Errorf("stealer %d GameSTL = %d, want 1", ev.DefenderID, defense.box(ev.DefenderID).GameSTL)
		}
		return
	}
	t.Fatal("no seed in range produced a steal")
}

// --- matrix #4: all-zero STL weights → players[0] fallback, no divide-by-zero -

func TestSelectStealer_AllZeroWeightsFallsBack(t *testing.T) {
	b := richBundle()
	// Zero out every STL rating so the weighted sum is zero.
	for i := range b.Players {
		b.Players[i].STL = 0
	}
	defense := newTeamState(b.Players, 3, true)

	got, ok := selectStealer(defense, rng.New(7))
	if ok {
		t.Error("ok should be false on the all-zero fallback")
	}
	if got.PID != defense.players[0].PID {
		t.Errorf("fallback returned %d, want players[0] = %d", got.PID, defense.players[0].PID)
	}
}

// --- matrix #5: the steal/unforced split is real -----------------------------

func TestCreditSteal_SplitIsReal(t *testing.T) {
	var steals, unforced int
	for seed := uint64(1); seed <= 400; seed++ {
		offense, defense := twoTeams()
		victim := offense.players[0]
		offense.box(victim.PID).GameTOV++
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		if gs.creditSteal(offense, defense, victim) {
			steals++
		} else {
			unforced++
		}
	}
	// Both outcomes must occur — not every turnover is a steal, and not every
	// turnover is unforced. (stealFraction = 0.55.)
	if steals == 0 {
		t.Error("no turnovers resolved to a steal")
	}
	if unforced == 0 {
		t.Error("no turnovers were unforced (no stealer)")
	}
}
