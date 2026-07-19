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
	// The aggregates are 0 because the fixture carries zero DefAST48/
	// NonMatchedTerm and a zero league array — not because the phase is
	// unimplemented (see TestMatchupQualityPhase3 for the live-aggregate path).
	got := matchupQuality(oc(slotPG, bundle.Player{FGP: 50, Stamina: 50}), threeDefenders(), [6]float64{})
	if math.Abs(got-(-0.02)) > 1e-9 {
		t.Errorf("matchupQuality(50) = %v, want -0.02", got)
	}
	// Deterministic: identical inputs reproduce the result exactly.
	if again := matchupQuality(oc(slotPG, bundle.Player{FGP: 50, Stamina: 50}), threeDefenders(), [6]float64{}); again != got {
		t.Errorf("non-deterministic: %v vs %v", got, again)
	}
}

// --- matrix #14: boundary — zero composite defaults to 50; fatigue cap -----

func TestMatchupQuality_Boundaries(t *testing.T) {
	// Zero composite defaults to 50 → same −0.02.
	if got := matchupQuality(oc(slotPG, bundle.Player{FGP: 0, Stamina: 50}), threeDefenders(), [6]float64{}); math.Abs(got-(-0.02)) > 1e-9 {
		t.Errorf("zero composite = %v, want -0.02 (defaults to 50)", got)
	}
	// Fatigue caps at 1.0 even for very high energy — Phase-3 aggregates are 0
	// (fixture carries zero DefAST48/NonMatchedTerm/league array), so the
	// result is unaffected and stays finite.
	if got := matchupQuality(oc(slotPG, bundle.Player{FGP: 99, Stamina: 100000}), threeDefenders(), [6]float64{}); math.IsNaN(got) || math.IsInf(got, 0) {
		t.Errorf("result not finite for huge energy: %v", got)
	}
	// fatigueFactor itself must cap at 1.0.
	if got := fatigueFactor(100000); got != 1.0 {
		t.Errorf("fatigueFactor(huge) = %v, want 1.0", got)
	}
}

// --- J24 Phase 3: matched + non-matched defender terms ----------------------

func TestMatchupQualityPhase3(t *testing.T) {
	// bh at slotSF, FGP 60 → normalized = 60·0.2 − 9.9 = 2.1. energy 90 → fatigue 1.0.
	bh := oc(slotSF, bundle.Player{FGP: 60, Stamina: 90})
	defs := []onCourt{
		oc(slotSF, bundle.Player{DefAST48: 8.0, Stamina: 50}), // MATCHED (slot == bh.slot)
		oc(slotPG, bundle.Player{NonMatchedTerm: 1.0, Stamina: 50}),
		oc(slotSG, bundle.Player{NonMatchedTerm: 2.0, Stamina: 50}),
		oc(slotPF, bundle.Player{NonMatchedTerm: -1.0, Stamina: 50}), // negative term allowed
		oc(slotC, bundle.Player{NonMatchedTerm: 0.5, Stamina: 50}),
	}
	league := [6]float64{}
	league[slotSF] = 5.0 // leagueAST48[SF]
	// matched  = (8.0 − 5.0)·0.8·1.0 = 2.4
	// nonMatch = (1.0 + 2.0 − 1.0 + 0.5)·1.0·1.0 = 2.5
	// acc = 4.9 ; return (4.9 + 0 − 2.1)·0.2 = 0.56
	got := matchupQuality(bh, defs, league)
	if math.Abs(got-0.56) > 1e-9 {
		t.Errorf("matchupQuality Phase3 = %v, want 0.56", got)
	}
}

// TestMatchupQualityPhase3_NoMatchedDefender (P4b boundary): no defender at
// bh.slot → the matched arm is absent; the result equals the non-matched-only
// value.
func TestMatchupQualityPhase3_NoMatchedDefender(t *testing.T) {
	// bh at slotPG; no defender occupies slotPG, so every defender is non-matched.
	bh := oc(slotPG, bundle.Player{FGP: 60, Stamina: 90})
	defs := []onCourt{
		oc(slotSG, bundle.Player{NonMatchedTerm: 1.0, Stamina: 50}),
		oc(slotSF, bundle.Player{NonMatchedTerm: 2.0, Stamina: 50}),
		oc(slotPF, bundle.Player{NonMatchedTerm: -1.0, Stamina: 50}),
		oc(slotC, bundle.Player{NonMatchedTerm: 0.5, Stamina: 50}),
	}
	league := [6]float64{}
	// normalized = 60·0.2 − 9.9 = 2.1. fatigue = 1.0.
	// nonMatch = (1.0 + 2.0 − 1.0 + 0.5)·1.0·1.0 = 2.5
	// return (2.5 + 0 − 2.1)·0.2 = 0.08
	got := matchupQuality(bh, defs, league)
	want := 0.08
	if math.Abs(got-want) > 1e-9 {
		t.Errorf("matchupQuality (no matched defender) = %v, want %v", got, want)
	}
}

// TestMatchupQualityPhase3_ZeroLeagueBucket (P4b boundary): a zero
// leagueAST48ByPos bucket at bh.slot makes the matched arm reduce to
// DefAST48·0.8·fatigue — no NaN/Inf, no divide-by-zero.
func TestMatchupQualityPhase3_ZeroLeagueBucket(t *testing.T) {
	bh := oc(slotSF, bundle.Player{FGP: 60, Stamina: 90})
	defs := []onCourt{
		oc(slotSF, bundle.Player{DefAST48: 8.0, Stamina: 50}), // MATCHED
	}
	league := [6]float64{} // league[slotSF] == 0 — zero bucket
	// matched = (8.0 − 0)·0.8·1.0 = 6.4
	// normalized = 2.1
	// return (6.4 + 0 − 2.1)·0.2 = 0.86
	got := matchupQuality(bh, defs, league)
	want := 0.86
	if math.Abs(got-want) > 1e-9 {
		t.Errorf("matchupQuality (zero league bucket) = %v, want %v", got, want)
	}
	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Errorf("matchupQuality (zero league bucket) not finite: %v", got)
	}
}
