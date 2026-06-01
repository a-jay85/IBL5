package validate

import (
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// Row #2: bandFor returns the band from the requested game type's table. The
// placeholder values are currently identical across game types, so the test
// asserts the lookup hits a populated entry for each known type rather than a
// distinct numeric value (calibration will diverge the values later).
func TestBandFor_KnownGameTypeAndStat(t *testing.T) {
	for _, gt := range []bundle.GameType{
		bundle.GameTypeRegular, bundle.GameTypeRegularAlt,
		bundle.GameTypePlayoff, bundle.GameTypeAllStarA, bundle.GameTypeAllStarB,
	} {
		got := bandFor(gt, "points")
		want := placeholderBands["points"]
		if got != want {
			t.Errorf("bandFor(%d, points) = %+v, want %+v", int(gt), got, want)
		}
	}
}

// Row #2: every statNames entry resolves to its explicit placeholder band under
// the regular table — no stat silently falls through to the generic fallback.
func TestBandFor_EveryStatHasExplicitBand(t *testing.T) {
	for _, name := range statNames {
		got := bandFor(bundle.GameTypeRegular, name)
		want, ok := placeholderBands[name]
		if !ok {
			t.Fatalf("placeholderBands missing an entry for stat %q", name)
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
