package calibrate

import (
	"math"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/result"
)

// synthPlr builds one starter PlrPlayer at the given depth-chart position.
func synthPlr(ord, pid, team int, posDepth string, fgp int) backup.PlrPlayer {
	p := backup.PlrPlayer{
		Ordinal: ord, PID: pid, TeamID: team, Age: 25, Peak: 27, CanPlayInGame: 1,
		RatingFGA: 60, RatingFGP: fgp, RatingFTA: 20, RatingFTP: 75,
		Rating3GA: 25, Rating3GP: 35, RatingORB: 20, RatingDRB: 35,
		RatingAST: 30, RatingSTL: 30, RatingTVR: 40, RatingBLK: 20,
		RatingOO: 6, RatingOD: 5, RatingDO: 5, RatingDD: 5,
		RatingPO: 5, RatingPD: 5, RatingTO: 7, RatingTD: 5,
		Clutch: 5, Consistency: 5,
	}
	switch posDepth {
	case "PG":
		p.PGDepth = 1
	case "SG":
		p.SGDepth = 1
	case "SF":
		p.SFDepth = 1
	case "PF":
		p.PFDepth = 1
	case "C":
		p.CDepth = 1
	}
	return p
}

// synthBundle assembles a minimal two-team, multi-game regular-season bundle via
// the real backup.ToBundle path — sim-able, with the two teams rated differently so
// the cross-team decomposition is non-degenerate.
func synthBundle(t *testing.T) bundle.Bundle {
	t.Helper()
	pos := []string{"PG", "SG", "SF", "PF", "C"}
	var players []backup.PlrPlayer
	ord := 0
	for _, tm := range []struct{ id, fgp int }{{3, 50}, {7, 44}} {
		for i, ps := range pos {
			ord++
			players = append(players, synthPlr(ord, 100+ord, tm.id, ps, tm.fgp-i))
		}
	}
	var sched []backup.SchGame
	for d := 1; d <= 6; d++ {
		g := backup.SchGame{VisitorTeamID: 7, HomeTeamID: 3, Month: 11, Day: d}
		if d%2 == 0 {
			g.VisitorTeamID, g.HomeTeamID = 3, 7
		}
		sched = append(sched, g)
	}
	b, err := backup.ToBundle(players, sched, backup.AssembleOptions{GameType: bundle.GameTypeRegular})
	if err != nil {
		t.Fatalf("ToBundle: %v", err)
	}
	return b
}

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

// TestCollectFreezeAttribution_Synthetic drives the lattice end-to-end on synthetic
// bundles via the injected seam (no archive zips): it exercises the per-season
// baseline + 15 frozen passes, the engine runner, the row appenders, and the
// report assembly, asserting a complete, finite report.
func TestCollectFreezeAttribution_Synthetic(t *testing.T) {
	b := synthBundle(t)
	loadFn := func(string) (bundle.Bundle, *Skip) { return b, nil }
	countFn := func(string) (int, int, error) { return 1148, 28, nil } // proxy medGP 82 ≥ floor

	seasons := []season{
		{name: "A", regularCandidates: []string{"A_reg-sim.zip"}, finalsZip: "A_reg-sim.zip"},
		{name: "B", regularCandidates: []string{"B_reg-sim.zip"}, finalsZip: "B_reg-sim.zip"},
	}
	rep, skips := collectFreezeAttribution(seasons, Options{Runs: 2, SampleStride: 1}, 1, loadFn, countFn)

	if rep.NumSeasons != 2 {
		t.Errorf("NumSeasons = %d, want 2 (skips: %v)", rep.NumSeasons, skips)
	}
	if len(rep.Configs) != 16 {
		t.Errorf("len(Configs) = %d, want 16", len(rep.Configs))
	}
	if len(rep.Arms) != numArms {
		t.Errorf("len(Arms) = %d, want %d", len(rep.Arms), numArms)
	}
	checkFinite(t, "baseline cov", rep.BaselineCovLnFGALnPPS)
	checkFinite(t, "all-frozen cov", rep.AllFrozenCovLnFGALnPPS)
	checkFinite(t, "residual frac", rep.ResidualFracOfBaseline)
	for _, a := range rep.Arms {
		checkFinite(t, "arm "+a.Arm+" dVarFGA", a.DVarLnFGA)
		checkFinite(t, "arm "+a.Arm+" dCovPF", a.DCovLnFGALnPF)
		checkFinite(t, "arm "+a.Arm+" dCovPPS", a.DCovLnFGALnPPS)
		checkFinite(t, "arm "+a.Arm+" collapseFrac", a.CovPPSCollapseFrac)
	}
	// The no-freeze config (mask 0) is the reference; the all-frozen config is mask 15.
	if rep.Configs[0].Mask != 0 || rep.Configs[15].Mask != 15 {
		t.Errorf("config masks not in order: [0]=%d [15]=%d", rep.Configs[0].Mask, rep.Configs[15].Mask)
	}
}

// TestLoadSeasonBundle_Errors covers loadSeasonBundle's failure paths (the happy
// path needs real .plr bytes and is covered by the archive diagnostic).
func TestLoadSeasonBundle_Errors(t *testing.T) {
	// Non-existent zip → extract error.
	if _, skip := loadSeasonBundle(filepath.Join(t.TempDir(), "nope.zip")); skip == nil {
		t.Error("loadSeasonBundle on a missing zip: got nil Skip, want an extract error")
	}

	// Zip missing IBL5.sco → not found.
	noSco := filepath.Join(t.TempDir(), "partial_reg-sim.zip")
	makeZip(t, noSco, map[string]string{"IBL5.plr": "x", "IBL5.sch": "y"})
	if _, skip := loadSeasonBundle(noSco); skip == nil {
		t.Error("loadSeasonBundle on a zip missing IBL5.sco: got nil Skip, want a missing-member error")
	}

	// Full triple but unparseable .plr bytes → readBackup parse error.
	bad := filepath.Join(t.TempDir(), "bad_reg-sim.zip")
	makeZip(t, bad, fullTriple())
	if _, skip := loadSeasonBundle(bad); skip == nil {
		t.Error("loadSeasonBundle on unparseable .plr bytes: got nil Skip, want a parse error")
	}
}

// TestCollectFreezeAttribution_PublicWrapper covers the real-archive entrypoint over
// a synthetic root: fake .sco bytes fail the selection count, so no season qualifies
// — the wrapper returns an empty report plus skips, never an error.
func TestCollectFreezeAttribution_PublicWrapper(t *testing.T) {
	root := t.TempDir()
	makeZip(t, filepath.Join(root, "02-03", "02-03_08_reg-sim01.zip"), fullTriple())

	rep, skips, err := CollectFreezeAttribution(root, Options{Runs: 1, SampleStride: 1}, 1)
	if err != nil {
		t.Fatalf("CollectFreezeAttribution: %v", err)
	}
	if rep.NumSeasons != 0 {
		t.Errorf("NumSeasons = %d, want 0 (fake .sco cannot be counted)", rep.NumSeasons)
	}
	if len(skips) == 0 {
		t.Error("want at least one Skip for the uncountable snapshot")
	}
	if len(rep.Configs) != 16 {
		t.Errorf("len(Configs) = %d, want 16 even with no seasons", len(rep.Configs))
	}
}
