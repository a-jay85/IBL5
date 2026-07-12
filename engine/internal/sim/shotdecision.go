package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// Make-value calibration constants. The decompile assembles shot_value on a
// per-mille scale (the make roll compares it to rand_int(1,1000)) using
// league_baseline (CEngine+0x6638) and per-player per-game bases (player[+0xD64]
// 2pt, +0xD68 FT).
//
// league_baseline's true identity is NOT a shooting percentage — it is the
// league's 2-point attempts per 48 player-minutes: bucket 0 (ALL positions) of
// the 6-double-per-stat table FUN_004385f0 writes at CEngine+0x6638, computed
// as (Σ2PA / ΣMIN) × 48 over qualifying players (ratio of accumulated sums, not
// a mean of per-player rates — see the write loop, jsb560_decompiled.c
// :27124-27175, e.g. pdVar11[0xc] = (Σ2PA/ΣMIN)×_DAT_00669ed0 at :27131).
// Qualifying players are .plr records 1-959 with MIN > 2×GP. Consumption sites
// confirm the identity byte-for-byte: 2pt normal shot_value divides by
// *(double*)(param_1+0x6638) at :93886-93887, and 3pt shot_value multiplies it
// by 1.5 at :94025 — exactly the shotValue3pt() = leagueBaseline×1.5 form
// below. (Prior comment here mislabeled it "the historical league-wide 2P%";
// corrected 2026-07-12 per jsb-native/00_MASTER_REFERENCE.md and the J5
// RE artifact's offset row-map — both pin +0x6638 as the 2PA/48 row, and the
// true 2P%/FG% table lives at a different offset, +0x6698.)
//
// The baseline is now assembled ONCE per snapshot at bundle-build time
// (backup.ToBundle's computeLeagueShotBaseline, over raw .plr records 1-959 —
// NOT the bundle's player list, a different and larger population) and
// carried on bundle.Bundle.LeagueShotBaseline; gameloop.go copies it onto
// gameState.shotBaseline, read here via shotBaselineOrFallback (state.go).
// leagueBaselineFallback is the documented fallback when a snapshot has no
// qualifying records (e.g. a synthetic test bundle that never wired
// LeagueShotBaseline): it is the prior .sco-calibrated stand-in (ADR-0045):
// 2pt% ≈ 49.8% and 3pt% ≈ 37.2% (JSB 3pt make = baseline×1.5, so fallback =
// sco 3pt% × 666.7 ≈ 248) — a different scale than the true ~19.78 (IBL5.plr)
// 2PA/48 volume-rate value, since it was fit to reproduce output shooting-%,
// not to be a faithful port of the raw attempt-rate. fgpToPermille shares
// that calibration.
const (
	leagueBaselineFallback = 250.0 // per-mille stand-in; 3pt make = fallback×1.5 = 375‰ (~37.5%, sco-implied)
	fgpToPermille          = 9.4   // base 2pt make = FGP × this (calibrated to 2pt% ≈ 50%)
	ftpToPermille          = 10.0  // FT make = FTP × this  (FTP 75 → 750‰)

	netToShotValue   = 500.0 // _DAT_00669ef0 (0.5) × _DAT_0066ac40 (1000.0)
	shotClock2ptMult = 1.3333333333
	clutchScale      = 0.01
	clutchOffset     = 0.98
)

// base2pt is the player's baseline 2pt make value in per-mille (player[+0xD64]
// stand-in), derived from the FGP rating.
func base2pt(fgp int) float64 { return float64(fgp) * fgpToPermille }

// shotValue2pt assembles the 2-point make value. Normal:
// net × 500 / baseline + base_2pt. Shot clock: base_2pt × 1.3333 (the corrected
// per-player +0xD60 form — a rushed look ignores the matchup net advantage).
// baseline is the per-snapshot league 2PA/48 (gameState.shotBaseline).
func shotValue2pt(net float64, fgp int, shotClock bool, baseline float64) float64 {
	if shotClock {
		return base2pt(fgp) * shotClock2ptMult
	}
	return net*netToShotValue/baseline + base2pt(fgp)
}

// putbackValue2pt is the JSB 5.60 putback (OReb-continuation) 2pt make-value:
// player[+0xD60] × 1.3333 — net-advantage-free and 4/3-boosted (decompile
// jsb560_decompiled.c:93880-93883). base2pt(fgp) is the engine's +0xD60 2P%-rating
// stand-in; shotClock2ptMult is the 4/3 boost. This is the SAME assembled form as
// the shotValue2pt shotClock==true branch, named for its distinct concept (a
// putback, not a rushed shot-clock look) so the call site reads its intent.
func putbackValue2pt(fgp int) float64 {
	return base2pt(fgp) * shotClock2ptMult
}

// shotValue3pt is league-baseline × 1.5 (decompile :94025, ×_DAT_0066d388) —
// deliberately independent of net advantage. ODPT ratings decide whether a 3pt
// is *attempted*, but once attempted, make probability is identical for all
// players. baseline is the per-snapshot league 2PA/48 (gameState.shotBaseline).
func shotValue3pt(baseline float64) float64 { return baseline * 1.5 }

// shotValueFT is the per-attempt free-throw value: the FTP rating, per-mille.
func shotValueFT(ftp int) float64 { return float64(ftp) * ftpToPermille }

func absInt(n int) int {
	if n < 0 {
		return -n
	}
	return n
}

// clutchMultiplier is clutch_rating × 0.01 + 0.98 (range 0.98–1.03).
func clutchMultiplier(clutch int) float64 {
	return float64(clutch)*clutchScale + clutchOffset
}

// applyClutch scales a 2pt shot_value by the clutch multiplier, but only in the
// clutch window: period 4 or later AND score differential within 5 points.
// The 3pt path applies no clutch (its value stays league-baseline×1.5).
func applyClutch(shotValue float64, clutch, period, scoreDiff int) float64 {
	if period >= 4 && absInt(scoreDiff) < 6 {
		return clutchMultiplier(clutch) * shotValue
	}
	return shotValue
}

// rollMake performs the final make/miss roll: effective = shot_value × fatigue;
// made if effective ≥ rand_int(1,1000). FG make uses base-stamina fatigue
// (≈1.0 in PR3a); free throws pass their own energy-based fatigue.
func rollMake(shotValue, fatigue float64, r *rng.RNG) bool {
	effective := shotValue * fatigue
	roll := r.IntN(1000) + 1 // 1..1000
	return effective >= float64(roll)
}
