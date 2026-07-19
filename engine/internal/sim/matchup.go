package sim

// matchupQuality reimplements the deterministic 4-phase Matchup Quality
// Calculator (FUN_004e3860). Its output feeds the play-outcome selector as the
// "net advantage" that drives the and-one bucket weight.
//
// J24 Phase 3/4 note: Phase 3 sums per-game defender aggregates — a MATCHED
// term (struct +0xDC8, keyed on the ball-handler's covered slot) and a static
// per-depth-chart NON-MATCHED term (struct +0x350, bundle-baked NonMatchedTerm,
// live as of J25 — backup/assemble.go computeNonMatchedTerm) — and Phase 4
// adds the coaching-gated accumulator (CEngine+0x33F0, still stubbed to 0
// pending the .lge +0x12c strategy-field pin — see the phase4Accumulator
// comment below).
func matchupQuality(bh onCourt, defenders []onCourt, leagueAST48ByPos [6]float64) float64 {
	// Phase 1 — rating normalization (composite defaults to 50 when zero).
	composite := bh.FGP
	if composite <= 0 {
		composite = 50
	}
	normalized := float64(composite)*0.2 - 9.9

	// Phase 2 — fatigue factor (the ball-handler's live energy).
	fatigue := fatigueFactor(bh.energy)

	// Phase 3 — defender loop (RE §5). The MATCHED defender (covering the ball-
	// handler's slot, +0xDC8) contributes (DefAST48 − leagueAST48[pos])·0.8·fatigue;
	// every NON-MATCHED defender contributes its static per-depth-chart +0x350
	// (bundle-baked NonMatchedTerm), scaled by fatigue. teamWeight stays 1.0 (no
	// §6 pin scales the non-matched arm; the accumulated shape is preserved).
	//
	// Faithfulness divergence (adjudicated at PR review, auto_merge:false): the RE
	// artifact's matched slot is param_3 = pass-target slot (FUN_004e2ad0), not the
	// ball-handler's own slot. FUN_004e2ad0 is unported (no pass-target model exists
	// in the sim); both callers already pick the matched defender via
	// defenderAtSlot(defense, bh.slot), so bh.slot is the consistent proxy here. The
	// term (defAST48[s] − leagueAST48[s])·0.8 is mean-zero in expectation for any slot
	// s, so the proxy cannot materially shift league-average FG%. The non-matched arm
	// is LIVE as of J25 (NonMatchedTerm = the FUN_00561c00 +0x350 bake, computed at
	// bundle-assembly time — backup/assemble.go computeNonMatchedTerm). The artifact's
	// PG-slot weight-gate (weight=0 if param_1==1) and skip-self remain unported:
	// param_1 (possession-context flag) and the pass-target model (FUN_004e2ad0) have
	// no sim counterpart; teamWeight=1.0 keeps every non-matched defender weighted,
	// the same adjudicated divergence class as the bh.slot proxy above.
	var accumulated float64
	const teamWeight = 1.0    // no §6 .rdata pin overrides 1.0
	const matchedWeight = 0.8 // §6 pin 0x669E78
	for _, d := range defenders {
		if d.slot == bh.slot {
			accumulated += (d.DefAST48 - leagueAST48ByPos[d.slot]) * matchedWeight * fatigue
		} else {
			accumulated += d.NonMatchedTerm * fatigue * teamWeight
		}
	}

	// Phase 4 — CEngine+0x33F0 accumulator (FUN_004e45a0, positive-only, strategy
	// ==4/==3/==2 gate). Stubbed to 0 pending the .lge +0x12c strategy-field offset
	// pin — J24 residual (4). This is the same faithful strategy_adj=0 degrade the
	// engine already applies at transition.go:55-61 / tempo.go:39 for the unpinned
	// +0x12c. Phase 3 (this PR) alone moves FG% into [47.5%, 48.9%]; Phase 4 is
	// additive-only and does not gate acceptance.
	const phase4Accumulator = 0.0
	return (accumulated + phase4Accumulator - normalized) * 0.2
}
