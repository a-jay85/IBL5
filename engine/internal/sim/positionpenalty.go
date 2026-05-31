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
func penaltyBase(minutes int) float64 {
	if minutes < 0 {
		minutes = 0
	}
	return float64(minutes)/96.0 + 1.0
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
	base := penaltyBase(p.DCMinutes)
	m := positionMultipliers[p.slot-1]
	expOO, expDO, expPO := base*m[0], base*m[1], base*m[2]

	num := (oo-expOO)*oo + (do-expDO)*do + (po-expPO)*po
	den := oo + do + po
	if den == 0 {
		return 0
	}
	return num / den
}
