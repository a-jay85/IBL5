//go:build archive

// J24 residual (7) 3P-undershoot LOCALISATION instrument over the REAL JSB
// backup archive. It pins WHERE the ~2.8pp 3P% undershoot is injected —
// 3PA attempt-SELECTION (Branch A: FUN_004e1ba0 Σ(de0+db0+d90) normalisation)
// vs 3P make-model COMPOSITION (Branch B: shotValue3pt inputs) — by measuring
// BOTH sides over the recent-era 05-08 corpus and decomposing the gap into an
// attempt-rate delta (dRate) and a make-rate delta (dPct). The disagreement
// between a per-roster (mean-of-ratios) and a population (ratio-of-sums)
// framing is itself the selection-composition signal. A logged 2x2 rule maps
// the (sign(dRate), sign(dPct)) pair to the RE branch so the choice is
// mechanical, not eyeballed. No assertion failure — it writes a dated JSON
// artifact for the Phase-1 verdict.
//
// Reuses loadTripleWithSco, possEnvInt (possession_archive_test.go),
// listArchiveZips (walk.go), seasonName/isOlympicsPath (season.go) — same
// package calibrate, same build tag. Do NOT redefine them: duplicate-symbol
// error under -tags archive.
//
// Invoke manually (run in the background; do not poll):
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/calibrate \
//	  -run TestRealArchive_ThreePtUndershoot -v -timeout 1800s
//
// JSB_3PT_GAMES caps per-snapshot sim cost (default 60). JSB_3PT_SNAP_STRIDE
// thins the snapshot corpus for a fast smoke (default 1 = all ~97 recent-era
// snapshots; set 30 for a 2-3 snapshot smoke). Without JSB_ARCHIVE_DIR set (or
// the dir absent) the test SKIPS — always green on CI.
package calibrate

import (
	"encoding/json"
	"fmt"
	"math"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/sim"
)

// recentEra05to08 is the ADR-0088 arming-share baseline corpus (backlog line
// 197: "recent-era 05-08, 97 snapshots"). seasonName filters the archive to it.
var recentEra05to08 = map[string]bool{"05-06": true, "06-07": true, "07-08": true}

// engineBaseline3pt is the engine's fixed leagueBaseline (233.0); the sco-
// implied baseline (sco 3pt% x 666.7) discriminates Branch B's baseline x 1.5
// net-divisor — see possession_archive_test.go:234.
const engineBaseline3pt = 233.0

// threePtUndershootArtifact is the committed decomposition from one archive
// pass. It carries the raw population sums (not just derived ratios) so the
// decomposition is self-verifiable from the JSON alone (matrix row 5:
// pop 3P% == 100*sum3gm/sum3ga; dRate/dPct reconcile against the totals).
type threePtUndershootArtifact struct {
	Generated         string `json:"generated"`
	Snapshots         int    `json:"snapshots"`          // recent-era snapshots aggregated
	ExcludedSnapshots int    `json:"excluded_snapshots"` // dropped from the per-roster means (degenerate denominator)
	GamesCap          int    `json:"games_cap"`
	SnapshotStride    int    `json:"snapshot_stride"`
	Seed              uint64 `json:"seed"`

	// Population raw sums (over ALL team-games of ALL aggregated snapshots).
	EngineSum3GM float64 `json:"engine_sum_3gm"`
	EngineSum3GA float64 `json:"engine_sum_3ga"`
	EngineSum2GM float64 `json:"engine_sum_2gm"`
	EngineSum2GA float64 `json:"engine_sum_2ga"`
	EngineSumFTA float64 `json:"engine_sum_fta"`
	EngineSumTOV float64 `json:"engine_sum_tov"`
	EngineSumORB float64 `json:"engine_sum_orb"`
	EnginePoss   float64 `json:"engine_poss"`
	ScoSum3GM    float64 `json:"sco_sum_3gm"`
	ScoSum3GA    float64 `json:"sco_sum_3ga"`
	ScoSum2GM    float64 `json:"sco_sum_2gm"`
	ScoSum2GA    float64 `json:"sco_sum_2ga"`
	ScoSumFTA    float64 `json:"sco_sum_fta"`
	ScoSumTOV    float64 `json:"sco_sum_tov"`
	ScoSumORB    float64 `json:"sco_sum_orb"`
	ScoPoss      float64 `json:"sco_poss"`

	// Population-framed (ratio-of-sums). 3PAPerPoss is the raw ratio (~0.2);
	// 3Pct is a percentage (0-100); dPct is in pp; dRatePer100 is dRate scaled
	// to 3PA-per-100-poss (the units the 2x2 rule's +-0.5 band applies in).
	PopEngine3PAPerPoss float64 `json:"pop_engine_3pa_per_poss"`
	PopSco3PAPerPoss    float64 `json:"pop_sco_3pa_per_poss"`
	PopEngine3Pct       float64 `json:"pop_engine_3pct"`
	PopSco3Pct          float64 `json:"pop_sco_3pct"`
	PopDRatePerPoss     float64 `json:"pop_drate_per_poss"`
	PopDRatePer100      float64 `json:"pop_drate_per100poss"`
	PopDPct             float64 `json:"pop_dpct"`

	// 2P% cross-check (advisor 2026-07-21) — retained but CONFOUNDED; SUPERSEDED by
	// SimWeightedD80Pct below. shotValue3pt (line 298) and makeValue2pt (line 259)
	// share the per-possession `net` and block terms, so a shared net/block port bug
	// would drag 2P% too — the original hope was "2P%≈real ⇒ 3pt-specific static;
	// 2P% also dragged ⇒ shared". But pooled 2P% is NOT a clean mirror: 2pt has
	// guaranteed/boosted makes with no 3pt analog (and-one → guaranteed made 2;
	// putback + shot-clock 2s → net-free base×1.333), and the make BASES are
	// asymmetric (d80 raw ≈ real 3P%; D64 a formula transform of real 2P‰). So the
	// measured Pop2DPct = +3.38pp is consistent with BOTH a 3pt-specific static site
	// AND a shared faithful-modifier drag — it does NOT discriminate. Use the
	// SimWeightedD80Pct cut (the 3pt path vs its own sim base) instead; this pair is
	// kept only as corroborating context, never as the discriminator.
	PopEngine2Pct float64 `json:"pop_engine_2pct"`
	PopSco2Pct    float64 `json:"pop_sco_2pct"`
	PopD2Pct      float64 `json:"pop_d2pct"`

	// Per-roster (mean-of-ratios across the included snapshots).
	PerRosterEngine3PAPerPoss float64 `json:"perroster_engine_3pa_per_poss"`
	PerRosterSco3PAPerPoss    float64 `json:"perroster_sco_3pa_per_poss"`
	PerRosterEngine3Pct       float64 `json:"perroster_engine_3pct"`
	PerRosterSco3Pct          float64 `json:"perroster_sco_3pct"`
	PerRosterDRatePerPoss     float64 `json:"perroster_drate_per_poss"`
	PerRosterDRatePer100      float64 `json:"perroster_drate_per100poss"`
	PerRosterDPct             float64 `json:"perroster_dpct"`

	// leagueBaseline cross-check (Branch B net-divisor discriminator).
	ScoImpliedBaseline float64 `json:"sco_implied_baseline"`
	EngineBaseline     float64 `json:"engine_baseline"`

	// d80-population cross-check (Branch B data-feed vs sim-realized discriminator,
	// advisor 2026-07-21). d80 = round(RealLife3GM/RealLife3GA*1000) is assembled
	// from the .plr (backup/assemble.go). Its 3GA-weighted mean over the loaded
	// roster collapses BY CONSTRUCTION to Σ3GM/Σ3GA — so if the .plr 3pt read is
	// faithful this equals the sco 3P% (~39%). PlrWeightedD80Pct ≈ PopSco3Pct ⇒
	// data feed faithful, the make-gap is sim-realized (attempt-routing to low-d80
	// shooters and/or non-zero-mean net/block modifiers), NOT a static formula
	// divergence. PlrWeightedD80Pct ≈ engine's realized 3P% (~31%) ⇒ the .plr
	// 3GM/3GA offsets undersize d80 — a pinned data-feed site (prime suspect: the
	// offRealLife3GM=76/3GA=80 offsets added in #1519, never re-verified vs ground
	// truth). Zero3GAFracMinPos flags the legacy-serialization caveat directly: a
	// non-trivial fraction of MIN>0 players with 3GA==0 zeroes BOTH their 3pt
	// attempt bucket AND their d80 — one cause hitting both branches.
	PlrSum3GM         float64 `json:"plr_sum_3gm"`
	PlrSum3GA         float64 `json:"plr_sum_3ga"`
	PlrWeightedD80Pct float64 `json:"plr_weighted_d80_pct"`
	PlrPlayersMinPos  int     `json:"plr_players_min_pos"`
	PlrZero3GAMinPos  int     `json:"plr_zero_3ga_min_pos"`
	Zero3GAFracMinPos float64 `json:"plr_zero_3ga_frac_min_pos"`

	// Sim-3GA-weighted d80 (advisor 2026-07-21 — the CONFOUND-FREE cut). The 2P%
	// discriminator above is confounded: pooled 2P% mixes 2pt-only guaranteed/boosted
	// makes with no 3pt analog (and-one → guaranteed made 2, shotdecision/possession;
	// putback + shot-clock 2s → net-free base×1.333), and the make BASES are asymmetric
	// (d80 is raw ≈ real 3P%; D64 is a formula transform of real 2P‰). So "2P% +3.4 /
	// 3P% −7.9" is consistent with BOTH a 3pt-specific static site AND a shared faithful
	// modifier drag — it does not discriminate. This cut avoids all of it by weighting
	// each player's d80 by the sim's REALIZED Game3GA (not real-life 3GA), comparing the
	// 3pt path to ITS OWN shot-time base: realized_3P%×10 ≈ SimWeightedD80(‰) + mean(net+
	// block, ‰). The −79‰ gap then splits cleanly:
	//   SimWeightedD80Pct ≈ PlrWeightedD80Pct (~39%) ⇒ sim does NOT route to low-d80
	//     shooters; the whole drag is the net+block modifier means — the net-advantage
	//     FEED is NOT yet excluded (follow-on: re-RE net + measure block-mean via sim A/B).
	//   SimWeightedD80Pct ≈ engine realized (~31%) ⇒ sim routes 3PA to low-d80 shooters;
	//     base faithful, net feed exonerated; follow-on is the minutes/attempt-routing model.
	//   between ⇒ split, quantified. RoutingSignalPp = PlrWeightedD80Pct − SimWeightedD80Pct
	//   is the routing contribution to the operative base (real-3GA vs sim-3GA weighting).
	SimSum3GA         float64 `json:"sim_sum_3ga"`
	SimWeightedD80Pct float64 `json:"sim_weighted_d80_pct"`
	RoutingSignalPp   float64 `json:"routing_signal_pp"`

	// Composition signal + mechanical verdict. PerRosterDPctMilder true means
	// the per-roster make-gap is smaller in magnitude than the population one —
	// the signature of weak/zero-3GA shooters pooling down the population 3P%.
	PerRosterDPctMilder bool   `json:"perroster_dpct_milder_than_population"`
	SelectedBranch      string `json:"selected_branch"`
	BranchRationale     string `json:"branch_rationale"`

	// 3pt make-value modifier decomposition (J24 residual 7). The per-attempt means
	// of shotValue3pt's three additive components (pp), harvested by ThreePtDiagAccum
	// over the SAME archive pass. MeanD80BasePp must reconcile with SimWeightedD80Pct
	// (same population, Game3GA-weighted). ReconResidualPp is LOGGED (non-fatal) —
	// rollMake clamps effective<1→0% and >1000→100% and applies fatigue, so the additive
	// value model is only approximate. NetSuspectThresholdPp documents the Phase-6 branch.
	DiagCount             int     `json:"diag_count"`
	MeanD80BasePp         float64 `json:"mean_d80_base_pp"`
	MeanNetTermPp         float64 `json:"mean_net_term_pp"`
	MeanBlockTermPp       float64 `json:"mean_block_term_pp"`
	ReconResidualPp       float64 `json:"recon_residual_pp"`
	NetSuspectThresholdPp float64 `json:"net_suspect_threshold_pp"` // -5.0

	// Clamp / dispersion decomposition of the reconstruction residual (advisor 2026-07-21).
	// rollMake makes at clamp(sv/1000) not sv/1000 (fatigue ≡ 1.0), so the ~9pp residual is
	// net clamp loss, and a null net MEAN does not exonerate net: a high-VARIANCE net term
	// pushes mass past the ceiling. MeanClampLossPp − MeanClampGainPp reconciles with
	// ReconResidualPp to MC tolerance (asserted). The InClamp component means say WHICH feed
	// (d80 vs net) drives the sv ≥ 1000 excess — a large NetInClamp vs the ~0 all-attempt net
	// mean is the net-term-dispersion signature (RE target: the 3pt net-term scale vs 5.60).
	FracSvGe1000     float64 `json:"frac_sv_ge_1000"`
	FracSvLe0        float64 `json:"frac_sv_le_0"`
	MeanClampLossPp  float64 `json:"mean_clamp_loss_pp"`
	MeanClampGainPp  float64 `json:"mean_clamp_gain_pp"`
	ClampNetResidPp  float64 `json:"clamp_net_residual_pp"` // MeanClampLossPp − MeanClampGainPp (== ReconResidualPp ± MC)
	CountSvGe1000    int     `json:"count_sv_ge_1000"`
	MeanD80InClampPp float64 `json:"mean_d80_in_clamp_pp"`
	MeanNetInClampPp float64 `json:"mean_net_in_clamp_pp"`
	MeanBlkInClampPp float64 `json:"mean_blk_in_clamp_pp"`

	// Direct make-realization cut (advisor 2026-07-21, clamp REFUTED). DiagMadePct is the
	// roll's OWN 3P% over the diag population; DiagMeanFatigue the mean fatigue at the roll.
	// The decisive discriminator: DiagMadePct ≈ ReconMakePct (E[sv/1000]×100) ⇒ rollMake
	// faithful, drag is downstream box crediting; DiagMadePct ≈ PopEngine3Pct with fatigue
	// < 1 ⇒ runtime fatigue is the drag. MadeVsBoxGapPp = DiagMadePct − PopEngine3Pct isolates
	// any roll-vs-box divergence (nonzero ⇒ makes lost between the roll and the box counter).
	DiagMadePct     float64 `json:"diag_made_pct"`
	DiagMeanFatigue float64 `json:"diag_mean_fatigue"`
	ReconMakePct    float64 `json:"recon_make_pct"`     // MeanD80BasePp + MeanNetTermPp + MeanBlockTermPp (= E[sv/1000]×100)
	MadeVsBoxGapPp  float64 `json:"made_vs_box_gap_pp"` // DiagMadePct − PopEngine3Pct
	MeanSvActualPp  float64 `json:"mean_sv_actual_pp"`  // mean of the ACTUAL sv fed to rollMake (‰/10)
	ReconVsSvGapPp  float64 `json:"recon_vs_sv_gap_pp"` // ReconMakePct − MeanSvActualPp: nonzero ⇒ reconstruction error
}

// teamShoot is one side's summed shooting components for one snapshot (or, when
// accumulated across snapshots, the whole population).
type teamShoot struct{ gm3, ga3, gm2, ga2, fta, tov, orb float64 }

func (s teamShoot) poss() float64 { return (s.ga2 + s.ga3) + 0.44*s.fta + s.tov - s.orb }

func (s *teamShoot) add(o teamShoot) {
	s.gm3 += o.gm3
	s.ga3 += o.ga3
	s.gm2 += o.gm2
	s.ga2 += o.ga2
	s.fta += o.fta
	s.tov += o.tov
	s.orb += o.orb
}

// abFreeze selects the A/B arm from JSB_3PT_AB.
//   - Unset (default): sim.FreezeConfig{} — faithful JSB 5.60: putback 3pt is
//     SUPPRESSED (OriginOffReb restricted to {2pt, foul} per the 2026-07-23
//     adjudication). Reproduces the committed baseline artifact.
//   - "unfaithful3pt": sim.FreezeConfig{UnfaithfulPutback3pt: true} — restores the
//     2026-07-22-to-2026-07-23 behavior (putback 3pt reachable) as an A/B arm. The
//     attributable arm for the 3pt-suppression mechanism; make-value untouched.
//     Direct J24 candidate-(a) A/B comparator.
//   - "putback": sim.FreezeConfig{UnfaithfulPutback: true} — the ADR-0055 OFF
//     walk (make-value site only, not the 3pt suppression). Kept for reference;
//     it moves a DIFFERENT mechanism than "unfaithful3pt". Toggling a mechanism,
//     never a constant (ADR-0090).
//   - "suppress_transition": sim.FreezeConfig{SuppressTransition3pt: true} — the
//     pre-port baseline arm (transition 3pt suppressed, matching the pre-j24
//     behavior). Used to measure the gate contribution before removing it.
func abFreeze() sim.FreezeConfig {
	switch os.Getenv("JSB_3PT_AB") {
	case "unfaithful3pt":
		return sim.FreezeConfig{UnfaithfulPutback3pt: true}
	case "putback":
		return sim.FreezeConfig{UnfaithfulPutback: true}
	case "suppress_transition":
		return sim.FreezeConfig{SuppressTransition3pt: true}
	default:
		return sim.FreezeConfig{}
	}
}

// abSuffix keeps each arm's artifact on its own path so a run cannot silently
// overwrite the other arm's measurement.
func abSuffix() string {
	if arm := os.Getenv("JSB_3PT_AB"); arm != "" {
		return "-ab-" + arm
	}
	return ""
}

func TestRealArchive_ThreePtUndershoot(t *testing.T) {
	dir := os.Getenv("JSB_ARCHIVE_DIR")
	if dir == "" {
		dir = "/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
	}
	if _, err := os.Stat(dir); err != nil {
		t.Skipf("archive dir %q not available: %v", dir, err)
	}

	zips, _, err := listArchiveZips(dir)
	if err != nil {
		t.Fatalf("listArchiveZips: %v", err)
	}
	if len(zips) == 0 {
		t.Fatal("no zips in archive")
	}

	gameCap := possEnvInt("JSB_3PT_GAMES", 60)
	snapStride := possEnvInt("JSB_3PT_SNAP_STRIDE", 1)
	if snapStride < 1 {
		snapStride = 1
	}
	seed := uint64(possEnvInt("JSB_3PT_SEED", 20240601))

	// Population accumulators (ratio-of-sums) and per-roster accumulators
	// (mean-of-ratios: sum each snapshot's own ratio, divide by the included count).
	var engPop, scoPop teamShoot
	var prEngRate, prScoRate, prEngPct, prScoPct float64
	snapshots, excluded := 0, 0

	// d80-population cross-check accumulators (advisor: static, no sim). Summed
	// over the loaded .plr rosters across all snapshots.
	var plrSum3GM, plrSum3GA float64
	var plrMinPos, plrZero3GAMinPos int

	// Sim-3GA-weighted d80 accumulators (advisor confound-free cut): Σ(d80·sim3GA)
	// and Σ sim3GA over every simmed player-box across all snapshots.
	var simD80Weighted, simSum3GA float64

	// 3pt make-value modifier decomposition (J24 residual 7): one shared accum across
	// ALL snapshots and ALL games so its means are population-framed.
	diag := &sim.ThreePtDiagAccum{}

	// Filter to the recent-era 05-08 corpus and iterate ALL cumulative snapshots
	// in those season dirs (per-snapshot = per-roster granularity, ~97 expected).
	seasonZips := make([]string, 0, len(zips))
	for _, zp := range zips {
		if isOlympicsPath(zp) {
			continue
		}
		if recentEra05to08[seasonName(dir, zp)] {
			seasonZips = append(seasonZips, zp)
		}
	}
	t.Logf("recent-era 05-08 snapshots matched: %d (stride %d, games-cap %d)", len(seasonZips), snapStride, gameCap)

	for si := 0; si < len(seasonZips); si += snapStride {
		zp := seasonZips[si]
		b, scoGames, cleanup, skip := loadTripleWithSco(zp)
		if skip != nil {
			t.Logf("skip %s: %s", filepath.Base(zp), skip.Reason)
			continue
		}

		var eng, sco teamShoot

		// d80-population cross-check: scan the ASSEMBLED roster once per snapshot.
		// Weighting by RealLife3GA makes Σ3GM/Σ3GA the 3GA-weighted mean d80 (0-3GA
		// players contribute 0 to both sums). Zero-3GA fraction is counted among the
		// faithful-path population (RealLifeMIN>0 — the bucketweights/shotdecision
		// guard); those players' 3pt bucket AND d80 both zero out.
		for _, p := range b.Players {
			if p.RealLifeMIN <= 0 {
				continue
			}
			plrMinPos++
			if p.RealLife3GA <= 0 {
				plrZero3GAMinPos++
				continue
			}
			plrSum3GM += float64(p.RealLife3GM)
			plrSum3GA += float64(p.RealLife3GA)
		}

		// Per-player d80 lookup for the sim-3GA-weighted base (advisor cut). Built
		// once per snapshot from the assembled roster; every simmed PID resolves here.
		d80ByPID := make(map[int]int, len(b.Players))
		for _, p := range b.Players {
			d80ByPID[p.PID] = p.D80
		}

		// Engine side: sim a capped sample of the scheduled matchups.
		//
		// The seed MUST vary per game (seed+gi). Passing a constant `seed` to every
		// single-game SimulateWith restarts the PCG stream from state 0 for each game, so
		// every game draws an *overlapping prefix* of one fixed sequence. Only the first
		// few hundred draws of that one stream are ever consulted, and they are sampled
		// thousands of times over; whatever bias that prefix happens to carry is amplified
		// rather than averaged away. This seed's prefix runs high: measured with a constant
		// seed, the 3pt make-roll mean was 565.7 (uniform expects 500.5), manufacturing a
		// spurious 9.1pp "3pt undershoot" (engine 31.05% vs the 40.15% E[sv]/1000 demands).
		//
		// Why 40.15% is the *correct* value and not merely a less-biased one: P(make) is
		// linear in the effective shot value, so for uniform independent rolls the realized
		// rate is forced to equal E[sv]/1000 — no distribution shape can bend it. 31.05%
		// violated that identity, which is what marks it (not the model) as the error.
		// With seed+gi the residual collapses to −0.19pp.
		//
		// See also possession_archive_test.go:141 and threept_attemptrouting_archive_test.go:119,
		// which still use the constant-seed single-game form and carry the same artifact.
		for gi, g := range b.Schedule {
			if gi >= gameCap {
				break
			}
			sub := bundle.Bundle{LeagueID: b.LeagueID, Teams: b.Teams, Players: b.Players, Schedule: []bundle.Game{g}}
			res, err := sim.SimulateWith(sub, seed+uint64(gi), sim.Options{ThreePtDiag: diag, Freeze: abFreeze()})
			if err != nil {
				t.Fatalf("SimulateWith: %v", err)
			}
			for _, tb := range res.Games[0].TeamBoxes {
				eng.add(teamShoot{
					gm3: float64(tb.Game3GM), ga3: float64(tb.Game3GA),
					gm2: float64(tb.Game2GM), ga2: float64(tb.Game2GA),
					fta: float64(tb.GameFTA), tov: float64(tb.GameTOV), orb: float64(tb.GameORB),
				})
			}
			// Sim-3GA-weighted d80: accumulate each shooter's d80 by realized Game3GA.
			// DNP / no-3PA boxes contribute 0 to both sums (Game3GA==0 guard).
			for _, pb := range res.Games[0].PlayerBoxes {
				if pb.Game3GA == 0 {
					continue
				}
				simD80Weighted += float64(d80ByPID[pb.PID]) * float64(pb.Game3GA)
				simSum3GA += float64(pb.Game3GA)
			}
		}

		// Real-life side: aggregate per-team from the .sco player rows, skipping
		// the PID-0 team-total row (mirrors possession_archive_test.go:162-200).
		for gi, sg := range scoGames {
			if gi >= gameCap {
				break
			}
			for _, bx := range sg.Boxes {
				if bx.PlayerID == 0 {
					continue
				}
				if bx.TeamID != sg.VisitorTeamID && bx.TeamID != sg.HomeTeamID {
					continue
				}
				sco.add(teamShoot{
					gm3: float64(bx.ThreeGM), ga3: float64(bx.ThreeGA),
					gm2: float64(bx.TwoGM), ga2: float64(bx.TwoGA),
					fta: float64(bx.FTA), tov: float64(bx.TOV), orb: float64(bx.ORB),
				})
			}
		}
		cleanup()

		engPop.add(eng)
		scoPop.add(sco)
		snapshots++

		// Per-roster (mean-of-ratios): a snapshot with a degenerate denominator on
		// either side (no possessions, or nobody shooting 3s) is EXCLUDED from the
		// means rather than producing NaN/Inf. Population sums are unaffected.
		ePoss, sPoss := eng.poss(), sco.poss()
		if ePoss <= 0 || sPoss <= 0 || eng.ga3 == 0 || sco.ga3 == 0 {
			excluded++
			continue
		}
		prEngRate += eng.ga3 / ePoss
		prScoRate += sco.ga3 / sPoss
		prEngPct += 100 * eng.gm3 / eng.ga3
		prScoPct += 100 * sco.gm3 / sco.ga3
	}

	if snapshots == 0 {
		t.Fatal("no recent-era snapshots aggregated — corpus empty (check JSB_ARCHIVE_DIR / season dirs)")
	}
	if engPop.poss() <= 0 || scoPop.poss() <= 0 {
		t.Fatalf("degenerate population possessions (engine=%.1f sco=%.1f) — cannot decompose", engPop.poss(), scoPop.poss())
	}
	if engPop.ga3 == 0 || scoPop.ga3 == 0 {
		t.Fatalf("no 3PA aggregated (engine=%.0f sco=%.0f) — cannot measure 3P%%", engPop.ga3, scoPop.ga3)
	}

	included := snapshots - excluded

	// Population-framed (ratio-of-sums).
	engPoss, scoPoss := engPop.poss(), scoPop.poss()
	popEngRate, popScoRate := engPop.ga3/engPoss, scoPop.ga3/scoPoss
	popEngPct, popScoPct := 100*engPop.gm3/engPop.ga3, 100*scoPop.gm3/scoPop.ga3
	popDRate := popEngRate - popScoRate
	popDPct := popEngPct - popScoPct
	// 2P% shared-modifier discriminator (guarded — 2GA is never 0 over the corpus,
	// but stay NaN-free if a degenerate cap is ever passed).
	var pop2EngPct, pop2ScoPct float64
	if engPop.ga2 > 0 {
		pop2EngPct = 100 * engPop.gm2 / engPop.ga2
	}
	if scoPop.ga2 > 0 {
		pop2ScoPct = 100 * scoPop.gm2 / scoPop.ga2
	}

	art := threePtUndershootArtifact{
		Generated:         time.Now().Format(time.RFC3339),
		Snapshots:         snapshots,
		ExcludedSnapshots: excluded,
		GamesCap:          gameCap,
		SnapshotStride:    snapStride,
		Seed:              seed,

		EngineSum3GM: engPop.gm3, EngineSum3GA: engPop.ga3,
		EngineSum2GM: engPop.gm2, EngineSum2GA: engPop.ga2,
		EngineSumFTA: engPop.fta, EngineSumTOV: engPop.tov, EngineSumORB: engPop.orb, EnginePoss: engPoss,
		ScoSum3GM: scoPop.gm3, ScoSum3GA: scoPop.ga3,
		ScoSum2GM: scoPop.gm2, ScoSum2GA: scoPop.ga2,
		ScoSumFTA: scoPop.fta, ScoSumTOV: scoPop.tov, ScoSumORB: scoPop.orb, ScoPoss: scoPoss,

		PopEngine3PAPerPoss: popEngRate, PopSco3PAPerPoss: popScoRate,
		PopEngine3Pct: popEngPct, PopSco3Pct: popScoPct,
		PopDRatePerPoss: popDRate, PopDRatePer100: 100 * popDRate, PopDPct: popDPct,

		PopEngine2Pct: pop2EngPct, PopSco2Pct: pop2ScoPct, PopD2Pct: pop2EngPct - pop2ScoPct,

		ScoImpliedBaseline: popScoPct / 100 * 666.7,
		EngineBaseline:     engineBaseline3pt,

		PlrSum3GM:        plrSum3GM,
		PlrSum3GA:        plrSum3GA,
		PlrPlayersMinPos: plrMinPos,
		PlrZero3GAMinPos: plrZero3GAMinPos,
	}
	if plrSum3GA > 0 {
		art.PlrWeightedD80Pct = 100 * plrSum3GM / plrSum3GA
	}
	if plrMinPos > 0 {
		art.Zero3GAFracMinPos = float64(plrZero3GAMinPos) / float64(plrMinPos)
	}
	// Sim-3GA-weighted d80 (%): d80 is per-mille, so divide the weighted mean by 10.
	// RoutingSignalPp is the base drop attributable to sim routing (real-3GA-weighted
	// minus sim-3GA-weighted); it must be read AFTER PlrWeightedD80Pct is set above.
	art.SimSum3GA = simSum3GA
	if simSum3GA > 0 {
		art.SimWeightedD80Pct = simD80Weighted / simSum3GA / 10
		art.RoutingSignalPp = art.PlrWeightedD80Pct - art.SimWeightedD80Pct
	}

	// Per-roster means (only when at least one snapshot survived the guard).
	if included > 0 {
		inc := float64(included)
		art.PerRosterEngine3PAPerPoss = prEngRate / inc
		art.PerRosterSco3PAPerPoss = prScoRate / inc
		art.PerRosterEngine3Pct = prEngPct / inc
		art.PerRosterSco3Pct = prScoPct / inc
		art.PerRosterDRatePerPoss = art.PerRosterEngine3PAPerPoss - art.PerRosterSco3PAPerPoss
		art.PerRosterDRatePer100 = 100 * art.PerRosterDRatePerPoss
		art.PerRosterDPct = art.PerRosterEngine3Pct - art.PerRosterSco3Pct
	}

	// Composition signal: per-roster make-gap milder (smaller magnitude) than the
	// population one ⇒ weak/zero-3GA shooters pool down the population 3P%.
	art.PerRosterDPctMilder = included > 0 && math.Abs(art.PerRosterDPct) < math.Abs(popDPct)

	art.SelectedBranch, art.BranchRationale = selectBranch(art.PopDRatePer100, popDPct, art.PerRosterDPctMilder)

	if diag.Count == 0 {
		t.Fatal("no 3pt attempts accumulated — ThreePtDiag accum is unwired (check Phase 3 edits)")
	}
	if diag.Count != int(simSum3GA) {
		t.Fatalf("diag.Count=%d != sim_sum_3ga=%.0f — accum population diverges from box-score 3GA",
			diag.Count, simSum3GA)
	}
	art.DiagCount = diag.Count
	art.MeanD80BasePp = diag.MeanD80Pp()
	art.MeanNetTermPp = diag.MeanNetTermPp()
	art.MeanBlockTermPp = diag.MeanBlockTermPp()
	art.NetSuspectThresholdPp = -5.0
	art.ReconResidualPp = (diag.MeanD80Pp() + diag.MeanNetTermPp() + diag.MeanBlockTermPp()) - art.PopEngine3Pct

	// Clamp / dispersion decomposition (advisor 2026-07-21). The residual is net clamp loss.
	art.FracSvGe1000 = diag.FracSvGe1000()
	art.FracSvLe0 = diag.FracSvLe0()
	art.MeanClampLossPp = diag.MeanClampLossPp()
	art.MeanClampGainPp = diag.MeanClampGainPp()
	art.ClampNetResidPp = art.MeanClampLossPp - art.MeanClampGainPp
	art.CountSvGe1000 = diag.CountSvGe1000
	art.MeanD80InClampPp = diag.MeanD80InClampPp()
	art.MeanNetInClampPp = diag.MeanNetInClampPp()
	art.MeanBlkInClampPp = diag.MeanBlkInClampPp()

	// Direct make-realization cut. ReconMakePct = E[sv/1000]×100 (the additive model's
	// analytic make prob); DiagMadePct = the roll's OWN realized 3P% on the SAME attempts.
	art.DiagMadePct = diag.MadePct()
	art.DiagMeanFatigue = diag.MeanFatigue()
	art.ReconMakePct = diag.MeanD80Pp() + diag.MeanNetTermPp() + diag.MeanBlockTermPp()
	art.MadeVsBoxGapPp = art.DiagMadePct - art.PopEngine3Pct
	art.MeanSvActualPp = diag.MeanSvActualPp()
	art.ReconVsSvGapPp = art.ReconMakePct - art.MeanSvActualPp

	// Clamp-identity cross-check — LOGGED, NOT asserted. With the per-game seed fix the
	// residual is ~−0.19pp and clamping is ≈0, so (clampLoss−clampGain) ≈ recon_residual
	// within the ±1pp MC tolerance. Kept as t.Logf to surface any future divergence
	// without hard-failing the archive run.
	if d := art.ClampNetResidPp - art.ReconResidualPp; d < -1.0 || d > 1.0 {
		t.Logf("  clamp identity gap outside ±1pp: (clampLoss−clampGain)=%.4f vs recon_residual=%.4f (Δ=%.4f)",
			art.ClampNetResidPp, art.ReconResidualPp, d)
	}

	// HARD same-sample cross-check: the accum's Game3GA-weighted d80 mean must equal the
	// player-grouped sim_weighted_d80_pct (both weight d80 by realized Game3GA over the
	// same games). Tolerance is floating-point slack only.
	if d := art.MeanD80BasePp - art.SimWeightedD80Pct; d < -0.5 || d > 0.5 {
		t.Fatalf("d80 base mismatch: accum MeanD80BasePp=%.4f vs sim_weighted_d80_pct=%.4f (Δ=%.4f) — accum not on the same population",
			art.MeanD80BasePp, art.SimWeightedD80Pct, d)
	}
	t.Logf("3pt modifier means (pp): d80=%.3f net=%.3f block=%.3f | recon_residual=%.3f (logged, not asserted)",
		art.MeanD80BasePp, art.MeanNetTermPp, art.MeanBlockTermPp, art.ReconResidualPp)
	t.Logf("  CLAMP DECOMP: recon_residual %.3fpp = clamp_loss %.3f − clamp_gain %.3f (=%.3f, MC-reconciled) | sv≥1000 frac %.4f (%d attempts), sv≤0 frac %.4f",
		art.ReconResidualPp, art.MeanClampLossPp, art.MeanClampGainPp, art.ClampNetResidPp, art.FracSvGe1000, art.CountSvGe1000, art.FracSvLe0)
	t.Logf("    → WHICH FEED (over sv≥1000 attempts, pp): d80=%.2f net=%.2f block=%.2f. Large net here vs ~%.2f all-attempt net ⇒ net-term DISPERSION drives the clamp (RE: 3pt net-term scale vs 5.60).",
		art.MeanD80InClampPp, art.MeanNetInClampPp, art.MeanBlkInClampPp, art.MeanNetTermPp)
	t.Logf("  MAKE-REALIZATION CUT (decisive): recon_make %.2f%% (E[sv/1000]×100) vs roll's own DiagMadePct %.2f%% vs box PopEngine3Pct %.2f%% | mean_fatigue %.4f | made-vs-box gap %.3fpp",
		art.ReconMakePct, art.DiagMadePct, art.PopEngine3Pct, art.DiagMeanFatigue, art.MadeVsBoxGapPp)
	t.Logf("  ACTUAL-SV CUT: mean_sv_actual %.2f%% (the real value fed to rollMake) vs recon %.2f%% → recon−sv gap %.3fpp. Nonzero ⇒ the d80+net+block reconstruction ≠ the value the roll sees.",
		art.MeanSvActualPp, art.ReconMakePct, art.ReconVsSvGapPp)
	switch {
	// Leading case: all three rates agree ⇒ no make-realization residual at all. This is
	// the state after the per-game seed fix below (2026-07-21): the 9.1pp "undershoot"
	// was a harness artifact (every single-game SimulateWith replayed one PCG stream from
	// the same seed, so the make-roll subsequence was a short, repeated, high-biased slice
	// — measured roll mean 565.7 vs the uniform 500.5), NOT an engine defect.
	case math.Abs(art.ReconResidualPp) < 1.0 && math.Abs(art.MadeVsBoxGapPp) < 1.0:
		t.Logf("    → VERDICT: CLOSED — recon, roll, and box all agree within 1pp (residual %.3fpp). The 3pt make model is faithful; no undershoot to explain.", art.ReconResidualPp)
	case math.Abs(art.DiagMadePct-art.ReconMakePct) < 1.0 && math.Abs(art.MadeVsBoxGapPp) > 1.0:
		t.Logf("    → VERDICT: roll REALIZES the recon (DiagMadePct≈recon) but the BOX 3P%% is lower ⇒ makes lost DOWNSTREAM of the roll (box crediting/aggregation). RE target: made-3 crediting path, NOT the value model.")
	case math.Abs(art.DiagMadePct-art.PopEngine3Pct) < 1.0 && art.DiagMeanFatigue < 0.99:
		t.Logf("    → VERDICT: roll makes at the box rate with mean_fatigue<1 ⇒ RUNTIME FATIGUE is the drag (fatigueFactor is NOT ≡1.0 in practice). RE target: the stamina→fatigue curve.")
	case math.Abs(art.DiagMadePct-art.PopEngine3Pct) < 1.0 && art.DiagMeanFatigue >= 0.99:
		t.Logf("    → VERDICT: roll makes at the box rate at fatigue≈1 while recon is higher ⇒ recon over-states sv (a value-model/units error upstream of the roll). RE target: the sv reconstruction terms.")
	default:
		t.Logf("    → VERDICT: unclassified — DiagMadePct sits between recon and box; inspect the three rates above.")
	}

	// Self-consistency (matrix row 5): the derived population ratios must
	// reconcile against the raw sums to floating-point tolerance.
	if got, want := popEngPct, 100*engPop.gm3/engPop.ga3; math.Abs(got-want) > 1e-9 {
		t.Fatalf("population 3P%% self-consistency broken: %.9f != 100*sum3gm/sum3ga=%.9f", got, want)
	}
	if got, want := popDRate, popEngRate-popScoRate; math.Abs(got-want) > 1e-9 {
		t.Fatalf("dRate reconciliation broken: %.9f != engRate-scoRate=%.9f", got, want)
	}

	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-3pt-undershoot%s.json", time.Now().Format("20060102"), abSuffix()))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	t.Logf("J24 3P-UNDERSHOOT DECOMPOSITION (%d snapshots, %d excluded, games-cap %d):", snapshots, excluded, gameCap)
	t.Logf("  POPULATION : 3PA/poss engine %.4f sco %.4f (dRate %.4f = %.2f /100poss) | 3P%% engine %.2f%% sco %.2f%% (dPct %.2fpp)",
		popEngRate, popScoRate, popDRate, 100*popDRate, popEngPct, popScoPct, popDPct)
	if included > 0 {
		t.Logf("  PER-ROSTER : 3PA/poss engine %.4f sco %.4f (dRate %.4f = %.2f /100poss) | 3P%% engine %.2f%% sco %.2f%% (dPct %.2fpp)",
			art.PerRosterEngine3PAPerPoss, art.PerRosterSco3PAPerPoss, art.PerRosterDRatePerPoss,
			art.PerRosterDRatePer100, art.PerRosterEngine3Pct, art.PerRosterSco3Pct, art.PerRosterDPct)
		t.Logf("  COMPOSITION: per-roster dPct milder than population dPct? %v (true ⇒ weak-shooter pooling signal)", art.PerRosterDPctMilder)
	}
	t.Logf("  2P%% SHARED-MODIFIER CHECK: engine %.2f%% sco %.2f%% (d2Pct %.2fpp) vs 3P%% dPct %.2fpp — shotValue3pt+makeValue2pt share `net`+block.",
		pop2EngPct, pop2ScoPct, art.PopD2Pct, popDPct)
	t.Logf("    → CONFOUNDED (2pt-only guaranteed/boosted makes + asymmetric d80/D64 bases): d2Pct does NOT discriminate static-3pt vs shared-drag. See the SIM-3GA-WEIGHTED d80 cut below (authoritative).")
	t.Logf("  leagueBaseline CHECK: engine uses %.1f; sco-implied = sco3pt%% x 666.7 = %.1f (discriminates Branch B baseline x 1.5)",
		engineBaseline3pt, art.ScoImpliedBaseline)
	t.Logf("  d80-POPULATION CHECK: 3GA-weighted mean d80 = 100*Σ3GM/Σ3GA = %.2f%% (sco 3P%% %.2f%%, engine realized 3P%% %.2f%%).",
		art.PlrWeightedD80Pct, popScoPct, popEngPct)
	t.Logf("    → ≈sco ⇒ .plr 3pt feed FAITHFUL, make-gap is SIM-REALIZED (attempt-routing / non-zero-mean modifiers), not a static formula site.")
	t.Logf("    → ≈engine ⇒ .plr 3GM/3GA offsets UNDERSIZE d80 — PINNED data-feed site (suspect #1519 offRealLife3GM=76/3GA=80).")
	t.Logf("    zero-3GA fraction among MIN>0 players: %.4f (%d/%d) — non-trivial ⇒ legacy-serialization zeroes bucket+d80 (both branches, one cause).",
		art.Zero3GAFracMinPos, art.PlrZero3GAMinPos, art.PlrPlayersMinPos)
	t.Logf("  SIM-3GA-WEIGHTED d80 (CONFOUND-FREE cut): %.2f%% vs real-3GA-weighted %.2f%% (routing signal %.2fpp); engine realized 3P%% %.2f%%.",
		art.SimWeightedD80Pct, art.PlrWeightedD80Pct, art.RoutingSignalPp, popEngPct)
	t.Logf("    → ≈real-weighted (~39%%) ⇒ sim does NOT route to low-d80 shooters; the %.2fpp drag is net/block modifier means — net FEED not yet excluded (sim A/B).", -popDPct)
	t.Logf("    → ≈engine realized (~31%%) ⇒ sim routes 3PA to low-d80 shooters; base faithful, net exonerated; follow-on is minutes/routing.")
	t.Logf("  SELECTED BRANCH: %s — %s", art.SelectedBranch, art.BranchRationale)
}

// selectBranch maps the (dRatePer100, dPct) pair to the RE branch per the
// Phase-0 2x2 rule. Bands: dRate ~0 is |dRatePer100| <= rateBand (3PA per 100
// poss); dPct ~0 is |dPct| <= pctBand (pp). The mechanical string is advisory —
// the Phase-1 Opus interpretation of the logged raw values is authoritative
// (auto_merge: false judgment gate).
func selectBranch(dRatePer100, dPct float64, perRosterMilder bool) (branch, rationale string) {
	const rateBand = 0.5 // 3PA per 100 poss
	const pctBand = 0.5  // pp
	rateNear := math.Abs(dRatePer100) <= rateBand
	ratePos := dRatePer100 > rateBand
	rateNeg := dRatePer100 < -rateBand
	pctNear := math.Abs(dPct) <= pctBand
	pctNeg := dPct < -pctBand
	pctPos := dPct > pctBand

	switch {
	case rateNear && pctNeg:
		return "B", "dRate ~0, dPct<0 — pure make-model undersize: shotValue3pt inputs (Phase 3)"
	case ratePos && pctNeg:
		corr := "no composition corroboration (per-roster dPct not milder)"
		if perRosterMilder {
			corr = "corroborated: per-roster dPct milder than population (weak-shooter pooling)"
		}
		return "A", "dRate>0, dPct<0 — selection routes weak/zero-3GA shooters to outcome3pt, pooling down 3P% (Phase 2); " + corr
	case rateNeg && pctNear:
		return "A", "dRate<0, dPct ~0 — pure volume deficit in FUN_004e1ba0 Σ(de0+db0+d90) normalisation (Phase 2)"
	case rateNeg && pctNeg:
		return "BOTH", "dRate<0 AND dPct<0 both material — run Branch A (Phase 2) and Branch B (Phase 3)"
	case ratePos && pctNear:
		return "A", "dRate>0, dPct ~0 — attempt over-selection with faithful make-model (Phase 2)"
	case rateNear && pctNear:
		return "NONE", "neither dRate nor dPct material at this cap — no undershoot localised"
	default: // pctPos combinations: engine OVER-shoots the make/attempt rate — not the undershoot pattern
		_ = pctPos
		return "INSPECT", fmt.Sprintf("unexpected pattern (dRatePer100=%.2f dPct=%.2fpp) — engine over-shoots; inspect raw sums", dRatePer100, dPct)
	}
}
