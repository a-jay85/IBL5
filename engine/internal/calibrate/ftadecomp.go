package calibrate

// FTA-undershoot decomposition (J27). Pure functions only: no file I/O, no sim
// calls, no env reads. The archive-tagged driver (fta_decomp_archive_test.go)
// feeds it raw team-game sums from the engine and the .sco corpus; the committed
// artifact carries the raw sums so every ratio is recomputable from the JSON.

import (
	"bufio"
	"fmt"
	"io"
	"math"
	"strconv"
	"strings"
)

// ftaBand is the relative band inside which a per-side delta counts as "near".
const ftaBand = 0.05

// FTASide holds RAW sums over every team-game of one side (engine or .sco).
type FTASide struct {
	TeamGames float64 `json:"team_games"`
	FTA       float64 `json:"fta"`
	FTM       float64 `json:"ftm"`
	PF        float64 `json:"pf"`
	FGA       float64 `json:"fga"`
	TOV       float64 `json:"tov"`
	ORB       float64 `json:"orb"`
	Points    float64 `json:"points"`

	HomeFTA   float64 `json:"home_fta"`
	HomeGames float64 `json:"home_games"`
	AwayFTA   float64 `json:"away_fta"`
	AwayGames float64 `json:"away_games"`

	// WinnerFTA / LoserFTA sum the FTA of the winning / losing team-game of each
	// decided game; DecidedGames counts decided games (one per winner, ties skipped).
	WinnerFTA    float64 `json:"winner_fta"`
	LoserFTA     float64 `json:"loser_fta"`
	DecidedGames float64 `json:"decided_games"`

	// BucketFTA / BucketGames are per team-game, indexed by marginBucket.
	BucketFTA   [3]float64 `json:"bucket_fta"`
	BucketGames [3]float64 `json:"bucket_games"`
}

// AddTeamGame accumulates one team-game. finalMargin is the absolute final
// score difference of the game; won and tied describe this team's result (a
// tied game is skipped from the winner/loser split).
func (s *FTASide) AddTeamGame(fta, ftm, pf, fga, tov, orb, points float64, isHome bool, finalMargin int, won, tied bool) {
	s.TeamGames++
	s.FTA += fta
	s.FTM += ftm
	s.PF += pf
	s.FGA += fga
	s.TOV += tov
	s.ORB += orb
	s.Points += points
	if isHome {
		s.HomeFTA += fta
		s.HomeGames++
	} else {
		s.AwayFTA += fta
		s.AwayGames++
	}
	if !tied {
		if won {
			s.WinnerFTA += fta
			s.DecidedGames++
		} else {
			s.LoserFTA += fta
		}
	}
	b := marginBucket(finalMargin)
	s.BucketFTA[b] += fta
	s.BucketGames[b]++
}

// marginBucket maps an absolute final margin to 0 close (<=5), 1 mid (6..15),
// 2 blowout (>=16). A negative input is treated as its absolute value.
func marginBucket(absMargin int) int {
	if absMargin < 0 {
		absMargin = -absMargin
	}
	switch {
	case absMargin <= 5:
		return 0
	case absMargin <= 15:
		return 1
	default:
		return 2
	}
}

// FTASummary is the derived per-side ratios of an FTASide.
type FTASummary struct {
	FTAPerG       float64    `json:"fta_per_g"`
	PFPerG        float64    `json:"pf_per_g"`
	FTAPerPF      float64    `json:"fta_per_pf"`
	FTAPer100Poss float64    `json:"fta_per_100poss"`
	PacePerG      float64    `json:"pace_per_g"`
	PPS           float64    `json:"pps"`
	HomeAwayRatio float64    `json:"home_away_ratio"`
	WinnerFTAPerG float64    `json:"winner_fta_per_g"`
	LoserFTAPerG  float64    `json:"loser_fta_per_g"`
	BucketFTAPerG [3]float64 `json:"bucket_fta_per_g"`
}

// ratioOrZero returns num/den, or 0 when den is 0 (an empty bucket, no decided
// games, no away FTA): never NaN/Inf in a committed artifact.
func ratioOrZero(num, den float64) float64 {
	if den == 0 {
		return 0
	}
	return num / den
}

// SummarizeFTASide derives the ratios. It errors when a core denominator
// (TeamGames, PF, HomeGames, AwayGames) is 0; empty margin buckets and zero
// decided games yield 0 for that field. Possessions use the Dean-Oliver form
// FGA + 0.44*FTA + TOV - ORB.
func SummarizeFTASide(s FTASide) (FTASummary, error) {
	switch {
	case s.TeamGames == 0:
		return FTASummary{}, fmt.Errorf("calibrate: FTA summary: TeamGames == 0")
	case s.PF == 0:
		return FTASummary{}, fmt.Errorf("calibrate: FTA summary: PF == 0")
	case s.HomeGames == 0:
		return FTASummary{}, fmt.Errorf("calibrate: FTA summary: HomeGames == 0")
	case s.AwayGames == 0:
		return FTASummary{}, fmt.Errorf("calibrate: FTA summary: AwayGames == 0")
	}
	poss := s.FGA + 0.44*s.FTA + s.TOV - s.ORB
	out := FTASummary{
		FTAPerG:       s.FTA / s.TeamGames,
		PFPerG:        s.PF / s.TeamGames,
		FTAPerPF:      s.FTA / s.PF,
		FTAPer100Poss: 100 * ratioOrZero(s.FTA, poss),
		PacePerG:      poss / s.TeamGames,
		PPS:           ratioOrZero(s.Points, s.FGA+0.44*s.FTA),
		HomeAwayRatio: ratioOrZero(s.HomeFTA/s.HomeGames, s.AwayFTA/s.AwayGames),
		WinnerFTAPerG: ratioOrZero(s.WinnerFTA, s.DecidedGames),
		LoserFTAPerG:  ratioOrZero(s.LoserFTA, s.DecidedGames),
	}
	for i := range out.BucketFTAPerG {
		out.BucketFTAPerG[i] = ratioOrZero(s.BucketFTA[i], s.BucketGames[i])
	}
	return out, nil
}

// SelectFTABranch maps the engine-vs-sco (PF/g, FTA/PF) deltas to the Phase-2
// primary RE site. Advisory only (same sense as selectBranch in
// threept_undershoot_archive_test.go): Phase 2 always runs its fixed first
// step; the branch fixes which site is examined first. "Near" is |d| <= ftaBand.
func SelectFTABranch(eng, sco FTASummary) (branch, reTarget, rationale string) {
	if sco.PFPerG == 0 || sco.FTAPerPF == 0 {
		return "INSPECT", "none: zero sco denominator; inspect raw sums",
			"sco PF/g or FTA/PF is 0; relative deltas undefined"
	}
	dPF := (eng.PFPerG - sco.PFPerG) / sco.PFPerG
	dYield := (eng.FTAPerPF - sco.FTAPerPF) / sco.FTAPerPF
	rationale = fmt.Sprintf("dPF=%+.4f dYield=%+.4f (band %.2f)", dPF, dYield, ftaBand)

	pfNear := math.Abs(dPF) <= ftaBand
	yieldNear := math.Abs(dYield) <= ftaBand
	switch {
	case dPF > ftaBand:
		return "INSPECT", "none: engine over-fouls; inspect raw sums", rationale
	case dPF < -ftaBand && dYield < -ftaBand:
		return "BOTH", "FUN_004e9fc0 call-site n args, then second foul decision :93471-93475", rationale
	case dPF < -ftaBand:
		return "VOLUME", "second foul decision :93471-93475 + FUN_004ec630 foul types :93571", rationale
	case pfNear && dYield < -ftaBand:
		return "YIELD", "FUN_004e9fc0 call-site n args (:93605 :93681 :93714 :93728 :93759 :93773 :93810)", rationale
	case pfNear && yieldNear:
		return "NONE", "none localised; run the fixed Phase-2 step only", rationale
	default: // near PF with yield overshoot
		return "INSPECT", "none: yield overshoot with matched volume; inspect raw sums", rationale
	}
}

// ftaPhaseSuffix maps JSB_FTA_PHASE to the artifact filename suffix.
func ftaPhaseSuffix(v string) (string, error) {
	switch v {
	case "", "before":
		return "-before", nil
	case "after":
		return "-after", nil
	default:
		return "", fmt.Errorf("calibrate: JSB_FTA_PHASE=%q invalid: accepted values are \"\", \"before\", \"after\"", v)
	}
}

// MeasureSnapshot is the subset of TestMeasureBaseline_Archive's MEASURE lines
// the ship verdict needs.
type MeasureSnapshot struct {
	EngineFTAPerG float64
	ScoFTAPerG    float64
	HomeMarginGap map[int]float64
	HomeAwayRatio float64
}

// parseMeasureKV splits "k=v" tokens into a map; tokens without "=" are ignored.
func parseMeasureKV(fields []string) map[string]string {
	kv := make(map[string]string, len(fields))
	for _, f := range fields {
		if k, v, ok := strings.Cut(f, "="); ok {
			kv[k] = v
		}
	}
	return kv
}

func measureFloat(kind string, kv map[string]string, key string) (float64, error) {
	raw, ok := kv[key]
	if !ok {
		return 0, fmt.Errorf("calibrate: MEASURE %s: missing %s=", kind, key)
	}
	f, err := strconv.ParseFloat(raw, 64)
	if err != nil {
		return 0, fmt.Errorf("calibrate: MEASURE %s: bad %s=%q: %w", kind, key, raw, err)
	}
	return f, nil
}

// ParseMeasureLog scans r for the MEASURE lines printed by
// TestMeasureBaseline_Archive (measure_baseline_archive_test.go:84, :89, :132).
// The kind is the exact first token after "MEASURE ", so
// home_away_fta_split_SCO (:136) never overwrites the engine ratio. It errors
// when fta_per_g, home_margin gt=2, or home_margin gt=4 is absent.
func ParseMeasureLog(r io.Reader) (MeasureSnapshot, error) {
	snap := MeasureSnapshot{HomeMarginGap: map[int]float64{}}
	var haveFTA bool
	sc := bufio.NewScanner(r)
	for sc.Scan() {
		line := sc.Text()
		idx := strings.Index(line, "MEASURE ")
		if idx < 0 {
			continue
		}
		fields := strings.Fields(line[idx+len("MEASURE "):])
		if len(fields) == 0 {
			continue
		}
		kind, kv := fields[0], parseMeasureKV(fields[1:])
		switch kind {
		case "fta_per_g":
			eng, err := measureFloat(kind, kv, "engine")
			if err != nil {
				return MeasureSnapshot{}, err
			}
			sco, err := measureFloat(kind, kv, "sco")
			if err != nil {
				return MeasureSnapshot{}, err
			}
			snap.EngineFTAPerG, snap.ScoFTAPerG, haveFTA = eng, sco, true
		case "home_margin":
			gt, err := measureFloat(kind, kv, "gt")
			if err != nil {
				return MeasureSnapshot{}, err
			}
			gap, err := measureFloat(kind, kv, "gap")
			if err != nil {
				return MeasureSnapshot{}, err
			}
			snap.HomeMarginGap[int(gt)] = gap
		case "home_away_fta_split":
			ratio, err := measureFloat(kind, kv, "ratio")
			if err != nil {
				return MeasureSnapshot{}, err
			}
			snap.HomeAwayRatio = ratio
		}
	}
	if err := sc.Err(); err != nil {
		return MeasureSnapshot{}, fmt.Errorf("calibrate: scan measure log: %w", err)
	}
	if !haveFTA {
		return MeasureSnapshot{}, fmt.Errorf("calibrate: measure log has no MEASURE fta_per_g line")
	}
	for _, gt := range []int{2, 4} {
		if _, ok := snap.HomeMarginGap[gt]; !ok {
			return MeasureSnapshot{}, fmt.Errorf("calibrate: measure log has no MEASURE home_margin gt=%d line", gt)
		}
	}
	return snap, nil
}

// FTAShipVerdict is the outcome of the criteria that are measurable from the
// before/after MEASURE snapshots and instrument summaries.
type FTAShipVerdict struct {
	Ship         bool
	Failed       []string
	GapBeforePct float64
	GapAfterPct  float64
}

// withinFrac reports |a-b| <= tol*|b|: equivalent to |a/b-1| <= tol, but exact
// at the boundary where a/b rounds just past the tolerance.
func withinFrac(a, b, tol float64) bool {
	return math.Abs(a-b) <= tol*math.Abs(b)
}

// EvaluateFTAShip applies criteria b1, b2, c1, c2. Criteria (a) FUN_ citation,
// (d) FreezeConfig flag, (e) golden/coverage/lint are properties of the diff
// and are checked by commands in Phase 6, not here.
func EvaluateFTAShip(before, after MeasureSnapshot, instBefore, instAfter FTASummary) (FTAShipVerdict, error) {
	if before.ScoFTAPerG == 0 || after.ScoFTAPerG == 0 {
		return FTAShipVerdict{}, fmt.Errorf("calibrate: ship verdict: ScoFTAPerG == 0")
	}
	if instBefore.PacePerG == 0 || instBefore.PPS == 0 {
		return FTAShipVerdict{}, fmt.Errorf("calibrate: ship verdict: instBefore PacePerG/PPS == 0")
	}
	v := FTAShipVerdict{
		GapBeforePct: 100 * (before.EngineFTAPerG - before.ScoFTAPerG) / before.ScoFTAPerG,
		GapAfterPct:  100 * (after.EngineFTAPerG - after.ScoFTAPerG) / after.ScoFTAPerG,
	}
	if v.GapAfterPct-v.GapBeforePct < 5.0 {
		v.Failed = append(v.Failed, "b1-gap-shrink")
	}
	if after.EngineFTAPerG > 1.05*after.ScoFTAPerG {
		v.Failed = append(v.Failed, "b2-no-overshoot")
	}
	for _, gt := range []int{2, 4} {
		if math.Abs(after.HomeMarginGap[gt])-math.Abs(before.HomeMarginGap[gt]) > 0.25 {
			v.Failed = append(v.Failed, "c1-home-margin")
			break
		}
	}
	if !withinFrac(instAfter.PacePerG, instBefore.PacePerG, 0.02) || !withinFrac(instAfter.PPS, instBefore.PPS, 0.02) {
		v.Failed = append(v.Failed, "c2-pace-pps")
	}
	v.Ship = len(v.Failed) == 0
	return v, nil
}
