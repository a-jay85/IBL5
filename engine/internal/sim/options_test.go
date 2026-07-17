package sim

import "testing"

// ptrF is a test helper: address of a float64 literal, for *float64 Options fields.
func ptrF(f float64) *float64 { return &f }

// TestResolveBaseTimeMid_NilUsesConst is the OFF-by-default guarantee at the
// resolver level: a zero Options (nil BaseTimeMid) resolves to the package const
// baseTimeMid, so the live engine is byte-identical.
func TestResolveBaseTimeMid_NilUsesConst(t *testing.T) {
	if got := resolveBaseTimeMid(Options{}); got != baseTimeMid {
		t.Fatalf("resolveBaseTimeMid(Options{}) = %v, want const %v", got, baseTimeMid)
	}
}

// TestResolveBaseTimeMid_OverrideHonored: a non-nil override — including one that
// equals the const — must be returned as given, never second-guessed against the
// const path.
func TestResolveBaseTimeMid_OverrideHonored(t *testing.T) {
	if got := resolveBaseTimeMid(Options{BaseTimeMid: ptrF(16.0)}); got != 16.0 {
		t.Fatalf("resolveBaseTimeMid(ptr(16.0)) = %v, want 16.0", got)
	}
	if got := resolveBaseTimeMid(Options{BaseTimeMid: ptrF(baseTimeMid)}); got != baseTimeMid {
		t.Fatalf("resolveBaseTimeMid(ptr(const)) = %v, want %v", got, baseTimeMid)
	}
}
