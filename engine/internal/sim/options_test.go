package sim

import "testing"

// ptr is a test helper: address of a float64 literal, for *float64 Options fields.
func ptrF(f float64) *float64 { return &f }

// TestResolveOffVolumeScale_NilUsesConst is the ADR-0054 OFF-by-default guarantee at
// the resolver level: a zero Options (nil OffVolumeScale) resolves to the package
// const offVolumeScale, so the live engine is byte-identical.
func TestResolveOffVolumeScale_NilUsesConst(t *testing.T) {
	if got := resolveOffVolumeScale(Options{}); got != offVolumeScale {
		t.Fatalf("resolveOffVolumeScale(Options{}) = %v, want const %v", got, offVolumeScale)
	}
}

// TestResolveOffVolumeScale_ZeroHonored is the boundary the pointer exists for: an
// explicit override of 0 (a valid sweep value that disables the channel) must be
// honored, NOT treated as "unset" and silently replaced by the const.
func TestResolveOffVolumeScale_ZeroHonored(t *testing.T) {
	if got := resolveOffVolumeScale(Options{OffVolumeScale: ptrF(0)}); got != 0 {
		t.Fatalf("resolveOffVolumeScale(ptr(0)) = %v, want 0 (zero honored, not unset)", got)
	}
	if got := resolveOffVolumeScale(Options{OffVolumeScale: ptrF(0.06)}); got != 0.06 {
		t.Fatalf("resolveOffVolumeScale(ptr(0.06)) = %v, want 0.06", got)
	}
}
