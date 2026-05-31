package sim

import "github.com/a-jay85/IBL5/engine/internal/rng"

// shotTypeWeights is the per-slot matchup tax/bonus table (00_MASTER_REFERENCE
// "Shot Type Selection"), indexed [slot-1] → {OO_wt, DO_wt, PO_wt}. The offense
// subtracts its own slot's weight and adds the defender's slot weight per
// category, so an aligned matchup cancels and a mismatch skews attempts toward
// the defender's weak play type. These control which play type is *attempted*,
// not how good its net advantage is.
var shotTypeWeights = [5][3]int{
	slotPG - 1: {9, 9, 1},
	slotSG - 1: {8, 8, 3},
	slotSF - 1: {7, 7, 5},
	slotPF - 1: {5, 5, 7},
	slotC - 1:  {4, 4, 8},
}

// adjustedShotWeights returns the floored adjusted OO/DO/PO weights for a
// handler defended by the given defender. Each adjusted rating is floored at 1
// so every play type keeps a positive selection probability (no NaN/zero-sum).
func adjustedShotWeights(handler, defender onCourt) (oo, do, po float64) {
	off := shotTypeWeights[handler.slot-1]
	def := shotTypeWeights[defender.slot-1]
	adj := func(rating, offW, defW int) float64 {
		v := rating - offW + defW
		if v < 1 {
			v = 1
		}
		return float64(v)
	}
	oo = adj(int(floor1(handler.OO)), off[0], def[0])
	do = adj(int(floor1(handler.DriveOff)), off[1], def[1])
	po = adj(int(floor1(handler.PO)), off[2], def[2])
	return
}

// selectShotType picks the offensive play type (outside / drive / post) by
// weighted random over the adjusted ODPT weights. P(type) = adj / Σadj.
func selectShotType(handler, defender onCourt, r *rng.RNG) playType {
	oo, do, po := adjustedShotWeights(handler, defender)
	sum := oo + do + po
	if sum <= 0 {
		return playOutside
	}
	roll := r.Float64() * sum
	switch {
	case roll <= oo:
		return playOutside
	case roll <= oo+do:
		return playDrive
	default:
		return playPost
	}
}
