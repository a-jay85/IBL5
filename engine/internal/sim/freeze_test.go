package sim

import (
	"math"
	"reflect"
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
	// float64 vars force runtime (not constant-folded) evaluation, matching the
	// wrapper's accumulated rounding exactly.
	careless, pressure := 60.0, 100.0
	wantTurn := stealTurnoverScale * careless * pressure // below the clamp
	wantMake := shotValue2pt(5, 50, false)
	off := []onCourt{oc(slotPG, mkPlayer(1, 7, slotPG, 46))}
	def := []onCourt{oc(slotPG, mkPlayer(2, 3, slotPG, 50))}
	wantFoul := foulBucketWeight(off, def, 0)

	if got := base.orebProb(100, 100); got != wantOreb {
		t.Errorf("baseline orebProb = %v, want live %v", got, wantOreb)
	}
	if got := base.turnoverProb(60, 100); got != wantTurn {
		t.Errorf("baseline turnoverProb = %v, want live %v", got, wantTurn)
	}
	if got := base.makeValue2pt(5, 50, result.OriginInitial); got != wantMake {
		t.Errorf("baseline makeValue2pt = %v, want live %v", got, wantMake)
	}
	if got := base.foulWeight(off, def, 0); got != wantFoul {
		t.Errorf("baseline foulWeight = %v, want live %v", got, wantFoul)
	}

	m := acc.Means()
	if m.OrebProb != wantOreb || m.TurnProb != wantTurn || m.MakeVal2pt != wantMake || m.FoulWeight != wantFoul {
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
	careless, pressure := 60.0, 100.0
	liveTurn := stealTurnoverScale * careless * pressure // runtime eval, below the clamp
	liveMake := shotValue2pt(5, 50, false)
	liveFoul := foulBucketWeight(off, def, 0)

	// Sentinel means, deliberately distinct from the live values so a leak is visible.
	means := FreezeMeans{OrebProb: 0.31, TurnProb: 0.07, FoulWeight: 0.13, MakeVal2pt: 111.0}

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
			gotTurn := gs.turnoverProb(60, 100)
			gotMake := gs.makeValue2pt(5, 50, result.OriginInitial)
			gotFoul := gs.foulWeight(off, def, 0)

			// The frozen arm returns the sentinel; every OTHER arm returns live.
			wantOreb, wantTurn, wantMake, wantFoul := liveOreb, liveTurn, liveMake, liveFoul
			if c.cfg.ORB {
				wantOreb = means.OrebProb
			}
			if c.cfg.TVR {
				wantTurn = means.TurnProb
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
				t.Errorf("turnoverProb = %v, want %v", gotTurn, wantTurn)
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
		Means: FreezeMeans{OrebProb: 0.5, TurnProb: 0.15, FoulWeight: 0.6, MakeVal2pt: 450},
	}
	if _, err := SimulateWith(b, 1, Options{Freeze: good}); err != nil {
		t.Errorf("SimulateWith with a fully-specified freeze: got error %v, want nil", err)
	}
}

// ADR-0053 — the origin-scoped MakePutback / MakePutbackHalf arms.
//
// The arm substitutes the league mean ONLY for an OriginOffReb (putback) 2pt make-
// value; OriginInitial/OriginTransition keep the live value (no cross-origin leak).
// MakePutbackHalf returns the halfway blend (live + mean)/2.
//
// ADR-0055: the live OriginOffReb value is now the faithful putbackValue2pt form
// (net-free, boosted), so the accumulator harvests putbackValue2pt for a putback
// and shotValue2pt for initial/transition — the freeze arms substitute against this
// NEW baseline (no-cross-confound preserved against the faithful distribution).
func TestMakePutback_OriginScoped(t *testing.T) {
	const fgp = 50
	net := 5.0
	live := shotValue2pt(net, fgp, false) // initial/transition live value
	putback := putbackValue2pt(fgp)       // faithful OriginOffReb live value (ADR-0055)
	mean := 111.0

	cases := []struct {
		name      string
		cfg       FreezeConfig
		origin    result.ShotOrigin
		wantMake  float64
		wantAccum float64 // the LIVE value harvested for this origin (ADR-0055)
	}{
		{"full putback", FreezeConfig{MakePutback: true, Means: FreezeMeans{MakeVal2pt: mean}}, result.OriginOffReb, mean, putback},
		{"full initial untouched", FreezeConfig{MakePutback: true, Means: FreezeMeans{MakeVal2pt: mean}}, result.OriginInitial, live, live},
		{"full transition untouched", FreezeConfig{MakePutback: true, Means: FreezeMeans{MakeVal2pt: mean}}, result.OriginTransition, live, live},
		{"half putback", FreezeConfig{MakePutbackHalf: true, Means: FreezeMeans{MakeVal2pt: mean}}, result.OriginOffReb, (putback + mean) / 2, putback},
		{"half initial untouched", FreezeConfig{MakePutbackHalf: true, Means: FreezeMeans{MakeVal2pt: mean}}, result.OriginInitial, live, live},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			acc := &FreezeAccum{}
			gs := &gameState{freeze: c.cfg, accum: acc}
			if got := gs.makeValue2pt(net, fgp, c.origin); got != c.wantMake {
				t.Errorf("makeValue2pt(%s) = %v, want %v", c.origin, got, c.wantMake)
			}
			// The harvest captures the LIVE value for this origin (faithful for a putback).
			if acc.makeN != 1 || acc.makeSum != c.wantAccum {
				t.Errorf("accum = {n:%d sum:%v}, want {n:1 sum:%v (live)}", acc.makeN, acc.makeSum, c.wantAccum)
			}
		})
	}
}

// ADR-0053 — OFF by default is byte-identical to a zero-Options Simulate, and
// validate() rejects the arms with an unset mean (negative path).
func TestMakePutback_OffInertAndValidate(t *testing.T) {
	b := richBundle()

	// OFF (zero Options) == the live engine.
	base, err := SimulateWith(b, 7, Options{})
	if err != nil {
		t.Fatalf("baseline SimulateWith: %v", err)
	}
	plain := Simulate(b, 7)
	if !reflect.DeepEqual(base, plain) {
		t.Error("zero-Options SimulateWith diverged from Simulate — MakePutback fields not inert when off")
	}

	// Negative path: arm on with an unset (zero) mean is rejected.
	for _, c := range []struct {
		name string
		cfg  FreezeConfig
	}{
		{"MakePutback", FreezeConfig{MakePutback: true}},
		{"MakePutbackHalf", FreezeConfig{MakePutbackHalf: true}},
	} {
		if _, err := SimulateWith(b, 1, Options{Freeze: c.cfg}); err == nil {
			t.Errorf("SimulateWith %s with unset mean: got nil error, want rejection", c.name)
		}
	}
}

// ADR-0053 — boundary: a MakePutback-ON run differs from OFF only when putback
// (OriginOffReb) shots occur. Driving a half-court possession with an EXTREME
// putback make-value forces every putback 2pt attempt to make; the initial-attempt
// make rate is untouched.
func TestMakePutback_AffectsOnlyPutbackShots(t *testing.T) {
	b := richBundle()

	// Count putback (OriginOffReb) and initial 2pt misses under an extreme-high
	// frozen putback make-value: putback misses must vanish while initial misses
	// persist (the initial path keeps its live make/miss roll).
	count := func(cfg FreezeConfig) (orebMiss, initMiss int) {
		for seed := uint64(1); seed <= 600; seed++ {
			res, err := SimulateWith(b, seed, Options{Freeze: cfg})
			if err != nil {
				t.Fatalf("SimulateWith: %v", err)
			}
			for _, e := range res.Games[0].Events {
				if e.Kind != result.EventShotMiss || e.ShotType != result.ShotTwoPoint {
					continue
				}
				switch e.Origin {
				case result.OriginOffReb:
					orebMiss++
				case result.OriginInitial:
					initMiss++
				}
			}
		}
		return
	}

	// High putback make-value (above the roll ceiling) → zero putback 2pt misses,
	// while initial 2pt misses stay positive (untouched).
	cfg := FreezeConfig{MakePutback: true, Means: FreezeMeans{MakeVal2pt: 5000}}
	orebMiss, initMiss := count(cfg)
	if orebMiss != 0 {
		t.Errorf("high putback make-value: %d OriginOffReb 2pt misses, want 0 (arm did not drive putback make/miss)", orebMiss)
	}
	if initMiss == 0 {
		t.Error("no OriginInitial 2pt misses observed — fixture cannot prove the initial path is untouched")
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

// TestGateCont_Accumulates — the L1 gate-1 counterfactual instrument (ADR-0057/0058)
// accumulates per offensive team AND is read-only: a run with the accumulator attached
// produces a byte-identical result.Result to a zero-Options Simulate (no rng draw).
func TestGateCont_Accumulates(t *testing.T) {
	b := richBundle()

	acc := NewGateContAccum()
	res, err := SimulateWith(b, 7, Options{GateCont: acc})
	if err != nil {
		t.Fatalf("SimulateWith: %v", err)
	}
	// Read-only guarantee at the result level: identical to the live engine.
	if !reflect.DeepEqual(res, Simulate(b, 7)) {
		t.Error("attaching GateCont changed the result — the instrument is not read-only")
	}

	ids := acc.TeamIDs()
	if len(ids) == 0 {
		t.Fatal("no offensive teams accumulated — the rebound site never fired")
	}
	for _, id := range ids {
		n, g1, g2, prod, offStr, defStr := acc.Team(id)
		if n == 0 {
			t.Errorf("team %d: TeamIDs listed it but n==0", id)
		}
		for name, v := range map[string]float64{"sumG1": g1, "sumG2": g2, "sumProd": prod, "sumOffStr": offStr, "sumDefStr": defStr} {
			if math.IsNaN(v) || math.IsInf(v, 0) {
				t.Errorf("team %d: %s non-finite (%v)", id, name, v)
			}
		}
		// gate-1 ∈ [0,1] ⇒ Σ(gate1×gate2) ≤ Σ gate2 per team.
		if prod > g2+1e-9 {
			t.Errorf("team %d: sumProd %v > sumG2 %v (gate-1 must reduce)", id, prod, g2)
		}
	}
	// A team never seen returns all zeros (no spurious key).
	if n, _, _, _, _, _ := acc.Team(99999); n != 0 {
		t.Errorf("unseen team should return n=0, got %d", n)
	}
}

// TestGateCont_AllDefensiveRebounds — the accumulator fires at every rebound RESOLUTION
// (a miss reaching gs.rebound), NOT only when the offense RETAINS. So per offensive team
// the resolution count strictly exceeds its credited offensive rebounds (every ORB is a
// resolution, but resolutions also include the defense winning the board).
func TestGateCont_AllDefensiveRebounds(t *testing.T) {
	b := richBundle()
	acc := NewGateContAccum()
	res, err := SimulateWith(b, 11, Options{GateCont: acc})
	if err != nil {
		t.Fatalf("SimulateWith: %v", err)
	}

	// Count offensive rebounds credited to each team from the event stream.
	orbByTeam := map[int]int{}
	for _, e := range res.Games[0].Events {
		if e.Kind == result.EventRebound && e.OffensiveRebound {
			orbByTeam[e.TeamID]++
		}
	}
	for _, id := range acc.TeamIDs() {
		n, _, _, _, _, _ := acc.Team(id)
		if n <= orbByTeam[id] {
			t.Errorf("team %d: resolution count %d not > credited ORBs %d — accumulator is keying on retention, not resolution",
				id, n, orbByTeam[id])
		}
	}
}
