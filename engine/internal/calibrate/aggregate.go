package calibrate

import (
	"math"
	"sort"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// observation is one (game type, stat) data point: a single .sco ground-truth
// value and the engine mean the harness observed for the same matchup.
type observation struct {
	gameType bundle.GameType
	stat     string
	scoVal   float64
	mean     float64
}

// collectObservations flattens every StatRow across all reports/games into the
// per-(game type, stat) observations the calibration consumes. Each report is
// stamped with its game type by the validation harness.
func collectObservations(reports []validate.Report) []observation {
	var obs []observation
	for _, rep := range reports {
		for _, g := range rep.Games {
			for _, r := range g.Rows {
				obs = append(obs, observation{
					gameType: rep.GameType,
					stat:     r.Stat,
					scoVal:   r.ScoVal,
					mean:     r.EngineMean,
				})
			}
		}
	}
	return obs
}

// --- calibrate mode ---------------------------------------------------------

// StatCalibration is the proposed band plus the residual distribution it was
// derived from, for one (game type, stat). InBandRate is the fraction of this
// bucket's observations the proposed band would accept — a human's sanity check
// that the band is neither vacuous (rate ~1 with a huge floor) nor too tight.
type StatCalibration struct {
	Stat             string  `json:"stat"`
	N                int     `json:"n"`
	MeanEngine       float64 `json:"mean_engine"`
	P50AbsResid      float64 `json:"p50_abs_resid"`
	P90AbsResid      float64 `json:"p90_abs_resid"`
	P95AbsResid      float64 `json:"p95_abs_resid"`
	P99AbsResid      float64 `json:"p99_abs_resid"`
	ProposedRelPct   float64 `json:"proposed_rel_pct"`
	ProposedAbsFloor float64 `json:"proposed_abs_floor"`
	InBandRate       float64 `json:"in_band_rate"`
}

// GameTypeCalibration is the proposed bands for one game type, stats in the
// canonical validate.StatNames order.
type GameTypeCalibration struct {
	GameType int               `json:"game_type"`
	Stats    []StatCalibration `json:"stats"`
}

// CalibrationReport is the full calibrate-mode output: proposed per-game-type
// bands derived at the requested residual coverage. Buckets are sorted by game
// type for determinism. HomeMargins carries the per-game-type home-court-margin
// readout (the HCA fidelity signal); it rides along on the same run so one
// calibrate pass yields both bands and margins.
type CalibrationReport struct {
	Coverage    float64                 `json:"coverage"`
	Buckets     []GameTypeCalibration   `json:"buckets"`
	HomeMargins []HomeMarginCalibration `json:"home_margins"`
}

// Calibrate derives, per (game type, stat), a tolerance band whose absolute
// floor and relative percent each cover `coverage` of the observed
// engine-mean-vs-.sco residuals — calibrated ACROSS games (a single .sco line is
// one noisy draw from jumpshot.exe, so per-game pass/fail would just measure
// that draw's variance). The band keeps the harness's max(absFloor, relPct×mean)
// shape, so its values slot straight into validate's per-game-type band tables.
func Calibrate(reports []validate.Report, coverage float64) CalibrationReport {
	byType := groupObservations(collectObservations(reports))
	rep := CalibrationReport{Coverage: coverage}
	for _, gt := range sortedGameTypes(byType) {
		gc := GameTypeCalibration{GameType: int(gt)}
		for _, stat := range validate.StatNames() {
			obs := byType[gt][stat]
			if len(obs) == 0 {
				continue
			}
			gc.Stats = append(gc.Stats, calibrateStat(stat, obs, coverage))
		}
		rep.Buckets = append(rep.Buckets, gc)
	}
	rep.HomeMargins = CollectHomeMargins(reports)
	return rep
}

func calibrateStat(stat string, obs []observation, coverage float64) StatCalibration {
	n := len(obs)
	absResid := make([]float64, n)
	var relResid []float64
	var sumMean float64
	for i, o := range obs {
		d := math.Abs(o.scoVal - o.mean)
		absResid[i] = d
		sumMean += o.mean
		if o.mean >= 1 { // skip near-zero means so the ratio doesn't explode
			relResid = append(relResid, d/o.mean)
		}
	}
	sort.Float64s(absResid)

	absFloor := math.Ceil(percentile(absResid, coverage))
	if absFloor < 1 {
		absFloor = 1 // never a zero-width band
	}
	relPct := 0.15 // fallback when relative spread is unmeasurable (all means < 1)
	if len(relResid) > 0 {
		sort.Float64s(relResid)
		relPct = percentile(relResid, coverage)
	}

	b := validate.Band{RelPct: relPct, AbsFloor: absFloor}
	inBand := 0
	for _, o := range obs {
		if withinBand(b, o.scoVal, o.mean) {
			inBand++
		}
	}
	return StatCalibration{
		Stat:             stat,
		N:                n,
		MeanEngine:       sumMean / float64(n),
		P50AbsResid:      percentile(absResid, 0.50),
		P90AbsResid:      percentile(absResid, 0.90),
		P95AbsResid:      percentile(absResid, 0.95),
		P99AbsResid:      percentile(absResid, 0.99),
		ProposedRelPct:   relPct,
		ProposedAbsFloor: absFloor,
		InBandRate:       float64(inBand) / float64(n),
	}
}

// --- gate mode --------------------------------------------------------------

// StatGate is one (game type, stat) in-band rate under the COMMITTED validate
// bands (StatRow.Pass already reflects them), with its pass/fail verdict.
type StatGate struct {
	Stat   string  `json:"stat"`
	N      int     `json:"n"`
	InBand int     `json:"in_band"`
	Rate   float64 `json:"rate"`
	Pass   bool    `json:"pass"`
}

// GameTypeGate is the gate verdict for one game type.
type GameTypeGate struct {
	GameType int        `json:"game_type"`
	Stats    []StatGate `json:"stats"`
}

// GateResult is the full gate-mode output. Pass is true only when every
// (game type, stat) bucket's in-band rate is at least MinRate.
type GateResult struct {
	MinRate float64        `json:"min_rate"`
	Pass    bool           `json:"pass"`
	Buckets []GameTypeGate `json:"buckets"`
}

// Gate applies the committed validate bands across the archive: it tallies, per
// (game type, stat), how many observations the harness already marked in-band
// (StatRow.Pass), and fails any bucket whose rate falls below minRate.
func Gate(reports []validate.Report, minRate float64) GateResult {
	type tally struct{ n, pass int }
	byType := map[bundle.GameType]map[string]*tally{}
	for _, rep := range reports {
		for _, g := range rep.Games {
			for _, r := range g.Rows {
				if byType[rep.GameType] == nil {
					byType[rep.GameType] = map[string]*tally{}
				}
				t := byType[rep.GameType][r.Stat]
				if t == nil {
					t = &tally{}
					byType[rep.GameType][r.Stat] = t
				}
				t.n++
				if r.Pass {
					t.pass++
				}
			}
		}
	}

	res := GateResult{MinRate: minRate, Pass: true}
	gts := make([]bundle.GameType, 0, len(byType))
	for gt := range byType {
		gts = append(gts, gt)
	}
	sort.Slice(gts, func(i, j int) bool { return gts[i] < gts[j] })
	for _, gt := range gts {
		gg := GameTypeGate{GameType: int(gt)}
		for _, stat := range validate.StatNames() {
			t := byType[gt][stat]
			if t == nil {
				continue
			}
			rate := float64(t.pass) / float64(t.n)
			pass := rate >= minRate
			if !pass {
				res.Pass = false
			}
			gg.Stats = append(gg.Stats, StatGate{
				Stat: stat, N: t.n, InBand: t.pass, Rate: rate, Pass: pass,
			})
		}
		res.Buckets = append(res.Buckets, gg)
	}
	return res
}

// --- shared helpers ---------------------------------------------------------

func groupObservations(obs []observation) map[bundle.GameType]map[string][]observation {
	byType := map[bundle.GameType]map[string][]observation{}
	for _, o := range obs {
		if byType[o.gameType] == nil {
			byType[o.gameType] = map[string][]observation{}
		}
		byType[o.gameType][o.stat] = append(byType[o.gameType][o.stat], o)
	}
	return byType
}

func sortedGameTypes(byType map[bundle.GameType]map[string][]observation) []bundle.GameType {
	gts := make([]bundle.GameType, 0, len(byType))
	for gt := range byType {
		gts = append(gts, gt)
	}
	sort.Slice(gts, func(i, j int) bool { return gts[i] < gts[j] })
	return gts
}

// withinBand mirrors validate.compareStat's max(absFloor, relPct×|mean|) rule.
func withinBand(b validate.Band, scoVal, mean float64) bool {
	tol := b.AbsFloor
	if rel := b.RelPct * math.Abs(mean); rel > tol {
		tol = rel
	}
	return math.Abs(scoVal-mean) <= tol
}

// percentile returns the value at the given quantile of an ascending-sorted
// slice using the nearest-rank method (deterministic, no interpolation). An
// empty slice yields 0.
func percentile(sorted []float64, p float64) float64 {
	n := len(sorted)
	if n == 0 {
		return 0
	}
	if p <= 0 {
		return sorted[0]
	}
	if p >= 1 {
		return sorted[n-1]
	}
	rank := int(math.Ceil(p * float64(n)))
	if rank < 1 {
		rank = 1
	}
	if rank > n {
		rank = n
	}
	return sorted[rank-1]
}
