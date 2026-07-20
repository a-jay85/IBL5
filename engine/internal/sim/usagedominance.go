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
// ratio = twoPtBucketWeight(p) / Σ twoPtBucketWeight(q) over the on-court five —
// numerator and denominator terms are the SAME +0xD90 composite, so the ratio is
// player p's SHARE of the five's sum (J26's "local_ac" numerator RE-pinned to +0xD90,
// NOT +0xD88; jsb-fgpct-phase4-numerator-pin-20260720.md). Coaching-INDEPENDENT and
// dynamic per-possession, per J26 §1.
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
		// FAITHFUL numerator (RE-pinned J-fgpct-re, 2026-07-20): +0xD90 =
		// twoPtBucketWeight, the SAME composite summed in denom — NOT twoPARate
		// (+0xD88). objdump of FUN_004e04e0: the flag-4 numerator load
		// `fldl 0xe20(%esp)` resolves to copy-dest(esp+0x90)+0xd90; the copy-ctor
		// FUN_00405970 writes dest+0xd90 = src+0xd90 (decompile :3511). So the ratio
		// is player p's SHARE of the on-court five's +0xD90 sum (mean 0.2), and at most
		// one slot per team can exceed 0.5. Denominator base _DAT_00669f00 = 0.0, so
		// Σ twoPtBucketWeight is faithful with no base term. This subsystem stays INERT
		// (both call sites pass [6]bool{}): a live archive measurement (stride 100, 4
		// runs, 30k games) put the flag's fire rate at 0.0005% of slot-evals → FG%
		// 46.40% vs 46.39% baseline (+0.01pp), band [47.5,48.9] NOT closed. Numerator
		// corrected from the earlier twoPARate stand-in for faithfulness only — it makes
		// the flag fire RARER, never toward the band. See jsb-native/re-artifacts/
		// jsb-fgpct-phase4-numerator-pin-20260720.md.
		ratio := twoPtBucketWeight(p) / denom
		flags[p.slot] = ratio > usageDominanceFloor && ratio > usageDominanceThreshold
	}
	return flags
}
