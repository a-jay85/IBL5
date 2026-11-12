package sim

import "testing"

// TestFoulVolumeSiteTriggers table-tests every ported foul-volume predicate at
// its pinned trigger and one step past each pinned bound. The forced foul
// (FUN_004d8570 92991-93003) is the only site that met the J27 port criteria.
func TestFoulVolumeSiteTriggers(t *testing.T) {
	cases := []struct {
		name                       string
		period, clock, offenseLead int
		want                       bool
	}{
		{"forced foul: Q4, 24s, offense up 1", 4, 24, 1, true},
		{"forced foul: overtime, 0s, offense up 3", 5, 0, 3, true},
		{"forced foul: clock one past the bound (25s)", 4, 25, 2, false},
		{"forced foul: lead one past the bound (+4)", 4, 10, 4, false},
		{"forced foul: period one below the bound (Q3)", 3, 10, 2, false},
	}
	for _, c := range cases {
		if got := forcedFoulFires(c.period, c.clock, c.offenseLead); got != c.want {
			t.Errorf("%s: forcedFoulFires(%d, %d, %d) = %v, want %v",
				c.name, c.period, c.clock, c.offenseLead, got, c.want)
		}
	}
}

// TestForcedFoulTrigger pins the margin sign of the forced-foul gate: it opens
// only when the offense leads by 1 to 3, which leaves the defense trailing by 1
// to 3 (J17 objdump 0x4d87c6-0x4d87f1). Leading defenses, ties, a margin one
// past either bound, and periods before Q4 stay silent.
func TestForcedFoulTrigger(t *testing.T) {
	for lead := forcedFoulMinLead; lead <= forcedFoulMaxLead; lead++ {
		if !forcedFoulFires(4, 10, lead) {
			t.Errorf("defense trailing by %d in Q4: gate closed, want open", lead)
		}
		if forcedFoulFires(4, 10, -lead) {
			t.Errorf("defense leading by %d in Q4: gate open, want closed", lead)
		}
		for period := 1; period < forcedFoulMinPeriod; period++ {
			if forcedFoulFires(period, 10, lead) {
				t.Errorf("defense trailing by %d in period %d: gate open, want closed", lead, period)
			}
		}
	}
	silent := []struct {
		name string
		lead int
	}{
		{"tied", 0},
		{"one past the upper bound", forcedFoulMaxLead + 1},
		{"one past the lower bound", forcedFoulMinLead - 1},
	}
	for _, s := range silent {
		if forcedFoulFires(4, 10, s.lead) {
			t.Errorf("%s (offense lead %d): gate open, want closed", s.name, s.lead)
		}
	}
}
