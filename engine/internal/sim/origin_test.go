package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// validShotOrigins is the closed set every field-goal-attempt event must carry.
var validShotOrigins = map[result.ShotOrigin]bool{
	result.OriginInitial:    true,
	result.OriginOffReb:     true,
	result.OriginTransition: true,
}

func isShotEvent(k result.EventKind) bool {
	return k == result.EventShotAttempt || k == result.EventShotMake || k == result.EventShotMiss
}

// TestShotOrigin_Exhaustive asserts every field-goal event over a full-game
// sweep carries exactly one of the three non-empty origins, and no non-shot
// event carries one. The assertion reads the in-memory struct field (not JSON),
// so the `omitempty` tag cannot mask a missing ("") origin. It also requires all
// three origin values to be observed, proving every tagging path is reachable.
func TestShotOrigin_Exhaustive(t *testing.T) {
	b := richBundle()
	seen := map[result.ShotOrigin]int{}
	for seed := uint64(1); seed <= 40; seed++ {
		g := Simulate(b, seed).Games[0]
		for _, e := range g.Events {
			if !isShotEvent(e.Kind) {
				if e.Origin != "" {
					t.Fatalf("seed %d: non-shot event %s carries origin %q", seed, e.Kind, e.Origin)
				}
				continue
			}
			if !validShotOrigins[e.Origin] {
				t.Fatalf("seed %d: shot event %s has invalid/empty origin %q", seed, e.Kind, e.Origin)
			}
			seen[e.Origin]++
		}
	}
	for o := range validShotOrigins {
		if seen[o] == 0 {
			t.Errorf("origin %q never observed across the sweep — a tagging path is dead or mis-tagged", o)
		}
	}
}

// TestShotOrigin_BySite ties each origin to its generating site: an
// oreb_continuation attempt is always preceded within the same possession by an
// offensive rebound; an initial attempt opens a possession's shot sequence; a
// transition attempt is reachable. All three must occur across the sweep.
func TestShotOrigin_BySite(t *testing.T) {
	b := richBundle()
	var sawInitial, sawOffReb, sawTransition bool
	for seed := uint64(1); seed <= 40; seed++ {
		g := Simulate(b, seed).Games[0]
		offRebSincePossStart := false
		for _, e := range g.Events {
			switch e.Kind {
			case result.EventPossessionStart:
				offRebSincePossStart = false
			case result.EventRebound:
				if e.OffensiveRebound {
					offRebSincePossStart = true
				}
			case result.EventShotAttempt:
				switch e.Origin {
				case result.OriginInitial:
					sawInitial = true
				case result.OriginOffReb:
					sawOffReb = true
					if !offRebSincePossStart {
						t.Fatalf("seed %d: oreb_continuation attempt with no preceding offensive rebound in the possession", seed)
					}
				case result.OriginTransition:
					sawTransition = true
				}
			}
		}
	}
	if !sawInitial || !sawOffReb || !sawTransition {
		t.Fatalf("missing origin coverage: initial=%v offReb=%v transition=%v", sawInitial, sawOffReb, sawTransition)
	}
}
