package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- L1 gate-1 counterfactual (ADR-0057/0058) ----------------------------------

// TestGate1Probability_KnownValues pins gate1Probability against hand-computed values
// from FUN_004e22a0 (decompile 97390-97405): adv = (share×100 − baseline)×0.5, then
// the sqrt branch, then P = clamp(value/100). Three regimes: adv=0 (equal/at-baseline),
// adv>0 (offense above baseline, add branch), adv<0 (offense below baseline, subtract
// branch). Also asserts the product gate1×gate2 < gate2 (the mean-inflation claim).
func TestGate1Probability_KnownValues(t *testing.T) {
	cases := []struct {
		name           string
		off, def, base float64
		want           float64
	}{
		// no cap (def not < off), share=0.5, adv=0 → value=baseline → P=baseline/100.
		{"equal-at-baseline", 50, 50, 50, 0.50},
		// share=0.5, adv=(50−30)*0.5=10>0 → value=√10+10+30=43.1623 → P=0.431623.
		{"above-baseline-add", 50, 50, 30, 0.4316228},
		// share=0.2, adv=(20−50)*0.5=−15≤0 → value=(−15+50)−√15=31.1270 → P=0.311270.
		{"below-baseline-subtract", 20, 80, 50, 0.3112702},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if got := gate1Probability(c.off, c.def, c.base); math.Abs(got-c.want) > 1e-6 {
				t.Errorf("gate1Probability(%v,%v,%v) = %v, want %v", c.off, c.def, c.base, got, c.want)
			}
		})
	}
	// The product is strictly below gate-2 whenever gate-1 < 1 — the dropped gate only
	// reduces continuation (the mean-inflation mechanism).
	g1 := gate1Probability(20, 80, 50)
	g2 := orebProbability(20, 80)
	if g1*g2 >= g2 {
		t.Errorf("product %v not < gate-2 %v (gate-1 %v should reduce)", g1*g2, g2, g1)
	}
}

// TestGate1Probability_OffLeqDefCap verifies the off≤def cap (decompile 97393-97395):
// when off > def, off collapses to def, so gate1Probability(off,def) == the def==off
// case at the same def. The share never exceeds 0.5.
func TestGate1Probability_OffLeqDefCap(t *testing.T) {
	const base = 40
	capped := gate1Probability(80, 20, base) // off>def → off:=def=20, share=20/(20+20)=0.5
	equal := gate1Probability(20, 20, base)  // already off==def=20, share=0.5
	if math.Abs(capped-equal) > 1e-12 {
		t.Errorf("off>def not capped to def: gate1(80,20)=%v != gate1(20,20)=%v", capped, equal)
	}
}

// TestGate1Probability_DegenerateAndClamp covers the denom guard and the [0,1] clamp.
func TestGate1Probability_DegenerateAndClamp(t *testing.T) {
	for _, base := range []float64{0, 50} {
		if got := gate1Probability(0, 0, base); math.IsNaN(got) || math.IsInf(got, 0) || got < 0 || got > 1 {
			t.Errorf("gate1Probability(0,0,%v) = %v, want finite in [0,1]", base, got)
		}
	}
	// An out-of-range baseline drives value>100 → clamp to 1.0 (defensive bound — the
	// faithful baseline is ≤100, but the clamp must hold).
	if got := gate1Probability(50, 50, 300); got != 1.0 {
		t.Errorf("value>100 should clamp to 1.0, got %v", got)
	}
	// A negative baseline drives value<0 → clamp to 0.0.
	if got := gate1Probability(50, 50, -300); got != 0.0 {
		t.Errorf("value<0 should clamp to 0.0, got %v", got)
	}
}

// TestLeagueReboundBaseline_AllZeroRatings asserts the all-zero-rated bundle returns the
// neutral 50.0 share with no divide-by-zero, and a normal bundle returns the league ORB
// share × 100 in (0,100).
func TestLeagueReboundBaseline_AllZeroRatings(t *testing.T) {
	zero := bundle.Bundle{Players: []bundle.Player{{PID: 1, ORB: 0, DRB: 0}, {PID: 2, ORB: 0, DRB: 0}}}
	if got := leagueReboundBaseline(zero); got != 50.0 {
		t.Errorf("all-zero-rated bundle baseline = %v, want neutral 50.0", got)
	}
	if got := leagueReboundBaseline(bundle.Bundle{}); got != 50.0 {
		t.Errorf("empty bundle baseline = %v, want neutral 50.0", got)
	}
	// richBundle: ORB=20, DRB=35 per player ⇒ share = 20/55 ×100 ≈ 36.36.
	if got := leagueReboundBaseline(richBundle()); math.Abs(got-20.0/55.0*100) > 1e-9 {
		t.Errorf("richBundle baseline = %v, want %v", got, 20.0/55.0*100)
	}
}

// --- matrix #20: defender weighting, OREB floor/ceil, rebounder weighting --

func TestOrebProbability_FloorCeilEqual(t *testing.T) {
	if got := orebProbability(0, 100); math.Abs(got-0.25) > 1e-9 {
		t.Errorf("weak offense OREB = %v, want 0.25 floor", got)
	}
	if got := orebProbability(100, 0); math.Abs(got-0.75) > 1e-9 {
		t.Errorf("dominant offense OREB = %v, want 0.75 ceil", got)
	}
	if got := orebProbability(50, 50); math.Abs(got-0.5) > 1e-9 {
		t.Errorf("equal OREB = %v, want 0.50", got)
	}
}

func TestSelectDefender_FavorsHigherRating(t *testing.T) {
	d := &teamState{players: []onCourt{
		oc(slotPG, bundle.Player{PID: 1, OD: 2, Stamina: 50}),
		oc(slotSG, bundle.Player{PID: 2, OD: 9, Stamina: 50}), // strongest perimeter D
		oc(slotSF, bundle.Player{PID: 3, OD: 2, Stamina: 50}),
	}}
	r := rng.New(13)
	counts := map[int]int{}
	for i := 0; i < 6000; i++ {
		counts[selectDefender(d, playOutside, r).PID]++
	}
	if counts[2] <= counts[1] || counts[2] <= counts[3] {
		t.Errorf("higher OD defender not favored: %v", counts)
	}
}

func TestSelectRebounder_FavorsHigherRating(t *testing.T) {
	tm := &teamState{players: []onCourt{
		oc(slotPG, bundle.Player{PID: 1, DRB: 10, Stamina: 50}),
		oc(slotC, bundle.Player{PID: 2, DRB: 90, Stamina: 50}), // dominant rebounder
		oc(slotSF, bundle.Player{PID: 3, DRB: 10, Stamina: 50}),
	}}
	r := rng.New(17)
	counts := map[int]int{}
	for i := 0; i < 6000; i++ {
		counts[selectRebounder(tm, false, r).PID]++
	}
	if counts[2] <= counts[1] || counts[2] <= counts[3] {
		t.Errorf("dominant rebounder not favored: %v", counts)
	}
}

// --- matrix #21: boundary — equal off/def → .50; all-1s no divide-by-zero --

func TestRebound_Boundaries(t *testing.T) {
	// Equal-zero strengths must not divide by zero and must give .50.
	if got := orebProbability(0, 0); math.Abs(got-0.5) > 1e-9 {
		t.Errorf("zero/zero OREB = %v, want 0.50", got)
	}
	// All-zero rebound ratings: ratings floor to 1, so selection still works.
	tm := &teamState{players: []onCourt{
		oc(slotPG, bundle.Player{PID: 1}),
		oc(slotC, bundle.Player{PID: 2}),
	}}
	r := rng.New(19)
	for i := 0; i < 1000; i++ {
		got := selectRebounder(tm, true, r)
		if got.PID != 1 && got.PID != 2 {
			t.Fatalf("invalid rebounder PID %d", got.PID)
		}
	}
}

// orbContinuationRate runs N seeded full sims of richBundle and returns the observed
// offensive-rebound fraction (offensive rebound events / all rebound events) — the
// live ORB-continuation rate the swap moves. The richBundle path is deterministic in
// the seed, so the rate is stable across runs.
func orbContinuationRate(t *testing.T, unfaithful bool, n uint64) float64 {
	t.Helper()
	b := richBundle()
	var off, total int
	for seed := uint64(0); seed < n; seed++ {
		var res result.Result
		if unfaithful {
			r, err := SimulateWith(b, seed, Options{Freeze: FreezeConfig{UnfaithfulOreb: true}})
			if err != nil {
				t.Fatalf("SimulateWith(UnfaithfulOreb) seed %d: %v", seed, err)
			}
			res = r
		} else {
			res = Simulate(b, seed)
		}
		for _, g := range res.Games {
			for _, e := range g.Events {
				if e.Kind == result.EventRebound {
					total++
					if e.OffensiveRebound {
						off++
					}
				}
			}
		}
	}
	if total == 0 {
		t.Fatal("no rebound events observed")
	}
	return float64(off) / float64(total)
}

// TestOrebContinuation_LiveGate1Rate is the hard correctness gate for the ADR-0058
// swap: the live ORB-continuation roll is now the faithful sqrt gate-1 by default, and
// the UnfaithfulOreb escape hatch restores the old linear gate-2 path.
//
// The bands are pinned from the MEASURED richBundle rates (synthetic baseline 36.36 —
// NOT the real-corpus archive aggregates 0.158/0.194, which are on a different scale).
// Measured over N=2000 seeded sims: faithful gate-1 ≈ 0.3639, gate-2 ≈ 0.4326. Both
// are deterministic in the seed set, so the bands are tight.
func TestOrebContinuation_LiveGate1Rate(t *testing.T) {
	const n = 2000

	// 1) Faithful default (gate-1) inside the measured band.
	faithful := orbContinuationRate(t, false, n)
	if faithful < 0.355 || faithful > 0.373 {
		t.Errorf("faithful gate-1 ORB rate = %.5f, want in [0.355, 0.373] (measured ≈0.3639)", faithful)
	}

	// 3) Negative path: UnfaithfulOreb restores the old gate-2 rate (measured band).
	gate2 := orbContinuationRate(t, true, n)
	if gate2 < 0.423 || gate2 > 0.443 {
		t.Errorf("UnfaithfulOreb gate-2 ORB rate = %.5f, want in [0.423, 0.443] (measured ≈0.4326)", gate2)
	}

	// 2) The swap is live: the faithful rate is DISTINCTLY below the gate-2 rate (the
	// sqrt diminishing-returns + off≤def cap suppress the offensive board share). A
	// gated-baseline regression (or a no-op swap) would collapse this margin.
	if margin := gate2 - faithful; margin < 0.04 {
		t.Errorf("faithful gate-1 (%.5f) not distinctly below gate-2 (%.5f): margin %.5f < 0.04", faithful, gate2, margin)
	}
}
