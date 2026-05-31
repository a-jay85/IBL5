package sim

// netAdvantage is the core probability driver for a 2-point attempt:
//
//	net = offense_rating − position_penalty − defense_rating
//
// offense/defense are the ODPT pair for the chosen play type (outside → OO/OD,
// drive → DO/DD, post → PO/PD). The shot-clock modifier subtracts 4.0 (a
// rushed, end-of-clock look); the regular modifier is ×1.0. The playoff ×1.25
// and ASG modes are deferred to PR7. Net may go negative — that is meaningful
// (a bad matchup) and must not underflow.
func netAdvantage(pt playType, handler, defender onCourt, penalty float64, shotClock bool) float64 {
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
	net *= 1.0 // regular-game modifier; playoff ×1.25 deferred to PR7
	return net
}
