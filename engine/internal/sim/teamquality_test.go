package sim

import (
	"math"
	"testing"
)

// fiveStarters builds a 5-man lineup of identical starters (mkPlayer defaults:
// OO=6, OD=5) for the given team, used to exercise the lineup aggregators.
func fiveStarters(team int) []onCourt {
	var lineup []onCourt
	for slot := slotPG; slot <= slotC; slot++ {
		lineup = append(lineup, oc(slot, mkPlayer(slot, team, slot, 48)))
	}
	return lineup
}

const teamQualityEps = 1e-9

// --- matrix #6,7,8: compressQuality — identity / narrowing / fixed point -----

// TestCompressQuality locks the three design properties of the foulCompress
// transform compressQuality(total, neutral, factor) = total + (factor−1)(total−neutral):
//
//	#6 identity     — factor == 1.0 is the EXACT (bit-stable) identity, any neutral.
//	#7 narrowing    — factor < 1.0 strictly pulls total toward neutral, and two
//	                  points straddling neutral converge: their spread scales by factor.
//	#8 fixed point  — a total exactly AT neutral is unchanged by ANY factor
//	                  (mean-preservation reference).
func TestCompressQuality(t *testing.T) {
	t.Run("identity at factor 1.0 (exact)", func(t *testing.T) {
		for _, tc := range []struct{ total, neutral float64 }{
			{1.77, 1.7253}, {0.25, 8.21}, {123.75, 6.0}, {0, 5}, {-2, 3},
		} {
			if got := compressQuality(tc.total, tc.neutral, 1.0); got != tc.total {
				t.Errorf("compressQuality(%v,%v,1.0) = %v, want EXACT %v", tc.total, tc.neutral, got, tc.total)
			}
		}
	})
	t.Run("narrowing toward neutral for factor < 1.0", func(t *testing.T) {
		const neutral = 1.7253
		const factor = 0.4
		hi, lo := 3.0, 0.5 // straddle the neutral
		cHi := compressQuality(hi, neutral, factor)
		cLo := compressQuality(lo, neutral, factor)
		// Each pulled toward neutral (strictly inside the original distance).
		if !(math.Abs(cHi-neutral) < math.Abs(hi-neutral) && math.Abs(cLo-neutral) < math.Abs(lo-neutral)) {
			t.Errorf("compression did not pull toward neutral: hi %v→%v lo %v→%v (neutral %v)", hi, cHi, lo, cLo, neutral)
		}
		// The pair's spread scales by exactly the factor (the dispersion-narrowing claim).
		if math.Abs((cHi-cLo)-factor*(hi-lo)) > teamQualityEps {
			t.Errorf("spread %v did not scale by factor %v× original %v", cHi-cLo, factor, hi-lo)
		}
	})
	t.Run("fixed point at neutral for any factor", func(t *testing.T) {
		const neutral = 8.21
		for _, factor := range []float64{1.0, 0.5, 0.34, 0.1, 0.0} {
			if got := compressQuality(neutral, neutral, factor); math.Abs(got-neutral) > teamQualityEps {
				t.Errorf("compressQuality(neutral,neutral,%v) = %v, want neutral %v", factor, got, neutral)
			}
		}
	})
	// Concrete LITERAL anchors (expected values hand-computed independently of the
	// implementation), so a broken compressQuality formula fails here even though the
	// formula-mirroring composition tests below would still pass.
	t.Run("concrete literals", func(t *testing.T) {
		for _, tc := range []struct {
			total, neutral, factor, want float64
		}{
			{10, 0, 0.5, 5.0},         // 0 + 0.5·(10−0)
			{10, 4, 0.5, 7.0},         // 4 + 0.5·(10−4)
			{2, 8, 0.25, 6.5},         // 8 + 0.25·(2−8) = 8 − 1.5
			{6.25, 8.21, 0.45, 7.328}, // the committed def OD=5 pre-cap value
		} {
			if got := compressQuality(tc.total, tc.neutral, tc.factor); math.Abs(got-tc.want) > 1e-9 {
				t.Errorf("compressQuality(%v,%v,%v) = %v, want %v", tc.total, tc.neutral, tc.factor, got, tc.want)
			}
		}
	})
}

// --- matrix #3,7: defMatchupQuality — compose(compress, cap) -----------------

func TestDefMatchupQuality_SumAndCap(t *testing.T) {
	ceiling := teamDefBaseline * defQualityCapTeamMult * defQualityCapMultiplier

	// Exact composition: defMatchupQuality = min(compress(rawΣ, defQualityNeutral,
	// foulCompress), ceiling). Asserted symbolically so it holds at any committed
	// foulCompress. OD=5 (raw 6.25) exercises the (possibly-)uncapped branch.
	def := fiveStarters(7)
	rawSum := 5 * floor1(5) * defQualityRatingScale // 5 × 5 × 0.25 = 6.25
	want := compressQuality(rawSum, defQualityNeutral, foulCompress)
	if want > ceiling {
		want = ceiling
	}
	if got := defMatchupQuality(def); math.Abs(got-want) > teamQualityEps {
		t.Errorf("defMatchupQuality(OD=5) = %.4f, want compose(compress,cap) = %.4f", got, want)
	}

	// Capped: 5 defenders at OD=99 → raw 123.75, compressed toward defQualityNeutral
	// is still far above the ceiling for any factor>0 → returns ceiling.
	hi := fiveStarters(7)
	for i := range hi {
		hi[i].OD = 99
	}
	rawHi := 5 * floor1(99) * defQualityRatingScale
	if compressQuality(rawHi, defQualityNeutral, foulCompress) <= ceiling {
		t.Fatalf("test setup: compressed high-OD %.3f should exceed ceiling %.3f", compressQuality(rawHi, defQualityNeutral, foulCompress), ceiling)
	}
	if got := defMatchupQuality(hi); math.Abs(got-ceiling) > teamQualityEps {
		t.Errorf("defMatchupQuality (capped) = %.4f, want ceiling %.4f", got, ceiling)
	}
}

// --- matrix #3: offQualityWithHCA — summation + HCA-subtraction sign --------

func TestOffQualityWithHCA_SubtractionSign(t *testing.T) {
	off := fiveStarters(3)

	neutral := offQualityWithHCA(off, 0)
	home := offQualityWithHCA(off, hcaMagnitude)  // +0.2 per player → Σ shrinks
	away := offQualityWithHCA(off, -hcaMagnitude) // −0.2 per player → Σ grows

	// At neutral HCA the divisor is the volume-neutral constant itself: no per-player
	// OO read, no off-side compression (ADR-0061). Asserted against the symbol so the
	// Phase-6 calibration swap does not break this test.
	wantNeutral := offQualityConstant
	if math.Abs(neutral-wantNeutral) > teamQualityEps {
		t.Errorf("offQualityWithHCA(neutral) = %.4f, want offQualityConstant = %.4f", neutral, wantNeutral)
	}

	// The home/away delta is EXACTLY len×hcaMagnitude (= 5×0.2 = 1.0) — the
	// #955-calibrated HCA magnitude is preserved (the constant base is volume-neutral,
	// so the fixed subtraction is the whole home/away delta). This shrinking divisor
	// for the home team is the home-favorable mechanism.
	if math.Abs((neutral-home)-1.0) > teamQualityEps {
		t.Errorf("home Σ = %.4f, want neutral − 1.0 = %.4f (HCA must be unscaled by foulCompress)", home, neutral-1.0)
	}
	if math.Abs((away-neutral)-1.0) > teamQualityEps {
		t.Errorf("away Σ = %.4f, want neutral + 1.0 = %.4f", away, neutral+1.0)
	}
	if !(home < neutral && neutral < away) {
		t.Errorf("expected home(%.4f) < neutral(%.4f) < away(%.4f) — HCA divisor sign wrong", home, neutral, away)
	}
}

// --- matrix #9,10 (boundary): floor guards a non-positive divisor ------------

func TestOffQualityWithHCA_Floor(t *testing.T) {
	// The divisor base is now the volume-neutral constant, so the floor binds only
	// when the HCA subtraction len×hcaDelta drops it below offQualityFloor. A normal
	// home lineup (offQualityConstant − 5×0.2) stays well above the floor; force the
	// degenerate case with an oversized per-player HCA delta so the clamp guards the
	// division (no divide-by-zero, no sign flip, no NaN/Inf).
	off := fiveStarters(3)

	// Below-floor: 5 players × 0.30 = 1.5 subtracted. offQualityConstant − 1.5 is below
	// offQualityFloor (0.25) for every swept constant in the calibration range
	// (≤ 1.65 − 1.5 = 0.15 < 0.25), so the clamp binds and returns exactly the floor.
	const bigHCA = 0.30
	composed := offQualityConstant - 5*bigHCA
	if composed >= offQualityFloor {
		t.Fatalf("test setup: offQualityConstant %.4f − 5×%.2f = %.4f is not below floor %.4f; raise bigHCA",
			offQualityConstant, bigHCA, composed, offQualityFloor)
	}
	if got := offQualityWithHCA(off, bigHCA); math.Abs(got-offQualityFloor) > teamQualityEps {
		t.Errorf("below-floor divisor → got %.4f, want clamp to offQualityFloor %.4f", got, offQualityFloor)
	}

	// Above-floor: the neutral divisor is the constant itself, unclamped.
	if got := offQualityWithHCA(off, 0); math.Abs(got-offQualityConstant) > teamQualityEps {
		t.Errorf("neutral divisor → got %.4f, want offQualityConstant %.4f (unclamped)", got, offQualityConstant)
	}

	// Invariant: never below the floor, always finite (the guarantee the foul
	// divisor relies on).
	got := offQualityWithHCA(off, bigHCA)
	if got < offQualityFloor || math.IsNaN(got) || math.IsInf(got, 0) {
		t.Errorf("offQualityWithHCA = %v, want a finite value ≥ offQualityFloor %.4f", got, offQualityFloor)
	}
}
