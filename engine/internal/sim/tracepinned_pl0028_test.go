package sim

import (
	"encoding/json"
	"errors"
	"fmt"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// pl0028In is one capture input: the raw draws in draw order.
type pl0028In struct {
	Rand []int `json:"rand"`
}

// pl0028Out is one capture output: the returned team index.
type pl0028Out struct {
	Ret *int `json:"ret"`
}

// decodePl0028Record decodes one record and fails loudly when it carries no
// rand draw or no ret, so a capture too thin to pin the port blocks the test.
func decodePl0028Record(inRaw, outRaw json.RawMessage) (pl0028In, pl0028Out, error) {
	var in pl0028In
	var out pl0028Out
	if err := json.Unmarshal(inRaw, &in); err != nil {
		return in, out, fmt.Errorf("decode in: %w", err)
	}
	if err := json.Unmarshal(outRaw, &out); err != nil {
		return in, out, fmt.Errorf("decode out: %w", err)
	}
	if len(in.Rand) == 0 {
		return in, out, errors.New("capture in carries no rand draw; port blocked")
	}
	if out.Ret == nil {
		return in, out, errors.New("capture out carries no ret; port blocked")
	}
	return in, out, nil
}

// TestTracePinnedPl0028TipOff replays every capture record through
// tipOffWinner. It asserts the returned index equals the capture's ret, and
// that the port takes exactly the captured raw draws, every one logged as raw.
func TestTracePinnedPl0028TipOff(t *testing.T) {
	jsbtrace.SkipIfAbsent(t, "pl-0028.jsonl")
	c := jsbtrace.MustLoad(t, "pl-0028.jsonl")
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	for i, rec := range c.Records {
		in, out, err := decodePl0028Record(rec.In, rec.Out)
		if err != nil {
			t.Fatalf("record %d: %v", i, err)
		}
		r, err := rng.NewReplay(in.Rand)
		if err != nil {
			t.Fatalf("record %d: replay: %v", i, err)
		}
		got := tipOffWinner(r)
		if got != *out.Ret {
			t.Fatalf("record %d: tipOffWinner returned a different index than the capture's ret", i)
		}
		d := r.Draws()
		if len(d) != len(in.Rand) {
			t.Fatalf("record %d: took %d draws, capture holds %d", i, len(d), len(in.Rand))
		}
		for j := range d {
			if d[j].API != "raw" {
				t.Fatalf("record %d: draw %d logged as %q, want raw", i, j, d[j].API)
			}
		}
	}
	t.Logf("pinned %d capture records", len(c.Records))
}

func TestTipOffWinnerReadsOneRawDraw(t *testing.T) {
	r, err := rng.NewReplay([]int{5})
	if err != nil {
		t.Fatal(err)
	}
	tipOffWinner(r)
	d := r.Draws()
	if len(d) != 1 {
		t.Fatalf("took %d draws, want 1", len(d))
	}
	if d[0].API != "raw" {
		t.Fatalf("draw logged as %q, want raw", d[0].API)
	}
}

func TestTipOffWinnerBoundaryDraws(t *testing.T) {
	cases := []struct{ draw, want int }{
		{rng.RawMax / 2, 1},
		{rng.RawMax/2 + 1, 2},
	}
	for _, tc := range cases {
		r, err := rng.NewReplay([]int{tc.draw})
		if err != nil {
			t.Fatal(err)
		}
		if got := tipOffWinner(r); got != tc.want {
			t.Errorf("draw %d: got %d, want %d", tc.draw, got, tc.want)
		}
	}
}

func TestTipOffWinnerExhaustedStreamPanics(t *testing.T) {
	r, err := rng.NewReplay(nil)
	if err != nil {
		t.Fatal(err)
	}
	defer func() {
		v := recover()
		if _, ok := v.(*rng.ExhaustedError); !ok {
			t.Fatalf("recovered %v, want *rng.ExhaustedError", v)
		}
	}()
	tipOffWinner(r)
	t.Fatal("no panic on an empty stream")
}

func TestPl0028DecodeRejectsMissingFields(t *testing.T) {
	cases := []struct {
		name, in, out, wantErr string
	}{
		{"missing rand", `{"args":[]}`, `{"ret":1}`, "rand"},
		{"empty rand", `{"rand":[]}`, `{"ret":1}`, "rand"},
		{"near-miss ret key", `{"rand":[3]}`, `{"retval":1}`, "ret"},
		{"well formed", `{"rand":[3]}`, `{"ret":0}`, ""},
	}
	for _, tc := range cases {
		_, _, err := decodePl0028Record(json.RawMessage(tc.in), json.RawMessage(tc.out))
		if tc.wantErr == "" {
			if err != nil {
				t.Errorf("%s: unexpected error %v", tc.name, err)
			}
			continue
		}
		if err == nil || !strings.Contains(err.Error(), tc.wantErr) {
			t.Errorf("%s: err = %v, want one naming %q", tc.name, err, tc.wantErr)
		}
	}
}
