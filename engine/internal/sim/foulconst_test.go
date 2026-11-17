package sim

import "testing"

// TestPortedFoulConstants pins each foul constant to the value at its cited
// FUN_004d8570 line in jsb560_decompiled.c. Each want comes from the
// decompile (or, for a _DAT_ value the decompile shows only by name, from the
// write-up that read it), never from the Go source.
func TestPortedFoulConstants(t *testing.T) {
	cases := []struct {
		name      string
		got, want float64
		cite      string
	}{
		{"shootingFoulDrawCut", shootingFoulDrawCut, 0.5, "_DAT_00669ef0, jsb560_decompiled.c:93469-93480"},
		{"teamFoulBonusThreshold", teamFoulBonusThreshold, 4, "4 < team fouls, jsb560_decompiled.c:93647"},
		{"forcedFoulMinPeriod", forcedFoulMinPeriod, 4, "iVar19 < 4, jsb560_decompiled.c:92995"},
		{"forcedFoulClockLimit", forcedFoulClockLimit, 0x19, "+0x4c24 < 0x19, jsb560_decompiled.c:92992"},
		{"forcedFoulMinLead", forcedFoulMinLead, 1, "uVar20 < 1, jsb560_decompiled.c:92995-92996"},
		{"forcedFoulMaxLead", forcedFoulMaxLead, 3, "3 < uVar20, jsb560_decompiled.c:92995-92996"},
		{"andOneMadeTwoCut", andOneMadeTwoCut, 0.05, "_DAT_0066c7d8, jsb560_decompiled.c:93924"},
		{"offensiveFoulOnTurnover", offensiveFoulOnTurnover, 0.1, "_DAT_00669aa0 (const 0.1 at 0x4da7a0), jsb560_decompiled.c:94290-94293, trace 2026-11-16"},
	}
	for _, c := range cases {
		if c.got != c.want {
			t.Errorf("%s = %v, want %v (%s)", c.name, c.got, c.want, c.cite)
		}
	}
}

// TestPortedFoulPredicates checks each foul predicate at and just inside its
// cut.
func TestPortedFoulPredicates(t *testing.T) {
	if andOneMadeTwoFires(andOneMadeTwoCut) {
		t.Error("andOneMadeTwoFires(andOneMadeTwoCut) = true, want false: the draw must be strictly below the cut")
	}
	if !andOneMadeTwoFires(andOneMadeTwoCut - 1e-9) {
		t.Error("andOneMadeTwoFires(cut - 1e-9) = false, want true")
	}
	if isShootingFoul(shootingFoulDrawCut, false, false, false) {
		t.Error("isShootingFoul(0.5, ...) = true, want false: the draw must be strictly above the cut")
	}
	if !isShootingFoul(shootingFoulDrawCut+1e-9, false, false, false) {
		t.Error("isShootingFoul(0.5 + 1e-9, ...) = false, want true")
	}
	forced := []struct {
		period, clock, lead int
		want                bool
	}{
		{4, 0x18, 1, true},
		{4, 0x19, 1, false},
		{4, 0x18, 0, false},
		{4, 0x18, 3, true},
		{4, 0x18, 4, false},
		{3, 0x18, 1, false},
	}
	for _, c := range forced {
		if got := forcedFoulFires(c.period, c.clock, c.lead); got != c.want {
			t.Errorf("forcedFoulFires(%d, %#x, %d) = %v, want %v", c.period, c.clock, c.lead, got, c.want)
		}
	}
}
