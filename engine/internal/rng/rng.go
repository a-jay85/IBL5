// Package rng provides a seedable, deterministic pseudo-random number generator
// for the JSB simulation engine.
//
// JSB itself used the MSVC C runtime rand() with no srand() call, so its runs
// were non-reproducible across invocations. This engine instead records a seed
// per run: the same seed always yields the same sequence, which lets any
// simulation be replayed exactly (for audit, regression tests, and bug repro).
package rng

import "math/rand/v2"

// RNG is a deterministic source of pseudo-randomness. Always construct via New;
// never reach for the package-global rand functions, so that engine output
// depends only on the recorded seed.
type RNG struct {
	src *rand.Rand
	rep *replay // non-nil only from NewReplay; nil keeps the PCG path byte-identical
}

// New returns an RNG seeded from the given seed. math/rand/v2's PCG is a
// stdlib, seedable, deterministic source. PCG takes two 64-bit words; we derive
// the second from the first (golden-ratio mix) so a single seed fully
// determines the stream.
func New(seed uint64) *RNG {
	return &RNG{src: rand.New(rand.NewPCG(seed, seed^0x9e3779b97f4a7c15))}
}

// Float64 returns a deterministic value in [0, 1).
func (r *RNG) Float64() float64 {
	if r.rep != nil {
		return r.rep.float64()
	}
	return r.src.Float64()
}

// IntN returns a deterministic value in [0, n). It panics if n <= 0, matching
// math/rand/v2.IntN semantics.
func (r *RNG) IntN(n int) int {
	if r.rep != nil {
		return r.rep.intN(n)
	}
	return r.src.IntN(n)
}

// Raw returns one draw in 0..RawMax, the range of JSB's rand(). A ported call
// site uses it to copy JSB's own arithmetic on the draw.
func (r *RNG) Raw() int {
	if r.rep != nil {
		return r.rep.raw()
	}
	return int(r.src.Uint32() >> 17)
}
