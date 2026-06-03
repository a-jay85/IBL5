package calibrate

import (
	"math"
	"testing"
)

// TestDecomposeByOrigin checks the exact additive identity: the three per-origin
// covariance contributions sum to the total within-season FGA variance.
func TestDecomposeByOrigin(t *testing.T) {
	rows := []originRow{
		{season: "A", fgaInitial: 70, fgaOreb: 12, fgaT: 8},
		{season: "A", fgaInitial: 60, fgaOreb: 20, fgaT: 6},
		{season: "A", fgaInitial: 80, fgaOreb: 8, fgaT: 10},
		{season: "B", fgaInitial: 65, fgaOreb: 18, fgaT: 12},
		{season: "B", fgaInitial: 75, fgaOreb: 10, fgaT: 5},
	}
	varTotal, ci, co, ct := decomposeByOrigin(rows)
	if got := ci + co + ct; math.Abs(got-varTotal) > 1e-9 {
		t.Fatalf("contributions %.6f+%.6f+%.6f=%.6f must sum to varTotal %.6f", ci, co, ct, got, varTotal)
	}
	if varTotal <= 0 {
		t.Fatalf("varTotal must be positive on a dispersed fixture, got %.6f", varTotal)
	}
}

// TestDecomposeByOrigin_Attribution: when only ONE origin varies across teams
// (the others are constant within season), that origin carries ~all the variance
// and the others ~0 — the property Phase-6 calibration reads to pick the dominant
// empty-FGA source.
func TestDecomposeByOrigin_Attribution(t *testing.T) {
	rows := []originRow{
		{season: "A", fgaInitial: 70, fgaOreb: 5, fgaT: 8},
		{season: "A", fgaInitial: 70, fgaOreb: 15, fgaT: 8},
		{season: "A", fgaInitial: 70, fgaOreb: 25, fgaT: 8},
	}
	varTotal, ci, co, ct := decomposeByOrigin(rows)
	if math.Abs(ci) > 1e-9 || math.Abs(ct) > 1e-9 {
		t.Fatalf("constant origins must contribute ~0: initial=%.6f transition=%.6f", ci, ct)
	}
	if math.Abs(co-varTotal) > 1e-9 {
		t.Fatalf("the only varying origin (oreb) must carry all variance: oreb=%.6f varTotal=%.6f", co, varTotal)
	}
}

// TestDecomposeByOrigin_Degenerate: empty, single-team, and all-zero-FGA inputs
// yield zeros, never NaN/Inf.
func TestDecomposeByOrigin_Degenerate(t *testing.T) {
	t.Run("empty", func(t *testing.T) {
		v, a, b, c := decomposeByOrigin(nil)
		if v != 0 || a != 0 || b != 0 || c != 0 {
			t.Fatalf("empty input must be all zeros, got %v %v %v %v", v, a, b, c)
		}
	})
	t.Run("single-team season yields zero variance", func(t *testing.T) {
		v, a, b, c := decomposeByOrigin([]originRow{{season: "A", fgaInitial: 70, fgaOreb: 12, fgaT: 8}})
		for _, x := range []float64{v, a, b, c} {
			if x != 0 || math.IsNaN(x) {
				t.Fatalf("single-team season must yield 0 (no NaN), got %v", x)
			}
		}
	})
	t.Run("all-zero FGA yields zero, no NaN", func(t *testing.T) {
		v, a, b, c := decomposeByOrigin([]originRow{{season: "A"}, {season: "A"}, {season: "A"}})
		for _, x := range []float64{v, a, b, c} {
			if x != 0 || math.IsNaN(x) || math.IsInf(x, 0) {
				t.Fatalf("all-zero FGA must yield 0 finite, got %v", x)
			}
		}
	})
}
