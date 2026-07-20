package sim

import "github.com/a-jay85/IBL5/engine/internal/result"

// EndingMixCounts is one accumulation bucket of per-possession endings and
// event tallies (both teams pooled, like the J3 corpus numbers).
type EndingMixCounts struct {
	Games       int `json:"games"`
	Possessions int `json:"possessions"`

	// Possession endings (each possession classified exactly once).
	EndSteal   int `json:"end_steal"`    // EventSteal present (steal IS the turnover)
	EndTOInd   int `json:"end_to_ind"`   // EventTurnover without a steal (independent check)
	EndDReb    int `json:"end_dreb"`     // defensive rebound (always trip-terminal)
	EndMade    int `json:"end_made"`     // made FG, no FT sequence
	EndFT      int `json:"end_ft"`       // FT sequence last (and-one or foul-only; engine FTs never rebound)
	EndOther   int `json:"end_other"`    // OREB-cap exhaustion / empty possession
	ORebCont   int `json:"oreb_cont"`    // offensive-rebound CONTINUATIONS (not endings)
	AndOneSeqs int `json:"and_one_seqs"` // and-one FT sequences (FTAttempts==1) inside EndFT

	// Event tallies for the sub-model rate decomposition.
	FGA    int `json:"fga"` // all shot attempts (2pt+3pt, all origins)
	FGM    int `json:"fgm"`
	FGA3   int `json:"fga3"`
	FGM3   int `json:"fgm3"`
	OReb   int `json:"oreb"`
	DReb   int `json:"dreb"`
	Steals int `json:"steals"`
	TOs    int `json:"turnovers"` // all EventTurnover (steal-driven + independent)
	FTA    int `json:"fta"`
	FTM    int `json:"ftm"`
	Fouls  int `json:"fouls"`
}

// ClassifyPossession tallies one possession's events into c. Priority mirrors
// the trip structure: a steal is the dominant turnover and ends the trip; an
// independent EventTurnover (no steal) likewise; a defensive rebound is always
// trip-terminal; otherwise the possession ended on a make or an FT sequence.
func ClassifyPossession(evs []result.Event, c *EndingMixCounts) {
	if len(evs) == 0 {
		return
	}
	c.Possessions++
	var hasSteal, hasTO, hasDReb, hasMake, hasFT bool
	var lastFTAttempts int
	for _, e := range evs {
		switch e.Kind {
		case result.EventShotAttempt:
			c.FGA++
			if e.ShotType == result.ShotThree {
				c.FGA3++
			}
		case result.EventShotMake:
			c.FGM++
			hasMake = true
			if e.ShotType == result.ShotThree {
				c.FGM3++
			}
		case result.EventRebound:
			if e.OffensiveRebound {
				c.OReb++
				c.ORebCont++
			} else {
				c.DReb++
				hasDReb = true
			}
		case result.EventSteal:
			c.Steals++
			hasSteal = true
		case result.EventTurnover:
			c.TOs++
			hasTO = true
		case result.EventFreeThrow:
			c.FTA += e.FTAttempts
			c.FTM += e.FTMade
			hasFT = true
			lastFTAttempts = e.FTAttempts
		case result.EventFoul:
			c.Fouls++
		}
	}
	switch {
	case hasSteal:
		c.EndSteal++
	case hasTO:
		c.EndTOInd++
	case hasDReb:
		c.EndDReb++
	case hasFT:
		c.EndFT++
		if lastFTAttempts == 1 {
			c.AndOneSeqs++
		}
	case hasMake:
		c.EndMade++
	default:
		c.EndOther++
	}
}
