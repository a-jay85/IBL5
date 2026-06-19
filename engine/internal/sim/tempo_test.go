package sim

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// ocVol builds a one-player on-court slice entry whose offensive volume composite
// (Σ r_fga+r_3ga+r_fta) is off and whose defensive composite (Σ OD+DD+PD+TD) is
// def — all loaded into one rating each so the team-average equals off/def.
func ocVol(off, def int) onCourt {
	return onCourt{Player: bundle.Player{FGA: off, OD: def}}
}

func lineup(offPerStarter, defPerStarter int) []onCourt {
	s := make([]onCourt, 5)
	for i := range s {
		s[i] = ocVol(offPerStarter, defPerStarter)
	}
	return s
}

// TestTeamBaseTime_OffensiveVolumeShortensPace is the channel's core monotone
// property: a higher offensive volume composite yields a STRICTLY shorter
// base_time (faster pace → more possessions → more FGA), all else equal. Values
// straddle the neutral 161 so both land strictly inside the open (13,16) clamp.
func TestTeamBaseTime_OffensiveVolumeShortensPace(t *testing.T) {
	const def = 24 // neutral defense for both
	high := teamBaseTime(lineup(175, def))
	low := teamBaseTime(lineup(147, def))
	if !(high < low) {
		t.Fatalf("higher offensive volume must shorten base_time: high-vol=%.4f low-vol=%.4f", high, low)
	}
	for name, bt := range map[string]float64{"high": high, "low": low} {
		if bt <= baseTimeLow || bt >= baseTimeHigh {
			t.Fatalf("%s-vol base_time %.4f not strictly inside (%.1f,%.1f)", name, bt, baseTimeLow, baseTimeHigh)
		}
	}
	// A neutral roster (real composite means) lands exactly at baseTimeMid.
	if neutral := teamBaseTime(lineup(int(offVolumeNeutral), int(defRatingNeutral))); math.Abs(neutral-baseTimeMid) > 1e-9 {
		t.Fatalf("neutral roster base_time = %.6f, want %.1f", neutral, baseTimeMid)
	}
	// Stronger defense lengthens the pace (the minor, faithful defensive term).
	slowD := teamBaseTime(lineup(int(offVolumeNeutral), 30))
	fastD := teamBaseTime(lineup(int(offVolumeNeutral), 20))
	if !(slowD > fastD) {
		t.Fatalf("stronger defense must lengthen base_time: strongD=%.4f weakD=%.4f", slowD, fastD)
	}
}

// TestTeamBaseTime_ZeroRatedNoNaN is the degenerate boundary: an all-zero-rated
// lineup (zero offensive AND zero defensive composite) must still produce a
// finite base_time inside [13,16] — no NaN/Inf — and an empty lineup returns the
// low bound.
func TestTeamBaseTime_ZeroRatedNoNaN(t *testing.T) {
	zero := teamBaseTime(lineup(0, 0))
	if math.IsNaN(zero) || math.IsInf(zero, 0) {
		t.Fatalf("all-zero lineup base_time is non-finite: %v", zero)
	}
	if zero < baseTimeLow || zero > baseTimeHigh {
		t.Fatalf("all-zero lineup base_time %.4f outside [%.1f,%.1f]", zero, baseTimeLow, baseTimeHigh)
	}
	if empty := teamBaseTime(nil); empty != baseTimeLow {
		t.Fatalf("empty lineup base_time = %.4f, want %.1f", empty, baseTimeLow)
	}
	// An extreme-high-volume lineup clamps to the low bound (no underflow below 13).
	if hot := teamBaseTime(lineup(400, 24)); hot != baseTimeLow {
		t.Fatalf("extreme-volume lineup base_time = %.4f, want clamp to %.1f", hot, baseTimeLow)
	}
}

// TestTeamBaseTimeWith_ConstWrapperIdentity is the ADR-0054 refactor characterization:
// teamBaseTimeWith called with the package const offVolumeScale must reproduce
// teamBaseTime byte-for-byte, so the const-wrapper split is behavior-preserving and a
// nil Options.OffVolumeScale stays golden-stable.
func TestTeamBaseTimeWith_ConstWrapperIdentity(t *testing.T) {
	for _, l := range [][]onCourt{
		lineup(175, 24), lineup(147, 30), lineup(int(offVolumeNeutral), int(defRatingNeutral)),
		lineup(0, 0), nil,
	} {
		if w, c := teamBaseTimeWith(l, offVolumeScale), teamBaseTime(l); w != c {
			t.Fatalf("teamBaseTimeWith(_, const)=%.9f != teamBaseTime(_)=%.9f", w, c)
		}
	}
}

// TestTeamBaseTimeWith_ScaleBoundary is the scale-aware boundary: a larger sweep scale
// never escapes the [13,16] clamp, and the empty-lineup guard is scale-independent.
func TestTeamBaseTimeWith_ScaleBoundary(t *testing.T) {
	if hot := teamBaseTimeWith(lineup(400, 24), 0.06); hot != baseTimeLow {
		t.Fatalf("scale=0.06 extreme-volume base_time = %.4f, want clamp to %.1f", hot, baseTimeLow)
	}
	if empty := teamBaseTimeWith(nil, 0.06); empty != baseTimeLow {
		t.Fatalf("scale=0.06 empty lineup base_time = %.4f, want %.1f", empty, baseTimeLow)
	}
	// scale=0 disables the offensive-volume term: a high-volume roster no longer
	// shortens below a neutral one (the channel is off), but stays finite in-clamp.
	off0 := teamBaseTimeWith(lineup(200, int(defRatingNeutral)), 0)
	if math.Abs(off0-baseTimeMid) > 1e-9 {
		t.Fatalf("scale=0 should null the volume term → baseTimeMid, got %.6f", off0)
	}
}

// TestPossessionTime_FallbackBounds locks the (2.0−factor) form and the 24.0
// out-of-range fallback: an in-range base_time passes through (truncated to int),
// while a base_time below 1.0 or above 24.0 resets to the JSB fallback of 24.
func TestPossessionTime_FallbackBounds(t *testing.T) {
	if got := possessionTime(14.0); got != 14 {
		t.Fatalf("in-range possessionTime(14.0) = %d, want 14", got)
	}
	if got := possessionTime(13.0); got != 13 {
		t.Fatalf("possessionTime(13.0) = %d, want 13", got)
	}
	if got := possessionTime(25.0); got != 24 {
		t.Fatalf("over-range possessionTime(25.0) = %d, want 24 fallback", got)
	}
	if got := possessionTime(0.5); got != 24 {
		t.Fatalf("under-range possessionTime(0.5) = %d, want 24 fallback", got)
	}
}
