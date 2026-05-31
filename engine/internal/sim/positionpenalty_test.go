package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// --- matrix #9: exact worked examples (base = 1.25 at 24 minutes) ----------

func TestPositionPenalty_WorkedExamples(t *testing.T) {
	cases := []struct {
		name       string
		slot       int
		oo, do, po int
		want       float64
	}{
		{"Steph-style PG", slotPG, 9, 5, 1, 4.3833},
		{"avg PG matching exp", slotPG, 1, 5, 5, -0.0227},
		{"anti-PG (post-up)", slotPG, 1, 5, 9, 2.3833},
		{"Center OO1/DO2/PO9", slotC, 1, 2, 9, 0.6042},
	}
	for _, c := range cases {
		p := oc(c.slot, bundle.Player{OO: c.oo, DriveOff: c.do, PO: c.po, DCMinutes: 24})
		got := positionPenalty(p)
		if math.Abs(got-c.want) > 0.01 {
			t.Errorf("%s: penalty = %.4f, want ≈ %.4f", c.name, got, c.want)
		}
	}
}

// --- matrix #10: boundary — base at minutes 0 & 48, floor at 1 -------------

func TestPenaltyBase_Bounds(t *testing.T) {
	if got := penaltyBase(0); got != 1.0 {
		t.Errorf("base(0) = %v, want 1.0", got)
	}
	if got := penaltyBase(48); got != 1.5 {
		t.Errorf("base(48) = %v, want 1.5", got)
	}
	if got := penaltyBase(-5); got != 1.0 {
		t.Errorf("base(neg) = %v, want 1.0 (clamped)", got)
	}
}

func TestPositionPenalty_FloorAt1(t *testing.T) {
	// Zero ratings are floored to 1 before the penalty math (no NaN/zero-div).
	p := oc(slotPG, bundle.Player{OO: 0, DriveOff: 0, PO: 0, DCMinutes: 24})
	got := positionPenalty(p)
	if math.IsNaN(got) || math.IsInf(got, 0) {
		t.Fatalf("penalty = %v, want finite", got)
	}
}
