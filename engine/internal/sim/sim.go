// Package sim is the JSB-compatible basketball simulation core: a half-court
// possession engine and game driver translated from the JSB 5.60 decompile
// (see 00_MASTER_REFERENCE.md). Each possession resolves through ball-handler
// selection → shot-type selection → position penalty → net advantage → matchup
// quality → play-outcome path → make/miss (or free throws) → rebound/turnover,
// emitting a per-possession event stream and accumulating box scores. The
// driver wraps possessions in four quarters plus overtime, with a fixed
// starting lineup and constant energy (fatigue ≈ 1.0).
//
// PR3a scope: half-court only. Steal/block attribution and the fast-break /
// transition system are PR3b; substitutions and energy drain are PR4; playoff
// and all-star modifiers are PR7. Where a JSB formula needs a per-game stat
// aggregate or league constant that does not exist before validation phase, the
// engine uses a documented rating-derived stand-in (see the per-file notes);
// probabilistic decisions reimplement the *probability* via the seedable PCG
// (rng) so a given seed always replays identically.
package sim

import (
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// Simulate runs the engine over every game in the bundle and returns a
// deterministic Result keyed to the given seed.
func Simulate(b bundle.Bundle, seed uint64) result.Result {
	r := rng.New(seed)
	res := result.Result{Seed: seed, Games: make([]result.GameResult, 0, len(b.Schedule))}
	for _, g := range b.Schedule {
		gr, _ := simGame(b, g, r)
		res.Games = append(res.Games, gr)
	}
	return res
}
