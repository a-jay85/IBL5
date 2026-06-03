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
	res, _ := SimulateWith(b, seed, Options{}) // zero Options never errors (validate is a no-op)
	return res
}

// SimulateWith is Simulate plus a freeze/accumulation Options (freeze.go): the
// empty-FGA source-isolation diagnostic uses it to harvest league-mean derived
// values (Options.Accum) and to re-run with one or more mechanism arms frozen
// (Options.Freeze). A zero Options is byte-identical to Simulate. It returns an
// error only when the config freezes an arm with no precomputed mean.
func SimulateWith(b bundle.Bundle, seed uint64, opts Options) (result.Result, error) {
	if err := opts.validate(); err != nil {
		return result.Result{}, err
	}
	r := rng.New(seed)
	res := result.Result{Seed: seed, Games: make([]result.GameResult, 0, len(b.Schedule))}
	for _, g := range b.Schedule {
		gr, _, _, _ := simGameWith(b, g, r, opts)
		res.Games = append(res.Games, gr)
	}
	return res, nil
}
