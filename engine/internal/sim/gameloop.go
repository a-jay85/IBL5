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

// resolveOffVolumeScale returns the offensive-volume scale for this run: the package
// const offVolumeScale (tempo.go) when opts.OffVolumeScale is nil — byte-identical to
// the live engine — else the overridden value (the ADR-0054 sweep seam). A pure
// function so "resolves the const when nil" is a direct unit test, not through-the-sim.
func resolveOffVolumeScale(opts Options) float64 {
	if opts.OffVolumeScale != nil {
		return *opts.OffVolumeScale
	}
	return offVolumeScale
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

	gs := &gameState{rng: r, gameType: g.GameType, madeFG: map[int]int{}, freeze: opts.Freeze, accum: opts.Accum, branchB: opts.BranchBAccum, gateCont: opts.GateCont}
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

	// One shared possession length per game: the average of the two teams' base
	// times (factor 1.0). Each team's base_time now carries its offensive volume
	// composite (the ADR-0042 volume→count channel, tempo.go). Under strict
	// alternation a shared-average step and a per-team step yield the SAME
	// possession COUNT per team (clock / avg(BT_v, BT_h)), so no per-possession
	// step is needed; the season-level FGA channel emerges because a high-volume
	// team's games average faster across its varied opponents.
	scale := resolveOffVolumeScale(opts)
	baseTime := (teamBaseTimeWith(visitor.players, scale) + teamBaseTimeWith(home.players, scale)) / 2.0
	step := possessionTime(baseTime)

	// Tip-off winner starts on offense; possessions strictly alternate.
	offense, defense := visitor, home
	if r.IntN(2) == 1 {
		offense, defense = home, visitor
	}

	playPeriod := func(period, seconds int) {
		gs.period = period
		gs.clock = seconds
		gs.transitionShotRate = 0 // Stage-3 decay resets per period ("within a period")
		pending := false
		for gs.clock > 0 {
			// Dead-ball substitution sweep for both teams before the possession
			// (foul-out / foul-trouble / fatigue). Zero RNG — see checkSubstitutions.
			checkSubstitutions(offense, period, gs.clock, gs.emit)
			checkSubstitutions(defense, period, gs.clock, gs.emit)

			pending = possession(gs, offense, defense, period-1, pending)

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
