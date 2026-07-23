package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// TestOriginSharePin_Current pins the CURRENT engine's field-goal-attempt
// origin mix (share of total FGA per origin) to a ±0.02 (2 percentage-point)
// band around the values observed on this sweep. This is a characterization
// pin, not a target: it encodes what the engine does today so a future
// restructure of within-possession shot generation shows up as a test
// failure here rather than silently drifting. Band centers were read off a
// throwaway counting run over the same fixture/seed sweep as
// TestShotOrigin_BySite (origin_test.go) — seeds 1..40 against richBundle(),
// counting result.EventShotAttempt events by e.Origin.
func TestOriginSharePin_Current(t *testing.T) {
	b := richBundle()
	counts := map[result.ShotOrigin]int{}
	total := 0
	for seed := uint64(1); seed <= 40; seed++ {
		g := Simulate(b, seed).Games[0]
		for _, e := range g.Events {
			if e.Kind != result.EventShotAttempt {
				continue
			}
			counts[e.Origin]++
			total++
		}
	}

	if total == 0 {
		t.Fatalf("no field-goal-attempt events observed across the sweep")
	}

	initialShare := float64(counts[result.OriginInitial]) / float64(total)
	orebShare := float64(counts[result.OriginOffReb]) / float64(total)
	transitionShare := float64(counts[result.OriginTransition]) / float64(total)

	const band = 0.02

	// PIN: re-baseline if a future within-possession-generation change moves this
	// Re-baselined for J24 Phase 5 steal-split + nonStealTurnover: 0.721140 -> 0.694298.
	// nonStealTurnover fires before the shot path, removing some initial FGA;
	// transition shots are unaffected (runTransitionPossession doesn't call it),
	// so the transition fraction grows relative to total FGA. Measured seed=1..40.
	//
	// Re-baselined again 2026-07-22 for the putback-3pt revert (possession.go: the
	// OReb-continuation threePtW zeroing was unfaithful — see
	// jsb-native/re-artifacts/jsb-j24-oreb-3pt-eligibility-20260722.md). Putback 3pt
	// attempts now occur, and they convert at a lower rate than putback 2pt, so more
	// putback misses feed further OReb continuations: oreb 0.137482 -> 0.165285, with
	// initial 0.694298 -> 0.680608 and transition 0.141379 -> 0.154107 giving back the
	// share. Only the oreb pin was out of band; all three re-measured seed=1..40 for
	// consistency.
	if math.Abs(initialShare-0.680608) > band {
		t.Errorf("initial share drifted: got %.6f, want %.6f ± %.2f", initialShare, 0.680608, band)
	}
	// PIN: re-baseline if a future within-possession-generation change moves this
	if math.Abs(orebShare-0.165285) > band {
		t.Errorf("oreb_continuation share drifted: got %.6f, want %.6f ± %.2f", orebShare, 0.165285, band)
	}
	// PIN: re-baseline if a future within-possession-generation change moves this
	if math.Abs(transitionShare-0.154107) > band {
		t.Errorf("transition share drifted: got %.6f, want %.6f ± %.2f", transitionShare, 0.154107, band)
	}

	// Permanent invariants (not pinned/re-baselined): the three origin shares
	// must always partition total FGA, and the sweep must have produced
	// attempts at all.
	sum := initialShare + orebShare + transitionShare
	if math.Abs(sum-1.0) > 1e-9 {
		t.Errorf("origin shares do not sum to 1.0: got %.9f", sum)
	}
	if total <= 0 {
		t.Errorf("total FGA count must be > 0, got %d", total)
	}
}
