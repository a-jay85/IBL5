package sim

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// J27: the JSB 5.60 shooting-foul draw (93469-93480). The cut is strict, and
// the forced flag clears shooting even for an and-one.
func TestIsShootingFoul(t *testing.T) {
	cases := []struct {
		name                    string
		draw                    float64
		andOne, threePt, forced bool
		want                    bool
	}{
		{"draw exactly at cut is non-shooting", 0.5, false, false, false, false},
		{"draw above cut is shooting", 0.51, false, false, false, true},
		{"and-one is shooting at draw 0", 0.0, true, false, false, true},
		{"3pt attempt is shooting at draw 0", 0.0, false, true, false, true},
		{"forced clears and-one at draw 0.9", 0.9, true, false, true, false},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if got := isShootingFoul(c.draw, c.andOne, c.threePt, c.forced); got != c.want {
				t.Errorf("isShootingFoul(%v, andOne=%v, threePt=%v, forced=%v) = %v, want %v",
					c.draw, c.andOne, c.threePt, c.forced, got, c.want)
			}
		})
	}
}

// J27: the 93647 FT guard. The team count is incremented before the test, so
// the 5th team foul of a period is the first non-shooting foul that shoots.
func TestFoulAwardsFreeThrows(t *testing.T) {
	cases := []struct {
		name            string
		shooting        bool
		teamFouls       int
		andOne, threePt bool
		want            bool
	}{
		{"non-shooting 4th team foul shoots none", false, 4, false, false, false},
		{"non-shooting 5th team foul is in the bonus", false, 5, false, false, true},
		{"shooting foul at count 0 shoots", true, 0, false, false, true},
		{"and-one at count 0 shoots", false, 0, true, false, true},
		{"3pt attempt at count 0 shoots", false, 0, false, true, true},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if got := foulAwardsFreeThrows(c.shooting, c.teamFouls, c.andOne, c.threePt); got != c.want {
				t.Errorf("foulAwardsFreeThrows(shooting=%v, teamFouls=%d, andOne=%v, threePt=%v) = %v, want %v",
					c.shooting, c.teamFouls, c.andOne, c.threePt, got, c.want)
			}
		})
	}
}

// J27: over the golden fixture, the event stream obeys the per-period team-foul
// bonus. A "no-FT" foul is an EventFoul whose next event is not an
// EventFreeThrow. chargeFoul is the only EventFoul emitter, and freeThrows
// emits its EventFreeThrow directly after it, so this split is exact. One run
// of the fixture rarely reaches 5 fouls by one team in one period (engine
// PF/g is about 8), so the fixture runs over bonusStreamSeeds seeds from its
// own seed.
const bonusStreamSeeds = 200

func TestTeamFoulBonusEventStream(t *testing.T) {
	in, err := os.ReadFile(filepath.Join("testdata", "bundle.json"))
	if err != nil {
		t.Fatalf("read fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode fixture: %v", err)
	}
	var games []result.GameResult
	for i := 0; i < bonusStreamSeeds; i++ {
		games = append(games, Simulate(b, b.Seed+uint64(i)).Games...)
	}

	type periodTeam struct{ period, team int }
	var (
		sawFivePlus   bool // (a) some (game, period, team) reached 5+ fouls
		noFT          int  // (c) count of no-FT fouls
		cumulativeHit bool // (d) a no-FT foul at game-cumulative team ordinal 5+
	)
	for gi, g := range games {
		perPeriod := map[periodTeam]int{}
		perGame := map[int]int{}
		for i, e := range g.Events {
			if e.Kind != result.EventFoul {
				continue
			}
			key := periodTeam{e.Period, e.TeamID}
			perPeriod[key]++
			perGame[e.TeamID]++
			if perPeriod[key] >= 5 {
				sawFivePlus = true
			}
			shotFTs := i+1 < len(g.Events) && g.Events[i+1].Kind == result.EventFreeThrow
			if shotFTs {
				continue
			}
			noFT++
			if perPeriod[key] > teamFoulBonusThreshold {
				t.Errorf("game %d period %d team %d: no-FT foul at period team-foul ordinal %d, want <= %d",
					gi, e.Period, e.TeamID, perPeriod[key], teamFoulBonusThreshold)
			}
			if perGame[e.TeamID] >= 5 {
				cumulativeHit = true
			}
		}
	}
	if !sawFivePlus {
		t.Fatal("precondition: no (game, period, team) reached 5 fouls; the bonus check would pass vacuously")
	}
	if noFT == 0 {
		t.Error("no below-bonus foul emitted an EventFoul without free throws")
	}
	if !cumulativeHit {
		t.Error("no no-FT foul at a game-cumulative team ordinal of 5+; the per-period reset is not taking effect")
	}
}

// J27: chargeFoul feeds the per-player tally that foul-out and foul-trouble
// substitution read, for below-bonus fouls and bonus fouls alike. The golden
// fixture commits too few fouls for any substitution to fire, so the golden
// bytes alone cannot pin this. The TestGolden name prefix keeps it inside the
// `-run TestGolden` run that guards the golden snapshot.
func TestGoldenFoulTally_ChargeFoulFeedsSubstitution(t *testing.T) {
	b := richBundle()
	offense := newTeamState(b.Players, 7, false)
	defense := newTeamState(b.Players, 3, true)
	shooter := offense.players[0]
	defender := defense.players[0]
	gs := &gameState{rng: rng.New(1), period: 1, clock: 600}

	gs.chargeFoul(defense, defender) // a below-bonus foul: PF only
	if got := defense.fouls[defender.PID]; got != 1 {
		t.Fatalf("after chargeFoul, defender fouls = %d, want 1", got)
	}
	if len(gs.events) != 1 || gs.events[0].Kind != result.EventFoul {
		t.Fatalf("chargeFoul events = %+v, want exactly one EventFoul", gs.events)
	}

	defense.teamFouls = teamFoulBonusThreshold // the next foul is the 5th: in the bonus
	if !gs.resolveFoul(offense, defense, shooter, defender, 2, false, false, 0) {
		t.Fatal("resolveFoul on the 5th team foul shot no free throws")
	}
	if got := defense.fouls[defender.PID]; got != 2 {
		t.Errorf("after a bonus foul, defender fouls = %d, want 2", got)
	}
	if defense.teamFouls != teamFoulBonusThreshold+1 {
		t.Errorf("teamFouls = %d, want %d", defense.teamFouls, teamFoulBonusThreshold+1)
	}
}

// TestResolveFoulShotThreePtShootsBelowBonus pins JSB's local_110 (94148):
// a fouled 3 shoots 3 FTs below the bonus even on a draw at or under the
// shooting cut, and the same draw without the 3pt flag shoots nothing.
func TestResolveFoulShotThreePtShootsBelowBonus(t *testing.T) {
	seed := uint64(0)
	for s := uint64(1); s <= 200; s++ {
		ref := rng.New(s)
		if ref.Float64() <= shootingFoulDrawCut {
			seed = s
			break
		}
	}
	if seed == 0 {
		t.Fatal("no seed in 1..200 gives a first draw at or under the shooting cut")
	}
	for _, c := range []struct {
		threePt bool
		wantFTs bool
	}{{true, true}, {false, false}} {
		b := richBundle()
		offense := newTeamState(b.Players, 7, false)
		defense := newTeamState(b.Players, 3, true)
		shooter, defender := offense.players[0], defense.players[0]
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 600}
		defense.teamFouls = 0
		got := gs.resolveFoulShot(offense, defense, shooter, defender, 3, false, c.threePt, false, 0)
		if got != c.wantFTs {
			t.Errorf("threePt=%v: resolveFoulShot = %v, want %v", c.threePt, got, c.wantFTs)
		}
		fts := 0
		for _, e := range gs.events {
			if e.Kind != result.EventFreeThrow {
				continue
			}
			fts++
			if e.FTAttempts != 3 {
				t.Errorf("threePt=%v: FT trip with %d attempts, want 3", c.threePt, e.FTAttempts)
			}
		}
		wantTrips := 0
		if c.wantFTs {
			wantTrips = 1
		}
		if fts != wantTrips {
			t.Errorf("threePt=%v: %d FT events, want %d", c.threePt, fts, wantTrips)
		}
		if got := defense.fouls[defender.PID]; got != 1 {
			t.Errorf("threePt=%v: defender fouls = %d, want 1", c.threePt, got)
		}
	}
}
