package sim

import (
	"encoding/json"
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// pl0025ExactIn is one capture input: the two scale words, the scale itself
// and the single raw draw the routine consumes.
type pl0025ExactIn struct {
	Args  []int64 `json:"args"`
	Param float64 `json:"param"`
	Rand  []int   `json:"rand"`
}

// pl0025ExactOut is one capture output: the raw draw and the scaled result.
// Value decodes a bare JSON integer as well as a float.
type pl0025ExactOut struct {
	Ret   int     `json:"ret"`
	Value float64 `json:"value"`
}

// TestTracePinnedPl0025ExactValue replays every capture record through
// scaledDraw and asserts the scaled result equals the capture exactly, with
// one draw whose raw value equals the capture. Records at the and-one floor
// scale also replay through bucket3Weight's floor branch.
func TestTracePinnedPl0025ExactValue(t *testing.T) {
	jsbtrace.SkipIfAbsent(t, "pl-0025.jsonl")
	c := jsbtrace.MustLoad(t, "pl-0025.jsonl")
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	floor := 0
	for i, rec := range c.Records {
		var in pl0025ExactIn
		var out pl0025ExactOut
		if err := json.Unmarshal(rec.In, &in); err != nil {
			t.Fatalf("record %d: decode in: %v", i, err)
		}
		if err := json.Unmarshal(rec.Out, &out); err != nil {
			t.Fatalf("record %d: decode out: %v", i, err)
		}
		if len(in.Rand) != 1 {
			t.Fatalf("record %d: want 1 raw draw in the input, got %d", i, len(in.Rand))
		}
		if len(in.Args) < 4 {
			t.Fatalf("record %d: want at least 4 arg words, got %d", i, len(in.Args))
		}
		bits := uint64(uint32(in.Args[2])) | uint64(uint32(in.Args[3]))<<32
		if got := math.Float64frombits(bits); got != in.Param {
			t.Fatalf("record %d: arg words give scale %v, param is %v", i, got, in.Param)
		}
		r, err := rng.NewReplay(in.Rand)
		if err != nil {
			t.Fatalf("record %d: replay: %v", i, err)
		}
		got := scaledDraw(r.Float64(), in.Param)
		if got != out.Value {
			t.Fatalf("record %d: scaledDraw = %v, capture value = %v", i, got, out.Value)
		}
		draws := r.Draws()
		if len(draws) != 1 || draws[0].Value != out.Ret || out.Ret != in.Rand[0] {
			t.Fatalf("record %d: draws %v, capture ret %d, input rand %v", i, draws, out.Ret, in.Rand)
		}
		if in.Param == andOneBucketFloor {
			floor++
			rf, err := rng.NewReplay(in.Rand)
			if err != nil {
				t.Fatalf("record %d: floor replay: %v", i, err)
			}
			if w := bucket3Weight(0, -1, 0, false, rf); w != out.Value {
				t.Fatalf("record %d: bucket3Weight floor = %v, capture value = %v", i, w, out.Value)
			}
			if n := len(rf.Draws()); n != 1 {
				t.Fatalf("record %d: bucket3Weight floor took %d draws, want 1", i, n)
			}
		}
	}
	if floor == 0 {
		t.Fatal("no record uses andOneBucketFloor")
	}
}
