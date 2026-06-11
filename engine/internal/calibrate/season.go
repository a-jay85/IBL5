package calibrate

import (
	"fmt"
	"io"
	"path/filepath"
	"sort"
	"strings"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// A snapshot's .sco is CUMULATIVE — a late snapshot holds every game of the
// season to date (verified on the real archive: reg-sim01=49 games,
// reg-sim13=549, last reg-sim=1148, the full regular season). Two facts shape
// the season-grouped collector:
//
//  1. Processing every snapshot would double-count early games (re-counted in
//     each later cumulative snapshot). So per season we take ONE snapshot per
//     bucket: the MOST-COMPLETE regular-type snapshot for the regular bucket
//     (the candidate whose .sco holds the most games — see collectSeasonReports;
//     lexical-last is NOT a reliable completeness signal, since a misclassified
//     or partial mid-season backup can sort last), and the finals (last overall)
//     snapshot for the playoff bucket.
//
//  2. REGULAR games are validated via validate.ValidateCorpus, which pairs each
//     .sco game to a .sch schedule entry. PLAYOFF games are NOT in the .sch
//     (playoff brackets are scheduled dynamically, from Schedule.htm in prod),
//     so they are validated via validate.ValidateUnscheduled — the complement
//     path that synthesizes each unmatched .sco game's matchup from its own
//     team IDs (PR9d). All-star/Olympics calibration is still out of scope:
//     Olympics national-team rosters are not a clean .plr-franchise sim.

// minSeasonMedianGP is the proxy-medGP floor below which a season is treated as
// incomplete in the archive and skipped (rather than reported as if complete).
// Every complete season has medGP≈82 regardless of era (each team plays 82
// games), so a floor of 70 drops genuinely-partial seasons (07-08 preseason-only,
// 06-07/90-91/91-92/92-93 mid-season-truncated backups) without dropping any
// complete one. proxyMedGP = 2 * games / distinctTeams, computed pre-sim from the
// chosen snapshot's .sco so a doomed season costs one cheap .sco read, not a sim.
const minSeasonMedianGP = 70

// season is one season's selected snapshots. olympics seasons are recorded so
// they can be reported as skipped (out of scope — national-team rosters need
// separate handling). regularCandidates holds EVERY regular-type snapshot for
// the season (in lexical order); collectSeasonReports picks the most-complete one
// by .sco game count. finalsZip is the last snapshot overall (lexical/sim-step
// order); it carries the playoff games when it is a playoff-typed snapshot.
type season struct {
	name              string
	olympics          bool
	regularCandidates []string // every regular-type snapshot; empty if the season has none
	finalsZip         string   // last snapshot overall; "" if the season has none
}

// seasonName is the path segment immediately under root (e.g. "02-03", or
// "olympics" for the Olympics subtree). A zip directly under root is its own
// single-snapshot season.
func seasonName(root, path string) string {
	rel, err := filepath.Rel(root, path)
	if err != nil {
		return filepath.Dir(path)
	}
	parts := strings.Split(filepath.ToSlash(rel), "/")
	return parts[0]
}

func isOlympicsPath(path string) bool {
	return strings.Contains(strings.ToLower(filepath.ToSlash(path)), "olympics")
}

// groupSeasons buckets zip paths into seasons. It collects EVERY regular-type
// snapshot per season (regularCandidates, in lexical order) — collectSeasonReports
// then picks the most-complete one by .sco game count — and the last snapshot
// overall (finalsZip, which carries the playoff games when it is a playoff-typed
// snapshot). The archive's NN index is zero-padded, so lexical order is sim-step
// order; that still seeds finalsZip, but the regular bucket no longer trusts it
// for completeness (a partial backup can sort last). Olympics snapshots are
// flagged (and skipped: not yet supported, see the note above).
func groupSeasons(zipPaths []string, root string) ([]season, []Skip) {
	type acc struct {
		olympics          bool
		regularCandidates []string
		finalsZip         string
	}
	m := map[string]*acc{}
	var skips []Skip
	for _, p := range zipPaths {
		name := seasonName(root, p)
		a := m[name]
		if a == nil {
			a = &acc{}
			m[name] = a
		}
		if isOlympicsPath(p) {
			a.olympics = true
			skips = append(skips, Skip{p, "olympics not yet supported (national-team rosters — see season.go)"})
			continue
		}
		// The last snapshot overall is the finals snapshot (playoff bucket); every
		// regular-type snapshot is a regular-bucket candidate. zipPaths is lexical,
		// so regularCandidates accumulates in lexical order.
		if p > a.finalsZip {
			a.finalsZip = p
		}
		if gt, _ := inferGameType(p, false); gt == bundle.GameTypeRegular {
			a.regularCandidates = append(a.regularCandidates, p)
		}
	}

	names := make([]string, 0, len(m))
	for n := range m {
		names = append(names, n)
	}
	sort.Strings(names)
	seasons := make([]season, 0, len(names))
	for _, n := range names {
		a := m[n]
		seasons = append(seasons, season{name: n, olympics: a.olympics, regularCandidates: a.regularCandidates, finalsZip: a.finalsZip})
	}
	return seasons, skips
}

// CollectSeasonReports is the calibration-correct collector: it groups the
// archive into seasons and, per season, validates the MOST-COMPLETE regular-type
// snapshot as the REGULAR bucket and (when a distinct playoff snapshot exists)
// its unmatched .sco games as the PLAYOFF bucket. A season whose best regular
// snapshot is still incomplete (proxy medGP below minSeasonMedianGP) is skipped
// rather than reported as if complete. --sample-stride thins by season; both
// buckets for a selected season are produced together. All-star and Olympics
// remain out of scope.
func CollectSeasonReports(root string, opts Options) ([]validate.Report, []Skip, error) {
	zips, zskips, err := listArchiveZips(root)
	if err != nil {
		return nil, nil, err
	}
	seasons, gskips := groupSeasons(zips, root)
	reports, pskips := collectSeasonReports(seasons, opts, resolveValidate(opts), resolveValidateUnscheduled(opts), resolveCountSco(opts))
	skips := append(append(zskips, gskips...), pskips...)
	return reports, skips, nil
}

// collectSeasonReports is the injected-dependency core of CollectSeasonReports.
// validateFn handles the regular (scheduled) bucket; validateUnscheduledFn
// handles the playoff (unscheduled) bucket; countFn counts each regular
// candidate's .sco games/teams for the max-games selection and medGP floor — all
// seams so the season grouping and bucket selection are unit-testable without
// running the engine or building multi-megabyte .sco fixtures.
func collectSeasonReports(seasons []season, opts Options, validateFn, validateUnscheduledFn ValidateFunc, countFn CountScoFunc) ([]validate.Report, []Skip) {
	progress := opts.Progress
	if progress == nil {
		progress = io.Discard
	}
	stride := opts.SampleStride
	if stride < 1 {
		stride = 1
	}

	var reports []validate.Report
	var skips []Skip
	selected := 0
	for _, s := range seasons {
		if s.olympics || len(s.regularCandidates) == 0 {
			if !s.olympics {
				skips = append(skips, Skip{s.name, "season has no regular-type snapshot"})
			}
			continue
		}
		idx := selected
		selected++
		if idx%stride != 0 {
			continue
		}

		// Pick the most-complete regular snapshot by .sco game count (cumulative,
		// so most games = furthest into the season), and compute the pre-sim proxy
		// medGP from it. A candidate whose .sco cannot be counted is recorded as a
		// Skip but does not disqualify the season — a later candidate may count.
		regularZip, games, teams, cskips := selectMostComplete(s.regularCandidates, countFn)
		skips = append(skips, cskips...)
		if regularZip == "" {
			skips = append(skips, Skip{s.name, "season has no readable regular snapshot"})
			continue
		}
		if proxyMedGP := 2 * float64(games) / float64(max(teams, 1)); proxyMedGP < minSeasonMedianGP {
			skips = append(skips, Skip{s.name, fmt.Sprintf(
				"regular season incomplete in archive (proxy medGP %.0f < floor %d)", proxyMedGP, minSeasonMedianGP)})
			continue
		}

		rep, skip := processZip(regularZip, bundle.GameTypeRegular, opts.Runs, opts.Seed, validateFn)
		if skip != nil {
			skips = append(skips, *skip)
			continue
		}
		rep.Label = s.name
		_, _ = fmt.Fprintf(progress, "regular %s games=%d\n", regularZip, len(rep.Games))
		reports = append(reports, *rep)

		// Playoff bucket: the finals snapshot's unmatched .sco games, validated via
		// the unscheduled path. Only when finalsZip is a genuinely playoff-typed
		// snapshot — never a regular snapshot that merely sorts last (which, after
		// max-games selection, can differ from the chosen regular snapshot).
		if s.finalsZip != "" {
			if gt, _ := inferGameType(s.finalsZip, false); gt == bundle.GameTypePlayoff {
				prep, pskip := processZip(s.finalsZip, bundle.GameTypePlayoff, opts.Runs, opts.Seed, validateUnscheduledFn)
				if pskip != nil {
					skips = append(skips, *pskip)
					continue
				}
				prep.Label = s.name + " (playoffs)"
				_, _ = fmt.Fprintf(progress, "playoff %s games=%d excluded=%d\n", s.finalsZip, len(prep.Games), len(prep.Excluded))
				reports = append(reports, *prep)
			}
		}
	}
	return reports, skips
}

// selectMostComplete counts each candidate's .sco and returns the path with the
// most games (the cumulative-completeness signal), along with that snapshot's
// game and distinct-team counts. Candidates whose .sco cannot be counted are
// returned as Skips and excluded from the running max; if none can be counted,
// best is "". Ties keep the first (lexically-earliest) candidate.
func selectMostComplete(candidates []string, countFn CountScoFunc) (best string, games, teams int, skips []Skip) {
	games = -1
	for _, c := range candidates {
		g, t, err := countFn(c)
		if err != nil {
			skips = append(skips, Skip{c, "count .sco: " + err.Error()})
			continue
		}
		if g > games {
			best, games, teams = c, g, t
		}
	}
	if best == "" {
		return "", 0, 0, skips
	}
	return best, games, teams, skips
}

// resolveValidate returns opts.Validate (the injected test seam, which ignores the
// measurement toggles) or a real default that threads them through ValidateCorpusWith —
// so the A/B configures Branch-B / the ADR-0053 decoupling arms without widening the
// 4-arg ValidateFunc seam (and its mocks).
func resolveValidate(opts Options) ValidateFunc {
	if opts.Validate != nil {
		return opts.Validate
	}
	return validateWithArms(opts, validate.ValidateCorpusWith)
}

// resolveValidateUnscheduled returns opts.ValidateUnscheduled or a real default that
// threads the measurement toggles through ValidateUnscheduledWith (the playoff-bucket
// seam).
func resolveValidateUnscheduled(opts Options) ValidateFunc {
	if opts.ValidateUnscheduled != nil {
		return opts.ValidateUnscheduled
	}
	return validateWithArms(opts, validate.ValidateUnscheduledWith)
}

// makePutbackActive reports whether either ADR-0053 shots-per-possession decoupling
// arm is enabled.
func (o Options) makePutbackActive() bool { return o.MakePutback || o.MakePutbackHalf }

// validateWithArms wraps a real validate.*With function into the 4-arg ValidateFunc
// seam, threading the configured measurement seams as a sim.Options bundle.
//
// Without the ADR-0053 arms it is the single Branch-B passthrough (a zero sim.Options{}
// when Branch-B is also off ⇒ byte-identical to the pre-ADR-0053 closure). With an arm
// on it runs a PER-SEASON-BUCKET two-pass, because the arms consume
// FreezeMeans.MakeVal2pt and the league-mean make-value is era-specific:
//
//  1. Harvest pass — a FRESH sim.FreezeAccum, allocated INSIDE this closure invocation
//     so each season harvests its OWN mean (an accum hoisted to resolveValidate's scope
//     would be shared across the whole walk → a cross-era global mean, an era-
//     contaminated fidelity regression). validateFn is called once per season bucket
//     (processZip → validateFn), so per-invocation == per-bucket.
//  2. Frozen pass — the SAME seed (so the arm perturbs the same realized games the mean
//     was harvested from, mirroring CollectFreezeAttribution's two same-seed passes),
//     the arm on, and Means populated from the harvest. Its report is the returned one.
//
// The harvest report is discarded; the OFF aggregate is a separate top-level walk
// (exactly like the Branch-B A/B), never this closure's harvest pass.
func validateWithArms(opts Options, validateFn func(string, int, uint64, bundle.GameType, sim.Options) (validate.Report, error)) ValidateFunc {
	return func(dir string, runs int, seed uint64, gt bundle.GameType) (validate.Report, error) {
		base := sim.Options{}
		base.OffVolumeScale = opts.OffVolumeScale              // ADR-0054 sweep seam: nil ⇒ const path; survives both the early-return and two-pass paths below
		base.GateBaseline = opts.GateBaseline                  // ADR-0058 gate-baseline sweep seam: nil ⇒ bundle-derived baseline; survives both paths below
		base.Freeze.UnfaithfulPutback = opts.UnfaithfulPutback // ADR-0055 OFF walk: restore master's coupled putback; survives both paths (copied into harvest/frozen)
		if opts.BranchB {
			base.Freeze.BranchB = true
			base.BranchBAccum = opts.BranchBAccum
		}
		if !opts.makePutbackActive() {
			return validateFn(dir, runs, seed, gt, base)
		}
		acc := &sim.FreezeAccum{}
		harvest := base
		harvest.Accum = acc
		if _, err := validateFn(dir, runs, seed, gt, harvest); err != nil {
			return validate.Report{}, err
		}
		frozen := base
		frozen.Freeze.MakePutback = opts.MakePutback
		frozen.Freeze.MakePutbackHalf = opts.MakePutbackHalf
		frozen.Freeze.Means = acc.Means()
		return validateFn(dir, runs, seed, gt, frozen)
	}
}

// resolveCountSco returns opts.CountSco or the real countScoGames default (the
// season-selection .sco game/team counter seam).
func resolveCountSco(opts Options) CountScoFunc {
	if opts.CountSco != nil {
		return opts.CountSco
	}
	return countScoGames
}
