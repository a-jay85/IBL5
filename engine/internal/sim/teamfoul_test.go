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
			if e.FoulSite == result.FoulSiteOffensiveTurnover {
				continue // an offensive foul counts as a team foul but never shoots FTs
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

	gs.chargeFoul(defense, defender, result.FoulSiteNonShooting) // a below-bonus foul: PF only
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

// nonShootingDrawSeed returns a seed whose first Float64 is at or under
// shootingFoulDrawCut, so the next foul resolves as a non-shooting draw.
func nonShootingDrawSeed(t *testing.T) uint64 {
	t.Helper()
	for s := uint64(1); s <= 200; s++ {
		if rng.New(s).Float64() <= shootingFoulDrawCut {
			return s
		}
	}
	t.Fatal("no seed in 1..200 gives a first draw at or under the shooting cut")
	return 0
}

// resolveNonShootingAt charges one non-shooting draw with the defense already
// at priorTeamFouls, and returns the shoot verdict, the foul's site tag, and
// the total free-throw attempts emitted.
func resolveNonShootingAt(t *testing.T, priorTeamFouls int) (bool, result.FoulSite, int) {
	t.Helper()
	b := richBundle()
	offense := newTeamState(b.Players, 7, false)
	defense := newTeamState(b.Players, 3, true)
	shooter, defender := offense.players[0], defense.players[0]
	gs := &gameState{rng: rng.New(nonShootingDrawSeed(t)), period: 1, clock: 600}
	defense.teamFouls = priorTeamFouls
	shot := gs.resolveFoul(offense, defense, shooter, defender, 2, false, false, 0)
	site := result.FoulSiteUnknown
	fouls, fta := 0, 0
	for _, e := range gs.events {
		switch e.Kind {
		case result.EventFoul:
			fouls++
			site = e.FoulSite
		case result.EventFreeThrow:
			fta += e.FTAttempts
		}
	}
	if fouls != 1 {
		t.Fatalf("priorTeamFouls=%d: %d EventFoul, want 1", priorTeamFouls, fouls)
	}
	return shot, site, fta
}

// TestNonShootingBonusTagBoundary pins the J27 rate-port bonus tag: the 5th
// team foul of a period on a non-shooting draw shoots 2 FTs and is tagged
// FoulSiteNonShootingBonus.
func TestNonShootingBonusTagBoundary(t *testing.T) {
	shot, site, fta := resolveNonShootingAt(t, teamFoulBonusThreshold)
	if !shot {
		t.Error("5th team foul: resolveFoul = false, want true (in the bonus)")
	}
	if site != result.FoulSiteNonShootingBonus {
		t.Errorf("5th team foul: site = %s, want %s", site, result.FoulSiteNonShootingBonus)
	}
	if fta != 2 {
		t.Errorf("5th team foul: FTA = %d, want 2", fta)
	}
}

// TestNonShootingBelowBonusNoFT pins the other side of the boundary: the 4th
// team foul on a non-shooting draw shoots nothing, keeps FoulSiteNonShooting,
// and returns false so the offense keeps the ball.
func TestNonShootingBelowBonusNoFT(t *testing.T) {
	shot, site, fta := resolveNonShootingAt(t, teamFoulBonusThreshold-1)
	if shot {
		t.Error("4th team foul: resolveFoul = true, want false (below the bonus)")
	}
	if site != result.FoulSiteNonShooting {
		t.Errorf("4th team foul: site = %s, want %s", site, result.FoulSiteNonShooting)
	}
	if fta != 0 {
		t.Errorf("4th team foul: FTA = %d, want 0", fta)
	}
}
