package sim

import "github.com/a-jay85/IBL5/engine/internal/rng"

// ballHandlerRating returns the ODPT rating that drives ball-handling
// probability for a given lineup slot (00_MASTER_REFERENCE.md "Ball Handler
// Selection"): slot 1 (PG) → drive offense, slots 2-3 (SG/SF) → outside
// offense, slots 4-5 (PF/C) → post offense. Floored at 1 so an all-zero roster
// still yields a valid, divide-by-zero-free distribution.
func ballHandlerRating(p onCourt) int {
	var r int
	switch p.slot {
	case slotPG:
		r = p.DriveOff
	case slotSG, slotSF:
		r = p.OO
	case slotPF, slotC:
		r = p.PO
	default:
		r = p.OO
	}
	if r < 1 {
		r = 1
	}
	return r
}

// ballHandlerShare is the JSB exponential-concentration share
// rating / (team_total_for_that_rating − rating). The denominator is guarded so
// a sole or dominant handler (team_total == rating) cannot divide by zero.
func ballHandlerShare(rating, teamTotal int) float64 {
	denom := teamTotal - rating
	if denom <= 0 {
		denom = 1
	}
	return float64(rating) / float64(denom)
}

// selectBallHandler picks which starter initiates the possession by weighted
// random over each starter's ball-handling share. The team total is summed per
// rating type (OO/DO/PO) exactly as the decompile does, so each starter's share
// uses the correct denominator for its slot.
func selectBallHandler(t *teamState, r *rng.RNG) onCourt {
	var totOO, totDO, totPO int
	for _, p := range t.players {
		oo, do, po := p.OO, p.DriveOff, p.PO
		if oo < 1 {
			oo = 1
		}
		if do < 1 {
			do = 1
		}
		if po < 1 {
			po = 1
		}
		totOO += oo
		totDO += do
		totPO += po
	}

	shares := make([]float64, len(t.players))
	var sum float64
	for i, p := range t.players {
		rating := ballHandlerRating(p)
		var total int
		switch p.slot {
		case slotPG:
			total = totDO
		case slotSG, slotSF:
			total = totOO
		case slotPF, slotC:
			total = totPO
		default:
			total = totOO
		}
		shares[i] = ballHandlerShare(rating, total)
		sum += shares[i]
	}

	if sum <= 0 || len(t.players) == 0 {
		return t.players[0]
	}
	roll := r.Float64() * sum
	var acc float64
	for i, s := range shares {
		acc += s
		if roll <= acc {
			return t.players[i]
		}
	}
	return t.players[len(t.players)-1]
}
