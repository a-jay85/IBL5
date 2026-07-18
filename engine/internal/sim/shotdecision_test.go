package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #15: shot_value assembly + clutch + fatigue --------------------

func TestShotValue_Assembly(t *testing.T) {
	// 2pt normal (fallback path: D60==0 → base2ptFallback): base + net×500/baseline
	want2 := 50*fgpToPermille + 2.0*netToShotValue/leagueBaselineFallback
	if got := shotValue2pt(2.0, onCourt{Player: bundle.Player{FGP: 50}}, 0, false, leagueBaselineFallback, 0, 0); math.Abs(got-want2) > 1e-9 {
		t.Errorf("2pt = %v, want %v", got, want2)
	}
	// 2pt shot clock (fallback path: D60==0 → base2ptFallback): FGP × 9.4 × 1.3333 (ignores net)
	wantSC := 50 * fgpToPermille * shotClock2ptMult
	if got := shotValue2pt(999.0, onCourt{Player: bundle.Player{FGP: 50}}, 0, true, leagueBaselineFallback, 0, 0); math.Abs(got-wantSC) > 1e-6 {
		t.Errorf("2pt shot-clock = %v, want %v", got, wantSC)
	}
	// 3pt: float64(d80) + net×500/(baseline×1.5) + blockMod (with leagueBlk48=defBlkSum=0)
	want3 := float64(375) + 2.0*netToShotValue/(leagueBaselineFallback*1.5)
	if got := shotValue3pt(2.0, 375, leagueBaselineFallback, 0, 0); math.Abs(got-want3) > 1e-9 {
		t.Errorf("3pt = %v, want %v", got, want3)
	}
	// FT: FTP × 10
	if got := shotValueFT(75); math.Abs(got-750) > 1e-9 {
		t.Errorf("FT = %v, want 750", got)
	}
	// Clutch multiplier: rating 5 → 1.03.
	if got := clutchMultiplier(5); math.Abs(got-1.03) > 1e-9 {
		t.Errorf("clutchMult(5) = %v, want 1.03", got)
	}
}

// --- ADR-0045 matrix #6: 2pt make-value calibration ------------------------

// The 2pt make-value is calibrated (fgpToPermille=10.7, leagueBaseline=248) so an
// average shooter's neutral-net value lands near 498‰ (≈49.8% 2pt, the .sco level),
// while the 3pt channel — independent of net and of fgpToPermille — is untouched.
func TestShotValue2pt_Calibration(t *testing.T) {
	// Formula: base2ptFallback = FGP × fgpToPermille (per-mille).
	if got := base2ptFallback(50); got != 50*fgpToPermille {
		t.Errorf("base2ptFallback(50) = %v, want %v", got, 50*fgpToPermille)
	}
	// The archive proves the in-game 2pt% lands ≈50% (matrix #9). Here we assert the
	// make-value for an average shooter (FGP 47, D60==0 → base2ptFallback fallback) with a small positive net
	// sits in a realistic ~44–56% band — neither degenerate-low (the pre-0045 underfit) nor
	// absurd-high.
	if v := shotValue2pt(5, onCourt{Player: bundle.Player{FGP: 47}}, 0, false, leagueBaselineFallback, 0, 0); v < 440 || v > 560 {
		t.Errorf("avg-FGP 2pt make-value = %.1f‰, want a realistic ~44–56%% band", v)
	}
	// Boundary FGP=0 → base2ptFallback 0; a neutral-net value stays ≥ 0 (no underflow).
	if got := base2ptFallback(0); got != 0 {
		t.Errorf("base2ptFallback(0) = %v, want 0", got)
	}
	if v := shotValue2pt(0, onCourt{}, 0, false, leagueBaselineFallback, 0, 0); v < 0 {
		t.Errorf("shotValue2pt(0,0) = %v, want ≥ 0 (no underflow)", v)
	}
	// The 3pt make-value does not depend on fgpToPermille (the 2pt recalibration knob).
	// With d80=375 (= leagueBaselineFallback×1.5), net=0, leagueBlk48=defBlkSum=0:
	// shotValue3pt = float64(d80) + 0 + 0 = 375 = leagueBaselineFallback×1.5.
	if got := shotValue3pt(0, 375, leagueBaselineFallback, 0, 0); got != leagueBaselineFallback*1.5 {
		t.Errorf("3pt value = %v, want %v (independent of fgpToPermille)", got, leagueBaselineFallback*1.5)
	}
}

// --- matrix #16: boundaries — 3pt uses net + d80, rand bounds, clutch gate ---

func TestShotValue3pt_UsesNetAndD80(t *testing.T) {
	// 3pt base is float64(d80); d80=0 → base 0; d80=400 → base 400.
	if got := shotValue3pt(0.0, 0, leagueBaselineFallback, 0, 0); got != 0.0 {
		t.Errorf("3pt with d80=0, net=0 = %v, want 0", got)
	}
	if got := shotValue3pt(0.0, 400, leagueBaselineFallback, 0, 0); got != 400.0 {
		t.Errorf("3pt with d80=400, net=0 = %v, want 400", got)
	}
	// Net advantage moves the value: a positive net adds a positive term.
	netTerm := 2.0 * netToShotValue / (leagueBaselineFallback * 1.5)
	got := shotValue3pt(2.0, 0, leagueBaselineFallback, 0, 0)
	if math.Abs(got-netTerm) > 1e-9 {
		t.Errorf("3pt net term = %v, want %v", got, netTerm)
	}
}

// TestShotBaselineOrFallback pins the guard gameloop.go's gs.shotBaseline =
// b.LeagueShotBaseline depends on: bundle.Bundle.LeagueShotBaseline is now
// assembled at bundle-build time (backup.ToBundle's computeLeagueShotBaseline),
// so a bundle whose builder never wired it (or whose raw .plr population had
// no qualifying records) leaves gameState.shotBaseline at the Go zero value —
// this must degrade to leagueBaselineFallback, never a zero divisor for
// shotValue2pt/3pt.
func TestShotBaselineOrFallback(t *testing.T) {
	var zero gameState
	if got := zero.shotBaselineOrFallback(); got != leagueBaselineFallback {
		t.Errorf("zero-value gameState.shotBaselineOrFallback() = %v, want fallback %v", got, leagueBaselineFallback)
	}
	wired := gameState{shotBaseline: 19.7805}
	if got := wired.shotBaselineOrFallback(); got != 19.7805 {
		t.Errorf("wired gameState.shotBaselineOrFallback() = %v, want 19.7805 (pass-through)", got)
	}
}

func TestRollMake_Bounds(t *testing.T) {
	r := rng.New(1)
	// effective = 1000 ≥ rand_int(1,1000) for every roll → always made.
	for i := 0; i < 2000; i++ {
		if !rollMake(1000, 1.0, r) {
			t.Fatal("shot_value 1000 should always make (roll ≤ 1000)")
		}
	}
	// effective = 0.5 < 1 ≤ rand → never made.
	for i := 0; i < 2000; i++ {
		if rollMake(0.5, 1.0, r) {
			t.Fatal("shot_value 0.5 should never make")
		}
	}
}

func TestApplyClutch_Gate(t *testing.T) {
	const sv = 100.0
	// In the window (Q4+, |diff| < 6): scaled by 1.03.
	if got := applyClutch(sv, 5, 4, 0); math.Abs(got-103) > 1e-9 {
		t.Errorf("Q4 diff 0 = %v, want 103", got)
	}
	if got := applyClutch(sv, 5, 5, 5); math.Abs(got-103) > 1e-9 {
		t.Errorf("OT diff 5 = %v, want 103", got)
	}
	// Outside the window: unchanged.
	if got := applyClutch(sv, 5, 3, 0); got != sv {
		t.Errorf("pre-Q4 = %v, want %v (no clutch)", got, sv)
	}
	if got := applyClutch(sv, 5, 4, 6); got != sv {
		t.Errorf("diff 6 = %v, want %v (not < 6)", got, sv)
	}
}
