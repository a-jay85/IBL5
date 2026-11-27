package sim

import (
	"encoding/json"
	"sort"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// pl0005In is one capture input: the rand_int bound and the raw draws the
// routine consumed, in draw order.
type pl0005In struct {
	Max  int   `json:"max"`
	Rand []int `json:"rand"`
}

// pl0005Out is one capture output: the routine's return value.
type pl0005Out struct {
	Ret int `json:"ret"`
}

// requireKeys fails the test when the raw capture object lacks any named key.
// The test never guesses a field name.
func requireKeys(t *testing.T, i int, side string, raw json.RawMessage, keys ...string) {
	t.Helper()
	var m map[string]json.RawMessage
	if err := json.Unmarshal(raw, &m); err != nil {
		t.Fatalf("record %d: decode %s: %v", i, side, err)
	}
	for _, k := range keys {
		if _, ok := m[k]; !ok {
			present := make([]string, 0, len(m))
			for p := range m {
				present = append(present, p)
			}
			sort.Strings(present)
			t.Fatalf("record %d: capture %s has no %q field (keys present: %v); stop and record this blocker", i, side, k, present)
		}
	}
}

// TestTracePinnedPl0005RandInt replays every capture record through randInt
// and asserts the result equals the captured return exactly, from one raw draw
// equal to in.rand[0].
func TestTracePinnedPl0005RandInt(t *testing.T) {
	c := jsbtrace.MustLoad(t, "pl-0005.jsonl")
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	for i, rec := range c.Records {
		requireKeys(t, i, "in", rec.In, "max", "rand")
		requireKeys(t, i, "out", rec.Out, "ret")
		var in pl0005In
		var out pl0005Out
		if err := json.Unmarshal(rec.In, &in); err != nil {
			t.Fatalf("record %d: decode in: %v", i, err)
		}
		if err := json.Unmarshal(rec.Out, &out); err != nil {
			t.Fatalf("record %d: decode out: %v", i, err)
		}
		if len(in.Rand) != 1 {
			t.Fatalf("record %d: want 1 raw draw in the input, got %d", i, len(in.Rand))
		}
		bound := in.Max
		if bound >= 1 && (out.Ret < 1 || out.Ret > bound) {
			t.Fatalf("record %d: capture ret %d is outside 1..%d, so in.max is not the bound; stop and record this blocker", i, out.Ret, bound)
		}
		r, err := rng.NewReplay(in.Rand)
		if err != nil {
			t.Fatalf("record %d: replay: %v", i, err)
		}
		if got := randInt(r, bound); got != out.Ret {
			t.Fatalf("record %d: randInt(%d) = %d, capture ret = %d", i, bound, got, out.Ret)
		}
		draws := r.Draws()
		if len(draws) != 1 || draws[0].API != "raw" || draws[0].Value != in.Rand[0] {
			t.Fatalf("record %d: draws %v, want one raw draw equal to in.rand[0] = %v", i, draws, in.Rand)
		}
	}
}
