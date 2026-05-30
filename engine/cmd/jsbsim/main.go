// Command jsbsim runs the JSB-compatible basketball simulation engine.
//
// It reads a simulation input bundle as JSON on stdin and writes the result as
// JSON on stdout. The engine is a pure transform: it touches no database, no
// network, and no files beyond stdin/stdout. The PHP side builds the bundle
// (from the database, or from a historical backup .plr for validation) and
// loads the result into the IBL5 updateAllTheThings pipeline.
//
// Usage:
//
//	jsbsim [--seed N] < bundle.json > result.json
//
// --seed N (N >= 0) overrides the seed carried in the bundle; the seed actually
// used is echoed in the output so any run can be replayed.
package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"os"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

func main() {
	if err := run(os.Args[1:], os.Stdin, os.Stdout); err != nil {
		fmt.Fprintln(os.Stderr, "jsbsim:", err)
		os.Exit(1)
	}
}

// run is the testable entrypoint: it takes the args, input, and output
// explicitly so tests can drive it without touching the process globals.
func run(args []string, stdin io.Reader, stdout io.Writer) error {
	fs := flag.NewFlagSet("jsbsim", flag.ContinueOnError)
	seedOverride := fs.Int64("seed", -1, "override the bundle seed (N >= 0); -1 uses the bundle's own seed")
	if err := fs.Parse(args); err != nil {
		return err
	}

	data, err := io.ReadAll(stdin)
	if err != nil {
		return fmt.Errorf("reading stdin: %w", err)
	}

	b, err := bundle.Decode(data)
	if err != nil {
		return err
	}

	seed := b.Seed
	if *seedOverride >= 0 {
		seed = uint64(*seedOverride)
	}

	res := sim.Simulate(b, seed)

	enc := json.NewEncoder(stdout)
	enc.SetIndent("", "  ")
	if err := enc.Encode(res); err != nil {
		return fmt.Errorf("encoding result: %w", err)
	}
	return nil
}
