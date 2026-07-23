package calibrate

// StandIn describes one perturbable engine parameter: why it is a stand-in
// (not a pin), what values to sweep, and how to apply a candidate value to a
// calibrate.Options so the research walk can exercise it. The registry is
// default-DENY / safe-by-omission (ADR-0087 §2): only explicitly registered
// stand-ins are swept; everything else is pinned by its package const.
type StandIn struct {
	ID            string                      // stable key, e.g. "steal_turnover_scale"
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
			ID:   "steal_turnover_scale",
			Term: "steal_share",
			Justification: "stealTurnoverScale (steal.go) is the dominant per-possession steal " +
				"probability coefficient. Calibrated from the J3 corpus target (8.9 steals/team " +
				"→ 1.69e-5) but plausibly off by ±15%: the archive gate is 17.8±0.7/g (both " +
				"teams) and the ending-mix steal-share gate is [8.0%, 9.0%]. Perturbable as a " +
				"research lever — the harness can quantify how much this scale moves the steal " +
				"ending-share and the TO-rate fidelity terms without re-running the full calibration.",
			Sweep: []float64{1.69e-5, 1.5e-5, 1.85e-5},
			Apply: func(o *Options, v float64) { o.StealTurnoverScale = ptr(v) },
		},
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
				"(ADR-0085, J23 re-center sweep). The live engine runs the PROVISIONAL value 17.7 " +
				"(J24 Phase 5 re-center, deliberately above the faithful [13,16] band per tempo.go); " +
				"the provisional center is expected to walk back toward the faithful 16.0 when that " +
				"arm closes. The sweep baseline is therefore the current operational 17.7, bracketed " +
				"by the faithful floor 16.0 and an upper 19.0. Perturbable as a research lever — the " +
				"harness reproduces the direction and rough magnitude of the archive sweep (PR #1495) " +
				"as a self-validation arm (ADR-0087 §4 base_time arm); that arm only requires some " +
				"pace sweep point above noise, which any reasonable bracketing of 17.7 satisfies.",
			Sweep: []float64{17.7, 16.0, 19.0},
			Apply: func(o *Options, v float64) { o.BaseTimeMid = ptr(v) },
		},
	}
}
