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
// │   engine base    : 325621864 (hca-margin-instrument / PR #956)             │
// │   calibrated knob: offQualityRatingScale = 0.059 (this PR; teamquality.go) │
// │   seed           : 20240601                                                │
// │   coverage       : 0.95 (bands cover the 95th abs-residual percentile)     │
// │   selection      : season  (one clean regular snapshot/season + playoffs)  │
// │   runs           : 50       (seeded engine runs per corpus game)           │
// │   sample-stride  : 1        (every selected season; 18 reg + 12 PO)        │
// │   corpus         : ibl5/backups (olympics excluded)                        │
// │   date           : 2026-06-02                                              │
// │   n observations : 39686 points-rows regular (gt 2), 2018 playoff (gt 4) - │
// │                    PR1 clean corpus (regenerated; 2 rows/game; 19843 /     │
// │                    1009 games feed the per-game home-margin readout)       │
// │                                                                            │
// │ Audit artifact (the raw CalibrationReport JSON) is committed at            │
// │   internal/validate/testdata/calibration-5.60-20260602.json                │
// │ for documentation ONLY; nothing reads it at runtime. It was regenerated    │
// │ on PR1's clean most-complete-snapshot corpus, adding season-aggregate +    │
// │ level/dispersion fidelity diagnostics, so its buckets reflect that         │
// │ corpus. Band literals below stay #957-derived, not re-transcribed here.    │
// │                                                                            │
// │ HCA IS NOW ACTIVE (faithful home-court advantage landed in #955; its       │
// │ magnitude is calibrated here). These bands reflect the calibrated quality- │
// │ stand-in scale: at offQualityRatingScale=0.059 the engine's mean home-     │
// │ minus-visitor point margin matches the corpus within ±0.5 pts for both     │
// │ game types (gt 2 gap +0.10, gt 4 gap −0.09). NOTE on the margin instrument:│
// │ its home WIN-SHARE is only comparable to .sco at --runs 1 (engine win-     │
// │ share = P(mean-over-N-runs margin>0) inflates as √N; .sco is a single      │
// │ realization). At runs=1 the win-share gap is +1.7pp (gt 2) / −0.6pp (gt 4),│
// │ within ±3pp; the runs=50 artifact's win_share_gap (~+23pp) is that √N      │
// │ measurement artifact, NOT a model gap. Bands are unaffected — they come    │
// │ from per-stat residuals (runs-stable), not from win-share.                 │
// │                                                                            │
// │ DOCUMENTED CURRENT GAP (AbsFloor > engine mean — band is wide because the  │
// │ engine under-models the stat at this build stage, NOT a useful tolerance): │
// │ ftm, fta, tgm, tga, ast, blk. In particular `ast` is structurally 0 in     │
// │ the engine (commentary-only, master-reference L1098), so its band absorbs  │
// │ the full .sco assist total as the gap. (pf is no longer in this set — the  │
// │ calibrated foul rate brought its engine mean ≈17 above the abs floor.)     │
// │ These wide bands are the recorded baseline of the engine-vs-jumpshot model │
// │ gap; band WIDTH is the fidelity signal, and the bands tighten as the       │
// │ engine matures.                                                            │
// └──────────────────────────────────────────────────────────────────────────┘

// regularBands holds the calibrated regular-season (game_type 2) tolerances.
var regularBands = map[string]Band{
	"points": {RelPct: 0.516437, AbsFloor: 48},
	"fgm":    {RelPct: 1.008547, AbsFloor: 27},
	"fga":    {RelPct: 0.780316, AbsFloor: 47},
	"ftm":    {RelPct: 0.794454, AbsFloor: 31},
	"fta":    {RelPct: 0.775939, AbsFloor: 43},
	"tgm":    {RelPct: 1.75, AbsFloor: 8},
	"tga":    {RelPct: 1.155172, AbsFloor: 13},
	"reb":    {RelPct: 0.550388, AbsFloor: 22},
	"ast":    {RelPct: 0.15, AbsFloor: 31},
	"stl":    {RelPct: 0.791956, AbsFloor: 13},
	"tov":    {RelPct: 0.735799, AbsFloor: 22},
	"blk":    {RelPct: 9.843373, AbsFloor: 11},
	"pf":     {RelPct: 1.350427, AbsFloor: 16},
}

// playoffBands holds the calibrated playoff (game_type 4) tolerances.
var playoffBands = map[string]Band{
	"points": {RelPct: 0.498308, AbsFloor: 46},
	"fgm":    {RelPct: 1.019129, AbsFloor: 27},
	"fga":    {RelPct: 0.812572, AbsFloor: 46},
	"ftm":    {RelPct: 0.794745, AbsFloor: 32},
	"fta":    {RelPct: 0.778679, AbsFloor: 44},
	"tgm":    {RelPct: 1.678571, AbsFloor: 7},
	"tga":    {RelPct: 1.153558, AbsFloor: 13},
	"reb":    {RelPct: 0.584507, AbsFloor: 23},
	"ast":    {RelPct: 0.15, AbsFloor: 31},
	"stl":    {RelPct: 0.751861, AbsFloor: 12},
	"tov":    {RelPct: 0.73565, AbsFloor: 21},
	"blk":    {RelPct: 10.320755, AbsFloor: 12},
	"pf":     {RelPct: 1.445652, AbsFloor: 16},
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
