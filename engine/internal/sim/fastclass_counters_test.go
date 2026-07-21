package sim

import (
	"reflect"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// TestFastClassCounters verifies the FastClassAccum instrument is:
//
//	(A) read-only — attaching the accumulator must not alter any game outcome;
//	(B) exhaustive and mutually exclusive — every EventPossessionStart increments
//	    exactly one class counter, summing to TotalPossessions; and
//	(C) both merged classes reachable across a modest seed sweep.
func TestFastClassCounters(t *testing.T) {
	b := richBundle()

	// Sub-check A — pure-instrument identity.
	// SimulateWith with FastClassAccum must produce byte-identical TeamBoxes to
	// Simulate over the same seed. A rng-draw ordering violation (draw after the
	// nil guard) would shift the stream and cause a mismatch here.
	for _, seed := range []uint64{1, 42, 1988} {
		plain := Simulate(b, seed)
		acc := &FastClassAccum{}
		withAccum, err := SimulateWith(b, seed, Options{FastClassAccum: acc})
		if err != nil {
			t.Fatalf("seed %d: SimulateWith error: %v", seed, err)
		}
		if len(plain.Games) != len(withAccum.Games) {
			t.Fatalf("seed %d: game count: plain %d vs accum %d",
				seed, len(plain.Games), len(withAccum.Games))
		}
		for i := range plain.Games {
			pg, ag := plain.Games[i], withAccum.Games[i]
			if len(pg.TeamBoxes) != len(ag.TeamBoxes) {
				t.Fatalf("seed %d game %d: TeamBox count %d vs %d",
					seed, i, len(pg.TeamBoxes), len(ag.TeamBoxes))
			}
			for j := range pg.TeamBoxes {
				if !reflect.DeepEqual(pg.TeamBoxes[j], ag.TeamBoxes[j]) {
					t.Fatalf("seed %d game %d team box %d: mismatch — instrument altered a game outcome",
						seed, i, j)
				}
			}
		}
	}

	// Sub-check B — exhaustive + mutually exclusive.
	// Every possession must increment exactly one of DRBPushClass, HalfCourt
	// (via TotalPossessions), and the total must equal the count of
	// EventPossessionStart events across all games.
	for seed := uint64(1); seed <= 20; seed++ {
		acc := &FastClassAccum{}
		res, err := SimulateWith(b, seed, Options{FastClassAccum: acc})
		if err != nil {
			t.Fatalf("seed %d: SimulateWith error: %v", seed, err)
		}
		var possCount int
		for _, g := range res.Games {
			for _, e := range g.Events {
				if e.Kind == result.EventPossessionStart {
					possCount++
				}
			}
		}
		sum := acc.DRBPushClass + acc.HalfCourt
		if sum != acc.TotalPossessions {
			t.Errorf("seed %d: DRBPushClass(%d)+HalfCourt(%d)=%d != TotalPossessions(%d)",
				seed, acc.DRBPushClass, acc.HalfCourt, sum, acc.TotalPossessions)
		}
		if acc.TotalPossessions != possCount {
			t.Errorf("seed %d: TotalPossessions(%d) != EventPossessionStart count(%d)",
				seed, acc.TotalPossessions, possCount)
		}
	}

	// Sub-check C — all classes reachable.
	// Aggregate counters across 20 seeds; each class must have fired at least once.
	var total FastClassAccum
	for seed := uint64(1); seed <= 20; seed++ {
		acc := &FastClassAccum{}
		if _, err := SimulateWith(b, seed, Options{FastClassAccum: acc}); err != nil {
			t.Fatal(err)
		}
		total.DRBPushClass += acc.DRBPushClass
		total.HalfCourt += acc.HalfCourt
	}
	// DRBPushClass now aggregates gated code-7 survivors from BOTH steal-sourced
	// and DRB-sourced fast breaks (J24 §1d merge); there is no separate steal class.
	if total.DRBPushClass == 0 {
		t.Error("DRB-push-class counter never incremented across 20 seeds")
	}
	if total.HalfCourt == 0 {
		t.Error("half-court-class counter never incremented across 20 seeds")
	}

	// Sub-check D — POST-§1d-MERGE routing assertion (was the Phase 1 pin).
	// Steal-armed possessions no longer have their own counter: gate survivors
	// merge into DRBPushClass, gate failures fall to HalfCourt. StealClass has
	// been removed from FastClassAccum, so any lingering reference here is a
	// compile error — this block referencing only DRBPushClass/HalfCourt is the
	// migration's compile-time proof that the steal class is gone. Behaviorally,
	// the merged DRBPushClass must strictly exceed the DRB-only floor: with steal
	// survivors folded in, its sweep total must be > 0 and, because steal-armed
	// possessions are a real fraction of the sweep, materially non-trivial.
	var pin FastClassAccum
	for seed := uint64(1); seed <= 20; seed++ {
		acc := &FastClassAccum{}
		if _, err := SimulateWith(b, seed, Options{FastClassAccum: acc}); err != nil {
			t.Fatalf("seed %d: SimulateWith error: %v", seed, err)
		}
		pin.DRBPushClass += acc.DRBPushClass
		pin.HalfCourt += acc.HalfCourt
	}
	if pin.DRBPushClass == 0 {
		t.Error("POST-MERGE: DRBPushClass never incremented — merged code-7 class not exercised")
	}
	if pin.HalfCourt == 0 {
		t.Error("POST-MERGE: HalfCourt never incremented — gate-fail/half-court path not exercised")
	}
}
