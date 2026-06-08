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

// stubCollectors returns canned reports/skips from both selection strategies,
// so the CLI's flag/mode/encoding paths are exercised without walking a real
// archive.
func stubCollectors(reports []validate.Report, skips []calibrate.Skip) collectors {
	fn := func(string, calibrate.Options) ([]validate.Report, []calibrate.Skip, error) {
		return reports, skips, nil
	}
	return collectors{season: fn, flat: fn}
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
	code := runWith([]string{"--archive", "/x", "--mode", "calibrate"}, &out, &errBuf, stubCollectors(reports, nil))
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

// calibrate mode also emits the per-game-type home_margins readout (the HCA
// signal) alongside buckets, from the same run. Home pts Engine=108/Sco=110
// (TeamID 7), visitor Engine=100/Sco=100 (TeamID 3) → engine margin 8, sco
// margin 10, gap −2.
func TestRun_CalibrateEmitsHomeMargins(t *testing.T) {
	reports := []validate.Report{{
		GameType: bundle.GameTypeRegular,
		Games: []validate.GameReport{{
			HomeTeamID:    7,
			VisitorTeamID: 3,
			Rows: []validate.StatRow{
				{TeamID: 3, Stat: "points", ScoVal: 100, EngineMean: 100},
				{TeamID: 7, Stat: "points", ScoVal: 110, EngineMean: 108},
			},
		}},
	}}
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--mode", "calibrate"}, &out, &errBuf, stubCollectors(reports, nil))
	if code != 0 {
		t.Fatalf("exit = %d, want 0 (stderr: %s)", code, errBuf.String())
	}
	var rep calibrate.CalibrationReport
	if err := json.Unmarshal(out.Bytes(), &rep); err != nil {
		t.Fatalf("stdout is not a CalibrationReport JSON: %v\n%s", err, out.String())
	}
	if len(rep.HomeMargins) != 1 {
		t.Fatalf("home_margins len = %d, want 1: %+v", len(rep.HomeMargins), rep.HomeMargins)
	}
	hm := rep.HomeMargins[0]
	if hm.GameType != int(bundle.GameTypeRegular) {
		t.Errorf("home_margins GameType = %d, want %d", hm.GameType, int(bundle.GameTypeRegular))
	}
	if hm.MarginGap != -2 {
		t.Errorf("MarginGap = %v, want -2", hm.MarginGap)
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
	code := runWith([]string{"--archive", "/x", "--mode", "gate", "--min-rate", "0.9"}, &out, &errBuf, stubCollectors(reports, nil))
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
	code := runWith([]string{"--archive", "/x", "--mode", "gate"}, &out, &errBuf, stubCollectors(reports, nil))
	if code != 0 {
		t.Fatalf("exit = %d, want 0 (stderr: %s)", code, errBuf.String())
	}
}

// Row #15 (negative): a missing --archive is a usage error (exit 2).
func TestRun_MissingArchive(t *testing.T) {
	var out, errBuf bytes.Buffer
	if code := runWith(nil, &out, &errBuf, stubCollectors(nil, nil)); code != 2 {
		t.Fatalf("exit = %d, want 2", code)
	}
	if !strings.Contains(errBuf.String(), "--archive") {
		t.Errorf("expected a --archive usage message, got: %q", errBuf.String())
	}
}

// Negative: an invalid --mode is a usage error (exit 2).
func TestRun_InvalidMode(t *testing.T) {
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--mode", "bogus"}, &out, &errBuf, stubCollectors(nil, nil))
	if code != 2 {
		t.Fatalf("exit = %d, want 2", code)
	}
	if !strings.Contains(errBuf.String(), "invalid --mode") {
		t.Errorf("expected an invalid --mode message, got: %q", errBuf.String())
	}
}

// Negative: an invalid --selection is a usage error (exit 2).
func TestRun_InvalidSelection(t *testing.T) {
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--selection", "bogus"}, &out, &errBuf, stubCollectors(nil, nil))
	if code != 2 {
		t.Fatalf("exit = %d, want 2", code)
	}
	if !strings.Contains(errBuf.String(), "invalid --selection") {
		t.Errorf("expected an invalid --selection message, got: %q", errBuf.String())
	}
}

// Negative: an archive that yields no reports exits 1 with a diagnostic.
func TestRun_NoReports(t *testing.T) {
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x"}, &out, &errBuf, stubCollectors(nil, nil))
	if code != 1 {
		t.Fatalf("exit = %d, want 1", code)
	}
	if !strings.Contains(errBuf.String(), "no snapshots") {
		t.Errorf("expected a 'no snapshots' message, got: %q", errBuf.String())
	}
}

// Row #13: calibrate mode emits the season_aggregates readout (standings detail
// + residuals) from the same run; gate mode does NOT carry it.
func TestRun_CalibrateEmitsSeasonAggregates(t *testing.T) {
	reports := []validate.Report{{
		Label:    "04-05",
		GameType: bundle.GameTypeRegular,
		Games: []validate.GameReport{{
			HomeTeamID:            7,
			VisitorTeamID:         3,
			EngineHomeWinFraction: 0.6,
			Rows: []validate.StatRow{
				{TeamID: 3, Stat: "points", ScoVal: 99, EngineMean: 100},
				{TeamID: 7, Stat: "points", ScoVal: 108, EngineMean: 105},
			},
		}},
	}}

	var out, errBuf bytes.Buffer
	if code := runWith([]string{"--archive", "/x", "--mode", "calibrate"}, &out, &errBuf, stubCollectors(reports, nil)); code != 0 {
		t.Fatalf("exit = %d, want 0 (stderr: %s)", code, errBuf.String())
	}
	var rep calibrate.CalibrationReport
	if err := json.Unmarshal(out.Bytes(), &rep); err != nil {
		t.Fatalf("stdout is not a CalibrationReport JSON: %v\n%s", err, out.String())
	}
	if len(rep.SeasonAggregates.Seasons) != 1 || rep.SeasonAggregates.Seasons[0].Label != "04-05" {
		t.Fatalf("season_aggregates.seasons = %+v, want one labeled 04-05", rep.SeasonAggregates.Seasons)
	}
	if len(rep.SeasonAggregates.Residuals) != 1 {
		t.Errorf("season_aggregates.residuals = %+v, want one bucket", rep.SeasonAggregates.Residuals)
	}

	// Gate mode encodes a GateResult, which has no season_aggregates field.
	var gout, gerr bytes.Buffer
	_ = runWith([]string{"--archive", "/x", "--mode", "gate", "--min-rate", "0"}, &gout, &gerr, stubCollectors(reports, nil))
	if strings.Contains(gout.String(), "season_aggregates") {
		t.Errorf("gate output should not carry season_aggregates:\n%s", gout.String())
	}
}

// Row #6: calibrate mode emits season_aggregates.fidelity as a per-game-type
// array, deterministically ordered ascending by game type (regular 2 before
// playoff 4), serialized automatically alongside seasons + residuals.
func TestRun_CalibrateEmitsFidelityOrdered(t *testing.T) {
	reports := []validate.Report{
		{Label: "playoffs", GameType: bundle.GameTypePlayoff, Games: []validate.GameReport{{
			HomeTeamID: 3, VisitorTeamID: 1, EngineHomeWinFraction: 0.7,
			Rows: []validate.StatRow{
				{TeamID: 1, Stat: "points", ScoVal: 92, EngineMean: 95},
				{TeamID: 3, Stat: "points", ScoVal: 100, EngineMean: 99},
			},
		}}},
		{Label: "04-05", GameType: bundle.GameTypeRegular, Games: []validate.GameReport{{
			HomeTeamID: 7, VisitorTeamID: 3, EngineHomeWinFraction: 0.6,
			Rows: []validate.StatRow{
				{TeamID: 3, Stat: "points", ScoVal: 99, EngineMean: 100},
				{TeamID: 7, Stat: "points", ScoVal: 108, EngineMean: 105},
			},
		}}},
	}

	var out, errBuf bytes.Buffer
	if code := runWith([]string{"--archive", "/x", "--mode", "calibrate"}, &out, &errBuf, stubCollectors(reports, nil)); code != 0 {
		t.Fatalf("exit = %d, want 0 (stderr: %s)", code, errBuf.String())
	}
	if !strings.Contains(out.String(), `"fidelity"`) {
		t.Fatalf("encoded report missing season_aggregates.fidelity:\n%s", out.String())
	}
	var rep calibrate.CalibrationReport
	if err := json.Unmarshal(out.Bytes(), &rep); err != nil {
		t.Fatalf("stdout is not a CalibrationReport JSON: %v\n%s", err, out.String())
	}
	fid := rep.SeasonAggregates.Fidelity
	if len(fid) != 2 {
		t.Fatalf("fidelity = %+v, want two game-type entries", fid)
	}
	if fid[0].GameType != int(bundle.GameTypeRegular) || fid[1].GameType != int(bundle.GameTypePlayoff) {
		t.Errorf("fidelity not sorted ascending by game type: %+v", fid)
	}
}

// Row #7: calibrate mode emits the volume/efficiency channel-decomposition fields
// (volume_dispersion_ratio, efficiency_dispersion_ratio, the four real_* terms)
// in season_aggregates.fidelity[], end-to-end through the encoder, with "fga"
// rows feeding a non-zero volume spread.
func TestRun_CalibrateEmitsChannelDecompositionFields(t *testing.T) {
	game := func(date string, homeFGA, visFGA float64) validate.GameReport {
		return validate.GameReport{
			HomeTeamID: 7, VisitorTeamID: 3, EngineHomeWinFraction: 0.6, Date: date,
			Rows: []validate.StatRow{
				{TeamID: 3, Stat: "points", ScoVal: 99, EngineMean: 100},
				{TeamID: 7, Stat: "points", ScoVal: 108, EngineMean: 105},
				{TeamID: 3, Stat: "fga", ScoVal: visFGA, EngineMean: visFGA},
				{TeamID: 7, Stat: "fga", ScoVal: homeFGA, EngineMean: homeFGA},
			},
		}
	}
	// Two seasons so the within-season demean has >1 season to pool over.
	reports := []validate.Report{
		{Label: "04-05", GameType: bundle.GameTypeRegular, Games: []validate.GameReport{game("d1", 90, 80)}},
		{Label: "05-06", GameType: bundle.GameTypeRegular, Games: []validate.GameReport{game("d2", 94, 84)}},
	}

	var out, errBuf bytes.Buffer
	if code := runWith([]string{"--archive", "/x", "--mode", "calibrate"}, &out, &errBuf, stubCollectors(reports, nil)); code != 0 {
		t.Fatalf("exit = %d, want 0 (stderr: %s)", code, errBuf.String())
	}
	for _, field := range []string{
		`"volume_dispersion_ratio"`, `"efficiency_dispersion_ratio"`,
		`"real_var_ln_pf"`, `"real_var_ln_fga"`, `"real_var_ln_pps"`, `"real_cov_ln_fga_ln_pps"`,
		`"engine_var_ln_pf"`, `"engine_var_ln_fga"`, `"engine_var_ln_pps"`, `"engine_cov_ln_fga_ln_pps"`,
	} {
		if !strings.Contains(out.String(), field) {
			t.Errorf("encoded fidelity missing %s:\n%s", field, out.String())
		}
	}
	var rep calibrate.CalibrationReport
	if err := json.Unmarshal(out.Bytes(), &rep); err != nil {
		t.Fatalf("stdout is not a CalibrationReport JSON: %v", err)
	}
	if len(rep.SeasonAggregates.Fidelity) == 0 {
		t.Fatal("no fidelity entries emitted")
	}
}

// measureGame builds a points+fga GameReport (the channel-decomp inputs) for the
// terse measure-mode tests.
func measureGame(date string, homeFGA, visFGA float64) validate.GameReport {
	return validate.GameReport{
		HomeTeamID: 7, VisitorTeamID: 3, EngineHomeWinFraction: 0.6, Date: date,
		Rows: []validate.StatRow{
			{TeamID: 3, Stat: "points", ScoVal: 99, EngineMean: 100},
			{TeamID: 7, Stat: "points", ScoVal: 108, EngineMean: 105},
			{TeamID: 3, Stat: "fga", ScoVal: visFGA, EngineMean: visFGA},
			{TeamID: 7, Stat: "fga", ScoVal: homeFGA, EngineMean: homeFGA},
		},
	}
}

// Row #16: measure mode emits the terse ~6-line-per-game-type verdict (Cov sign + the
// three Var ratios + PASS/FAIL) to stdout — NOT a JSON blob — and exits 0/1 on the sign.
func TestRun_MeasureEmitsTerseVerdict(t *testing.T) {
	reports := []validate.Report{
		{Label: "04-05", GameType: bundle.GameTypeRegular, Games: []validate.GameReport{measureGame("d1", 90, 80)}},
		{Label: "05-06", GameType: bundle.GameTypeRegular, Games: []validate.GameReport{measureGame("d2", 94, 84)}},
	}
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--mode", "measure"}, &out, &errBuf, stubCollectors(reports, nil))
	if code != 0 && code != 1 {
		t.Fatalf("exit = %d, want 0 or 1 (stderr: %s)", code, errBuf.String())
	}
	s := out.String()
	if json.Valid(out.Bytes()) {
		t.Errorf("measure stdout should be terse text, not JSON:\n%s", s)
	}
	for _, want := range []string{
		"Cov(lnFGA,lnPPS):", "Var(lnFGA):", "Var(lnPPS):", "Var(lnPF):",
		"Cov-split engine:", "Cov-split real:", "Var(lnPOSS):", "verdict=", "game_type=2",
	} {
		if !strings.Contains(s, want) {
			t.Errorf("terse output missing %q:\n%s", want, s)
		}
	}
	// One game type → nine lines (header + Cov + 3 Var + 2 Cov-split + Var(lnPOSS) +
	// verdict; the ADR-0049 POSS lines added three diagnostic rows).
	if n := strings.Count(strings.TrimSpace(s), "\n") + 1; n != 9 {
		t.Errorf("expected 9 terse lines for one game type, got %d:\n%s", n, s)
	}
}

// Row #15 (ADR-0049, negative-path): the POSS Cov-split lines are DIAGNOSTIC —
// writeMeasureVerdict's PASS verdict keys solely on the Cov(lnFGA,lnPPS) sign and
// is invariant to the possession-count fields. Two summaries identical except for
// wildly different POSS values must return the same PASS and the same verdict text.
func TestWriteMeasureVerdict_PossLinesDoNotChangeVerdict(t *testing.T) {
	base := calibrate.FidelitySummary{
		GameType: 2, N: 10,
		EngineCovLnFGALnPPS: -0.0012, RealCovLnFGALnPPS: 0.0003, // sign mismatch → FAIL
	}
	withPoss := base
	withPoss.EngineCovLnPossLnPPS, withPoss.EngineCovLnShotsPerPossLnPPS = -0.5, +0.4988
	withPoss.RealCovLnPossLnPPS, withPoss.RealCovLnShotsPerPossLnPPS = +0.9, -0.8997
	withPoss.EngineVarLnPoss, withPoss.RealVarLnPoss, withPoss.PossDispersionRatio = 7, 2, 3.5

	var a, b bytes.Buffer
	passA := writeMeasureVerdict(&a, []calibrate.FidelitySummary{base})
	passB := writeMeasureVerdict(&b, []calibrate.FidelitySummary{withPoss})
	if passA != passB {
		t.Errorf("PASS changed by POSS fields: base=%v withPoss=%v", passA, passB)
	}
	if passA { // Cov signs differ → must be FAIL regardless of POSS
		t.Errorf("expected FAIL on mismatched Cov sign, got PASS")
	}
	if !strings.Contains(a.String(), "verdict=FAIL") || !strings.Contains(b.String(), "verdict=FAIL") {
		t.Errorf("verdict text changed by POSS fields:\nbase:\n%s\nwithPoss:\n%s", a.String(), b.String())
	}
}

// Row #16 (negative-path): measure mode on a report whose only game is a home==visitor
// collision (no contributing rows ⇒ no fidelity) prints a one-line error and exits 1.
func TestRun_MeasureNoFidelityErrors(t *testing.T) {
	reports := []validate.Report{{
		Label: "04-05", GameType: bundle.GameTypeRegular,
		Games: []validate.GameReport{{HomeTeamID: 5, VisitorTeamID: 5, Rows: []validate.StatRow{
			{TeamID: 5, Stat: "points", ScoVal: 100, EngineMean: 100},
		}}},
	}}
	var out, errBuf bytes.Buffer
	code := runWith([]string{"--archive", "/x", "--mode", "measure"}, &out, &errBuf, stubCollectors(reports, nil))
	if code != 1 {
		t.Fatalf("exit = %d, want 1", code)
	}
	if !strings.Contains(errBuf.String(), "no fidelity summary") {
		t.Errorf("expected a one-line 'no fidelity summary' error, got: %s", errBuf.String())
	}
}
