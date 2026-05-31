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

// onCourt is one starter plus the per-game values PR3a holds constant. Energy
// equals base stamina (no drain/recovery until PR4), so fatigue is effectively
// 1.0 for every player all game.
type onCourt struct {
	bundle.Player
	slot    int
	fatigue float64
}

// teamState is one team's live game state: its five starters, running score,
// quarter splits, and a box-score row per rostered player (including DNPs).
type teamState struct {
	teamID   int
	isHome   bool
	players  []onCourt           // starters, ordered by slot (1..5); may be < 5
	boxes    []*result.PlayerBox // one per rostered player, in bundle order
	byPID    map[int]*result.PlayerBox
	score    int
	quarters []int // points per period in order; index 0 = Q1
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
	rng    *rng.RNG
	period int // 1-based; 1..4 regulation, 5+ overtime
	clock  int // seconds remaining in the current period
	events []result.Event

	// transitionShotRate is the Stage-3 decaying team shot-rate threshold for
	// fast-break steal-success (00_MASTER_REFERENCE.md L900-914). It is seeded
	// once per period on the first possession that carries the fast-break pending
	// flag (before Stage 2/3 are tested) and decays by transitionShotRateDecay on
	// each successful break (floor transitionShotRateFloor), so fast-break
	// frequency falls as the period progresses. playPeriod resets it to 0 at the
	// top of each period.
	transitionShotRate float64

	// transitions counts fast-break possessions that actually fired this game
	// (Stage 2 and Stage 3 both passed). It is internal observability for tests;
	// it is never serialized into the result contract.
	transitions int
}

func (g *gameState) emit(e result.Event) { g.events = append(g.events, e) }

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
