package sim

// matchupQuality reimplements the deterministic 4-phase Matchup Quality
// Calculator (FUN_004e3860). Its output feeds the play-outcome selector as the
// "net advantage" that drives the and-one bucket weight.
//
// PR3a note: Phase 3 sums per-game defender aggregates (struct +0x350 / +0xDC8)
// and Phase 4 adds the coaching-gated accumulator (CEngine+0x33F0). Neither is
// available before PR4/PR-coaching, so both contribute 0 here, leaving
// result = (0 + 0 − normalized) × 0.2 = −normalized × 0.2. The full four-phase
// shape is implemented so later PRs populate the aggregates without changing
// callers. The default-50 composite → 0.1 normalized behavior is exact.
func matchupQuality(composite, energy int, defenders []onCourt) float64 {
	// Phase 1 — rating normalization (composite defaults to 50 when zero).
	if composite <= 0 {
		composite = 50
	}
	normalized := float64(composite)*0.2 - 9.9

	// Phase 2 — fatigue factor (1.0 in PR3a; energy = base stamina).
	fatigue := fatigueFactor(energy)

	// Phase 3 — defender loop. Per-game aggregates are 0 in PR3a, so each
	// contribution is 0; the loop preserves the real structure.
	var accumulated float64
	const teamWeight = 1.0
	for range defenders {
		const perGameAggregate = 0.0 // +0x350 / +0xDC8 unavailable pre-PR4
		accumulated += perGameAggregate * fatigue * teamWeight
	}

	// Phase 4 — final calibration. CEngine+0x33F0 accumulator is 0 in PR3a.
	const phase4Accumulator = 0.0
	return (accumulated + phase4Accumulator - normalized) * 0.2
}
