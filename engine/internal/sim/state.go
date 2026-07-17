package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// Lineup slot numbers (1=PG … 5=C), the JSB "position slot" that three
// mechanics read (shot-type selection, ball-handler selection, position
// penalty). See 00_MASTER_REFERENCE.md "What IS and IS NOT Slot-Dependent".
const (
	slotPG = 1
	slotSG = 2
	slotSF = 3
	slotPF = 4
	slotC  = 5
)

// playType is the offensive play the shot-type selector picks. It selects which
// ODPT pair drives the net advantage; it is NOT the 2pt-vs-3pt distinction
// (that is decided later, as buckets 1 vs 2 of the play-outcome selector).
type playType int

const (
	playOutside playType = iota // OO vs OD
	playDrive                   // DO vs DD
	playPost                    // PO vs PD
)

// onCourt is one player currently on the floor plus the per-game live values.
// energy is the player's current energy (drained on court, recovered on the
// bench); fatigue is fatigueFactor(energy), refreshed each possession. Under the
// committed fatigue curve fatigue is identically 1.0 for any energy ≥ 0 and
// clamps to 1.0 for negative energy too — see fatigueFactor — so live energy is
// behaviorally inert today, but the wiring is faithful for when the curve is
// repaired.
type onCourt struct {
	bundle.Player
	slot    int
	energy  int
	fatigue float64
}

// teamState is one team's live game state: its current on-court players, running
// score, quarter splits, a box-score row per rostered player (including DNPs),
// and the live energy/minutes/foul-out bookkeeping the substitution system reads.
type teamState struct {
	teamID  int
	isHome  bool
	players []onCourt           // on-court players, ordered by slot (1..5); may be < 5
	boxes   []*result.PlayerBox // one per rostered player, in bundle order
	byPID   map[int]*result.PlayerBox

	// roster is the team's eligible players (TeamID match, dc_can_play_in_game
	// != 0), in bundle order — the candidate pool for substitution backups.
	// playerByPID indexes it for O(1) sub-in lookups.
	roster      []bundle.Player
	playerByPID map[int]bundle.Player

	// energy/minutes are keyed by PID for every eligible player (on court or
	// bench). energy may go negative (drain is unfloored); minutes accumulates
	// on-court seconds and is finalized into GameMIN at game end. fouledOut marks
	// players who hit the 6th foul and can never re-enter. fouls is the live
	// per-player personal-foul tally read by checkSubstitutions for foul-out /
	// foul-trouble decisions — decision state kept live (like the score), NOT the
	// output: the box GamePF is derived from EventFoul by aggregateBoxes. injured
	// mirrors fouledOut: players hurt mid-game who can never re-enter (set by
	// maybeInjure, read by checkSubstitutions for permanent removal). No
	// games-missed is stored — EventInjury carries it and the Injuries slice
	// derives from the event stream, so the boolean is all removal needs.
	energy    map[int]float64
	minutes   map[int]float64
	fouledOut map[int]bool
	injured   map[int]bool
	fouls     map[int]int

	score    int
	quarters []int // points per period in order; index 0 = Q1

	// drbRate/astRate are the offensive team's per-48 defensive-rebound and assist
	// rates (bundle.Team.DRBRate/ASTRate), reached at the play-outcome bucket sites
	// for the JSB Branch-B usage-shrink (bucketweights.go). Default 0 — a team absent
	// from bundle.Teams, the DB-built bundle, or any test-constructed teamState leaves
	// them 0, so Branch-B is inert (Branch-A cold-start) there. Set in simGameWith.
	drbRate float64
	astRate float64
}

// addPeriodPoints credits n points to the team in the current 0-based period
// index, growing the quarter slice as overtime periods are reached.
func (t *teamState) addPeriodPoints(periodIdx, n int) {
	for len(t.quarters) <= periodIdx {
		t.quarters = append(t.quarters, 0)
	}
	t.quarters[periodIdx] += n
	t.score += n
}

// gameState threads the per-game clock, period, event stream, and RNG through
// the possession loop.
type gameState struct {
	rng      *rng.RNG
	gameType bundle.GameType // read-only; gates playoff (net×1.25, fast-break special_sub). Never serialized.
	period   int             // 1-based; 1..4 regulation, 5+ overtime
	clock    int             // seconds remaining in the current period
	events   []result.Event

	// madeFG is the live per-shooter made-field-goal tally, keyed by PID. It is
	// decision state (the block-probability penalty reads it — block.go), kept
	// live like the score, NOT part of the output contract. The box score's
	// Game2GM/Game3GM are now derived from the event stream by aggregateBoxes.
	madeFG map[int]int

	// transitionShotRate is the Stage-3 decaying team shot-rate threshold for
	// fast-break steal-success (00_MASTER_REFERENCE.md L900-914). It is seeded
	// once per period on the first possession that carries the fast-break pending
	// flag (before Stage 2/3 are tested) and decays by transitionShotRateDecay on
	// each successful break (floor transitionShotRateFloor), so fast-break
	// frequency falls as the period progresses. playPeriod resets it to 0 at the
	// top of each period.
	transitionShotRate float64

	// drbPushFired reports, for the possession about to be stepped, whether the
	// Stage-2 transitionTriggers gate fired on a DRB-armed possession (prev ==
	// possDRB) THIS iteration (J24 Phase 4, FUN_004e42e0 code 7). possession()
	// captures the gate result ONCE (in its fbPending branch — the same draw
	// that decides whether the possession runs as a transition break) rather
	// than gameloop.go re-evaluating transitionTriggers, which would draw a
	// second (starter-pick, rand_int(18)) pair and desync the step class from
	// the run decision. gameloop.go reads this flag to route the DRB-push clock
	// step ({2,3,4}s) instead of re-drawing the gate. Reset to false at the top
	// of EVERY possession() call so a stale true never leaks into a later
	// iteration or a non-DRB-armed possession.
	drbPushFired bool

	// transitions counts fast-break possessions that actually fired this game
	// (Stage 2 and Stage 3 both passed). It is internal observability for tests;
	// it is never serialized into the result contract.
	transitions int

	// freeze / accum drive the empty-FGA source-isolation diagnostic (freeze.go,
	// ADR-0043). freeze is the per-arm counterfactual config (zero value = live
	// engine); accum, when non-nil, harvests league-mean derived values during a
	// no-freeze baseline pass. Both are internal observability — never serialized.
	freeze FreezeConfig
	accum  *FreezeAccum

	// branchB, when non-nil, harvests the Branch-B engagement instrument (freeze.go
	// branchBShrink, ADR-0048) across the run: possessions where the usage-shrink
	// engaged vs fell back to Branch-A, plus the s distribution. Shared across a run's
	// games (like accum); nil outside the Phase-7 A/B harness. Internal, never serialized.
	branchB *BranchBAccum
	// fastClass, when non-nil, receives per-class step-count increments for
	// the J24 fast-class arming-share instrument (freeze.go FastClassAccum).
	// A nil pointer (a plain run) is a no-op at all three nil-guard sites in
	// gameloop.go's step-routing switch — no rng draw, no state mutation beyond
	// the counter fields. Internal, never serialized.
	fastClass *FastClassAccum

	// gateCont, when non-nil, harvests the L1 gate-1 decomposition instrument
	// (freeze.go accumulateGateCont, ADR-0057/0058): per offensive-rebound resolution,
	// the gate-1 (live since ADR-0058) / linear gate-2 / product, keyed by offensive team.
	// gateBaseline is gate-1's league-baseline term, set once per game (ADR-0058: read by
	// the live faithful ORB roll on EVERY run) from opts.GateBaseline, else
	// leagueReboundBaseline. Both internal, never serialized; nil gateCont (a zero Options)
	// leaves the read-only instrument inert.
	gateCont     *GateContAccum
	gateBaseline float64

	// shotBaseline is the league 2PA-per-48-player-minutes shot baseline
	// (CEngine+0x6638), copied ONCE per game from bundle.Bundle.LeagueShotBaseline
	// (gameloop.go) — assembled at bundle-build time over raw .plr records
	// (backup.ToBundle's computeLeagueShotBaseline), league-constant within a
	// snapshot, like gateBaseline. It feeds shotValue2pt's net term and
	// shotValue3pt (= baseline×1.5).
	shotBaseline float64
}

func (g *gameState) emit(e result.Event) { g.events = append(g.events, e) }

// shotBaselineOrFallback returns the per-game league 2PA/48 shot baseline, or
// leagueBaselineFallback when unset — a zero-value gameState constructed
// directly (as unit tests do), OR a bundle whose LeagueShotBaseline was never
// wired (a hand-built test bundle, or computeLeagueShotBaseline finding no
// qualifying raw records). This guards the shotValue2pt zero divisor: an
// unset baseline must degrade to the documented constant, never to ±Inf
// make-values.
func (g *gameState) shotBaselineOrFallback() float64 {
	if g.shotBaseline > 0 {
		return g.shotBaseline
	}
	return leagueBaselineFallback
}

// fatigueFactor is the shared JSB fatigue curve: (energy/30 + 100) × 0.01,
// capped at 1.0. Because PR3a energy is non-negative base stamina, this is
// always 1.0 — but the formula is implemented faithfully so PR4 (energy drain)
// drops in without changing callers.
func fatigueFactor(energy int) float64 {
	if energy < 0 {
		energy = 0
	}
	f := (float64(energy)/30.0 + 100.0) * 0.01
	if f > 1.0 {
		f = 1.0
	}
	return f
}
