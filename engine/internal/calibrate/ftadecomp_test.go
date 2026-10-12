package calibrate

import (
	"encoding/json"
	"math"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"testing"
)

const ftaEps = 1e-9

func ftaApprox(t *testing.T, name string, got, want float64) {
	t.Helper()
	if math.Abs(got-want) > ftaEps {
		t.Errorf("%s = %.12f, want %.12f", name, got, want)
	}
}

func TestSummarizeFTASide_Ratios(t *testing.T) {
	var s FTASide
	// Home winner, close game (margin 4).
	s.AddTeamGame(20, 15, 18, 80, 12, 10, 100, true, 4, true, false)
	// Away loser, blowout (margin 20).
	s.AddTeamGame(10, 8, 22, 84, 14, 6, 90, false, 20, false, false)

	got, err := SummarizeFTASide(s)
	if err != nil {
		t.Fatalf("SummarizeFTASide: %v", err)
	}
	// Hand arithmetic: FTA 30, PF 40, FGA 164, TOV 26, ORB 16, Points 190, 2 team-games.
	poss := 164 + 0.44*30 + 26 - 16 // 187.2
	ftaApprox(t, "FTAPerG", got.FTAPerG, 15)
	ftaApprox(t, "PFPerG", got.PFPerG, 20)
	ftaApprox(t, "FTAPerPF", got.FTAPerPF, 0.75)
	ftaApprox(t, "FTAPer100Poss", got.FTAPer100Poss, 100*30/poss)
	ftaApprox(t, "PacePerG", got.PacePerG, poss/2)
	ftaApprox(t, "PacePerG literal", got.PacePerG, 93.6)
	ftaApprox(t, "PPS", got.PPS, 190/(164+0.44*30))
	ftaApprox(t, "HomeAwayRatio", got.HomeAwayRatio, 2)
	ftaApprox(t, "WinnerFTAPerG", got.WinnerFTAPerG, 20)
	ftaApprox(t, "LoserFTAPerG", got.LoserFTAPerG, 10)
	ftaApprox(t, "Bucket[0]", got.BucketFTAPerG[0], 20)
	ftaApprox(t, "Bucket[1]", got.BucketFTAPerG[1], 0)
	ftaApprox(t, "Bucket[2]", got.BucketFTAPerG[2], 10)
}

func TestSummarizeFTASide_TiesAndEmptyDenominators(t *testing.T) {
	var s FTASide
	// Tied game: skipped from the winner/loser split. Zero FTA and zero away FTA
	// exercise the ratioOrZero guard (HomeAwayRatio, PPS-free denominators).
	s.AddTeamGame(0, 0, 5, 0, 0, 0, 0, true, 0, false, true)
	s.AddTeamGame(0, 0, 5, 0, 0, 0, 0, false, 0, false, true)
	if s.DecidedGames != 0 || s.WinnerFTA != 0 || s.LoserFTA != 0 {
		t.Fatalf("tie leaked into winner/loser split: %+v", s)
	}
	got, err := SummarizeFTASide(s)
	if err != nil {
		t.Fatalf("SummarizeFTASide: %v", err)
	}
	for name, v := range map[string]float64{
		"FTAPer100Poss": got.FTAPer100Poss, "PPS": got.PPS, "HomeAwayRatio": got.HomeAwayRatio,
		"WinnerFTAPerG": got.WinnerFTAPerG, "LoserFTAPerG": got.LoserFTAPerG, "Bucket1": got.BucketFTAPerG[1],
	} {
		if v != 0 || math.IsNaN(v) || math.IsInf(v, 0) {
			t.Errorf("%s = %v, want 0", name, v)
		}
	}
}

func TestSummarizeFTASide_ZeroDenominatorsError(t *testing.T) {
	full := FTASide{TeamGames: 2, FTA: 30, PF: 40, HomeGames: 1, AwayGames: 1}
	cases := []struct {
		name string
		mut  func(*FTASide)
	}{
		{"team_games_zero", func(s *FTASide) { s.TeamGames = 0 }},
		{"pf_zero", func(s *FTASide) { s.PF = 0 }},
		{"home_games_zero", func(s *FTASide) { s.HomeGames = 0 }},
		{"away_games_zero", func(s *FTASide) { s.AwayGames = 0 }},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			s := full
			c.mut(&s)
			if _, err := SummarizeFTASide(s); err == nil {
				t.Fatal("want error, got nil")
			}
		})
	}
}

func TestMarginBucket_Boundaries(t *testing.T) {
	in := []int{0, 5, 6, 15, 16, -7}
	want := []int{0, 0, 1, 1, 2, 1}
	for i, m := range in {
		if got := marginBucket(m); got != want[i] {
			t.Errorf("marginBucket(%d) = %d, want %d", m, got, want[i])
		}
	}
}

func TestSelectFTABranch(t *testing.T) {
	const (
		tgtInspectOver  = "none: engine over-fouls; inspect raw sums"
		tgtBoth         = "FUN_004e9fc0 call-site n args, then second foul decision :93471-93475"
		tgtVolume       = "second foul decision :93471-93475 + FUN_004ec630 foul types :93571"
		tgtYield        = "FUN_004e9fc0 call-site n args (:93605 :93681 :93714 :93728 :93759 :93773 :93810)"
		tgtNone         = "none localised; run the fixed Phase-2 step only"
		tgtInspectYield = "none: yield overshoot with matched volume; inspect raw sums"
	)
	sco := FTASummary{PFPerG: 1000, FTAPerPF: 1000}
	cases := []struct {
		name         string
		pf, yield    float64
		wantBranch   string
		wantReTarget string
	}{
		{"pf_over", 1200, 1000, "INSPECT", tgtInspectOver},
		{"pf_over_any_yield", 1200, 700, "INSPECT", tgtInspectOver},
		{"both", 800, 800, "BOTH", tgtBoth},
		{"volume", 800, 1000, "VOLUME", tgtVolume},
		{"yield", 1000, 800, "YIELD", tgtYield},
		{"none", 1000, 1000, "NONE", tgtNone},
		{"yield_overshoot", 1000, 1200, "INSPECT", tgtInspectYield},
		{"boundary_0.05_is_near", 950, 950, "NONE", tgtNone},
		{"just_past_boundary", 949.9, 1000, "VOLUME", tgtVolume},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			eng := FTASummary{PFPerG: c.pf, FTAPerPF: c.yield}
			branch, target, rationale := SelectFTABranch(eng, sco)
			if branch != c.wantBranch {
				t.Errorf("branch = %q, want %q", branch, c.wantBranch)
			}
			if target != c.wantReTarget {
				t.Errorf("reTarget = %q, want %q", target, c.wantReTarget)
			}
			if rationale == "" {
				t.Error("empty rationale")
			}
		})
	}

	t.Run("zero_sco_denominator", func(t *testing.T) {
		branch, target, rationale := SelectFTABranch(FTASummary{PFPerG: 1, FTAPerPF: 1}, FTASummary{})
		if branch != "INSPECT" || !strings.Contains(target, "zero sco denominator") || rationale == "" {
			t.Errorf("got %q %q %q", branch, target, rationale)
		}
	})
}

func TestFTAPhaseSuffix(t *testing.T) {
	for in, want := range map[string]string{"": "-before", "before": "-before", "after": "-after"} {
		got, err := ftaPhaseSuffix(in)
		if err != nil || got != want {
			t.Errorf("ftaPhaseSuffix(%q) = %q, %v; want %q, nil", in, got, err, want)
		}
	}
	for _, bad := range []string{"After", "x"} {
		if got, err := ftaPhaseSuffix(bad); err == nil {
			t.Errorf("ftaPhaseSuffix(%q) = %q, nil; want error", bad, got)
		} else if !strings.Contains(err.Error(), "before") || !strings.Contains(err.Error(), "after") {
			t.Errorf("error %q does not name accepted values", err)
		}
	}
}

// realMeasureLog is the verbatim `go test -v` output of TestMeasureBaseline_Archive.
const realMeasureLog = `    measure_baseline_archive_test.go:84: MEASURE fta_per_g engine=15.81 sco=21.32
    measure_baseline_archive_test.go:89: MEASURE home_margin gt=2 engine=5.120 sco=3.319 gap=+1.802
    measure_baseline_archive_test.go:89: MEASURE home_margin gt=4 engine=6.056 sco=3.082 gap=+2.974
    measure_baseline_archive_test.go:132: MEASURE home_away_fta_split home=16.13 away=15.46 ratio=1.044 n=1233
    measure_baseline_archive_test.go:136: MEASURE home_away_fta_split_SCO home=22.64 away=19.86 ratio=1.140 n=1233
`

func TestParseMeasureLog_RealShape(t *testing.T) {
	// Noise lines and the PASS trailer must be ignored.
	log := "=== RUN   TestMeasureBaseline_Archive\n" + realMeasureLog + "--- PASS: TestMeasureBaseline_Archive (1.0s)\n"
	got, err := ParseMeasureLog(strings.NewReader(log))
	if err != nil {
		t.Fatalf("ParseMeasureLog: %v", err)
	}
	ftaApprox(t, "EngineFTAPerG", got.EngineFTAPerG, 15.81)
	ftaApprox(t, "ScoFTAPerG", got.ScoFTAPerG, 21.32)
	ftaApprox(t, "gap gt=2", got.HomeMarginGap[2], 1.802)
	ftaApprox(t, "gap gt=4", got.HomeMarginGap[4], 2.974)
	ftaApprox(t, "HomeAwayRatio", got.HomeAwayRatio, 1.044) // the _SCO 1.140 must not win
}

func TestParseMeasureLog_MissingLinesError(t *testing.T) {
	lines := strings.Split(strings.TrimSpace(realMeasureLog), "\n")
	cases := []struct {
		name string
		drop string
	}{
		{"no_fta_per_g", "MEASURE fta_per_g"},
		{"no_gt2", "gt=2"},
		{"no_gt4", "gt=4"},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			var kept []string
			for _, l := range lines {
				if !strings.Contains(l, c.drop) {
					kept = append(kept, l)
				}
			}
			if len(kept) != len(lines)-1 {
				t.Fatalf("dropped %d lines, want 1", len(lines)-len(kept))
			}
			if _, err := ParseMeasureLog(strings.NewReader(strings.Join(kept, "\n"))); err == nil {
				t.Fatal("want error, got nil")
			}
		})
	}
}

func TestParseMeasureLog_MalformedLinesError(t *testing.T) {
	const gt2 = "MEASURE home_margin gt=2 engine=1 sco=1 gap=+1.0\n"
	cases := []struct{ name, log string }{
		{"fta_engine_nan_text", "MEASURE fta_per_g engine=abc sco=2\n"},
		{"fta_sco_missing", "MEASURE fta_per_g engine=1\n"},
		{"fta_engine_missing", "MEASURE fta_per_g sco=2\n"},
		{"home_margin_gt_bad", "MEASURE home_margin gt=x gap=+1.0\n"},
		{"home_margin_gap_missing", "MEASURE home_margin gt=2\n"},
		{"split_ratio_bad", "MEASURE home_away_fta_split home=1 away=1 ratio=zz n=1\n"},
		{"scanner_too_long", gt2 + strings.Repeat("x", 100000) + "\n"},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if _, err := ParseMeasureLog(strings.NewReader(c.log)); err == nil {
				t.Fatal("want error, got nil")
			}
		})
	}

	t.Run("bare_measure_token_ignored", func(t *testing.T) {
		log := "MEASURE \n" + realMeasureLog
		if _, err := ParseMeasureLog(strings.NewReader(log)); err != nil {
			t.Fatalf("bare MEASURE line should be skipped: %v", err)
		}
	})
}

// shipBase is an all-pass fixture: gap -30% -> -20% (shrink 10), no overshoot,
// home-margin gaps unchanged, pace/PPS unchanged.
func shipBase() (before, after MeasureSnapshot, ib, ia FTASummary) {
	before = MeasureSnapshot{EngineFTAPerG: 70, ScoFTAPerG: 100, HomeMarginGap: map[int]float64{2: 2.0, 4: 3.0}}
	after = MeasureSnapshot{EngineFTAPerG: 80, ScoFTAPerG: 100, HomeMarginGap: map[int]float64{2: 2.0, 4: 3.0}}
	ib = FTASummary{PacePerG: 100, PPS: 50}
	ia = FTASummary{PacePerG: 100, PPS: 50}
	return before, after, ib, ia
}

func TestEvaluateFTAShip_Thresholds(t *testing.T) {
	cases := []struct {
		name       string
		mut        func(before, after *MeasureSnapshot, ib, ia *FTASummary)
		wantFailed []string
	}{
		{"all_pass", func(_, _ *MeasureSnapshot, _, _ *FTASummary) {}, nil},
		{"b1_shrink_5.0_passes", func(_, a *MeasureSnapshot, _, _ *FTASummary) { a.EngineFTAPerG = 75 }, nil},
		{"b1_shrink_4.99_fails", func(_, a *MeasureSnapshot, _, _ *FTASummary) { a.EngineFTAPerG = 74.99 }, []string{"b1-gap-shrink"}},
		{"b2_exactly_1.05_passes", func(_, a *MeasureSnapshot, _, _ *FTASummary) { a.EngineFTAPerG = 1.05 * a.ScoFTAPerG }, nil},
		{"b2_1.0501_fails", func(_, a *MeasureSnapshot, _, _ *FTASummary) { a.EngineFTAPerG = 1.0501 * a.ScoFTAPerG }, []string{"b2-no-overshoot"}},
		{"c1_gt2_0.25_passes", func(_, a *MeasureSnapshot, _, _ *FTASummary) { a.HomeMarginGap[2] = 2.25 }, nil},
		{"c1_gt2_0.2501_fails", func(_, a *MeasureSnapshot, _, _ *FTASummary) { a.HomeMarginGap[2] = 2.2501 }, []string{"c1-home-margin"}},
		{"c1_gt4_0.25_passes", func(_, a *MeasureSnapshot, _, _ *FTASummary) { a.HomeMarginGap[4] = 3.25 }, nil},
		{"c1_gt4_0.2501_fails", func(_, a *MeasureSnapshot, _, _ *FTASummary) { a.HomeMarginGap[4] = 3.2501 }, []string{"c1-home-margin"}},
		{"c1_negative_gap_uses_abs", func(b, a *MeasureSnapshot, _, _ *FTASummary) { b.HomeMarginGap[2] = -2.0; a.HomeMarginGap[2] = 2.25 }, nil},
		{"c1_both_gts_fail_one_reason", func(_, a *MeasureSnapshot, _, _ *FTASummary) {
			a.HomeMarginGap[2] = 3
			a.HomeMarginGap[4] = 4
		}, []string{"c1-home-margin"}},
		{"c2_pace_1.02_passes", func(_, _ *MeasureSnapshot, _, ia *FTASummary) { ia.PacePerG = 102 }, nil},
		{"c2_pace_1.0201_fails", func(_, _ *MeasureSnapshot, _, ia *FTASummary) { ia.PacePerG = 102.01 }, []string{"c2-pace-pps"}},
		{"c2_pace_0.98_passes", func(_, _ *MeasureSnapshot, _, ia *FTASummary) { ia.PacePerG = 98 }, nil},
		{"c2_pps_1.02_passes", func(_, _ *MeasureSnapshot, _, ia *FTASummary) { ia.PPS = 51 }, nil},
		{"c2_pps_1.0201_fails", func(_, _ *MeasureSnapshot, _, ia *FTASummary) { ia.PPS = 51.005 }, []string{"c2-pace-pps"}},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			before, after, ib, ia := shipBase()
			c.mut(&before, &after, &ib, &ia)
			v, err := EvaluateFTAShip(before, after, ib, ia)
			if err != nil {
				t.Fatalf("EvaluateFTAShip: %v", err)
			}
			if strings.Join(v.Failed, ",") != strings.Join(c.wantFailed, ",") {
				t.Errorf("Failed = %v, want %v", v.Failed, c.wantFailed)
			}
			if v.Ship != (len(c.wantFailed) == 0) {
				t.Errorf("Ship = %v with Failed %v", v.Ship, v.Failed)
			}
		})
	}

	t.Run("gap_pcts", func(t *testing.T) {
		before, after, ib, ia := shipBase()
		v, err := EvaluateFTAShip(before, after, ib, ia)
		if err != nil {
			t.Fatal(err)
		}
		ftaApprox(t, "GapBeforePct", v.GapBeforePct, -30)
		ftaApprox(t, "GapAfterPct", v.GapAfterPct, -20)
	})

	t.Run("multiple_failures_all_named", func(t *testing.T) {
		before, after, ib, ia := shipBase()
		after.EngineFTAPerG = 70 // no shrink
		ia.PacePerG = 200
		v, err := EvaluateFTAShip(before, after, ib, ia)
		if err != nil {
			t.Fatal(err)
		}
		if v.Ship || len(v.Failed) != 2 {
			t.Errorf("Ship=%v Failed=%v, want 2 failures", v.Ship, v.Failed)
		}
	})
}

func TestEvaluateFTAShip_ZeroDenominatorsError(t *testing.T) {
	cases := []struct {
		name string
		mut  func(before, after *MeasureSnapshot, ib *FTASummary)
	}{
		{"before_sco_zero", func(b, _ *MeasureSnapshot, _ *FTASummary) { b.ScoFTAPerG = 0 }},
		{"after_sco_zero", func(_, a *MeasureSnapshot, _ *FTASummary) { a.ScoFTAPerG = 0 }},
		{"pace_zero", func(_, _ *MeasureSnapshot, ib *FTASummary) { ib.PacePerG = 0 }},
		{"pps_zero", func(_, _ *MeasureSnapshot, ib *FTASummary) { ib.PPS = 0 }},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			before, after, ib, ia := shipBase()
			c.mut(&before, &after, &ib)
			if _, err := EvaluateFTAShip(before, after, ib, ia); err == nil {
				t.Fatal("want error, got nil")
			}
		})
	}
}

// ftaLastGlob returns the lexicographically last match of pattern in dir,
// skipping the JSB_3PT_AB arm files (names containing "-ab-"), or "".
func ftaLastGlob(t *testing.T, dir, pattern string) string {
	t.Helper()
	matches, err := filepath.Glob(filepath.Join(dir, pattern))
	if err != nil {
		t.Fatalf("glob %q: %v", pattern, err)
	}
	var kept []string
	for _, m := range matches {
		if !strings.Contains(filepath.Base(m), "-ab-") {
			kept = append(kept, m)
		}
	}
	if len(kept) == 0 {
		return ""
	}
	sort.Strings(kept)
	return kept[len(kept)-1]
}

func ftaReadMeasure(t *testing.T, path string) MeasureSnapshot {
	t.Helper()
	f, err := os.Open(path)
	if err != nil {
		t.Fatalf("open %s: %v", path, err)
	}
	defer func() { _ = f.Close() }()
	snap, err := ParseMeasureLog(f)
	if err != nil {
		t.Fatalf("parse %s: %v", path, err)
	}
	return snap
}

func ftaReadEngineSummary(t *testing.T, path string) FTASummary {
	t.Helper()
	raw, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("read %s: %v", path, err)
	}
	var art struct {
		EngineSummary FTASummary `json:"engine_summary"`
	}
	if err := json.Unmarshal(raw, &art); err != nil {
		t.Fatalf("decode %s: %v", path, err)
	}
	return art.EngineSummary
}

// TestFTAShipVerdict_Committed is the CI-run home of the ship verdict: it reads
// the committed before/after measure logs and decomposition artifacts and logs
// FTA_SHIP_VERDICT. It skips until all four exist (before the Phase-4 "after"
// artifacts are committed).
func TestFTAShipVerdict_Committed(t *testing.T) {
	dir := filepath.Join("..", "validate", "testdata")
	measBefore := ftaLastGlob(t, dir, "calibration-5.60-*-fta-measure-before.txt")
	measAfter := ftaLastGlob(t, dir, "calibration-5.60-*-fta-measure-after.txt")
	decBefore := ftaLastGlob(t, dir, "calibration-5.60-*-fta-decomp-before.json")
	decAfter := ftaLastGlob(t, dir, "calibration-5.60-*-fta-decomp-after.json")
	if measBefore == "" || measAfter == "" || decBefore == "" || decAfter == "" {
		t.Skipf("committed FTA artifacts incomplete (measure before=%q after=%q, decomp before=%q after=%q)",
			measBefore, measAfter, decBefore, decAfter)
	}
	v, err := EvaluateFTAShip(
		ftaReadMeasure(t, measBefore), ftaReadMeasure(t, measAfter),
		ftaReadEngineSummary(t, decBefore), ftaReadEngineSummary(t, decAfter),
	)
	if err != nil {
		t.Fatalf("EvaluateFTAShip: %v", err)
	}
	t.Logf("FTA_SHIP_VERDICT ship=%v failed=%v gap_before=%.2f gap_after=%.2f",
		v.Ship, v.Failed, v.GapBeforePct, v.GapAfterPct)
}

func TestIsDegenerateFTAGame(t *testing.T) {
	cases := []struct {
		name                   string
		pts0, pts1, fta0, fta1 int
		want                   bool
	}{
		{"all_zero_box", 0, 0, 0, 0, true},
		{"zero_zero_with_fta_team0", 0, 0, 2, 0, false},
		{"zero_zero_with_fta_team1", 0, 0, 0, 1, false},
		{"real_tie_with_points", 98, 98, 0, 0, false},
		{"team0_scored", 2, 0, 0, 0, false},
		{"team1_scored", 0, 2, 0, 0, false},
		{"ordinary_decided_game", 104, 97, 21, 18, false},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			if got := isDegenerateFTAGame(tc.pts0, tc.pts1, tc.fta0, tc.fta1); got != tc.want {
				t.Errorf("isDegenerateFTAGame(%d,%d,%d,%d) = %v, want %v",
					tc.pts0, tc.pts1, tc.fta0, tc.fta1, got, tc.want)
			}
		})
	}
}
