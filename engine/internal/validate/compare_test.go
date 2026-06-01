package validate

import "testing"

// Row #3: compareStat uses max(absFloor, relPct×mean) — the relative term
// dominates for large means, the floor for small ones — and passes when the
// .sco value is inside that band.
func TestCompareStat_BandSelection(t *testing.T) {
	b := Band{RelPct: 0.15, AbsFloor: 8}

	// Large mean: relative band (0.15×100 = 15) dominates the floor (8).
	if pass, _ := compareStat("points", 112, 100, b); !pass {
		t.Error("sco 112 vs mean 100 should pass (within ±15 relative band)")
	}
	if pass, _ := compareStat("points", 120, 100, b); pass {
		t.Error("sco 120 vs mean 100 should FAIL (outside ±15 relative band)")
	}

	// Small mean: floor (8) dominates the relative term (0.15×4 = 0.6).
	if pass, _ := compareStat("ftm", 11, 4, b); !pass {
		t.Error("sco 11 vs mean 4 should pass (within ±8 floor band)")
	}
	if pass, _ := compareStat("ftm", 13, 4, b); pass {
		t.Error("sco 13 vs mean 4 should FAIL (outside ±8 floor band)")
	}
}

// Row #4: the band edge is INCLUSIVE — a value exactly at the boundary passes,
// one epsilon beyond fails.
func TestCompareStat_InclusiveEdge(t *testing.T) {
	b := Band{RelPct: 0.10, AbsFloor: 5} // mean 50 -> band = max(5, 5) = 5
	mean := 50.0

	if pass, _ := compareStat("x", mean+5, mean, b); !pass {
		t.Error("value exactly at the band edge (+5) must pass (inclusive)")
	}
	if pass, _ := compareStat("x", mean-5, mean, b); !pass {
		t.Error("value exactly at the lower band edge (-5) must pass (inclusive)")
	}
	const eps = 1e-9
	if pass, _ := compareStat("x", mean+5+eps, mean, b); pass {
		t.Error("value one epsilon beyond the edge must FAIL")
	}
}
