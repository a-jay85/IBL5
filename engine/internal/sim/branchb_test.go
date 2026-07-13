package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// expectedUsage mirrors branchBShrink's usage target so the tests pin the literal
// JSB formula, not branchBShrink's own arithmetic.
func expectedUsage(transOff int, drbRate, astRate float64) float64 {
	return float64(transOff) * (drbRate + astRate) * branchBTeamScale * branchBPlayerScale
}

// #10 — branchB arithmetic: usage = TransOff×(drb+ast)×0.2×0.04;
// s = (ΣD−usage)/ΣD over ΣD = 2pt+3pt+foul (and-one and TO excluded); each mapped
// bucket ×= s. Pinned against the literal constants, not a recomputation.
func TestBranchBShrink_Arithmetic(t *testing.T) {
	acc := &BranchBAccum{}
	gs := &gameState{branchB: acc}
	raw2pt, raw3pt, rawFoul := 20.0, 5.0, 0.6
	drbRate, astRate, transOff := 150.0, 90.0, 7

	usage := expectedUsage(transOff, drbRate, astRate)
	if math.Abs(usage-7*240*0.008) > 1e-9 {
		t.Fatalf("usage = %v, want %v", usage, 7*240*0.008)
	}
	sigmaD := raw2pt + raw3pt + rawFoul
	wantS := (sigmaD - usage) / sigmaD

	s2, s3, sf := gs.branchBShrink(raw2pt, raw3pt, rawFoul, drbRate, astRate, transOff)
	if d := math.Abs(s2 - raw2pt*wantS); d > 1e-9 {
		t.Errorf("2pt: got %v want %v", s2, raw2pt*wantS)
	}
	if d := math.Abs(s3 - raw3pt*wantS); d > 1e-9 {
		t.Errorf("3pt: got %v want %v", s3, raw3pt*wantS)
	}
	if d := math.Abs(sf - rawFoul*wantS); d > 1e-9 {
		t.Errorf("foul: got %v want %v", sf, rawFoul*wantS)
	}
	if acc.Taken != 1 || acc.Fallback != 0 {
		t.Errorf("engagement: taken=%d fallback=%d, want 1/0", acc.Taken, acc.Fallback)
	}
	if math.Abs(acc.MeanS()-wantS) > 1e-9 || acc.MinS != acc.MaxS {
		t.Errorf("s distribution: mean=%v min=%v max=%v, want single value %v", acc.MeanS(), acc.MinS, acc.MaxS, wantS)
	}
}

// #11 — proportional shrink: all three live buckets are scaled by the SAME s, so
// their pairwise ratios are preserved (NOT 2pt-only). Uses three distinct nonzero
// composites so a 2pt-only shrink would visibly skew the ratios.
func TestBranchBShrink_Proportional(t *testing.T) {
	gs := &gameState{}
	raw2pt, raw3pt, rawFoul := 22.0, 6.0, 0.8
	s2, s3, sf := gs.branchBShrink(raw2pt, raw3pt, rawFoul, 120.0, 80.0, 5)

	// ratios preserved ⇔ s2/raw2pt == s3/raw3pt == sf/rawFoul.
	r2, r3, rf := s2/raw2pt, s3/raw3pt, sf/rawFoul
	if math.Abs(r2-r3) > 1e-9 || math.Abs(r2-rf) > 1e-9 {
		t.Errorf("ratios not equal: 2pt=%v 3pt=%v foul=%v (expected the same s)", r2, r3, rf)
	}
	if r2 <= 0 || r2 >= 1 {
		t.Errorf("s = %v, expected a proper shrink in (0,1) for this config", r2)
	}
}

// #13 — Branch-A cold-start (literal gate): the buckets are returned UNCHANGED when
// the 2pt composite ≤ 0, when usage ≤ 0 (TransOff 0 or rates 0), or when ΣD ≤ 0; no
// NaN/Inf; the fallback counter increments and no s is recorded.
func TestBranchBShrink_BranchAColdStart(t *testing.T) {
	cases := []struct {
		name                    string
		raw2pt, raw3pt, rawFoul float64
		drbRate, astRate        float64
		transOff                int
	}{
		{"2pt<=0", 0, 5, 0.6, 150, 90, 7},
		{"usage<=0 via rates 0", 20, 5, 0.6, 0, 0, 7},
		{"usage<=0 via transOff 0", 20, 5, 0.6, 150, 90, 0},
		{"sigmaD<=0", -1, 0, 0, 150, 90, 7}, // 2pt<=0 fires first, but assert no shrink/NaN
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			acc := &BranchBAccum{}
			gs := &gameState{branchB: acc}
			s2, s3, sf := gs.branchBShrink(c.raw2pt, c.raw3pt, c.rawFoul, c.drbRate, c.astRate, c.transOff)
			if s2 != c.raw2pt || s3 != c.raw3pt || sf != c.rawFoul {
				t.Errorf("buckets changed: got (%v,%v,%v), want (%v,%v,%v)", s2, s3, sf, c.raw2pt, c.raw3pt, c.rawFoul)
			}
			for _, v := range []float64{s2, s3, sf} {
				if math.IsNaN(v) || math.IsInf(v, 0) {
					t.Errorf("non-finite bucket %v", v)
				}
			}
			if acc.Fallback != 1 || acc.Taken != 0 {
				t.Errorf("engagement: fallback=%d taken=%d, want 1/0", acc.Fallback, acc.Taken)
			}
		})
	}
}

// #14 — over-shrink: when usage > ΣD the factor s is negative and the scaled weights
// go negative; outcomeInputs.weight() clamps each to 0 and the selector still
// normalizes over whatever non-negative mass remains.
func TestBranchBShrink_OverShrinkClampedByWeight(t *testing.T) {
	gs := &gameState{}
	// huge rates + high TransOff drive usage far above ΣD (≈25.6).
	s2, s3, sf := gs.branchBShrink(20, 5, 0.6, 900, 600, 9)
	if s2 >= 0 {
		t.Fatalf("expected over-shrink to drive the 2pt weight negative, got %v", s2)
	}
	in := outcomeInputs{twoPtWeight: s2, threePtWeight: s3, andOneWeight: 0.03, foulOnlyWeight: sf, turnoverDefValue: 0}
	if w := in.weight(outcome2pt); w != 0 {
		t.Errorf("weight() did not clamp negative 2pt to 0: %v", w)
	}
	// selectOutcome must not panic and must return a valid path (and-one survives).
	got := selectOutcome(in, false, false, false, rng.New(1))
	if got != outcomeAndOne {
		t.Errorf("with only and-one positive, expected outcomeAndOne, got %v", got)
	}
}

// #21 — BranchB scales bucket weights by s without corrupting signs. The faithful
// foul bucket has a side-symmetric base plus the small ±0.2 half-court HCA legs
// (superseding the ADR-0082 home-deterministic/away-stochastic stand-in), so `s`
// depends on `hca` through the foul weight and the exact home-minus-away delta is not
// invariant across BranchB ON/OFF — that property is replaced by: (a) the 2pt home
// weight gains +hcaScaled after s-scaling (sign preserved); (b) the home foul weight
// is positive (guard against sign flip from s); (c) BranchB ON produces a lower home
// 2pt weight than BranchB OFF (s < 1 — shrink engaged). BranchB is an OFF-by-default
// diagnostic not exercised by any shipped path, golden snapshot, or SIGN gate.
func TestPlayBuckets_HCADeltaInvariantToBranchB(t *testing.T) {
	bh := oc(slotPG, mkPlayer(1, 3, slotPG, 48))
	offense := &teamState{players: fiveStarters(3), drbRate: 150, astRate: 90, isHome: true}
	defense := &teamState{players: fiveStarters(7)}

	hcaHome := hcaDelta(bundle.GameTypeRegular, true) // +0.2 (raw, foul legs B/C)
	hcaScaled := hcaHome * hcaSite2BasisScale         // scaled site-2 addend (legs A/D)

	// BranchB OFF: home 2pt = raw2pt + hca, home foul = det_home.
	gsOff := &gameState{rng: rng.New(42)}
	offH2, _, offHF := gsOff.playBuckets(bh, offense, defense, hcaHome, hcaScaled, 0, true)

	// BranchB ON: home 2pt = s*raw2pt + hca, home foul = s*det_home (s < 1).
	gsOn := &gameState{rng: rng.New(42)}
	gsOn.freeze.BranchB = true
	onH2, _, onHF := gsOn.playBuckets(bh, offense, defense, hcaHome, hcaScaled, 0, true)

	// (a) BranchB shrinks the home 2pt weight (s < 1 with realistic rates).
	if onH2 >= offH2 {
		t.Errorf("BranchB ON home 2pt %.4f ≥ OFF %.4f — shrink not engaged", onH2, offH2)
	}
	// (c) Home foul weight is positive in both modes.
	if offHF <= 0 || onHF <= 0 {
		t.Errorf("home foul weight non-positive: off=%.4f on=%.4f", offHF, onHF)
	}
	t.Logf("home 2pt: off=%.4f on=%.4f | home foul: off=%.4f on=%.4f", offH2, onH2, offHF, onHF)
}

// #22 (integration) — with realistic team rates wired onto the bundle, Branch-B
// ENGAGES end-to-end (rates → teamState → branchBShrink): the engagement accumulator
// records mostly Taken (near-zero Fallback) and a mean s materially below 1 and in
// (0,1). This is the load-bearing magnitude check at the full-pipeline level — wrong
// units would show either all-Fallback (s≈1) or s≤0.
func TestBranchB_EngagesWithRealisticRates(t *testing.T) {
	b := richBundle()
	for i := range b.Teams {
		b.Teams[i].DRBRate = 150 // ≈ the IBL5.plr-decoded faithful per-48 team rates
		b.Teams[i].ASTRate = 90
	}
	acc := &BranchBAccum{}
	_, err := SimulateWith(b, 4242, Options{Freeze: FreezeConfig{BranchB: true}, BranchBAccum: acc})
	if err != nil {
		t.Fatalf("SimulateWith: %v", err)
	}
	if acc.Taken == 0 {
		t.Fatal("Branch-B never engaged with realistic rates — usage too small (unit mis-pin?)")
	}
	if frac := float64(acc.Fallback) / float64(acc.Taken+acc.Fallback); frac > 0.10 {
		t.Errorf("fallback fraction %.2f too high — Branch-A cold-start dominating unexpectedly", frac)
	}
	if m := acc.MeanS(); m <= 0 || m >= 1 {
		t.Errorf("mean s = %.3f, want a real shrink in (0,1); ≥1 ⇒ no-op, ≤0 ⇒ degenerate over-shrink", m)
	}
	if acc.MinS < -1 {
		t.Errorf("min s = %.3f unexpectedly far negative (mass should mostly shrink, not invert)", acc.MinS)
	}
}

// #13/#15 flavor — Branch-B ON but with ZERO team rates is Branch-A everywhere
// (usage=0 → fallback every possession), so the full-game output is byte-identical to
// Branch-B OFF. This is the "never engaged ⇒ no-op" property the engagement instrument
// detects: high fallback, no taken.
func TestBranchB_ZeroRatesIsInert(t *testing.T) {
	b := richBundle() // its Teams carry no DRB/AST rates → 0
	off := encode(t, Simulate(b, 7777))
	onRes, err := SimulateWith(b, 7777, Options{Freeze: FreezeConfig{BranchB: true}})
	if err != nil {
		t.Fatalf("SimulateWith: %v", err)
	}
	on := encode(t, onRes)
	if string(off) != string(on) {
		t.Error("Branch-B ON with zero team rates changed output (should be inert Branch-A)")
	}
}
