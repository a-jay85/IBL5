package calibrate

import (
	"bytes"
	"math"
	"os"
	"strings"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/result"
)

// TestTeamBoxPts verifies the points-for helper sums Q1–Q4 plus OT correctly.
func TestTeamBoxPts(t *testing.T) {
	tb := result.TeamBox{Q1: 20, Q2: 18, Q3: 24, Q4: 15, OT: []int{5, 3}}
	want := float64(20 + 18 + 24 + 15 + 5 + 3)
	if got := teamBoxPts(tb); got != want {
		t.Errorf("teamBoxPts = %v, want %v", got, want)
	}
	// No OT — should not panic.
	if got := teamBoxPts(result.TeamBox{Q1: 10, Q2: 10, Q3: 10, Q4: 10}); got != 40 {
		t.Errorf("teamBoxPts no-OT = %v, want 40", got)
	}
}

// TestWriteResearchReport verifies the writer emits one line per LeveragePoint
// with the correct ABOVE NOISE / sub-noise tag and sorts by |Delta| descending.
func TestWriteResearchReport(t *testing.T) {
	rep := ResearchReport{
		NoiseFloor: map[string]float64{"steal_share": 0.001},
		Points: []LeveragePoint{
			{StandInID: "steal_turnover_scale", Value: 1.8e-5, Term: "steal_share", Delta: 0.005, NoiseFloor: 0.001, AboveNoise: true},
			{StandInID: "steal_turnover_scale", Value: 1.8e-5, Term: "cov_poss_pps", Delta: 0.0001, NoiseFloor: 0.002, AboveNoise: false},
		},
	}
	var buf bytes.Buffer
	WriteResearchReport(&buf, rep)
	out := buf.String()
	if !strings.Contains(out, "[ABOVE NOISE]") {
		t.Errorf("WriteResearchReport missing [ABOVE NOISE]: %q", out)
	}
	if !strings.Contains(out, "[sub-noise]") {
		t.Errorf("WriteResearchReport missing [sub-noise]: %q", out)
	}
	// Sorted by |Delta| descending: steal_share (0.005) before cov_poss_pps (0.0001).
	lines := strings.Split(strings.TrimSpace(out), "\n")
	if len(lines) != 2 {
		t.Fatalf("want 2 lines, got %d", len(lines))
	}
	if !strings.Contains(lines[0], "steal_share") {
		t.Errorf("first line should be steal_share (largest |Delta|): %q", lines[0])
	}
}

// TestRunResearch_EmptyRoot calls RunResearch against a temp dir with no zip
// files. researchWalk should list zero zips, return zero terms for every walk,
// and RunResearch should return a valid ResearchReport (not error).
func TestRunResearch_EmptyRoot(t *testing.T) {
	dir, err := os.MkdirTemp("", "jsbresearch-test-*")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = os.RemoveAll(dir) })

	rep, err := RunResearch(dir, Options{Runs: 1, SampleStride: 1})
	if err != nil {
		t.Fatalf("RunResearch empty root: %v", err)
	}
	// With no games simulated all terms are zero.
	for term, nf := range rep.NoiseFloor {
		if nf != 0 {
			t.Errorf("NoiseFloor[%q] = %v, want 0 (no archive)", term, nf)
		}
	}
	// No above-noise points since all deltas are zero.
	for _, p := range rep.Points {
		if p.AboveNoise {
			t.Errorf("unexpected AboveNoise=true from empty archive: %+v", p)
		}
	}
}

// copyTermMap returns a shallow copy of a fidelity-term map.
func copyTermMap(m map[string]float64) map[string]float64 {
	c := make(map[string]float64, len(m))
	for k, v := range m {
		c[k] = v
	}
	return c
}

// TestResearchWalk_ZeroDelta exercises the runResearch orchestration logic via
// the researchWalkFn seam — no archive access required. It uses a synthetic
// walk function that returns fixed term maps so the test covers:
//
//	(a) When both baseline walks and all sweep walks return identical maps,
//	    every LeveragePoint has Delta==0 and AboveNoise==false.
//
//	(b) When the two baseline walks differ slightly (establishing a non-zero
//	    noise floor) and one stand-in's sweep walks return a value that
//	    exceeds the noise floor, those LeveragePoints have AboveNoise==true.
func TestResearchWalk_ZeroDelta(t *testing.T) {
	t.Run("zero_delta_identical_walks", func(t *testing.T) {
		fixed := map[string]float64{
			"cov_poss_pps":           0.010,
			"cov_shots_per_poss_pps": -0.005,
			"steal_share":            0.085,
			"non_steal_to_share":     0.049,
		}

		// Every call returns the same values: both baselines are identical, so
		// noise floor == 0 for every term; all sweep deltas are also 0.
		fakeFn := func(_ string, _ Options, _ func(*Options)) (map[string]float64, error) {
			return copyTermMap(fixed), nil
		}

		rep, err := runResearch("", Options{Runs: 1, SampleStride: 1}, fakeFn)
		if err != nil {
			t.Fatalf("runResearch: %v", err)
		}
		if len(rep.Points) == 0 {
			t.Fatal("no Points produced — StandInRegistry must have at least one non-baseline Sweep entry")
		}
		for term, nf := range rep.NoiseFloor {
			if nf != 0 {
				t.Errorf("NoiseFloor[%q] = %v, want 0 (identical baselines)", term, nf)
			}
		}
		for _, p := range rep.Points {
			if p.Delta != 0 {
				t.Errorf("%s/%g/%s: Delta = %v, want 0", p.StandInID, p.Value, p.Term, p.Delta)
			}
			if p.AboveNoise {
				t.Errorf("%s/%g/%s: AboveNoise = true, want false (delta == 0, noise floor == 0)",
					p.StandInID, p.Value, p.Term)
			}
		}
	})

	t.Run("above_noise_detected", func(t *testing.T) {
		// base1: the reference baseline.
		base1 := map[string]float64{
			"cov_poss_pps":           0.010,
			"cov_shots_per_poss_pps": -0.005,
			"steal_share":            0.0850,
			"non_steal_to_share":     0.049,
		}
		// base2: tiny steal_share nudge → noise floor steal_share = 0.0001.
		base2 := map[string]float64{
			"cov_poss_pps":           0.010,
			"cov_shots_per_poss_pps": -0.005,
			"steal_share":            0.0851,
			"non_steal_to_share":     0.049,
		}
		// sweepResult: large steal_share delta (0.005) >> noise floor (0.0001).
		sweepResult := map[string]float64{
			"cov_poss_pps":           0.010,
			"cov_shots_per_poss_pps": -0.005,
			"steal_share":            0.0900,
			"non_steal_to_share":     0.049,
		}

		// call counts the number of walkFn invocations so we can distinguish the
		// two baseline calls from the subsequent sweep calls.
		call := 0
		fakeFn := func(_ string, o Options, apply func(*Options)) (map[string]float64, error) {
			call++
			if call == 1 {
				return copyTermMap(base1), nil // baseline run 1 (seed opts.Seed)
			}
			if call == 2 {
				return copyTermMap(base2), nil // baseline run 2 (seed opts.Seed+1)
			}
			// Sweep call: check whether the apply closure set StealTurnoverScale.
			probe := o
			apply(&probe)
			if probe.StealTurnoverScale != nil {
				return copyTermMap(sweepResult), nil
			}
			return copyTermMap(base1), nil // all other sweeps → no change from baseline
		}

		rep, err := runResearch("", Options{Runs: 1, SampleStride: 1, Seed: 42}, fakeFn)
		if err != nil {
			t.Fatalf("runResearch: %v", err)
		}

		// Noise floor for steal_share must equal |base2 − base1|.
		wantNF := math.Abs(base2["steal_share"] - base1["steal_share"])
		if got := rep.NoiseFloor["steal_share"]; math.Abs(got-wantNF) > 1e-15 {
			t.Errorf("NoiseFloor[steal_share] = %v, want %v", got, wantNF)
		}

		// At least one steal_turnover_scale × steal_share point must be AboveNoise.
		foundAbove := false
		for _, p := range rep.Points {
			if p.StandInID == "steal_turnover_scale" && p.Term == "steal_share" && p.AboveNoise {
				foundAbove = true
				break
			}
		}
		if !foundAbove {
			t.Error("want at least one steal_turnover_scale × steal_share LeveragePoint with AboveNoise = true")
		}
	})
}
