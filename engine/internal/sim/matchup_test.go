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
	got := matchupQuality(oc(slotPG, bundle.Player{FGP: 50, Stamina: 50}), threeDefenders(), [6]float64{}, [6]bool{}, [6]bool{})
	if math.Abs(got-(-0.02)) > 1e-9 {
		t.Errorf("matchupQuality(50) = %v, want -0.02", got)
	}
	// Deterministic: identical inputs reproduce the result exactly.
	if again := matchupQuality(oc(slotPG, bundle.Player{FGP: 50, Stamina: 50}), threeDefenders(), [6]float64{}, [6]bool{}, [6]bool{}); again != got {
		t.Errorf("non-deterministic: %v vs %v", got, again)
	}
}

// --- matrix #14: boundary — zero composite defaults to 50; fatigue cap -----

func TestMatchupQuality_Boundaries(t *testing.T) {
	// Zero composite defaults to 50 → same −0.02.
	if got := matchupQuality(oc(slotPG, bundle.Player{FGP: 0, Stamina: 50}), threeDefenders(), [6]float64{}, [6]bool{}, [6]bool{}); math.Abs(got-(-0.02)) > 1e-9 {
		t.Errorf("zero composite = %v, want -0.02 (defaults to 50)", got)
	}
	// Fatigue caps at 1.0 even for very high energy — Phase-3 aggregates are 0
	// (fixture carries zero DefAST48/NonMatchedTerm/league array), so the
	// result is unaffected and stays finite.
	if got := matchupQuality(oc(slotPG, bundle.Player{FGP: 99, Stamina: 100000}), threeDefenders(), [6]float64{}, [6]bool{}, [6]bool{}); math.IsNaN(got) || math.IsInf(got, 0) {
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
	got := matchupQuality(bh, defs, league, [6]bool{}, [6]bool{})
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
	got := matchupQuality(bh, defs, league, [6]bool{}, [6]bool{})
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
	got := matchupQuality(bh, defs, league, [6]bool{}, [6]bool{})
	want := 0.86
	if math.Abs(got-want) > 1e-9 {
		t.Errorf("matchupQuality (zero league bucket) = %v, want %v", got, want)
	}
	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Errorf("matchupQuality (zero league bucket) not finite: %v", got)
	}
}

// --- J24 Phase 4: CEngine+0x33F0 usage-dominance accumulator ---------------
//
// Shared fixture for Phase-4 tests: bh slotSF FGP=60 Stamina=90 →
//   normalized=2.1, fatigue=1.0
// Matched defender at slotSF: DefAST48=8.0, league[slotSF]=5.0 →
//   matched arm = (8−5)·0.8·1.0 = 2.4
// Non-matched: PG NonMatchedTerm=1.0, SG=2.0, PF=−1.0, C=0.5 →
//   nonMatch = (1+2−1+0.5)·1.0 = 2.5
// acc (Phase 3) = 2.4 + 2.5 = 4.9
// phase3-only result = (4.9 + 0 − 2.1)·0.2 = 0.56

func phase4Fixture() (bh onCourt, defs []onCourt, league [6]float64) {
	bh = oc(slotSF, bundle.Player{FGP: 60, Stamina: 90})
	defs = []onCourt{
		oc(slotSF, bundle.Player{DefAST48: 8.0, NonMatchedTerm: 3.0, Stamina: 50}), // MATCHED at slotSF
		oc(slotPG, bundle.Player{NonMatchedTerm: 1.0, Stamina: 50}),
		oc(slotSG, bundle.Player{NonMatchedTerm: 2.0, Stamina: 50}),
		oc(slotPF, bundle.Player{NonMatchedTerm: -1.0, Stamina: 50}),
		oc(slotC, bundle.Player{NonMatchedTerm: 0.5, Stamina: 50}),
	}
	league[slotSF] = 5.0
	return
}

// TestMatchupQualityPhase4_BHFlagGate: when the ball-handler's slot is
// usage-dominant, the Phase-4 gate closes and phase4 == 0.
// acc=4.9, phase4=0 → (4.9+0−2.1)·0.2 = 0.56
func TestMatchupQualityPhase4_BHFlagGate(t *testing.T) {
	bh, defs, league := phase4Fixture()
	var offFlags, defFlags [6]bool
	offFlags[slotSF] = true // BH slot dominant → gate closed
	for i := 1; i <= 5; i++ {
		defFlags[i] = true // all defenders flagged (gate would fire if open)
	}
	got := matchupQuality(bh, defs, league, offFlags, defFlags)
	want := 0.56
	if math.Abs(got-want) > 1e-9 {
		t.Errorf("BH flag gate: got %v, want %v", got, want)
	}
}

// TestMatchupQualityPhase4_DefenderFlagPositive: gate open; PG and SG defenders
// flagged with positive NonMatchedTerm; C unflagged; PF negative (unflagged).
// phase4 = NonMatchedTerm[PG]=1.0 + NonMatchedTerm[SG]=2.0 = 3.0
// (4.9 + 3.0 − 2.1)·0.2 = 1.16
func TestMatchupQualityPhase4_DefenderFlagPositive(t *testing.T) {
	bh, defs, league := phase4Fixture()
	var offFlags, defFlags [6]bool
	defFlags[slotPG] = true // PG NonMatchedTerm=1.0 → accumulates
	defFlags[slotSG] = true // SG NonMatchedTerm=2.0 → accumulates
	// C (0.5) and PF (−1.0) unflagged; SF matched defender: Phase 4 uses its
	// NonMatchedTerm=3.0 when defFlags[slotSF]=false (it stays unflagged here).
	got := matchupQuality(bh, defs, league, offFlags, defFlags)
	want := 1.16
	if math.Abs(got-want) > 1e-9 {
		t.Errorf("defender positive flags: got %v, want %v", got, want)
	}
}

// TestMatchupQualityPhase4_NegativeExcluded: gate open; only PF flagged with a
// negative NonMatchedTerm=−1.0. Negative terms must NOT accumulate.
// phase4 = 0 → (4.9 + 0 − 2.1)·0.2 = 0.56
// Discriminator: if negatives were summed, result = (4.9−1.0−2.1)·0.2 = 0.36 ≠ 0.56
func TestMatchupQualityPhase4_NegativeExcluded(t *testing.T) {
	bh, defs, league := phase4Fixture()
	var offFlags, defFlags [6]bool
	defFlags[slotPF] = true // PF NonMatchedTerm=−1.0 → must NOT accumulate
	got := matchupQuality(bh, defs, league, offFlags, defFlags)
	want := 0.56
	if math.Abs(got-want) > 1e-9 {
		t.Errorf("negative excluded: got %v, want %v (wrong: %.2f if negatives summed)", got, want, 0.36)
	}
}

// TestMatchupQualityPhase4_CombinedPhase3And4: gate open; slotSF defender also
// flagged (NonMatchedTerm=3.0); PG flagged (1.0); SG unflagged (2.0); PF flagged
// negative (−1.0, excluded); C unflagged (0.5).
// Phase 3 acc = 4.9 (unchanged — Phase 4 is additive, not a Phase-3 re-sum).
// phase4 = 3.0 (slotSF) + 1.0 (PG) = 4.0 (PF flagged-but-negative excluded)
// (4.9 + 4.0 − 2.1)·0.2 = 1.36
func TestMatchupQualityPhase4_CombinedPhase3And4(t *testing.T) {
	bh, defs, league := phase4Fixture()
	var offFlags, defFlags [6]bool
	defFlags[slotSF] = true // matched defender, NonMatchedTerm=3.0 → Phase 4 accumulates it
	defFlags[slotPG] = true // PG NonMatchedTerm=1.0
	defFlags[slotPF] = true // PF NonMatchedTerm=−1.0 → excluded (negative)
	got := matchupQuality(bh, defs, league, offFlags, defFlags)
	want := 1.36
	if math.Abs(got-want) > 1e-9 {
		t.Errorf("combined Phase3+4: got %v, want %v", got, want)
	}
}
