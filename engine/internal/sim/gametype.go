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
// either team in an ASG (where the magnitude is zeroed). It feeds the two modeled
// HCA sites: site 2 (the made-shot bucket gains +delta, the foul bucket loses
// delta) and site 3 (each offensive player's offQuality term is reduced by delta,
// shrinking the foul-bucket divisor for the home team — the dominant, home-
// favorable mechanism; 00_MASTER_REFERENCE.md L661 site table, COMPOSITE_DOUBLES_
// TRACE.md §RESOLUTION).
func hcaDelta(gt bundle.GameType, isHome bool) float64 {
	if isASG(gt) {
		return 0
	}
	if isHome {
		return hcaMagnitude
	}
	return -hcaMagnitude
}
