package calibrate

import (
	"fmt"
	"io"
	"math"
	"os"
	"path/filepath"
	"sort"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// fidelityTerms is the ordered set of fidelity terms researchWalk returns.
// The fixed order makes ResearchReport.Points deterministic regardless of map
// iteration order.
var fidelityTerms = []string{
	"cov_poss_pps",
	"cov_shots_per_poss_pps",
	"steal_share",
	"non_steal_to_share",
}

// LeveragePoint is one stand-in × value × fidelity-term observation: how much
// value v for the named stand-in moves the fidelity term relative to baseline,
// and whether the move exceeds the empirical noise floor.
//
// Note: the packet draft types Value as string, but Sweep values are float64 and
// WriteResearchReport formats with %g; Value is float64 here for lossless
// round-trip and to match Apply's func(o *Options, v float64) signature.
type LeveragePoint struct {
	StandInID  string
	Value      float64
	Term       string
	Delta      float64
	NoiseFloor float64
	AboveNoise bool
}

// ResearchReport is the full leverage readout from one RunResearch call:
// an empirical per-term noise floor (derived from two same-config
// different-seed baseline walks) plus every (stand-in, value, term)
// delta observation.
type ResearchReport struct {
	NoiseFloor map[string]float64
	Points     []LeveragePoint
}

// researchWalkFn is the walk-function type injected into runResearch as a test
// seam. The real implementation is researchWalk; tests substitute a closure that
// returns fixed maps without touching the archive.
type researchWalkFn func(root string, opts Options, apply func(*Options)) (map[string]float64, error)

// teamBoxPts returns a TeamBox's total points scored: Q1+Q2+Q3+Q4+ΣOT.
//
// IMPORTANT: this is "points for" (PF in basketball-statistics parlance).
// TeamBox.GamePF is personal FOULS — a different field entirely. Using GamePF
// here would silently produce plausible-but-wrong covariances.
func teamBoxPts(tb result.TeamBox) float64 {
	pts := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
	for _, ot := range tb.OT {
		pts += ot
	}
	return float64(pts)
}

// researchWalk is the real archive-walking fidelity-term collector.
//
// It applies the stand-in override by calling apply on a copy of opts, then
// walks every zip in root (strided by opts.SampleStride), extracts each
// backup triple, and simulates opts.Runs games per zip. From each simulation
// it accumulates:
//
//   - per-(season,team) box stats for the Dean-Oliver possession proxy and the
//     possRow/decomposePossCoupling path; and
//   - per-possession ending-mix counts for steal_share / non_steal_to_share.
//
// Returns a map keyed by the four fidelityTerms.
func researchWalk(root string, opts Options, apply func(*Options)) (map[string]float64, error) {
	// Apply the override to a private copy — opts itself is the original baseline.
	optsCopy := opts
	apply(&optsCopy)

	runs := optsCopy.Runs
	if runs < 1 {
		runs = 1
	}
	stride := optsCopy.SampleStride
	if stride < 1 {
		stride = 1
	}
	seed := optsCopy.Seed

	simOpts := sim.Options{
		BaseTimeMid:           optsCopy.BaseTimeMid,
		StealTurnoverScale:    optsCopy.StealTurnoverScale,
		NonStealTurnoverScale: optsCopy.NonStealTurnoverScale,
	}

	zips, _, err := listArchiveZips(root)
	if err != nil {
		return nil, err
	}

	// Per-(season,team) box accumulator for the possRow path.
	type teamKey struct {
		season string
		teamID int
	}
	type teamAcc struct {
		games  int
		sumPF  float64 // total points-for (Q1+Q2+Q3+Q4+OT), NOT personal fouls
		sumFGA float64 // 2-pt + 3-pt field-goal attempts
		sumFTA float64 // free-throw attempts
		sumORB float64 // offensive rebounds
		sumTOV float64 // turnovers
	}
	accs := map[teamKey]*teamAcc{}

	// Global ending-mix counts for steal_share / non_steal_to_share.
	var counts sim.EndingMixCounts

	for idx, zipPath := range zips {
		if idx%stride != 0 {
			continue
		}

		sname := seasonName(root, zipPath)

		tmp, mkErr := os.MkdirTemp("", "jsbres-*")
		if mkErr != nil {
			continue // skip: can't create temp dir
		}

		found, exErr := extractTriple(zipPath, tmp)
		if exErr != nil || !found {
			_ = os.RemoveAll(tmp)
			continue
		}

		plrFile, err := os.Open(filepath.Join(tmp, "IBL5.plr"))
		if err != nil {
			_ = os.RemoveAll(tmp)
			continue
		}
		players, err := backup.ReadPlr(plrFile)
		_ = plrFile.Close()
		if err != nil {
			_ = os.RemoveAll(tmp)
			continue
		}

		schFile, err := os.Open(filepath.Join(tmp, "IBL5.sch"))
		if err != nil {
			_ = os.RemoveAll(tmp)
			continue
		}
		sched, err := backup.ReadSch(schFile)
		_ = schFile.Close()
		_ = os.RemoveAll(tmp)
		if err != nil {
			continue
		}

		b, err := backup.ToBundle(players, sched, backup.AssembleOptions{})
		if err != nil {
			continue
		}

		for run := 0; run < runs; run++ {
			res, simErr := sim.SimulateWith(b, seed+uint64(run), simOpts)
			if simErr != nil {
				continue
			}
			for _, g := range res.Games {
				// Box-score accumulation for possRow / decomposePossCoupling.
				for _, tb := range g.TeamBoxes {
					k := teamKey{sname, tb.TeamID}
					a := accs[k]
					if a == nil {
						a = &teamAcc{}
						accs[k] = a
					}
					a.games++
					a.sumPF += teamBoxPts(tb) // points-for, not personal fouls
					a.sumFGA += float64(tb.Game2GA + tb.Game3GA)
					a.sumFTA += float64(tb.GameFTA)
					a.sumORB += float64(tb.GameORB)
					a.sumTOV += float64(tb.GameTOV)
				}

				// Ending-mix segmentation loop (verbatim from TestEndingMixBaseline).
				counts.Games++
				var cur []result.Event
				for _, e := range g.Events {
					if e.Kind == result.EventPossessionStart {
						sim.ClassifyPossession(cur, &counts)
						cur = cur[:0]
						continue
					}
					cur = append(cur, e)
				}
				sim.ClassifyPossession(cur, &counts) // trailing partial possession
			}
		}
	}

	// Build one possRow per (season, team) from per-game averages.
	rows := make([]possRow, 0, len(accs))
	for k, a := range accs {
		if a.games == 0 {
			continue
		}
		g := float64(a.games)
		pfPG := a.sumPF / g
		fgaPG := a.sumFGA / g
		ftaPG := a.sumFTA / g
		orbPG := a.sumORB / g
		tovPG := a.sumTOV / g
		// Dean-Oliver possession proxy (symmetric with standings.go):
		//   POSS ≈ FGA + 0.44·FTA + TOV − ORB
		possPG := fgaPG + 0.44*ftaPG + tovPG - orbPG
		rows = append(rows, possRow{
			season: k.season,
			pf:     pfPG,
			fga:    fgaPG,
			poss:   possPG,
		})
	}

	_, covPossPPS, covShotsPerPossPPS := decomposePossCoupling(rows)

	terms := map[string]float64{
		"cov_poss_pps":           covPossPPS,
		"cov_shots_per_poss_pps": covShotsPerPossPPS,
	}
	if counts.Possessions > 0 {
		terms["steal_share"] = float64(counts.EndSteal) / float64(counts.Possessions)
		terms["non_steal_to_share"] = float64(counts.EndTOInd) / float64(counts.Possessions)
	} else {
		terms["steal_share"] = 0
		terms["non_steal_to_share"] = 0
	}

	return terms, nil
}

// runResearch is the internal entry point for the research walk. It accepts a
// researchWalkFn seam so unit tests can exercise the noise-floor, delta, and
// AboveNoise logic without accessing the real archive.
//
// It runs three phases:
//  1. Noise floor: two baseline walks (seeds opts.Seed and opts.Seed+1) to
//     estimate per-term measurement variance.
//  2. Sweep: for each stand-in in StandInRegistry(), for each non-first
//     (non-baseline) Sweep value, one walk with the override applied.
//  3. Assemble: for every (stand-in, value, term) compute Delta = result −
//     baseline and AboveNoise = |Delta| > noise floor.
func runResearch(root string, opts Options, walkFn researchWalkFn) (ResearchReport, error) {
	if opts.Runs < 1 {
		opts.Runs = 1
	}
	if opts.SampleStride < 1 {
		opts.SampleStride = 1
	}

	noApply := func(*Options) {}

	// Baseline run 1 — used as the reference for Delta computation.
	opts1 := opts
	base1, err := walkFn(root, opts1, noApply)
	if err != nil {
		return ResearchReport{}, fmt.Errorf("research baseline run 1: %w", err)
	}

	// Baseline run 2 — different seed, same config → empirical noise floor.
	opts2 := opts
	opts2.Seed = opts.Seed + 1
	base2, err := walkFn(root, opts2, noApply)
	if err != nil {
		return ResearchReport{}, fmt.Errorf("research baseline run 2: %w", err)
	}

	// Noise floor: |run2[term] − run1[term]| per term.
	noiseFloor := make(map[string]float64, len(fidelityTerms))
	for _, term := range fidelityTerms {
		noiseFloor[term] = math.Abs(base2[term] - base1[term])
	}

	report := ResearchReport{NoiseFloor: noiseFloor}

	// Sweep every stand-in's non-baseline values in registry order, iterating
	// the fixed fidelityTerms slice so Points order is deterministic.
	for _, si := range StandInRegistry() {
		for _, v := range si.Sweep[1:] { // Sweep[0] is the baseline value
			apply := si.Apply
			vv := v
			res, wErr := walkFn(root, opts, func(o *Options) { apply(o, vv) })
			if wErr != nil {
				continue
			}
			for _, term := range fidelityTerms {
				delta := res[term] - base1[term]
				nf := noiseFloor[term]
				report.Points = append(report.Points, LeveragePoint{
					StandInID:  si.ID,
					Value:      v,
					Term:       term,
					Delta:      delta,
					NoiseFloor: nf,
					AboveNoise: math.Abs(delta) > nf,
				})
			}
		}
	}

	return report, nil
}

// RunResearch runs the full research walk over the backup archive at root.
// It establishes an empirical noise floor from two baseline passes at
// opts.Seed and opts.Seed+1, then sweeps every registered stand-in's
// non-baseline values and assembles a ResearchReport.
func RunResearch(root string, opts Options) (ResearchReport, error) {
	return runResearch(root, opts, researchWalk)
}

// WriteResearchReport writes a leverage summary to w. All points are ranked by
// |Delta| (largest first). Each line has the form:
//
//	<stand-in> <value> <term>: delta=<±v> noise=<v> [ABOVE NOISE]
//	<stand-in> <value> <term>: delta=<±v> noise=<v> [sub-noise]
func WriteResearchReport(w io.Writer, r ResearchReport) {
	pts := make([]LeveragePoint, len(r.Points))
	copy(pts, r.Points)
	sort.Slice(pts, func(i, j int) bool {
		return math.Abs(pts[i].Delta) > math.Abs(pts[j].Delta)
	})
	for _, p := range pts {
		tag := "[sub-noise]"
		if p.AboveNoise {
			tag = "[ABOVE NOISE]"
		}
		_, _ = fmt.Fprintf(w, "%s %g %s: delta=%+g noise=%g %s\n",
			p.StandInID, p.Value, p.Term, p.Delta, p.NoiseFloor, tag)
	}
}
