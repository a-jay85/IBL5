package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

const (
	// transitionTriggerDenom is the Stage-2 trigger roll domain: a random on-court
	// starter fires the break iff rand_int(1..transitionTriggerDenom) ≤ its
	// TransOff rating (00_MASTER_REFERENCE.md L878-896). The denom (20) sits above
	// the 1-9 TransOff scale so a max roll never fires for a real rating; it stands
	// in for the unpinned coaching-mod threshold. Documented stand-in.
	transitionTriggerDenom = 20

	// transitionShotRateDecay / transitionShotRateFloor implement the Stage-3
	// per-period decay (L912): the team shot-rate threshold drops by 2.0 on each
	// successful fast break, floored at 2.0, so break frequency falls as the
	// period progresses.
	transitionShotRateDecay = 2.0
	transitionShotRateFloor = 2.0
)

// resetTransitionShotRate is the Stage-3 base threshold: the breaking team's
// aggregate attempt rate Σ(FGA + TGA + FTA) over its starters
// (00_MASTER_REFERENCE.md L900-909, team DA0 = FT+2P+3P rates). Higher-tempo
// offenses generate more fast breaks.
func resetTransitionShotRate(breakingTeam *teamState) float64 {
	var rate float64
	for _, p := range breakingTeam.players {
		rate += float64(p.FGA + p.TGA + p.FTA)
	}
	return rate
}

// transitionTriggers is Stage 2 (L878-896): pick a random on-court starter and
// fire the break iff a 1..transitionTriggerDenom roll falls at or under that
// starter's TransOff rating, minus the playoff special_sub. A failed check means
// the possession proceeds as a normal half-court play despite the pending flag.
// In playoff games the effective threshold is TransOff − 1 (special_sub), so
// fast breaks fire slightly less often (coaching_mod is 0 — neutral coaching).
// The two RNG draws (starter pick, then trigger roll) are unchanged in count and
// order, so determinism is preserved for non-playoff games.
func transitionTriggers(offense *teamState, gt bundle.GameType, r *rng.RNG) bool {
	if len(offense.players) == 0 {
		return false
	}
	p := offense.players[r.IntN(len(offense.players))]
	specialSub := 0
	if isPlayoff(gt) {
		specialSub = playoffFastBreakSub
	}
	return r.IntN(transitionTriggerDenom)+1 <= p.TransOff-specialSub
}

// transitionStealSucceeds is Stage 3 (L900-914): P(success) = rate / (rate + blk)
// where rate is the decaying team shot-rate (gs.transitionShotRate) and blk is
// the defending team's Σ BLK — block-heavy defenses convert fewer stops into
// fast breaks. On success the shot-rate decays by transitionShotRateDecay,
// floored at transitionShotRateFloor.
func (gs *gameState) transitionStealSucceeds(defense *teamState) bool {
	rate := gs.transitionShotRate
	var blk float64
	for _, p := range defense.players {
		blk += float64(p.BLK)
	}
	span := rate + blk
	success := true
	if span > 0 {
		success = gs.rng.Float64()*span <= rate
	}
	if success {
		gs.transitionShotRate = rate - transitionShotRateDecay
		if gs.transitionShotRate < transitionShotRateFloor {
			gs.transitionShotRate = transitionShotRateFloor
		}
	}
	return success
}

// transitionNet is the Stage-4 net advantage for a fast break (L916-920):
// a fixed 5.0 minus only the defender's transition-defense rating. No position
// penalty and no OO/DO/PO term apply; TransOff does not affect this formula.
func transitionNet(defender onCourt) float64 {
	return 5.0 - floor1(defender.TD)
}

// runTransitionPossession resolves a fired fast break (Stage 4). The ball handler
// is reselected by the normal Phase-4 weighted random (L922-923); the contesting
// transition defender supplies the 5.0−TD net. The outcome routes through the
// existing shot/free-throw/rebound machinery with stealPlay=true, so the attempt
// can never be a 3-pointer (allowedPaths). A defensive rebound or steal ending
// the break re-arms the fast-break flag (returned as fbNext). Offensive rebounds
// continue the break, bounded by maxOffensiveRebounds.
func (gs *gameState) runTransitionPossession(offense, defense *teamState, periodIdx int) (fbNext bool) {
	gs.transitions++
	bh := selectBallHandler(offense, gs.rng)
	for trip := 0; trip <= maxOffensiveRebounds; trip++ {
		// Steal-driven turnover on the break too (ADR-0045), mirroring the half-court
		// path so fast-break possessions use the same model.
		if gs.stealTurnover(offense, defense, bh) {
			return true // steal → fast-break pending for the defense
		}
		scoreDiff := offense.score - defense.score
		matched := defenderAtSlot(defense, bh.slot)
		pt := selectShotType(bh, matched, gs.rng)
		def := selectDefender(defense, pt, gs.rng)

		net := transitionNet(def)
		mq := matchupQuality(bh.FGP, bh.energy, defense.players) // live energy (inert under current curve)
		// Make/foul/turnover arms route through the gameState freeze wrappers
		// (freeze.go) on the transition path too, so a frozen Make/Foul/TVR arm
		// applies to fast-break FGA — not only the half-court loop.
		sv2 := applyClutch(gs.makeValue2pt(net, bh.FGP), bh.Clutch, gs.period, scoreDiff)
		// Play-outcome buckets use the same faithful helpers as the half-court path
		// (bucketweights.go) — the second of the two outcomeInputs assembly sites. sv2
		// (above) feeds shotAttempt on the 2pt path ONLY; it does not double as the
		// 2pt bucket weight. HCA is wired here too (site 2 on the made-shot/foul
		// buckets, site 3 on the offQuality divisor inside foulBucketWeight), so a
		// home fast-break possession also grows the foul bucket.
		hca := hcaDelta(gs.gameType, offense.isHome)
		in := outcomeInputs{
			twoPtWeight:      twoPtBucketWeight(bh) + hca,
			threePtWeight:    0, // a fast break is never a 3pt attempt (allowedPaths excludes it)
			andOneWeight:     andOneBucketWeight(mq, bh),
			foulOnlyWeight:   gs.foulWeight(offense.players, defense.players, hca),
			turnoverDefValue: energyCeiling(bh),
		}

		switch selectOutcome(in, false, false, true, gs.rng) {
		case outcome2pt:
			// Every shot on a fired fast break is tagged transition — including a
			// putback after an offensive rebound within the break (the possession
			// ORIGIN is the fast break, so the half-court oreb_continuation bucket
			// stays half-court-only for the ADR-0042 empty-FGA split).
			if made, _ := gs.shotAttempt(offense, defense, bh, sv2, result.ShotTwoPoint, result.OriginTransition, periodIdx); !made {
				gs.creditBlock(offense, defense, bh, def)
				if cont, next := gs.rebound(offense, defense, periodIdx); cont {
					bh = next
					continue
				}
				return true // defensive rebound re-arms the fast break
			}
			return false // made shot
		case outcomeAndOne:
			gs.madeFieldGoal(offense, bh, result.ShotTwoPoint, result.OriginTransition, periodIdx)
			gs.freeThrows(offense, defense, bh, def, 1, periodIdx)
			return false
		case outcomeFoulOnly:
			gs.freeThrows(offense, defense, bh, def, 2, periodIdx)
			return false
		case outcomeTurnover:
			// Negligible independent [2,5] check: unforced change of possession, no
			// stealer (steal-driven turnovers are rolled at the top of the trip).
			gs.emit(result.Event{
				Kind: result.EventTurnover, Period: gs.period, Clock: gs.clock,
				TeamID: offense.teamID, PlayerID: bh.PID,
			})
			gs.maybeInjure(offense, bh) // per-turnover injury check on the committer
			return false
		}
	}
	return false
}
