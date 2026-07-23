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

// --- matrix #10: transition 3pt gate — faithful port and suppress arm ---------
//
// Positive: the faithful default (SuppressTransition3pt=false) must produce at
// least one 3pt attempt over many seeds — the gate is open.
// Negative: with SuppressTransition3pt=true the suppress arm zeroes threePtW
// before selectOutcome, so transition 3pt must remain unreachable.

func TestRunTransitionPossession_ThreePtReachableAndSuppressable(t *testing.T) {
	var attempts, threes, threesSupp int
	for seed := uint64(1); seed <= 300; seed++ {
		offense, defense := twoTeams()

		// Default arm: faithful port — 3pt is eligible on fast breaks.
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		gs.transitionShotRate = resetTransitionShotRate(offense)
		gs.runTransitionPossession(offense, defense, 0)
		for _, e := range gs.events {
			if e.Kind == result.EventShotAttempt {
				attempts++
				if e.ShotType == result.ShotThree {
					threes++
				}
			}
		}

		// Suppress arm: SuppressTransition3pt=true must block all transition 3pt.
		gs2 := &gameState{rng: rng.New(seed), period: 1, clock: 500, freeze: FreezeConfig{SuppressTransition3pt: true}}
		gs2.transitionShotRate = resetTransitionShotRate(offense)
		gs2.runTransitionPossession(offense, defense, 0)
		for _, e := range gs2.events {
			if e.Kind == result.EventShotAttempt && e.ShotType == result.ShotThree {
				threesSupp++
			}
		}
	}
	if attempts == 0 {
		t.Fatal("transition possessions produced no shot attempts")
	}
	if threes == 0 {
		t.Errorf("faithful default produced 0 transition 3pt attempts over 300 seeds — gate appears closed")
	}
	if threesSupp != 0 {
		t.Errorf("suppress arm produced %d transition 3pt attempts, want 0", threesSupp)
	}
}

// --- matrix #6: the transition foul bucket is SIDE-SYMMETRIC (J6/J16) ----------
//
// The transition possession path is the SECOND of the two outcomeInputs assembly
// sites. The faithful foul bucket carries NO home/away term (J16 §3: "no home/away
// term anywhere in the function or its inputs") — HCA lives at site-2, not here.
// So with identical-rated teams a HOME offense and an AWAY offense must draw the
// SAME free throws over many fast breaks: the only difference between the two cases
// is offense.isHome, and the foul bucket is blind to it. This guards against a
// regression that re-introduces the refuted ADR-0082 home-grows-the-bucket asymmetry.
func TestRunTransitionPossession_FoulBucketSideSymmetric(t *testing.T) {
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

	// Same ratings, same RNG seeds, side-symmetric bucket ⇒ the home/away FTA split
	// must be tight. Allow a small band for the independent RNG streams (home vs away
	// draw from the same seeds but different team-id shot decisions upstream); a real
	// home>away asymmetry would blow well past this.
	t.Logf("transition FTA over 3000 fast breaks: home=%d away=%d (home−away=%+d)", homeFTA, awayFTA, homeFTA-awayFTA)
	diff := homeFTA - awayFTA
	if diff < 0 {
		diff = -diff
	}
	total := homeFTA + awayFTA
	if total == 0 {
		t.Fatal("transition fast breaks produced no free throws")
	}
	if frac := float64(diff) / float64(total); frac > 0.05 {
		t.Errorf("home=%d away=%d — |home−away|/total = %.3f > 0.05; foul bucket is not side-symmetric", homeFTA, awayFTA, frac)
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

// --- matrix #15/#16/#17: outcome == possSteal/possDRB/possNormal by ending ----
//
// J24 Phase 3: possession() now returns a 3-valued possOutcome instead of a bool,
// so a steal-ending and a defensive-rebound-ending are distinguishable (Phase 3
// routes possSteal to the fast steal-transition step class; Phase 4 will route
// possDRB). This asserts the exact mapping — a clean defensive rebound must
// return possDRB, NOT possSteal, and vice versa.
func TestPossession_FastBreakFlagMatchesEnding(t *testing.T) {
	var seenMade, seenDReb, seenSteal bool
	for seed := uint64(1); seed <= 400; seed++ {
		offense, defense := twoTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500, stealTurnoverScale: stealTurnoverScale, nonStealTurnoverScale: nonStealTurnoverScale}
		outcome := possession(gs, offense, defense, 0, possNormal)
		dreb, steal, made := classifyEnding(gs.events)
		var want possOutcome
		switch {
		case steal:
			want = possSteal
		case dreb:
			want = possDRB
		default:
			want = possNormal
		}
		if outcome != want {
			t.Fatalf("seed %d: outcome = %v, want %v (dreb=%v steal=%v)", seed, outcome, want, dreb, steal)
		}
		switch {
		case steal:
			seenSteal = true // #17
		case dreb:
			seenDReb = true // #16
		case made && outcome == possNormal:
			seenMade = true // #15: a made-shot possession clears the flag
		}
	}
	if !seenMade {
		t.Error("never observed a made-shot possession returning possNormal")
	}
	if !seenDReb {
		t.Error("never observed a defensive-rebound possession returning possDRB")
	}
	if !seenSteal {
		t.Error("never observed a steal possession returning possSteal")
	}
}

// --- matrix #18: pending flag consumed unconditionally on Stage-2 failure -----

func TestPossession_PendingConsumedOnStageTwoFail(t *testing.T) {
	var seenClearedDespitePending bool
	for seed := uint64(1); seed <= 400; seed++ {
		offense, defense := noTransitionTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		// fbPending = true (prev == possSteal), but TransOff=0 means Stage 2 always fails.
		outcome := possession(gs, offense, defense, 0, possSteal)
		if gs.transitions != 0 {
			t.Fatalf("seed %d: a transition fired despite TransOff=0", seed)
		}
		dreb, steal, made := classifyEnding(gs.events)
		// The return reflects THIS possession, not the stale input flag.
		var want possOutcome
		switch {
		case steal:
			want = possSteal
		case dreb:
			want = possDRB
		default:
			want = possNormal
		}
		if outcome != want {
			t.Fatalf("seed %d: outcome = %v, want %v", seed, outcome, want)
		}
		if made && outcome == possNormal {
			seenClearedDespitePending = true
		}
	}
	if !seenClearedDespitePending {
		t.Error("never observed the pending flag cleared by a made-shot possession")
	}
}

// --- J24 Phase 4: DRB-push clock gate (gs.drbPushFired) ----------------------
//
// possession.go's fbPending branch draws transitionTriggers exactly ONCE and,
// when prev == possDRB, captures the result into gs.drbPushFired for
// gameloop.go to route the {2,3,4}s DRB-push step class (state.go's
// drbPushFired field, gameloop.go's step switch). These tests drive
// possession() directly with a rigged TransOff so the Stage-2 gate is
// deterministic (mirroring TestTransitionTriggers_Boundary), isolating the
// flag from Stage-3 (transitionStealSucceeds) and from the step-value overlap
// between the DRB-push support {2,3,4} and the half-court jitter's support
// [3,27] (widened from [3,23] by the J24 Phase 5 NO-GO baseTimeMid re-center,
// 13.65 -> 17.7 — see possession_pace_pin_test.go Pin A) — asserting on
// gs.drbPushFired directly sidesteps that overlap.

// alwaysTransitionTeams builds offense (TransOff=transitionTriggerDenom,
// Stage-2 always fires) and a normal defense.
func alwaysTransitionTeams() (*teamState, *teamState) {
	var ps []bundle.Player
	pid := 400
	for slot := slotPG; slot <= slotC; slot++ {
		pid++
		p := mkPlayer(pid, 7, slot, 46)
		p.TransOff = transitionTriggerDenom
		ps = append(ps, p)
	}
	for slot := slotPG; slot <= slotC; slot++ {
		pid++
		ps = append(ps, mkPlayer(pid, 3, slot, 50))
	}
	return newTeamState(ps, 7, false), newTeamState(ps, 3, true)
}

// TestDRBPushGate_FiresSetsFlag is required assertion (a)'s unit-level half:
// prev == possDRB with a deterministically-firing Stage-2 gate must set
// gs.drbPushFired true. (The gameloop-level half — that the drawn step then
// lands in {2,3,4} over a real game — is TestDRBPushClassStepRange below.)
func TestDRBPushGate_FiresSetsFlag(t *testing.T) {
	for seed := uint64(1); seed <= 300; seed++ {
		offense, defense := alwaysTransitionTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		possession(gs, offense, defense, 0, possDRB)
		if !gs.drbPushFired {
			t.Fatalf("seed %d: TransOff=denom + prev=possDRB must always set drbPushFired", seed)
		}
	}
}

// TestDRBPushGate_FailsClearsFlag is required assertion (b): a Stage-2
// gate-fail (TransOff=0) with prev == possDRB must leave gs.drbPushFired
// false, asserted on the flag itself — NOT on the drawn step value, since the
// DRB-push support {2,3,4} overlaps the half-court jitter's support [3,27] at
// steps 3-4 (a purely observational split would be ambiguous there).
func TestDRBPushGate_FailsClearsFlag(t *testing.T) {
	for seed := uint64(1); seed <= 300; seed++ {
		offense, defense := noTransitionTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		possession(gs, offense, defense, 0, possDRB)
		if gs.drbPushFired {
			t.Fatalf("seed %d: TransOff=0 must never set drbPushFired", seed)
		}
	}
}

// TestDRBPushGate_OnlyArmsOnDRBPrev confirms the flag is scoped to
// prev == possDRB specifically: a fast-break-eligible possession armed by a
// STEAL (prev == possSteal), even with a deterministically-firing Stage-2
// gate, must never set gs.drbPushFired — that flag is reserved for the
// DRB-push class, not the steal-transition class (which routes off
// prevOutcome == possSteal directly in gameloop.go, no flag needed).
func TestDRBPushGate_OnlyArmsOnDRBPrev(t *testing.T) {
	for seed := uint64(1); seed <= 300; seed++ {
		offense, defense := alwaysTransitionTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		possession(gs, offense, defense, 0, possSteal)
		if gs.drbPushFired {
			t.Fatalf("seed %d: prev=possSteal must never set drbPushFired even when Stage-2 always fires", seed)
		}
	}
}

// TestDRBPushGate_ResetsAcrossPossessions is required assertion (c): after a
// possession sets gs.drbPushFired true, the NEXT possession — armed normally
// (prev == possNormal), so fbPending is false and the fbPending branch never
// runs — must leave the flag false (the top-of-possession reset in
// possession.go), not carry the stale true forward into a possession that
// will draw the half-court step.
func TestDRBPushGate_ResetsAcrossPossessions(t *testing.T) {
	for seed := uint64(1); seed <= 300; seed++ {
		offense, defense := alwaysTransitionTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		possession(gs, offense, defense, 0, possDRB)
		if !gs.drbPushFired {
			t.Fatalf("seed %d: setup failed — drbPushFired not set by the first (DRB-armed) possession", seed)
		}
		possession(gs, offense, defense, 0, possNormal)
		if gs.drbPushFired {
			t.Fatalf("seed %d: drbPushFired leaked true into a possNormal-armed possession (fbPending branch never runs)", seed)
		}
	}
}
