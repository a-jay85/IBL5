package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- J24 Phase 3: steal-transition step class -------------------------------
//
// gameloop.go routes each possession's clock-drain step off the PRIOR
// possession's possOutcome (possession.go): when the prior possession ended in
// a steal (possSteal), THIS possession draws the FAST steal-transition class
// r.IntN(3) ∈ {0,1,2}s instead of the half-court jittered possessionTime()
// draw. These tests drive full games (Simulate) and reconstruct each
// possession's step from consecutive EventPossessionStart clock values, so
// they exercise the real gameloop.go routing rather than re-implementing it.

// possessionSegment is one possession's slice of the event stream: the events
// from its EventPossessionStart (exclusive) up to (but not including) the next
// possession's EventPossessionStart, or the period boundary.
type possessionSegment struct {
	period int
	clock  int // gs.clock AT the possession's start (seconds remaining)
	events []result.Event
}

// possessionSegments splits a game's event stream into per-possession
// segments, grouping by period so a step is only computed between two
// possessions in the SAME period (a period boundary is not a possession step).
func possessionSegments(events []result.Event) []possessionSegment {
	var segs []possessionSegment
	for _, e := range events {
		if e.Kind == result.EventPossessionStart {
			segs = append(segs, possessionSegment{period: e.Period, clock: e.Clock})
			continue
		}
		if len(segs) == 0 {
			continue
		}
		segs[len(segs)-1].events = append(segs[len(segs)-1].events, e)
	}
	return segs
}

// segmentOutcome maps a possession segment's terminal event to the possOutcome
// it must have returned, mirroring classifyEnding (transition_test.go) but
// resolved to the 3-valued type this phase introduces.
func segmentOutcome(evs []result.Event) possOutcome {
	dreb, steal, _ := classifyEnding(evs)
	switch {
	case steal:
		return possSteal
	case dreb:
		return possDRB
	default:
		return possNormal
	}
}

// TestStealClassStepRange drives many seeded full games and checks every
// possession immediately following a steal-ending possession (same period)
// drains the clock by a step in {0,1,2} — the FAST steal-transition class.
//
// One-iteration offset (matches gameloop.go): segs[i] ending in a steal arms
// possSteal as the `prev` INPUT to segs[i+1]'s possession() call, and it is
// THAT value which selects segs[i+1]'s OWN step (clock[i+1]-clock[i+2]) — not
// segs[i]'s step. So the fast-class check below is offset by one segment from
// the steal-ending segment.
func TestStealClassStepRange(t *testing.T) {
	b := richBundle()
	var stealFollowSteps int
	stepCounts := map[int]int{}
	for seed := uint64(1); seed <= 60; seed++ {
		g := Simulate(b, seed).Games[0]
		segs := possessionSegments(g.Events)
		for i := 0; i+2 < len(segs); i++ {
			if segs[i].period != segs[i+1].period || segs[i+1].period != segs[i+2].period {
				continue // step is undefined across a period boundary
			}
			if segmentOutcome(segs[i].events) != possSteal {
				continue
			}
			// segs[i] ended in a steal -> segs[i+1] runs with fbPending=true and
			// its OWN step (drained after it resolves) is the fast class.
			step := segs[i+1].clock - segs[i+2].clock
			stealFollowSteps++
			stepCounts[step]++
			if step < 0 || step > 2 {
				t.Fatalf("seed %d: possession following a steal drained %ds, want in {0,1,2}", seed, step)
			}
		}
	}
	if stealFollowSteps == 0 {
		t.Fatal("no steal-followed possessions observed over the seed sweep")
	}
	// The support should show real mass at more than one value across enough
	// draws — otherwise the fast class isn't actually the r.IntN(3) draw.
	if len(stepCounts) < 2 {
		t.Errorf("steal-class step support too narrow: %v (want mass at multiple of {0,1,2})", stepCounts)
	}
	t.Logf("steal-followed possession step distribution over %d seeds: %v", 60, stepCounts)
}

// --- J24 Phase 4: DRB-push step class ---------------------------------------
//
// A possession following a DRB-ending possession, WHEN the shared Stage-2
// gate fires (captured into gs.drbPushFired by possession.go's fbPending
// branch — see transition_test.go's unit-level gate tests for the flag-only
// assertions), draws r.IntN(3)+2 ∈ {2,3,4}s instead of the half-court
// jittered step. Step 2 is UNAMBIGUOUS evidence the push class fired — it is
// below the half-court jitter's redraw floor of 3 (possession_pace_pin_test.go
// Pin A), so it cannot arise from the gate-fail path. Steps 3-4 are ambiguous
// (both supports include them), so this test does not attempt to attribute
// every {3,4} to one class or the other — see the required unit-level
// flag-only assertions in transition_test.go for that.
//
// Upper bound WIDENED for the J24 Phase 5 NO-GO re-center (baseTimeMid
// 13.65 -> 17.7, tempo.go): the half-court jitter's own support widened to
// [3,27] (Pin A, possession_pace_pin_test.go), so the union with the push
// class's {2,3,4} is now [2,27], not [2,23].
func TestDRBPushClassStepRange(t *testing.T) {
	b := richBundle()
	var drbFollowSteps int
	stepCounts := map[int]int{}
	for seed := uint64(1); seed <= 60; seed++ {
		g := Simulate(b, seed).Games[0]
		segs := possessionSegments(g.Events)
		for i := 0; i+2 < len(segs); i++ {
			if segs[i].period != segs[i+1].period || segs[i+1].period != segs[i+2].period {
				continue // step is undefined across a period boundary
			}
			if segmentOutcome(segs[i].events) != possDRB {
				continue
			}
			// segs[i] ended in a defensive rebound -> segs[i+1] runs with
			// fbPending=true and its OWN step (drained after it resolves) is
			// either the DRB-push class (gate fired) or the half-court jitter
			// (gate failed) — see the one-iteration-offset note above
			// TestStealClassStepRange, which applies identically here.
			step := segs[i+1].clock - segs[i+2].clock
			drbFollowSteps++
			stepCounts[step]++
			if step < 2 || step > 27 {
				t.Fatalf("seed %d: possession following a DRB drained %ds, want in [2,27] (union of push {2,3,4} and half-court [3,27])", seed, step)
			}
		}
	}
	if drbFollowSteps == 0 {
		t.Fatal("no DRB-followed possessions observed over the seed sweep")
	}
	t.Logf("DRB-followed possession step distribution over %d seeds: %v", 60, stepCounts)
	// Unambiguous push evidence: step==2 can ONLY come from the push class
	// (r.IntN(3)+2), never from the half-court jitter (floor 3). Its presence
	// at material mass confirms the push class is actually reachable and
	// firing through the real gameloop routing, not just in the isolated
	// unit-level gate tests.
	if stepCounts[2] == 0 {
		t.Error("never observed step==2 following a DRB-ended possession — the DRB-push class ({2,3,4}s) appears unreachable through gameloop.go's routing")
	}
	// Gate-fail evidence: steps beyond the push class's ceiling (>4) can only
	// come from the half-court jitter, confirming the gate-fail path is also
	// live (transitionTriggerDenom=18 means Stage-2 fails often even for
	// reasonably-rated TransOff, so both paths should show real mass).
	var sawBeyondPush bool
	for step := range stepCounts {
		if step > 4 {
			sawBeyondPush = true
			break
		}
	}
	if !sawBeyondPush {
		t.Error("never observed a step >4 following a DRB-ended possession — the gate-fail half-court path appears unreachable")
	}
}

// TestPossession_MadeShotReturnsNormal_DRBReturnsNotSteal is the Phase-3
// negative-path pin: a made-shot ending must return possNormal (no break
// armed), and a clean defensive-rebound ending must return possDRB — NOT
// possSteal. This distinguishes the two arming endings the old bool fbNext
// collapsed together.
func TestPossession_MadeShotReturnsNormal_DRBReturnsNotSteal(t *testing.T) {
	var seenMadeNormal, seenDRBNotSteal bool
	for seed := uint64(1); seed <= 400; seed++ {
		offense, defense := twoTeams()
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		outcome := possession(gs, offense, defense, 0, possNormal)
		dreb, steal, made := classifyEnding(gs.events)
		switch {
		case made && !dreb && !steal:
			if outcome != possNormal {
				t.Fatalf("seed %d: made-shot ending returned %v, want possNormal", seed, outcome)
			}
			seenMadeNormal = true
		case dreb:
			if outcome != possDRB {
				t.Fatalf("seed %d: defensive-rebound ending returned %v, want possDRB", seed, outcome)
			}
			if outcome == possSteal {
				t.Fatalf("seed %d: defensive-rebound ending returned possSteal", seed)
			}
			seenDRBNotSteal = true
		}
	}
	if !seenMadeNormal {
		t.Error("never observed a made-shot possession returning possNormal")
	}
	if !seenDRBNotSteal {
		t.Error("never observed a defensive-rebound possession returning possDRB (not possSteal)")
	}
}

// TestFullGameTerminationWithStealFastClass drives a wide seed sweep of full
// games through Simulate and asserts every one completes: step==0 is
// reachable under the steal class (gs.clock -= 0 is a no-op), but the loop
// still terminates because a chain of steal-armed step==0 possessions cannot
// recur unboundedly — each fast-break possession either scores, turns the
// ball over some other way, or ends in a fresh (non-guaranteed) steal/DRB,
// and the NEXT possession's step is drawn fresh from that new outcome. A
// genuine unbounded loop would hang this test (Go's default test timeout, no
// per-call bound needed here).
func TestFullGameTerminationWithStealFastClass(t *testing.T) {
	b := richBundle()
	for seed := uint64(1); seed <= 100; seed++ {
		res := Simulate(b, seed)
		if len(res.Games) == 0 {
			t.Fatalf("seed %d: no games produced", seed)
		}
		g := res.Games[0]
		var sawFinalBoundary bool
		for _, e := range g.Events {
			if e.Kind == result.EventPeriodBoundary {
				sawFinalBoundary = true
			}
		}
		if !sawFinalBoundary {
			t.Fatalf("seed %d: game produced no period-boundary event — did a period fail to terminate?", seed)
		}
		if len(g.TeamBoxes) != 2 {
			t.Fatalf("seed %d: expected 2 team boxes, got %d", seed, len(g.TeamBoxes))
		}
	}
}
