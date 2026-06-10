package sim

import "github.com/a-jay85/IBL5/engine/internal/result"

// maxOffensiveRebounds caps offensive-rebound continuations within a single
// trip, guaranteeing the inner loop terminates even on a pathological roster.
const maxOffensiveRebounds = 8

// energyCeilingMin/Max are the JSB +0xDF8 clamp bounds [2,5]: the dc-minutes
// energy parameter that feeds the INDEPENDENT turnover roll. Because it lands in
// [2,5], rand(1,1793) ≤ sqrt(value) fires only ~0.1%/poss — the independent check
// is negligible by design and the dominant turnover source is steal-driven
// (steal.go). 00_MASTER_REFERENCE.md +0xDF8 / lines 9617-9623.
const (
	energyCeilingMin = 2.0
	energyCeilingMax = 5.0
)

// energyCeiling is the per-player JSB +0xDF8 value fed to the independent turnover
// roll: (48 − min(dc_minutes, 28)) × 0.03 × conditioning + 1, clamped [2,5], where
// conditioning is the stamina rating normalized to ~[0,1]. It is derived from
// existing bundle fields (no new field invented). The exact upstream conditioning
// term is validation-phase; what matters for fidelity is that the value lands in
// [2,5], keeping the independent check negligible (matching JSB).
func energyCeiling(p onCourt) float64 {
	dcMin := p.DCMinutes
	if dcMin > 28 {
		dcMin = 28
	}
	conditioning := float64(p.Stamina) / 100.0
	v := float64(48-dcMin)*0.03*conditioning + 1.0
	if v < energyCeilingMin {
		return energyCeilingMin
	}
	if v > energyCeilingMax {
		return energyCeilingMax
	}
	return v
}

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

// playBuckets assembles the 2pt/3pt/foul play-outcome bucket weights for one attempt,
// applying home-court advantage at the two modeled JSB sites (site-2 +hca to the 2pt
// bucket and −hca to the foul bucket; site-3 the home-offQuality divisor inside
// foulBucketWeight). allow3pt is false on a fast break (3pt path excluded).
//
// With Branch-B OFF (the default / freeze-lattice path) this is the byte-identical
// pre-PR assembly: twoPtBucketWeight(bh)+hca, threePtBucketWeight(bh), and the foul
// bucket via gs.foulWeight — which preserves the freeze arm + baseline accumulation.
//
// With Branch-B ON it is the ordering-faithful restructure (FUN_004cfa50 setup stage,
// COMPOSITE_DOUBLES_TRACE.md §4): shrink the RAW PRE-HCA composites by the usage factor
// s (branchBShrink), THEN apply HCA. The foul HCA nudge is re-added as the full-composite
// delta foulBucketWeight(...,hca)−foulBucketWeight(...,0), so HCA's magnitude is NEVER
// scaled by s (acceptance-bar precondition), and a cold-start s=1 reproduces the OFF foul
// bucket exactly. Branch-B and the four freeze arms are mutually exclusive diagnostics, so
// the ON path does not route the foul bucket through gs.foulWeight.
func (gs *gameState) playBuckets(bh onCourt, offense, defense *teamState, hca float64, allow3pt bool) (twoPtW, threePtW, foulW float64) {
	raw3pt := 0.0
	if allow3pt {
		raw3pt = threePtBucketWeight(bh)
	}
	if !gs.freeze.BranchB {
		foul := gs.foulWeight(offense.players, defense.players, hca)
		return twoPtBucketWeight(bh) + hca, raw3pt, foul
	}
	raw2pt := twoPtBucketWeight(bh)
	rawFoul := foulBucketWeight(offense.players, defense.players, 0)
	s2, s3, sf := gs.branchBShrink(raw2pt, raw3pt, rawFoul, offense.drbRate, offense.astRate, bh.TransOff)
	hcaFoulDelta := foulBucketWeight(offense.players, defense.players, hca) - rawFoul
	return s2 + hca, s3, sf + hcaFoulDelta
}

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
		// trip 0 is the initial attempt; trip > 0 is reached only via a `continue`
		// after an offensive rebound, so the attempt is a putback continuation.
		origin := result.OriginInitial
		if trip > 0 {
			origin = result.OriginOffReb
		}
		// Dominant, steal-driven turnover (ADR-0045): a successful steal IS the
		// turnover and ends the trip, crediting the stealing defender and arming the
		// defense's fast break. Rolled before the shot path; the negligible
		// independent [2,5] check stays inside selectOutcome below.
		if gs.stealTurnover(offense, defense, bh) {
			return true // steal → fast-break pending for the defense
		}
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

		// Make/foul/turnover arms route through the gameState freeze wrappers
		// (freeze.go): live values in the normal/baseline path, league-mean
		// substitutes when an arm is frozen for the ADR-0043 attribution.
		sv2 := applyClutch(gs.makeValue2pt(net, bh.FGP, origin), bh.Clutch, gs.period, scoreDiff)
		// Home-court advantage, applied at the two modeled JSB sites (delta = +0.2
		// home / −0.2 away, 0 for ASG). Site 2: the made-shot (2pt) bucket gains
		// +delta, the foul bucket loses delta (handled inside foulBucketWeight).
		// Site 3: each offensive player's offQuality term is reduced by delta inside
		// foulBucketWeight's divisor — the dominant, home-favorable term.
		hca := hcaDelta(gs.gameType, offense.isHome)
		twoPtW, threePtW, foulW := gs.playBuckets(bh, offense, defense, hca, true)
		in := outcomeInputs{
			twoPtWeight:      twoPtW,
			threePtWeight:    threePtW,
			andOneWeight:     andOneBucketWeight(mq, bh),
			foulOnlyWeight:   foulW,
			turnoverDefValue: energyCeiling(bh),
		}

		switch selectOutcome(in, false, false, false, gs.rng) {
		case outcome2pt:
			if made, _ := gs.shotAttempt(offense, defense, bh, sv2, result.ShotTwoPoint, origin, periodIdx); !made {
				gs.creditBlock(offense, defense, bh, def)
				if cont, next := gs.rebound(offense, defense, periodIdx); cont {
					bh = next
					continue
				}
				return true // defensive rebound → fast-break pending
			}
			return false // made shot
		case outcome3pt:
			if made, _ := gs.shotAttempt(offense, defense, bh, shotValue3pt(), result.ShotThree, origin, periodIdx); !made {
				gs.creditBlock(offense, defense, bh, def)
				if cont, next := gs.rebound(offense, defense, periodIdx); cont {
					bh = next
					continue
				}
				return true // defensive rebound → fast-break pending
			}
			return false // made shot
		case outcomeAndOne:
			gs.madeFieldGoal(offense, bh, result.ShotTwoPoint, origin, periodIdx)
			gs.freeThrows(offense, defense, bh, def, 1, periodIdx)
			return false
		case outcomeFoulOnly:
			gs.freeThrows(offense, defense, bh, def, 2, periodIdx)
			return false
		case outcomeTurnover:
			// The negligible independent [2,5] check (energyCeiling): an UNFORCED
			// change of possession — no stealer, no fast break (the dominant
			// steal-driven turnover is handled by stealTurnover at the top of the trip).
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

// shotAttempt records a field-goal attempt of the given type, rolls make/miss,
// and scores points on a make. It emits the attempt event (the box-score
// Game2GA/Game3GA counters are derived from it by aggregateBoxes). It returns
// (made, ended): ended is always true for a single attempt; made distinguishes a
// basket from a miss so the caller can route a miss to the rebound phase.
func (gs *gameState) shotAttempt(offense, defense *teamState, shooter onCourt, shotValue float64, st result.ShotType, origin result.ShotOrigin, periodIdx int) (made, ended bool) {
	gs.emit(result.Event{
		Kind: result.EventShotAttempt, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: st, Origin: origin,
	})
	// FG make uses BASE stamina fatigue (per spec, distinct from the live-energy
	// outcome weights/selectors); under the current curve this is ≈1.0 anyway.
	if rollMake(shotValue, fatigueFactor(shooter.Stamina), gs.rng) {
		gs.creditMadeFieldGoal(offense, shooter, st, origin, periodIdx)
		return true, true
	}
	gs.emit(result.Event{
		Kind: result.EventShotMiss, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: st, Origin: origin,
	})
	return false, true
}

// madeFieldGoal records a guaranteed made 2pt (the and-one basket): it emits the
// attempt event then credits the make.
func (gs *gameState) madeFieldGoal(offense *teamState, shooter onCourt, st result.ShotType, origin result.ShotOrigin, periodIdx int) {
	gs.emit(result.Event{
		Kind: result.EventShotAttempt, Period: gs.period, Clock: gs.clock,
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: st, Origin: origin,
	})
	gs.creditMadeFieldGoal(offense, shooter, st, origin, periodIdx)
}

// creditMadeFieldGoal scores the points (live score, read for clutch/OT), bumps
// the live per-shooter made-FG tally (read by the block-probability penalty),
// and emits the make event. The box-score Game2GM/Game3GM counters are derived
// from that event by aggregateBoxes — no box row is written here.
func (gs *gameState) creditMadeFieldGoal(offense *teamState, shooter onCourt, st result.ShotType, origin result.ShotOrigin, periodIdx int) {
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
		TeamID: offense.teamID, PlayerID: shooter.PID, ShotType: st, Origin: origin,
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
	// ORB-continuation arm routes through the freeze wrapper (freeze.go); shared by
	// the half-court and transition rebound paths, so one site covers both.
	if gs.rng.Float64() < gs.orebProb(offStr, defStr) {
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
