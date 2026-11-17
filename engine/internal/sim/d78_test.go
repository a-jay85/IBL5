package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// jamesWorthyRL is James Worthy's (pid 6322, team 18) real-life block from the
// current-season IBL5.plr, copied as literals so the test never opens the .plr.
func jamesWorthyRL() onCourt {
	return onCourt{Player: bundle.Player{
		PID: 6322, Name: "James Worthy", TeamID: 18,
		RealLifeGP: 74, RealLifeMIN: 2664, RealLifeFGA: 1304, RealLifeFTA: 236,
		RealLife3GA: 85, RealLifeORB: 98, RealLifeFGM: 646, RealLife3GM: 24,
		RealLifeTVR: 115, RealLifePF: 109, D60: 510,
	}}
}

// TestD78Composite_RealRecords: a real starter's +0xD78 equals the value a
// python3 copy of the asm 4d45af-4d4662 formula computed from the same fields.
func TestD78Composite_RealRecords(t *testing.T) {
	const want = 1.9952761510097965
	if got := d78Composite(jamesWorthyRL()); math.Abs(got-want) > 1e-12 {
		t.Errorf("d78Composite(James Worthy) = %.17g, want %.17g", got, want)
	}
}

// TestD78Composite_ZeroMinutesIsZero: JSB's else-0 store covers a rookie
// (RealLifeMIN == 0) and a lineup member whose db8+d88 is not positive.
func TestD78Composite_ZeroMinutesIsZero(t *testing.T) {
	rookie := jamesWorthyRL()
	rookie.RealLifeMIN = 0
	if got := d78Composite(rookie); got != 0 {
		t.Errorf("rookie (RealLifeMIN == 0) d78 = %v, want 0", got)
	}

	// No ORB and FGA == 3GA: db8 == d88 == 0 while FTA keeps d70 > 0.
	noTwo := jamesWorthyRL()
	noTwo.RealLifeORB = 0
	noTwo.RealLifeFGA = noTwo.RealLife3GA
	if got := d78Composite(noTwo); got != 0 {
		t.Errorf("db8+d88 == 0 (ORB 0, FGA == 3GA) d78 = %v, want 0", got)
	}

	// A corrupt record with ORB == −2PA makes db8+d88 exactly 0 while d88 > 0,
	// so only the db8+d88 > 0 guard (asm 4d45da) stands between it and a NaN.
	cancel := jamesWorthyRL()
	cancel.RealLifeORB = -(cancel.RealLifeFGA - cancel.RealLife3GA)
	if got := d78Composite(cancel); got != 0 {
		t.Errorf("db8+d88 == 0 with d88 > 0 (ORB == -2PA) d78 = %v, want 0", got)
	}
}
