package sim

import (
	"math"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

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

// gate1Probability is the "which team wins the board" gate that JSB 5.60 applies
// AHEAD of the linear retention roll (orebProbability = gate-2) — the L1 carrier
// ADR-0057 positively identified. Since ADR-0058 it is the LIVE offensive-rebound
// continuation roll (gs.orebProb resolves the single determination against it by
// default); the old linear gate-2 path survives only behind the UnfaithfulOreb hatch.
//
// Faithful port of FUN_004e22a0 (jsb560_decompiled.c:97352-97405), the sqrt
// diminishing-returns team-strength pick:
//
//	off ← min(off, def)                                  // off≤def cap (97393-97395)
//	share = off/(off+def) × 100                          // offensive board share, 0..100
//	adv   = (share − baseline) × 0.5                     // centered advantage (97396)
//	value = adv ≤ 0 ? (adv + baseline) − √|adv|          // sqrt diminishing returns
//	              : √|adv| + adv + baseline              // (97397-97402)
//	P(offense wins board) = clamp(value / 100, 0, 1)     // vs rand(0,100) (97403-97405)
//
// baseline is the league offensive-rebound SHARE × 100, from leagueReboundBaseline
// (the loader's +0x6818/+0x6848 league ORB/DRB baselines; master-ref 186-187). It is
// ADDITIVE-CENTERING, not a multiplicative neutral constant — it sets the sign
// threshold of adv (which sqrt branch fires) and shifts P by ~0.005 per baseline
// point — so a wrong baseline biases the discriminator, hence it is computed from the
// bundle's ratings (not hardcoded) and swept in the archive instrument. The exact
// runtime value is loader-populated and unpinned in the static decompile; an x32dbg
// breakpoint on FUN_004e22a0 dumping local_c/local_14/the roll is the fallback.
func gate1Probability(off, def, baseline float64) float64 {
	if def < off { // off≤def cap (decompile: if local_14 < local_c { local_c = local_14 })
		off = def
	}
	denom := off + def
	share := 0.5
	if denom > 0 {
		share = off / denom
	}
	adv := (share*100 - baseline) * 0.5
	var value float64
	if adv <= 0 {
		value = (adv + baseline) - math.Sqrt(math.Abs(adv))
	} else {
		value = math.Sqrt(math.Abs(adv)) + adv + baseline
	}
	p := value / 100
	if p < 0 {
		p = 0
	}
	if p > 1 {
		p = 1
	}
	return p
}

// leagueReboundBaseline is gate-1's baseline term: the league offensive-rebound
// share × 100, leagueORB/(leagueORB+leagueDRB) × 100, where leagueORB/leagueDRB are
// the mean floor1(ORB)/floor1(DRB) across EVERY player in the bundle. Because it is a
// SHARE, the mean-vs-sum scale cancels — faithful to +0x6818/+0x6848 being league
// baselines. An all-zero-rated bundle (or an empty one) returns the neutral 50.0
// (a 50% share), with no divide-by-zero.
func leagueReboundBaseline(b bundle.Bundle) float64 {
	var sumORB, sumDRB float64
	for _, p := range b.Players {
		sumORB += floor1(p.ORB)
		sumDRB += floor1(p.DRB)
	}
	denom := sumORB + sumDRB
	if denom <= 0 {
		return 50.0
	}
	return sumORB / denom * 100
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
