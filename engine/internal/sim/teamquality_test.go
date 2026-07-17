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
	// 5 offense at TVR=70, RealLifeMIN=0 → fallback: 5·4.6944002 = 23.472001.
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

// --- J22: real per-player composite path (RealLifeMIN > 0) -------------------

// ocWithReal builds an on-court player with real career stats wired (RealLifeMIN > 0),
// so stlRate/tovRate use the J22 real-composite path instead of the rating stand-in.
func ocWithReal(stl, tvr, min int) onCourt {
	p := mkPlayer(1, 3, slotPG, 48)
	p.RealLifeSTL = stl
	p.RealLifeTVR = tvr
	p.RealLifeMIN = min
	return oc(slotPG, p)
}

// TestTeamQuality_RealComposite_HappyPath: stlRate/tovRate with RealLifeMIN>0 equal
// hand-computed STL/MIN×44 and TOV/MIN×48.
func TestTeamQuality_RealComposite_HappyPath(t *testing.T) {
	p := ocWithReal(110, 150, 2000)
	// stlRate = 110/2000 × 44 = 2.42
	wantSTL := 110.0 / 2000.0 * 44.0
	if got := stlRate(p); math.Abs(got-wantSTL) > teamQualityEps {
		t.Errorf("stlRate(real) = %.6f, want %.6f", got, wantSTL)
	}
	// tovRate = 150/2000 × 48 = 3.6
	wantTOV := 150.0 / 2000.0 * 48.0
	if got := tovRate(p); math.Abs(got-wantTOV) > teamQualityEps {
		t.Errorf("tovRate(real) = %.6f, want %.6f", got, wantTOV)
	}
}

// TestTeamQuality_DivByZeroGuard: RealLifeSTL>0, RealLifeTVR>0, RealLifeMIN==0
// returns rating fallback (not NaN/Inf) — guards division by zero.
func TestTeamQuality_DivByZeroGuard(t *testing.T) {
	p := mkPlayer(1, 3, slotPG, 48)
	p.RealLifeSTL = 110
	p.RealLifeTVR = 150
	p.RealLifeMIN = 0 // guard must select fallback, not divide by zero
	oc0 := oc(slotPG, p)

	stl := stlRate(oc0)
	tov := tovRate(oc0)
	if math.IsNaN(stl) || math.IsInf(stl, 0) {
		t.Errorf("stlRate(RealLifeMIN=0) = %v, want finite fallback", stl)
	}
	if math.IsNaN(tov) || math.IsInf(tov, 0) {
		t.Errorf("tovRate(RealLifeMIN=0) = %v, want finite fallback", tov)
	}
	// Must equal the rating stand-in (same as mkPlayer's STL=30, TVR=70).
	wantSTL := floor1(p.STL) / ratingRefScale * leagueSTL48
	wantTOV := floor1(p.TVR) / ratingRefScale * leagueTOV48
	if math.Abs(stl-wantSTL) > teamQualityEps {
		t.Errorf("stlRate fallback = %.6f, want %.6f (rating stand-in)", stl, wantSTL)
	}
	if math.Abs(tov-wantTOV) > teamQualityEps {
		t.Errorf("tovRate fallback = %.6f, want %.6f (rating stand-in)", tov, wantTOV)
	}
}

// TestTeamQuality_CapBoundary_RealPath: high-real-STL lineup still hits defQ cap.
func TestTeamQuality_CapBoundary_RealPath(t *testing.T) {
	ceiling := defQualityCapMultiplier * defQualityCapTeamMult * leagueSTL48
	// Very high real STL/MIN ratio → uncapped sum > ceiling → capped.
	var hi []onCourt
	for slot := slotPG; slot <= slotC; slot++ {
		hi = append(hi, ocWithReal(500, 10, 100)) // 500/100×44 = 220 per player
	}
	if raw := 5 * (500.0 / 100.0 * 44.0); raw <= ceiling {
		t.Fatalf("test setup: raw high-real-STL %.3f should exceed ceiling %.3f", raw, ceiling)
	}
	if got := defQuality(hi); math.Abs(got-ceiling) > teamQualityEps {
		t.Errorf("defQuality (real, capped) = %.4f, want ceiling %.4f", got, ceiling)
	}
}

// TestTeamQuality_DispersionFlows: mixed-real lineup produces defQ strictly between
// the min and max single-player contributions — proves real per-player spread flows through.
func TestTeamQuality_DispersionFlows(t *testing.T) {
	// Five players with different real STL/MIN ratios.
	lineup := []onCourt{
		ocWithReal(50, 100, 2000),  // stlRate = 50/2000×44 = 1.1
		ocWithReal(100, 100, 2000), // stlRate = 100/2000×44 = 2.2
		ocWithReal(150, 100, 2000), // stlRate = 150/2000×44 = 3.3
		ocWithReal(200, 100, 2000), // stlRate = 200/2000×44 = 4.4
		ocWithReal(250, 100, 2000), // stlRate = 250/2000×44 = 5.5
	}
	minContrib := 50.0 / 2000.0 * 44.0
	maxContrib := 250.0 / 2000.0 * 44.0
	got := defQuality(lineup)
	if got <= minContrib || got >= 5*maxContrib {
		t.Errorf("defQ = %.4f, want strictly between min contrib %.4f and 5×max %.4f — dispersion broken",
			got, minContrib, 5*maxContrib)
	}
	// Specifically, expect sum = (50+100+150+200+250)/2000×44 = 750/2000×44 = 16.5,
	// but the cap is 13.755 so we get the ceiling.
	// With different numbers that don't cap:
	lineup2 := []onCourt{
		ocWithReal(10, 100, 2000), // 0.22
		ocWithReal(20, 100, 2000), // 0.44
		ocWithReal(30, 100, 2000), // 0.66
		ocWithReal(40, 100, 2000), // 0.88
		ocWithReal(50, 100, 2000), // 1.1
	}
	got2 := defQuality(lineup2)
	min2 := 10.0 / 2000.0 * 44.0
	max2 := 50.0 / 2000.0 * 44.0
	if got2 <= min2 || got2 >= 5*max2+teamQualityEps {
		t.Errorf("defQ2 = %.4f, want strictly between %.4f and %.4f — dispersion broken",
			got2, min2, 5*max2)
	}
}
