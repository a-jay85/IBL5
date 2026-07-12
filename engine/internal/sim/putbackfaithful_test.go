package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// ADR-0055 — faithful putback shot resolution. Two decompile-verified divergences
// become the default live engine behavior: (1) the OriginOffReb (putback) 2pt
// make-value uses the net-free 4/3-boosted putbackValue2pt form (93880-93883),
// and (2) putback 3pt is suppressed (94022-94024). The UnfaithfulPutback escape
// hatch (FreezeConfig) restores master's old net-coupled, 3pt-reachable behavior
// for the archive A/B's OFF walk only.

// matrix #2,#3,#4 — make-value is origin-scoped.
//
// A putback (OriginOffReb) make-value equals putbackValue2pt(fgp) = base2pt(fgp)×1.3333
// (net-free, boosted) and is DISTINCT from the normal net-coupled value; an initial
// or transition attempt keeps the normal shotValue2pt(net,fgp,false) — the faithful
// form must not leak to non-putback origins.
func TestPutbackFaithful_MakeValueOriginScoped(t *testing.T) {
	const fgp = 50
	net := 5.0
	normal := shotValue2pt(net, fgp, false, leagueBaselineFallback)
	putback := putbackValue2pt(fgp)
	if putback == normal {
		t.Fatalf("fixture too weak: putbackValue2pt(%d)=%v equals the normal value %v — cannot distinguish the forms", fgp, putback, normal)
	}

	cases := []struct {
		origin result.ShotOrigin
		want   float64
	}{
		{result.OriginOffReb, putback},    // faithful putback form
		{result.OriginInitial, normal},    // unchanged
		{result.OriginTransition, normal}, // unchanged (transition faithfulness OOS)
	}
	for _, c := range cases {
		t.Run(string(c.origin), func(t *testing.T) {
			gs := &gameState{} // zero freeze ⇒ faithful (production)
			if got := gs.makeValue2pt(net, fgp, c.origin); got != c.want {
				t.Errorf("makeValue2pt(%s) = %v, want %v", c.origin, got, c.want)
			}
		})
	}
}

// matrix #6 — freeze arms harvest against the NEW (faithful) baseline.
//
// With the accumulator set (a no-freeze harvest pass), an OriginOffReb attempt
// records the FAITHFUL putbackValue2pt, not the old net-coupled shotValue2pt — so
// the ADR-0053 MakePutback/MakePutbackHalf league mean freezes against the new
// distribution (no-cross-confound preserved). An OriginInitial harvest is unchanged.
func TestPutbackFaithful_HarvestUsesNewBaseline(t *testing.T) {
	const fgp = 50
	net := 5.0
	normal := shotValue2pt(net, fgp, false, leagueBaselineFallback)
	putback := putbackValue2pt(fgp)

	accPut := &FreezeAccum{}
	gsPut := &gameState{accum: accPut}
	gsPut.makeValue2pt(net, fgp, result.OriginOffReb)
	if accPut.makeN != 1 || accPut.makeSum != putback {
		t.Errorf("putback harvest = {n:%d sum:%v}, want {n:1 sum:%v (faithful putbackValue2pt)}", accPut.makeN, accPut.makeSum, putback)
	}
	if accPut.makeSum == normal {
		t.Errorf("putback harvest captured the OLD net-coupled value %v — faithful baseline not in effect", normal)
	}

	accInit := &FreezeAccum{}
	gsInit := &gameState{accum: accInit}
	gsInit.makeValue2pt(net, fgp, result.OriginInitial)
	if accInit.makeSum != normal {
		t.Errorf("initial harvest = %v, want normal %v (unchanged)", accInit.makeSum, normal)
	}
}

// matrix #7 — the UnfaithfulPutback escape hatch reproduces master.
//
// With FreezeConfig.UnfaithfulPutback=true an OriginOffReb make-value returns the
// OLD net-coupled shotValue2pt(net,fgp,false) (the 3pt-reachability half is asserted
// in TestPutbackFaithful_ThreePtSuppressed). This guarantees the archive A/B OFF
// arm is a true master baseline.
func TestPutbackFaithful_EscapeHatchRestoresMaster(t *testing.T) {
	const fgp = 50
	net := 5.0
	normal := shotValue2pt(net, fgp, false, leagueBaselineFallback)

	gs := &gameState{freeze: FreezeConfig{UnfaithfulPutback: true}}
	if got := gs.makeValue2pt(net, fgp, result.OriginOffReb); got != normal {
		t.Errorf("escape-hatch putback make-value = %v, want OLD net-coupled %v", got, normal)
	}
	// And the harvest under the escape hatch records the old value too (a faithful OFF baseline).
	acc := &FreezeAccum{}
	gsAcc := &gameState{freeze: FreezeConfig{UnfaithfulPutback: true}, accum: acc}
	gsAcc.makeValue2pt(net, fgp, result.OriginOffReb)
	if acc.makeSum != normal {
		t.Errorf("escape-hatch harvest = %v, want OLD net-coupled %v", acc.makeSum, normal)
	}
}

// matrix #5,#7 — putback 3pt suppression (faithful default) and reachability under
// the escape hatch.
//
// Over a full-game seed sweep: the faithful engine emits ZERO ShotThree attempts
// tagged OriginOffReb (a putback is never a 3pt), while OriginInitial 3pt attempts
// remain (the suppression is putback-scoped) and OriginOffReb attempts DO occur (the
// fixture exercises putbacks). With the UnfaithfulPutback escape hatch, OriginOffReb
// 3pt becomes reachable again — proving the fixture CAN produce them and the
// suppression is doing real work.
func TestPutbackFaithful_ThreePtSuppressed(t *testing.T) {
	b := richBundle()

	// Faithful (default Simulate): count putback 3pt, putback attempts, initial 3pt.
	var putback3pt, putbackAtt, initial3pt int
	for seed := uint64(1); seed <= 200; seed++ {
		for _, e := range Simulate(b, seed).Games[0].Events {
			if e.Kind != result.EventShotAttempt {
				continue
			}
			switch e.Origin {
			case result.OriginOffReb:
				putbackAtt++
				if e.ShotType == result.ShotThree {
					putback3pt++
				}
			case result.OriginInitial:
				if e.ShotType == result.ShotThree {
					initial3pt++
				}
			}
		}
	}
	if putback3pt != 0 {
		t.Errorf("faithful engine: %d OriginOffReb 3pt attempts, want 0 (putback 3pt must be suppressed)", putback3pt)
	}
	if putbackAtt == 0 {
		t.Fatal("no OriginOffReb attempts observed — fixture cannot exercise the putback path")
	}
	if initial3pt == 0 {
		t.Error("no OriginInitial 3pt attempts — suppression must be putback-scoped, but the fixture shows no initial 3pt")
	}

	// Escape hatch: OriginOffReb 3pt must become reachable again.
	var hatchPutback3pt int
	for seed := uint64(1); seed <= 200; seed++ {
		res, err := SimulateWith(b, seed, Options{Freeze: FreezeConfig{UnfaithfulPutback: true}})
		if err != nil {
			t.Fatalf("SimulateWith escape hatch: %v", err)
		}
		for _, e := range res.Games[0].Events {
			if e.Kind == result.EventShotAttempt && e.Origin == result.OriginOffReb && e.ShotType == result.ShotThree {
				hatchPutback3pt++
			}
		}
	}
	if hatchPutback3pt == 0 {
		t.Error("escape hatch: 0 OriginOffReb 3pt attempts — the fixture cannot produce putback 3pt, so the suppression test proves nothing")
	}
}

// matrix #8 — zeroing the putback 3pt bucket weight does not change RNG consumption.
//
// selectOutcome draws exactly one Float64 (path roll, total>0) + one IntN (turnover
// check) regardless of the bucket weights. So zeroing threePtWeight leaves both RNGs
// at the SAME draw position: the next Float64 from each must be identical. This is the
// invariant that keeps the suppression from desyncing the stream at its own site
// (the golden moves by design via downstream path/value changes, NOT via extra draws).
func TestPutbackFaithful_RNGConsumptionUnchanged(t *testing.T) {
	for seed := uint64(1); seed <= 50; seed++ {
		r1 := rng.New(seed)
		r2 := rng.New(seed)
		inWith := outcomeInputs{twoPtWeight: 10, threePtWeight: 5, andOneWeight: 1, foulOnlyWeight: 2, turnoverDefValue: 3}
		inZero := inWith
		inZero.threePtWeight = 0
		selectOutcome(inWith, false, false, false, r1)
		selectOutcome(inZero, false, false, false, r2)
		if got1, got2 := r1.Float64(), r2.Float64(); got1 != got2 {
			t.Fatalf("seed %d: RNG desynced after zeroing threePtWeight (next draw %v != %v) — selectOutcome consumed a different number of values", seed, got1, got2)
		}
	}
}
