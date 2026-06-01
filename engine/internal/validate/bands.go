package validate

import "github.com/a-jay85/IBL5/engine/internal/bundle"

// Band is one stat's tolerance: a value passes when it lies within
// max(AbsFloor, RelPct×engineMean) of the observed engine mean (see compareStat).
type Band struct {
	RelPct   float64 // relative tolerance as a fraction of the engine mean
	AbsFloor float64 // absolute floor so small means still get a usable band
}

// placeholderBands is the per-stat starting tolerance, shared across every game
// type until corpus calibration replaces it (see the box below).
//
// ┌──────────────────────────────────────────────────────────────────────────┐
// │ STARTING BANDS — NOT AUTHORITATIVE. These ±15% relative / per-stat floor   │
// │ values are a defensible placeholder, NOT grounded in measured corpus       │
// │ variance. They MUST be calibrated by running `jsbcalibrate` against the    │
// │ real 5.60 backup archive (ibl5/backups) and widening/tightening each band  │
// │ to absorb (a) the single-.sco-draw sampling noise (one .sco line is one    │
// │ draw from jumpshot.exe's own distribution) AND (b) the engine-vs-jumpshot  │
// │ model gap, WITHOUT making any band so wide it can never fail. Until         │
// │ calibrated, the tagged corpus suite is a DIAGNOSTIC, not a merge gate.     │
// │                                                                            │
// │ Note also: the backup-driven sim runs with no per-player minutes/stamina   │
// │ signal (the .plr carries none — see backup.ToBundle), so the engine-side   │
// │ distribution is wider than a DB-driven sim's; the bands must absorb that   │
// │ too.                                                                       │
// └──────────────────────────────────────────────────────────────────────────┘
var placeholderBands = map[string]Band{
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

// bands is the SINGLE source of truth for tolerance calibration, now keyed by
// game type: regular, playoff, and all-star ground truth differ systematically
// (pace, defense, shot mix), so each game type carries its own band table.
//
// Every game type is currently seeded with an identical clone of
// placeholderBands. The jsbcalibrate harness (cmd/jsbcalibrate) emits per-game-
// type calibrated values derived from the real backup archive; replacing a game
// type's entry with those values is a data-only follow-up edit. Edit ONLY this
// table (or placeholderBands) to recalibrate.
var bands = buildBands()

func buildBands() map[bundle.GameType]map[string]Band {
	out := make(map[bundle.GameType]map[string]Band)
	for _, gt := range []bundle.GameType{
		bundle.GameTypeRegular, bundle.GameTypeRegularAlt,
		bundle.GameTypePlayoff, bundle.GameTypeAllStarA, bundle.GameTypeAllStarB,
	} {
		m := make(map[string]Band, len(placeholderBands))
		for k, v := range placeholderBands {
			m[k] = v
		}
		out[gt] = m
	}
	return out
}

// bandFor returns the configured band for a (game type, stat). An unknown game
// type falls back to the regular-season table; an unknown stat (within a known
// or fallback table) gets a generic ±15%/floor-3 band — defensive, since every
// statNames entry has an explicit band in every game type's table.
func bandFor(gt bundle.GameType, name string) Band {
	m, ok := bands[gt]
	if !ok {
		m = bands[bundle.GameTypeRegular]
	}
	if b, ok := m[name]; ok {
		return b
	}
	return Band{RelPct: 0.15, AbsFloor: 3}
}
