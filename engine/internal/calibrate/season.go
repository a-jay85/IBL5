package calibrate

import (
	"fmt"
	"io"
	"path/filepath"
	"sort"
	"strings"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// A snapshot's .sco is CUMULATIVE — a late snapshot holds every game of the
// season to date (verified on the real archive: reg-sim01=49 games,
// reg-sim13=549, last reg-sim=1148, the full regular season). Two facts shape
// the season-grouped collector:
//
//  1. Processing every snapshot would double-count early games (re-counted in
//     each later cumulative snapshot). So per season we take ONE snapshot per
//     bucket: the last regular-type snapshot for the regular bucket, and the
//     finals (last overall) snapshot for the playoff bucket.
//
//  2. REGULAR games are validated via validate.ValidateCorpus, which pairs each
//     .sco game to a .sch schedule entry. PLAYOFF games are NOT in the .sch
//     (playoff brackets are scheduled dynamically, from Schedule.htm in prod),
//     so they are validated via validate.ValidateUnscheduled — the complement
//     path that synthesizes each unmatched .sco game's matchup from its own
//     team IDs (PR9d). All-star/Olympics calibration is still out of scope:
//     Olympics national-team rosters are not a clean .plr-franchise sim.

// season is one season's selected snapshots. olympics seasons are recorded so
// they can be reported as skipped (out of scope — national-team rosters need
// separate handling). finalsZip is the last snapshot overall (lexical/sim-step
// order); when it differs from regularZip it carries the playoff games.
type season struct {
	name       string
	olympics   bool
	regularZip string // last regular-type snapshot; "" if the season has none
	finalsZip  string // last snapshot overall; "" if the season has none
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

// groupSeasons buckets zip paths into seasons, selecting two snapshots per
// season by lexical (sim-step) order — valid because the archive's NN index is
// zero-padded, so the names sort by sim step: the last regular-type snapshot
// (regularZip, the complete regular season) and the last snapshot overall
// (finalsZip, which carries the playoff games when a finals snapshot exists).
// Olympics snapshots are flagged (and skipped: not yet supported, see the note
// above).
func groupSeasons(zipPaths []string, root string) ([]season, []Skip) {
	type acc struct {
		olympics   bool
		regularZip string
		finalsZip  string
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
		// The last snapshot overall is the finals snapshot (playoff bucket); the
		// last regular-type snapshot seeds the regular bucket. When the season has
		// no finals snapshot, finalsZip == regularZip and no playoff bucket forms.
		if p > a.finalsZip {
			a.finalsZip = p
		}
		if gt, _ := inferGameType(p, false); gt == bundle.GameTypeRegular && p > a.regularZip {
			a.regularZip = p
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
		seasons = append(seasons, season{name: n, olympics: a.olympics, regularZip: a.regularZip, finalsZip: a.finalsZip})
	}
	return seasons, skips
}

// CollectSeasonReports is the calibration-correct collector: it groups the
// archive into seasons and, per season, validates the last regular-type
// snapshot as the REGULAR bucket and (when a distinct finals snapshot exists)
// its unmatched .sco games as the PLAYOFF bucket. --sample-stride thins by
// season; both buckets for a selected season are produced together. All-star
// and Olympics remain out of scope.
func CollectSeasonReports(root string, opts Options) ([]validate.Report, []Skip, error) {
	zips, zskips, err := listArchiveZips(root)
	if err != nil {
		return nil, nil, err
	}
	seasons, gskips := groupSeasons(zips, root)
	reports, pskips := collectSeasonReports(seasons, opts, resolveValidate(opts), resolveValidateUnscheduled(opts))
	skips := append(append(zskips, gskips...), pskips...)
	return reports, skips, nil
}

// collectSeasonReports is the injected-dependency core of CollectSeasonReports.
// validateFn handles the regular (scheduled) bucket; validateUnscheduledFn
// handles the playoff (unscheduled) bucket — both seams so the season grouping
// and bucket selection are unit-testable without running the engine.
func collectSeasonReports(seasons []season, opts Options, validateFn, validateUnscheduledFn ValidateFunc) ([]validate.Report, []Skip) {
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
		if s.olympics || s.regularZip == "" {
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
		rep, skip := processZip(s.regularZip, bundle.GameTypeRegular, opts.Runs, opts.Seed, validateFn)
		if skip != nil {
			skips = append(skips, *skip)
			continue
		}
		rep.Label = s.name
		_, _ = fmt.Fprintf(progress, "regular %s games=%d\n", s.regularZip, len(rep.Games))
		reports = append(reports, *rep)

		// Playoff bucket: the finals snapshot's unmatched .sco games, validated
		// via the unscheduled path. Only when a distinct finals snapshot exists
		// (otherwise finalsZip == regularZip = no playoffs captured).
		if s.finalsZip != "" && s.finalsZip != s.regularZip {
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
	return reports, skips
}

// resolveValidate returns opts.Validate or the real ValidateCorpus default.
func resolveValidate(opts Options) ValidateFunc {
	if opts.Validate != nil {
		return opts.Validate
	}
	return validate.ValidateCorpus
}

// resolveValidateUnscheduled returns opts.ValidateUnscheduled or the real
// validate.ValidateUnscheduled default (the playoff-bucket seam).
func resolveValidateUnscheduled(opts Options) ValidateFunc {
	if opts.ValidateUnscheduled != nil {
		return opts.ValidateUnscheduled
	}
	return validate.ValidateUnscheduled
}
