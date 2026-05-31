package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// --- matrix #11: net = offense − penalty − defense; modifiers --------------

func TestNetAdvantage_Formula(t *testing.T) {
	handler := oc(slotPG, bundle.Player{OO: 9, DriveOff: 7, PO: 4})
	defender := oc(slotPG, bundle.Player{OD: 5, DD: 3, PD: 6})
	const penalty = 2.0

	// Outside: OO − penalty − OD = 9 − 2 − 5 = 2.0 (regular ×1.0).
	if got := netAdvantage(playOutside, handler, defender, penalty, false); math.Abs(got-2.0) > 1e-9 {
		t.Errorf("outside net = %v, want 2.0", got)
	}
	// Drive: DO − penalty − DD = 7 − 2 − 3 = 2.0.
	if got := netAdvantage(playDrive, handler, defender, penalty, false); math.Abs(got-2.0) > 1e-9 {
		t.Errorf("drive net = %v, want 2.0", got)
	}
	// Post: PO − penalty − PD = 4 − 2 − 6 = −4.0.
	if got := netAdvantage(playPost, handler, defender, penalty, false); math.Abs(got-(-4.0)) > 1e-9 {
		t.Errorf("post net = %v, want -4.0", got)
	}
	// Shot clock subtracts 4.0: outside 2.0 − 4.0 = −2.0.
	if got := netAdvantage(playOutside, handler, defender, penalty, true); math.Abs(got-(-2.0)) > 1e-9 {
		t.Errorf("shot-clock net = %v, want -2.0", got)
	}
}

// --- matrix #12: boundary — shot clock can drive net negative --------------

func TestNetAdvantage_GoesNegativeSafely(t *testing.T) {
	handler := oc(slotC, bundle.Player{PO: 1})
	defender := oc(slotC, bundle.Player{PD: 9})
	got := netAdvantage(playPost, handler, defender, 5.0, true) // 1 − 5 − 9 − 4
	if math.Abs(got-(-17.0)) > 1e-9 {
		t.Errorf("net = %v, want -17.0", got)
	}
	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Error("net not finite")
	}
}
