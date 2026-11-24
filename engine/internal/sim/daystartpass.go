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

// dayStartScaledPass ports the once-per-sim-day league record pass. For each
// record, in order, it copies the block out, takes one scaled draw with that
// record's scale, truncates the draw toward zero, and writes the block back
// unchanged. It returns the written-back blocks and the truncated draws.
// The engine has no sim-day loop, so no game code calls it.
func dayStartScaledPass(blocks []dayStartBlock, scales []int, r *rng.RNG) ([]dayStartBlock, []int, error) {
	if len(blocks) != len(scales) {
		return nil, nil, fmt.Errorf("day-start pass: %d blocks but %d scales", len(blocks), len(scales))
	}
	back := make([]dayStartBlock, len(blocks))
	draws := make([]int, len(blocks))
	for i := range blocks {
		b := blocks[i]
		draws[i] = int(scaledDraw(r.Float64(), float64(scales[i])))
		back[i] = b
	}
	return back, draws, nil
}
