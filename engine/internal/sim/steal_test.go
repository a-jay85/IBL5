package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// twoTeams builds offense/defense states from richBundle (team 7 visitor, team 3
// home), each with five fully-rated starters.
func twoTeams() (*teamState, *teamState) {
	b := richBundle()
	return newTeamState(b.Players, 7, false), newTeamState(b.Players, 3, true)
}

// stealLineupPlayers builds a 5-starter player slice for team `teamID`, overriding
// every player's TVR (ball-handler carelessness) and STL (steal pressure) so the
// steal-turnover orientation is controllable and not clamp-saturated.
func stealLineupPlayers(teamID, tvr, stl int) []bundle.Player {
	var players []bundle.Player
	pid := teamID * 100
	for slot := slotPG; slot <= slotC; slot++ {
		pid++
		p := mkPlayer(pid, teamID, slot, 48)
		p.TVR = tvr
		p.STL = stl
		players = append(players, p)
	}
	return players
}

// stealLineups builds offense (team 7) and defense (team 3) 5-starter teams with a
// given ball-handler TVR and defender STL. Returned via newTeamState so the live
// maps (injured, box) are initialized.
func stealLineups(victimTVR, defenderSTL int) (*teamState, *teamState) {
	players := append(stealLineupPlayers(7, victimTVR, 30), stealLineupPlayers(3, 40, defenderSTL)...)
	return newTeamState(players, 7, false), newTeamState(players, 3, true)
}

// countStealTurnovers runs `seeds` independent single steal-turnover rolls (one
// fresh RNG per seed) and returns how many resolved to a turnover. count/seeds
// approximates the per-possession steal-turnover probability.
func countStealTurnovers(victimTVR, defenderSTL int, seeds uint64) int {
	count := 0
	for s := uint64(1); s <= seeds; s++ {
		off, def := stealLineups(victimTVR, defenderSTL)
		gs := &gameState{rng: rng.New(s), period: 1, clock: 500, stealTurnoverScale: stealTurnoverScale, nonStealTurnoverScale: nonStealTurnoverScale}
		if gs.stealTurnover(off, def, off.players[0]) {
			count++
		}
	}
	return count
}

// --- matrix #3: orientation — higher TVR → fewer TO; more STL → more TO --------

func TestTurnoverCarelessness_Orientation(t *testing.T) {
	// Higher TVR (better ball security) → lower carelessness → fewer turnovers.
	if turnoverCarelessness(20) <= turnoverCarelessness(80) {
		t.Errorf("carelessness must decrease with TVR: tvr20=%v tvr80=%v",
			turnoverCarelessness(20), turnoverCarelessness(80))
	}
	// Exact orientation: carelessnessBase − TVR.
	if got := turnoverCarelessness(40); got != carelessnessBase-40 {
		t.Errorf("carelessness(40) = %v, want %v", got, carelessnessBase-40)
	}
	// Floored at 0 for an over-base rating (never negative).
	if got := turnoverCarelessness(150); got != 0 {
		t.Errorf("carelessness(150) = %v, want 0 (floor)", got)
	}
}

func TestStealTurnover_ScalesWithDefensiveSTL(t *testing.T) {
	const seeds = 4000
	// Same ball-handler carelessness (TVR 40); only defensive STL differs.
	lowSTL := countStealTurnovers(40, 10, seeds)
	highSTL := countStealTurnovers(40, 40, seeds)
	if highSTL <= lowSTL {
		t.Errorf("high-STL defense should force MORE turnovers than low-STL: high=%d low=%d (of %d)",
			highSTL, lowSTL, seeds)
	}
}

func TestStealTurnover_HigherTVRFewerTurnovers(t *testing.T) {
	const seeds = 4000
	// Same defensive STL; only ball-handler TVR differs.
	careless := countStealTurnovers(20, 25, seeds) // low TVR = careless
	secure := countStealTurnovers(80, 25, seeds)   // high TVR = secure
	if secure >= careless {
		t.Errorf("a higher-TVR (more secure) handler should turn it over LESS: secure=%d careless=%d (of %d)",
			secure, careless, seeds)
	}
}

func TestTeamStealPressure_ScalesWithSTL(t *testing.T) {
	hi := teamStealPressure(newTeamState(stealLineupPlayers(3, 40, 40), 3, true))
	lo := teamStealPressure(newTeamState(stealLineupPlayers(3, 40, 10), 3, true))
	if !(hi > lo && lo > 0) {
		t.Errorf("steal pressure must rise with STL and be positive: hi=%v lo=%v", hi, lo)
	}
	zero := teamStealPressure(newTeamState(stealLineupPlayers(3, 40, 0), 3, true))
	if zero != 0 {
		t.Errorf("all-zero-STL pressure = %v, want 0", zero)
	}
}

// --- matrix #4: negative/boundary — zero STL, zero ratings ---------------------

func TestStealTurnover_AllZeroSTLNoTurnover(t *testing.T) {
	// An all-zero-STL defense has zero steal pressure → probability 0 → no
	// steal-driven turnover, and no divide-by-zero / panic.
	const seeds = 2000
	if got := countStealTurnovers(40, 0, seeds); got != 0 {
		t.Errorf("zero-STL defense produced %d steal turnovers, want 0", got)
	}
}

func TestTurnoverProb_NoNaNOnEmptyRatings(t *testing.T) {
	// All-zero TVR (carelessness = base) and all-zero STL (pressure 0): the weight
	// must be finite, never NaN/Inf, and exactly 0 (no pressure).
	gs := &gameState{stealTurnoverScale: stealTurnoverScale, nonStealTurnoverScale: nonStealTurnoverScale}
	pressure := teamStealPressure(newTeamState(stealLineupPlayers(3, 0, 0), 3, true))
	p := gs.turnoverProb(turnoverCarelessness(0), pressure)
	if math.IsNaN(p) || math.IsInf(p, 0) {
		t.Errorf("turnoverProb on empty ratings = %v, want finite", p)
	}
	if p != 0 {
		t.Errorf("zero steal pressure → prob %v, want 0", p)
	}
}

// --- matrix #5: on a steal-driven TO, defender credited STL, victim keeps TOV --

// On a steal-driven turnover stealTurnover emits EventTurnover (victim, → GameTOV)
// AND EventSteal (DefenderID = stealer, → the stealer's GameSTL). aggregateBoxes
// derives the box counters from those events; the helper writes no box rows.
func TestStealTurnover_CreditsDefenderVictimKeepsTOV(t *testing.T) {
	// A careless handler vs a high-STL defense almost always turns it over; find
	// the first seed that does so (the probabilities cannot be forced exactly).
	for seed := uint64(1); seed < 200; seed++ {
		offense, defense := stealLineups(10, 45)
		victim := offense.players[0]
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500, stealTurnoverScale: stealTurnoverScale, nonStealTurnoverScale: nonStealTurnoverScale}
		if !gs.stealTurnover(offense, defense, victim) {
			continue
		}

		var tos, steals []result.Event
		for _, e := range gs.events {
			switch e.Kind {
			case result.EventTurnover:
				tos = append(tos, e)
			case result.EventSteal:
				steals = append(steals, e)
			}
		}
		if len(tos) != 1 || len(steals) != 1 {
			t.Fatalf("expected 1 turnover + 1 steal, got %d turnovers / %d steals", len(tos), len(steals))
		}
		// Turnover belongs to the victim (→ GameTOV).
		if tos[0].PlayerID != victim.PID || tos[0].TeamID != offense.teamID {
			t.Errorf("turnover PlayerID/TeamID = %d/%d, want victim %d / offense %d",
				tos[0].PlayerID, tos[0].TeamID, victim.PID, offense.teamID)
		}
		// Steal credits a DEFENDER via DefenderID (→ GameSTL), never the victim.
		st := steals[0]
		if st.PlayerID != victim.PID {
			t.Errorf("steal PlayerID = %d, want victim %d", st.PlayerID, victim.PID)
		}
		if defense.box(st.DefenderID) == nil {
			t.Fatalf("DefenderID %d is not on the defending team", st.DefenderID)
		}
		if st.DefenderID == victim.PID {
			t.Error("stealer must not be the victim")
		}
		// No box rows written here (the box is event-derived).
		if defense.box(st.DefenderID).GameSTL != 0 {
			t.Error("stealTurnover must not write GameSTL (box is event-derived)")
		}
		return
	}
	t.Fatal("no seed in range produced a steal-driven turnover")
}

// --- nonStealTurnover: rows 14, 15, 23 ----------------------------------------

// countNonStealTurnovers runs `seeds` independent rolls with a ball-handler of the
// given TVR and returns how many resolved to a nonStealTurnover.
func countNonStealTurnovers(tvr int, seeds uint64) int {
	count := 0
	for s := uint64(1); s <= seeds; s++ {
		off, _ := stealLineups(tvr, 30)
		gs := &gameState{rng: rng.New(s), period: 1, clock: 500, stealTurnoverScale: stealTurnoverScale, nonStealTurnoverScale: nonStealTurnoverScale}
		if gs.nonStealTurnover(off, off.players[0]) {
			count++
		}
	}
	return count
}

// Row 14: prob at TVR=99 (carelessness=1) equals nonStealTurnoverScale×1.
func TestNonStealTurnover_ProbAtMaxSecurity(t *testing.T) {
	got := nonStealTurnoverScale * turnoverCarelessness(99)
	if got != nonStealTurnoverScale {
		t.Errorf("TVR=99 prob=%v, want nonStealTurnoverScale=%v", got, nonStealTurnoverScale)
	}
	if got <= 0 || got >= 0.01 {
		t.Errorf("TVR=99 prob=%v outside (0, 0.01)", got)
	}
}

// Row 15: nonStealTurnover rate scales with carelessness (higher TVR → fewer TOs).
func TestNonStealTurnover_RateScalesWithCarelessness(t *testing.T) {
	const seeds = 4000
	secure := countNonStealTurnovers(90, seeds)
	careless := countNonStealTurnovers(20, seeds)
	if secure >= careless {
		t.Errorf("higher TVR should yield fewer nonSteal TOs: TVR=90 got %d >= TVR=20 got %d (of %d)", secure, careless, seeds)
	}
}

// Row 23: nonStealTurnover emits EventTurnover but NOT EventSteal (non-arming).
func TestNonStealTurnover_NoEventSteal(t *testing.T) {
	offense, _ := stealLineups(0, 30) // TVR=0: max carelessness for high probability
	victim := offense.players[0]
	for seed := uint64(1); seed < 500; seed++ {
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500, stealTurnoverScale: stealTurnoverScale, nonStealTurnoverScale: nonStealTurnoverScale}
		if !gs.nonStealTurnover(offense, victim) {
			continue
		}
		var tos, steals int
		for _, e := range gs.events {
			switch e.Kind {
			case result.EventTurnover:
				tos++
			case result.EventSteal:
				steals++
			}
		}
		if tos != 1 {
			t.Errorf("seed=%d: expected 1 EventTurnover, got %d", seed, tos)
		}
		if steals != 0 {
			t.Errorf("seed=%d: nonStealTurnover emitted %d EventSteal(s), want 0 (non-arming)", seed, steals)
		}
		return
	}
	t.Fatal("no seed in range [1,500) triggered a nonStealTurnover")
}

// --- matrix #4: all-zero STL weights → players[0] fallback, no divide-by-zero --

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
