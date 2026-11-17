package sim

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// andOneAwayBeforeBaseline is the away and-one count over andOneSplitSeeds
// seeds measured on the master base, where the engine floored the weight and
// then added the HCA term.
const andOneAwayBeforeBaseline = 34

// andOneSplitSeeds is the number of fixture seeds TestAndOneHomeAwaySplit
// simulates when it counts and-one free-throw trips.
const andOneSplitSeeds = 500

// countAndOnes simulates the golden fixture bundle over seeds 1..seeds and
// tallies and-one trips from the event stream. It builds each game the way
// TestTeamFoulBonusEventStream does and reads the event slice the way
// aggregateBoxes receives it. An and-one is an EventFreeThrow whose
// FTAttempts is 1. home and away count those trips by the shooting TeamID.
// misCredited counts and-one FT events whose TeamID differs from the TeamID of
// the most recent made field goal before it.
func countAndOnes(t *testing.T, seeds int) (home, away, misCredited int) {
	t.Helper()
	in, err := os.ReadFile(filepath.Join("testdata", "bundle.json"))
	if err != nil {
		t.Fatalf("read fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode fixture: %v", err)
	}
	for s := 1; s <= seeds; s++ {
		for _, g := range Simulate(b, b.Seed+uint64(s)).Games {
			lastMakeTeam := 0
			for _, e := range g.Events {
				switch e.Kind {
				case result.EventShotMake:
					lastMakeTeam = e.TeamID
				case result.EventFreeThrow:
					if e.FTAttempts != 1 {
						continue
					}
					switch e.TeamID {
					case g.HomeTeamID:
						home++
					case g.VisitorTeamID:
						away++
					}
					if e.TeamID != lastMakeTeam {
						misCredited++
					}
				}
			}
		}
	}
	return home, away, misCredited
}

// TestAndOneHomeAwaySplit logs the home, away and misCredited and-one counts
// over the fixture seeds and requires at least one and-one, so the split
// assertions added by the and-one verdict cannot pass vacuously.
func TestAndOneHomeAwaySplit(t *testing.T) {
	home, away, misCredited := countAndOnes(t, andOneSplitSeeds)
	t.Logf("and-ones over %d seeds: home=%d away=%d misCredited=%d", andOneSplitSeeds, home, away, misCredited)
	if home+away == 0 {
		t.Fatal("no and-one free-throw trips found; the split check would pass vacuously")
	}
	if misCredited != 0 {
		t.Errorf("%d and-one FT trips credited to a team other than the one that made the basket", misCredited)
	}
	// Q3 verdict UNFAITHFUL-ORDER (FUN_004e1ba0 97158-97168): the HCA term joins
	// the raw weight before the floor, so away and-ones rise above the BEFORE
	// baseline, where the floor came first and the away weight always pinned to
	// the floor. No home/away ratio band is asserted: the engine's and-one basis
	// is far below JSB's, so the ratio stays large without retuning (ADR-0158).
	if away <= andOneAwayBeforeBaseline {
		t.Errorf("away and-ones %d did not rise above the BEFORE baseline %d", away, andOneAwayBeforeBaseline)
	}
}

// TestAndOneWeightOrder pins the JSB order of the half-court and-one weight:
// the HCA addend joins the raw weight first and the floor applies to the sum
// (FUN_004e1ba0 97158-97168).
func TestAndOneWeightOrder(t *testing.T) {
	var p onCourt
	p.FGP = 50
	raw := andOneBucketRaw(0, p)
	away := -hcaMagnitude * hcaSite2BasisScale
	if raw+away >= andOneBucketFloor {
		t.Fatalf("fixture: raw %v plus away HCA %v must fall below the floor", raw, away)
	}
	if got := halfCourtAndOneWeight(0, p, away); got != andOneBucketFloor {
		t.Errorf("below the floor: got %v, want exactly %v", got, andOneBucketFloor)
	}
	home := hcaMagnitude * hcaSite2BasisScale
	if got, want := halfCourtAndOneWeight(0, p, home), raw+home; got != want {
		t.Errorf("above the floor: got %v, want raw plus HCA %v", got, want)
	}
	if got := andOneBucketWeight(0, p); got != raw {
		t.Errorf("andOneBucketWeight above the floor: got %v, want raw %v", got, raw)
	}
	if got := andOneBucketWeight(-1, p); got != andOneBucketFloor {
		t.Errorf("andOneBucketWeight below the floor: got %v, want %v", got, andOneBucketFloor)
	}
}
