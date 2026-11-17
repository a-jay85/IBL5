// Command reallife-fixture builds the committed real-life engine fixture
// (internal/sim/testdata/bundle_reallife.json) from a JSB .plr, and runs the
// league-rate calibration gate that proves the Go reader matches the J18 reader.
//
// Usage:
//
//	reallife-fixture -plr <path> -calibrate
//	reallife-fixture -plr <path> -out <path> [-sch <path>]
//
// -plr is required and has no default: no committed file names a .plr path.
// With -calibrate it prints the J18 population size, ΣMIN and the league
// PF/TOV/FTA/FGA per-48 rates. Otherwise it assembles the full roster and
// schedule through backup.ToBundle and writes indented JSON to -out. -sch
// defaults to the .sch beside the .plr (same stem, .sch extension).
package main

import (
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/a-jay85/IBL5/engine/internal/backup"
)

func main() {
	if err := run(os.Args[1:], os.Stdout); err != nil {
		fmt.Fprintln(os.Stderr, "reallife-fixture:", err)
		os.Exit(1)
	}
}

func run(args []string, stdout io.Writer) error {
	fs := flag.NewFlagSet("reallife-fixture", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	plrPath := fs.String("plr", "", "path to the JSB .plr roster file (required)")
	schPath := fs.String("sch", "", "path to the JSB .sch schedule (default: the .sch beside -plr)")
	outPath := fs.String("out", "", "fixture JSON output path (required unless -calibrate)")
	calibrate := fs.Bool("calibrate", false, "print the league per-48 rate calibration and exit")
	if err := fs.Parse(args); err != nil {
		return err
	}
	if *plrPath == "" {
		return errors.New("missing required flag -plr")
	}
	if !*calibrate && *outPath == "" {
		return errors.New("one of -calibrate or -out is required")
	}
	players, err := readFile(*plrPath, backup.ReadPlr)
	if err != nil {
		return err
	}
	if *calibrate {
		return printCalibration(stdout, players)
	}
	sch := *schPath
	if sch == "" {
		sch = strings.TrimSuffix(*plrPath, filepath.Ext(*plrPath)) + ".sch"
	}
	sched, err := readFile(sch, backup.ReadSch)
	if err != nil {
		return err
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{})
	if err != nil {
		return err
	}
	data, err := json.MarshalIndent(b, "", "  ")
	if err != nil {
		return fmt.Errorf("marshal fixture: %w", err)
	}
	data = append(data, '\n')
	if err := os.WriteFile(*outPath, data, 0o644); err != nil {
		return fmt.Errorf("write %q: %w", *outPath, err)
	}
	return nil
}

// printCalibration prints n, ΣMIN and the four league per-48 rates in
// shortest round-trip form, one key=value per line.
func printCalibration(w io.Writer, players []backup.PlrPlayer) error {
	rate, n, sumMIN := backup.LeagueRate48Calibration(players)
	if rate == nil {
		return errors.New("no J18-eligible player has real-life minutes")
	}
	f := func(v float64) string { return strconv.FormatFloat(v, 'g', -1, 64) }
	_, err := fmt.Fprintf(w, "n=%d\nsumMIN=%d\npf48=%s\ntov48=%s\nfta48=%s\nfga48=%s\n",
		n, sumMIN, f(rate.PF), f(rate.TOV), f(rate.FTA), f(rate.FGA))
	return err
}

// readFile opens path and applies a backup reader, wrapping any error with
// the path (the internal/validate harness readFile shape).
func readFile[T any](path string, read func(io.Reader) ([]T, error)) ([]T, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, fmt.Errorf("open %q: %w", path, err)
	}
	defer func() { _ = f.Close() }()
	out, err := read(f)
	if err != nil {
		return nil, fmt.Errorf("parse %q: %w", path, err)
	}
	return out, nil
}
