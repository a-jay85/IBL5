package sim

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// --- matrix #6: a block credits the contesting defender ----------------------

func TestCreditBlock_CreditsDefenderOnMiss(t *testing.T) {
	for seed := uint64(1); seed < 500; seed++ {
		offense, defense := twoTeams()
		shooter := offense.players[0]
		defender := defense.players[0]
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}

		score0 := offense.score
		made0 := offense.box(shooter.PID).Game2GM
		before := len(gs.events)
		gs.creditBlock(offense, defense, shooter, defender)

		if defense.box(defender.PID).GameBLK == 0 {
			continue // this seed did not roll a block; try the next
		}

		// A block neither scores points nor invents a make.
		if offense.score != score0 {
			t.Errorf("score changed on a block: %d → %d", score0, offense.score)
		}
		if offense.box(shooter.PID).Game2GM != made0 {
			t.Error("block altered the shooter's made-FG count")
		}
		var blocks []result.Event
		for _, e := range gs.events[before:] {
			if e.Kind == result.EventBlock {
				blocks = append(blocks, e)
			}
		}
		if len(blocks) != 1 {
			t.Fatalf("expected 1 EventBlock, got %d", len(blocks))
		}
		ev := blocks[0]
		if ev.TeamID != offense.teamID || ev.PlayerID != shooter.PID || ev.DefenderID != defender.PID {
			t.Errorf("EventBlock = %+v, want TeamID=%d PlayerID=%d DefenderID=%d",
				ev, offense.teamID, shooter.PID, defender.PID)
		}
		if defense.box(ev.DefenderID).GameBLK != 1 {
			t.Errorf("blocker GameBLK = %d, want 1", defense.box(ev.DefenderID).GameBLK)
		}
		return
	}
	t.Fatal("no seed in range produced a block")
}

// --- matrix #7: a block never flips a make → miss ----------------------------
//
// creditBlock only ever touches the defender's GameBLK and the event stream; it
// is gated by the caller to fire solely on a missed field goal. This verifies it
// cannot add/remove a made shot or change the score even when forced to fire.
func TestCreditBlock_NeverFlipsMake(t *testing.T) {
	offense, defense := twoTeams()
	shooter := offense.players[0]
	defender := defense.players[0]
	// Pre-credit a made shot, as the half-court loop would on a make.
	gs := &gameState{rng: rng.New(1), period: 1, clock: 500}
	gs.creditMadeFieldGoal(offense, shooter, result.ShotTwoPoint, 0)
	made0 := offense.box(shooter.PID).Game2GM
	score0 := offense.score

	// Force a block by looping seeds; assert the make and score are untouched.
	for seed := uint64(1); seed < 500; seed++ {
		gs.rng = rng.New(seed)
		gs.creditBlock(offense, defense, shooter, defender)
		if defense.box(defender.PID).GameBLK > 0 {
			break
		}
	}
	if offense.box(shooter.PID).Game2GM != made0 {
		t.Errorf("made-FG count changed: %d → %d", made0, offense.box(shooter.PID).Game2GM)
	}
	if offense.score != score0 {
		t.Errorf("score changed: %d → %d", score0, offense.score)
	}
}

// --- matrix #8: a free-throw miss is never block-eligible --------------------
//
// The free-throw path (gs.freeThrows) must never credit a block or emit an
// EventBlock — blocks fire only on 2pt/3pt misses.
func TestFreeThrows_NeverBlocked(t *testing.T) {
	for seed := uint64(1); seed <= 100; seed++ {
		offense, defense := twoTeams()
		shooter := offense.players[0]
		defender := defense.players[0]
		gs := &gameState{rng: rng.New(seed), period: 1, clock: 500}
		gs.freeThrows(offense, defense, shooter, defender, 2, 0)
		for _, e := range gs.events {
			if e.Kind == result.EventBlock {
				t.Fatalf("seed %d: free-throw path emitted an EventBlock", seed)
			}
		}
		for _, pb := range append(offense.playerBoxes(), defense.playerBoxes()...) {
			if pb.GameBLK != 0 {
				t.Fatalf("seed %d: free-throw path credited a block", seed)
			}
		}
	}
}

// --- matrix #9: P(block) floored ≥ 0 -----------------------------------------

func TestBlockProbability_FlooredAndBounded(t *testing.T) {
	b := richBundle()
	blocker := newTeamState(b.Players, 3, true).players[0] // BLK = 20

	// Cold shooter: positive but ≤ ceiling.
	p0 := blockProbability(blocker, 0)
	if p0 <= 0 || p0 > blockFraction {
		t.Errorf("blockProbability(0 made) = %v, want (0, %v]", p0, blockFraction)
	}
	// Hot shooter: the per-made-FG penalty drives it to exactly 0, never negative.
	if p := blockProbability(blocker, 1000); p != 0 {
		t.Errorf("blockProbability(1000 made) = %v, want 0 (floored)", p)
	}
	// A higher made-FG count never raises the probability.
	if blockProbability(blocker, 5) > blockProbability(blocker, 0) {
		t.Error("made-FG penalty must not increase P(block)")
	}
	// An unrated blocker contests at 0.
	var unrated onCourt
	unrated.BLK = 0
	unrated.fatigue = 1.0
	if p := blockProbability(unrated, 0); p != 0 {
		t.Errorf("blockProbability(BLK=0) = %v, want 0", p)
	}
}
