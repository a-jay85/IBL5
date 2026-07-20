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

// --- +0xD58 base-minutes faithful port (J26) -------------------------------
// penaltyBaseMinutes reproduces the binary's player[+0xD58]: the GM's Game-Plan
// minutes target (DCMinutes>0) when set, else MPG = RealLifeMIN/RealLifeGP, else
// 0 (no games, no target → base stays 1.0). See positionpenalty.go docblock and
// re-artifacts/jsb-J26-penalty-minutes-20260720.md §3.
func TestPenaltyBaseMinutes(t *testing.T) {
	cases := []struct {
		name           string
		dcMin, min, gp int
		want           float64
	}{
		{"game-plan target set wins", 30, 2400, 80, 30}, // DCMinutes>0 → dc, ignore MPG
		{"target overrides even when MPG differs", 18, 2400, 80, 18},
		{"no target → MPG fallback", 0, 2400, 80, 30}, // MIN/GP = 2400/80
		{"no target, MPG fractional", 0, 2450, 79, 2450.0 / 79.0},
		{"no target, no games → 0", 0, 0, 0, 0},        // preserves base=1.0
		{"no target, MIN but GP==0 → 0", 0, 500, 0, 0}, // GP guard avoids div-by-zero
	}
	for _, c := range cases {
		p := oc(slotPG, bundle.Player{DCMinutes: c.dcMin, RealLifeMIN: c.min, RealLifeGP: c.gp})
		if got := penaltyBaseMinutes(p); math.Abs(got-c.want) > 1e-9 {
			t.Errorf("%s: penaltyBaseMinutes(dc=%d,min=%d,gp=%d) = %v, want %v",
				c.name, c.dcMin, c.min, c.gp, got, c.want)
		}
	}
}
