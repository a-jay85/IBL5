// Command jsbsim runs the JSB-compatible basketball simulation engine.
//
// It reads a simulation input bundle as JSON on stdin and writes the result as
// NDJSON on stdout. The engine is a pure transform: it touches no database, no
// network, and no files beyond stdin/stdout. The PHP side builds the bundle
// (from the database, or from a historical backup .plr for validation) and
// loads the result into the IBL5 updateAllTheThings pipeline.
//
// Output is newline-delimited JSON: a header line {"seed":N} followed by one
// compact GameResult per line. This lets the PHP loader stream one game at a
// time at constant memory instead of decoding a single multi-hundred-MB object.
//
// Usage:
//
//	jsbsim [--seed N] < bundle.json > result.ndjson
//
// --seed N (N >= 0) overrides the seed carried in the bundle; the seed actually
// used is echoed on the header line so any run can be replayed.
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

	// NDJSON: header line {"seed":N}, then one compact GameResult per line. A
	// non-indented json.Encoder appends '\n' after each value, so each game is
	// single-line. A no-games bundle emits the header line and zero game lines.
	if _, err := fmt.Fprintf(stdout, "{\"seed\":%d}\n", res.Seed); err != nil {
		return fmt.Errorf("encoding header: %w", err)
	}
	enc := json.NewEncoder(stdout)
	for i := range res.Games {
		if err := enc.Encode(&res.Games[i]); err != nil {
			return fmt.Errorf("encoding game: %w", err)
		}
	}
	return nil
}
