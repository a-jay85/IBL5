package sim

import "testing"

func TestResolveStealTurnoverScale_NilUsesConst(t *testing.T) {
	got := resolveStealTurnoverScale(Options{})
	if got != stealTurnoverScale {
		t.Errorf("resolveStealTurnoverScale(nil) = %v, want const %v", got, stealTurnoverScale)
	}
}

func TestResolveStealTurnoverScale_NonNilOverrides(t *testing.T) {
	v := 3.14e-5
	got := resolveStealTurnoverScale(Options{StealTurnoverScale: &v})
	if got != v {
		t.Errorf("resolveStealTurnoverScale(override) = %v, want %v", got, v)
	}
}

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
