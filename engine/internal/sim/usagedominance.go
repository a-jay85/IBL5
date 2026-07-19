package sim

// usageDominanceThreshold and usageDominanceFloor are the two FUN_004e04e0 gates
// (J26 §2): 0x3fe00000 = 0.5 and _DAT_0066d398 = 0.3. The 0.3 floor is INERT for
// the resulting flag (0.5 > 0.3, so any ratio clearing 0.5 already clears 0.3) and
// is kept only to mirror the binary's nested structure faithfully.
const (
	usageDominanceThreshold = 0.5
	usageDominanceFloor     = 0.3
)

// computeUsageDominanceFlags reproduces FUN_004e04e0's per-slot usage-dominance
// flag (CEngine[+0x6334+slot*4] ∈ {0,4}) for ONE team's on-court five. It returns a
// [6]bool indexed by slot 1..5 (index 0 unused, mirroring leagueAST48ByPos [6]float64):
// flags[slot] == true means the binary's "flag 4" — that slot's per-possession usage
// ratio strictly exceeds ~0.5.
//
// ratio = twoPARate(p) / Σ twoPtBucketWeight(q) over the on-court five (J26 §2:
// numerator "2pt-attempt-weight" local_ac ÷ denominator Σ +0xD90 composite). The
// numerator is the D88 per-48 2PA rate — a STATED approximation of the still-unpinned
// local_ac (J26 "The unpinned load"), never an identity; see Architectural trade-offs.
// Coaching-INDEPENDENT and dynamic per-possession, per J26 §1.
func computeUsageDominanceFlags(players []onCourt) [6]bool {
	var flags [6]bool

	// Compute denominator: Σ twoPtBucketWeight over all on-court players.
	var denom float64
	for _, p := range players {
		denom += twoPtBucketWeight(p)
	}

	// Guard: zero or non-positive denominator → all flags false (no division).
	if denom <= 0 {
		return flags
	}

	for _, p := range players {
		if p.slot < 1 || p.slot > 5 {
			continue // defensive: on-court slots are always 1..5
		}
		ratio := twoPARate(p) / denom
		flags[p.slot] = ratio > usageDominanceFloor && ratio > usageDominanceThreshold
	}
	return flags
}
