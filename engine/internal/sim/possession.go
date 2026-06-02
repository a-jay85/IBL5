package sim

import "github.com/a-jay85/IBL5/engine/internal/result"

// maxOffensiveRebounds caps offensive-rebound continuations within a single
// trip, guaranteeing the inner loop terminates even on a pathological roster.
const maxOffensiveRebounds = 8

// andOneBaseShare is the made-base term of the OLD O(100) and-one bucket weight
// (a fraction of the player's 2pt make value). The live buckets now use the
// net-free O(1) helpers in bucketweights.go; this const is retained solely for
// the pre-rescale characterization test (bucketweights_test.go), which
// reconstructs the old assembly to document the O(100) HCA no-op.
const andOneBaseShare = 0.05

// turnoverPropensityScale maps a ball-handler's TVR rating to the turnover
// roll threshold: sqrt(turnoverDefValue) ≈ TVR × scale, compared to
// rand_int(1,1793). The scale is chosen so a typical handler turns the ball
// over at a plausible rate; absolute turnover frequency is a validation-phase
// calibration item (the real engine's local_44 is a per-game double of
// unpinned scale). turnoverDefValue is squared so the spec's sqrt() recovers
// this linear threshold.
const turnoverPropensityScale = 5.8

// defenderAtSlot returns the defender sharing the ball-handler's slot, falling
// back to the first defender when no exact match exists (short lineup).
func defenderAtSlot(d *teamState, slot int) onCourt {
	for _, p := range d.players {
		if p.slot == slot {
			return p
		}
	}
	return d.players[0]
}

// threePtPropensity is the share of a player's attempts that are 3-pointers,
// from r_3ga / (r_fga + r_3ga). An unrated roster defaults to 0.25 so the engine
// still attempts some 3s without dividing by zero.
func threePtPropensity(p onCourt) float64 {
	denom := p.FGA + p.TGA
	if denom <= 0 {
		return 0.25
	}
	return float64(p.TGA) / float64(denom)
}

// turnoverThreshold is the linear turnover roll threshold for a TVR rating; the
// caller squares it so the outcome selector's sqrt() recovers this value.
func turnoverThreshold(tvr int) float64 { return float64(tvr) * turnoverPropensityScale }

// possession resolves one offensive trip: ball-handler selection, shot-type and
// matchup resolution, the play-outcome path, and any free throws or rebounds.
// Offensive rebounds continue the trip; a made shot, defensive rebound, or
// turnover ends it. The caller flips possession after every trip.
//
// fbPending is the fast-break flag set by the prior possession (its defensive
// rebound or steal). It is consumed unconditionally: when Stage 2 (TransOff
// trigger) and Stage 3 (steal-success) both pass, the possession runs as a fast
// break; otherwise it falls through to the normal half-court loop. The named
// return fbNext re-arms the flag when THIS possession ends via a defensive
// rebound or a steal (the team that gained the ball gets the next break).
func possession(gs *gameState, offense, defense *teamState, periodIdx int, fbPending bool) (fbNext bool) {
	if len(offense.players) == 0 || len(defense.players) == 0 {
		return false
	}
	gs.emit(result.Event{
		Kind: result.EventPossessionStart, Period: gs.period, Clock: gs.clock, TeamID: offense.teamID,
	})

	if fbPending {
		if gs.transitionShotRate <= 0 {
			gs.transitionShotRate = resetTransitionShotRate(offense)
		}
		if transitionTriggers(offense, gs.gameType, gs.rng) && gs.transitionStealSucceeds(defense) {
			return gs.runTransitionPossession(offense, defense, periodIdx)
		}
	}

	bh := selectBallHandler(offense, gs.rng)
	for trip := 0; trip <= maxOffensiveRebounds; trip++ {
		scoreDiff := offense.score - defense.score
		matched := defenderAtSlot(defense, bh.slot)
		pt := selectShotType(bh, matched, gs.rng)
		def := selectDefender(defense, pt, gs.rng)

		penalty := positionPenalty(bh)
		// Playoff net×1.25 lives in netAdvantage and feeds shot_value only — matching
		// JSB, where net enters solely via shot_value (+0xD90 is an independent
		// offensive-rate composite). The 2pt bucket weight is now net-free (see
		// bucketweights.go), so the playoff multiplier no longer amplifies it.
		net := netAdvantage(pt, bh, def, penalty, false, gs.gameType)
		mq := matchupQuality(bh.FGP, bh.energy, defense.players) // live energy (inert under current curve)

		sv2 := applyClutch(shotValue2pt(net, bh.FGP, false), bh.Clutch, gs.period, scoreDiff)
		in := outcomeInputs{
			twoPtWeight:      twoPtBucketWeight(bh),
			threePtWeight:    threePtBucketWeight(bh),
			andOneWeight:     andOneBucketWeight(mq, bh),
			foulOnlyWeight:   foulBucketWeight(net, bh),
			turnoverDefValue: turnoverThreshold(bh.TVR) * turnoverThreshold(bh.TVR),
		}

		switch selectOutcome(in, false, false, false, gs.rng) {
		case outcome2pt:
			if made, _ := gs.shotAttempt(offense, defense, bh, sv2, result.ShotTwoPoint, periodIdx); !made {
				gs.creditBlock(offense, defense, bh, def)
				if cont, next := gs.rebound(offense, defense, periodIdx); cont {
					bh = next
					continue
				}
				return true // defensive rebound → fast-break pending
			}
			return false // made shot
		case outcome3pt:
			if made, _ := gs.shotAttempt(offense, defense, bh, shotValue3pt(), result.ShotThree, periodIdx); !made {
				gs.creditBlock(offense, defense, bh, def)
				if cont, next := gs.rebound(offense, defense, periodIdx); cont {
					bh = next
					continue
				}
				return true // defensive rebound → fast-break pending
			}
			return false // made shot
		case outcomeAndOne:
			gs.madeFieldGoal(offense, bh, result.ShotTwoPoint, periodIdx)
			gs.freeThrows(offense, defense, bh, def, 1, periodIdx)
			return false
		case outcomeFoulOnly:
			gs.freeThrows(offense, defense, bh, def, 2, periodIdx)
			return false
		case outcomeTurnover:
			gs.emit(result.Event{
				Kind: result.EventTurnover, Period: gs.period, Clock: gs.clock,
				TeamID: offense.teamID, PlayerID: bh.PID,
			})
			gs.maybeInjure(offense, bh)                 // per-turnover injury check on the committer
			return gs.creditSteal(offense, defense, bh) // steal → fast-break pending
		}
	}
	return false
}

// shotAttempt records a field-goal attempt of the given type, rolls make/miss,
// and scores points on a make. It emits the attempt event (the box-score
// Game2GA/Game3GA counters are derived from it by aggregateBoxes). It returns
// (made, ended): ended is always true for a single attempt; made distinguishes a
// basket from a miss so the caller can route a miss to the rebound phase.
func (gs *gameState) shotAttempt(offense, defense *teamState, shooter onCourt, shotValue float64, st result.ShotType, periodIdx int) (made, ended bool) {
	gs.emit(result.Event{
		Kind: result.EventShotAttempt, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: st,
	})
	// FG make uses BASE stamina fatigue (per spec, distinct from the live-energy
	// outcome weights/selectors); under the current curve this is ≈1.0 anyway.
	if rollMake(shotValue, fatigueFactor(shooter.Stamina), gs.rng) {
		gs.creditMadeFieldGoal(offense, shooter, st, periodIdx)
		return true, true
	}
	gs.emit(result.Event{
		Kind: result.EventShotMiss, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: st,
	})
	return false, true
}

// madeFieldGoal records a guaranteed made 2pt (the and-one basket): it emits the
// attempt event then credits the make.
func (gs *gameState) madeFieldGoal(offense *teamState, shooter onCourt, st result.ShotType, periodIdx int) {
	gs.emit(result.Event{
		Kind: result.EventShotAttempt, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: st,
	})
	gs.creditMadeFieldGoal(offense, shooter, st, periodIdx)
}

// creditMadeFieldGoal scores the points (live score, read for clutch/OT), bumps
// the live per-shooter made-FG tally (read by the block-probability penalty),
// and emits the make event. The box-score Game2GM/Game3GM counters are derived
// from that event by aggregateBoxes — no box row is written here.
func (gs *gameState) creditMadeFieldGoal(offense *teamState, shooter onCourt, st result.ShotType, periodIdx int) {
	pts := 2
	if st == result.ShotThree {
		pts = 3
	}
	offense.addPeriodPoints(periodIdx, pts)
	if gs.madeFG == nil {
		gs.madeFG = map[int]int{}
	}
	gs.madeFG[shooter.PID]++
	gs.emit(result.Event{
		Kind: result.EventShotMake, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: st,
	})
}

// freeThrows charges the foul to the contesting defender and resolves n
// free-throw attempts for the shooter, scoring each make. It emits the foul and
// free-throw events (the latter carrying FTAttempts/FTMade so aggregateBoxes can
// reconstruct GamePF/GameFTA/GameFTM); only the live score is mutated here.
func (gs *gameState) freeThrows(offense, defense *teamState, shooter, defender onCourt, n, periodIdx int) {
	defense.fouls[defender.PID]++ // live PF tally for foul-out/trouble subs (box PF is event-derived)
	gs.emit(result.Event{
		Kind: result.EventFoul, Period: gs.period, Clock: gs.clock,
		TeamID: defense.teamID, PlayerID: defender.PID,
	})
	made := shootFreeThrows(shooter, n, gs.rng)
	offense.addPeriodPoints(periodIdx, made)
	gs.emit(result.Event{
		Kind: result.EventFreeThrow, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: result.ShotFreeThrow,
		FTAttempts: n, FTMade: made,
	})
}

// rebound resolves a missed-shot rebound. It returns (offensiveRetained,
// newHandler): when true, the offense kept the ball and newHandler continues the
// trip; when false, the defense rebounded and the possession ends.
func (gs *gameState) rebound(offense, defense *teamState, periodIdx int) (bool, onCourt) {
	offStr := teamReboundStrength(offense, true)
	defStr := teamReboundStrength(defense, false)
	if gs.rng.Float64() < orebProbability(offStr, defStr) {
		reb := selectRebounder(offense, true, gs.rng)
		gs.emit(result.Event{
			Kind: result.EventRebound, Period: gs.period, Clock: gs.clock,
			TeamID: offense.teamID, PlayerID: reb.PID, OffensiveRebound: true,
		})
		return true, reb
	}
	reb := selectRebounder(defense, false, gs.rng)
	gs.emit(result.Event{
		Kind: result.EventRebound, Period: gs.period, Clock: gs.clock,
		TeamID: defense.teamID, PlayerID: reb.PID, OffensiveRebound: false,
	})
	return false, onCourt{}
}
