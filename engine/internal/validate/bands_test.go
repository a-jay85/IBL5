package validate

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// expectedTable maps each game type to the band table bandFor must resolve to:
// Regular and Playoff carry their own calibrated tables; RegularAlt and the two
// all-star types inherit a copy of the calibrated Regular table (buildBands).
var expectedTable = map[bundle.GameType]map[string]Band{
	bundle.GameTypeRegular:    regularBands,
	bundle.GameTypeRegularAlt: regularBands,
	bundle.GameTypePlayoff:    playoffBands,
	bundle.GameTypeAllStarA:   regularBands,
	bundle.GameTypeAllStarB:   regularBands,
}

// Row #2/#3: bandFor returns the calibrated band for the requested game type.
// Regular and playoff now diverge, so the test asserts each game type resolves
// to its expected table's value (playoff distinct from regular; alt/all-star
// inherit regular).
func TestBandFor_KnownGameTypeAndStat(t *testing.T) {
	for gt, table := range expectedTable {
		got := bandFor(gt, "points")
		want := table["points"]
		if got != want {
			t.Errorf("bandFor(%d, points) = %+v, want %+v", int(gt), got, want)
		}
	}
	// Guard the divergence is real: playoff points must differ from regular.
	if bandFor(bundle.GameTypePlayoff, "points") == bandFor(bundle.GameTypeRegular, "points") {
		t.Error("playoff and regular points bands are identical — calibration did not diverge")
	}
}

// Row #2: every statNames entry resolves to its explicit calibrated band under
// the regular table — no stat silently falls through to the generic fallback.
func TestBandFor_EveryStatHasExplicitBand(t *testing.T) {
	for _, name := range statNames {
		got := bandFor(bundle.GameTypeRegular, name)
		want, ok := regularBands[name]
		if !ok {
			t.Fatalf("regularBands missing an entry for stat %q", name)
		}
		if got != want {
			t.Errorf("bandFor(regular, %q) = %+v, want explicit %+v", name, got, want)
		}
	}
}

// Row #3 (boundary): an unknown game type falls back to the regular-season
// table — same band a regular lookup would return, not a zero Band.
func TestBandFor_UnknownGameTypeFallsBackToRegular(t *testing.T) {
	const unknown = bundle.GameType(99)
	got := bandFor(unknown, "reb")
	want := bandFor(bundle.GameTypeRegular, "reb")
	if got != want {
		t.Errorf("bandFor(unknown, reb) = %+v, want regular fallback %+v", got, want)
	}
	if got == (Band{}) {
		t.Error("unknown game type must not yield a zero (zero-width) band")
	}
}

// Row #3 (boundary): an unknown stat — even within a known game type — gets the
// generic ±15%/floor-3 defensive band, never a zero Band.
func TestBandFor_UnknownStatGetsGenericBand(t *testing.T) {
	got := bandFor(bundle.GameTypeRegular, "not_a_real_stat")
	want := Band{RelPct: 0.15, AbsFloor: 3}
	if got != want {
		t.Errorf("bandFor(regular, unknown stat) = %+v, want generic %+v", got, want)
	}
}

// Sanity-bound ceilings: generous round backstops against a decimal-place
// transcription error from the calibration JSON — NOT a fidelity claim. The
// landed maxima (2026-06-02 post-HCA run) are RelPct≈10.32 (blk, gt 4) and
// AbsFloor=48 (points, gt 2); these ceilings sit well above so a misplaced
// decimal (e.g. 103.2 or 480) trips the test.
const (
	relCeiling      = 50.0
	absFloorCeiling = 100.0
)

// Row #2: every landed band in all five game-type tables is structurally valid —
// every statNames entry is present (no missing stat), no band is zero-width or
// negative, and no band exceeds the transcription-backstop ceilings. This does
// NOT assert the bands are tight (many are deliberately wide — see the bands.go
// provenance note's documented current gap); it only catches a vacuous-infinite,
// zero, or fat-fingered band.
func TestBands_SanityBounds(t *testing.T) {
	allTypes := []bundle.GameType{
		bundle.GameTypeRegular, bundle.GameTypeRegularAlt, bundle.GameTypePlayoff,
		bundle.GameTypeAllStarA, bundle.GameTypeAllStarB,
	}
	for _, gt := range allTypes {
		table, ok := bands[gt]
		if !ok {
			t.Fatalf("bands has no table for game type %d", int(gt))
		}
		// Every canonical stat must have an explicit entry — no missing stat.
		for _, name := range statNames {
			b, ok := table[name]
			if !ok {
				t.Errorf("game type %d table missing stat %q", int(gt), name)
				continue
			}
			if b.RelPct < 0 || b.AbsFloor < 0 {
				t.Errorf("game type %d stat %q has a negative band %+v", int(gt), name, b)
			}
			if b.RelPct == 0 && b.AbsFloor == 0 {
				t.Errorf("game type %d stat %q is a zero-width band (never fails meaningfully)", int(gt), name)
			}
			if b.RelPct >= relCeiling {
				t.Errorf("game type %d stat %q RelPct %v >= ceiling %v (transcription error?)", int(gt), name, b.RelPct, relCeiling)
			}
			if b.AbsFloor >= absFloorCeiling {
				t.Errorf("game type %d stat %q AbsFloor %v >= ceiling %v (transcription error?)", int(gt), name, b.AbsFloor, absFloorCeiling)
			}
		}
	}
}
