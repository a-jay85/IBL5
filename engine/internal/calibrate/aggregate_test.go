package calibrate

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

func statRow(stat string, sco, mean float64, pass bool) validate.StatRow {
	return validate.StatRow{Stat: stat, ScoVal: sco, EngineMean: mean, Pass: pass}
}

func reportWith(gt bundle.GameType, rows ...validate.StatRow) validate.Report {
	return validate.Report{GameType: gt, Games: []validate.GameReport{{Rows: rows}}}
}

func findStatCal(t *testing.T, rep CalibrationReport, gt bundle.GameType, stat string) StatCalibration {
	t.Helper()
	for _, b := range rep.Buckets {
		if b.GameType != int(gt) {
			continue
		}
		for _, s := range b.Stats {
			if s.Stat == stat {
				return s
			}
		}
	}
	t.Fatalf("no calibration for game_type=%d stat=%q", int(gt), stat)
	return StatCalibration{}
}

// Row #10: Calibrate derives AbsFloor=ceil(p95(|d|)) and RelPct=p95(|d|/mean)
// per (game type, stat) and reports the resulting in-band rate. With residuals
// 0..19 against mean 100, p95 (nearest-rank over 20) = 18 → floor 18, rel 0.18,
// and 19/20 observations land in band.
func TestCalibrate_DerivesBandsAndInBandRate(t *testing.T) {
	var rows []validate.StatRow
	for d := 0; d < 20; d++ {
		rows = append(rows, statRow("points", 100+float64(d), 100, true))
	}
	rep := Calibrate([]validate.Report{reportWith(bundle.GameTypeRegular, rows...)}, 0.95)

	sc := findStatCal(t, rep, bundle.GameTypeRegular, "points")
	if sc.N != 20 {
		t.Errorf("N = %d, want 20", sc.N)
	}
	if sc.ProposedAbsFloor != 18 {
		t.Errorf("AbsFloor = %v, want 18", sc.ProposedAbsFloor)
	}
	if math.Abs(sc.ProposedRelPct-0.18) > 1e-9 {
		t.Errorf("RelPct = %v, want ~0.18", sc.ProposedRelPct)
	}
	if math.Abs(sc.P95AbsResid-18) > 1e-9 {
		t.Errorf("P95AbsResid = %v, want 18", sc.P95AbsResid)
	}
	if math.Abs(sc.InBandRate-0.95) > 1e-9 {
		t.Errorf("InBandRate = %v, want 0.95", sc.InBandRate)
	}
}

// Row #11 (boundary): a degenerate single-observation, zero-residual bucket
// yields AbsFloor >= 1 — never a zero-width band that would reject any nonzero
// difference.
func TestCalibrate_DegenerateBucketNeverZeroWidth(t *testing.T) {
	rep := Calibrate([]validate.Report{
		reportWith(bundle.GameTypeRegular, statRow("reb", 10, 10, true)),
	}, 0.95)
	sc := findStatCal(t, rep, bundle.GameTypeRegular, "reb")
	if sc.N != 1 {
		t.Fatalf("N = %d, want 1", sc.N)
	}
	if sc.ProposedAbsFloor < 1 {
		t.Errorf("AbsFloor = %v, want >= 1 (no zero-width band)", sc.ProposedAbsFloor)
	}
}

// Row #12: Calibrate segments residuals by game type — a large playoff residual
// must not widen the regular-season band, and vice versa.
func TestCalibrate_SegmentsByGameType(t *testing.T) {
	reg := reportWith(bundle.GameTypeRegular,
		statRow("points", 50, 50, true), // residual 0
		statRow("points", 50, 50, true),
	)
	playoff := reportWith(bundle.GameTypePlayoff,
		statRow("points", 80, 50, false), // residual 30
		statRow("points", 80, 50, false),
	)
	rep := Calibrate([]validate.Report{reg, playoff}, 0.95)

	regCal := findStatCal(t, rep, bundle.GameTypeRegular, "points")
	playCal := findStatCal(t, rep, bundle.GameTypePlayoff, "points")
	if regCal.ProposedAbsFloor != 1 {
		t.Errorf("regular AbsFloor = %v, want 1 (clamped; not polluted by playoff)", regCal.ProposedAbsFloor)
	}
	if playCal.ProposedAbsFloor != 30 {
		t.Errorf("playoff AbsFloor = %v, want 30 (its own residual)", playCal.ProposedAbsFloor)
	}
}

// Gate: a bucket below min-rate flips the overall verdict to fail; a bucket at
// or above passes.
func TestGate_FailsBelowMinRate(t *testing.T) {
	var rows []validate.StatRow
	for i := 0; i < 10; i++ {
		rows = append(rows, statRow("points", 0, 0, i < 8)) // 8/10 pass -> rate 0.8
		rows = append(rows, statRow("reb", 0, 0, true))     // 10/10 pass -> rate 1.0
	}
	res := Gate([]validate.Report{reportWith(bundle.GameTypeRegular, rows...)}, 0.90)
	if res.Pass {
		t.Fatal("gate should FAIL: points rate 0.8 < min-rate 0.9")
	}
	var sawPointsFail, sawRebPass bool
	for _, b := range res.Buckets {
		for _, s := range b.Stats {
			switch s.Stat {
			case "points":
				if !s.Pass && math.Abs(s.Rate-0.8) < 1e-9 {
					sawPointsFail = true
				}
			case "reb":
				if s.Pass && math.Abs(s.Rate-1.0) < 1e-9 {
					sawRebPass = true
				}
			}
		}
	}
	if !sawPointsFail || !sawRebPass {
		t.Errorf("expected points FAIL (rate 0.8) and reb PASS (rate 1.0); got buckets %+v", res.Buckets)
	}
}

func TestGate_PassesAtOrAboveMinRate(t *testing.T) {
	rows := []validate.StatRow{
		statRow("points", 0, 0, true),
		statRow("reb", 0, 0, true),
	}
	res := Gate([]validate.Report{reportWith(bundle.GameTypeRegular, rows...)}, 0.90)
	if !res.Pass {
		t.Errorf("gate should PASS when every stat is 100%% in band: %+v", res.Buckets)
	}
}
