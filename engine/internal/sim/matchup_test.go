package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

func threeDefenders() []onCourt {
	return []onCourt{
		oc(slotPG, bundle.Player{Stamina: 50}),
		oc(slotSG, bundle.Player{Stamina: 50}),
		oc(slotC, bundle.Player{Stamina: 50}),
	}
}

// --- matrix #13: 4-phase deterministic; default-50 → 0.1 normalized --------

func TestMatchupQuality_DefaultComposite(t *testing.T) {
	// composite 50 → normalized 0.1 → result = (0 + 0 − 0.1) × 0.2 = −0.02.
	got := matchupQuality(50, 50, threeDefenders())
	if math.Abs(got-(-0.02)) > 1e-9 {
		t.Errorf("matchupQuality(50) = %v, want -0.02", got)
	}
	// Deterministic: identical inputs reproduce the result exactly.
	if again := matchupQuality(50, 50, threeDefenders()); again != got {
		t.Errorf("non-deterministic: %v vs %v", got, again)
	}
}

// --- matrix #14: boundary — zero composite defaults to 50; fatigue cap -----

func TestMatchupQuality_Boundaries(t *testing.T) {
	// Zero composite defaults to 50 → same −0.02.
	if got := matchupQuality(0, 50, threeDefenders()); math.Abs(got-(-0.02)) > 1e-9 {
		t.Errorf("zero composite = %v, want -0.02 (defaults to 50)", got)
	}
	// Fatigue caps at 1.0 even for very high energy — Phase-3 aggregates are 0
	// in PR3a, so the result is unaffected and stays finite.
	if got := matchupQuality(99, 100000, threeDefenders()); math.IsNaN(got) || math.IsInf(got, 0) {
		t.Errorf("result not finite for huge energy: %v", got)
	}
	// fatigueFactor itself must cap at 1.0.
	if got := fatigueFactor(100000); got != 1.0 {
		t.Errorf("fatigueFactor(huge) = %v, want 1.0", got)
	}
}
