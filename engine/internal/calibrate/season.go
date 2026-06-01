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
//     each later cumulative snapshot). So per season we take ONE snapshot.
//
//  2. Only REGULAR-season games can be validated today. The harness pairs each
//     .sco game to a .sch schedule entry to build the matchup; playoff (and
//     all-star) games are NOT in the .sch — playoff brackets are scheduled
//     dynamically, so those .sco games are unmatched and dropped. Validating
//     them would require synthesizing the matchup from the .sco's own team IDs,
//     a PR9b harness-contract change tracked separately. Until then this
//     collector emits the clean REGULAR bucket only: the season's last
//     regular-type snapshot (complete regular season, zero unmatched games).

// season is one season's selected regular snapshot. olympics seasons are
// recorded so they can be reported as skipped (out of scope, same unmatched
// constraint as playoffs).
type season struct {
	name       string
	olympics   bool
	regularZip string // last regular-type snapshot; "" if the season has none
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

// groupSeasons buckets zip paths into seasons, selecting each season's last
// regular-type snapshot by lexical (sim-step) order — valid because the
// archive's NN index is zero-padded, so the names sort by sim step. Olympics
// snapshots are flagged (and skipped: not yet supported, see the note above).
func groupSeasons(zipPaths []string, root string) ([]season, []Skip) {
	type acc struct {
		olympics   bool
		regularZip string
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
			skips = append(skips, Skip{p, "olympics/playoff not yet supported (unscheduled games — see season.go)"})
			continue
		}
		// Only regular-type snapshots seed the regular bucket; a playoff/finals
		// snapshot is ignored here (its playoff games are unmatched anyway).
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
		seasons = append(seasons, season{name: n, olympics: a.olympics, regularZip: a.regularZip})
	}
	return seasons, skips
}

// CollectSeasonReports is the calibration-correct collector: it groups the
// archive into seasons and validates ONE snapshot per season — the last
// regular-type snapshot, the complete regular season — as the clean regular
// bucket. --sample-stride thins by season. Playoff/all-star are out of scope
// until the harness can validate unscheduled games.
func CollectSeasonReports(root string, opts Options) ([]validate.Report, []Skip, error) {
	zips, zskips, err := listArchiveZips(root)
	if err != nil {
		return nil, nil, err
	}
	seasons, gskips := groupSeasons(zips, root)
	reports, pskips := collectSeasonReports(seasons, opts, resolveValidate(opts))
	skips := append(append(zskips, gskips...), pskips...)
	return reports, skips, nil
}

// collectSeasonReports is the injected-dependency core of CollectSeasonReports.
func collectSeasonReports(seasons []season, opts Options, validateFn ValidateFunc) ([]validate.Report, []Skip) {
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
		_, _ = fmt.Fprintf(progress, "regular %s games=%d\n", s.regularZip, len(rep.Games))
		reports = append(reports, *rep)
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
