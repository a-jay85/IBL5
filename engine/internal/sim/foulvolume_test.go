package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// TestFoulVolumeSiteTriggers table-tests every ported foul-volume predicate at
// its pinned trigger and one step past each pinned bound. The forced foul
// (FUN_004d8570 92991-93003) is the only site that met the J27 port criteria.
func TestFoulVolumeSiteTriggers(t *testing.T) {
	cases := []struct {
		name                       string
		period, clock, offenseLead int
		want                       bool
	}{
		{"forced foul: Q4, 24s, offense up 1", 4, 24, 1, true},
		{"forced foul: overtime, 0s, offense up 3", 5, 0, 3, true},
		{"forced foul: clock one past the bound (25s)", 4, 25, 2, false},
		{"forced foul: lead one past the bound (+4)", 4, 10, 4, false},
		{"forced foul: period one below the bound (Q3)", 3, 10, 2, false},
	}
	for _, c := range cases {
		if got := forcedFoulFires(c.period, c.clock, c.offenseLead); got != c.want {
			t.Errorf("%s: forcedFoulFires(%d, %d, %d) = %v, want %v",
				c.name, c.period, c.clock, c.offenseLead, got, c.want)
		}
	}
	// Made-2pt and-one roll (FUN_004d8570 93919-93926, _DAT_0066c7d8): strict <.
	for _, c := range []struct {
		draw float64
		want bool
	}{{0, true}, {0.0499, true}, {0.05, false}, {0.5, false}} {
		if got := andOneMadeTwoFires(c.draw); got != c.want {
			t.Errorf("andOneMadeTwoFires(%v) = %v, want %v", c.draw, got, c.want)
		}
	}
}

// TestForcedFoulTrigger pins the margin sign of the forced-foul gate: it opens
// only when the offense leads by 1 to 3, which leaves the defense trailing by 1
// to 3 (J17 objdump 0x4d87c6-0x4d87f1). Leading defenses, ties, a margin one
// past either bound, and periods before Q4 stay silent.
func TestForcedFoulTrigger(t *testing.T) {
	for lead := forcedFoulMinLead; lead <= forcedFoulMaxLead; lead++ {
		if !forcedFoulFires(4, 10, lead) {
			t.Errorf("defense trailing by %d in Q4: gate closed, want open", lead)
		}
		if forcedFoulFires(4, 10, -lead) {
			t.Errorf("defense leading by %d in Q4: gate open, want closed", lead)
		}
		for period := 1; period < forcedFoulMinPeriod; period++ {
			if forcedFoulFires(period, 10, lead) {
				t.Errorf("defense trailing by %d in period %d: gate open, want closed", lead, period)
			}
		}
	}
	silent := []struct {
		name string
		lead int
	}{
		{"tied", 0},
		{"one past the upper bound", forcedFoulMaxLead + 1},
		{"one past the lower bound", forcedFoulMinLead - 1},
	}
	for _, s := range silent {
		if forcedFoulFires(4, 10, s.lead) {
			t.Errorf("%s (offense lead %d): gate open, want closed", s.name, s.lead)
		}
	}
}

// andOneBucketOnlyCount is the number of 1-FT trips right after a made 2pt over
// andOneSplitSeeds fixture seeds on the master base (1cadb0416), where every
// such trip came from the outcomeAndOne bucket. The scratch count in
// engine/docs/j27-foul-volume-2-port.md (And-one frequency) recorded it.
const andOneBucketOnlyCount = 1289

// andOneMadeTwoFloor is the count TestAndOneAfterMadeTwo must exceed. It sits
// above the bucket-only base because the new rolls shift the RNG stream: with
// the made-2pt roll disabled (draw < 0) the count is 1255, near the base 1289,
// and with it live the count is 3246.
const andOneMadeTwoFloor = 2000

// TestAndOneAfterMadeTwo requires the made-2pt and-one roll to add 1-FT trips
// on top of the bucket and-ones: the count over the fixture seeds must exceed
// the bucket-only base count.
func TestAndOneAfterMadeTwo(t *testing.T) {
	got := 0
	forEachFixtureGame(t, andOneSplitSeeds, func(events []result.Event) {
		var last result.Event
		for _, e := range events {
			if e.Kind == result.EventFreeThrow && e.FTAttempts == 1 &&
				last.Kind == result.EventShotMake && last.ShotType == result.ShotTwoPoint {
				got++
			}
			if e.Kind != result.EventFoul {
				last = e
			}
		}
	})
	t.Logf("1-FT trips after a made 2 over %d seeds: %d (bucket-only base %d)", andOneSplitSeeds, got, andOneBucketOnlyCount)
	if got <= andOneMadeTwoFloor {
		t.Errorf("1-FT trips after a made 2 = %d, want above %d: the made-2pt roll added no and-ones", got, andOneMadeTwoFloor)
	}
}
