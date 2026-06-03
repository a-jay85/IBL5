package calibrate

import (
	"fmt"
	"io"
	"math"
	"math/bits"
	"os"
	"path/filepath"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// Freeze-lattice attribution harness for the empty-FGA source-isolation diagnostic
// (ADR-0043). It NAMES which within-possession mechanism carries the empty/miss-
// driven FGA that makes the engine's Cov(lnFGA,lnPPS) wrong-signed (ADR-0042).
//
// Method: a counterfactual freeze lattice. Per season bucket the engine runs a
// no-freeze BASELINE pass (harvesting each mechanism's league-mean derived value
// via sim.FreezeAccum), then re-runs every one of the 2^4 = 16 freeze configs with
// those means substituted (sim.SimulateWith). Per config the engine-side scoring
// spread is read out through decomposeLogVariance (REUSED — the same within-season-
// demeaned identity the season-aggregate verdict uses). The per-arm Shapley
// marginal of each of three sub-deltas — ΔVar(lnFGA), ΔCov(lnFGA,lnPF),
// ΔCov(lnFGA,lnPPS) — names the lever and whether the follow-on is CUT (narrows
// Var) or RE-WIRE (raises Cov-with-PF).
//
// This harness is ENGINE-ONLY (frozen-engine vs baseline-engine); it does NOT pair
// against .sco, so it bypasses ValidateCorpus entirely and leaves the validate
// package untouched. The no-freeze config is its OWN reference, so every Δ is
// self-consistent regardless of the absolute Cov (which shifts with runs/stride/
// season-selection and is NOT a fixed constant).

// numArms is the count of freezable within-possession mechanisms. Arm bit i of a
// 16-config mask is set when arm i is frozen.
const numArms = 4

// armNames indexes ORB / TVR / Foul / Make by arm bit position.
var armNames = [numArms]string{"ORB", "TVR", "Foul", "Make"}

// ConfigResult is one freeze config's engine-side scoring-spread decomposition.
// CovLnFGALnPF == VarLnFGA + CovLnFGALnPPS by the decomposeLogVariance identity.
type ConfigResult struct {
	Mask          int      `json:"mask"`
	Frozen        []string `json:"frozen"`
	VarLnPF       float64  `json:"var_ln_pf"`
	VarLnFGA      float64  `json:"var_ln_fga"`
	VarLnPPS      float64  `json:"var_ln_pps"`
	CovLnFGALnPPS float64  `json:"cov_ln_fga_ln_pps"`
	CovLnFGALnPF  float64  `json:"cov_ln_fga_ln_pf"`
	NumRows       int      `json:"num_rows"`
}

// ArmAttribution is one arm's Shapley marginal for each of the three sub-deltas,
// averaged over the 2^3 contexts of the other arms (Shapley-exact). The three
// per-arm contributions of each metric sum to allFrozen − baseline by construction.
// CovPPSCollapseFrac is DCovLnFGALnPPS / |baseline Cov(lnFGA,lnPPS)|: the fraction
// of the baseline (negative) covariance magnitude this arm removes — the quantity
// the dominance criterion thresholds.
type ArmAttribution struct {
	Arm                string  `json:"arm"`
	DVarLnFGA          float64 `json:"d_var_ln_fga"`
	DCovLnFGALnPF      float64 `json:"d_cov_ln_fga_ln_pf"`
	DCovLnFGALnPPS     float64 `json:"d_cov_ln_fga_ln_pps"`
	CovPPSCollapseFrac float64 `json:"cov_pps_collapse_frac"`
}

// MechCorr is the corroborating descriptive panel for one mechanism rate: its
// within-season-demeaned cross-team covariance with lnFGA and lnPPS. It does NOT
// drive the verdict (the freeze lattice does); it is a cheap independent cross-
// check folded from the baseline-pass event stream. The rates are turnover/poss,
// oreb-continuation/poss, foul-only/poss, and miss/FGA.
type MechCorr struct {
	Mech         string  `json:"mech"`
	CovWithLnFGA float64 `json:"cov_with_ln_fga"`
	CovWithLnPPS float64 `json:"cov_with_ln_pps"`
}

// FreezeAttributionReport is the full diagnostic for the regular-season bucket
// (the verdict bucket: the baseline reference is regular-season scoring). The
// controls are reported, not hard-asserted to a literal:
//   - BaselineCovLnFGALnPPS is the no-freeze self-reference.
//   - AllFrozenCovLnFGALnPPS / ResidualFracOfBaseline is the covariance that
//     SURVIVES freezing all four arms — the non-arm (pace / shot-mix / FT /
//     rebound-count) residual. A large residual is itself a verdict: the defect
//     lives outside the four arms.
type FreezeAttributionReport struct {
	GameType               int              `json:"game_type"`
	NumSeasons             int              `json:"num_seasons"`
	Runs                   int              `json:"runs"`
	Configs                []ConfigResult   `json:"configs"`
	Arms                   []ArmAttribution `json:"arms"`
	MechPanel              []MechCorr       `json:"mech_panel"`
	BaselineCovLnFGALnPPS  float64          `json:"baseline_cov_ln_fga_ln_pps"`
	AllFrozenCovLnFGALnPPS float64          `json:"all_frozen_cov_ln_fga_ln_pps"`
	ResidualFracOfBaseline float64          `json:"residual_frac_of_baseline"`
}

// CollectFreezeAttribution runs the freeze lattice over the most-complete regular
// snapshot of every (stride-selected) season in the archive root and returns the
// attribution report. opts.Runs / opts.SampleStride / opts.Progress mirror the
// season-aggregate collector; baseSeed seeds the engine runs.
func CollectFreezeAttribution(root string, opts Options, baseSeed uint64) (FreezeAttributionReport, []Skip, error) {
	zips, zskips, err := listArchiveZips(root)
	if err != nil {
		return FreezeAttributionReport{}, nil, err
	}
	seasons, gskips := groupSeasons(zips, root)
	rep, pskips := collectFreezeAttribution(seasons, opts, baseSeed, loadSeasonBundle, resolveCountSco(opts))
	return rep, append(append(zskips, gskips...), pskips...), nil
}

// collectFreezeAttribution is the injected-dependency core of
// CollectFreezeAttribution: loadFn assembles a season's bundle from its regular
// snapshot, countFn counts a candidate's .sco games/teams for the medGP floor.
// Both are seams so the lattice is unit-testable on synthetic bundles without real
// archive zips (mirrors collectSeasonReports).
func collectFreezeAttribution(seasons []season, opts Options, baseSeed uint64, loadFn func(string) (bundle.Bundle, *Skip), countFn CountScoFunc) (FreezeAttributionReport, []Skip) {
	progress := opts.Progress
	if progress == nil {
		progress = io.Discard
	}
	stride := opts.SampleStride
	if stride < 1 {
		stride = 1
	}
	var skips []Skip

	var configRows [16][]decompRow
	var mechRows []mechRateRow
	nSeasons := 0
	selected := 0
	for _, s := range seasons {
		if s.olympics || len(s.regularCandidates) == 0 {
			continue // recorded by groupSeasons / the season collector already
		}
		idx := selected
		selected++
		if idx%stride != 0 {
			continue
		}

		regularZip, games, teams, cskips := selectMostComplete(s.regularCandidates, countFn)
		skips = append(skips, cskips...)
		if regularZip == "" {
			skips = append(skips, Skip{s.name, "season has no readable regular snapshot"})
			continue
		}
		if proxyMedGP := 2 * float64(games) / float64(max(teams, 1)); proxyMedGP < minSeasonMedianGP {
			skips = append(skips, Skip{s.name, fmt.Sprintf("regular season incomplete in archive (proxy medGP %.0f < floor %d)", proxyMedGP, minSeasonMedianGP)})
			continue
		}

		b, skip := loadFn(regularZip)
		if skip != nil {
			skips = append(skips, *skip)
			continue
		}

		// Baseline pass (mask 0): harvest per-season league means + mech rates. A sim
		// error (an unsatisfiable freeze config never happens here; only a malformed
		// bundle could) is recorded as a Skip, not fatal to the whole run.
		acc := &sim.FreezeAccum{}
		mech := map[int]*mechAcc{}
		baseStats, err := simSeasonStats(b, sim.Options{Accum: acc}, opts.Runs, baseSeed, mech)
		if err != nil {
			skips = append(skips, Skip{regularZip, "baseline sim: " + err.Error()})
			continue
		}
		means := acc.Means()
		appendDecompRows(&configRows[0], s.name, baseStats)
		appendMechRows(&mechRows, s.name, baseStats, mech)

		// Frozen configs (mask 1..15) using this season's means.
		failed := false
		for mask := 1; mask < 16; mask++ {
			st, err := simSeasonStats(b, sim.Options{Freeze: cfgFromMask(mask, means)}, opts.Runs, baseSeed, nil)
			if err != nil {
				skips = append(skips, Skip{regularZip, fmt.Sprintf("frozen sim mask %d: %s", mask, err.Error())})
				failed = true
				break
			}
			appendDecompRows(&configRows[mask], s.name, st)
		}
		if failed {
			continue
		}
		nSeasons++
		_, _ = fmt.Fprintf(progress, "freeze-lattice %s teams=%d\n", regularZip, len(baseStats))
	}

	return buildFreezeReport(configRows, mechRows, nSeasons, opts.Runs), skips
}

// buildFreezeReport decomposes every config, runs the Shapley attribution and the
// mech panel, and assembles the report. Split out so it is unit-testable on
// synthetic rows without touching the archive.
func buildFreezeReport(configRows [16][]decompRow, mechRows []mechRateRow, nSeasons, runs int) FreezeAttributionReport {
	var (
		configs                     []ConfigResult
		varFGA, covFGAPF, covFGAPPS [16]float64
	)
	for mask := 0; mask < 16; mask++ {
		vpf, vfga, vpps, cov := decomposeLogVariance(configRows[mask])
		configs = append(configs, ConfigResult{
			Mask:          mask,
			Frozen:        maskNames(mask),
			VarLnPF:       vpf,
			VarLnFGA:      vfga,
			VarLnPPS:      vpps,
			CovLnFGALnPPS: cov,
			CovLnFGALnPF:  vfga + cov,
			NumRows:       len(configRows[mask]),
		})
		varFGA[mask] = vfga
		covFGAPF[mask] = vfga + cov
		covFGAPPS[mask] = cov
	}

	baseline := covFGAPPS[0]
	allFrozen := covFGAPPS[15]
	absBase := math.Abs(baseline)

	arms := make([]ArmAttribution, numArms)
	for a := 0; a < numArms; a++ {
		dCovPPS := shapleyValue(covFGAPPS, a)
		frac := 0.0
		if absBase > 0 {
			frac = dCovPPS / absBase
		}
		arms[a] = ArmAttribution{
			Arm:                armNames[a],
			DVarLnFGA:          shapleyValue(varFGA, a),
			DCovLnFGALnPF:      shapleyValue(covFGAPF, a),
			DCovLnFGALnPPS:     dCovPPS,
			CovPPSCollapseFrac: frac,
		}
	}

	return FreezeAttributionReport{
		GameType:               int(bundle.GameTypeRegular),
		NumSeasons:             nSeasons,
		Runs:                   runs,
		Configs:                configs,
		Arms:                   arms,
		MechPanel:              mechRateCorr(mechRows),
		BaselineCovLnFGALnPPS:  baseline,
		AllFrozenCovLnFGALnPPS: allFrozen,
		ResidualFracOfBaseline: safeDiv(allFrozen, baseline),
	}
}

// cfgFromMask builds a sim.FreezeConfig from a 4-bit mask and the season means.
func cfgFromMask(mask int, m sim.FreezeMeans) sim.FreezeConfig {
	return sim.FreezeConfig{
		ORB:   mask&1 != 0,
		TVR:   mask&2 != 0,
		Foul:  mask&4 != 0,
		Make:  mask&8 != 0,
		Means: m,
	}
}

// maskNames lists the frozen arm names for a mask (nil for the no-freeze baseline).
func maskNames(mask int) []string {
	var names []string
	for a := 0; a < numArms; a++ {
		if mask&(1<<uint(a)) != 0 {
			names = append(names, armNames[a])
		}
	}
	return names
}

// shapleyValue is arm a's Shapley contribution to the metric M (indexed by freeze
// mask): φ_a = Σ_{S ⊆ N\{a}} |S|!(n−|S|−1)!/n! · (M[S∪{a}] − M[S]). The four φ sum
// to M[full] − M[empty] exactly.
func shapleyValue(M [16]float64, a int) float64 {
	bit := 1 << uint(a)
	n := numArms
	fact := [...]float64{1, 1, 2, 6, 24}
	var phi float64
	for mask := 0; mask < 16; mask++ {
		if mask&bit != 0 {
			continue // S must exclude arm a
		}
		s := bits.OnesCount(uint(mask))
		w := fact[s] * fact[n-s-1] / fact[n]
		phi += w * (M[mask|bit] - M[mask])
	}
	return phi
}

// safeDiv returns a/b, or 0 when b is 0.
func safeDiv(a, b float64) float64 {
	if b == 0 {
		return 0
	}
	return a / b
}

// --- engine runner ---

// pfFga accumulates a team's Σ points / Σ total-FGA over n (game, run) samples.
type pfFga struct{ pf, fga, n float64 }

// simSeasonStats plays every game in b.Schedule `runs` times under opts and sums
// per-team points and total FGA. When mech != nil it also folds the mechanism-rate
// event tallies (baseline pass only). opts.Accum, when set, harvests league means
// across all runs/games/possessions.
func simSeasonStats(b bundle.Bundle, opts sim.Options, runs int, baseSeed uint64, mech map[int]*mechAcc) (map[int]*pfFga, error) {
	stats := map[int]*pfFga{}
	for r := 0; r < runs; r++ {
		res, err := sim.SimulateWith(b, baseSeed+uint64(r), opts)
		if err != nil {
			return nil, err
		}
		for gi := range res.Games {
			gr := &res.Games[gi]
			for _, tb := range gr.TeamBoxes {
				s := stats[tb.TeamID]
				if s == nil {
					s = &pfFga{}
					stats[tb.TeamID] = s
				}
				s.pf += teamPF(tb)
				s.fga += teamFGA(tb)
				s.n++
			}
			if mech != nil {
				foldMechRates(mech, gr.Events)
			}
		}
	}
	return stats, nil
}

// teamPF / teamFGA mirror validate.teamStatFromBox EXACTLY (the established
// comparison basis): points are the quarter totals plus every overtime period;
// total FGA is 2pt + 3pt attempts. Kept in lock-step so the engine-side Cov here
// is comparable to the season-aggregate decomposition.
func teamPF(tb result.TeamBox) float64 {
	p := tb.Q1 + tb.Q2 + tb.Q3 + tb.Q4
	for _, ot := range tb.OT {
		p += ot
	}
	return float64(p)
}

func teamFGA(tb result.TeamBox) float64 { return float64(tb.Game2GA + tb.Game3GA) }

// appendDecompRows turns per-team Σ stats into per-(season,team) per-game-mean
// decompRows (pf<=0 / fga<=0 rows are dropped by decomposeLogVariance).
func appendDecompRows(dst *[]decompRow, season string, stats map[int]*pfFga) {
	for _, s := range stats {
		if s.n == 0 {
			continue
		}
		*dst = append(*dst, decompRow{season: season, pf: s.pf / s.n, fga: s.fga / s.n})
	}
}

// loadSeasonBundle extracts a snapshot's triple and assembles the regular-season
// bundle, mirroring validate.readTriple via the exported backup primitives.
func loadSeasonBundle(zipPath string) (bundle.Bundle, *Skip) {
	tmp, err := os.MkdirTemp("", "jsbfreeze-*")
	if err != nil {
		return bundle.Bundle{}, &Skip{zipPath, "mkdir temp: " + err.Error()}
	}
	defer func() { _ = os.RemoveAll(tmp) }()

	found, err := extractTriple(zipPath, tmp)
	if err != nil {
		return bundle.Bundle{}, &Skip{zipPath, "extract: " + err.Error()}
	}
	if !found {
		return bundle.Bundle{}, &Skip{zipPath, "missing one of IBL5.{plr,sch,sco}"}
	}

	players, err := readBackup(filepath.Join(tmp, "IBL5.plr"), backup.ReadPlr)
	if err != nil {
		return bundle.Bundle{}, &Skip{zipPath, err.Error()}
	}
	sched, err := readBackup(filepath.Join(tmp, "IBL5.sch"), backup.ReadSch)
	if err != nil {
		return bundle.Bundle{}, &Skip{zipPath, err.Error()}
	}
	var minutes map[int]int
	if plb := filepath.Join(tmp, "IBL5.plb"); fileExists(plb) {
		f, err := os.Open(plb)
		if err == nil {
			minutes, _ = backup.ReadPlb(f)
			_ = f.Close()
		}
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{GameType: bundle.GameTypeRegular, Minutes: minutes})
	if err != nil {
		return bundle.Bundle{}, &Skip{zipPath, "assemble: " + err.Error()}
	}
	return b, nil
}

// readBackup opens path and applies a backup reader (mirrors validate.readFile).
func readBackup[T any](path string, read func(io.Reader) ([]T, error)) ([]T, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, fmt.Errorf("open %q: %w", path, err)
	}
	defer func() { _ = f.Close() }()
	out, err := read(f)
	if err != nil {
		return nil, fmt.Errorf("parse %q: %w", path, err)
	}
	return out, nil
}

func fileExists(path string) bool {
	_, err := os.Stat(path)
	return err == nil
}

// --- mechanism-rate corroborating panel ---

// mechAcc tallies one team's within-possession mechanism events over a season
// (summed across games and runs). All five kinds are offense-attributed, so the
// event TeamID is the offense throughout (EventFoul, which carries the DEFENSE id,
// is deliberately not used — foul-only is counted from EventFreeThrow FTAttempts==2).
type mechAcc struct {
	poss     float64 // EventPossessionStart
	tov      float64 // EventTurnover
	orebCont float64 // EventRebound with OffensiveRebound
	foulOnly float64 // EventFreeThrow with FTAttempts == 2 (and-one shoots 1)
	miss     float64 // EventShotMiss
	fga      float64 // EventShotAttempt
}

// foldMechRates folds one game's events into per-team mechanism tallies.
func foldMechRates(into map[int]*mechAcc, events []result.Event) {
	get := func(team int) *mechAcc {
		a := into[team]
		if a == nil {
			a = &mechAcc{}
			into[team] = a
		}
		return a
	}
	for _, e := range events {
		switch e.Kind {
		case result.EventPossessionStart:
			get(e.TeamID).poss++
		case result.EventTurnover:
			get(e.TeamID).tov++
		case result.EventRebound:
			if e.OffensiveRebound {
				get(e.TeamID).orebCont++
			}
		case result.EventFreeThrow:
			if e.FTAttempts == 2 {
				get(e.TeamID).foulOnly++
			}
		case result.EventShotMiss:
			get(e.TeamID).miss++
		case result.EventShotAttempt:
			get(e.TeamID).fga++
		}
	}
}

// mechRateRow is one (season, team) observation: the four mechanism rates plus the
// per-game points/FGA means for the lnFGA / lnPPS the rates are correlated against.
type mechRateRow struct {
	season                                string
	tovRate, orebRate, foulRate, missRate float64
	pf, fga                               float64
}

// appendMechRows turns the per-team mech tallies + pf/fga means into mechRateRows.
func appendMechRows(dst *[]mechRateRow, season string, stats map[int]*pfFga, mech map[int]*mechAcc) {
	for id, m := range mech {
		s := stats[id]
		if s == nil || s.n == 0 || m.poss == 0 || m.fga == 0 {
			continue
		}
		*dst = append(*dst, mechRateRow{
			season:   season,
			tovRate:  m.tov / m.poss,
			orebRate: m.orebCont / m.poss,
			foulRate: m.foulOnly / m.poss,
			missRate: m.miss / m.fga,
			pf:       s.pf / s.n,
			fga:      s.fga / s.n,
		})
	}
}

// mechRateCorr computes, for each mechanism rate, its within-season-demeaned cross-
// team covariance with lnFGA and lnPPS (lnPPS = lnPF − lnFGA, the same shared-term
// definition decomposeLogVariance uses). This is descriptive corroboration only.
func mechRateCorr(rows []mechRateRow) []MechCorr {
	type lr struct {
		season                                string
		tovRate, orebRate, foulRate, missRate float64
		lnFGA, lnPPS                          float64
	}
	valid := make([]lr, 0, len(rows))
	for _, r := range rows {
		if r.pf <= 0 || r.fga <= 0 {
			continue
		}
		lnFGA := math.Log(r.fga)
		lnPPS := math.Log(r.pf) - lnFGA
		valid = append(valid, lr{r.season, r.tovRate, r.orebRate, r.foulRate, r.missRate, lnFGA, lnPPS})
	}
	extract := []struct {
		name string
		rate func(lr) float64
	}{
		{"turnover", func(v lr) float64 { return v.tovRate }},
		{"oreb_continuation", func(v lr) float64 { return v.orebRate }},
		{"foul_only", func(v lr) float64 { return v.foulRate }},
		{"miss", func(v lr) float64 { return v.missRate }},
	}
	out := make([]MechCorr, 0, len(extract))
	for _, ex := range extract {
		covFGA, covPPS := covWithinSeason(len(valid),
			func(i int) string { return valid[i].season },
			func(i int) float64 { return ex.rate(valid[i]) },
			func(i int) float64 { return valid[i].lnFGA },
			func(i int) float64 { return valid[i].lnPPS },
		)
		out = append(out, MechCorr{Mech: ex.name, CovWithLnFGA: covFGA, CovWithLnPPS: covPPS})
	}
	return out
}

// covWithinSeason returns the within-season-demeaned population covariance of the
// rate series with each of two target series (lnFGA, lnPPS), pooled over all rows.
// Mirrors decomposeLogVariance's per-season demean.
func covWithinSeason(n int, seasonOf func(int) string, rateOf, aOf, bOf func(int) float64) (covA, covB float64) {
	if n == 0 {
		return 0, 0
	}
	var sumR, sumA, sumB map[string]float64 = map[string]float64{}, map[string]float64{}, map[string]float64{}
	cnt := map[string]float64{}
	for i := 0; i < n; i++ {
		s := seasonOf(i)
		sumR[s] += rateOf(i)
		sumA[s] += aOf(i)
		sumB[s] += bOf(i)
		cnt[s]++
	}
	var sA, sB float64
	for i := 0; i < n; i++ {
		s := seasonOf(i)
		c := cnt[s]
		rR := rateOf(i) - sumR[s]/c
		sA += rR * (aOf(i) - sumA[s]/c)
		sB += rR * (bOf(i) - sumB[s]/c)
	}
	fn := float64(n)
	return sA / fn, sB / fn
}
