package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// ADR-0055 — faithful putback shot resolution. One decompile-verified divergence
// remains default live engine behavior: (1) the OriginOffReb (putback) 2pt
// make-value uses the net-free 4/3-boosted putbackValue2pt form (93880-93883).
// The UnfaithfulPutback escape hatch (FreezeConfig) restores master's old
// net-coupled behavior for the ADR-0055 archive A/B's OFF walk (make-value site
// only). Divergence (2) — putback 3pt suppressed — was REVERTED 2026-07-22: the
// decompile misread 94022-94024 (local_15c is the OReb continuation flag, not a
// 3pt→2pt re-roll), and 5.60 actually CLEARS the shot-clock flag on OReb
// (93278-93280), guaranteeing the full four-bucket set. Putback 3pt is now
// REACHABLE by default (faithful). SuppressPutback3pt (FreezeConfig) restores the
// old zeroing as an A/B baseline only.

// matrix #2,#3,#4 — make-value is origin-scoped.
//
// A putback (OriginOffReb) make-value equals putbackValue2pt(fgp) = base2pt(fgp)×1.3333
// (net-free, boosted) and is DISTINCT from the normal net-coupled value; an initial
// or transition attempt keeps the normal shotValue2pt(net,fgp,false) — the faithful
// form must not leak to non-putback origins.
func TestPutbackFaithful_MakeValueOriginScoped(t *testing.T) {
	const fgp = 50
	net := 5.0
	bh := oc(slotPG, mkPlayer(1, 1, slotPG, fgp)) // FGP=50, D64=D60=0 → fallback path
	normal := shotValue2pt(net, bh, 0, false, leagueBaselineFallback, 0, 0)
	putback := putbackValue2pt(bh)
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
			if got := gs.makeValue2pt(net, bh, 0, c.origin, 0, 0); got != c.want {
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
	bhFgp := oc(slotPG, mkPlayer(1, 1, slotPG, fgp)) // FGP=50, D64=D60=0 → fallback path
	normal := shotValue2pt(net, bhFgp, 0, false, leagueBaselineFallback, 0, 0)
	putback := putbackValue2pt(bhFgp)

	accPut := &FreezeAccum{}
	gsPut := &gameState{accum: accPut}
	gsPut.makeValue2pt(net, bhFgp, 0, result.OriginOffReb, 0, 0)
	if accPut.makeN != 1 || accPut.makeSum != putback {
		t.Errorf("putback harvest = {n:%d sum:%v}, want {n:1 sum:%v (faithful putbackValue2pt)}", accPut.makeN, accPut.makeSum, putback)
	}
	if accPut.makeSum == normal {
		t.Errorf("putback harvest captured the OLD net-coupled value %v — faithful baseline not in effect", normal)
	}

	accInit := &FreezeAccum{}
	gsInit := &gameState{accum: accInit}
	gsInit.makeValue2pt(net, bhFgp, 0, result.OriginInitial, 0, 0)
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
	bh := oc(slotPG, mkPlayer(1, 1, slotPG, fgp)) // FGP=50, D64=D60=0 → fallback path
	normal := shotValue2pt(net, bh, 0, false, leagueBaselineFallback, 0, 0)

	gs := &gameState{freeze: FreezeConfig{UnfaithfulPutback: true}}
	if got := gs.makeValue2pt(net, bh, 0, result.OriginOffReb, 0, 0); got != normal {
		t.Errorf("escape-hatch putback make-value = %v, want OLD net-coupled %v", got, normal)
	}
	// And the harvest under the escape hatch records the old value too (a faithful OFF baseline).
	acc := &FreezeAccum{}
	gsAcc := &gameState{freeze: FreezeConfig{UnfaithfulPutback: true}, accum: acc}
	gsAcc.makeValue2pt(net, bh, 0, result.OriginOffReb, 0, 0)
	if acc.makeSum != normal {
		t.Errorf("escape-hatch harvest = %v, want OLD net-coupled %v", acc.makeSum, normal)
	}
}

// matrix #5,#7 — putback 3pt reachability (faithful default) and suppression under
// the SuppressPutback3pt A/B arm.
//
// Over a full-game seed sweep: the faithful engine (default Simulate) MUST emit at
// least one ShotThree attempt tagged OriginOffReb — putback 3pt is reachable since
// the 2026-07-22 revert of the ADR-0055 suppression (the decompile misread of
// 94022-94024 is documented in
// jsb-native/re-artifacts/jsb-j24-oreb-3pt-eligibility-20260722.md). OriginInitial
// 3pt attempts remain and OriginOffReb attempts DO occur (fixture exercises putbacks).
// With SuppressPutback3pt set, the old zeroing is restored and OriginOffReb 3pt must
// return to exactly 0 — proving the arm does real work and the fixture CAN produce
// putback 3pt in the default path.
func TestPutbackFaithful_ThreePtReachable(t *testing.T) {
	b := richBundle()

	// Faithful (default Simulate): putback 3pt must be reachable (>0).
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
	if putback3pt == 0 {
		t.Errorf("faithful engine: 0 OriginOffReb 3pt attempts — putback 3pt must be reachable (faithful JSB 5.60)")
	}
	if putbackAtt == 0 {
		t.Fatal("no OriginOffReb attempts observed — fixture cannot exercise the putback path")
	}
	if initial3pt == 0 {
		t.Error("no OriginInitial 3pt attempts — fixture shows no initial 3pt at all")
	}

	// SuppressPutback3pt arm: OriginOffReb 3pt must be zeroed back to 0.
	var suppressPutback3pt int
	for seed := uint64(1); seed <= 200; seed++ {
		res, err := SimulateWith(b, seed, Options{Freeze: FreezeConfig{SuppressPutback3pt: true}})
		if err != nil {
			t.Fatalf("SimulateWith SuppressPutback3pt: %v", err)
		}
		for _, e := range res.Games[0].Events {
			if e.Kind == result.EventShotAttempt && e.Origin == result.OriginOffReb && e.ShotType == result.ShotThree {
				suppressPutback3pt++
			}
		}
	}
	if suppressPutback3pt != 0 {
		t.Errorf("SuppressPutback3pt arm: %d OriginOffReb 3pt attempts, want 0 (suppression must zero putback 3pt)", suppressPutback3pt)
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
