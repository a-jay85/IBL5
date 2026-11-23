package rng_test

import (
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

func TestReplayReturnsRecordedValuesInOrder(t *testing.T) {
	r, err := rng.NewReplay([]int{5, 32767, 0})
	if err != nil {
		t.Fatal(err)
	}
	for i, want := range []int{5, 32767, 0} {
		if got := r.Raw(); got != want {
			t.Fatalf("draw %d = %d, want %d", i, got, want)
		}
	}
	d := r.Draws()
	if len(d) != 3 {
		t.Fatalf("draws = %d, want 3", len(d))
	}
	for _, x := range d {
		if x.API != "raw" {
			t.Fatalf("api = %q, want raw", x.API)
		}
	}
}

func TestReplayExhaustionPanicsWithIndex(t *testing.T) {
	r, _ := rng.NewReplay([]int{1})
	var got any
	func() {
		defer func() { got = recover() }()
		r.Raw()
		r.Raw()
	}()
	e, ok := got.(*rng.ExhaustedError)
	if !ok {
		t.Fatalf("panic = %#v, want *rng.ExhaustedError", got)
	}
	if e.Index != 1 || e.API != "raw" || e.Caller != "rng_test.TestReplayExhaustionPanicsWithIndex" {
		t.Fatalf("got %+v", e)
	}
}

func TestNewReplayRejectsOutOfRange(t *testing.T) {
	for _, bad := range []int{32768, -1} {
		_, err := rng.NewReplay([]int{0, bad})
		if err == nil || !strings.Contains(err.Error(), "index 1") {
			t.Fatalf("value %d: err = %v, want error naming index 1", bad, err)
		}
	}
	if _, err := rng.NewReplay([]int{0, 32767}); err != nil {
		t.Fatalf("boundaries rejected: %v", err)
	}
}

func TestRawStaysIn15BitRange(t *testing.T) {
	r := rng.New(1)
	lo, hi := 1<<30, -1
	for i := 0; i < 100000; i++ {
		v := r.Raw()
		if v < 0 || v > rng.RawMax {
			t.Fatalf("raw %d out of range", v)
		}
		lo, hi = min(lo, v), max(hi, v)
	}
	if hi < 32000 || lo > 767 {
		t.Fatalf("range [%d,%d] too narrow", lo, hi)
	}
}

func TestReplayFloat64AndIntNAreTagged(t *testing.T) {
	r, _ := rng.NewReplay([]int{16384, 37})
	if got := r.Float64(); got != 0.5 {
		t.Fatalf("Float64 = %v, want 0.5", got)
	}
	if got := r.IntN(10); got != 7 {
		t.Fatalf("IntN = %d, want 7", got)
	}
	d := r.Draws()
	if d[0].API != "float64" || d[1].API != "intn" || d[1].N != 10 {
		t.Fatalf("draws = %+v", d)
	}
}

func TestNormalizeCaller(t *testing.T) {
	// A regexp that strips only one trailing .funcN leaves the nested cases below
	// with a closure suffix, so they pin the repeated-suffix strip.
	cases := map[string]string{
		"github.com/a-jay85/IBL5/engine/internal/sim.(*state).roll.func1":              "sim.(*state).roll",
		"github.com/a-jay85/IBL5/engine/internal/sim.roll":                             "sim.roll",
		"github.com/a-jay85/IBL5/engine/internal/sim.(*gameState).rebound.func2.func1": "sim.(*gameState).rebound",
		"github.com/a-jay85/IBL5/engine/internal/sim.(*gameState).rebound.func1":       "sim.(*gameState).rebound",
		"github.com/a-jay85/IBL5/engine/internal/sim.(*gameState).rebound.func1.2":     "sim.(*gameState).rebound",
		"github.com/a-jay85/IBL5/engine/internal/sim.runGame.gowrap1":                  "sim.runGame",
		"github.com/a-jay85/IBL5/engine/internal/sim.(*gameState).shoot":               "sim.(*gameState).shoot",
		"github.com/a-jay85/IBL5/engine/internal/sim.funcWeight":                       "sim.funcWeight",
		"github.com/a-jay85/IBL5/engine/internal/sim.applyFunc2":                       "sim.applyFunc2",
	}
	for in, want := range cases {
		if got := rng.NormalizeCaller(in); got != want {
			t.Errorf("NormalizeCaller(%q) = %q, want %q", in, got, want)
		}
	}
}
