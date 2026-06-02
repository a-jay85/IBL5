package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// onePlayerTeam wraps a single starter as a teamState — used to neutralize the
// random-starter draw in Stage-2 trigger boundary tests (IntN(1) == 0).
func onePlayerTeam(p bundle.Player) *teamState {
	return &teamState{players: []onCourt{oc(slotPG, p)}}
}

// --- matrix #10: a transition possession resolves and is never a 3pt ---------

func TestRunTransitionPossession_NeverThreePoint(t *testing.T) {
	var attempts, threes, resolved int
	for seed := uint64(1); seed <= 300; seed++ {
		offense, defense := twoTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		gs.transitionShotRate = resetTransitionShotRate(offense)
		gs.runTransitionPossession(offense, defense, 0)
		resolved++
		for _, e := range gs.events {
			if e.Kind == result.EventShotAttempt {
				attempts++
				if e.ShotType == result.ShotThree {
					threes++
				}
			}
		}
	}
	if resolved == 0 {
		t.Fatal("no transition possessions ran")
	}
	if attempts == 0 {
		t.Fatal("transition possessions produced no shot attempts")
	}
	if threes != 0 {
		t.Errorf("transition produced %d three-point attempts, want 0", threes)
	}
}

// --- matrix #6: HCA is wired at the transition (fast-break) assembly site -----
//
// The transition possession path is the SECOND of the two outcomeInputs assembly
// sites. This proves HCA threads into it: with identical-rated teams (symmetric
// fixture), a HOME offense must draw more free throws over many fast breaks than an
// AWAY offense (the foul-bucket divisor shrinks for the home team). The only
// difference between the two cases is offense.isHome → hcaDelta; ratings are equal.
func TestRunTransitionPossession_HomeGrowsFoulBucket(t *testing.T) {
	b := symmetricBundle()

	countFTA := func(offTeam, defTeam int, isHome bool) int {
		var fta int
		for seed := uint64(1); seed <= 3000; seed++ {
			offense := newTeamState(b.Players, offTeam, isHome)
			defense := newTeamState(b.Players, defTeam, !isHome)
			gs := &gameState{rng: rng.New(seed), gameType: bundle.GameTypeRegular, period: 1, clock: 500}
			gs.transitionShotRate = resetTransitionShotRate(offense)
			gs.runTransitionPossession(offense, defense, 0)
			for _, e := range gs.events {
				if e.Kind == result.EventFreeThrow {
					fta += e.FTAttempts
				}
			}
		}
		return fta
	}

	homeFTA := countFTA(3, 7, true)  // team 3 on offense, isHome=true
	awayFTA := countFTA(7, 3, false) // team 7 on offense, isHome=false (identical ratings)

	t.Logf("transition FTA over 3000 fast breaks: home=%d away=%d (home−away=%+d)", homeFTA, awayFTA, homeFTA-awayFTA)
	if homeFTA <= awayFTA {
		t.Errorf("home transition FTA %d ≤ away %d — HCA not wired at the transition assembly site", homeFTA, awayFTA)
	}
}

// --- matrix #11: transitionNet = 5.0 − floor1(TD), no position penalty --------

func TestTransitionNet(t *testing.T) {
	cases := []struct {
		td   int
		want float64
	}{
		{5, 0.0},  // 5.0 − 5
		{0, 4.0},  // floor1(0) = 1 → 5.0 − 1
		{9, -4.0}, // 5.0 − 9
		{1, 4.0},  // 5.0 − 1
	}
	for _, c := range cases {
		p := mkPlayer(1, 7, slotPG, 46)
		p.TD = c.td
		if got := transitionNet(oc(slotPG, p)); got != c.want {
			t.Errorf("transitionNet(TD=%d) = %v, want %v", c.td, got, c.want)
		}
	}
}

// --- matrix #12: Stage-2 trigger boundary ------------------------------------

func TestTransitionTriggers_Boundary(t *testing.T) {
	zero := mkPlayer(1, 7, slotPG, 46)
	zero.TransOff = 0
	full := mkPlayer(2, 7, slotPG, 46)
	full.TransOff = transitionTriggerDenom // roll (1..denom) ≤ denom always holds

	for seed := uint64(1); seed <= 200; seed++ {
		r := rng.New(seed)
		if transitionTriggers(onePlayerTeam(zero), bundle.GameTypeRegular, r) {
			t.Fatalf("seed %d: TransOff=0 must never trigger", seed)
		}
		if !transitionTriggers(onePlayerTeam(full), bundle.GameTypeRegular, rng.New(seed)) {
			t.Fatalf("seed %d: TransOff=denom must always trigger", seed)
		}
	}
}

// --- playoff special_sub: trigger threshold is TransOff − 1 ------------------

func TestTransitionTriggers_PlayoffSpecialSub(t *testing.T) {
	// TransOff=1: regular triggers iff the roll lands its minimum (rand_int(1..20)
	// ≤ 1); playoff subtracts 1 → threshold 0 → can NEVER trigger. This isolates
	// the special_sub deterministically.
	p := mkPlayer(1, 7, slotPG, 46)
	p.TransOff = 1

	var regHits, poHits, divergent int
	for seed := uint64(1); seed <= 400; seed++ {
		reg := transitionTriggers(onePlayerTeam(p), bundle.GameTypeRegular, rng.New(seed))
		po := transitionTriggers(onePlayerTeam(p), bundle.GameTypePlayoff, rng.New(seed))
		if reg {
			regHits++
		}
		if po {
			poHits++
		}
		if reg && !po {
			divergent++ // same seed, regular fires but playoff (−1) does not
		}
	}
	if poHits != 0 {
		t.Errorf("playoff special_sub: TransOff=1 must NEVER trigger in playoffs, got %d hits", poHits)
	}
	if regHits == 0 {
		t.Fatal("test setup: regular TransOff=1 never triggered across 400 seeds — cannot demonstrate special_sub")
	}
	if divergent == 0 {
		t.Error("special_sub produced no observable divergence between regular and playoff")
	}
}

// --- matrix #13: Stage-3 decay floors at 2.0 ---------------------------------

func TestTransitionStealSucceeds_DecayFloors(t *testing.T) {
	// blk = 0 makes success deterministic (always true for rate > 0), isolating
	// the decay from the RNG.
	z := mkPlayer(1, 3, slotPG, 50)
	z.BLK = 0
	defense := onePlayerTeam(z)

	gs := &gameState{rng: rng.New(1), period: 1, clock: 500}
	gs.transitionShotRate = 5.0
	want := []float64{3.0, 2.0, 2.0, 2.0, 2.0} // 5→3→floor 2, then stays
	for i, w := range want {
		if !gs.transitionStealSucceeds(defense) {
			t.Fatalf("call %d: expected success with blk=0", i)
		}
		if gs.transitionShotRate != w {
			t.Fatalf("call %d: shot rate = %v, want %v", i, gs.transitionShotRate, w)
		}
		if gs.transitionShotRate < transitionShotRateFloor {
			t.Fatalf("call %d: shot rate fell below floor", i)
		}
	}
}

// --- matrix #14: Stage-3 failure falls back (no transition) ------------------

func TestTransitionStealSucceeds_FailsWhenRateZero(t *testing.T) {
	// rate = 0 with blk > 0: success requires Float64()*blk ≤ 0, effectively
	// never. The shot rate must not decay on a failure.
	big := mkPlayer(1, 3, slotPG, 50)
	big.BLK = 100
	defense := onePlayerTeam(big)
	for seed := uint64(1); seed <= 200; seed++ {
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		gs.transitionShotRate = 0
		if gs.transitionStealSucceeds(defense) {
			t.Fatalf("seed %d: steal-success must fail when rate=0, blk>0", seed)
		}
		if gs.transitionShotRate != 0 {
			t.Fatalf("seed %d: shot rate decayed on a failure", seed)
		}
	}
}

// noTransitionTeams builds offense (TransOff=0, Stage-2 always fails) and a
// normal defense, so possession() always falls through to the half-court loop.
func noTransitionTeams() (*teamState, *teamState) {
	var ps []bundle.Player
	pid := 300
	for slot := slotPG; slot <= slotC; slot++ {
		pid++
		p := mkPlayer(pid, 7, slot, 46)
		p.TransOff = 0
		ps = append(ps, p)
	}
	for slot := slotPG; slot <= slotC; slot++ {
		pid++
		ps = append(ps, mkPlayer(pid, 3, slot, 50))
	}
	return newTeamState(ps, 7, false), newTeamState(ps, 3, true)
}

// classifyEnding reports whether a possession's events ended in a defensive
// rebound or a steal — the two conditions that re-arm the fast-break flag.
func classifyEnding(evs []result.Event) (dreb, steal, made bool) {
	for _, e := range evs {
		switch {
		case e.Kind == result.EventRebound && !e.OffensiveRebound:
			dreb = true
		case e.Kind == result.EventSteal:
			steal = true
		case e.Kind == result.EventShotMake:
			made = true
		}
	}
	return
}

// --- matrix #15/#16/#17: fbNext == (defensive rebound OR steal) --------------

func TestPossession_FastBreakFlagMatchesEnding(t *testing.T) {
	var seenMade, seenDReb, seenSteal bool
	for seed := uint64(1); seed <= 400; seed++ {
		offense, defense := twoTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		fbNext := possession(gs, offense, defense, 0, false)
		dreb, steal, made := classifyEnding(gs.events)
		if want := dreb || steal; fbNext != want {
			t.Fatalf("seed %d: fbNext = %v, want %v (dreb=%v steal=%v)", seed, fbNext, want, dreb, steal)
		}
		switch {
		case steal:
			seenSteal = true // #17
		case dreb:
			seenDReb = true // #16
		case made && !fbNext:
			seenMade = true // #15: a made-shot possession clears the flag
		}
	}
	if !seenMade {
		t.Error("never observed a made-shot possession returning fbNext=false")
	}
	if !seenDReb {
		t.Error("never observed a defensive-rebound possession returning fbNext=true")
	}
	if !seenSteal {
		t.Error("never observed a steal possession returning fbNext=true")
	}
}

// --- matrix #18: pending flag consumed unconditionally on Stage-2 failure -----

func TestPossession_PendingConsumedOnStageTwoFail(t *testing.T) {
	var seenClearedDespitePending bool
	for seed := uint64(1); seed <= 400; seed++ {
		offense, defense := noTransitionTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		// fbPending = true, but TransOff=0 means Stage 2 always fails.
		fbNext := possession(gs, offense, defense, 0, true)
		if gs.transitions != 0 {
			t.Fatalf("seed %d: a transition fired despite TransOff=0", seed)
		}
		dreb, steal, made := classifyEnding(gs.events)
		// The return reflects THIS possession, not the stale input flag.
		if want := dreb || steal; fbNext != want {
			t.Fatalf("seed %d: fbNext = %v, want %v", seed, fbNext, want)
		}
		if made && !fbNext {
			seenClearedDespitePending = true
		}
	}
	if !seenClearedDespitePending {
		t.Error("never observed the pending flag cleared by a made-shot possession")
	}
}
