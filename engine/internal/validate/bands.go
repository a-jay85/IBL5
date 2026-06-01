package validate

// Band is one stat's tolerance: a value passes when it lies within
// max(AbsFloor, RelPct×engineMean) of the observed engine mean (see compareStat).
type Band struct {
	RelPct   float64 // relative tolerance as a fraction of the engine mean
	AbsFloor float64 // absolute floor so small means still get a usable band
}

// bands is the SINGLE source of truth for tolerance calibration — one named,
// commented entry per compared stat. Edit ONLY this table to recalibrate.
//
// ┌──────────────────────────────────────────────────────────────────────────┐
// │ STARTING BANDS — NOT AUTHORITATIVE. These ±15% relative / per-stat floor   │
// │ values are a defensible placeholder, NOT grounded in measured corpus       │
// │ variance. They MUST be calibrated by running `jsbvalidate` against the     │
// │ real 5.60 corpus and widening/tightening each band to absorb (a) the       │
// │ single-.sco-draw sampling noise (one .sco line is one draw from            │
// │ jumpshot.exe's own distribution) AND (b) the engine-vs-jumpshot model gap, │
// │ WITHOUT making any band so wide it can never fail. Until calibrated, the   │
// │ tagged corpus suite is a DIAGNOSTIC, not a merge gate. See OPEN #1 in the  │
// │ PR9b plan.                                                                 │
// │                                                                            │
// │ Note also: the backup-driven sim runs with no per-player minutes/stamina   │
// │ signal (the .plr carries none — see backup.ToBundle), so the engine-side   │
// │ distribution is wider than a DB-driven sim's; the bands must absorb that   │
// │ too.                                                                       │
// └──────────────────────────────────────────────────────────────────────────┘
var bands = map[string]Band{
	"points": {RelPct: 0.15, AbsFloor: 8},
	"fgm":    {RelPct: 0.15, AbsFloor: 5},
	"fga":    {RelPct: 0.15, AbsFloor: 6},
	"ftm":    {RelPct: 0.15, AbsFloor: 3},
	"fta":    {RelPct: 0.15, AbsFloor: 4},
	"tgm":    {RelPct: 0.15, AbsFloor: 3},
	"tga":    {RelPct: 0.15, AbsFloor: 4},
	"reb":    {RelPct: 0.15, AbsFloor: 4},
	"ast":    {RelPct: 0.15, AbsFloor: 4},
	"stl":    {RelPct: 0.15, AbsFloor: 3},
	"tov":    {RelPct: 0.15, AbsFloor: 3},
	"blk":    {RelPct: 0.15, AbsFloor: 3},
	"pf":     {RelPct: 0.15, AbsFloor: 4},
}

// bandFor returns the configured band for a stat, falling back to a generic
// ±15%/floor-3 band for any stat not explicitly listed (defensive — every
// statNames entry has an explicit band above).
func bandFor(name string) Band {
	if b, ok := bands[name]; ok {
		return b
	}
	return Band{RelPct: 0.15, AbsFloor: 3}
}
