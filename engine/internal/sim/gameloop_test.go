package sim

import "testing"

func TestResolveNonStealTurnoverScale_NilUsesConst(t *testing.T) {
	got := resolveNonStealTurnoverScale(Options{})
	if got != nonStealTurnoverScale {
		t.Errorf("resolveNonStealTurnoverScale(nil) = %v, want const %v", got, nonStealTurnoverScale)
	}
}

func TestResolveNonStealTurnoverScale_NonNilOverrides(t *testing.T) {
	v := 0.0025
	got := resolveNonStealTurnoverScale(Options{NonStealTurnoverScale: &v})
	if got != v {
		t.Errorf("resolveNonStealTurnoverScale(override) = %v, want %v", got, v)
	}
}
