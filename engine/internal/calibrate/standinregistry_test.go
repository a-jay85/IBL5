package calibrate

import "testing"

// TestStandInRegistryJustified is the ADR-0087 §2 CI gate: every registered
// StandIn must carry a non-empty Justification (safe-by-omission enforcement).
// A blank Justification means an un-reviewed parameter is being swept — reject at
// go test time rather than silently producing an unjustified leverage report.
func TestStandInRegistryJustified(t *testing.T) {
	registry := StandInRegistry()
	seen := make(map[string]bool, len(registry))
	for _, si := range registry {
		if si.Justification == "" {
			t.Errorf("StandIn %q: Justification is empty (ADR-0087 §2 requires non-empty)", si.ID)
		}
		if si.ID == "" {
			t.Errorf("StandIn with Term=%q: ID is empty", si.Term)
		}
		if len(si.Sweep) < 2 {
			t.Errorf("StandIn %q: len(Sweep)=%d, want >=2 (baseline + at least one perturbation)", si.ID, len(si.Sweep))
		}
		if seen[si.ID] {
			t.Errorf("StandIn %q: duplicate ID in registry", si.ID)
		}
		seen[si.ID] = true
	}
}
