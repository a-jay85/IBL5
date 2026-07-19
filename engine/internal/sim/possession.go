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

// playBuckets assembles the 2pt/3pt/foul play-outcome bucket weights for one
// attempt, threading the two HCA magnitudes (decompile :97157-97164, param_5==1):
//   - hcaScaled (= hca·hcaSite2BasisScale) is the SCALED site-2 made-shot addend
//     (leg A): +hcaScaled home / −hcaScaled away on the O(10s) 2pt bucket.
//   - hca (RAW ±0.2) feeds the foul bucket, where the base (leg B) and offQ (leg C)
//     carry the raw delta on the faithful CEngine TOV48 basis — see foulBucketWeight.
//
// Callers pass 0 for both on the transition path (param_5==0, fully symmetric) and
// for ASG (hcaDelta returns 0). The and-one leg D (e90 inherits e88's +hca) is added
// by the caller to andOneWeight, since that bucket is assembled outside playBuckets.
//
// allow3pt is false on a fast break (3pt path excluded).
//
// BranchB is an OFF-by-default diagnostic. When active it passes the foul weight
// through branchBShrink; acceptable because BranchB is not exercised by any shipped
// path, golden snapshot, or sign gate. BranchB and freeze arms are exclusive.
func (gs *gameState) playBuckets(bh onCourt, offense, defense *teamState, hca, hcaScaled, mq float64, allow3pt bool) (twoPtW, threePtW, foulW float64) {
	raw3pt := 0.0
	if allow3pt {
		raw3pt = threePtBucketWeight(bh)
	}
	if !gs.freeze.BranchB {
		foul := gs.foulWeight(bh, offense.players, defense.players, hca, mq)
		return twoPtBucketWeight(bh) + hcaScaled, raw3pt, foul
	}
	raw2pt := twoPtBucketWeight(bh)
	foul := foulBucketWeight(bh, offense.players, defense.players, hca, mq, gs.rng)
	s2, s3, sf := gs.branchBShrink(raw2pt, raw3pt, foul, offense.drbRate, offense.astRate, bh.TransOff)
	return s2 + hcaScaled, s3, sf
}

// possOutcome classifies how a possession ended, so the caller can route both
// the next possession's fast-break eligibility (fbPending, unchanged) and its
// step-class draw (J24 Phase 3+): a steal arms the FAST steal-transition class
// (0-2s, Phase 3), a defensive rebound arms the DRB-push class (2-4s, Phase 4),
// and everything else (a make or a non-arming turnover) draws the normal
// half-court jittered step.
type possOutcome int

const (
	possNormal possOutcome = iota // ended in make / non-arming turnover — no break armed
	possSteal                     // ended in a steal -> arms steal fast break (step 0-2)
	possDRB                       // ended in a defensive rebound -> arms DRB push (step 2-4, Phase 4)
)

// possession resolves one offensive trip: ball-handler selection, shot-type and
// matchup resolution, the play-outcome path, and any free throws or rebounds.
// Offensive rebounds continue the trip; a made shot, defensive rebound, or
// turnover ends it. The caller flips possession after every trip.
//
// prev is the outcome of the PRIOR possession (its defensive rebound, steal, or
// neither). Its fast-break eligibility (fbPending, derived below) is consumed
// unconditionally: when Stage 2 (TransOff trigger) and Stage 3 (steal-success)
// both pass, the possession runs as a fast break; otherwise it falls through to
// the normal half-court loop. The named return outcome re-arms possSteal or
// possDRB when THIS possession ends via a steal or a defensive rebound
// respectively (the team that gained the ball gets the next break); it also lets
// the caller (gameloop.go) route the NEXT possession's step-class draw off how
// THIS one ended.
func possession(gs *gameState, offense, defense *teamState, periodIdx int, prev possOutcome) (outcome possOutcome) {
	fbPending := prev != possNormal
	// Reset on EVERY call (not just the DRB-armed branch below) so a stale
	// true from a prior possession never leaks into this one, including the
	// empty-roster early return.
	gs.drbPushFired = false
	if len(offense.players) == 0 || len(defense.players) == 0 {
		return possNormal
	}
	gs.emit(result.Event{
		Kind: result.EventPossessionStart, Period: gs.period, Clock: gs.clock, TeamID: offense.teamID,
	})

	if fbPending {
		if gs.transitionShotRate <= 0 {
			gs.transitionShotRate = resetTransitionShotRate(offense)
		}
		// Draw the Stage-2 gate exactly ONCE and capture it for the DRB-push
		// clock class (J24 Phase 4) — re-drawing it in gameloop.go would pull a
		// second (starter-pick, rand_int(18)) pair off the RNG stream and let
		// the clock class disagree with whether the break actually ran.
		trig := transitionTriggers(offense, gs.gameType, gs.rng)
		if prev == possDRB {
			gs.drbPushFired = trig // DRB-push clock class (strategy_adj=0, J24 Phase 4)
		}
		if trig && gs.transitionStealSucceeds(defense) {
			return gs.runTransitionPossession(offense, defense, periodIdx)
		}
	}

	// defBlkSum is the defending lineup's cumulative DE8 (BLK/MIN×48), capped at
	// 1.5×5×leagueBlk48 when the sum exceeds that ceiling. Computed ONCE per
	// possession since the defending lineup is constant within a trip. When
	// gs.leagueBlk48==0 (unwired bundle), the cap forces defBlkSum=0 so
	// blockMod returns 0 — graceful no-op.
	var defBlkSum float64
	for _, dp := range defense.players {
		defBlkSum += dp.DE8
	}
	blkCap := 1.5 * 5 * gs.leagueBlk48
	if defBlkSum > blkCap {
		defBlkSum = blkCap
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
			return possSteal // steal → steal fast-break pending for the defense
		}
		// Independent (non-steal) turnover: carelessness only, no arming.
		// Checked after the dominant steal path; returns possNormal (no fast break).
		if gs.nonStealTurnover(offense, bh) {
			return possNormal
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
		offFlags := computeUsageDominanceFlags(offense.players)
		defFlags := computeUsageDominanceFlags(defense.players)
		mq := matchupQuality(bh, defense.players, gs.leagueAST48ByPos, offFlags, defFlags) // live usage-dominance flags (J26 Phase 4)

		// Make/foul/turnover arms route through the gameState freeze wrappers
		// (freeze.go): live values in the normal/baseline path, league-mean
		// substitutes when an arm is frozen for the ADR-0043 attribution.
		sv2 := applyClutch(gs.makeValue2pt(net, bh, mq, origin, gs.leagueBlk48, defBlkSum), bh.Clutch, gs.period, scoreDiff)
		// Home-court advantage (raw delta = +0.2 home / −0.2 away, 0 for ASG),
		// re-homed to all four half-court legs (J15 Phase 5, decompile :97157-97164):
		// the 2pt made-shot bucket (leg A) and and-one (leg D, below) take the SCALED
		// delta; the foul base (leg B) and offQ (leg C) take the RAW delta inside
		// foulBucketWeight. hcaScaled preserves the ~10% proportional made-bucket effect
		// across the O(10s) 2pt basis (hcaSite2BasisScale, gametype.go).
		hca := hcaDelta(gs.gameType, offense.isHome)
		hcaScaled := hca * hcaSite2BasisScale
		twoPtW, threePtW, foulW := gs.playBuckets(bh, offense, defense, hca, hcaScaled, mq, true)
		// Putback 3pt suppression (ADR-0055): a half-court OReb continuation is never a
		// 3pt attempt — 5.60 re-loops a 3pt outcome on the OReb flag forcing a 2pt
		// (decompile 94022-94024). Zero the 3pt bucket weight (same mechanism as
		// transition.go's allow3pt=false) so selectOutcome cannot pick outcome3pt. The
		// UnfaithfulPutback escape hatch leaves it reachable for the ADR-0055 OFF walk.
		if origin == result.OriginOffReb && !gs.freeze.UnfaithfulPutback {
			threePtW = 0
		}
		in := outcomeInputs{
			twoPtWeight:      twoPtW,
			threePtWeight:    threePtW,
			andOneWeight:     andOneBucketWeight(mq, bh) + hcaScaled, // leg D: e90 inherits e88's +hca
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
				return possDRB // defensive rebound → DRB-push fast-break pending
			}
			return possNormal // made shot
		case outcome3pt:
			if made, _ := gs.shotAttempt(offense, defense, bh, shotValue3pt(net, bh.D80, gs.shotBaselineOrFallback(), gs.leagueBlk48, defBlkSum), result.ShotThree, origin, periodIdx); !made {
				gs.creditBlock(offense, defense, bh, def)
				if cont, next := gs.rebound(offense, defense, periodIdx); cont {
					bh = next
					continue
				}
				return possDRB // defensive rebound → DRB-push fast-break pending
			}
			return possNormal // made shot
		case outcomeAndOne:
			gs.madeFieldGoal(offense, bh, result.ShotTwoPoint, origin, periodIdx)
			gs.freeThrows(offense, defense, bh, def, 1, periodIdx)
			return possNormal
		case outcomeFoulOnly:
			gs.freeThrows(offense, defense, bh, def, 2, periodIdx)
			return possNormal
		case outcomeTurnover:
			// The negligible independent [2,5] check (energyCeiling): an UNFORCED
			// change of possession — no stealer, no fast break (the dominant
			// steal-driven turnover is handled by stealTurnover at the top of the trip).
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
	// L1 gate-1 decomposition instrument (ADR-0057/0058): record the linear gate-2, the
	// sqrt gate-1 (the live continuation roll since ADR-0058), and their product BEFORE
	// the outcome roll. Read-only — issues no rng draw and is a no-op unless the instrument
	// is attached, so attaching it leaves the live outcome (the single gs.orebProb roll
	// below) unchanged and goldens stay byte-identical.
	gs.accumulateGateCont(offense.teamID, offStr, defStr)
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
