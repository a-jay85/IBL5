package sim

import "github.com/a-jay85/IBL5/engine/internal/rng"

// typeDefense returns the defender's defensive rating for a play type
// (outside → OD, drive → DD, post → PD), floored at 1.
func typeDefense(p onCourt, pt playType) float64 {
	switch pt {
	case playOutside:
		return floor1(p.OD)
	case playDrive:
		return floor1(p.DD)
	case playPost:
		return floor1(p.PD)
	}
	return floor1(p.OD)
}

// selectDefender picks the contesting defender by weighted random over
// (2.0 − fatigue) × def_rating. Tired defenders carry a larger (2−fatigue)
// term, so offense is steered toward them; in PR3a fatigue ≈ 1.0, so the pick
// is driven by the type-specific defensive rating. The chosen defender supplies
// the net-advantage defense rating and is charged any foul on the possession.
func selectDefender(d *teamState, pt playType, r *rng.RNG) onCourt {
	weights := make([]float64, len(d.players))
	var sum float64
	for i, p := range d.players {
		w := (2.0 - p.fatigue) * typeDefense(p, pt)
		if w < 0 {
			w = 0
		}
		weights[i] = w
		sum += w
	}
	if sum <= 0 || len(d.players) == 0 {
		return d.players[0]
	}
	roll := r.Float64() * sum
	var acc float64
	for i, w := range weights {
		acc += w
		if roll <= acc {
			return d.players[i]
		}
	}
	return d.players[len(d.players)-1]
}

// orebProbability is P(offensive rebound) = off/(off+def) × 0.5 + 0.25, floored
// at .25 and capped at .75. Equal strengths give .50. Equal-zero strengths
// (an all-unrated roster) are guarded to .50 with no divide-by-zero.
func orebProbability(off, def float64) float64 {
	denom := off + def
	ratio := 0.5
	if denom > 0 {
		ratio = off / denom
	}
	p := ratio*0.5 + 0.25
	if p < 0.25 {
		p = 0.25
	}
	if p > 0.75 {
		p = 0.75
	}
	return p
}

// teamReboundStrength sums a team's rebound rating × fatigue across its
// starters, using offensive-rebound ratings when crashing the offensive glass
// and defensive-rebound ratings otherwise.
func teamReboundStrength(t *teamState, offensive bool) float64 {
	var sum float64
	for _, p := range t.players {
		rating := floor1(p.DRB)
		if offensive {
			rating = floor1(p.ORB)
		}
		sum += rating * p.fatigue
	}
	return sum
}

// selectRebounder picks which player on the rebounding team is credited, by
// weighted random over reb_rating × fatigue (ORB on an offensive board, DRB on
// a defensive one).
func selectRebounder(t *teamState, offensive bool, r *rng.RNG) onCourt {
	weights := make([]float64, len(t.players))
	var sum float64
	for i, p := range t.players {
		rating := floor1(p.DRB)
		if offensive {
			rating = floor1(p.ORB)
		}
		w := rating * p.fatigue
		weights[i] = w
		sum += w
	}
	if sum <= 0 || len(t.players) == 0 {
		return t.players[0]
	}
	roll := r.Float64() * sum
	var acc float64
	for i, w := range weights {
		acc += w
		if roll <= acc {
			return t.players[i]
		}
	}
	return t.players[len(t.players)-1]
}
