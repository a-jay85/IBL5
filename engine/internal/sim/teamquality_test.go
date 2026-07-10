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
