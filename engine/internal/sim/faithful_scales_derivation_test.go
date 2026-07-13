package sim

import (
	"math"
	"testing"
)

// TestFaithfulScales_Recompute guards the DERIVED constants of the faithful foul
// bucket (J6/J16, 2026-07-10) against silent drift: a magic literal that no longer
// matches its RE derivation fails here. It asserts the RELATIONSHIPS the derivation
// pins — not the stand-in input VALUES (leagueSTL48's absolute level is a documented
// stand-in; see teamquality.go). Provenance: phase2-derivation.md.
func TestFaithfulScales_Recompute(t *testing.T) {
	const eps = 1e-9

	// Coupling factor denominator coefficient: PE 0x66D3A0 = 5/6 ≈ 0.8333 (J6 :97163).
	// The correct factor is 1 + (defQ − 5/6·5·leagueSTL48)/offQ — increasing in defQ;
	// "1 − defQ·(5/6)/offQ" was the C1 mis-transcription, superseded by ADR-0084.
	if math.Abs(foulDivisorTeamDefCoef-5.0/6.0) > eps {
		t.Errorf("foulDivisorTeamDefCoef = %.10f, want 5/6 = %.10f", foulDivisorTeamDefCoef, 5.0/6.0)
	}

	// Composite provenance (J6): +0xDD0 = STL/MIN×44, +0xDE0 = TOV/MIN×48. These are
	// the forms the pinned league means carry; the recompute locks them so a future
	// edit can't quietly swap the composite basis.
	if stlComposite44 != 44.0 {
		t.Errorf("stlComposite44 = %v, want 44.0 (J6 +0xDD0, PE 0x66D328)", stlComposite44)
	}
	if tovDivisor48 != 48.0 {
		t.Errorf("tovDivisor48 = %v, want 48.0 (J6 +0xDE0, PE 0x669ED0)", tovDivisor48)
	}

	// League TOV mean is the ONE J-pinned input (J16 line 67, CEngine[+0x68D8]).
	if math.Abs(leagueTOV48-3.353143) > eps {
		t.Errorf("leagueTOV48 = %.6f, want J16-pinned 3.353143", leagueTOV48)
	}

	// Cap RELATIONSHIP: defQuality of an all-max-STL lineup returns exactly
	// defQualityCapMultiplier·defQualityCapTeamMult·leagueSTL48 (J6 line 94) — the cap
	// is computed FROM the derivation, not a hardcoded ceiling.
	wantCap := defQualityCapMultiplier * defQualityCapTeamMult * leagueSTL48
	maxLineup := fiveStarters(7)
	for i := range maxLineup {
		maxLineup[i].STL = 99
	}
	if got := defQuality(maxLineup); math.Abs(got-wantCap) > eps {
		t.Errorf("defQuality cap = %.6f, want 1.5·5·leagueSTL48 = %.6f", got, wantCap)
	}

	// Balanced-matchup coupling factor closed form (C1-corrected, 2026-07-11): at a
	// rating-ratingRefScale matchup both sums reduce to 5·leagueMean, so
	//   factor = 1 + (5·leagueSTL48 − foulDivisorTeamDefCoef·5·leagueSTL48)/(5·leagueTOV48)
	//          = 1 + (leagueSTL48/leagueTOV48)/6
	// which MUST be > 1 (the coupling is INCREASING in defQ — a steal-gambling
	// defense fouls MORE, not less; J6 §5's "1 − defQ·(5/6)/offQ" was a
	// mis-transcription of the verbatim :97163 decompile). ≈1.091 at the committed
	// anchors.
	balFactor := 1.0 + (leagueSTL48/leagueTOV48)/6.0
	if !(balFactor > 1.0) {
		t.Fatalf("balanced factor %.4f not > 1 — the C1-corrected coupling must be INCREASING in defQ", balFactor)
	}
	// Recompute it through the REAL aggregators (both lineups at STL=TVR=ratingRefScale)
	// and confirm it matches the closed form — proving ratingRefScale cancels in the
	// ratio (the stand-in has no free magnitude knob for the factor).
	ref := int(ratingRefScale)
	dl, ol := fiveStarters(7), fiveStarters(3)
	for i := range dl {
		dl[i].STL = ref
	}
	for i := range ol {
		ol[i].TVR = ref
	}
	baseline := foulDivisorTeamDefCoef * defQualityCapTeamMult * leagueSTL48
	viaCode := 1.0 + (defQuality(dl)-baseline)/offQuality(ol)
	if math.Abs(viaCode-balFactor) > eps {
		t.Errorf("factor via aggregators = %.6f, closed form = %.6f (ratingRefScale must cancel)", viaCode, balFactor)
	}

	// Sanity on the STL:TOV anchor (real basketball ≈ 0.5–0.55): a fat-fingered
	// leagueSTL48 that breaks this ratio would silently distort every shrink.
	if r := leagueSTL48 / leagueTOV48; r < 0.45 || r > 0.60 {
		t.Errorf("leagueSTL48/leagueTOV48 = %.3f, outside the real STL:TOV band [0.45,0.60]", r)
	}
}
