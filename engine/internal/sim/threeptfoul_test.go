package sim

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// forEachFixtureGame simulates the golden fixture bundle over seeds 1..seeds,
// the way countAndOnes builds each game, and hands fn every game's events.
func forEachFixtureGame(t *testing.T, seeds int, fn func(events []result.Event)) {
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
			fn(g.Events)
		}
	}
}

// lastNonFoul returns the nearest event before index i that is not an
// EventFoul, and false when there is none.
func lastNonFoul(events []result.Event, i int) (result.Event, bool) {
	for j := i - 1; j >= 0; j-- {
		if events[j].Kind != result.EventFoul {
			return events[j], true
		}
	}
	return result.Event{}, false
}

// TestThreePtFoulFires pins the strict < on _DAT_00669e80 (94054-94060 and
// 94147-94148).
func TestThreePtFoulFires(t *testing.T) {
	for _, c := range []struct {
		draw float64
		want bool
	}{{0, true}, {0.0099, true}, {0.01, false}, {0.5, false}} {
		if got := threePtFoulFires(c.draw); got != c.want {
			t.Errorf("threePtFoulFires(%v) = %v, want %v", c.draw, got, c.want)
		}
	}
}

// threePtTestState builds a bare gameState and two richBundle teams for
// direct threePtShot calls.
func threePtTestState(seed uint64) (*gameState, *teamState, *teamState) {
	b := richBundle()
	offense := newTeamState(b.Players, 7, false)
	defense := newTeamState(b.Players, 3, true)
	return &gameState{rng: rng.New(seed), period: 1, clock: 600}, offense, defense
}

// fourPointTrials is how many guaranteed-make 3s TestThreePtFourPointPlay
// shoots. The golden fixture makes about 5 threes over 500 seeds, too few for
// a 1% roll, so the test drives threePtShot directly with a shot value above
// every rand_int(1000) roll.
const fourPointTrials = 5000

// TestThreePtFourPointPlay requires the made-3 roll (94054-94060) to produce
// 1-FT trips right after a made 3 by the same shooter, and keeps them at or
// under 3% of made 3s so the roll stays a 1% event.
func TestThreePtFourPointPlay(t *testing.T) {
	gs, offense, defense := threePtTestState(1)
	shooter, defender := offense.players[0], defense.players[0]
	for i := 0; i < fourPointTrials; i++ {
		if res := gs.threePtShot(offense, defense, shooter, defender, 2000, result.OriginInitial, 0); res != threePtMade {
			t.Fatalf("trial %d: threePtShot = %v with shot value 2000, want threePtMade", i, res)
		}
	}
	made3, fourPoint := 0, 0
	for i, e := range gs.events {
		switch e.Kind {
		case result.EventShotMake:
			if e.ShotType == result.ShotThree {
				made3++
			}
		case result.EventFreeThrow:
			if e.FTAttempts != 1 {
				t.Errorf("free-throw trip with %d attempts after a made 3, want 1", e.FTAttempts)
				continue
			}
			prev, ok := lastNonFoul(gs.events, i)
			if ok && prev.Kind == result.EventShotMake && prev.ShotType == result.ShotThree && prev.PlayerID == e.PlayerID {
				fourPoint++
			}
		}
	}
	t.Logf("four-point plays: %d of %d made 3s", fourPoint, made3)
	if made3 != fourPointTrials {
		t.Fatalf("made 3s = %d, want %d", made3, fourPointTrials)
	}
	if fourPoint == 0 {
		t.Fatal("no four-point play after a made 3; the made-3 roll never fired")
	}
	if fourPoint*100 > made3*3 {
		t.Errorf("four-point plays = %d of %d made 3s, above 3%%", fourPoint, made3)
	}
}

// TestThreePtMissFoulShootsThree checks the missed-3 roll (94102-94150) over
// the fixture seeds: fouled 3s shoot 3-FT trips, book no FGA, and never
// follow a block. A seed-scanned direct case pins that a blocked miss never
// rolls the foul.
func TestThreePtMissFoulShootsThree(t *testing.T) {
	trips := 0
	forEachFixtureGame(t, andOneSplitSeeds, func(events []result.Event) {
		for i, e := range events {
			if e.Kind != result.EventFreeThrow || e.FTAttempts != 3 {
				continue
			}
			trips++
			prev, ok := lastNonFoul(events, i)
			if ok && prev.PlayerID == e.PlayerID && prev.ShotType == result.ShotThree &&
				(prev.Kind == result.EventShotAttempt || prev.Kind == result.EventShotMiss) {
				t.Errorf("3-FT trip by %d follows its own 3pt %v event: the fouled miss booked an FGA", e.PlayerID, prev.Kind)
			}
			if i > 1 && events[i-1].Kind == result.EventFoul && events[i-2].Kind == result.EventBlock {
				t.Errorf("3-FT trip by %d follows a block: a blocked miss rolled the foul", e.PlayerID)
			}
		}
	})
	t.Logf("3-FT trips over %d seeds: %d", andOneSplitSeeds, trips)
	if trips == 0 {
		t.Fatal("no 3-FT trip found; the fouled missed 3 never fired")
	}

	// A blocked miss never rolls the foul. Scan seeds for one whose draws give
	// a miss (shot value 0), a block, and then a draw under threePtFoulCut, so
	// the foul would fire if the block gate were gone.
	_, offense0, defense0 := threePtTestState(1)
	shooter, defender := offense0.players[0], defense0.players[0]
	defender.BLK = int(blockRatingScale)
	defender.fatigue = 1
	pBlock := blockProbability(defender, 0)
	seed := uint64(0)
	for s := uint64(1); s <= 200000; s++ {
		ref := rng.New(s)
		ref.IntN(1000) // make roll (always a miss at shot value 0)
		if ref.Float64() < pBlock && threePtFoulFires(ref.Float64()) {
			seed = s
			break
		}
	}
	if seed == 0 {
		t.Fatal("no seed gives a blocked miss followed by a foul-cut draw")
	}
	gs, offense, defense := threePtTestState(seed)
	res := gs.threePtShot(offense, defense, shooter, defender, 0, result.OriginInitial, 0)
	if res != threePtMissed {
		t.Errorf("blocked miss (seed %d): threePtShot = %v, want threePtMissed", seed, res)
	}
	for _, e := range gs.events {
		if e.Kind == result.EventFreeThrow || e.Kind == result.EventFoul {
			t.Errorf("blocked miss (seed %d) emitted %v: a blocked miss rolled the shooting foul", seed, e.Kind)
		}
	}
	if len(gs.events) == 0 || gs.events[len(gs.events)-1].Kind != result.EventBlock {
		t.Errorf("blocked miss (seed %d) events = %+v, want attempt, miss, block", seed, gs.events)
	}
}
