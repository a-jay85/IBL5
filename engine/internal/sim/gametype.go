package sim

import "github.com/a-jay85/IBL5/engine/internal/bundle"

// Game-type-gated effects (JSB CEngine+0x63b4). PR7 implements the two in-engine,
// per-game effects gated on the PLAYOFF type; all other gated behaviors
// (season-record/stat/career/confidence/conditioning) are between-game
// persistence concerns handled outside this pure transform. ASG (5/6) has no
// in-engine effect here — its only JSB-gated per-game effect is zeroing the
// home-court-advantage magnitude, and HCA is not modeled (deferred; see
// docs/decisions and the RE notes). Coaching is already neutral.
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
)

// isPlayoff reports whether the game type is a playoff game (the only type that
// triggers the net×1.25 amplifier and the fast-break special_sub).
func isPlayoff(gt bundle.GameType) bool { return gt == bundle.GameTypePlayoff }
