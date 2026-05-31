package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

const floatEps = 1e-9

// --- matrix #1: energyParam formula + [2,5] clamp boundaries ----------------

func TestEnergyParam(t *testing.T) {
	cases := []struct {
		name                     string
		dcMinutes, skill, talent int
		want                     float64
	}{
		// High minutes, zero ratings → floors at 2.
		{"low clamp", 28, 0, 0, 2},
		// Low minutes, high ratings → caps at 5 (pre-clamp ≈ 8.56).
		{"high clamp", 0, 20, 20, 5},
		// Mid inputs land inside [2,5] unclamped: (48-20)*0.03*12*0.125+1 = 2.26.
		{"unclamped formula", 20, 5, 5, 2.26},
		// min(dc_minutes, 28): 28 and 40 give the same energy (verifies the cap).
		{"minutes cap at 28", 28, 20, 20, 4.15},
		{"minutes cap above 28", 40, 20, 20, 4.15},
	}
	for _, c := range cases {
		p := bundle.Player{DCMinutes: c.dcMinutes, Skill: c.skill, Talent: c.talent}
		if got := energyParam(p); math.Abs(got-c.want) > floatEps {
			t.Errorf("%s: energyParam(dc=%d,skill=%d,talent=%d) = %v, want %v",
				c.name, c.dcMinutes, c.skill, c.talent, got, c.want)
		}
	}
}

// --- matrix #2: severity band-multiplier selection + floor+1 + formula max ---

func TestSeverityBandMultiplier(t *testing.T) {
	cases := []struct {
		u    float64
		want int
	}{
		{0.0, 3}, {0.221, 3}, // u < 0.222 → ×3
		{0.222, 9}, {0.369, 9}, // [0.222, 0.370) → ×9
		{0.370, 27}, {0.418, 27}, // [0.370, 0.419) → ×27
		{0.419, 81}, {0.429, 81}, // [0.419, 0.430) → ×81
		{0.430, 1}, {0.99, 1}, // u ≥ 0.430 → ×1
	}
	for _, c := range cases {
		if got := severityBandMultiplier(c.u); got != c.want {
			t.Errorf("severityBandMultiplier(%v) = %d, want %d", c.u, got, c.want)
		}
	}
}

func TestSeverityFromU(t *testing.T) {
	// E = 4 → sqrt(E) = 2, so severity = floor(u × 2 × bandMult) + 1.
	cases := []struct {
		u    float64
		want int
	}{
		{0.10, 1},   // ×3:  floor(0.6)+1
		{0.30, 6},   // ×9:  floor(5.4)+1
		{0.40, 22},  // ×27: floor(21.6)+1
		{0.425, 69}, // ×81: floor(68.85)+1
		{0.50, 2},   // ×1:  floor(1.0)+1
	}
	for _, c := range cases {
		if got := severityFromU(c.u, 4); got != c.want {
			t.Errorf("severityFromU(%v, 4) = %d, want %d", c.u, got, c.want)
		}
	}

	// The corpus max ≈ 78 is the ×81 band at E=5, u≈0.4299.
	if got := severityFromU(0.4299, 5); got != 78 {
		t.Errorf("severityFromU(0.4299, 5) = %d, want 78 (corpus max)", got)
	}
	// No real draw at the max energy can exceed 78: sweep u ∈ [0, 0.5).
	maxSev := 0
	for u := 0.0; u < 0.5; u += 0.0001 {
		if s := severityFromU(u, 5); s > maxSev {
			maxSev = s
		}
	}
	if maxSev != 78 {
		t.Errorf("formula max over u∈[0,0.5) at E=5 = %d, want 78", maxSev)
	}
}

// --- matrix #3: clamp boundaries (160 is unreachable through the formula) ----

func TestClampHelpers(t *testing.T) {
	if got := clampInt(200, 1, injurySeverityCap); got != 160 {
		t.Errorf("clampInt(200,1,160) = %d, want 160", got)
	}
	if got := clampInt(0, 1, injurySeverityCap); got != 1 {
		t.Errorf("clampInt(0,1,160) = %d, want 1", got)
	}
	if got := clampInt(50, 1, injurySeverityCap); got != 50 {
		t.Errorf("clampInt(50,1,160) = %d, want 50 (in range)", got)
	}
	if got := clampFloat(10, 2, 5); got != 5 {
		t.Errorf("clampFloat(10,2,5) = %v, want 5", got)
	}
	if got := clampFloat(1, 2, 5); got != 2 {
		t.Errorf("clampFloat(1,2,5) = %v, want 2", got)
	}
	if got := clampFloat(3, 2, 5); got != 3 {
		t.Errorf("clampFloat(3,2,5) = %v, want 3 (in range)", got)
	}
}

// --- matrix #4: gamesMissedFromU boundaries + never 0/negative ---------------

func TestGamesMissedFromU(t *testing.T) {
	cases := []struct {
		sev  int
		u2   float64
		want int
	}{
		{2, 0.0, 4}, {2, 0.6, 3}, // sev=2 → 3 or 4 (corpus mode)
		{78, 0.0, 175}, {78, 0.9, 174}, // sev=78 → 175 (corpus max)
		{1, 0.0, 2}, {1, 0.9, 1}, // sev=1 → 1 or 2
	}
	for _, c := range cases {
		if got := gamesMissedFromU(c.sev, c.u2); got != c.want {
			t.Errorf("gamesMissedFromU(%d, %v) = %d, want %d", c.sev, c.u2, got, c.want)
		}
	}

	// Negative path: never 0 or negative for any severity ≥ 1, any draw.
	for sev := 1; sev <= injurySeverityCap; sev++ {
		for _, u2 := range []float64{0.0, 0.5, 0.999999} {
			if got := gamesMissedFromU(sev, u2); got < 1 {
				t.Fatalf("gamesMissedFromU(%d, %v) = %d, want ≥ 1", sev, u2, got)
			}
		}
	}
}

// injTeam builds a minimal teamState carrying only the injured marker the
// injury path needs (no roster/box machinery — maybeInjure touches only injured
// and the event stream).
func injTeam(teamID int) *teamState {
	return &teamState{teamID: teamID, injured: map[int]bool{}}
}

// --- matrix #7: maybeInjure fires (and marks) on the firing draw, else not ---

func TestMaybeInjure_FiresAndMarks(t *testing.T) {
	bh := oc(slotPG, mkPlayer(101, 7, slotPG, 50))

	// Seed 21's first Float64() < injuryProbability → the injury fires.
	gsFire := &gameState{rng: rng.New(21), period: 2, clock: 333}
	team := injTeam(7)
	gsFire.maybeInjure(team, bh)
	if len(gsFire.events) != 1 || gsFire.events[0].Kind != result.EventInjury {
		t.Fatalf("expected exactly one EventInjury, got %+v", gsFire.events)
	}
	e := gsFire.events[0]
	if e.PlayerID != 101 || e.TeamID != 7 {
		t.Errorf("injury event identity = PID %d / team %d, want 101 / 7", e.PlayerID, e.TeamID)
	}
	if e.Severity < 1 || e.GamesMissed < 1 {
		t.Errorf("injury severity/games-missed = %d/%d, want both ≥ 1", e.Severity, e.GamesMissed)
	}
	if e.Period != 2 || e.Clock != 333 {
		t.Errorf("injury clock = P%d %ds, want P2 333s", e.Period, e.Clock)
	}
	if !team.injured[101] {
		t.Error("injured PID 101 not marked in teamState.injured")
	}

	// Seed 1's first Float64() ≥ injuryProbability → no injury.
	gsNo := &gameState{rng: rng.New(1), period: 1, clock: 600}
	teamNo := injTeam(7)
	gsNo.maybeInjure(teamNo, bh)
	if len(gsNo.events) != 0 {
		t.Errorf("no-injury draw emitted events: %+v", gsNo.events)
	}
	if len(teamNo.injured) != 0 {
		t.Errorf("no-injury draw marked injured: %v", teamNo.injured)
	}
}

// Every EventInjury in a real game is emitted only inside the turnover branch,
// immediately after the committer's EventTurnover (same player/team).
func TestSimulate_InjuryOnlyAfterTurnover(t *testing.T) {
	var events []result.Event
	for seed := uint64(1); seed <= 50; seed++ {
		res := Simulate(richBundle(), seed)
		evs := res.Games[0].Events
		has := false
		for _, e := range evs {
			if e.Kind == result.EventInjury {
				has = true
				break
			}
		}
		if has {
			events = res.Games[0].Events
			break
		}
	}
	if events == nil {
		t.Fatal("no seed in [1,50] produced an in-game injury")
	}
	injuries := 0
	for i, e := range events {
		if e.Kind != result.EventInjury {
			continue
		}
		injuries++
		if i == 0 || events[i-1].Kind != result.EventTurnover {
			t.Fatalf("EventInjury at index %d not immediately preceded by a turnover (prev=%+v)", i, events[i-1])
		}
		if events[i-1].PlayerID != e.PlayerID || events[i-1].TeamID != e.TeamID {
			t.Errorf("injury %+v does not match its preceding turnover %+v", e, events[i-1])
		}
	}
	if injuries == 0 {
		t.Fatal("selected game had no EventInjury")
	}
}

// --- matrix #8: fixed-order draw discipline (1 draw no injury, 3 if injured) -

func TestMaybeInjure_DrawCount(t *testing.T) {
	bh := oc(slotPG, mkPlayer(101, 7, slotPG, 50))

	// No injury (seed 1): exactly 1 draw consumed. A reference RNG advanced by 1
	// must then track gs.rng exactly.
	gs1 := &gameState{rng: rng.New(1)}
	gs1.maybeInjure(injTeam(7), bh)
	ref1 := rng.New(1)
	ref1.Float64() // the single probability roll
	if gs1.rng.Float64() != ref1.Float64() {
		t.Error("no-injury path did not consume exactly 1 RNG draw")
	}

	// Injury (seed 21): exactly 3 draws consumed (prob, severity, games-missed).
	gs3 := &gameState{rng: rng.New(21)}
	gs3.maybeInjure(injTeam(7), bh)
	ref3 := rng.New(21)
	ref3.Float64() // probability roll (fires)
	ref3.Float64() // severity draw
	ref3.Float64() // games-missed draw
	if gs3.rng.Float64() != ref3.Float64() {
		t.Error("injury path did not consume exactly 3 RNG draws in fixed order")
	}
}
