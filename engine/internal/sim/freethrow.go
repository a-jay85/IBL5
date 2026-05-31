package sim

import "github.com/a-jay85/IBL5/engine/internal/rng"

// shootFreeThrows resolves n free-throw attempts for the shooter, one
// independent roll each: made if FTP_value × fatigue ≥ rand_int(1,1000). Free
// throws use the shooter's live current-energy fatigue (player[+0x24]) — distinct
// from FG make, which uses base stamina. Under the committed fatigue curve both
// clamp to ≈1.0, so the split is inert today but faithful for a repaired curve.
// Returns the number made (always ≤ n).
func shootFreeThrows(shooter onCourt, n int, r *rng.RNG) int {
	value := shotValueFT(shooter.FTP)
	fatigue := fatigueFactor(shooter.energy) // live energy (inert under current curve)
	made := 0
	for i := 0; i < n; i++ {
		if rollMake(value, fatigue, r) {
			made++
		}
	}
	return made
}
