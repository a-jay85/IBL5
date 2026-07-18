package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

const (
	// transitionTriggerDenom is the Stage-2 trigger roll domain: a random on-court
	// starter fires the break iff rand_int(1..transitionTriggerDenom) ≤ its
	// TransOff rating (00_MASTER_REFERENCE.md L878-896). Asm-pinned jumpshot 5.60
	// value (push 0x12 before the rand_int call) — 18.
	transitionTriggerDenom = 18

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
//
// This same gate doubles as the J24 Phase 4 DRB-push clock gate (FUN_004e42e0
// code 7): possession.go's fbPending branch calls this ONCE per fast-break-
// eligible possession and, when prev == possDRB, captures the result into
// gs.drbPushFired for gameloop.go to route the {2,3,4}s DRB-push step class —
// see possession.go's fbPending branch and state.go's drbPushFired field.
// gameloop.go never re-calls transitionTriggers itself: a second call would
// draw a fresh (starter-pick, rand_int(18)) pair off the RNG stream, shifting
// the stream and letting the clock class disagree with the run decision.
//
// RE threshold note: the reference-engine formula is
// r_trans_off − (gt==playoff?1:0) + strategy_adj, where strategy_adj derives
// from the offensive team's .lge tempo/coach setting at offset +0x12c
// (values 1-5: 5 → +1, 4 → +1 iff rand_int(2), 1 → −1, 2 → −1 iff rand_int(2),
// 3 → 0). This port omits the term entirely, which is exactly strategy_adj = 0
// (the neutral/coach-3 case) — so no gate-code change was needed for the J24
// Phase 4 DRB-push stand-in, only this documentation. This is an OPEN RE
// SUB-STEP pending the .lge +0x12c field being pinned and wired through
// bundle.Team/Player; until then every team is treated as coach-neutral.
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
// the break re-arms the fast-break flag (returned as the possOutcome — possDRB or
// possSteal respectively). Offensive rebounds continue the break, bounded by
// maxOffensiveRebounds.
func (gs *gameState) runTransitionPossession(offense, defense *teamState, periodIdx int) (outcome possOutcome) {
	gs.transitions++
	bh := selectBallHandler(offense, gs.rng)
	for trip := 0; trip <= maxOffensiveRebounds; trip++ {
		// Steal-driven turnover on the break too (ADR-0045), mirroring the half-court
		// path so fast-break possessions use the same model.
		if gs.stealTurnover(offense, defense, bh) {
			return possSteal // steal → steal fast-break pending for the defense
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
		// OriginTransition: the ADR-0053 MakePutback arm is OriginOffReb-scoped, so a
		// fast-break shot (and any rebound continuation within the break, which the
		// transition path also tags OriginTransition) keeps its live make-value.
		var defBlkSum float64
		for _, dp := range defense.players {
			defBlkSum += dp.DE8
		}
		blkCap := 1.5 * 5 * gs.leagueBlk48
		if defBlkSum > blkCap {
			defBlkSum = blkCap
		}
		sv2 := applyClutch(gs.makeValue2pt(net, bh, mq, result.OriginTransition, gs.leagueBlk48, defBlkSum), bh.Clutch, gs.period, scoreDiff)
		// Play-outcome buckets use the same faithful helpers as the half-court path
		// (bucketweights.go) — the second of the two outcomeInputs assembly sites. sv2
		// (above) feeds shotAttempt on the 2pt path ONLY; it does not double as the
		// 2pt bucket weight. HCA is NEUTRAL on the transition path (param_5==0 skips the
		// four HCA legs); all buckets are side-symmetric on a fast break (J15 Phase 5).
		// playBuckets threads this possession's bh through so its deterministic base
		// (2−fatigue)·tovRate(bh) matches the fast-break ball handler, same as the
		// half-court path.
		// allow3pt=false: a fast break is never a 3pt attempt (allowedPaths excludes it),
		// so the 3pt composite is 0 here and Branch-B's ΣD is 2pt+foul on the break.
		twoPtW, _, foulW := gs.playBuckets(bh, offense, defense, 0, 0, mq, false) // param_5==0: transition fully symmetric, no HCA legs (J15 Phase 5)
		in := outcomeInputs{
			twoPtWeight:      twoPtW,
			threePtWeight:    0,
			andOneWeight:     andOneBucketWeight(mq, bh),
			foulOnlyWeight:   foulW,
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
				return possDRB // defensive rebound re-arms the DRB-push fast break
			}
			return possNormal // made shot
		case outcomeAndOne:
			gs.madeFieldGoal(offense, bh, result.ShotTwoPoint, result.OriginTransition, periodIdx)
			gs.freeThrows(offense, defense, bh, def, 1, periodIdx)
			return possNormal
		case outcomeFoulOnly:
			gs.freeThrows(offense, defense, bh, def, 2, periodIdx)
			return possNormal
		case outcomeTurnover:
			// Negligible independent [2,5] check: unforced change of possession, no
			// stealer (steal-driven turnovers are rolled at the top of the trip).
			gs.emit(result.Event{
				Kind: result.EventTurnover, Period: gs.period, Clock: gs.clock,
				TeamID: offense.teamID, PlayerID: bh.PID,
			})
			gs.maybeInjure(offense, bh) // per-turnover injury check on the committer
			return possNormal
		}
	}
	return possNormal
}
