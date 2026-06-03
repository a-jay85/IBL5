package calibrate

import (
	"math"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// matrix #7 — mechanism-rate folding correctness.
//
// foldMechRates must count exactly the offense-attributed within-possession events
// and ignore the rest: a defensive rebound is NOT an oreb-continuation, and an
// and-one free throw (FTAttempts==1) is NOT a foul-only trip (FTAttempts==2).
func TestFoldMechRates(t *testing.T) {
	const team = 7
	events := []result.Event{
		{Kind: result.EventPossessionStart, TeamID: team},
		{Kind: result.EventPossessionStart, TeamID: team},
		{Kind: result.EventPossessionStart, TeamID: team},
		{Kind: result.EventTurnover, TeamID: team},
		{Kind: result.EventTurnover, TeamID: team},
		{Kind: result.EventRebound, TeamID: team, OffensiveRebound: true},
		{Kind: result.EventRebound, TeamID: team, OffensiveRebound: false}, // defensive — ignored
		{Kind: result.EventFreeThrow, TeamID: team, FTAttempts: 2},         // foul-only
		{Kind: result.EventFreeThrow, TeamID: team, FTAttempts: 2},         // foul-only
		{Kind: result.EventFreeThrow, TeamID: team, FTAttempts: 1},         // and-one — ignored
		{Kind: result.EventShotAttempt, TeamID: team},
		{Kind: result.EventShotAttempt, TeamID: team},
		{Kind: result.EventShotAttempt, TeamID: team},
		{Kind: result.EventShotAttempt, TeamID: team},
		{Kind: result.EventShotMiss, TeamID: team},
		{Kind: result.EventShotMiss, TeamID: team},
		{Kind: result.EventShotMake, TeamID: team}, // not folded
	}
	into := map[int]*mechAcc{}
	foldMechRates(into, events)

	a := into[team]
	if a == nil {
		t.Fatal("no accumulator for the team")
	}
	want := mechAcc{poss: 3, tov: 2, orebCont: 1, foulOnly: 2, miss: 2, fga: 4}
	if *a != want {
		t.Errorf("foldMechRates = %+v, want %+v", *a, want)
	}
}

// matrix #8 — mechanism-rate panel attribution property.
//
// When ONE mechanism rate co-varies with lnPPS across teams and the others are
// constant within season, only that mechanism shows a non-zero Cov(rate, lnPPS);
// the constant rates yield ~0. (Mirrors TestDecomposeByOrigin_Attribution's single-
// varying-source property — the corroboration the panel provides.)
func TestMechRateCorr_Attribution(t *testing.T) {
	// One season, three teams. missRate rises as PPS falls (fga held constant, pf
	// decreasing); the other three rates are identical across teams (no variance).
	rows := []mechRateRow{
		{season: "A", tovRate: 0.10, orebRate: 0.20, foulRate: 0.05, missRate: 0.30, pf: 100, fga: 80},
		{season: "A", tovRate: 0.10, orebRate: 0.20, foulRate: 0.05, missRate: 0.40, pf: 90, fga: 80},
		{season: "A", tovRate: 0.10, orebRate: 0.20, foulRate: 0.05, missRate: 0.50, pf: 80, fga: 80},
	}
	panel := mechRateCorr(rows)
	got := map[string]MechCorr{}
	for _, m := range panel {
		got[m.Mech] = m
	}

	if math.Abs(got["miss"].CovWithLnPPS) < 1e-6 {
		t.Errorf("miss CovWithLnPPS = %v, want clearly non-zero (it is the varying, PPS-coupled rate)", got["miss"].CovWithLnPPS)
	}
	if got["miss"].CovWithLnPPS >= 0 {
		t.Errorf("miss CovWithLnPPS = %v, want negative (higher miss rate → lower PPS)", got["miss"].CovWithLnPPS)
	}
	for _, m := range []string{"turnover", "oreb_continuation", "foul_only"} {
		if math.Abs(got[m].CovWithLnPPS) > 1e-9 {
			t.Errorf("%s CovWithLnPPS = %v, want ~0 (constant rate carries no covariance)", m, got[m].CovWithLnPPS)
		}
	}
}

// matrix #9 — Shapley closure identity.
//
// The four per-arm Shapley contributions sum to M[full] − M[empty] exactly, for any
// metric indexed by freeze mask.
func TestShapleyValue_Closure(t *testing.T) {
	metrics := [][16]float64{
		{0: 1.0, 15: -2.0, 3: 0.7, 5: -0.3, 10: 0.9, 12: 0.2, 7: 0.55, 8: -0.1},
		{}, // all-zero
	}
	// A non-trivial filled metric: M[mask] = sum of arbitrary per-arm weights + interactions.
	var m3 [16]float64
	w := []float64{0.3, -0.7, 1.1, -0.2}
	for mask := 0; mask < 16; mask++ {
		v := 0.05 * float64(mask) // a mask-dependent interaction term
		for a := 0; a < numArms; a++ {
			if mask&(1<<uint(a)) != 0 {
				v += w[a]
			}
		}
		m3[mask] = v
	}
	metrics = append(metrics, m3)

	for i, M := range metrics {
		var sum float64
		for a := 0; a < numArms; a++ {
			sum += shapleyValue(M, a)
		}
		want := M[15] - M[0]
		if math.Abs(sum-want) > 1e-9 {
			t.Errorf("metric %d: Σφ = %v, want M[full]-M[empty] = %v", i, sum, want)
		}
	}
}

// matrix #10 — single-carrier attribution.
//
// When the metric depends ONLY on one arm's freeze bit, that arm's Shapley value
// carries the entire change and the other three are ~0.
func TestShapleyValue_SingleCarrier(t *testing.T) {
	for carrier := 0; carrier < numArms; carrier++ {
		const delta = 1.7
		var M [16]float64
		bit := 1 << uint(carrier)
		for mask := 0; mask < 16; mask++ {
			if mask&bit != 0 {
				M[mask] = delta
			}
		}
		for a := 0; a < numArms; a++ {
			phi := shapleyValue(M, a)
			if a == carrier {
				if math.Abs(phi-delta) > 1e-9 {
					t.Errorf("carrier %d: φ = %v, want %v", carrier, phi, delta)
				}
			} else if math.Abs(phi) > 1e-9 {
				t.Errorf("carrier %d, arm %d: φ = %v, want ~0", carrier, a, phi)
			}
		}
	}
}

// matrix #11 — degenerate inputs (NEGATIVE).
//
// Empty / single-team / all-zero inputs must produce zeros, never NaN/Inf.
func TestFreeze_Degenerate(t *testing.T) {
	// Empty lattice + empty mech rows.
	var empty [16][]decompRow
	rep := buildFreezeReport(empty, nil, 0, 0)
	checkFinite(t, "empty baseline cov", rep.BaselineCovLnFGALnPPS)
	checkFinite(t, "empty residual frac", rep.ResidualFracOfBaseline)
	for _, arm := range rep.Arms {
		checkFinite(t, "empty arm dCovPPS "+arm.Arm, arm.DCovLnFGALnPPS)
		checkFinite(t, "empty arm collapseFrac "+arm.Arm, arm.CovPPSCollapseFrac)
	}
	// The panel always reports all four mechanisms; with no rows each is zero, never NaN.
	if len(rep.MechPanel) != numArms {
		t.Errorf("empty mech rows: panel len = %d, want %d (one entry per mechanism)", len(rep.MechPanel), numArms)
	}
	for _, m := range rep.MechPanel {
		checkFinite(t, "empty mech "+m.Mech+" lnPPS", m.CovWithLnPPS)
		if m.CovWithLnFGA != 0 || m.CovWithLnPPS != 0 {
			t.Errorf("empty mech %s: cov = (%v,%v), want (0,0)", m.Mech, m.CovWithLnFGA, m.CovWithLnPPS)
		}
	}

	// Single-team-per-season (within-season residuals all 0 → zero covariance).
	single := []mechRateRow{{season: "A", tovRate: 0.1, orebRate: 0.2, foulRate: 0.05, missRate: 0.4, pf: 100, fga: 80}}
	for _, m := range mechRateCorr(single) {
		checkFinite(t, "single-team "+m.Mech+" lnFGA", m.CovWithLnFGA)
		checkFinite(t, "single-team "+m.Mech+" lnPPS", m.CovWithLnPPS)
		if m.CovWithLnFGA != 0 || m.CovWithLnPPS != 0 {
			t.Errorf("single-team %s: cov = (%v,%v), want (0,0)", m.Mech, m.CovWithLnFGA, m.CovWithLnPPS)
		}
	}

	// Non-positive pf/fga rows are dropped (ln undefined) — must not NaN.
	bad := []mechRateRow{{season: "A", missRate: 0.4, pf: 0, fga: 80}, {season: "A", missRate: 0.5, pf: 100, fga: 0}}
	for _, m := range mechRateCorr(bad) {
		checkFinite(t, "bad-row "+m.Mech+" lnPPS", m.CovWithLnPPS)
	}
}

func checkFinite(t *testing.T, label string, v float64) {
	t.Helper()
	if math.IsNaN(v) || math.IsInf(v, 0) {
		t.Errorf("%s = %v, want finite", label, v)
	}
}
