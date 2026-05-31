package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #7: shot-type selection proportional to adjusted weights -------

func TestSelectShotType_Proportional(t *testing.T) {
	// Aligned PG-vs-PG matchup: the off/def weights cancel, so adjusted weights
	// equal the floored raw ratings (OO=6, DO=3, PO=1) → 6:3:1.
	handler := oc(slotPG, bundle.Player{OO: 6, DriveOff: 3, PO: 1})
	defender := oc(slotPG, bundle.Player{})
	oo, do, po := adjustedShotWeights(handler, defender)
	if oo != 6 || do != 3 || po != 1 {
		t.Fatalf("adjusted weights = %v/%v/%v, want 6/3/1", oo, do, po)
	}

	r := rng.New(3)
	const n = 60000
	counts := map[playType]int{}
	for i := 0; i < n; i++ {
		counts[selectShotType(handler, defender, r)]++
	}
	check := func(pt playType, wantFrac float64) {
		got := float64(counts[pt]) / n
		if got < wantFrac-0.02 || got > wantFrac+0.02 {
			t.Errorf("playType %d frac = %.3f, want ≈ %.3f", pt, got, wantFrac)
		}
	}
	check(playOutside, 0.6)
	check(playDrive, 0.3)
	check(playPost, 0.1)
}

// --- matrix #8: boundary — floor at 1, all-1s valid distribution -----------

func TestAdjustedShotWeights_FloorAt1(t *testing.T) {
	// A C-slot handler (off weights {4,4,8}) defended by a PG (def {9,9,1}) with
	// minimal ratings would go negative without the floor.
	handler := oc(slotC, bundle.Player{OO: 1, DriveOff: 1, PO: 1})
	defender := oc(slotPG, bundle.Player{})
	oo, do, po := adjustedShotWeights(handler, defender)
	for _, v := range []float64{oo, do, po} {
		if v < 1 {
			t.Errorf("adjusted weight %v below floor 1", v)
		}
	}
}

func TestSelectShotType_AllOnes(t *testing.T) {
	handler := oc(slotSF, bundle.Player{OO: 1, DriveOff: 1, PO: 1})
	defender := oc(slotSF, bundle.Player{OO: 1, DriveOff: 1, PO: 1})
	r := rng.New(5)
	seen := map[playType]bool{}
	for i := 0; i < 3000; i++ {
		seen[selectShotType(handler, defender, r)] = true // must not panic
	}
	if len(seen) == 0 {
		t.Error("no play types produced")
	}
}
