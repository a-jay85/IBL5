package sim

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"flag"
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

var update = flag.Bool("update", false, "regenerate golden files from current output")

// goldenSeedSweep is how many seeds after the fixture seed TestGolden also plays
// and hashes into testdata/golden_seeds.sha256. One game draws about 27 code-4
// turnover rolls, so a 0.1 → 0.11 change to a 10% cut changes a single game only
// about 1 time in 4. Over 30 more games the chance it changes none is under 1%.
const goldenSeedSweep = 30

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
// byte-stable output, and the goldenSeedSweep games after it must hash to the
// pinned value. Run `go test ./internal/sim -run Golden -update` to
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
	h := sha256.New()
	for i := uint64(1); i <= goldenSeedSweep; i++ {
		h.Write(encode(t, Simulate(b, b.Seed+i)))
	}
	gotSweep := []byte(hex.EncodeToString(h.Sum(nil)) + "\n")

	goldenPath := filepath.Join("testdata", "golden.json")
	sweepPath := filepath.Join("testdata", "golden_seeds.sha256")
	if *update {
		if err := os.WriteFile(goldenPath, got, 0o644); err != nil {
			t.Fatalf("write golden: %v", err)
		}
		if err := os.WriteFile(sweepPath, gotSweep, 0o644); err != nil {
			t.Fatalf("write golden seed sweep: %v", err)
		}
		return
	}

	wantSweep, err := os.ReadFile(sweepPath)
	if err != nil {
		t.Fatalf("read golden seed sweep (run with -update to create): %v", err)
	}
	if !bytes.Equal(gotSweep, wantSweep) {
		t.Errorf("seed-sweep hash over seeds %d..%d = %s, want %s (golden_seeds.sha256)",
			b.Seed+1, b.Seed+goldenSeedSweep, bytes.TrimSpace(gotSweep), bytes.TrimSpace(wantSweep))
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
