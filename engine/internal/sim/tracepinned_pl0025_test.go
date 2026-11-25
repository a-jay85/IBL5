package sim

import (
	"encoding/json"
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

type pl0025In struct {
	Args  []int64 `json:"args"`
	Param float64 `json:"param"`
	Rand  []int   `json:"rand"`
}

type pl0025Out struct {
	Ret int `json:"ret"`
}

// TestTracePinnedPl0025ScaledDraw replays every captured call through
// scaledDraw and checks the draw count and the raw value used. Records at the
// and-one floor scale also replay through bucket3Weight's floor branch, so the
// production call site is held to the same one-draw contract.
func TestTracePinnedPl0025ScaledDraw(t *testing.T) {
	c := jsbtrace.MustLoad(t, "pl-0025.jsonl")
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	floorHits := 0
	for i, rec := range c.Records {
		var in pl0025In
		var out pl0025Out
		if err := json.Unmarshal(rec.In, &in); err != nil {
			t.Fatalf("record %d: decode in: %v", i, err)
		}
		if err := json.Unmarshal(rec.Out, &out); err != nil {
			t.Fatalf("record %d: decode out: %v", i, err)
		}
		if len(in.Args) < 4 || len(in.Rand) == 0 {
			t.Fatalf("record %d: short args or empty rand", i)
		}
		bits := uint64(uint32(in.Args[3]))<<32 | uint64(uint32(in.Args[2]))
		if math.Float64frombits(bits) != in.Param {
			t.Fatalf("record %d: scale words do not match param", i)
		}
		r, err := rng.NewReplay(in.Rand)
		if err != nil {
			t.Fatalf("record %d: replay: %v", i, err)
		}
		_ = scaledDraw(r.Float64(), in.Param)
		checkPl0025Draws(t, i, r, in.Rand, out.Ret)
		if in.Param == andOneBucketFloor {
			floorHits++
			rf, err := rng.NewReplay(in.Rand)
			if err != nil {
				t.Fatalf("record %d: floor replay: %v", i, err)
			}
			_ = bucket3Weight(0, -1, 0, false, rf)
			checkPl0025Draws(t, i, rf, in.Rand, out.Ret)
		}
	}
	if floorHits == 0 {
		t.Fatal("no captured call uses the and-one floor scale")
	}
}

// checkPl0025Draws fails unless r logged exactly the captured draws and the
// first one used the captured raw value.
func checkPl0025Draws(t *testing.T, i int, r *rng.RNG, rand []int, ret int) {
	t.Helper()
	draws := r.Draws()
	if len(draws) != len(rand) {
		t.Fatalf("record %d: draws = %d, captured %d", i, len(draws), len(rand))
	}
	if draws[0].Value != ret {
		t.Fatalf("record %d: draw value %d, captured %d", i, draws[0].Value, ret)
	}
}

// TestBucket3WeightFloorUsesScaledDraw pins that the floor branch takes one
// draw scaled by andOneBucketFloor and the positive branch takes none.
func TestBucket3WeightFloorUsesScaledDraw(t *testing.T) {
	r, err := rng.NewReplay([]int{16384})
	if err != nil {
		t.Fatal(err)
	}
	if got := bucket3Weight(0, -1, 0, false, r); got != 0.5*andOneBucketFloor {
		t.Fatalf("floor branch = %v, want %v", got, 0.5*andOneBucketFloor)
	}
	if n := len(r.Draws()); n != 1 {
		t.Fatalf("floor branch draws = %d, want 1", n)
	}

	rh, err := rng.NewReplay([]int{16384})
	if err != nil {
		t.Fatal(err)
	}
	if got := bucket3Weight(0.5, 0, -10, true, rh); got != 0.5*andOneBucketFloor {
		t.Fatalf("half-court floor = %v, want %v", got, 0.5*andOneBucketFloor)
	}
	if n := len(rh.Draws()); n != 1 {
		t.Fatalf("half-court floor draws = %d, want 1", n)
	}

	rp, err := rng.NewReplay([]int{16384})
	if err != nil {
		t.Fatal(err)
	}
	want := 4*d90MakeShareQuarter + 0.5
	if got := bucket3Weight(0.5, 4, 0, false, rp); got != want {
		t.Fatalf("positive branch = %v, want %v", got, want)
	}
	if n := len(rp.Draws()); n != 0 {
		t.Fatalf("positive branch draws = %d, want 0", n)
	}
}
