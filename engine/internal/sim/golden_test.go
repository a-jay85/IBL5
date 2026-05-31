package sim

import (
	"bytes"
	"encoding/json"
	"flag"
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

var update = flag.Bool("update", false, "regenerate golden files from current output")

// encode mirrors the cmd/jsbsim encoding exactly (indented JSON via Encoder, so
// a trailing newline is included) so the golden bytes match real CLI output.
func encode(t *testing.T, v any) []byte {
	t.Helper()
	var buf bytes.Buffer
	enc := json.NewEncoder(&buf)
	enc.SetIndent("", "  ")
	if err := enc.Encode(v); err != nil {
		t.Fatalf("encode: %v", err)
	}
	return buf.Bytes()
}

// #11 — golden master: a fixed input fixture run at its fixed seed must produce
// byte-stable output. Run `go test ./internal/sim -run Golden -update` to
// regenerate after an intentional output change.
func TestGolden(t *testing.T) {
	in, err := os.ReadFile(filepath.Join("testdata", "bundle.json"))
	if err != nil {
		t.Fatalf("read fixture: %v", err)
	}
	b, err := bundle.Decode(in)
	if err != nil {
		t.Fatalf("decode fixture: %v", err)
	}

	got := encode(t, Simulate(b, b.Seed))

	goldenPath := filepath.Join("testdata", "golden.json")
	if *update {
		if err := os.WriteFile(goldenPath, got, 0o644); err != nil {
			t.Fatalf("write golden: %v", err)
		}
		return
	}

	want, err := os.ReadFile(goldenPath)
	if err != nil {
		t.Fatalf("read golden (run with -update to create): %v", err)
	}
	if !bytes.Equal(got, want) {
		t.Errorf("output does not match golden.json.\n--- got ---\n%s\n--- want ---\n%s", got, want)
	}
}

// #26 — determinism: the same seed must reproduce byte-identical output, and a
// different seed must change it (otherwise the seed flow is dead).
func TestDeterminism(t *testing.T) {
	b := richBundle()
	a1 := encode(t, Simulate(b, 1988))
	a2 := encode(t, Simulate(b, 1988))
	if !bytes.Equal(a1, a2) {
		t.Error("same seed produced different output")
	}
	b3 := encode(t, Simulate(b, 1989))
	if bytes.Equal(a1, b3) {
		t.Error("different seeds produced identical output")
	}
}
