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
	if math.Abs(initialShare-0.721140) > band {
		t.Errorf("initial share drifted: got %.6f, want %.6f ± %.2f", initialShare, 0.721140, band)
	}
	// PIN: re-baseline if a future within-possession-generation change moves this
	if math.Abs(orebShare-0.137482) > band {
		t.Errorf("oreb_continuation share drifted: got %.6f, want %.6f ± %.2f", orebShare, 0.137482, band)
	}
	// PIN: re-baseline if a future within-possession-generation change moves this
	if math.Abs(transitionShare-0.141379) > band {
		t.Errorf("transition share drifted: got %.6f, want %.6f ± %.2f", transitionShare, 0.141379, band)
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
