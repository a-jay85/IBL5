package sim

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// TestOptionsRNGReplacesSeededStream ties the runtime caller names logged by
// rng.NewReplay to the AST-derived DrawSites keys, and proves SimulateWith
// draws from Options.RNG instead of the seeded PCG stream.
func TestOptionsRNGReplacesSeededStream(t *testing.T) {
	in, err := os.ReadFile(filepath.Join("testdata", "bundle.json"))
	if err != nil {
		t.Fatalf("read fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode fixture: %v", err)
	}
	b.Schedule = b.Schedule[:1]

	src := rng.New(11)
	values := make([]int, 200000)
	for i := range values {
		values[i] = src.Raw()
	}
	rep, err := rng.NewReplay(values)
	if err != nil {
		t.Fatalf("NewReplay: %v", err)
	}
	if _, err := SimulateWith(b, 1, Options{RNG: rep}); err != nil {
		t.Fatalf("SimulateWith: %v", err)
	}
	draws := rep.Draws()
	if len(draws) == 0 {
		t.Fatal("replay source logged no draws: Options.RNG was not used")
	}
	for i, d := range draws {
		if _, ok := DrawSites[d.Caller]; !ok {
			t.Fatalf("draw %d caller %q is not a DrawSites key", i, d.Caller)
		}
	}
}
