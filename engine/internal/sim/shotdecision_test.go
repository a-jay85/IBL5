package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #15: shot_value assembly + clutch + fatigue --------------------

func TestShotValue_Assembly(t *testing.T) {
	// 2pt normal: net × 500 / baseline + FGP × 9
	want2 := 2.0*netToShotValue/leagueBaseline + 50*fgpToPermille
	if got := shotValue2pt(2.0, 50, false); math.Abs(got-want2) > 1e-9 {
		t.Errorf("2pt = %v, want %v", got, want2)
	}
	// 2pt shot clock: FGP × 9 × 1.3333 (ignores net)
	wantSC := 50 * fgpToPermille * shotClock2ptMult
	if got := shotValue2pt(999.0, 50, true); math.Abs(got-wantSC) > 1e-6 {
		t.Errorf("2pt shot-clock = %v, want %v", got, wantSC)
	}
	// 3pt: baseline × 1.5
	if got := shotValue3pt(); math.Abs(got-leagueBaseline*1.5) > 1e-9 {
		t.Errorf("3pt = %v, want %v", got, leagueBaseline*1.5)
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

// --- matrix #16: boundaries — 3pt ignores net, rand bounds, clutch gate ----

func TestShotValue3pt_IgnoresNet(t *testing.T) {
	// There is no net parameter; the value is constant regardless of matchup.
	if shotValue3pt() != leagueBaseline*1.5 {
		t.Error("3pt shot value must be league-baseline×1.5, independent of net advantage")
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
