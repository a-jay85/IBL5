// Command jsbcalibrate is the offline band-calibration harness CLI for the JSB
// native engine (PR9c). It walks a directory tree of JSB 5.60 backup zips (each
// zip carries a complete IBL5.plr/.sch/.sco triple — e.g. ibl5/backups), runs
// the PR9b validation harness on each snapshot under the game type inferred from
// its path, and either:
//
//	--mode calibrate : emits proposed per-game-type tolerance bands (JSON),
//	                   derived from the percentile of engine-mean-vs-.sco
//	                   residuals across all snapshots; or
//	--mode gate      : applies the COMMITTED validate bands across the archive
//	                   and emits per-game-type in-band pass rates (JSON),
//	                   exiting nonzero if any bucket falls below --min-rate.
//
// Usage:
//
//	jsbcalibrate --archive <dir> [--mode calibrate|gate] [--runs N] [--seed S]
//	             [--sample-stride N] [--include-olympics] [--coverage P]
//	             [--min-rate R]
//
// This CLI is developer-/nightly-invoked only — it is NOT wired into CI; the
// ~53 GB backup archive is large and not in the repo. Committing the emitted
// calibrate-mode values into internal/validate/bands.go is a data-only follow-up.
package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"os"

	"github.com/a-jay85/IBL5/engine/internal/calibrate"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

func main() {
	os.Exit(run(os.Args[1:], os.Stdout, os.Stderr))
}

// collectFunc is the seam to a calibrate collector, injected so the CLI's
// flag/mode/encoding paths are testable without walking a real archive.
type collectFunc func(root string, opts calibrate.Options) ([]validate.Report, []calibrate.Skip, error)

// collectors holds the two selection strategies the CLI can dispatch to.
type collectors struct {
	season collectFunc // set-difference per season (clean regular + playoff buckets)
	flat   collectFunc // every zip independently, type by filename
}

func run(args []string, stdout, stderr io.Writer) int {
	return runWith(args, stdout, stderr, collectors{
		season: calibrate.CollectSeasonReports,
		flat:   calibrate.CollectReports,
	})
}

func runWith(args []string, stdout, stderr io.Writer, c collectors) int {
	fs := flag.NewFlagSet("jsbcalibrate", flag.ContinueOnError)
	fs.SetOutput(stderr)
	archive := fs.String("archive", "", "root dir of JSB backup zips (required)")
	mode := fs.String("mode", "calibrate", "calibrate | gate")
	selection := fs.String("selection", "season", "snapshot selection: season (one regular snapshot/season, clean regular bucket) | flat (every zip, type by filename)")
	runs := fs.Int("runs", 50, "seeded engine runs per corpus game")
	seed := fs.Uint64("seed", 0, "base seed; per-game seeds derive deterministically from it")
	stride := fs.Int("sample-stride", 1, "process every Nth qualifying snapshot")
	includeOlympics := fs.Bool("include-olympics", false, "include olympics/ snapshots (game-type 6)")
	coverage := fs.Float64("coverage", 0.95, "calibrate: residual coverage percentile (0,1)")
	minRate := fs.Float64("min-rate", 0.90, "gate: minimum per-stat in-band rate to pass")
	if err := fs.Parse(args); err != nil {
		return 2
	}
	if *archive == "" {
		_, _ = fmt.Fprintln(stderr, "jsbcalibrate: --archive <dir> is required")
		return 2
	}
	if *mode != "calibrate" && *mode != "gate" {
		_, _ = fmt.Fprintf(stderr, "jsbcalibrate: invalid --mode %q (valid: calibrate, gate)\n", *mode)
		return 2
	}
	var collect collectFunc
	switch *selection {
	case "season":
		collect = c.season
	case "flat":
		collect = c.flat
	default:
		_, _ = fmt.Fprintf(stderr, "jsbcalibrate: invalid --selection %q (valid: season, flat)\n", *selection)
		return 2
	}

	opts := calibrate.Options{
		Runs:            *runs,
		Seed:            *seed,
		SampleStride:    *stride,
		IncludeOlympics: *includeOlympics,
		Progress:        stderr,
	}
	reports, skips, err := collect(*archive, opts)
	if err != nil {
		_, _ = fmt.Fprintln(stderr, "jsbcalibrate:", err)
		return 1
	}
	for _, s := range skips {
		_, _ = fmt.Fprintf(stderr, "skip %s: %s\n", s.Path, s.Reason)
	}
	if len(reports) == 0 {
		_, _ = fmt.Fprintln(stderr, "jsbcalibrate: no snapshots produced reports")
		return 1
	}

	enc := json.NewEncoder(stdout)
	enc.SetIndent("", "  ")
	if *mode == "gate" {
		res := calibrate.Gate(reports, *minRate)
		_ = enc.Encode(res)
		if !res.Pass {
			return 1
		}
		return 0
	}
	_ = enc.Encode(calibrate.Calibrate(reports, *coverage))
	return 0
}
