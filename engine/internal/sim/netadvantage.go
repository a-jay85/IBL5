package sim

import "github.com/a-jay85/IBL5/engine/internal/bundle"

// netAdvantage is the core probability driver for a 2-point attempt:
//
//	net = offense_rating − position_penalty − defense_rating
//
// offense/defense are the ODPT pair for the chosen play type (outside → OO/OD,
// drive → DO/DD, post → PO/PD). The shot-clock modifier subtracts 4.0 (a
// rushed, end-of-clock look). Playoff games (gt == GameTypePlayoff) amplify the
// net advantage ×1.25 (00_MASTER_REFERENCE.md L1022-1027); all other game types
// ×1.0. Net may go negative — that is meaningful (a bad matchup) and must not
// underflow; the playoff multiplier is applied to the signed net (it amplifies
// both good and bad matchups, as in the decompile).
func netAdvantage(pt playType, handler, defender onCourt, penalty float64, shotClock bool, gt bundle.GameType) float64 {
	var off, def float64
	switch pt {
	case playOutside:
		off, def = floor1(handler.OO), floor1(defender.OD)
	case playDrive:
		off, def = floor1(handler.DriveOff), floor1(defender.DD)
	case playPost:
		off, def = floor1(handler.PO), floor1(defender.PD)
	}

	net := off - penalty - def
	if shotClock {
		net -= 4.0
	}
	if isPlayoff(gt) {
		net *= playoffNetMultiplier
	}
	return net
}
