package calibrate

// StandIn describes one perturbable engine parameter: why it is a stand-in
// (not a pin), what values to sweep, and how to apply a candidate value to a
// calibrate.Options so the research walk can exercise it. The registry is
// default-DENY / safe-by-omission (ADR-0087 §2): only explicitly registered
// stand-ins are swept; everything else is pinned by its package const.
type StandIn struct {
	ID            string                      // stable key, e.g. "base_time_mid"
	Term          string                      // fidelity term it is hypothesized to move
	Justification string                      // REQUIRED non-empty (ADR-0087 §2): why perturbable
	Sweep         []float64                   // candidate values (baseline first)
	Apply         func(o *Options, v float64) // writes the override pointer onto Options
}

// StandInRegistry returns the full set of currently-registered stand-ins.
// The list is the source of truth for the research walk (RunResearch) and its
// Justification CI gate (TestStandInRegistryJustified). To register a new
// stand-in: add an entry here with a non-empty Justification, at least two
// Sweep values (baseline first), and an Apply closure that sets the matching
// Options pointer. Do NOT register a stand-in without a Justification — the CI
// gate rejects it.
func StandInRegistry() []StandIn {
	ptr := func(v float64) *float64 { return &v }
	return []StandIn{
		{
			ID:   "non_steal_turnover_scale",
			Term: "non_steal_to_share",
			Justification: "nonStealTurnoverScale (steal.go) drives independent (non-steal) " +
				"turnovers. Calibrated to 0.00175 to target non-steal TO endings ≈ 4.9±0.5% of " +
				"possessions, but the independent-TO rate is harder to measure from the J3 corpus " +
				"(it conflates offensive fouls). Perturbable as a research lever — the harness " +
				"isolates how this constant moves the non-steal ending-share independently of the " +
				"steal rate.",
			Sweep: []float64{0.00175, 0.00140, 0.00210},
			Apply: func(o *Options, v float64) { o.NonStealTurnoverScale = ptr(v) },
		},
		{
			ID:   "base_time_mid",
			Term: "pace",
			Justification: "baseTimeMid (tempo.go) is the per-game constant possession-clock center " +
				"(ADR-0085). The live engine runs the FAITHFUL value 16.0: with u = CEngine+0x38 = 0.0 " +
				"the FUN_004e4150 composite base_time ratio is dead code, so base_time collapses to the " +
				"constant ceiling of the faithful [13,16] band — 16.0 coincides with baseTimeHigh by " +
				"construction. The J25 walkback from the provisional 17.7 happened on that faithfulness " +
				"proof alone, WITH THE FAST-CLASS ARMING-SHARE GAP STILL OPEN — this constant is not a " +
				"lever on that gap (measured; see the 'Do NOT re-open' list in " +
				"docs/backlog/jsb-native-backlog.md). The sweep baseline is therefore the shipped 16.0, " +
				"bracketed symmetrically inside the faithful band by 13.65 (the retired J23 center) and " +
				"above it by 17.7 (the retired provisional), so the bracket still spans a pace range " +
				"wide enough to clear noise. Perturbable as a research lever — the harness reproduces " +
				"the direction and rough magnitude of the archive sweep (PR #1495) as a self-validation " +
				"arm (ADR-0087 §4 base_time arm); that arm only requires some pace sweep point above " +
				"noise, which this 13.65/17.7 bracketing of 16.0 satisfies.",
			Sweep: []float64{16.0, 13.65, 17.7},
			Apply: func(o *Options, v float64) { o.BaseTimeMid = ptr(v) },
		},
	}
}
