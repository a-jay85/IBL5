//go:build archive

// J24 Phase 0 — pre-port possession-clock baseline diagnostic (NO behavior change).
//
// Establishes the durable pre-port baseline BEFORE Phase 1 replaces the additive
// teamBaseTimeWith stand-in. Measured over the REAL ~53 GB JSB backup archive,
// roster-static (no sim run):
//
//   - The current-master deterministic step (possessionTime(teamBaseTime(starters)))
//     distribution, and its correlation against a team shooting-efficiency (PPS)
//     proxy — re-baselining the stale June −0.42 premise on TODAY's engine, so
//     Phase 4/5's Cov sign-flip gate compares against a current reference, not one
//     predating J15/J18/J22/J23. Measured 2026-07-17: −0.3659 (n=2352, stride 8).
//
// FAITHFUL BASE_TIME IS A CONSTANT — THE PLAN'S RATIO PREMISE WAS REFUTED IN PHASE 0
// (binary-verified 2026-07-17; full proof chain in jsb-native/re-artifacts/
// jsb-J24-pace-dispersion-RE-20260716.md §8):
//
//	CEngine+0x38 (u) = 0.5 × (two stack doubles that are ONLY ever zeroed) = 0.0,
//	written unconditionally at the end of every FUN_004cfa50 composite run
//	(VA 0x4d4e5a-0x4d4e79; both operand slots' sole writers are the prologue
//	zero-stores at VA 0x4cfb90-0x4cfbab). With u = 0 every composite/param term
//	of FUN_004e4150 vanishes: numerator = 2880, denominator = 0 → +inf →
//	clamped to 16.0, then × (2.0 − tempo) with tempo = 1.0 (the IBL5.lge text
//	anchor at 0x2ee0) → pt = 16.0, constant, every matchup.
//
// So 5.60's composite ratio is DEAD CODE (all 24 .rdata weight constants verify,
// but they multiply by zero), there is NO composite-driven base_time dispersion,
// and the archives' Var(lnPOSS) = 0.000721 / Cov structure is carried ENTIRELY by
// the fast step classes (steal U{0..2}, DRB-push U{2..4}, {3..23} redraw) that
// Phases 2-4 port. An earlier revision of this diagnostic reproduced the ratio
// over real pooled composites (u = 1.0) and measured pre-clamp ~9.18 — recorded
// here as the tell that forced the binary re-verification; the "~15.3-15.5
// RE-predicted band" was never achievable because the code never ran.
//
// WHY THIS LIVES IN PACKAGE sim (not calibrate, where the plan first sketched it):
// the pre-port step must use the SAME starter selection and helpers the port
// replaces — selectStarters, teamBaseTime, possessionTime, floor1 — all UNEXPORTED
// in package sim and unreachable from calibrate.
//
// Build-tag gated behind `archive` — NEVER compiled by `go test ./...` or engine.yml.
// Invoke manually (run in the background; do NOT poll — work-triage repeat-polling rule):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  JSB_ARCHIVE_STRIDE=4 \
//	  go test -tags archive ./internal/sim -run PossessionClockBaseline -v -timeout 2h
//
// A coarse STRIDE=4 smoke gives the same directional verdict in minutes; STRIDE=1 is
// the full pass. RUNS/SEED do not apply (this is roster-static, no sim run).
package sim

import (
	"archive/zip"
	"encoding/json"
	"fmt"
	"io"
	"math"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/a-jay85/IBL5/engine/internal/backup"
)

const (
	// maxEntryBytesP0 bounds a single extracted zip entry (the real .plr/.sch are
	// small; this leaves ample headroom while staying zip-bomb safe).
	maxEntryBytesP0 = 128 << 20

	// minStartersP0 skips degenerate teams whose eligible pool cannot fill enough of
	// the lineup for the pre-port step / PPS-proxy measurement.
	minStartersP0 = 3

	// faithfulBaseTimeP0 is 5.60's actual half-court base_time: the FUN_004e4150
	// clamp ceiling, reached on every call because u (CEngine+0x38) is 0.0 — see the
	// header. Recorded in the artifact as the ground truth Phase 5 installs.
	faithfulBaseTimeP0 = 16.0
)

// pcStats summarizes the pre-port step distribution.
type pcStats struct {
	N    int     `json:"n"`
	Mean float64 `json:"mean"`
	SD   float64 `json:"sd"`
	Min  float64 `json:"min"`
	Max  float64 `json:"max"`
}

func summarize(xs []float64) pcStats {
	s := pcStats{N: len(xs)}
	if len(xs) == 0 {
		return s
	}
	s.Min, s.Max = xs[0], xs[0]
	var sum float64
	for _, x := range xs {
		sum += x
		if x < s.Min {
			s.Min = x
		}
		if x > s.Max {
			s.Max = x
		}
	}
	s.Mean = sum / float64(len(xs))
	var ss float64
	for _, x := range xs {
		d := x - s.Mean
		ss += d * d
	}
	s.SD = math.Sqrt(ss / float64(len(xs)))
	return s
}

type pcBaselineArtifact struct {
	Generated        string  `json:"generated"`
	Stride           int     `json:"stride"`
	Snapshots        int     `json:"snapshots"`
	TeamGames        int     `json:"team_games"`
	PreportStep      pcStats `json:"preport_step"`
	CorrStepPPS      float64 `json:"corr_step_pps_proxy"`
	CorrN            int     `json:"corr_n"`
	FaithfulBaseTime float64 `json:"faithful_base_time"` // constant 16.0 — u=0, see file header
}

func TestRealArchive_PossessionClockBaseline(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}
	stride := envIntP0("JSB_ARCHIVE_STRIDE", 4)
	if stride < 1 {
		stride = 1
	}

	zips, err := listZipsP0(dir)
	if err != nil {
		t.Fatalf("list zips: %v", err)
	}
	if len(zips) == 0 {
		t.Skipf("no .zip snapshots under %q", dir)
	}

	var steps, ppsProxy []float64
	snapshots := 0

	for i := 0; i < len(zips); i += stride {
		players, sched, ok := readSnapshotP0(zips[i])
		if !ok {
			continue
		}
		b, err := backup.ToBundle(players, sched, backup.AssembleOptions{})
		if err != nil {
			continue
		}
		if b.LeagueShotBaseline <= 0 {
			continue // degenerate/short roster — no faithful shot baseline
		}
		snapshots++

		// Distinct real teams, deterministic order.
		teamSet := map[int]bool{}
		for _, p := range b.Players {
			if p.TeamID >= 1 && p.TeamID <= 32 {
				teamSet[p.TeamID] = true
			}
		}
		teamIDs := make([]int, 0, len(teamSet))
		for id := range teamSet {
			teamIDs = append(teamIDs, id)
		}
		sort.Ints(teamIDs)

		for _, id := range teamIDs {
			starters := selectStarters(b.Players, id)
			if len(starters) < minStartersP0 {
				continue
			}
			pps := teamPPSProxy(starters)
			if pps <= 0 {
				continue
			}
			// Inlined RETIRED deterministic round-half-up step, NOT a call to the
			// live possessionTime — possessionTime is per-possession stochastic
			// since J24 Phase 2 (takes an *rng.RNG and jitters). This baseline
			// diagnostic reproduces the recorded PRE-PORT artifact (mean 13.944,
			// corr −0.3659 at stride 8), so it must keep the old round-half-up(pt)
			// math verbatim, independent of what tempo.go does today.
			steps = append(steps, float64(int(preportTeamBaseTimeP0(starters)+0.5)))
			ppsProxy = append(ppsProxy, pps)
		}
	}

	if len(steps) == 0 {
		t.Fatalf("no team-games measured over %d snapshots — cannot baseline", snapshots)
	}

	stepStats := summarize(steps)
	corr := pearsonP0(steps, ppsProxy)
	for name, v := range map[string]float64{
		"step_mean": stepStats.Mean, "step_sd": stepStats.SD, "corr_step_pps": corr,
	} {
		if math.IsNaN(v) || math.IsInf(v, 0) {
			t.Fatalf("non-finite baseline term %s = %v", name, v)
		}
	}

	art := pcBaselineArtifact{
		Generated:        time.Now().Format(time.RFC3339),
		Stride:           stride,
		Snapshots:        snapshots,
		TeamGames:        stepStats.N,
		PreportStep:      stepStats,
		CorrStepPPS:      corr,
		CorrN:            len(steps),
		FaithfulBaseTime: faithfulBaseTimeP0,
	}
	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-possession-clock-baseline.json", time.Now().Format("20060102")))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	t.Logf("J24 PHASE 0 — pre-port possession-clock baseline (%d team-games, %d snapshots, stride %d):",
		stepStats.N, snapshots, stride)
	t.Logf("  pre-port step: mean=%.3f sd=%.3f min=%.0f max=%.0f",
		stepStats.Mean, stepStats.SD, stepStats.Min, stepStats.Max)
	t.Logf("  corr(pre-port step, PPS-proxy) = %+.4f (n=%d)  <-- re-baselines the stale June -0.42 premise",
		corr, len(steps))
	t.Logf("  faithful base_time = %.1f CONSTANT (u=0; RE artifact §8) — all dispersion ports via Phases 2-4 fast classes",
		faithfulBaseTimeP0)
}

// preportTeamBaseTimeP0 is a frozen copy of the PRE-PORT (retired at J24 Phase 1)
// additive teamBaseTime stand-in — the roster-dependent base_time this baseline was
// measured against. Preserved locally so the recorded artifact (mean 13.944, corr
// −0.3659 at stride 8) stays reproducible after tempo.go dropped the live formula.
func preportTeamBaseTimeP0(starters []onCourt) float64 {
	const (
		offVolumeScale   = 0.02
		defRatingScale   = 0.083
		offVolumeNeutral = 161.0
		defRatingNeutral = 24.0
	)
	if len(starters) == 0 {
		return baseTimeLow
	}
	var offSum, defSum float64
	for _, p := range starters {
		offSum += float64(p.FGA + p.TGA + p.FTA)
		defSum += float64(p.OD + p.DD + p.PD + p.TD)
	}
	n := float64(len(starters))
	bt := baseTimeMid - offVolumeScale*(offSum/n-offVolumeNeutral) + defRatingScale*(defSum/n-defRatingNeutral)
	if bt < baseTimeLow {
		bt = baseTimeLow
	}
	if bt > baseTimeHigh {
		bt = baseTimeHigh
	}
	return bt
}

// teamPPSProxy is a roster shooting-efficiency proxy (minutes-weighted FGP rating) used
// only for the SECONDARY corr(step, PPS) re-baseline. It is a proxy, not a measured PPS
// — the exact figure is refined at Phase 4/5 against real .sco outcomes; Phase 0 only
// needs a current-master reference sign.
func teamPPSProxy(starters []onCourt) float64 {
	var wsum, msum float64
	for _, p := range starters {
		m := float64(p.RealLifeMIN)
		if m <= 0 {
			m = 1
		}
		wsum += m * floor1(p.FGP)
		msum += m
	}
	if msum <= 0 {
		return 0
	}
	return wsum / msum
}

func pearsonP0(xs, ys []float64) float64 {
	n := float64(len(xs))
	if len(xs) < 2 || len(xs) != len(ys) {
		return 0
	}
	var sx, sy, sxx, syy, sxy float64
	for i := range xs {
		sx += xs[i]
		sy += ys[i]
		sxx += xs[i] * xs[i]
		syy += ys[i] * ys[i]
		sxy += xs[i] * ys[i]
	}
	num := n*sxy - sx*sy
	den := math.Sqrt((n*sxx - sx*sx) * (n*syy - sy*sy))
	if den == 0 {
		return 0
	}
	return num / den
}

func envIntP0(key string, def int) int {
	if s := os.Getenv(key); s != "" {
		if v, err := strconv.Atoi(s); err == nil {
			return v
		}
	}
	return def
}

func listZipsP0(root string) ([]string, error) {
	var files []string
	err := filepath.WalkDir(root, func(path string, d os.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if !d.IsDir() && strings.EqualFold(filepath.Ext(path), ".zip") {
			files = append(files, path)
		}
		return nil
	})
	sort.Strings(files)
	return files, err
}

func readSnapshotP0(zipPath string) ([]backup.PlrPlayer, []backup.SchGame, bool) {
	zr, err := zip.OpenReader(zipPath)
	if err != nil {
		return nil, nil, false
	}
	defer func() { _ = zr.Close() }()

	var players []backup.PlrPlayer
	var sched []backup.SchGame
	var gotPlr, gotSch bool
	for _, f := range zr.File {
		switch strings.ToLower(filepath.Base(f.Name)) {
		case "ibl5.plr":
			rc, err := f.Open()
			if err != nil {
				return nil, nil, false
			}
			players, err = backup.ReadPlr(io.LimitReader(rc, maxEntryBytesP0))
			_ = rc.Close()
			if err != nil {
				return nil, nil, false
			}
			gotPlr = true
		case "ibl5.sch":
			rc, err := f.Open()
			if err != nil {
				return nil, nil, false
			}
			sched, err = backup.ReadSch(io.LimitReader(rc, maxEntryBytesP0))
			_ = rc.Close()
			if err != nil {
				return nil, nil, false
			}
			gotSch = true
		}
	}
	return players, sched, gotPlr && gotSch
}
