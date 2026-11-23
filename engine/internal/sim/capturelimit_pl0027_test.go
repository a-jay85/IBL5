package sim

import (
	"encoding/json"
	"sort"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
)

// The pl-0027 capture carries only these keys today. Its records hold no
// record field values on entry or exit, so no Go port can be pinned against
// it yet (engine/docs/jsb-trace-capture.md, "Rows blocked on a richer
// capture"). When a recapture adds field keys, the live test below fails and
// the row is ready for a trace-pinned port that replaces this file.
var (
	pl0027InKeys  = []string{"args", "rand"}
	pl0027OutKeys = []string{"ret"}
)

// extraCaptureKeys decodes raw as one JSON object and returns its keys that
// are absent from allowed, sorted. It returns an error when raw is not an
// object.
func extraCaptureKeys(raw []byte, allowed []string) ([]string, error) {
	var m map[string]json.RawMessage
	if err := json.Unmarshal(raw, &m); err != nil {
		return nil, err
	}
	ok := make(map[string]bool, len(allowed))
	for _, k := range allowed {
		ok[k] = true
	}
	var extra []string
	for k := range m {
		if !ok[k] {
			extra = append(extra, k)
		}
	}
	sort.Strings(extra)
	return extra, nil
}

// TestCaptureLimitPl0027KeyCheck runs extraCaptureKeys on synthetic records,
// so the key check itself is held without the private capture.
func TestCaptureLimitPl0027KeyCheck(t *testing.T) {
	cases := []struct {
		name    string
		raw     string
		want    string
		wantErr bool
	}{
		{"allowed keys only", `{"args":[1],"rand":[2]}`, "", false},
		{"one field input", `{"args":[1],"rand":[2],"rating":7}`, "rating", false},
		{"two field inputs sorted", `{"z":1,"args":[],"a":2}`, "a,z", false},
		{"empty object", `{}`, "", false},
		{"not an object", `[1,2]`, "", true},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			got, err := extraCaptureKeys([]byte(tc.raw), pl0027InKeys)
			if tc.wantErr {
				if err == nil {
					t.Fatalf("want a decode error, got keys %v", got)
				}
				return
			}
			if err != nil {
				t.Fatalf("decode: %v", err)
			}
			if s := strings.Join(got, ","); s != tc.want {
				t.Fatalf("extra keys = %q, want %q", s, tc.want)
			}
		})
	}
}

// TestCaptureLimitPl0027NoFieldInputs checks that the pl-0027 capture still
// carries no record field values. A failure means the capture was enriched:
// port the routine with a trace-pinned test and delete this file.
func TestCaptureLimitPl0027NoFieldInputs(t *testing.T) {
	jsbtrace.SkipIfAbsent(t, "pl-0027.jsonl")
	c := jsbtrace.MustLoad(t, "pl-0027.jsonl")
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	for i, rec := range c.Records {
		var draws struct {
			Rand []int `json:"rand"`
		}
		if err := json.Unmarshal(rec.In, &draws); err != nil {
			t.Fatalf("record %d: decode in: %v", i, err)
		}
		if len(draws.Rand) == 0 {
			t.Fatalf("record %d: in carries no rand draws", i)
		}
		extraIn, err := extraCaptureKeys(rec.In, pl0027InKeys)
		if err != nil {
			t.Fatalf("record %d: decode in keys: %v", i, err)
		}
		extraOut, err := extraCaptureKeys(rec.Out, pl0027OutKeys)
		if err != nil {
			t.Fatalf("record %d: decode out keys: %v", i, err)
		}
		if len(extraIn) > 0 || len(extraOut) > 0 {
			t.Fatalf("record %d: capture now carries field keys (in %v, out %v); pl-0027 is unblocked for a trace-pinned port", i, extraIn, extraOut)
		}
	}
}
