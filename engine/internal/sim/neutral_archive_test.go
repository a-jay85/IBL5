//go:build archive

// Derivation harness for the foulCompress DEFENSIVE neutral reference
// (defQualityNeutral, teamquality.go). It walks a handful of real JSB backup
// seasons, selects each team's faithful five-pass starters, and runs them through
// the defensive quality aggregator at neutral HCA — the league-mean of the pre-cap
// def total is exactly the mean-preserving compression reference (a team AT the
// neutral is unchanged by any foulCompress; see the const provenance).
//
// The OFFENSIVE neutral derivation is GONE (ADR-0061): offQ is now the volume-
// neutral constant offQualityConstant, not a per-team summation to compress, so
// there is no off-side league mean to derive. The def side is unchanged.
//
// This is the .sco-corpus analog of how offVolumeNeutral=161 was derived (real
// per-starter composite means). It is build-tag gated behind `archive` so it is
// NEVER compiled by `go test ./...` or engine.yml.
//
// Invoke:
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/sim -run DeriveQualityNeutrals -v
//
// Override JSB_ARCHIVE_SEASONS to widen/narrow the season sample.
package sim

import (
	"archive/zip"
	"io"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// extractMember streams the first zip entry whose basename (case-insensitive)
// equals want into dest. Basename-only matching is zip-slip safe (the archive is
// trusted regardless).
func extractMember(zr *zip.ReadCloser, want, dest string) (bool, error) {
	for _, f := range zr.File {
		if !strings.EqualFold(filepath.Base(f.Name), want) {
			continue
		}
		rc, err := f.Open()
		if err != nil {
			return false, err
		}
		out, err := os.Create(dest)
		if err != nil {
			_ = rc.Close()
			return false, err
		}
		_, err = io.Copy(out, io.LimitReader(rc, 64<<20))
		_ = out.Close()
		_ = rc.Close()
		return err == nil, err
	}
	return false, nil
}

// loadRoster extracts IBL5.plr + IBL5.sch from a season zip and assembles the
// bundle (rosters + the team list), mirroring calibrate.loadSeasonBundle via the
// exported backup primitives. Returns nil on any snapshot that cannot be read
// (e.g. a partial / non-zip member) so the caller can skip it.
func loadRoster(t *testing.T, zipPath string) *bundle.Bundle {
	t.Helper()
	zr, err := zip.OpenReader(zipPath)
	if err != nil {
		return nil
	}
	defer func() { _ = zr.Close() }()

	tmp := t.TempDir()
	plrPath := filepath.Join(tmp, "IBL5.plr")
	schPath := filepath.Join(tmp, "IBL5.sch")
	if ok, _ := extractMember(zr, "IBL5.plr", plrPath); !ok {
		return nil
	}
	if ok, _ := extractMember(zr, "IBL5.sch", schPath); !ok {
		return nil
	}

	pf, err := os.Open(plrPath)
	if err != nil {
		return nil
	}
	defer func() { _ = pf.Close() }()
	players, err := backup.ReadPlr(pf)
	if err != nil {
		return nil
	}
	sf, err := os.Open(schPath)
	if err != nil {
		return nil
	}
	defer func() { _ = sf.Close() }()
	sched, err := backup.ReadSch(sf)
	if err != nil {
		return nil
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{GameType: bundle.GameTypeRegular})
	if err != nil {
		return nil
	}
	return &b
}

// TestDeriveQualityNeutrals logs the league-mean PRE-CAP defensive-quality
// reference and asserts the committed defQualityNeutral tracks the derived mean (so
// a future def rating-scale change that desyncs the neutral is caught). The off-side
// derivation was removed with ADR-0061 (offQ is a constant now). Never runs in CI
// (archive tag).
func TestDeriveQualityNeutrals(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	// One regular snapshot per season directory keeps the sample league-wide
	// without over-weighting any one season. The committed neutrals were DERIVED
	// from these 10 seasons (88-89…06-07, 269 team-snapshots); re-running with this
	// default reproduces them. Override JSB_ARCHIVE_SEASONS to vary the sample.
	seasons := []string{"88-89", "90-91", "92-93", "94-95", "96-97", "98-99", "00-01", "02-03", "04-05", "06-07"}
	if v := os.Getenv("JSB_ARCHIVE_SEASONS"); v != "" {
		seasons = strings.Split(v, ",")
	}

	var (
		defQSum         float64 // post-aggregator output (defQ capped)
		defPreCapSum    float64 // pre-cap def total (the compression space)
		nTeams, nCapped int
	)
	for _, season := range seasons {
		sdir := filepath.Join(dir, season)
		entries, err := os.ReadDir(sdir)
		if err != nil {
			t.Logf("season %s: %v (skipped)", season, err)
			continue
		}
		var zips []string
		for _, e := range entries {
			if strings.HasSuffix(strings.ToLower(e.Name()), ".zip") && strings.Contains(strings.ToLower(e.Name()), "reg") {
				zips = append(zips, filepath.Join(sdir, e.Name()))
			}
		}
		sort.Strings(zips)
		var b *bundle.Bundle
		for _, z := range zips { // first readable regular snapshot
			if b = loadRoster(t, z); b != nil {
				break
			}
		}
		if b == nil {
			t.Logf("season %s: no readable regular snapshot (skipped)", season)
			continue
		}
		ceiling := teamDefBaseline * defQualityCapTeamMult * defQualityCapMultiplier
		for _, tm := range b.Teams {
			starters := selectStarters(b.Players, tm.TeamID)
			if len(starters) < 5 {
				continue // short lineup — not a league-representative unit
			}
			var pre float64
			for _, p := range starters {
				pre += floor1(p.OD) * defQualityRatingScale
			}
			defPreCapSum += pre
			defQSum += defMatchupQuality(starters)
			if pre > ceiling {
				nCapped++
			}
			nTeams++
		}
	}
	if nTeams == 0 {
		t.Fatal("no teams sampled from the archive")
	}

	meanDefPreCap := defPreCapSum / float64(nTeams)
	meanDefOut := defQSum / float64(nTeams)
	capRate := float64(nCapped) / float64(nTeams)

	t.Logf("sampled teams=%d capped=%d (%.1f%%)", nTeams, nCapped, 100*capRate)
	t.Logf("DEF: mean pre-cap total=%.4f | mean post-cap output=%.4f", meanDefPreCap, meanDefOut)
	t.Logf("COMMITTED: defQualityNeutral=%.4f", defQualityNeutral)

	// Guard: the committed def neutral must track the derived corpus mean (the
	// mean-preservation premise). A wide band (±20%) tolerates the small season
	// sample while still catching a gross desync (e.g. a def rating-scale change that
	// left defQualityNeutral stale).
	if rel := relErr(defQualityNeutral, meanDefPreCap); rel > 0.20 {
		t.Errorf("defQualityNeutral %.4f is %.1f%% off the derived mean pre-cap total %.4f — re-derive", defQualityNeutral, 100*rel, meanDefPreCap)
	}
}

func relErr(got, want float64) float64 {
	if want == 0 {
		return 0
	}
	d := got - want
	if d < 0 {
		d = -d
	}
	return d / want
}
