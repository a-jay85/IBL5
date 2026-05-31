package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// --- net = offense − penalty − defense; modifiers --------------------------

func TestNetAdvantage_Formula(t *testing.T) {
	handler := oc(slotPG, bundle.Player{OO: 9, DriveOff: 7, PO: 4})
	defender := oc(slotPG, bundle.Player{OD: 5, DD: 3, PD: 6})
	const penalty = 2.0

	// Outside: OO − penalty − OD = 9 − 2 − 5 = 2.0 (regular ×1.0).
	if got := netAdvantage(playOutside, handler, defender, penalty, false, bundle.GameTypeRegular); math.Abs(got-2.0) > 1e-9 {
		t.Errorf("outside net = %v, want 2.0", got)
	}
	// Drive: DO − penalty − DD = 7 − 2 − 3 = 2.0.
	if got := netAdvantage(playDrive, handler, defender, penalty, false, bundle.GameTypeRegular); math.Abs(got-2.0) > 1e-9 {
		t.Errorf("drive net = %v, want 2.0", got)
	}
	// Post: PO − penalty − PD = 4 − 2 − 6 = −4.0.
	if got := netAdvantage(playPost, handler, defender, penalty, false, bundle.GameTypeRegular); math.Abs(got-(-4.0)) > 1e-9 {
		t.Errorf("post net = %v, want -4.0", got)
	}
	// Shot clock subtracts 4.0: outside 2.0 − 4.0 = −2.0.
	if got := netAdvantage(playOutside, handler, defender, penalty, true, bundle.GameTypeRegular); math.Abs(got-(-2.0)) > 1e-9 {
		t.Errorf("shot-clock net = %v, want -2.0", got)
	}
}

// --- boundary — shot clock can drive net negative --------------------------

func TestNetAdvantage_GoesNegativeSafely(t *testing.T) {
	handler := oc(slotC, bundle.Player{PO: 1})
	defender := oc(slotC, bundle.Player{PD: 9})
	got := netAdvantage(playPost, handler, defender, 5.0, true, bundle.GameTypeRegular) // 1 − 5 − 9 − 4
	if math.Abs(got-(-17.0)) > 1e-9 {
		t.Errorf("net = %v, want -17.0", got)
	}
	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Error("net not finite")
	}
}

// --- playoff net × 1.25 (game_type==4) -------------------------------------

func TestNetAdvantage_PlayoffMultiplier(t *testing.T) {
	handler := oc(slotPG, bundle.Player{OO: 9, DriveOff: 7, PO: 4})
	defender := oc(slotPG, bundle.Player{OD: 5, DD: 3, PD: 6})
	const penalty = 2.0

	// Positive matchup amplified: regular 2.0 → playoff 2.5.
	if got := netAdvantage(playOutside, handler, defender, penalty, false, bundle.GameTypePlayoff); math.Abs(got-2.5) > 1e-9 {
		t.Errorf("playoff outside net = %v, want 2.5 (2.0 × 1.25)", got)
	}
	// Negative matchup ALSO amplified (the multiplier hits the signed net):
	// regular −4.0 → playoff −5.0.
	if got := netAdvantage(playPost, handler, defender, penalty, false, bundle.GameTypePlayoff); math.Abs(got-(-5.0)) > 1e-9 {
		t.Errorf("playoff post net = %v, want -5.0 (-4.0 × 1.25)", got)
	}
	// Shot-clock subtraction happens BEFORE the multiplier: (2.0 − 4.0) × 1.25 = −2.5.
	if got := netAdvantage(playOutside, handler, defender, penalty, true, bundle.GameTypePlayoff); math.Abs(got-(-2.5)) > 1e-9 {
		t.Errorf("playoff shot-clock net = %v, want -2.5", got)
	}
}

// --- boundary: only PLAYOFF gets the multiplier; every other type is ×1.0 ---

func TestNetAdvantage_NonPlayoffIsUnmultiplied(t *testing.T) {
	handler := oc(slotPG, bundle.Player{OO: 9})
	defender := oc(slotPG, bundle.Player{OD: 5})
	const penalty = 2.0 // outside net = 9 − 2 − 5 = 2.0

	for _, gt := range []bundle.GameType{
		bundle.GameTypeRegular, bundle.GameTypeRegularAlt,
		bundle.GameTypeAllStarA, bundle.GameTypeAllStarB,
	} {
		if got := netAdvantage(playOutside, handler, defender, penalty, false, gt); math.Abs(got-2.0) > 1e-9 {
			t.Errorf("game_type %d net = %v, want 2.0 (no playoff multiplier)", int(gt), got)
		}
	}
}
