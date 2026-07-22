//go:build archive

// TestRealArchive_ThreePtLocalize localizes the J24 residual-(7) 3PA/min ATTEMPT-RATE
// gap (rate effect B = −0.0582, adjudicated by TestRealArchive_ThreePtAttemptRouting)
// across the four genuinely-open candidate carriers:
//
//	(a) feed defect        — the fed RealLife3GA/RealLifeMIN understate real 3pt volume
//	                         BEFORE the engine runs (stand-in RealLifeMIN==0 players, or a
//	                         sim minute distribution weighted toward starved feeds).
//	(b) denominator dilution — eligible shot-decisions pick 3pt less often than real, because
//	                         the 3pt bucket weight is diluted by the 2pt/foul/andOne composite.
//	(c) gate suppression   — too large a fraction of shot-decisions are forced 3pt-INELIGIBLE
//	                         (transition fast breaks + half-court OReb continuations).
//	(d) unmeasured divergence — the partition does not close; a selectOutcome/+0xDB0 mismatch
//	                         the prior RE missed.
//
// It consumes the Phase-1 sim.OutcomeDiagAccum side-channel (accumulation-only, proven
// byte-identical by TestOutcomeDiagAccum_NonPerturbationAndReachability, so DRBPushSharePct
// — gate-1 12.37%, ADR-0090 — cannot move as a side effect of measuring).
//
// CLOSURE MODEL. Unlike the sibling K-O decomposition (self-closing by construction), the
// four-way partition closes only via an EXPLICIT multiplicative model of the sim rate:
//
//	simPArate ≈ decPerMin × P(eligible) × E[3ptShare | eligible]
//
// Reference ("what real implies") point: eligibility 1−RealFBShareRef and 3pt share
// realShareFGA. Walking the reference point to the measured point in two exact steps gives
// the gate and denominator deltas; the feed delta is measured independently in the same
// 3PA/min units; and the REMAINDER is the residual:
//
//	TotalGap = FeedDeltaA + GateDeltaCExcess + DenomDeltaB + ClosureResidualD
//
// ResidualFracOfGap = |D|/|TotalGap| is therefore the conclusive-vs-inconclusive SWITCH:
// without the explicit model, "name the carrier" would be a subjective read; with it the
// verdict (NamedCarrier) is a computed threshold. ADR-0090 forbids tuning any constant
// toward a target — this instrument only MEASURES; the fix is a sequenced follow-on.
//
// Reuses listArchiveZips, seasonName, isOlympicsPath, loadTripleWithSco, possEnvInt,
// recentEra05to08 — same package calibrate, same build tag.
// Do NOT redefine them: duplicate-symbol error under -tags archive.
//
// Invoke manually:
//
//	cd engine && JSB_ARCHIVE_DIR=/Users/ajaynicolas/GitHub/IBL5/ibl5/backups \
//	  go test -tags archive ./internal/calibrate \
//	  -run TestRealArchive_ThreePtLocalize -v -timeout 1800s
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

// realFBShareRef is the real-basketball fast-break share of possessions (~9%), the
// reference eligibility loss. A sim SuppressionFrac at this level is FAITHFUL; only the
// EXCESS above it is attributable to candidate (c). Reference constant for measurement
// only — nothing is tuned toward it (ADR-0090).
const realFBShareRef = 0.09

// Verdict thresholds (Phase 3 closed decision rule). residualCloses gates any
// carrier verdict at all; carrierDominance is the share of |TotalGap| a single measured
// carrier must own to be NAMED rather than merely non-zero.
const (
	residualCloses   = 0.15
	carrierDominance = 0.60
	feedUnderstated  = 0.95 // bundleImplied < 0.95×real ⇒ fed rate understated >5%
	standInMinShare  = 0.05 // RealMinZero decision share above which the stand-in path matters
	standInDepressed = 0.80 // stand-in 3pt share below 80% of realShareFGA ⇒ >20% depressed
)

type threePtLocalizeArtifact struct {
	Generated      string `json:"generated"`
	Snapshots      int    `json:"snapshots"`
	GamesCap       int    `json:"games_cap"`
	Seed           uint64 `json:"seed"`
	ValidSnapshots int    `json:"valid_snapshots"`

	SimPAPerMin  float64 `json:"sim_pa_per_min"`
	RealPAPerMin float64 `json:"real_pa_per_min"`
	TotalGap     float64 `json:"total_gap"`

	// (a) feed audit
	PctRealMinZeroDecisions float64 `json:"pct_real_min_zero_decisions"`
	BundleImplied3PArate    float64 `json:"bundle_implied_3pa_rate"`
	FeedDeltaA              float64 `json:"feed_delta_a"`

	// (b) denominator audit
	MeanThreeShare2    float64 `json:"mean_three_share_2"`
	MeanThreeShareFull float64 `json:"mean_three_share_full"`
	RealShareFGA       float64 `json:"real_share_fga"`
	DenomDeltaB        float64 `json:"denom_delta_b"`

	// (c) gate/eligibility audit
	SuppressionFrac  float64 `json:"suppression_frac"`
	TransitionFrac   float64 `json:"transition_frac"`
	RealFBShareRef   float64 `json:"real_fb_share_ref"`
	GateDeltaCRaw    float64 `json:"gate_delta_c_raw"`
	GateDeltaCExcess float64 `json:"gate_delta_c_excess"`

	// (d) closure
	ReconstructedSimPAPerMin float64 `json:"reconstructed_sim_pa_per_min"`
	ClosureResidualD         float64 `json:"closure_residual_d"`
	ResidualFracOfGap        float64 `json:"residual_frac_of_gap"`

	NamedCarrier string `json:"named_carrier"`
}

func TestRealArchive_ThreePtLocalize(t *testing.T) {
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

	// Per-snapshot audit values, summed then averaged (mirrors the sibling's sum…/n).
	var (
		sumSimPA, sumRealPA, sumTotalGap                    float64
		sumPctRealMinZero, sumBundleImplied, sumFeedA       float64
		sumShare2, sumShareFull, sumRealShareFGA, sumDenomB float64
		sumSuppFrac, sumTransFrac, sumGateRaw, sumGateExc   float64
		sumRecon, sumResidD                                 float64
		snapshots, validSnapshots                           int
	)

	for si := 0; si < len(seasonZips); si += snapStride {
		zp := seasonZips[si]
		b, _, cleanup, skip := loadTripleWithSco(zp)
		if skip != nil {
			t.Logf("skip %s: %s", filepath.Base(zp), skip.Reason)
			continue
		}
		snapshots++
		base := filepath.Base(zp)

		// ONE shared accumulator per snapshot (owner-shared-instance pattern), so the
		// decision-level audits aggregate across every capped game of this season.
		od := &sim.OutcomeDiagAccum{}

		// Real-side per-player feed, for the (a) audit's minute-weighted implied rate.
		realMINByPID := make(map[int]float64, len(b.Players))
		real3GAByPID := make(map[int]float64, len(b.Players))
		var sumRealMIN, sumReal3GA, sumRealFGA float64
		for _, p := range b.Players {
			realMINByPID[p.PID] = float64(p.RealLifeMIN)
			real3GAByPID[p.PID] = float64(p.RealLife3GA)
			sumRealMIN += float64(p.RealLifeMIN)
			sumReal3GA += float64(p.RealLife3GA)
			sumRealFGA += float64(p.RealLifeFGA)
		}

		simMINByPID := make(map[int]float64)
		var totalSimMIN, totalSim3GA float64
		for gi, g := range b.Schedule {
			if gi >= gameCap {
				break
			}
			sub := bundle.Bundle{LeagueID: b.LeagueID, Teams: b.Teams, Players: b.Players, Schedule: []bundle.Game{g}}
			// HARNESS HAZARD — seed MUST vary per game. A constant seed over single-game
			// bundles restarts the PCG stream from state 0 every game, so the run only ever
			// draws overlapping *prefixes* of one fixed sequence — that prefix's bias is
			// amplified rather than averaged away (the contamination fixed in #1576/#1582,
			// worth a 9.1pp spurious residual). SimulateWith (NOT Simulate) is required:
			// Simulate passes a nil Options and reaches no OutcomeDiag hook.
			res, err := sim.SimulateWith(sub, seed+uint64(gi), sim.Options{OutcomeDiag: od})
			if err != nil {
				t.Fatalf("SimulateWith %s game %d: %v", base, gi, err)
			}
			for _, pb := range res.Games[0].PlayerBoxes {
				simMINByPID[pb.PID] += float64(pb.GameMIN)
				totalSimMIN += float64(pb.GameMIN)
				totalSim3GA += float64(pb.Game3GA)
			}
		}
		cleanup()

		// Reachability at the corpus level (Phase 2's richBundle deliberately left this here:
		// a short test game may fire zero transitions, but a 60-game season cannot).
		if od.ShotDecisions == 0 {
			t.Fatalf("%s: OutcomeDiag.ShotDecisions == 0 — accumulator never reached (Phase-1 hook regression)", base)
		}
		if od.Eligible3pt == 0 {
			t.Fatalf("%s: OutcomeDiag.Eligible3pt == 0 — half-court hook recorded no eligible decision", base)
		}
		if od.ShotDecisions != od.Eligible3pt+od.Suppressed {
			t.Fatalf("%s: partition broken: ShotDecisions=%d != Eligible3pt=%d + Suppressed=%d",
				base, od.ShotDecisions, od.Eligible3pt, od.Suppressed)
		}

		// Divide-by-zero guards — skip the snapshot with a log, NEVER emit NaN into the artifact.
		if totalSimMIN <= 0 || sumRealMIN <= 0 || sumRealFGA <= 0 {
			t.Logf("skip %s (degenerate denominators): simMIN=%.1f realMIN=%.1f realFGA=%.1f",
				base, totalSimMIN, sumRealMIN, sumRealFGA)
			continue
		}

		simPArate := totalSim3GA / totalSimMIN
		realPArate := sumReal3GA / sumRealMIN
		realShareFGA := sumReal3GA / sumRealFGA
		totalGap := simPArate - realPArate
		if totalGap == 0 {
			t.Logf("skip %s: TotalGap is exactly 0 — no rate effect to decompose", base)
			continue
		}

		decPerMin := float64(od.ShotDecisions) / totalSimMIN
		nElig := float64(od.Eligible3pt)
		nDec := float64(od.ShotDecisions)
		eligFrac := nElig / nDec
		suppFrac := float64(od.Suppressed) / nDec
		transFrac := float64(od.Transition) / nDec
		pctRealMinZero := float64(od.RealMinZero) / nDec
		share2 := od.SumThreeShare2 / nElig
		shareFull := od.SumThreeShareFull / nElig

		// (a) FEED. bundleImplied3PArate is the fed 3PA/min the engine actually EXPERIENCES:
		// each player's own fed rate (RealLife3GA/RealLifeMIN, and 0 on the RealLifeMIN==0
		// stand-in path — precisely where the feed starves), weighted by the SIM minutes that
		// player received. Contrast realPArate, the raw league aggregate. A gap between them
		// means the numerator is starved before any bucket weight is computed.
		var fedNum float64
		for pid, sm := range simMINByPID {
			rm := realMINByPID[pid]
			if rm <= 0 {
				continue // stand-in path: contributes sim minutes but no fed 3pt volume
			}
			fedNum += sm * (real3GAByPID[pid] / rm)
		}
		bundleImplied := fedNum / totalSimMIN
		feedDeltaA := bundleImplied - realPArate

		// (c) GATE. Walk eligibility from the reference (1−realFBShareRef) to the measured
		// eligFrac, holding the 3pt share at the REFERENCE value so this step is the pure
		// eligibility effect. Raw = removing ALL suppression (upper bound); Excess = only the
		// part above the faithful real fast-break share, which is what candidate (c) claims.
		gateDeltaCRaw := decPerMin * (eligFrac - 1.0) * realShareFGA
		gateDeltaCExcess := decPerMin * (eligFrac - (1.0 - realFBShareRef)) * realShareFGA

		// (b) DENOMINATOR. Walk the 3pt share from the reference realShareFGA to the measured
		// shareFull, holding eligibility at the MEASURED value. The two steps are exact and
		// sum to (decPerMin·eligFrac·shareFull − decPerMin·(1−ref)·realShareFGA) by construction.
		denomDeltaB := decPerMin * eligFrac * (shareFull - realShareFGA)

		// Model fit: does the multiplicative reconstruction recover the measured sim rate?
		reconstructed := decPerMin * eligFrac * shareFull
		if simPArate > 0 && math.Abs(reconstructed-simPArate)/simPArate > 0.05 {
			t.Logf("WARNING %s: reconstruction %.6f is >5%% from measured simPArate %.6f — "+
				"the multiplicative closure model does not fit this snapshot; attribution is model-limited",
				base, reconstructed, simPArate)
		}

		// (d) RESIDUAL. Whatever the three measured carriers do NOT account for.
		residD := totalGap - (feedDeltaA + gateDeltaCExcess + denomDeltaB)

		sumSimPA += simPArate
		sumRealPA += realPArate
		sumTotalGap += totalGap
		sumPctRealMinZero += pctRealMinZero
		sumBundleImplied += bundleImplied
		sumFeedA += feedDeltaA
		sumShare2 += share2
		sumShareFull += shareFull
		sumRealShareFGA += realShareFGA
		sumDenomB += denomDeltaB
		sumSuppFrac += suppFrac
		sumTransFrac += transFrac
		sumGateRaw += gateDeltaCRaw
		sumGateExc += gateDeltaCExcess
		sumRecon += reconstructed
		sumResidD += residD
		validSnapshots++

		t.Logf("  %s: simPA/min %.6f real %.6f gap %.6f | supp %.4f (trans %.4f) share3full %.4f realFGAshare %.4f | A %.6f B %.6f C %.6f D %.6f",
			base, simPArate, realPArate, totalGap, suppFrac, transFrac, shareFull, realShareFGA,
			feedDeltaA, denomDeltaB, gateDeltaCExcess, residD)
	}

	if validSnapshots == 0 {
		t.Fatal("no recent-era snapshots aggregated — corpus empty (check JSB_ARCHIVE_DIR / season dirs)")
	}

	n := float64(validSnapshots)
	art := threePtLocalizeArtifact{
		Generated:      time.Now().Format(time.RFC3339),
		Snapshots:      snapshots,
		GamesCap:       gameCap,
		Seed:           seed,
		ValidSnapshots: validSnapshots,

		SimPAPerMin:  sumSimPA / n,
		RealPAPerMin: sumRealPA / n,
		TotalGap:     sumTotalGap / n,

		PctRealMinZeroDecisions: sumPctRealMinZero / n,
		BundleImplied3PArate:    sumBundleImplied / n,
		FeedDeltaA:              sumFeedA / n,

		MeanThreeShare2:    sumShare2 / n,
		MeanThreeShareFull: sumShareFull / n,
		RealShareFGA:       sumRealShareFGA / n,
		DenomDeltaB:        sumDenomB / n,

		SuppressionFrac:  sumSuppFrac / n,
		TransitionFrac:   sumTransFrac / n,
		RealFBShareRef:   realFBShareRef,
		GateDeltaCRaw:    sumGateRaw / n,
		GateDeltaCExcess: sumGateExc / n,

		ReconstructedSimPAPerMin: sumRecon / n,
		ClosureResidualD:         sumResidD / n,
	}
	if art.TotalGap != 0 {
		art.ResidualFracOfGap = math.Abs(art.ClosureResidualD) / math.Abs(art.TotalGap)
	}
	art.NamedCarrier = nameCarrier(art)

	out := filepath.Join("..", "validate", "testdata",
		fmt.Sprintf("calibration-5.60-%s-3pt-localize.json", time.Now().Format("20060102")))
	blob, err := json.MarshalIndent(art, "", "  ")
	if err != nil {
		t.Fatalf("marshal artifact: %v", err)
	}
	if err := os.WriteFile(out, append(blob, '\n'), 0o644); err != nil {
		t.Fatalf("write artifact %q: %v", out, err)
	}
	t.Logf("wrote %s", out)

	t.Logf("3PT ATTEMPT-RATE LOCALIZATION (%d/%d snapshots valid, games-cap %d):",
		art.ValidSnapshots, art.Snapshots, gameCap)
	t.Logf("  sim 3PA/min %.6f  real 3PA/min %.6f  TOTAL GAP %.6f", art.SimPAPerMin, art.RealPAPerMin, art.TotalGap)
	t.Logf("  (a) feed:        bundle-implied %.6f vs real %.6f  RealMinZero-decisions %.2f%%  FeedDeltaA %.6f",
		art.BundleImplied3PArate, art.RealPAPerMin, 100*art.PctRealMinZeroDecisions, art.FeedDeltaA)
	t.Logf("  (b) denominator: share3(2-bucket) %.4f  share3(full) %.4f  realShareFGA %.4f  DenomDeltaB %.6f",
		art.MeanThreeShare2, art.MeanThreeShareFull, art.RealShareFGA, art.DenomDeltaB)
	t.Logf("  (c) gate:        SuppressionFrac %.4f (Transition %.4f) vs realFBRef %.2f  GateDeltaCExcess %.6f (raw %.6f)",
		art.SuppressionFrac, art.TransitionFrac, art.RealFBShareRef, art.GateDeltaCExcess, art.GateDeltaCRaw)
	t.Logf("  (d) closure:     reconstructed %.6f  ClosureResidualD %.6f  ResidualFracOfGap %.4f",
		art.ReconstructedSimPAPerMin, art.ClosureResidualD, art.ResidualFracOfGap)
	t.Logf("  NAMED CARRIER: %s", art.NamedCarrier)
}

// nameCarrier applies the closed Phase-3 decision rule to the averaged artifact. The
// verdict is a computed threshold, never a subjective read — it is the single input the
// follow-on plan branches on.
func nameCarrier(a threePtLocalizeArtifact) string {
	// (a) feed first: a starved numerator makes every downstream share audit moot.
	// The second disjunct uses MeanThreeShareFull as the stand-in-share proxy — the
	// per-decision capture does not split the 3pt share by RealMinZero, so a materially
	// depressed OVERALL share alongside a material stand-in decision share is the
	// available evidence for the stand-in path. Noted as a proxy, not an exact measure.
	if a.BundleImplied3PArate < feedUnderstated*a.RealPAPerMin {
		return "feed_a"
	}
	if a.PctRealMinZeroDecisions > standInMinShare && a.MeanThreeShareFull < standInDepressed*a.RealShareFGA {
		return "feed_a"
	}
	// A carrier can only be NAMED if the partition actually closed.
	if a.ResidualFracOfGap <= residualCloses {
		gap := math.Abs(a.TotalGap)
		if math.Abs(a.GateDeltaCExcess) >= carrierDominance*gap {
			return "gate_c"
		}
		if math.Abs(a.DenomDeltaB) >= carrierDominance*gap {
			return "denominator_b"
		}
	}
	return "inconclusive_d"
}
