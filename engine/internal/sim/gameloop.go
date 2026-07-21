package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

const (
	quarterSeconds    = 720 // 12:00 regulation quarter
	otSeconds         = 300 // 5:00 overtime period
	regulationPeriods = 4
	maxOvertime       = 20 // hard ceiling so a tied game always terminates
)

// simGame plays one scheduled game: four regulation quarters plus overtime
// while tied, alternating possessions and decrementing the clock by a tempo-
// derived possession length. It returns the full event stream and box scores
// (visitor team first), the count of fast-break possessions that fired, and the
// two live teamStates — the latter two are internal observability for tests (the
// live quarter tally lets the conservation test cross-check the event-derived
// box), not part of the result contract.
func simGame(b bundle.Bundle, g bundle.Game, r *rng.RNG) (result.GameResult, int, *teamState, *teamState) {
	return simGameWith(b, g, r, Options{})
}

// resolveBaseTimeMid returns the constant base-time for this run: the package
// const baseTimeMid (tempo.go) when opts.BaseTimeMid is nil — byte-identical to the
// live engine — else the overridden value (the J23 mean-pace re-center sweep seam).
// A pure function so "resolves the const when nil" is a direct unit test, not
// through-the-sim.
func resolveBaseTimeMid(opts Options) float64 {
	if opts.BaseTimeMid != nil {
		return *opts.BaseTimeMid
	}
	return baseTimeMid
}

func resolveStealTurnoverScale(opts Options) float64 {
	if opts.StealTurnoverScale != nil {
		return *opts.StealTurnoverScale
	}
	return stealTurnoverScale
}
func resolveNonStealTurnoverScale(opts Options) float64 {
	if opts.NonStealTurnoverScale != nil {
		return *opts.NonStealTurnoverScale
	}
	return nonStealTurnoverScale
}

// simGameWith is simGame plus the freeze/accumulation Options (freeze.go). A zero
// Options leaves every possession decision byte-identical to simGame; a non-zero
// Options either harvests league-mean derived values (opts.Accum) or substitutes a
// frozen league mean at one or more mechanism arms (opts.Freeze).
func simGameWith(b bundle.Bundle, g bundle.Game, r *rng.RNG, opts Options) (result.GameResult, int, *teamState, *teamState) {
	visitor := newTeamState(b.Players, g.VisitorTeamID, false)
	home := newTeamState(b.Players, g.HomeTeamID, true)
	// Attach each team's Branch-B usage-shrink rates (bucketweights.go). A team absent
	// from b.Teams (DB-built bundle, or a test bundle with no Teams) leaves them 0 —
	// Branch-B inert. Reading b.Teams here is the only place the loop touches it.
	visitor.drbRate, visitor.astRate = teamRates(b, g.VisitorTeamID)
	home.drbRate, home.astRate = teamRates(b, g.HomeTeamID)

	gs := &gameState{rng: r, gameType: g.GameType, madeFG: map[int]int{}, freeze: opts.Freeze, accum: opts.Accum, branchB: opts.BranchBAccum, gateCont: opts.GateCont, fastClass: opts.FastClassAccum}
	// The L1 gate-1 baseline is league-constant, so resolve it ONCE per game. The live
	// faithful ORB roll (gs.orebProb) reads gs.gateBaseline on EVERY run (ADR-0058), so
	// this MUST populate unconditionally — a zero baseline biases the sqrt branch and
	// breaks ORB fidelity. The GateBaseline override (nil ⇒ bundle-derived) feeds both
	// the ADR-0058 archive baseline sweep and the counterfactual instrument.
	if opts.GateBaseline != nil {
		gs.gateBaseline = *opts.GateBaseline
	} else {
		gs.gateBaseline = leagueReboundBaseline(b)
	}
	// The shot baseline (league 2PA/48, CEngine+0x6638) is likewise league-
	// constant: it is assembled ONCE per snapshot at bundle-build time, over
	// raw .plr records 1-959 (backup.ToBundle's computeLeagueShotBaseline) —
	// NOT over the bundle's player list, which is a different, larger
	// population. A zero/unwired field (e.g. a hand-built test bundle) falls
	// back to leagueBaselineFallback via shotBaselineOrFallback below.
	gs.shotBaseline = b.LeagueShotBaseline
	gs.leagueBlk48 = b.LeagueBlk48
	gs.leagueAST48ByPos = b.LeagueAST48ByPos

	// base_time is CONSTANT per game in 5.60 — the composite ratio is dead code
	// (u = 0; tempo.go const block, J24 Phase 0). This retired the ADR-0042
	// roster-dependent teamBaseTime stand-in. baseTimeMid is the provisional
	// center until the Phase 5 GO installs the faithful 16.0. But base_time being
	// a per-game constant does NOT mean the possession step is: each possession
	// draws its OWN jittered length from it (FUN_004e42e0 half-court step class,
	// J24 Phase 2 — see possessionTime's docblock); the steal (Phase 3) and
	// DRB-push (Phase 4) fast classes widen the mix further, drawn off the
	// PRIOR possession's outcome below.
	baseTime := resolveBaseTimeMid(opts)
	gs.stealTurnoverScale = resolveStealTurnoverScale(opts)
	gs.nonStealTurnoverScale = resolveNonStealTurnoverScale(opts)

	// Tip-off winner starts on offense; possessions strictly alternate.
	offense, defense := visitor, home
	if r.IntN(2) == 1 {
		offense, defense = home, visitor
	}

	playPeriod := func(period, seconds int) {
		gs.period = period
		gs.clock = seconds
		gs.transitionShotRate = 0 // Stage-3 decay resets per period ("within a period")
		prevOutcome := possNormal
		for gs.clock > 0 {
			// Dead-ball substitution sweep for both teams before the possession
			// (foul-out / foul-trouble / fatigue). Zero RNG — see checkSubstitutions.
			checkSubstitutions(offense, period, gs.clock, gs.emit)
			checkSubstitutions(defense, period, gs.clock, gs.emit)

			// One-iteration offset: prevOutcome going INTO possession() this
			// iteration is what armed (or didn't) THIS possession's fast break —
			// so it's also what governs THIS possession's step class, drawn
			// below. The value RETURNED by possession() (how THIS one ended)
			// only takes effect as prevOutcome on the NEXT iteration.
			outcome := possession(gs, offense, defense, period-1, prevOutcome)

			// Per-possession step draw, routed by prevOutcome (the outcome that
			// armed THIS possession) and the per-possession gate flags
			// gs.drbPushFired / gs.stealPushFired (set by THIS iteration's
			// possession() call above — see its docblock and state.go's field
			// comments):
			//   - possSteal AND gs.stealPushFired: the possession followed a
			//     steal AND the Stage-2 transitionTriggers gate fired (captured
			//     once in possession() as gs.stealPushFired — §1d faithful per
			//     jsb-J24-arming-share-RE-20260717.md: the 5.60 binary arms
			//     +0x4be4 unconditionally for steals and runs the SAME gate as
			//     DRB) → code-7 {2,3,4}s, counted in DRBPushClass alongside
			//     DRB survivors (steal and DRB survivors share code 7).
			//   - possSteal AND NOT gs.stealPushFired: gate failed → half-court
			//     step (same as a failed-DRB possession).
			//   - prevOutcome == possDRB AND gs.drbPushFired: the possession
			//     followed a defensive rebound AND the shared Stage-2 gate fired
			//     → transition-push class, {2,3,4}s (FUN_004e42e0 code 7, J24
			//     Phase 4, strategy_adj=0 — see transition.go's transitionTriggers
			//     docblock). Steal and DRB survivors both count in DRBPushClass.
			//   - default (possNormal, possDRB or possSteal with gate failed):
			//     half-court jittered step (J24 Phase 2).
			//
			// Ordering note: possession() (called above, this same iteration)
			// already read prevOutcome to decide which flag to capture, so both
			// flags reflect exactly the possession whose step is being drawn
			// here — no additional offset beyond the existing one documented above.
			var step int
			switch {
			case prevOutcome == possSteal:
				// §1d faithful: steal-armed possessions run the SAME Stage-2
				// transitionTriggers gate as DRB (captured once in possession()
				// as gs.stealPushFired). Gate pass → code-7 {2,3,4}s, counted in
				// DRBPushClass alongside DRB survivors (steal & DRB survivors share
				// code 7 in the 5.60 binary). Gate fail → half-court step. The old
				// unconditional r.IntN(3) StealClass routing was the J24 §1d
				// wrong-class stand-in and is removed.
				if gs.stealPushFired {
					step = r.IntN(3) + 2 // code-7 {2,3,4}s (steal-sourced, gated — §1d faithful)
					if gs.fastClass != nil {
						gs.fastClass.DRBPushClass++
						gs.fastClass.TotalPossessions++
					}
				} else {
					step = possessionTime(baseTime, r) // gate failed → half-court
					if gs.fastClass != nil {
						gs.fastClass.HalfCourt++
						gs.fastClass.TotalPossessions++
					}
				}
			case gs.drbPushFired:
				step = r.IntN(3) + 2 // DRB push (code 7): {2,3,4}s
				if gs.fastClass != nil {
					gs.fastClass.DRBPushClass++
					gs.fastClass.TotalPossessions++
				}
			default:
				step = possessionTime(baseTime, r) // half-court jitter (Phase 2)
				if gs.fastClass != nil {
					gs.fastClass.HalfCourt++
					gs.fastClass.TotalPossessions++
				}
			}
			prevOutcome = outcome

			// Both fives were on the floor: drain on-court energy + accrue minutes,
			// recover the benches.
			offense.drainAndRecover(step)
			defense.drainAndRecover(step)

			offense, defense = defense, offense
			gs.clock -= step
		}
		gs.emit(result.Event{Kind: result.EventPeriodBoundary, Period: period, Clock: 0})
	}

	for period := 1; period <= regulationPeriods; period++ {
		if period == 3 { // halftime: full energy restore for both teams
			visitor.restoreFull()
			home.restoreFull()
		}
		playPeriod(period, quarterSeconds)
	}
	for ot := 1; ot <= maxOvertime && visitor.score == home.score; ot++ {
		playPeriod(regulationPeriods+ot, otSeconds)
	}

	visitor.finalizeMinutes()
	home.finalizeMinutes()

	// The box score is derived purely from the event stream, joined with each
	// team's roster metadata (PID/Pos set at construction, GameMIN by
	// finalizeMinutes). The live teamState.boxes carry only that metadata now.
	playerBoxes, teamBoxes := aggregateBoxes(gs.events, rosterMetaOf(visitor), rosterMetaOf(home))

	gr := result.GameResult{
		Date:          g.Date,
		HomeTeamID:    g.HomeTeamID,
		VisitorTeamID: g.VisitorTeamID,
		GameOfThatDay: 1,
		SimGameType:   int(g.GameType),
		Events:        gs.events,
		PlayerBoxes:   playerBoxes,
		TeamBoxes:     teamBoxes,
		Injuries:      aggregateInjuries(gs.events),
	}
	return gr, gs.transitions, visitor, home
}

// teamRates returns the team's per-48 DRB/AST rates from b.Teams (the Branch-B
// usage-shrink inputs), or (0, 0) when the team is absent — leaving Branch-B inert.
// Linear scan: b.Teams holds ≤ a league's worth of teams, looked up twice per game.
func teamRates(b bundle.Bundle, teamID int) (drbRate, astRate float64) {
	for _, t := range b.Teams {
		if t.TeamID == teamID {
			return t.DRBRate, t.ASTRate
		}
	}
	return 0, 0
}

// rosterMetaOf snapshots a team's roster metadata (identity, position, finalized
// minutes) in bundle order for the box aggregator.
func rosterMetaOf(t *teamState) rosterMeta {
	rm := rosterMeta{teamID: t.teamID, isHome: t.isHome, players: make([]playerMeta, 0, len(t.boxes))}
	for _, b := range t.boxes {
		rm.players = append(rm.players, playerMeta{PID: b.PID, Pos: b.Pos, GameMIN: b.GameMIN})
	}
	return rm
}
