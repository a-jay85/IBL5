package sim

import "github.com/a-jay85/IBL5/engine/internal/rng"

// tempoFactor is the JSB "Gameplay Adjustment Factor" (CEngine+0x63b8). IBL runs
// it at 1.0 (confirmed from IBL5.lge), so possession_time == base_time — neutral
// NBA pace. See 00_MASTER_REFERENCE.md "Gameplay Adjustment Factor / Tempo".
const tempoFactor = 1.0

// base_time is CONSTANT per matchup in JSB 5.60 — the composite ratio is dead code.
//
// Binary-verified 2026-07-17 (J24 Phase 0; full proof chain in jsb-native/
// re-artifacts/jsb-J24-pace-dispersion-RE-20260716.md §8): FUN_004e4150's every
// composite/param term is multiplied by u = CEngine+0x38, and u is unconditionally
// 0.0 — its single writer (FUN_004cfa50 epilogue, VA 0x4d4e5a-0x4d4e79) averages two
// stack doubles whose ONLY writers are the prologue zero-stores (VA 0x4cfb90-0x4cfbab;
// exhaustive modrm scan of 0x4cfa50-0x4d5100). With u = 0 the ratio evaluates
// 2880 / 0 → +inf (x87 masked div-by-zero) → clamped to the 16.0 ceiling, every call.
// So ALL cross-matchup pace dispersion — Var(lnPOSS) ≈ 0.000721 and the positive
// Cov(lnPOSS,lnPPS) in the archives — comes from the possession-type MIX (steal
// transitions, DRB pushes, half-court jitter), the J24 Phases 2-4 port, NOT from a
// roster-dependent base_time. This retired the ADR-0042 additive teamBaseTime
// stand-in (offVolumeScale/defRatingScale channel): it modeled dispersion 5.60 does
// not have.
//
// The [13,16] clamp bounds below are FUN_004e4150's (00_MASTER_REFERENCE.md); with
// u = 0 only the 16.0 ceiling is reachable — 16.0 is the FAITHFUL constant center.
//
// J24 PHASE 5 NO-GO (2026-07-17, archive smoke runs=4 stride=4, seed 20240601):
// the faithful 16.0 could NOT be installed. With the Phase 2-4 fast-class mix
// live, mean pace at 16.0 lands 114.68 poss/g (real ~104.6, gate [103.5,105.5])
// because the engine's fast-class share is ~29% of possessions (implied
// half-court weight 0.706 from the {13.65, 16.0} pace pair) vs real ~11.5%
// (~24 transition markers / ~209 possessions). Var(lnPOSS) also stayed at
// ~0.00027 (target ≥ 0.0006) and Cov(lnPOSS,lnPPS) stayed negative (−0.000049
// vs real +0.000241) — the mix ports the step CLASSES faithfully but their
// ARMING RATES are the engine's own steal/transition-gate rates, which fire
// ~2.5× the real share. Closing that share gap (and the CEngine+0x30 redraw
// flag + .lge +0x12c strategy_adj open RE sub-steps) is the J24 residual; the
// provisional center walks back to the faithful 16.0 when it closes. ADR-0085.
const (
	baseTimeLow  = 13.0
	baseTimeHigh = 16.0
	baseTimeMid  = 17.7 // J24 Phase 5 re-center (PROVISIONAL, deliberately above
	// the faithful [13,16] — see NO-GO block above): the constant base_time
	// that restores mean pace under the over-armed fast-class mix. Bracket
	// smoke of record (basetimemid_sweep_archive_test.go, runs=4 stride=4):
	// 17.5 → 105.38, 17.7 → 104.25, 17.9 → 103.06 poss/g vs real ~104.6 —
	// 17.7 is the pace-closest measured config (auto_merge: false — human
	// signoff adjudicates the literal before merge).
)

// possessionTime is the integer seconds ONE POSSESSION removes from the game
// clock. pt = (2.0 − factor) × base_time is the same per-game constant as
// before (at factor 1.0, pt == base_time; out-of-range pt clamps to the JSB
// 24.0 fallback) — but unlike the retired deterministic round-half-up of pt
// itself, each CALL now draws its OWN jittered step from pt, so possessions
// within a game no longer share one shared length.
//
// ROUND-HALF-UP PROVENANCE (5.60-faithful, J23): FUN_004e42e0 (the possession-
// clock update, jsb560_decompiled.c:98386-98438) truncates its float step via
// __ftol then adds 1 when the fractional part ≥ 0.5 (`_DAT_00669ef0` = 0.5,
// confirmed from the raw .rdata bytes 0x3fe0000000000000). This engine uses the
// `int(x + 0.5)` idiom for that round-half-up throughout.
//
// J24 PHASE 2 — HALF-COURT JITTER (RE-derived from FUN_004e42e0's half-court
// step class, code 6; full derivation in jsb-native/re-artifacts/
// jsb-J24-pace-dispersion-RE-20260716.md):
//
//	step = round-half-up(pt/2 + U[0,pt))
//
// U[0,pt) ports 5.60's `rand() × (1/32768) × pt` — this engine's r.Float64()
// stands in for that normalized rand() draw. When the draw truncates to
// exactly pt (i.e. step lands on trunc(pt), which happens for ~1/pt of draws),
// 5.60 redraws ONCE into {3..23} via r.IntN(21)+3; that single redraw may
// land back on trunc(pt) again — faithful to the binary, which does not loop.
//
// OPEN RE SUB-STEP: the binary also gates this redraw on a CEngine+0x30 flag.
// The WRITER is now identified (J17b, objdump 2026-07-20, jsb-native/re-artifacts/
// jsb-J17-forcing-gate-RE-20260720.md): three direct movb $imm8,0x30(esi) sites
// at VA 0x4d88a0/0x4d88cf (=1 when the possessing team is leading and forcedMake
// is off) and 0x4d88d5 (=0 default). The READER (this quick-redraw path) is still
// unported and this port defaults the flag false — porting it changes the late-
// game pace model, so it is DEFERRED to J24 (same surface as J24 residual (2)) to
// be designed + re-validated against the pace gates alongside the fast-class
// share gap. See the NO-GO block above.
func possessionTime(baseTime float64, r *rng.RNG) int {
	pt := (2.0 - tempoFactor) * baseTime
	if pt < 1.0 || pt > 24.0 {
		pt = 24.0 // JSB out-of-range fallback
	}
	step := int(pt/2.0 + r.Float64()*pt + 0.5) // round-half-up(pt/2 + U[0,pt))
	if step == int(pt) {
		// trunc(pt) hit: single redraw into {3..23}, no loop (faithful to
		// FUN_004e42e0 — see docblock above).
		step = r.IntN(21) + 3
	}
	return step
}
