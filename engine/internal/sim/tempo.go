package sim

// tempoFactor is the JSB "Gameplay Adjustment Factor" (CEngine+0x63b8). IBL runs
// it at 1.0 (confirmed from IBL5.lge), so possession_time == base_time — neutral
// NBA pace. See 00_MASTER_REFERENCE.md "Gameplay Adjustment Factor / Tempo".
const tempoFactor = 1.0

// base_time clamp bounds (00_MASTER_REFERENCE.md: hard-clamped to [13.0, 16.0]).
const (
	baseTimeLow  = 13.0
	baseTimeHigh = 16.0
)

// teamBaseTime derives a per-team base possession length in [13,16] seconds.
//
// In JSB this is a ratio of team per-game offensive/defensive stat aggregates,
// which PR3a does not have yet (validation-phase pin). As a stand-in we map the
// team's average defensive ODPT composite onto the clamp range: a stronger
// defense lengthens possessions (slower pace), a weaker one shortens them. The
// [13,16] clamp and the (2.0−factor) form are the exact, confirmed parts.
func teamBaseTime(starters []onCourt) float64 {
	if len(starters) == 0 {
		return baseTimeLow
	}
	var sum float64
	for _, p := range starters {
		// OD+DD+PD+TD, each on the 1-9 ODPT scale → defender composite 0..36.
		sum += float64(p.OD + p.DD + p.PD + p.TD)
	}
	avg := sum / float64(len(starters)) // 0..36
	bt := baseTimeLow + (avg/36.0)*(baseTimeHigh-baseTimeLow)
	if bt < baseTimeLow {
		bt = baseTimeLow
	}
	if bt > baseTimeHigh {
		bt = baseTimeHigh
	}
	return bt
}

// possessionTime is the seconds one possession removes from the game clock:
// (2.0 − factor) × base_time. At factor 1.0 it equals base_time.
func possessionTime(baseTime float64) int {
	pt := (2.0 - tempoFactor) * baseTime
	if pt < 1.0 || pt > 24.0 {
		pt = 24.0 // JSB out-of-range fallback
	}
	return int(pt)
}
