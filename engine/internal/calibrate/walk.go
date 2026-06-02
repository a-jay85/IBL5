package calibrate

import (
	"archive/zip"
	"fmt"
	"io"
	"io/fs"
	"os"
	"path/filepath"
	"strings"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// maxEntryBytes caps a single extracted entry, bounding decompression even for a
// trusted archive. The real .sco is ~8 MB; this leaves ample headroom.
const maxEntryBytes = 128 << 20

// canonicalMember maps a lowercased member basename to the canonical name it is
// extracted as. Writing canonical names (not the zip's own casing) guarantees a
// stable stem for validate.findTriples and a known path for the season reader's
// backup.ReadSco. IBL5.plb (depth chart) is extracted when present but is NOT
// required (see requiredMembers) — a snapshot lacking it validates with zero
// minutes, reported by the harness as MissingPlb.
var canonicalMember = map[string]string{
	"ibl5.plr": "IBL5.plr",
	"ibl5.sch": "IBL5.sch",
	"ibl5.sco": "IBL5.sco",
	"ibl5.plb": "IBL5.plb",
}

// requiredMembers are the canonical members that MUST all be present for a
// snapshot to be validatable. The optional IBL5.plb is intentionally absent.
var requiredMembers = []string{"IBL5.plr", "IBL5.sch", "IBL5.sco"}

// ValidateFunc is the seam to internal/validate.ValidateCorpus, injected so the
// walk's extraction/inference/skip logic is unit-testable without running the
// engine. A nil Options.Validate defaults to validate.ValidateCorpus.
type ValidateFunc func(dir string, runs int, seed uint64, gt bundle.GameType) (validate.Report, error)

// Options configures an archive walk.
type Options struct {
	Runs                int          // seeded engine runs per corpus game
	Seed                uint64       // base seed
	SampleStride        int          // process every Nth qualifying snapshot (<1 -> 1)
	IncludeOlympics     bool         // include olympics/ snapshots (game-type 6)
	Validate            ValidateFunc // nil -> validate.ValidateCorpus (regular/scheduled bucket)
	ValidateUnscheduled ValidateFunc // nil -> validate.ValidateUnscheduled (playoff bucket)
	Progress            io.Writer    // nil -> io.Discard; one line per processed snapshot
}

// Skip records a snapshot (or archive entry) that was not turned into a Report,
// so nothing is silently dropped: an unreadable archive, a missing triple
// member, or an Olympics snapshot excluded by default all surface here.
type Skip struct {
	Path   string
	Reason string
}

// CollectReports walks root for *.zip backups in deterministic (lexical) order,
// extracts each snapshot's IBL5.{plr,sch,sco} triple one zip at a time into a
// fresh temp dir (deleted each iteration — the 53 GB archive is never exploded),
// infers the game type from the path, and runs the validation harness on it.
// It returns every produced Report (each stamped with its game type) plus the
// Skips for snapshots that could not be validated. A hard filesystem error
// (e.g. an unreadable root) is returned as err; per-zip problems become Skips.
func CollectReports(root string, opts Options) ([]validate.Report, []Skip, error) {
	validateFn := resolveValidate(opts)
	progress := opts.Progress
	if progress == nil {
		progress = io.Discard
	}
	stride := opts.SampleStride
	if stride < 1 {
		stride = 1
	}

	zips, skips, err := listArchiveZips(root)
	if err != nil {
		return nil, nil, err
	}

	var (
		reports    []validate.Report
		qualifying int
	)
	for _, path := range zips {
		gt, ok := inferGameType(path, opts.IncludeOlympics)
		if !ok {
			skips = append(skips, Skip{path, "olympics snapshot excluded (use --include-olympics)"})
			continue
		}
		// Stride thinning applies to qualifying snapshots only, so excluding
		// Olympics never consumes a stride slot.
		idx := qualifying
		qualifying++
		if idx%stride != 0 {
			continue
		}
		rep, skip := processZip(path, gt, opts.Runs, opts.Seed, validateFn)
		if skip != nil {
			skips = append(skips, *skip)
			continue
		}
		rep.Label = strings.TrimSuffix(filepath.Base(path), filepath.Ext(path))
		_, _ = fmt.Fprintf(progress, "processed %s game_type=%d games=%d\n", path, int(gt), len(rep.Games))
		reports = append(reports, *rep)
	}
	return reports, skips, nil
}

// processZip extracts one zip's triple to a temp dir and validates it. It
// returns either a Report or a Skip (never both). The temp dir is always
// removed before returning.
//
// Pairing note: a snapshot's IBL5.plr is the POST-sim rating state for the games
// in its IBL5.sco. This is exact for calibration, not approximate: IBL player
// ratings are held constant across a whole season (the only in-season changes —
// injury-driven rating drops in JSB — are manually reverted to the pre-injury
// values), so the post-sim .plr equals the ratings every game in the .sco was
// played under. Re-simulating those games from the same-zip .plr therefore uses
// the correct pre-game ratings.
func processZip(path string, gt bundle.GameType, runs int, seed uint64, validateFn ValidateFunc) (*validate.Report, *Skip) {
	tmp, err := os.MkdirTemp("", "jsbcal-*")
	if err != nil {
		return nil, &Skip{path, "mkdir temp: " + err.Error()}
	}
	defer func() { _ = os.RemoveAll(tmp) }()

	found, err := extractTriple(path, tmp)
	if err != nil {
		return nil, &Skip{path, "extract: " + err.Error()}
	}
	if !found {
		return nil, &Skip{path, "missing one of IBL5.{plr,sch,sco}"}
	}
	rep, err := validateFn(tmp, runs, seed, gt)
	if err != nil {
		return nil, &Skip{path, "validate: " + err.Error()}
	}
	return &rep, nil
}

// extractTriple writes the canonical members from zipPath into destDir,
// returning found=true only when all requiredMembers are present (the optional
// IBL5.plb is extracted when present but never gates found). Entries are written
// by BASENAME ONLY (filepath.Base), so a crafted entry name like "../../IBL5.plr"
// cannot escape destDir (zip-slip safe) even though the archive is trusted.
func extractTriple(zipPath, destDir string) (bool, error) {
	zr, err := zip.OpenReader(zipPath)
	if err != nil {
		return false, err
	}
	defer func() { _ = zr.Close() }()

	got := map[string]bool{}
	for _, f := range zr.File {
		canon, ok := canonicalMember[strings.ToLower(filepath.Base(f.Name))]
		if !ok {
			continue
		}
		// Basename-only join (canon is a literal constant) keeps extraction
		// inside destDir — zip-slip safe even for a crafted entry name.
		if err := extractOne(f, filepath.Join(destDir, canon)); err != nil {
			return false, err
		}
		got[canon] = true
	}
	for _, canon := range requiredMembers {
		if !got[canon] {
			return false, nil
		}
	}
	return true, nil
}

// extractOne streams one zip entry to dest, bounded by maxEntryBytes.
func extractOne(f *zip.File, dest string) error {
	rc, err := f.Open()
	if err != nil {
		return err
	}
	defer func() { _ = rc.Close() }()
	out, err := os.Create(dest)
	if err != nil {
		return err
	}
	if _, err := io.Copy(out, io.LimitReader(rc, maxEntryBytes)); err != nil {
		_ = out.Close()
		return err
	}
	return out.Close()
}

// listArchiveZips walks root and returns every *.zip path in deterministic
// (lexical) order, plus a Skip for each non-zip archive it cannot read (so a
// .rar is surfaced, not silently ignored like ordinary junk). A hard filesystem
// error (e.g. an unreadable root) is returned as err.
func listArchiveZips(root string) ([]string, []Skip, error) {
	var files []string
	if err := filepath.WalkDir(root, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if !d.IsDir() {
			files = append(files, path)
		}
		return nil
	}); err != nil {
		return nil, nil, fmt.Errorf("calibrate: walk %q: %w", root, err)
	}
	var zips []string
	var skips []Skip
	for _, path := range files {
		switch ext := strings.ToLower(filepath.Ext(path)); {
		case ext == ".zip":
			zips = append(zips, path)
		case isUnsupportedArchive(ext):
			skips = append(skips, Skip{path, "unsupported archive format (cannot read " + ext + ")"})
		}
	}
	return zips, skips, nil
}

// isUnsupportedArchive reports whether ext names an archive format the walk
// cannot read (so it is recorded as a Skip rather than silently ignored like
// ordinary junk files, e.g. .DS_Store).
func isUnsupportedArchive(ext string) bool {
	switch ext {
	case ".rar", ".7z", ".tar", ".gz", ".tgz", ".bz2":
		return true
	}
	return false
}
