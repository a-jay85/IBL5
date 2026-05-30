package rng

import "testing"

// #8 — the PRNG is deterministic: the same seed yields the same sequence, and
// different seeds diverge.
func TestRNG_DeterministicBySeed(t *testing.T) {
	a := New(12345)
	b := New(12345)
	for i := 0; i < 100; i++ {
		if got, want := a.Float64(), b.Float64(); got != want {
			t.Fatalf("Float64 diverged at %d: %v != %v", i, got, want)
		}
	}

	a = New(12345)
	b = New(12345)
	for i := 0; i < 100; i++ {
		if got, want := a.IntN(1000), b.IntN(1000); got != want {
			t.Fatalf("IntN diverged at %d: %d != %d", i, got, want)
		}
	}
}

func TestRNG_DifferentSeedsDiverge(t *testing.T) {
	a := New(1)
	b := New(2)
	// With 50 draws the odds of identical sequences from distinct seeds are
	// vanishingly small; any match indicates the seed is not threaded.
	identical := true
	for i := 0; i < 50; i++ {
		if a.Float64() != b.Float64() {
			identical = false
			break
		}
	}
	if identical {
		t.Fatal("seeds 1 and 2 produced identical sequences — seed not threaded")
	}
}
