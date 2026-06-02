package validate

import "github.com/a-jay85/IBL5/engine/internal/bundle"

// Band is one stat's tolerance: a value passes when it lies within
// max(AbsFloor, RelPct×engineMean) of the observed engine mean (see compareStat).
type Band struct {
	RelPct   float64 // relative tolerance as a fraction of the engine mean
	AbsFloor float64 // absolute floor so small means still get a usable band
}

// ┌──────────────────────────────────────────────────────────────────────────┐
// │ CALIBRATED BANDS — provenance below. These are NOT a fidelity proof.       │
// │                                                                            │
// │ Derived by running jsbcalibrate in --mode calibrate against the real 5.60  │
// │ backup archive and transcribing the proposed per-game-type bands:          │
// │                                                                            │
// │   engine git SHA : eee188415be48489ca91e4f650f1f1ec232a0dd3 (master base)  │
// │   seed           : 20240601                                                │
// │   coverage       : 0.95 (bands cover the 95th abs-residual percentile)     │
// │   selection      : season  (one clean regular snapshot/season + playoffs)  │
// │   runs           : 50       (seeded engine runs per corpus game)           │
// │   sample-stride  : 1        (every selected season; ~20 seasons)           │
// │   corpus         : ibl5/backups (olympics excluded)                        │
// │   date           : 2026-06-01                                              │
// │   n observations : 35782 regular (gt 2), 2184 playoff (gt 4)               │
// │                                                                            │
// │ Audit artifact (the raw CalibrationReport JSON) is committed at            │
// │   internal/validate/testdata/calibration-5.60-20240601.json                │
// │ for documentation ONLY. The band VALUES live hardcoded in this file;       │
// │ nothing reads that JSON at runtime. Re-running calibration regenerates     │
// │ the JSON; the literals below must then be re-transcribed by hand.          │
// │                                                                            │
// │ HCA CAVEAT: bands are calibrated against the CURRENT engine, which has NO  │
// │ home-court advantage. RE-CALIBRATE when HCA lands.                         │
// │                                                                            │
// │ PLAY-OUTCOME RESCALE CAVEAT (PR9): these bands were calibrated against the │
// │ OLD O(100) play-outcome bucket weights. PR9 rescaled those buckets onto a  │
// │ comparable O(1) basis (net-free 2pt composite), shifting the path-selection│
// │ mix — notably foul / and-one / FT path frequencies. The ftm, fta, and pf   │
// │ observed-vs-engine gaps therefore move. Bands are STALE; do NOT re-run the │
// │ 53GB calibration here. Re-calibrate ONCE, after HCA also lands (calibrate  │
// │ once, not twice). No band VALUES change in PR9.                            │
// │                                                                            │
// │ DOCUMENTED CURRENT GAP (AbsFloor > engine mean — band is wide because the  │
// │ engine under-models the stat at this build stage, NOT a useful tolerance): │
// │ ftm, fta, tgm, tga, ast, blk, pf. In particular `ast` is structurally 0 in │
// │ the engine (commentary-only, master-reference L1098), so its band absorbs  │
// │ the full .sco assist total as the gap. These wide bands are the recorded   │
// │ baseline of the engine-vs-jumpshot model gap; band WIDTH is the fidelity   │
// │ signal, and the bands tighten as the engine matures.                       │
// └──────────────────────────────────────────────────────────────────────────┘

// regularBands holds the calibrated regular-season (game_type 2) tolerances.
var regularBands = map[string]Band{
	"points": {RelPct: 0.512775, AbsFloor: 48},
	"fgm":    {RelPct: 0.343837, AbsFloor: 15},
	"fga":    {RelPct: 0.251896, AbsFloor: 23},
	"ftm":    {RelPct: 7.843537, AbsFloor: 25},
	"fta":    {RelPct: 7.293839, AbsFloor: 31},
	"tgm":    {RelPct: 1.835052, AbsFloor: 8},
	"tga":    {RelPct: 1.203065, AbsFloor: 14},
	"reb":    {RelPct: 0.307448, AbsFloor: 16},
	"ast":    {RelPct: 0.15, AbsFloor: 31},
	"stl":    {RelPct: 0.798658, AbsFloor: 13},
	"tov":    {RelPct: 0.744712, AbsFloor: 23},
	"blk":    {RelPct: 9.15625, AbsFloor: 11},
	"pf":     {RelPct: 5.75, AbsFloor: 23},
}

// playoffBands holds the calibrated playoff (game_type 4) tolerances.
var playoffBands = map[string]Band{
	"points": {RelPct: 0.499774, AbsFloor: 46},
	"fgm":    {RelPct: 0.32658, AbsFloor: 14},
	"fga":    {RelPct: 0.249437, AbsFloor: 23},
	"ftm":    {RelPct: 8.027778, AbsFloor: 25},
	"fta":    {RelPct: 7.413462, AbsFloor: 31},
	"tgm":    {RelPct: 1.777778, AbsFloor: 7},
	"tga":    {RelPct: 1.223926, AbsFloor: 13},
	"reb":    {RelPct: 0.307484, AbsFloor: 15},
	"ast":    {RelPct: 0.15, AbsFloor: 31},
	"stl":    {RelPct: 0.757576, AbsFloor: 12},
	"tov":    {RelPct: 0.746193, AbsFloor: 22},
	"blk":    {RelPct: 9.576923, AbsFloor: 11},
	"pf":     {RelPct: 5.914894, AbsFloor: 23},
}

// bands is the SINGLE source of truth for tolerance calibration, keyed by game
// type. Regular and playoff carry their own calibrated tables; the alt/all-star
// types inherit a copy of the calibrated Regular table (see buildBands).
var bands = buildBands()

func buildBands() map[bundle.GameType]map[string]Band {
	out := map[bundle.GameType]map[string]Band{
		bundle.GameTypeRegular: regularBands,
		bundle.GameTypePlayoff: playoffBands,
	}
	// The season collector skips olympics and inferGameType never emits 5, so the
	// corpus produces NO all-star/alt snapshots — these buckets are uncalibrated.
	// They inherit a copy of the calibrated Regular table: non-authoritative,
	// flagged for future calibration when an all-star/alt corpus exists.
	for _, gt := range []bundle.GameType{
		bundle.GameTypeRegularAlt, bundle.GameTypeAllStarA, bundle.GameTypeAllStarB,
	} {
		m := make(map[string]Band, len(regularBands))
		for k, v := range regularBands {
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
