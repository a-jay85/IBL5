package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #17: bucket selection at boundaries + turnover formula ---------

func TestSelectOutcome_Proportional(t *testing.T) {
	// 2pt:3pt weights 70:30, no other buckets, no turnover.
	in := outcomeInputs{twoPtWeight: 70, threePtWeight: 30}
	r := rng.New(2)
	const n = 60000
	counts := map[outcomeCode]int{}
	for i := 0; i < n; i++ {
		counts[selectOutcome(in, false, false, false, r)]++
	}
	frac2 := float64(counts[outcome2pt]) / n
	if frac2 < 0.68 || frac2 > 0.72 {
		t.Errorf("2pt frac = %.3f, want ≈ 0.70", frac2)
	}
	if counts[outcomeTurnover] != 0 {
		t.Errorf("turnover should be impossible with def value 0, got %d", counts[outcomeTurnover])
	}
}

func TestSelectOutcome_TurnoverFormula(t *testing.T) {
	r := rng.New(4)
	// sqrt(turnoverDefValue) = 1793 ≥ every rand_int(1,1793) → always turnover.
	always := outcomeInputs{twoPtWeight: 100, turnoverDefValue: 1793 * 1793}
	for i := 0; i < 3000; i++ {
		if selectOutcome(always, false, false, false, r) != outcomeTurnover {
			t.Fatal("max def value should force a turnover")
		}
	}
	// Zero def value → never a turnover.
	never := outcomeInputs{twoPtWeight: 100, turnoverDefValue: 0}
	for i := 0; i < 3000; i++ {
		if selectOutcome(never, false, false, false, r) == outcomeTurnover {
			t.Fatal("zero def value should never turn over")
		}
	}
}

// --- matrix #18: boundaries — forced modes, single bucket, turnover --------

func TestSelectOutcome_ForcedModes(t *testing.T) {
	// All four path weights present; turnover disabled to isolate the path set.
	in := outcomeInputs{twoPtWeight: 10, threePtWeight: 10, andOneWeight: 10, foulOnlyWeight: 10}
	r := rng.New(6)

	assertIn := func(name string, forcedMake, shotClock, steal bool, allowed map[outcomeCode]bool) {
		for i := 0; i < 5000; i++ {
			got := selectOutcome(in, forcedMake, shotClock, steal, r)
			if !allowed[got] {
				t.Fatalf("%s: produced disallowed outcome %d", name, got)
			}
		}
	}
	// forced_make → {2pt-attempt(1), and-one(3)} only; turnover suppressed.
	assertIn("forced_make", true, false, false, map[outcomeCode]bool{outcome2pt: true, outcomeAndOne: true})
	// shot_clock → {3pt-attempt(2), foul-only(4)} only (turnover off here).
	assertIn("shot_clock", false, true, false, map[outcomeCode]bool{outcome3pt: true, outcomeFoulOnly: true})
	// steal/transition play → never a 3pt attempt(2).
	for i := 0; i < 5000; i++ {
		if selectOutcome(in, false, false, true, r) == outcome3pt {
			t.Fatal("steal play must never be a 3pt attempt")
		}
	}
}

func TestSelectOutcome_SingleBucket(t *testing.T) {
	// Only the foul-only weight is nonzero → always selected (roll at 0..total).
	in := outcomeInputs{foulOnlyWeight: 5}
	r := rng.New(8)
	for i := 0; i < 2000; i++ {
		if got := selectOutcome(in, false, false, false, r); got != outcomeFoulOnly {
			t.Fatalf("single-bucket selection = %d, want foul-only", got)
		}
	}
}
