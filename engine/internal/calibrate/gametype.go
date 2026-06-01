// Package calibrate is the offline band-calibration harness (PR9c). It walks a
// directory tree of JSB 5.60 backup zips (each zip carries a complete
// IBL5.plr/.sch/.sco triple), extracts each triple one zip at a time, runs the
// PR9b validation harness (internal/validate) on it under the game type inferred
// from the snapshot's path, and aggregates the engine-mean-vs-.sco residuals
// SEGMENTED BY GAME TYPE to derive calibrated tolerance bands.
//
// Two modes (exposed by cmd/jsbcalibrate):
//   - calibrate: emit proposed per-game-type bands (percentile of residuals).
//   - gate:      apply the committed validate bands across the archive and
//     report per-game-type in-band pass rates.
//
// The package logic is unit-tested with synthetic in-memory zips. The full run
// over the real ~53 GB ibl5/backups archive is a developer-/nightly-invoked
// smoke test (realarchive_test.go, build-tag `archive`) — it is NOT wired into
// CI, since the corpus is large and not in the repo.
package calibrate

import (
	"path/filepath"
	"strings"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// inferGameType derives the JSB game type for a snapshot from its archive path,
// since neither the .sch nor the .sco carries one. It returns (type, true) for a
// snapshot to process, or (0, false) for one to skip — currently only an
// Olympics snapshot while includeOlympics is off.
//
// The mapping is intentionally coarse and path-based:
//   - any path under an "olympics" segment  -> all-star (6), opt-in only;
//   - any path naming a playoff round/final -> playoff (4);
//   - everything else (pre-heat, heat, reg-sim, …) -> regular (2).
//
// NOTE (assumption): Olympics is mapped to all-star type 6, but JSB's actual
// Olympics game type is unconfirmed and Olympics is not an exhibition all-star,
// so it stays opt-in and out of the default regular/playoff calibration to avoid
// polluting it.
func inferGameType(path string, includeOlympics bool) (bundle.GameType, bool) {
	p := strings.ToLower(filepath.ToSlash(path))
	switch {
	case strings.Contains(p, "olympics"):
		if !includeOlympics {
			return 0, false
		}
		return bundle.GameTypeAllStarB, true
	case strings.Contains(p, "playoff"), strings.Contains(p, "finals"):
		return bundle.GameTypePlayoff, true
	default:
		return bundle.GameTypeRegular, true
	}
}
