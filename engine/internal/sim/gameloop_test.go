package sim

import (
	"reflect"
	"testing"
)

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

// TestResolveFoulBucketScale mirrors TestResolveStealTurnoverScale_*: nil → const,
// non-nil → override, and explicit zero → 0.0 (not const). The zero case is the
// whole reason the resolver keys on nil, not on a zero-value check.
func TestResolveFoulBucketScale(t *testing.T) {
	if got := resolveFoulBucketScale(Options{}); got != foulBucketScale {
		t.Fatalf("nil override: got %v, want const %v", got, foulBucketScale)
	}
	v := 0.47
	if got := resolveFoulBucketScale(Options{FoulBucketScale: &v}); got != 0.47 {
		t.Fatalf("non-nil override: got %v, want 0.47", got)
	}
	// Explicit zero must not fall back to the const.
	z := 0.0
	if got := resolveFoulBucketScale(Options{FoulBucketScale: &z}); got != 0.0 {
		t.Fatalf("explicit zero override must not fall back to const, got %v", got)
	}
}

// TestResolveAndOneMadeRateScale mirrors the above for the and-one made-rate seam.
func TestResolveAndOneMadeRateScale(t *testing.T) {
	if got := resolveAndOneMadeRateScale(Options{}); got != andOneMadeRateScale {
		t.Fatalf("nil override: got %v, want const %v", got, andOneMadeRateScale)
	}
	v := 0.0016
	if got := resolveAndOneMadeRateScale(Options{AndOneMadeRateScale: &v}); got != 0.0016 {
		t.Fatalf("non-nil override: got %v, want 0.0016", got)
	}
	// Explicit zero must not fall back to the const.
	z := 0.0
	if got := resolveAndOneMadeRateScale(Options{AndOneMadeRateScale: &z}); got != 0.0 {
		t.Fatalf("explicit zero override must not fall back to const, got %v", got)
	}
}

// TestFoulBucketScaleSeamIsLive proves Options → gameState → call site is connected
// end-to-end for FoulBucketScale. Two halves:
//   - nil-neutrality: Options{} reproduces the live Simulate byte-for-byte.
//   - liveness: a 0.47 override (vs default 0.39, ~20% larger) must change the total
//     FTA; the foul bucket weight is linear in scale so the foul-path share shifts
//     by a detectable ~1.5 pp, expected ~6 extra FTA on seed 1988.
func TestFoulBucketScaleSeamIsLive(t *testing.T) {
	b := richBundle()

	// Nil-neutrality: Options{} must be byte-identical to Simulate (the live engine).
	base := Simulate(b, 1988)
	withNil, err := SimulateWith(b, 1988, Options{})
	if err != nil {
		t.Fatalf("SimulateWith zero Options: %v", err)
	}
	if !reflect.DeepEqual(base, withNil) {
		t.Error("zero-Options SimulateWith diverged from Simulate — FoulBucketScale field not inert when nil")
	}

	// Liveness: the override must change FTA.
	v := 0.47
	swept, err := SimulateWith(b, 1988, Options{FoulBucketScale: &v})
	if err != nil {
		t.Fatalf("SimulateWith FoulBucketScale=0.47: %v", err)
	}
	baseFTA := base.Games[0].TeamBoxes[0].GameFTA + base.Games[0].TeamBoxes[1].GameFTA
	sweptFTA := swept.Games[0].TeamBoxes[0].GameFTA + swept.Games[0].TeamBoxes[1].GameFTA
	if baseFTA == sweptFTA {
		t.Fatalf("FoulBucketScale override did not change FTA (base=%d swept=%d) — seam is not wired end-to-end", baseFTA, sweptFTA)
	}
}

// TestAndOneMadeRateScaleSeamIsLive proves Options → gameState → call site is
// connected end-to-end for AndOneMadeRateScale. Two halves:
//   - nil-neutrality: Options{} reproduces the live Simulate byte-for-byte.
//   - liveness: a 0.0016 override (2× default) must change the FTA total. The
//     and-one share is small (~0.2% of possessions) so a single game may not show a
//     difference; 100 seeds accumulate ~84 extra and-one FTAs (reliably non-zero).
func TestAndOneMadeRateScaleSeamIsLive(t *testing.T) {
	b := richBundle()

	// Nil-neutrality: Options{} must be byte-identical to Simulate (the live engine).
	base := Simulate(b, 1988)
	withNil, err := SimulateWith(b, 1988, Options{})
	if err != nil {
		t.Fatalf("SimulateWith zero Options: %v", err)
	}
	if !reflect.DeepEqual(base, withNil) {
		t.Error("zero-Options SimulateWith diverged from Simulate — AndOneMadeRateScale field not inert when nil")
	}

	// Liveness: sweep 100 seeds so the small and-one share produces a reliable signal.
	v := 0.0016 // 2× the default andOneMadeRateScale
	var baseFTA, sweptFTA int
	for s := uint64(1); s <= 100; s++ {
		r, err := SimulateWith(b, s, Options{})
		if err != nil {
			t.Fatalf("seed %d baseline: %v", s, err)
		}
		sw, err := SimulateWith(b, s, Options{AndOneMadeRateScale: &v})
		if err != nil {
			t.Fatalf("seed %d override: %v", s, err)
		}
		baseFTA += r.Games[0].TeamBoxes[0].GameFTA + r.Games[0].TeamBoxes[1].GameFTA
		sweptFTA += sw.Games[0].TeamBoxes[0].GameFTA + sw.Games[0].TeamBoxes[1].GameFTA
	}
	if baseFTA == sweptFTA {
		t.Fatalf("AndOneMadeRateScale override did not change FTA over 100 seeds (base=%d swept=%d) — seam is not wired end-to-end", baseFTA, sweptFTA)
	}
}
