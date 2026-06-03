//go:build archive

// REPORTED diagnostic (never a gate): does the archive's per-team offensive
// VOLUME composite correlate POSITIVELY with FGP, as ADR-0042 / the trace §3.1
// assumed (measured +0.265 on CURRENT dev-DB rosters, assumed cross-season
// stable)? The volume→count channel can only flip Cov(lnFGA,lnPPS) positive if
// this roster coupling is > 0 in the corpus. Run:
//
//	cd engine && JSB_ARCHIVE_DIR=…/ibl5/backups \
//	  go test -tags archive ./internal/calibrate -run VolumeFGPCoupling -v
package calibrate

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
)

func TestRealArchive_VolumeFGPCoupling(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}
	zips, _, err := listArchiveZips(dir)
	if err != nil {
		t.Fatalf("listArchiveZips: %v", err)
	}
	stride := envInt("JSB_ARCHIVE_STRIDE", 50)

	// Per (snapshot, team): real-minutes-weighted offensive volume composite
	// (RatingFGA+Rating3GA+RatingFTA) and FGP — the engine's channel input vs its
	// efficiency, as roster ratings (independent of any sim).
	var composite, fgp, fgaRate []float64
	snapshots := 0
	for i := 0; i < len(zips); i += stride {
		tmp, err := os.MkdirTemp("", "coupling-*")
		if err != nil {
			continue
		}
		found, err := extractTriple(zips[i], tmp)
		if err != nil || !found {
			_ = os.RemoveAll(tmp)
			continue
		}
		players := readPlrOrNil(filepath.Join(tmp, "IBL5.plr"))
		_ = os.RemoveAll(tmp)
		if players == nil {
			continue
		}
		snapshots++

		type acc struct{ vol, fgp, fga, min float64 }
		byTeam := map[int]*acc{}
		for _, p := range players {
			if p.RealLifeMIN <= 0 || p.TeamID < 1 || p.TeamID > 32 {
				continue
			}
			a := byTeam[p.TeamID]
			if a == nil {
				a = &acc{}
				byTeam[p.TeamID] = a
			}
			m := float64(p.RealLifeMIN)
			a.vol += m * float64(p.RatingFGA+p.Rating3GA+p.RatingFTA)
			a.fgp += m * float64(p.RatingFGP)
			a.fga += m * float64(p.RatingFGA)
			a.min += m
		}
		for _, a := range byTeam {
			if a.min <= 0 {
				continue
			}
			composite = append(composite, a.vol/a.min)
			fgp = append(fgp, a.fgp/a.min)
			fgaRate = append(fgaRate, a.fga/a.min)
		}
	}

	t.Logf("ARCHIVE ROSTER COUPLING (real-minutes weighted, N=%d teams over %d snapshots):", len(composite), snapshots)
	t.Logf("  corr(volume composite, FGP) = %+.4f   <-- ADR-0042 assumed > 0 (dev-DB was +0.265)", pearson(composite, fgp))
	t.Logf("  corr(r_fga, FGP)            = %+.4f", pearson(fgaRate, fgp))
}

// readPlrOrNil reads a .plr file, returning nil on any error (the caller skips).
func readPlrOrNil(path string) []backup.PlrPlayer {
	f, err := os.Open(path)
	if err != nil {
		return nil
	}
	defer func() { _ = f.Close() }()
	players, err := backup.ReadPlr(f)
	if err != nil {
		return nil
	}
	return players
}
