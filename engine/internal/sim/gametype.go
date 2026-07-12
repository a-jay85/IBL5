package sim

import "github.com/a-jay85/IBL5/engine/internal/bundle"

// Game-type-gated effects (JSB CEngine+0x63b4). PR7 implements the two in-engine,
// per-game effects gated on the PLAYOFF type; all other gated behaviors
// (season-record/stat/career/confidence/conditioning) are between-game
// persistence concerns handled outside this pure transform. ASG (5/6) zeroes the
// home-court-advantage magnitude (its only in-engine per-game effect — see
// hcaDelta below). Coaching is already neutral.
const (
	// playoffNetMultiplier amplifies the 2pt half-court net advantage in playoff
	// games (00_MASTER_REFERENCE.md L1022-1027: net × 1.25 when game_type==4,
	// else ×1.0). Applies ONLY to the half-court net (netAdvantage) — NOT the
	// transition net (fixed 5.0−TD) nor the 3pt path (league-baseline×1.5).
	playoffNetMultiplier = 1.25

	// playoffFastBreakSub is the playoff "special_sub" subtracted from the
	// fast-break trigger threshold (00_MASTER_REFERENCE.md L880-896:
	// triggers = roll ≤ TransOff + coaching_mod − special_sub; special_sub=1 iff
	// game_type==4, confirmed at possession_handler_RAW.c:185). coaching_mod is 0
	// (neutral coaching), so the playoff trigger is roll ≤ TransOff − 1 — fast
	// breaks fire slightly less often in the playoffs.
	playoffFastBreakSub = 1

	// hcaMagnitude is the home-court-advantage delta (CEngine+0x18B58 = 0.2,
	// 00_MASTER_REFERENCE.md L657 "Home Court Advantage": set in the engine
	// constructor FUN_004cee00). It is applied at the three HCA sites as
	// (team×2−3)×0.2 → +0.2 for the home team, −0.2 for the away team. ASG init
	// overwrites the magnitude to 0.0 (L669) — HCA is neutralized for all-star
	// games. The two HCA sites this engine models (the play-outcome made-shot/
	// foul buckets at L97159 and the offensive-quality divisor at L98300) are
	// wired in possession.go / transition.go / bucketweights.go.
	hcaMagnitude = 0.2

	// hcaSite2BasisScale converts the raw ±0.2 HCA delta (5.60 native e88 units,
	// where the made-shot bucket is O(1)) onto the engine's twoPtBucketWeight basis
	// — the admitted VALIDATION-PHASE stand-in composite at O(10s) (≈16.5 under
	// default ratings; bucketweights.go). Raw 0.2 on that basis is ~1.2%, but the
	// faithful proportional made-bucket effect (J16 e88 += s·0.2, where e88 is O(1))
	// is ~10%; scaling by this factor preserves the proportion across the basis
	// change. It is the SINGLE margin dial — tuned on the archive harness (1-D
	// search) so the engine's gt=2 home margin matches the PAIRED .sco value measured
	// on the SAME games (2.85 → engine 3.332 vs .sco 3.319, gap +0.014). The target is
	// that paired .sco margin (≈3.32), NOT the pooled-corpus 4.12 — which has no
	// runnable instrument here; see the J15 program's paired-comparator note.
	//
	// It applies ONLY to the SCALED legs — the site-2 made-shot bucket (leg A) and
	// the and-one channel (leg D, e90 = param_6·0.25 + e88, which inherits e88's
	// +hca). The FOUL legs use the RAW ±0.2: the foul base (leg B, e80 −= s·hca) and
	// the offensive-quality divisor (leg C, offQ −= s·hca per player) are built on
	// the faithful CEngine per-48 basis (leagueTOV48 = 3.353143 IS the 5.60 value,
	// teamquality.go), so the decompile's raw 0.2 is already in-basis there — scaling
	// them by ~2.85× would over-apply HCA and drive the foul ratio absurd. Half-court
	// only (decompile param_5==1); the transition path (param_5==0) is fully symmetric
	// and receives 0 for both legs.
	hcaSite2BasisScale = 2.85
)

// isPlayoff reports whether the game type is a playoff game (the only type that
// triggers the net×1.25 amplifier and the fast-break special_sub).
func isPlayoff(gt bundle.GameType) bool { return gt == bundle.GameTypePlayoff }

// isASG reports whether the game type is an all-star game (5/6). ASG zeroes the
// home-court-advantage magnitude (00_MASTER_REFERENCE.md L669), so isASG gates
// hcaDelta to 0 and the symmetric (no-HCA) path is restored.
func isASG(gt bundle.GameType) bool {
	return gt == bundle.GameTypeAllStarA || gt == bundle.GameTypeAllStarB
}

// hcaDelta is the per-team home-court-advantage delta for the given game type:
// +hcaMagnitude for the home team, −hcaMagnitude for the away team, and 0 for
// either team in an ASG (where the magnitude is zeroed). It feeds all four modeled
// HCA legs at the half-court play-outcome selector (decompile :97157-97164,
// param_5==1; 00_MASTER_REFERENCE.md L657-712 site table):
//   - leg A (site-2 e88): the made-shot bucket gains +delta·hcaSite2BasisScale (PRO-home scoring).
//   - leg D (e90 and-one): inherits e88's +delta·hcaSite2BasisScale.
//   - leg B (site-2 e80): the foul BASE loses raw delta before the factor (ANTI-home).
//   - leg C (site-3 offQ): each offensive player's offQuality term loses raw delta,
//     shrinking the foul divisor for the home team so the coupling factor moves off 1
//     toward sign(defQ−baseline) — pro-home only for a strong-steal defense (defQ >
//     baseline), anti-home otherwise.
//
// Net foul effect: leg B (±delta on the ~3.35 base, ~6%) DOMINATES leg C (±delta on
// the ~1.09 factor, sub-1%) by ~an order of magnitude (measured ≈9.5×, the defQ cap
// keeps leg C bounded), so the foul bucket nets ANTI-home (home draws slightly FEWER
// fouls, ratio ~0.91) regardless of leg C's sign. This is NOT a bug — the home MARGIN
// is a SCORING phenomenon carried by legs A/D, not a foul-drawing one; the real .sco
// pro-home FTA split is an emergent (home-lead-driven) effect this per-possession
// bucket does not model. The transition path (param_5==0) is fully symmetric, receives 0.
func hcaDelta(gt bundle.GameType, isHome bool) float64 {
	if isASG(gt) {
		return 0
	}
	if isHome {
		return hcaMagnitude
	}
	return -hcaMagnitude
}
