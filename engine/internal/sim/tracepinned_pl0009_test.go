package sim

import (
	"encoding/json"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// pl0009In is one site-capture input: the site name, the three shot-type
// weights, and the raw draws the site consumed, in draw order.
type pl0009In struct {
	Site    string `json:"site"`
	Weights []int  `json:"weights"`
	Rand    []int  `json:"rand"`
}

// pl0009Out is one site-capture output: the chosen shot type, 1..3.
type pl0009Out struct {
	Type int `json:"type"`
}

// pl0009PickCount is the number of pick records in pl-0009.jsonl, counted on
// the capture during planning.
const pl0009PickCount = 1830

func decodePl0009In(t *testing.T, i int, raw json.RawMessage) pl0009In {
	t.Helper()
	var in pl0009In
	if err := json.Unmarshal(raw, &in); err != nil {
		t.Fatalf("record %d: decode in: %v", i, err)
	}
	return in
}

// TestTracePinnedPl0009Pick replays each repick/pick record pair through
// selectShotType. The re-pick draw and the pick draw must be the only two raw
// draws, in capture order, and the chosen type must equal the pick record's
// captured type exactly.
func TestTracePinnedPl0009Pick(t *testing.T) {
	c := jsbtrace.MustLoad(t, "pl-0009.jsonl")
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	if len(c.Records)%2 != 0 {
		t.Fatalf("capture holds %d records; want repick/pick pairs", len(c.Records))
	}
	picks := 0
	for i := 0; i < len(c.Records); i += 2 {
		rp, pk := c.Records[i], c.Records[i+1]
		requireKeys(t, i, "in", rp.In, "site", "rand")
		requireKeys(t, i+1, "in", pk.In, "site", "weights", "rand")
		requireKeys(t, i+1, "out", pk.Out, "type")
		repick := decodePl0009In(t, i, rp.In)
		pick := decodePl0009In(t, i+1, pk.In)
		var out pl0009Out
		if err := json.Unmarshal(pk.Out, &out); err != nil {
			t.Fatalf("record %d: decode out: %v", i+1, err)
		}
		if repick.Site != "repick" || pick.Site != "pick" {
			t.Fatalf("records %d,%d: sites %q,%q; want repick then pick (a keep record needs a sticky flag the engine lacks; stop and record this blocker)", i, i+1, repick.Site, pick.Site)
		}
		if len(repick.Rand) != 1 || len(pick.Rand) != 1 {
			t.Fatalf("records %d,%d: want 1 raw draw per site, got %d and %d", i, i+1, len(repick.Rand), len(pick.Rand))
		}
		if len(pick.Weights) != 3 {
			t.Fatalf("record %d: want 3 weights, got %v", i+1, pick.Weights)
		}
		for _, w := range pick.Weights {
			if w < 1 {
				t.Fatalf("record %d: weight %d below 1 in %v; the engine floors at 1, so stop and record this blocker", i+1, w, pick.Weights)
			}
		}
		if out.Type < 1 || out.Type > 3 {
			t.Fatalf("record %d: captured type %d is outside 1..3", i+1, out.Type)
		}
		handler := onCourt{Player: bundle.Player{OO: pick.Weights[0], DriveOff: pick.Weights[1], PO: pick.Weights[2]}, slot: slotSF}
		defender := onCourt{slot: slotSF}
		r, err := rng.NewReplay([]int{repick.Rand[0], pick.Rand[0]})
		if err != nil {
			t.Fatalf("records %d,%d: replay: %v", i, i+1, err)
		}
		if got := selectShotType(handler, defender, r); int(got)+1 != out.Type {
			t.Fatalf("record %d: weights %v draw %d: selectShotType = type %d, capture type = %d", i+1, pick.Weights, pick.Rand[0], int(got)+1, out.Type)
		}
		draws := r.Draws()
		if len(draws) != 2 || draws[0].API != "raw" || draws[0].Value != repick.Rand[0] ||
			draws[1].API != "raw" || draws[1].Value != pick.Rand[0] {
			t.Fatalf("records %d,%d: draws %v, want raw %d then raw %d", i, i+1, draws, repick.Rand[0], pick.Rand[0])
		}
		picks++
	}
	if picks != pl0009PickCount {
		t.Fatalf("asserted %d pick records, want %d", picks, pl0009PickCount)
	}
}
