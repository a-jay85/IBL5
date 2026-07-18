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
// *(double*)(param_1+0x6638) at :93886-93887; the 3pt path multiplies it by 1.5
// at :94025 to form the net DIVISOR (baseline×1.5), NOT the make value —
// shotValue3pt() = d80 + net×500/(baseline×1.5) + block_term below, so 3pt DOES
// take net advantage, ~1.5× weaker than 2pt (corrected 2026-07-17 per
// jsb-native/re-artifacts/jsb-J24-3pt-consumption-RE-20260717.md §2).
// (Prior comment here mislabeled +0x6638 "the historical league-wide 2P%";
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
// 2pt% ≈ 49.8% and 3pt% ≈ 37.2% (fallback = sco 3pt% × 666.7 ≈ 248, derived
// under the pre-2026-07-17 model where baseline×1.5 was taken as the 3pt make
// value; under the corrected §2 formula baseline×1.5 is the net DIVISOR and d80
// carries the make level, so that derivation no longer holds and 250 is
// unverified against the corrected formula — test-only path, not re-derived
// here) — a different scale than the true ~19.78 (IBL5.plr)
// 2PA/48 volume-rate value, since it was fit to reproduce output shooting-%,
// not to be a faithful port of the raw attempt-rate. fgpToPermille shares
// that calibration.
const (
	leagueBaselineFallback = 250.0 // per-mille stand-in (sco-implied); its ×1.5 is the 3pt net DIVISOR, not the make value (corrected 2026-07-17); 250 unverified vs corrected formula, test-only path
	fgpToPermille          = 9.4   // base 2pt make = FGP × this (calibrated to 2pt% ≈ 50%)
	ftpToPermille          = 10.0  // FT make = FTP × this  (FTP 75 → 750‰)

	netToShotValue   = 500.0 // _DAT_00669ef0 (0.5) × _DAT_0066ac40 (1000.0)
	shotClock2ptMult = 1.3333333333
	clutchScale      = 0.01
	clutchOffset     = 0.98
)

// base2ptFallback returns fgp × fgpToPermille — the FGP-rating stand-in used
// when a player's real-life D64/D60 is zero (no prior-season 2PA data, e.g.
// rookies or DB-built bundles). Mirrors shotBaselineOrFallback: the faithful
// real-life value takes precedence; the rating stand-in is the degraded path.
func base2ptFallback(fgp int) float64 { return float64(fgp) * fgpToPermille }

// blockMod is the per-shot block-modifier: (5×leagueBlk48 − defBlkSum) × 500 / (5×b)
// where b = baseline×1.5 for 3pt or baseline for 2pt. Positive when the defending
// lineup blocks less than league average; negative when they block more. Zero when
// leagueBlk48=0 (unwired bundle: the cap in defBlkSum already forces defBlkSum=0,
// so (0-0)×500/... = 0).
func blockMod(b, leagueBlk48, defBlkSum float64) float64 {
	if b <= 0 {
		return 0
	}
	return (5*leagueBlk48 - defBlkSum) * netToShotValue / (5 * b)
}

// computeD64Base assembles the putback-adjusted 2P‰ base (JSB player[+0xD64]) at
// shot-time from the ball-handler's real-life shot distribution. D90 is the
// twoPtBucketWeight composite (JSB +0xD90 Branch-A cold composite) and D88 is the
// per-48 2PA rate — "D88/D90 already exist in bucketweights" (RE §6.3). The
// plan-architect's annotation "D90=3GA/MIN×48" was wrong; it read threePtBucketWeight
// (line 261) instead of the D90 composite (line 214). Falls back to
// base2ptFallback(fgp) when D60==0 (no real-life 2PA data), RealLifeMIN==0, or the
// formula floors (bucket-weight D90≤0 or d64≤0).
func computeD64Base(bh onCourt) float64 {
	if bh.D60 == 0 || bh.RealLifeMIN <= 0 {
		return base2ptFallback(bh.FGP)
	}
	twoPA := bh.RealLifeFGA - bh.RealLife3GA
	if twoPA < 0 {
		twoPA = 0
	}
	d88 := per48Min(twoPA, bh.RealLifeMIN)
	d90 := twoPtBucketWeight(bh)
	if d90 <= 0 {
		return base2ptFallback(bh.FGP)
	}
	d64 := float64(bh.D60) * (4*d90 - d88) / (3 * d90)
	if d64 <= 0 {
		return base2ptFallback(bh.FGP)
	}
	return d64
}

// shotValue2pt assembles the faithful 2-point make value using JSB player[+0xD64].
// D64 is computed at shot-time via computeD64Base (D90=twoPtBucketWeight, D88=2PA/48).
// Normal: computeD64Base(bh) + net×500/baseline + blockMod(baseline, leagueBlk48, defBlkSum) + flowTerm.
// mq is the matchupQuality for the possession; the flow term +mq×250/d88 (JSB 5.60
// faithful, RE §3: +flow×250/player[+0xD88]) is applied only on the NORMAL path —
// not shot-clock and not putback (putbackValue2pt is the putback form).
// Shot-clock (rushed look): float64(bh.D60) × 1.3333, net-free, faithful to the
//
//	+0xD60 form. Falls back to base2ptFallback(fgp) when D60==0 (no real-life data).
//
// baseline is the per-snapshot league 2PA/48 (gameState.shotBaseline).
func shotValue2pt(net float64, bh onCourt, mq float64, shotClock bool, baseline, leagueBlk48, defBlkSum float64) float64 {
	if shotClock {
		base := base2ptFallback(bh.FGP)
		if bh.D60 > 0 {
			base = float64(bh.D60)
		}
		return base * shotClock2ptMult
	}
	// flow term: +mq*250/d88 (JSB 5.60 faithful, RE §3: +flow×250/player[+0xD88]).
	// d88 = per-48 2PA rate (same as twoPtBucketWeight's D88). Zero when no real-life
	// 2PA data or zero minutes. Phase 3/4 of matchupQuality are stubs (zero) until
	// PR4/PR-coaching lands — the term matures automatically when they're wired.
	var flowTerm float64
	if bh.RealLifeMIN > 0 {
		twoPA := bh.RealLifeFGA - bh.RealLife3GA
		if twoPA < 0 {
			twoPA = 0
		}
		if twoPA > 0 {
			d88 := per48Min(twoPA, bh.RealLifeMIN)
			if d88 > 0 {
				flowTerm = mq * 250.0 / d88
			}
		}
	}
	return computeD64Base(bh) + net*netToShotValue/baseline + blockMod(baseline, leagueBlk48, defBlkSum) + flowTerm
}

// putbackValue2pt is the JSB 5.60 putback (OReb-continuation) 2pt make-value:
// player[+0xD60] × 1.3333 — net-free and 4/3-boosted (decompile :93880-93883).
// Uses D60 (the raw 2P‰, not the putback-adjusted D64). Falls back to
// base2ptFallback(fgp) when bh.D60==0 (no real-life data).
func putbackValue2pt(bh onCourt) float64 {
	base := base2ptFallback(bh.FGP)
	if bh.D60 > 0 {
		base = float64(bh.D60)
	}
	return base * shotClock2ptMult
}

// shotValue3pt assembles the faithful 3-point make value (JSB player[+0xD80]).
// = float64(d80) + net×500/(baseline×1.5) + blockMod(baseline×1.5, leagueBlk48, defBlkSum).
// d80 is the player's real-life 3P‰ (0 if no real-life 3GA — faithful, since
// a player with no 3pt attempts provides no real base, but a positive net term
// can still make the value positive). baseline is the per-snapshot league 2PA/48.
func shotValue3pt(net float64, d80 int, baseline, leagueBlk48, defBlkSum float64) float64 {
	b := baseline * 1.5
	return float64(d80) + net*netToShotValue/b + blockMod(b, leagueBlk48, defBlkSum)
}

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
// The 3pt path applies no clutch multiplier — but its value still takes net:
// d80 + net×500/(baseline×1.5) + block_term (corrected 2026-07-17).
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
