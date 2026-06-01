package main

import (
	"bytes"
	"encoding/json"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/calibrate"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// stubCollect returns canned reports/skips regardless of args, so the CLI's
// flag/mode/encoding paths are exercised without walking a real archive.
func stubCollect(reports []validate.Report, skips []calibrate.Skip) collectFunc {
	return func(string, calibrate.Options) ([]validate.Report, []calibrate.Skip, error) {
		return reports, skips, nil
	}
}

func reportWith(gt bundle.GameType, rows ...validate.StatRow) validate.Report {
	return validate.Report{GameType: gt, Games: []validate.GameReport{{Rows: rows}}}
}

// Row #13: calibrate mode emits a JSON CalibrationReport with per-game-type
// proposed bands and exits 0.
func TestRun_CalibrateEmitsJSON(t *testing.T) {
	reports := []validate.Report{reportWith(bundle.GameTypeRegular,
		validate.StatRow{Stat: "points", ScoVal: 110, EngineMean: 100, Pass: true},
	)}
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--mode", "calibrate"}, &out, &errBuf, stubCollect(reports, nil))
	if code != 0 {
		t.Fatalf("exit = %d, want 0 (stderr: %s)", code, errBuf.String())
	}
	var rep calibrate.CalibrationReport
	if err := json.Unmarshal(out.Bytes(), &rep); err != nil {
		t.Fatalf("stdout is not a CalibrationReport JSON: %v\n%s", err, out.String())
	}
	if len(rep.Buckets) != 1 || rep.Buckets[0].GameType != int(bundle.GameTypeRegular) {
		t.Errorf("buckets = %+v, want one regular bucket", rep.Buckets)
	}
}

// Row #14 (negative): gate mode exits nonzero when a stat's in-band rate is
// below --min-rate, and the JSON reports pass=false.
func TestRun_GateFailsBelowMinRate(t *testing.T) {
	var rows []validate.StatRow
	for i := 0; i < 10; i++ {
		rows = append(rows, validate.StatRow{Stat: "points", Pass: i < 5}) // rate 0.5
	}
	reports := []validate.Report{reportWith(bundle.GameTypeRegular, rows...)}

	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--mode", "gate", "--min-rate", "0.9"}, &out, &errBuf, stubCollect(reports, nil))
	if code == 0 {
		t.Fatalf("exit = 0, want nonzero (rate 0.5 < 0.9)")
	}
	var res calibrate.GateResult
	if err := json.Unmarshal(out.Bytes(), &res); err != nil {
		t.Fatalf("stdout is not a GateResult JSON: %v", err)
	}
	if res.Pass {
		t.Error("GateResult.Pass = true, want false")
	}
}

// Gate mode exits 0 when every bucket meets the threshold.
func TestRun_GatePasses(t *testing.T) {
	reports := []validate.Report{reportWith(bundle.GameTypeRegular,
		validate.StatRow{Stat: "points", Pass: true},
	)}
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--mode", "gate"}, &out, &errBuf, stubCollect(reports, nil))
	if code != 0 {
		t.Fatalf("exit = %d, want 0 (stderr: %s)", code, errBuf.String())
	}
}

// Row #15 (negative): a missing --archive is a usage error (exit 2).
func TestRun_MissingArchive(t *testing.T) {
	var out, errBuf bytes.Buffer
	if code := runWith(nil, &out, &errBuf, stubCollect(nil, nil)); code != 2 {
		t.Fatalf("exit = %d, want 2", code)
	}
	if !strings.Contains(errBuf.String(), "--archive") {
		t.Errorf("expected a --archive usage message, got: %q", errBuf.String())
	}
}

// Negative: an invalid --mode is a usage error (exit 2).
func TestRun_InvalidMode(t *testing.T) {
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--mode", "bogus"}, &out, &errBuf, stubCollect(nil, nil))
	if code != 2 {
		t.Fatalf("exit = %d, want 2", code)
	}
	if !strings.Contains(errBuf.String(), "invalid --mode") {
		t.Errorf("expected an invalid --mode message, got: %q", errBuf.String())
	}
}

// Negative: an archive that yields no reports exits 1 with a diagnostic.
func TestRun_NoReports(t *testing.T) {
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x"}, &out, &errBuf, stubCollect(nil, nil))
	if code != 1 {
		t.Fatalf("exit = %d, want 1", code)
	}
	if !strings.Contains(errBuf.String(), "no snapshots") {
		t.Errorf("expected a 'no snapshots' message, got: %q", errBuf.String())
	}
}
