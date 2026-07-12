package sim

import (
	"math"
	"testing"
)

// fiveStarters builds a 5-man lineup of identical starters (mkPlayer defaults:
// STL=30, TVR=70) for the given team, used to exercise the lineup aggregators.
func fiveStarters(team int) []onCourt {
	var lineup []onCourt
	for slot := slotPG; slot <= slotC; slot++ {
		lineup = append(lineup, oc(slot, mkPlayer(slot, team, slot, 48)))
	}
	return lineup
}

const teamQualityEps = 1e-9

// --- faithful side-symmetric aggregators (J6/J16) ----------------------------
//
// stlRate/tovRate map the 0-99 STL/TVR ratings to per-48 rate stand-ins anchored
// (via ratingRefScale) to the real league per-48 means. defQuality sums stlRate
// over the 5 defenders (capped); offQuality sums tovRate over the offense, with NO
// home/away term (J16 §3). Concrete literals are hand-computed independently of the
// implementation so a drifted scale/mean fails here.
func TestTeamQuality_RateStandIns(t *testing.T) {
	p := oc(slotPG, mkPlayer(1, 3, slotPG, 48)) // STL=30, TVR=70
	// stlRate = 30/50·1.834 = 0.6·1.834 = 1.1004
	if got := stlRate(p); math.Abs(got-1.1004) > teamQualityEps {
		t.Errorf("stlRate(STL=30) = %.6f, want 1.1004", got)
	}
	// tovRate = 70/50·3.353143 = 1.4·3.353143 = 4.6944002
	if got := tovRate(p); math.Abs(got-4.6944002) > teamQualityEps {
		t.Errorf("tovRate(TVR=70) = %.7f, want 4.6944002", got)
	}
	// floor1 floors the rating at 1, so a 0-rated player still yields a positive rate
	// (offQuality > 0 is what guards the shrink's divide).
	z := oc(slotPG, mkPlayer(1, 3, slotPG, 48))
	z.STL, z.TVR = 0, 0
	if stlRate(z) <= 0 || tovRate(z) <= 0 {
		t.Errorf("floor1 broken: stlRate/tovRate of a 0-rated player must stay > 0 (got %.6f / %.6f)", stlRate(z), tovRate(z))
	}
}

// TestDefQuality_SumAndCap: defQuality = min(Σ_5 stlRate, cap), cap =
// defQualityCapMultiplier·defQualityCapTeamMult·leagueSTL48 = 1.5·5·1.834 = 13.755.
func TestDefQuality_SumAndCap(t *testing.T) {
	ceiling := defQualityCapMultiplier * defQualityCapTeamMult * leagueSTL48

	// Uncapped: 5 defenders at STL=30 → 5·1.1004 = 5.502 < ceiling.
	def := fiveStarters(7)
	if got := defQuality(def); math.Abs(got-5.502) > teamQualityEps {
		t.Errorf("defQuality(STL=30 ×5) = %.4f, want 5.502 (uncapped)", got)
	}

	// Capped: 5 defenders at STL=99 → 5·(99/50·1.834) = 18.1566 > ceiling → returns ceiling.
	hi := fiveStarters(7)
	for i := range hi {
		hi[i].STL = 99
	}
	if raw := 5 * floor1(99) / ratingRefScale * leagueSTL48; raw <= ceiling {
		t.Fatalf("test setup: raw high-STL %.3f should exceed ceiling %.3f", raw, ceiling)
	}
	if got := defQuality(hi); math.Abs(got-ceiling) > teamQualityEps {
		t.Errorf("defQuality (capped) = %.4f, want ceiling %.4f", got, ceiling)
	}
}

// TestOffQuality_SumNoHCA: offQuality = Σ tovRate, and — the J16 §3 property — it
// depends ONLY on the lineup, never on any home/away input (there is no hca arg).
func TestOffQuality_SumNoHCA(t *testing.T) {
	off := fiveStarters(3)
	// 5 offense at TVR=70 → 5·4.6944002 = 23.472001.
	if got := offQuality(off); math.Abs(got-23.472001) > teamQualityEps {
		t.Errorf("offQuality(TVR=70 ×5) = %.5f, want 23.472001", got)
	}
	// Side-symmetry sanity: the SAME lineup used as "home offense" or "away offense"
	// yields the identical offQ (there is no channel to differ) — the structural
	// property that makes the foul bucket produce a ≈1.0 home/away FTA ratio.
	if offQuality(fiveStarters(3)) != offQuality(fiveStarters(99)) {
		t.Errorf("offQuality varies with team id — it must depend only on ratings (side-symmetric)")
	}
}
