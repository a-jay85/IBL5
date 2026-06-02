package sim

import (
	"math"
	"testing"
)

// fiveStarters builds a 5-man lineup of identical starters (mkPlayer defaults:
// OO=6, OD=5) for the given team, used to exercise the lineup aggregators.
func fiveStarters(team int) []onCourt {
	var lineup []onCourt
	for slot := slotPG; slot <= slotC; slot++ {
		lineup = append(lineup, oc(slot, mkPlayer(slot, team, slot, 48)))
	}
	return lineup
}

const teamQualityEps = 1e-9

// --- matrix #3: defMatchupQuality — summation + ×1.5 cap --------------------

func TestDefMatchupQuality_SumAndCap(t *testing.T) {
	// Uncapped: 5 defenders at OD=5 → Σ = 5 × floor1(5)×defQualityRatingScale.
	def := fiveStarters(7)
	wantSum := 5 * floor1(5) * defQualityRatingScale // 5 × 5 × 0.25 = 6.25
	ceiling := teamDefBaseline * defQualityCapTeamMult * defQualityCapMultiplier
	if wantSum >= ceiling {
		t.Fatalf("test setup: uncapped Σ %.3f should be below ceiling %.3f", wantSum, ceiling)
	}
	if got := defMatchupQuality(def); math.Abs(got-wantSum) > teamQualityEps {
		t.Errorf("defMatchupQuality (uncapped) = %.4f, want %.4f", got, wantSum)
	}

	// Capped: 5 defenders at OD=99 → Σ far exceeds the ceiling → returns ceiling.
	hi := fiveStarters(7)
	for i := range hi {
		hi[i].OD = 99
	}
	rawSum := 5 * floor1(99) * defQualityRatingScale // 5 × 99 × 0.25 = 123.75
	if rawSum <= ceiling {
		t.Fatalf("test setup: high-OD Σ %.3f should exceed ceiling %.3f", rawSum, ceiling)
	}
	if got := defMatchupQuality(hi); math.Abs(got-ceiling) > teamQualityEps {
		t.Errorf("defMatchupQuality (capped) = %.4f, want ceiling %.4f", got, ceiling)
	}
}

// --- matrix #3: offQualityWithHCA — summation + HCA-subtraction sign --------

func TestOffQualityWithHCA_SubtractionSign(t *testing.T) {
	off := fiveStarters(3)

	neutral := offQualityWithHCA(off, 0)
	home := offQualityWithHCA(off, hcaMagnitude)  // +0.2 per player → Σ shrinks
	away := offQualityWithHCA(off, -hcaMagnitude) // −0.2 per player → Σ grows

	wantNeutral := 5 * floor1(6) * offQualityRatingScale // 5 × 6 × 0.08 = 2.4
	if math.Abs(neutral-wantNeutral) > teamQualityEps {
		t.Errorf("offQualityWithHCA(neutral) = %.4f, want %.4f", neutral, wantNeutral)
	}

	// Home reduces every player's term by hcaMagnitude → Σ smaller by 5×0.2 = 1.0;
	// away increases it by the same. This shrinking divisor for the home team is the
	// home-favorable mechanism.
	if math.Abs((neutral-home)-1.0) > teamQualityEps {
		t.Errorf("home Σ = %.4f, want neutral − 1.0 = %.4f", home, neutral-1.0)
	}
	if math.Abs((away-neutral)-1.0) > teamQualityEps {
		t.Errorf("away Σ = %.4f, want neutral + 1.0 = %.4f", away, neutral+1.0)
	}
	if !(home < neutral && neutral < away) {
		t.Errorf("expected home(%.4f) < neutral(%.4f) < away(%.4f) — HCA divisor sign wrong", home, neutral, away)
	}
}

// --- matrix #3 (boundary): offQuality floor prevents a non-positive divisor --

func TestOffQualityWithHCA_Floor(t *testing.T) {
	// A single unrated player (floor1(0)=1 → 0.08) minus the +0.2 home delta is
	// negative; the floor must clamp it to offQualityFloor so foul/offQ stays
	// well-defined (no divide-by-zero, no sign flip).
	one := []onCourt{oc(slotPG, mkPlayer(1, 3, slotPG, 0))}
	one[0].OO = 0
	got := offQualityWithHCA(one, hcaMagnitude)
	raw := floor1(0)*offQualityRatingScale - hcaMagnitude // 0.08 − 0.2 = −0.12
	if raw >= offQualityFloor {
		t.Fatalf("test setup: raw %.4f should be below the floor %.4f", raw, offQualityFloor)
	}
	if math.Abs(got-offQualityFloor) > teamQualityEps {
		t.Errorf("offQualityWithHCA(floored) = %.4f, want offQualityFloor %.4f", got, offQualityFloor)
	}
}
