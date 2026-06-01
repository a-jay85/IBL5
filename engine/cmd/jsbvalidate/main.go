// Command jsbvalidate is the offline distributional fidelity harness CLI for
// the JSB native engine (PR9b). It reads a directory of JSB 5.60 backup triples
// (.plr/.sch/.sco), runs the engine N seeded times per corpus game, aggregates
// per-team/per-stat distributions, and compares them against the .sco
// ground-truth aggregates within tolerance bands — printing a PASS/FAIL report
// and exiting nonzero on any out-of-band stat, unmatched game, or read error.
//
// Usage:
//
//	jsbvalidate --corpus <dir> [--runs N] [--seed S] [--game-type T]
//
// The bar is statistical, not byte-exact: the Go engine's RNG differs from
// jumpshot.exe, so validation is aggregate-within-tolerance over many seeds.
//
// --game-type stamps every assembled game, since neither the .sch nor the .sco
// carries one. It defaults to 2 (regular season). A corpus of playoff (4) or
// all-star (5/6) backups MUST be run with the matching type, or the regular
// vs. non-regular basis mismatch makes the comparison meaningless. The
// tolerance bands are STARTING values pending corpus calibration (see the PR9b
// plan, OPEN #1), so a FAIL today is a diagnostic, not necessarily a bug.
//
// This CLI is developer-/nightly-invoked only — it is NOT wired into CI; the
// corpus files are large and not in the repo.
package main

import (
	"flag"
	"fmt"
	"io"
	"os"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

func main() {
	os.Exit(run(os.Args[1:], os.Stdout, os.Stderr))
}

// run is the testable entrypoint: it takes args and output streams explicitly
// so tests can drive it without the process globals. It returns the process
// exit code (0 = all stats in band, nonzero = any FAIL, unmatched game, or
// usage/read error).
func run(args []string, stdout, stderr io.Writer) int {
	fs := flag.NewFlagSet("jsbvalidate", flag.ContinueOnError)
	fs.SetOutput(stderr)
	corpus := fs.String("corpus", "", "directory of .plr/.sch/.sco backup triples (required)")
	runs := fs.Int("runs", 200, "seeded engine runs per corpus game")
	seed := fs.Uint64("seed", 0, "base seed; per-game seeds derive deterministically from it")
	gameType := fs.Int("game-type", int(bundle.GameTypeRegular),
		"JSB game type stamped on every game: 2/3 regular, 4 playoff, 5/6 all-star")
	if err := fs.Parse(args); err != nil {
		return 2
	}
	if *corpus == "" {
		fmt.Fprintln(stderr, "jsbvalidate: --corpus <dir> is required")
		return 2
	}
	switch *gameType {
	case 2, 3, 4, 5, 6: // regular / regular-alt / playoff / all-star A / all-star B
	default:
		fmt.Fprintf(stderr, "jsbvalidate: invalid --game-type %d (valid: 2,3 regular; 4 playoff; 5,6 all-star)\n", *gameType)
		return 2
	}
	gt := bundle.GameType(*gameType)

	rep, err := validate.ValidateCorpus(*corpus, *runs, *seed, gt)
	if err != nil {
		fmt.Fprintln(stderr, "jsbvalidate:", err)
		return 1
	}
	validate.WriteReport(stdout, rep)
	if !rep.Pass {
		return 1
	}
	return 0
}
