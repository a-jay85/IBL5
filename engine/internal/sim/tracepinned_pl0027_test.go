package sim

import (
	"encoding/json"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// pl0027Block is one capture entry: a record's block of ints, plus the
// record it was copied from (Src) or written back to (Dst).
type pl0027Block struct {
	Src    int   `json:"src"`
	Dst    int   `json:"dst"`
	Fields []int `json:"fields"`
}

// pl0027In is one capture input: the raw draws, the blocks copied out and the
// scale for each record's draw.
type pl0027In struct {
	Rand     []int         `json:"rand"`
	CopySrc  []pl0027Block `json:"copy_src"`
	ScaleArg []int         `json:"scale_arg"`
}

// pl0027Out is one capture output: the void return residue and the blocks
// written back.
type pl0027Out struct {
	Ret    int           `json:"ret"`
	Assign []pl0027Block `json:"assign"`
}

// TestTracePinnedPl0027DayStartPass replays every capture record through
// dayStartScaledPass. It asserts the pass consumes exactly the captured raw
// draws in order, and that every written-back block equals the capture's
// block (`out.assign`) at every index. The capture's output carries no truncated draw, so
// that value is not pinned here; TestDayStartScaledPassTruncatesScaledDraw
// pins the truncation rule.
func TestTracePinnedPl0027DayStartPass(t *testing.T) {
	c := jsbtrace.MustLoad(t, "pl-0027.jsonl")
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	for i, rec := range c.Records {
		var in pl0027In
		var out pl0027Out
		if err := json.Unmarshal(rec.In, &in); err != nil {
			t.Fatalf("record %d: decode in: %v", i, err)
		}
		if err := json.Unmarshal(rec.Out, &out); err != nil {
			t.Fatalf("record %d: decode out: %v", i, err)
		}
		if len(in.CopySrc) == 0 {
			t.Fatalf("record %d: no copied blocks in the input", i)
		}
		if len(in.ScaleArg) != len(in.CopySrc) {
			t.Fatalf("record %d: %d scales for %d copied blocks", i, len(in.ScaleArg), len(in.CopySrc))
		}
		if len(out.Assign) != len(in.CopySrc) {
			t.Fatalf("record %d: %d written-back blocks for %d copied blocks", i, len(out.Assign), len(in.CopySrc))
		}
		for k := range in.CopySrc {
			if len(in.CopySrc[k].Fields) != dayStartBlockLen {
				t.Fatalf("record %d: copied block %d has %d fields, want %d", i, k, len(in.CopySrc[k].Fields), dayStartBlockLen)
			}
			if len(out.Assign[k].Fields) != dayStartBlockLen {
				t.Fatalf("record %d: written-back block %d has %d fields, want %d", i, k, len(out.Assign[k].Fields), dayStartBlockLen)
			}
			if out.Assign[k].Dst != in.CopySrc[k].Src {
				t.Fatalf("record %d: block %d is written back to a different record than it was copied from", i, k)
			}
		}
		if out.Ret != 0 {
			t.Fatalf("record %d: void routine logged return %d, want 0", i, out.Ret)
		}

		blocks := make([]dayStartBlock, len(in.CopySrc))
		for k := range in.CopySrc {
			copy(blocks[k][:], in.CopySrc[k].Fields)
		}
		r, err := rng.NewReplay(in.Rand)
		if err != nil {
			t.Fatalf("record %d: replay: %v", i, err)
		}
		back, _, err := dayStartScaledPass(blocks, in.ScaleArg, r)
		if err != nil {
			t.Fatalf("record %d: dayStartScaledPass: %v", i, err)
		}

		d := r.Draws()
		if len(d) != len(in.Rand) {
			t.Fatalf("record %d: took %d draws, capture holds %d", i, len(d), len(in.Rand))
		}
		for j := range d {
			if d[j].Value != in.Rand[j] {
				t.Fatalf("record %d: draw %d = %d, capture raw draw = %d", i, j, d[j].Value, in.Rand[j])
			}
		}

		for k := range out.Assign {
			var want dayStartBlock
			copy(want[:], out.Assign[k].Fields)
			if back[k] != want {
				t.Fatalf("record %d: block %d written back %v, capture %v", i, k, back[k], want)
			}
		}
	}
	t.Logf("pinned %d capture records", len(c.Records))
}
