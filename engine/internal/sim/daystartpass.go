package sim

import (
	"fmt"

	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// dayStartBlockLen is the number of ints in the per-record block that the
// day-start pass copies out of each league record and writes back.
const dayStartBlockLen = 18

// dayStartBlock is one record's copied block.
type dayStartBlock [dayStartBlockLen]int

// dayStartScaledPass ports JSB's start-of-game record pass, whose draws come
// from FUN_005c6100. For each record, in order, it copies the block out, takes
// one raw draw scaled by that record's scale, truncates the draw toward zero,
// and writes the block back unchanged. It returns the written-back blocks and
// the truncated draws. It draws through r.Raw() so a replay logs api "raw",
// the form the JSB draw log matches. GameStartPass calls it at the head of every
// game cmd/jsbreplay replays; the live game sim does not call it.
func dayStartScaledPass(blocks []dayStartBlock, scales []int, r *rng.RNG) ([]dayStartBlock, []int, error) {
	if len(blocks) != len(scales) {
		return nil, nil, fmt.Errorf("day-start pass: %d blocks but %d scales", len(blocks), len(scales))
	}
	back := make([]dayStartBlock, len(blocks))
	draws := make([]int, len(blocks))
	for i := range blocks {
		b := blocks[i]
		draws[i] = int(scaledDraw(float64(r.Raw())/float64(rng.RawMax+1), float64(scales[i])))
		back[i] = b
	}
	return back, draws, nil
}

// GameStartPassDraws is the number of draws the game-start pass takes: one per
// roster slot, 2 teams x 30 slots, matching the 60 leading draws of every
// recorded game.
const GameStartPassDraws = 2 * 30

// GameStartPass runs dayStartScaledPass once on r at the start of a replayed
// game. The recorded values fix every draw, so it passes zero blocks and a fixed
// scale of 1 and discards the written-back blocks and truncated draws; the true
// per-slot scales live in JSB memory and do not change any draw value.
// Only cmd/jsbreplay calls it.
func GameStartPass(r *rng.RNG) error {
	blocks := make([]dayStartBlock, GameStartPassDraws)
	scales := make([]int, GameStartPassDraws)
	for i := range scales {
		scales[i] = 1
	}
	_, _, err := dayStartScaledPass(blocks, scales, r)
	return err
}
