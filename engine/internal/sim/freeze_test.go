package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// matrix #3 — freeze isolation (happy path) + baseline accumulation.
//
// A frozen arm returns its league-mean scalar regardless of inputs; an
// accumulating (non-freeze) pass records the LIVE derived value so Means() can be
// harvested.
func TestFreeze_SubstitutesAndAccumulates(t *testing.T) {
	// Frozen ORB: gs.orebProb returns the mean for any inputs.
	frozen := &gameState{freeze: FreezeConfig{ORB: true, Means: FreezeMeans{OrebProb: 0.4}}}
	if got := frozen.orebProb(999, 1); got != 0.4 {
		t.Errorf("frozen orebProb = %v, want 0.4 (the league mean, input-independent)", got)
	}
	if got := frozen.orebProb(1, 999); got != 0.4 {
		t.Errorf("frozen orebProb (reversed inputs) = %v, want 0.4", got)
	}

	// Baseline pass (accum set, no freeze): the live values are accumulated and
	// Means() returns them exactly (single sample each).
	acc := &FreezeAccum{}
	base := &gameState{accum: acc}
	wantOreb := orebProbability(100, 100) // 0.5
	wantTurn := turnoverThreshold(40)     // linear threshold
	wantMake := shotValue2pt(5, 50, false)
	off := []onCourt{oc(slotPG, mkPlayer(1, 7, slotPG, 46))}
	def := []onCourt{oc(slotPG, mkPlayer(2, 3, slotPG, 50))}
	wantFoul := foulBucketWeight(off, def, 0)

	if got := base.orebProb(100, 100); got != wantOreb {
		t.Errorf("baseline orebProb = %v, want live %v", got, wantOreb)
	}
	if got := base.turnThreshLinear(40); got != wantTurn {
		t.Errorf("baseline turnThreshLinear = %v, want live %v", got, wantTurn)
	}
	if got := base.makeValue2pt(5, 50); got != wantMake {
		t.Errorf("baseline makeValue2pt = %v, want live %v", got, wantMake)
	}
	if got := base.foulWeight(off, def, 0); got != wantFoul {
		t.Errorf("baseline foulWeight = %v, want live %v", got, wantFoul)
	}

	m := acc.Means()
	if m.OrebProb != wantOreb || m.TurnThresh != wantTurn || m.MakeVal2pt != wantMake || m.FoulWeight != wantFoul {
		t.Errorf("Means() = %+v, want {Oreb:%v Turn:%v Make:%v Foul:%v}", m, wantOreb, wantTurn, wantMake, wantFoul)
	}
	if acc.orebN != 1 || acc.turnN != 1 || acc.makeN != 1 || acc.foulN != 1 {
		t.Errorf("accumulator counts = oreb:%d turn:%d make:%d foul:%d, want 1 each", acc.orebN, acc.turnN, acc.makeN, acc.foulN)
	}
}

// matrix #4 — no-cross-confound (NEGATIVE).
//
// Freezing ONE arm must leave the other three wrappers returning their LIVE
// derived values. This is the property the attribution depends on: a per-arm ΔCov
// reflects only that arm, because the injection point is localized to one
// mechanism's output (not a shared rating that would spill into siblings).
func TestFreeze_NoCrossConfound(t *testing.T) {
	off := []onCourt{oc(slotPG, mkPlayer(1, 7, slotPG, 46))}
	def := []onCourt{oc(slotPG, mkPlayer(2, 3, slotPG, 50))}

	liveOreb := orebProbability(120, 80)
	liveTurn := turnoverThreshold(40)
	liveMake := shotValue2pt(5, 50, false)
	liveFoul := foulBucketWeight(off, def, 0)

	// Sentinel means, deliberately distinct from the live values so a leak is visible.
	means := FreezeMeans{OrebProb: 0.31, TurnThresh: 7.0, FoulWeight: 0.13, MakeVal2pt: 111.0}

	cases := []struct {
		name string
		cfg  FreezeConfig
	}{
		{"ORB-only", FreezeConfig{ORB: true, Means: means}},
		{"TVR-only", FreezeConfig{TVR: true, Means: means}},
		{"Foul-only", FreezeConfig{Foul: true, Means: means}},
		{"Make-only", FreezeConfig{Make: true, Means: means}},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			gs := &gameState{freeze: c.cfg}
			gotOreb := gs.orebProb(120, 80)
			gotTurn := gs.turnThreshLinear(40)
			gotMake := gs.makeValue2pt(5, 50)
			gotFoul := gs.foulWeight(off, def, 0)

			// The frozen arm returns the sentinel; every OTHER arm returns live.
			wantOreb, wantTurn, wantMake, wantFoul := liveOreb, liveTurn, liveMake, liveFoul
			if c.cfg.ORB {
				wantOreb = means.OrebProb
			}
			if c.cfg.TVR {
				wantTurn = means.TurnThresh
			}
			if c.cfg.Make {
				wantMake = means.MakeVal2pt
			}
			if c.cfg.Foul {
				wantFoul = means.FoulWeight
			}
			if gotOreb != wantOreb {
				t.Errorf("orebProb = %v, want %v", gotOreb, wantOreb)
			}
			if gotTurn != wantTurn {
				t.Errorf("turnThreshLinear = %v, want %v", gotTurn, wantTurn)
			}
			if gotMake != wantMake {
				t.Errorf("makeValue2pt = %v, want %v", gotMake, wantMake)
			}
			if gotFoul != wantFoul {
				t.Errorf("foulWeight = %v, want %v", gotFoul, wantFoul)
			}
		})
	}
}

// matrix #5 — misconfig (FAILURE).
//
// Freezing an arm whose precomputed mean is unset (zero) must return an explicit
// error, never silently substitute 0 (degenerate for every arm). A valid config
// returns nil.
func TestFreeze_MisconfigErrors(t *testing.T) {
	b := richBundle()
	bad := []struct {
		name string
		cfg  FreezeConfig
	}{
		{"ORB", FreezeConfig{ORB: true}},
		{"TVR", FreezeConfig{TVR: true}},
		{"Foul", FreezeConfig{Foul: true}},
		{"Make", FreezeConfig{Make: true}},
	}
	for _, c := range bad {
		if _, err := SimulateWith(b, 1, Options{Freeze: c.cfg}); err == nil {
			t.Errorf("SimulateWith with %s frozen but unset mean: got nil error, want an error", c.name)
		}
	}

	// A fully-specified config validates and runs.
	good := FreezeConfig{
		ORB: true, TVR: true, Foul: true, Make: true,
		Means: FreezeMeans{OrebProb: 0.5, TurnThresh: 200, FoulWeight: 0.6, MakeVal2pt: 450},
	}
	if _, err := SimulateWith(b, 1, Options{Freeze: good}); err != nil {
		t.Errorf("SimulateWith with a fully-specified freeze: got error %v, want nil", err)
	}
}

// matrix #6 — transition coverage (NEGATIVE/boundary).
//
// The Make freeze must reach the fast-break FGA path, not only the half-court
// loop. Driving the transition possession directly with an EXTREME frozen make-
// value forces every transition 2pt attempt to make (high) or miss (low); if the
// freeze missed transition.go, the rate would instead follow the live ~45%.
func TestFreeze_MakeReachesTransitionPath(t *testing.T) {
	b := richBundle()

	count := func(makeVal float64) (attempts, makes, misses int) {
		cfg := FreezeConfig{Make: true, Means: FreezeMeans{MakeVal2pt: makeVal}}
		for seed := uint64(1); seed <= 300; seed++ {
			offense := newTeamState(b.Players, 7, false)
			defense := newTeamState(b.Players, 3, true)
			gs := &gameState{rng: rng.New(seed), period: 1, clock: 500, madeFG: map[int]int{}, freeze: cfg}
			gs.runTransitionPossession(offense, defense, 0)
			for _, e := range gs.events {
				if e.Origin != result.OriginTransition || e.ShotType != result.ShotTwoPoint {
					continue
				}
				switch e.Kind {
				case result.EventShotMake:
					makes++
				case result.EventShotMiss:
					misses++
				case result.EventShotAttempt:
					attempts++
				}
			}
		}
		return
	}

	// Transition 2pt MISSES come ONLY from the rollMake path — the and-one bucket
	// is a guaranteed make (it never reaches makeValue2pt), so misses are the clean
	// signal that the Make freeze drove the make/miss roll on the transition path.
	//
	// High frozen make-value (well above the 1..1000 roll ceiling) → the rollMake
	// path always makes, so zero transition 2pt misses.
	hiAtt, _, hiMisses := count(5000)
	if hiAtt == 0 {
		t.Fatal("no transition 2pt attempts were produced — fixture cannot exercise the transition path")
	}
	if hiMisses != 0 {
		t.Errorf("high frozen make-value: %d transition 2pt misses, want 0 (freeze did not reach the transition path)", hiMisses)
	}

	// Low frozen make-value below the roll floor (effective 0.5 < the minimum roll
	// of 1, since rollMake compares effective >= rand_int(1,1000)) → every rollMake-
	// path transition 2pt attempt misses. A positive miss count proves the path is
	// exercised AND that the frozen value DRIVES the transition outcome (the
	// two-sided check vs the high run).
	_, _, loMisses := count(0.5)
	if loMisses == 0 {
		t.Error("low frozen make-value: 0 transition 2pt misses, want > 0 (the rollMake transition path was never exercised, or the freeze did not reach it)")
	}
}
