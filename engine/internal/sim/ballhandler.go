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
// Its one draw comes from FUN_005c6100 and reads r.Raw(), so a replay logs
// api "raw", the form the JSB draw log matches.
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
	roll := float64(r.Raw()) / float64(rng.RawMax+1) * sum
	var acc float64
	for i, s := range shares {
		acc += s
		if roll <= acc {
			return t.players[i]
		}
	}
	return t.players[len(t.players)-1]
}

// ballHandlerFlagCount is the length of the per-slot usage-dominance flag table
// the ported selector writes. Index 0 is unused. Indexes 1-5 hold offensive team
// 2's slots 1-5, and indexes 6-10 hold team 1's slots 1-5.
const ballHandlerFlagCount = 11

// The ported selector's constants, as the reference gives them.
const (
	// ballHandlerFlagGate is the share of the lineup's two-point composite a
	// player must clear before the routine rolls that player's flag.
	ballHandlerFlagGate = 0.3
	// ballHandlerFlagRollScale scales the flag roll: the flag is set when a
	// uniform draw on [0, 0.5) lands below the player's share.
	ballHandlerFlagRollScale = 0.5
	// ballHandlerFlagValue is the value a set flag holds.
	ballHandlerFlagValue = 4
	// ballHandlerShareCap caps a dominant player's composite at this share of
	// the lineup's sum before it enters the handling weight.
	ballHandlerShareCap = 0.375
	// ballHandlerRatingScale multiplies the slot rating in the share's divisor.
	ballHandlerRatingScale = 0.05
)

// ballHandlerPlayer holds what the ported selector reads for one on-court
// player, in lineup-slot order.
type ballHandlerPlayer struct {
	// ID is the player's league index. The routine returns it for the pick.
	ID int
	// OutsideOff, DriveOff and PostOff are the OO, DO and PO ratings. Slot 1
	// uses DO, slots 2-3 use OO and slots 4-5 use PO, as ballHandlerRating does.
	OutsideOff int
	DriveOff   int
	PostOff    int
	// TwoPtComposite is the per-game two-point composite that
	// twoPtBucketWeight models. It drives the usage-dominance flag.
	TwoPtComposite float64
	// Aux1 scales the share term of the handling multiplier. The reference
	// calls it only a per-call scalar.
	Aux1 int
	// Aux2 is a base term of the handling weight that the home-court term
	// shifts up for team 2. The reference gives it no meaning.
	Aux2 float64
	// Aux3 is added to the player's cumulative weight after the multiplier,
	// and it is the numerator of the p8 branch. The reference gives it no
	// meaning.
	Aux3 float64
	// Aux4 is a base term of the handling weight that the home-court term
	// shifts the other way from Aux2. The reference gives it no meaning.
	Aux4 float64
}

// ballHandlerInputs holds every value the ported ball-handler selector reads.
// Only the pl-0010 trace test fills it today. The engine has a source for
// TwoPtComposite (twoPtBucketWeight), the slot order (teamState.players) and the
// ratings. Aux1-Aux4, PosTwoPtPct, the team terms and the possession context
// have no confirmed engine source yet, so production keeps selectBallHandler.
type ballHandlerInputs struct {
	Players [5]ballHandlerPlayer
	// OffTeam is the offensive team, 1 or 2. It sets the sign of the
	// home-court term and which half of the flag table the routine writes.
	OffTeam int
	// HomeCourt is 1 when the home-court term applies.
	HomeCourt int
	// HomeCourtAdv is the home-court advantage the term scales.
	HomeCourtAdv float64
	// PosTwoPtPct is the league per-position 2P% table (x100), slot 1 first.
	PosTwoPtPct [5]float64
	// WeightGate is the config gate for the handling multiplier. At 0 every
	// multiplier is 1.0.
	WeightGate int
	// PlayType is the caller's play code. Play type 5 rolls p8 first.
	PlayType int
	// PrevHandler is the player who already holds the ball, or 0.
	PrevHandler int
	// P8In is the caller's p8 byte before the call.
	P8In int
	// TeamRollTerms are the four team terms whose sum scales the play-type-5
	// p8 roll. TeamRollLimit is the value the roll must not exceed. The
	// reference gives them no meaning.
	TeamRollTerms [4]float64
	TeamRollLimit float64
	// FlagsBefore is the flag table before the call. The routine rewrites
	// only the slots whose share clears ballHandlerFlagGate.
	FlagsBefore [ballHandlerFlagCount]int
}

// ballHandlerResult holds the ported selector's outputs: the picked player's
// league index, the picked slot, the two caller bytes and the flag table.
type ballHandlerResult struct {
	Ret   int
	Slot  int
	P6    int
	P8    int
	Flags [ballHandlerFlagCount]int
}

// selectBallHandlerJSB is the faithful JSB ball-handler selector, ported with
// every read as an explicit input. It is off the production draw path:
// selectBallHandler still picks the handler in a simulated game, and a later unit
// wires this port in once the engine has sources for every input.
//
// The draws run in the reference's order. Play type 5 with no prior handler
// rolls p8 first. Each slot whose two-point share clears 0.3 rolls its flag. Then
// a roll over the cumulative handling weights picks the slot, rolling again when
// the draw lands on no slot. The prior-handler path is not ported: the capture
// holds no record that takes it, and the path reads a player outside the lineup.
func selectBallHandlerJSB(in ballHandlerInputs, r *rng.RNG) ballHandlerResult {
	if in.PrevHandler != 0 {
		panic("selectBallHandlerJSB: the prior-handler path is not ported")
	}
	p8 := in.P8In
	if in.PlayType == 5 {
		// The capture holds no play type 5 record, so no test pins this roll.
		scale := in.TeamRollTerms[0] + in.TeamRollTerms[1] + in.TeamRollTerms[2] + in.TeamRollTerms[3]
		u := float64(r.Raw()) / float64(rng.RawMax+1) * scale
		p8 = 0
		if u <= in.TeamRollLimit {
			p8 = 1
		}
	}
	flags := usageDominanceFlagsFor(in, r)

	var sum float64
	var totOO, totDO, totPO int
	for _, p := range in.Players {
		sum += p.TwoPtComposite
		totOO += p.OutsideOff
		totDO += p.DriveOff
		totPO += p.PostOff
	}

	// Products are wrapped in float64() so the compiler cannot fuse them into
	// a later add; the reference rounds every step to a double.
	var cum [6]float64
	for k := 1; k <= 5; k++ {
		p := in.Players[k-1]
		var rating, total int
		switch k {
		case slotPG:
			rating, total = p.DriveOff, totDO
		case slotSG, slotSF:
			rating, total = p.OutsideOff, totOO
		default:
			rating, total = p.PostOff, totPO
		}
		rf := float64(rating)
		share := rf / (float64(total) - rf)
		mult := float64((share/float64(rf*ballHandlerRatingScale)*float64(p.Aux1)/in.PosTwoPtPct[k-1] + 1.0) * 0.5)
		if in.WeightGate == 0 {
			mult = 1.0
		}

		composite := p.TwoPtComposite
		if p.TwoPtComposite/sum > ballHandlerShareCap {
			composite = float64(sum * ballHandlerShareCap)
		}
		aux2, aux4 := p.Aux2, p.Aux4
		if in.HomeCourt == 1 {
			shift := float64(float64(in.OffTeam*2-3) * in.HomeCourtAdv)
			aux2 = shift + aux2
			aux4 = aux4 - shift
		}
		var w float64
		if p8 == 1 {
			w = float64(p.Aux3 / composite * aux4)
		} else {
			w = float64((composite + aux4 + aux2) * mult)
		}
		cum[k] = w + cum[k-1] + p.Aux3
	}

	ret, slot := 0, 0
	for ret == 0 {
		u := float64(float64(r.Raw()) / float64(rng.RawMax+1) * cum[5])
		for k := 1; k <= 5; k++ {
			if u <= cum[k] && cum[k-1] < u && ret == 0 && in.Players[k-1].ID != 0 {
				slot = k
				ret = in.Players[k-1].ID
			}
		}
	}
	// With no prior handler the routine never writes p6, and every captured
	// caller passes 0.
	p6 := 0

	return ballHandlerResult{
		Ret:   ret,
		Slot:  slot,
		P6:    p6,
		P8:    p8,
		Flags: flags,
	}
}

// usageDominanceFlagsFor rolls the per-slot usage-dominance flags the ported
// selector writes. A slot whose share of the lineup's two-point composite clears
// ballHandlerFlagGate draws once: its flag is set when a uniform draw on
// [0, 0.5) lands below the share, and cleared otherwise. A share above 0.5
// always sets it. Slots under the gate keep their FlagsBefore value.
//
// It does not reuse computeUsageDominanceFlags. That function sets the flag
// only when the share exceeds 0.5 and draws nothing, so it cannot match a
// capture in which the routine draws for each share over 0.3. Production still
// passes no flags at both call sites, so that difference has no effect today.
func usageDominanceFlagsFor(in ballHandlerInputs, r *rng.RNG) [ballHandlerFlagCount]int {
	flags := in.FlagsBefore
	var sum float64
	for _, p := range in.Players {
		sum += p.TwoPtComposite
	}
	for k := 1; k <= 5; k++ {
		share := in.Players[k-1].TwoPtComposite / sum
		if !(share > ballHandlerFlagGate) {
			continue
		}
		idx := k
		if in.OffTeam == 1 {
			idx = k + 5
		}
		u := float64(r.Raw()) / float64(rng.RawMax+1) * ballHandlerFlagRollScale
		flags[idx] = 0
		if u < share {
			flags[idx] = ballHandlerFlagValue
		}
	}
	return flags
}
