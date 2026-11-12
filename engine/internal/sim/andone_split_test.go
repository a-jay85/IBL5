package sim

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

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
}
