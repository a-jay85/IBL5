//go:build archive

package calibrate

import (
	"encoding/json"
	"math"
	"os"
	"path/filepath"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/backup"
	"github.com/a-jay85/IBL5/engine/internal/validate"
)

// leadersCheckASummary is the program-wide aggregate written to the artifact.
type leadersCheckASummary struct {
	Games           int                   `json:"games"`
	GamesPassed     int                   `json:"games_passed"`
	MismatchCount   int                   `json:"mismatch_count"`
	NegativeCount   int                   `json:"negative_count"`
	DominanceCount  int                   `json:"dominance_count"`
	MismatchSample  []validate.AMismatch  `json:"mismatch_sample,omitempty"`
	NegativeSample  []validate.ANegative  `json:"negative_sample,omitempty"`
	DominanceSample []validate.ADominance `json:"dominance_sample,omitempty"`
}

// leadersCheckBSummary is the aggregate written to the Check B artifact.
type leadersCheckBSummary struct {
	TeamSeasons       int                  `json:"team_seasons"`
	Mean              float64              `json:"mean"`
	StdDev            float64              `json:"std_dev"`
	FractionAboveHalf float64              `json:"fraction_above_half"`
	Skipped           int                  `json:"skipped"`
	NegativeTeamCount int                  `json:"negative_team_count"`
	NegativeSample    []validate.BNegative `json:"negative_sample,omitempty"`
	MaxPlayerAvg      float64              `json:"max_player_avg"`
}

const maxSample = 20

func writeLeadersArtifact(t *testing.T, name string, v any) {
	t.Helper()
	out := filepath.Join("..", "validate", "testdata", name)
	blob, err := json.MarshalIndent(v, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact %s: %v", name, err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)
}

func appendCapped[T any](dst []T, src []T) []T {
	for _, v := range src {
		if len(dst) >= maxSample {
			break
		}
		dst = append(dst, v)
	}
	return dst
}

// TestLeadersCheckAArchive runs Check A over every season's most-complete
// regular-season zip in the real corpus and asserts data-quality invariants.
//
// Invoke:
//
//	cd engine && JSB_ARCHIVE_DIR=... go test -tags archive ./internal/calibrate -run TestLeadersCheckAArchive -v -timeout 6h
func TestLeadersCheckAArchive(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	zips, skips, err := listArchiveZips(dir)
	if err != nil {
		t.Fatalf("listArchiveZips: %v", err)
	}
	for _, sk := range skips {
		t.Logf("skip: %s — %s", sk.Path, sk.Reason)
	}

	seasons, seasonSkips := groupSeasons(zips, dir)
	for _, sk := range seasonSkips {
		t.Logf("season skip: %s — %s", sk.Path, sk.Reason)
	}

	var agg leadersCheckASummary

	for _, s := range seasons {
		if s.olympics || len(s.regularCandidates) == 0 {
			continue
		}
		best, _, _, selSkips := selectMostComplete(s.regularCandidates, countScoGames)
		for _, sk := range selSkips {
			t.Logf("select skip: %s — %s", sk.Path, sk.Reason)
		}
		if best == "" {
			continue
		}

		tmp, err := os.MkdirTemp("", "jsbcal-leaders-a-*")
		if err != nil {
			t.Fatalf("MkdirTemp: %v", err)
		}
		defer func() { _ = os.RemoveAll(tmp) }()

		found, err := extractTriple(best, tmp)
		if err != nil || !found {
			t.Logf("extract skip %s: found=%v err=%v", best, found, err)
			continue
		}

		scoPath := filepath.Join(tmp, "IBL5.sco")
		f, err := os.Open(scoPath)
		if err != nil {
			t.Logf("open sco %s: %v", scoPath, err)
			continue
		}
		games, err := backup.ReadSco(f)
		_ = f.Close()
		if err != nil {
			t.Logf("ReadSco %s: %v", best, err)
			continue
		}

		rep := validate.CheckA(games)
		agg.Games += rep.Games
		agg.GamesPassed += rep.GamesPassed
		agg.MismatchCount += len(rep.Mismatches)
		agg.NegativeCount += len(rep.Negatives)
		agg.DominanceCount += len(rep.Dominances)
		agg.MismatchSample = appendCapped(agg.MismatchSample, rep.Mismatches)
		agg.NegativeSample = appendCapped(agg.NegativeSample, rep.Negatives)
		agg.DominanceSample = appendCapped(agg.DominanceSample, rep.Dominances)
	}

	writeLeadersArtifact(t, "leaders_checkA_archive.json", agg)

	// Invariants.
	if agg.Games == 0 {
		t.Fatal("Games==0: walk found no box scores — structural failure")
	}
	if agg.NegativeCount != 0 {
		t.Errorf("Negatives=%d want 0: negative counting stat indicates parser or corpus fault", agg.NegativeCount)
	}
	if agg.MismatchCount != 0 {
		t.Errorf("Mismatches=%d want 0: team-total row must equal player-row sum for every game/team/stat at ±0; see artifact for offending games", agg.MismatchCount)
	}
	t.Logf("Games=%d GamesPassed=%d Mismatches=%d Negatives=%d Dominances=%d",
		agg.Games, agg.GamesPassed, agg.MismatchCount, agg.NegativeCount, agg.DominanceCount)
}

// TestLeadersCheckBArchive runs Check B over every season's most-complete
// regular-season zip and asserts rating-vs-.sco correlation invariants.
//
// Invoke:
//
//	cd engine && JSB_ARCHIVE_DIR=... go test -tags archive ./internal/calibrate -run TestLeadersCheckBArchive -v -timeout 6h
func TestLeadersCheckBArchive(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	zips, skips, err := listArchiveZips(dir)
	if err != nil {
		t.Fatalf("listArchiveZips: %v", err)
	}
	for _, sk := range skips {
		t.Logf("skip: %s — %s", sk.Path, sk.Reason)
	}

	seasons, seasonSkips := groupSeasons(zips, dir)
	for _, sk := range seasonSkips {
		t.Logf("season skip: %s — %s", sk.Path, sk.Reason)
	}

	var (
		allCorrelations  []float64
		allNegatives     []validate.BNegative
		totalSkipped     int
		maxAcrossSeasons float64
		teamSeasons      int
	)

	for _, s := range seasons {
		if s.olympics || len(s.regularCandidates) == 0 {
			continue
		}
		best, _, _, selSkips := selectMostComplete(s.regularCandidates, countScoGames)
		for _, sk := range selSkips {
			t.Logf("select skip: %s — %s", sk.Path, sk.Reason)
		}
		if best == "" {
			continue
		}

		tmp, err := os.MkdirTemp("", "jsbcal-leaders-b-*")
		if err != nil {
			t.Fatalf("MkdirTemp: %v", err)
		}
		defer func() { _ = os.RemoveAll(tmp) }()

		found, err := extractTriple(best, tmp)
		if err != nil || !found {
			t.Logf("extract skip %s: found=%v err=%v", best, found, err)
			continue
		}

		plrPath := filepath.Join(tmp, "IBL5.plr")
		scoPath := filepath.Join(tmp, "IBL5.sco")

		fp, err := os.Open(plrPath)
		if err != nil {
			t.Logf("open plr %s: %v", plrPath, err)
			continue
		}
		players, err := backup.ReadPlr(fp)
		_ = fp.Close()
		if err != nil {
			t.Logf("ReadPlr %s: %v", best, err)
			continue
		}

		fs, err := os.Open(scoPath)
		if err != nil {
			t.Logf("open sco %s: %v", scoPath, err)
			continue
		}
		games, err := backup.ReadSco(fs)
		_ = fs.Close()
		if err != nil {
			t.Logf("ReadSco %s: %v", best, err)
			continue
		}

		rep := validate.CheckB(players, games, 5)
		teamSeasons += rep.TeamSeasons
		totalSkipped += rep.Skipped
		allCorrelations = append(allCorrelations, rep.Correlations...)
		allNegatives = appendCapped(allNegatives, rep.NegativeTeams)
		if rep.MaxPlayerAvg > maxAcrossSeasons {
			maxAcrossSeasons = rep.MaxPlayerAvg
		}
	}

	// Compute aggregate distribution.
	var mean, stdDev, fracAboveHalf float64
	if teamSeasons > 0 {
		var sum float64
		var aboveHalf int
		for _, rho := range allCorrelations {
			sum += rho
			if rho > 0.5 {
				aboveHalf++
			}
		}
		mean = sum / float64(teamSeasons)
		var varSum float64
		for _, rho := range allCorrelations {
			d := rho - mean
			varSum += d * d
		}
		stdDev = math.Sqrt(varSum / float64(teamSeasons))
		fracAboveHalf = float64(aboveHalf) / float64(teamSeasons)
	}

	agg := leadersCheckBSummary{
		TeamSeasons:       teamSeasons,
		Mean:              mean,
		StdDev:            stdDev,
		FractionAboveHalf: fracAboveHalf,
		Skipped:           totalSkipped,
		NegativeTeamCount: len(allNegatives),
		NegativeSample:    allNegatives,
		MaxPlayerAvg:      maxAcrossSeasons,
	}
	writeLeadersArtifact(t, "leaders_checkB_archive.json", agg)

	// Invariants.
	if teamSeasons == 0 {
		t.Fatal("TeamSeasons==0: no qualifying team-seasons found — structural failure")
	}
	for _, rho := range allCorrelations {
		if math.IsNaN(rho) || math.IsInf(rho, 0) {
			t.Errorf("non-finite rho in Correlations: %v — CheckB must filter NaNs before returning", rho)
		}
	}
	if maxAcrossSeasons >= 60.0 {
		t.Errorf("maxAcrossSeasons=%.2f >= 60.0: per-player scoring average too high — ScoGame boxes may be misread as season cumulatives", maxAcrossSeasons)
	}
	if mean <= 0 {
		t.Errorf("aggregate Mean=%.4f <= 0: scoring ratings should on average positively predict .sco scoring — indicates ratings/engine drift or instrument bug", mean)
	}
	t.Logf("TeamSeasons=%d Mean=%.4f StdDev=%.4f FractionAboveHalf=%.3f Skipped=%d NegativeTeams=%d MaxPlayerAvg=%.2f",
		teamSeasons, mean, stdDev, fracAboveHalf, totalSkipped, len(allNegatives), maxAcrossSeasons)
}
