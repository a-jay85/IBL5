package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #17: bucket selection at boundaries + turnover formula ---------

func TestSelectOutcome_Proportional(t *testing.T) {
	// 2pt:3pt weights 70:30, no other buckets, so no code-3 or code-4 pick.
	in := outcomeInputs{twoPtWeight: 70, threePtWeight: 30}
	r := rng.New(2)
	const n = 60000
	counts := map[outcomeCode]int{}
	for i := 0; i < n; i++ {
		counts[selectOutcome(in, false, false, false, false, r)]++
	}
	frac2 := float64(counts[outcome2pt]) / n
	if frac2 < 0.68 || frac2 > 0.72 {
		t.Errorf("2pt frac = %.3f, want ≈ 0.70", frac2)
	}
	if counts[outcomeTurnover] != 0 {
		t.Errorf("turnover should be impossible with turnover weight 0, got %d", counts[outcomeTurnover])
	}
}

// --- matrix #18: boundaries — forced modes, single bucket, turnover --------

func TestSelectOutcome_ForcedModes(t *testing.T) {
	// All four path weights present; turnover disabled to isolate the path set.
	in := outcomeInputs{twoPtWeight: 10, threePtWeight: 10, foulWeight: 10, turnoverWeight: 10}
	r := rng.New(6)

	assertIn := func(name string, forcedMake, shotClock, steal bool, allowed map[outcomeCode]bool) {
		for i := 0; i < 5000; i++ {
			got := selectOutcome(in, forcedMake, shotClock, steal, false, r)
			if !allowed[got] {
				t.Fatalf("%s: produced disallowed outcome %d", name, got)
			}
		}
	}
	// forced_make → {2pt-attempt(1), and-one(3)} only; turnover suppressed.
	assertIn("forced_make", true, false, false, map[outcomeCode]bool{outcome2pt: true, outcomeFoul: true})
	// shot_clock → {3pt-attempt(2), foul-only(4)} only (turnover off here).
	assertIn("shot_clock", false, true, false, map[outcomeCode]bool{outcome3pt: true, outcomeTurnover: true})
	// steal/transition play → never a 3pt attempt(2).
	for i := 0; i < 5000; i++ {
		if selectOutcome(in, false, false, true, false, r) == outcome3pt {
			t.Fatal("steal play must never be a 3pt attempt")
		}
	}
}

// TestSelectOutcome_ShotClockW4Rescale is the discriminating test for the port:
// with the rescale applied, the shot-clock 3pt share is w1/(w1+w4), INDEPENDENT
// of w2 (threePtWeight). The suppression arm proves the escape hatch restores the
// pre-port w2-dependent share. (JSB 5.60 @0x4e1e93–0x4e1ebf.)
func TestSelectOutcome_ShotClockW4Rescale(t *testing.T) {
	// share of outcome3pt over n shot-clock draws at the given w2 and suppression.
	share := func(threePt float64, suppress bool, seed uint64) float64 {
		in := outcomeInputs{twoPtWeight: 4, threePtWeight: threePt, foulWeight: 0, turnoverWeight: 1}
		r := rng.New(seed)
		const n = 40000
		three := 0
		for i := 0; i < n; i++ {
			if selectOutcome(in, false, true, false, suppress, r) == outcome3pt {
				three++
			}
		}
		return float64(three) / n
	}
	const band = 0.02
	// Rescale ON: share = w1/(w1+w4) = 4/5 = 0.80, independent of w2.
	lo := share(0.5, false, 11) // pre-port this would be 0.5/1.5 ≈ 0.33
	hi := share(5.0, false, 12) // pre-port this would be 5/6 ≈ 0.83
	for _, tc := range []struct {
		name string
		got  float64
	}{{"w2=0.5", lo}, {"w2=5.0", hi}} {
		if tc.got < 0.80-band || tc.got > 0.80+band {
			t.Errorf("rescale-on 3pt share (%s) = %.3f, want ≈ 0.80 = w1/(w1+w4)", tc.name, tc.got)
		}
	}
	if d := lo - hi; d < -band || d > band {
		t.Errorf("rescale-on shares must be w2-invariant: w2=0.5 → %.3f, w2=5.0 → %.3f (Δ %.3f)", lo, hi, d)
	}
	// Suppression ON: baseline restored → share = w2/(w2+w4), moves with w2.
	sLo := share(0.5, true, 13) // 0.5/1.5 ≈ 0.333
	sHi := share(5.0, true, 14) // 5/6 ≈ 0.833
	if sLo < 0.333-band || sLo > 0.333+band {
		t.Errorf("suppressed 3pt share (w2=0.5) = %.3f, want ≈ 0.333 = w2/(w2+w4)", sLo)
	}
	if sHi < 0.833-band || sHi > 0.833+band {
		t.Errorf("suppressed 3pt share (w2=5.0) = %.3f, want ≈ 0.833 = w2/(w2+w4)", sHi)
	}
}

// TestSelectOutcome_ShotClockRescale_DegenerateWeights covers the guard's negative
// paths. It asserts BEHAVIORALLY (observed outcome fractions), never on the locals
// total/roll: a NaN roll would return allowed[0] (3pt) unconditionally — 100% 3pt,
// cleanly separated from the correct 0.50 split.
func TestSelectOutcome_ShotClockRescale_DegenerateWeights(t *testing.T) {
	// counts outcome3pt and outcomeTurnover over n shot-clock draws (suppress=false).
	counts := func(in outcomeInputs, seed uint64) (three, foul, n int) {
		r := rng.New(seed)
		n = 40000
		for i := 0; i < n; i++ {
			switch selectOutcome(in, false, true, false, false, r) {
			case outcome3pt:
				three++
			case outcomeTurnover:
				foul++
			}
		}
		return
	}
	// 1. w1=0: the guard's reason for existing. Without in.twoPtWeight>0 this would be
	// +Inf foul weight → NaN roll → 100% 3pt. Guarded → no rescale → split ≈ 0.50.
	if three, foul, n := counts(outcomeInputs{twoPtWeight: 0, threePtWeight: 1, turnoverWeight: 1}, 21); three == 0 || foul == 0 || float64(three)/float64(n) < 0.47 || float64(three)/float64(n) > 0.53 {
		t.Errorf("w1=0: 3pt=%d foul=%d/%d, want split ≈ 0.50 with both codes (rescale must not fire)", three, foul, n)
	}
	// 2. w2=0: matches 5.60's own w2>0 gate; rescale must not fire, foulOnlyWeight
	// unchanged. 3pt bucket weight is 0 → ≈100% foul-only. If the rescale WRONGLY
	// fired it would zero foulOnly → total 0 → 100% 3pt, the opposite extreme.
	if three, foul, n := counts(outcomeInputs{twoPtWeight: 4, threePtWeight: 0, turnoverWeight: 1}, 22); float64(three)/float64(n) > 0.02 || foul == 0 {
		t.Errorf("w2=0: 3pt=%d foul=%d/%d, want ≈0%% 3pt (rescale must not fire)", three, foul, n)
	}
	// 3. w1<0: the guard is >0, so a negative w1 must not flip the ratio; no rescale
	// → split stays at 0.50.
	if three, foul, n := counts(outcomeInputs{twoPtWeight: -3, threePtWeight: 1, turnoverWeight: 1}, 23); three == 0 || foul == 0 || float64(three)/float64(n) < 0.47 || float64(three)/float64(n) > 0.53 {
		t.Errorf("w1<0: 3pt=%d foul=%d/%d, want split ≈ 0.50 with both codes (rescale must not fire)", three, foul, n)
	}
	// 4. shotClock=false: the rescale block never runs, so the suppression flag is
	// inert — identical weights + seed produce an IDENTICAL code sequence whether the
	// flag is true or false. (Scope: selectOutcome's own output for identical inputs;
	// whole-game golden output DOES move because shot-clock possessions take a new path.)
	inHalf := outcomeInputs{twoPtWeight: 1, threePtWeight: 10, turnoverWeight: 5, foulWeight: 2}
	rOff := rng.New(24)
	rOn := rng.New(24)
	for i := 0; i < 40000; i++ {
		a := selectOutcome(inHalf, false, false, false, false, rOff)
		b := selectOutcome(inHalf, false, false, false, true, rOn)
		if a != b {
			t.Fatalf("shotClock=false: flag changed output at i=%d: false→%d true→%d", i, a, b)
		}
	}
}

func TestSelectOutcome_SingleBucket(t *testing.T) {
	// Only the foul-only weight is nonzero → always selected (roll at 0..total).
	in := outcomeInputs{turnoverWeight: 5}
	r := rng.New(8)
	for i := 0; i < 2000; i++ {
		if got := selectOutcome(in, false, false, false, false, r); got != outcomeTurnover {
			t.Fatalf("single-bucket selection = %d, want foul-only", got)
		}
	}
}
