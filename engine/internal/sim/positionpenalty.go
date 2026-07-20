package sim

// positionMultipliers is the per-slot "league-average rating profile"
// (00_MASTER_REFERENCE.md "Position Penalty"): expected_X = base × multiplier.
// Indexed [slot-1] → {OO, DO, PO}.
var positionMultipliers = [5][3]float64{
	slotPG - 1: {1.0, 4.0, 4.0},
	slotSG - 1: {3.0, 6.0, 3.0},
	slotSF - 1: {3.0, 6.0, 3.0},
	slotPF - 1: {3.0, 4.0, 5.0},
	slotC - 1:  {3.0, 3.0, 6.0},
}

// penaltyBase is the rating-compression base (minutes × 1/96 + 1.0). It rises
// from 1.0 (0 min) to 1.5 (48 min), so the penalty grows with playing time.
// Takes fractional minutes: the binary's +0xD58 base is a double (MPG in the
// fallback path, see penaltyBaseMinutes).
func penaltyBase(minutes float64) float64 {
	if minutes < 0 {
		minutes = 0
	}
	return minutes/96.0 + 1.0
}

// penaltyBaseMinutes returns the per-game minutes fed to penaltyBase, faithfully
// porting the binary's player[+0xD58] (jsb-native/re-artifacts/jsb-J19-residue-
// 20260712.md §3): the GM's Game-Plan minutes target when set (DCMinutes>0), else
// actual MPG = RealLifeMIN/RealLifeGP. The binary computes t = GP·dc_minutes; if
// t==0 it substitutes the season MIN total, then divides by GP — so +0xD58 =
// dc_minutes when a target is set, else MIN/GP. The binary reads the real .plb, so
// where a GM set a target it uses that dc_minutes; Go's .plb game-plan minutes are
// currently unwired (DCMinutes==0 for all players, backup/assemble.go:327), so this
// code takes the MPG fallback for every player — a faithful approximation of the
// binary pending verified .plb wiring, not the exact per-snapshot value. GP==0 (no
// games, no target) → 0, preserving base=1.0. The binary's fully-CPU-team conditioning
// scale (×rec[+0x18]·0.01 ≈ ×1.0 at the nominal conditioning=100) is not modeled —
// Go carries no conditioning rating.
func penaltyBaseMinutes(p onCourt) float64 {
	if p.DCMinutes > 0 {
		return float64(p.DCMinutes)
	}
	if p.RealLifeGP > 0 {
		return float64(p.RealLifeMIN) / float64(p.RealLifeGP)
	}
	return 0
}

// floor1 floors a rating at 1, matching the decompile's adjusted-rating floor.
func floor1(r int) float64 {
	if r < 1 {
		return 1.0
	}
	return float64(r)
}

// positionPenalty is the rating-compression term subtracted from net advantage:
//
//	penalty = Σ((X − exp_X) × X) / (OO + DO + PO),  exp_X = base × slot_multiplier
//
// A rating that matches its slot's expected value contributes zero; a rating
// above expectation pays a penalty scaling with the rating itself (so extreme
// outside ratings at guard slots are compressed hardest), and a below-
// expectation rating gives a small bonus. Verified against the master-reference
// worked examples (Steph PG → +4.38, avg PG → −0.02, anti-PG → +2.38, C → 0.60).
func positionPenalty(p onCourt) float64 {
	oo, do, po := floor1(p.OO), floor1(p.DriveOff), floor1(p.PO)
	base := penaltyBase(penaltyBaseMinutes(p))
	m := positionMultipliers[p.slot-1]
	expOO, expDO, expPO := base*m[0], base*m[1], base*m[2]

	num := (oo-expOO)*oo + (do-expDO)*do + (po-expPO)*po
	den := oo + do + po
	if den == 0 {
		return 0
	}
	return num / den
}
