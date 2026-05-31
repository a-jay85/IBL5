package sim

import "github.com/a-jay85/IBL5/engine/internal/rng"

// shootFreeThrows resolves n free-throw attempts for the shooter, one
// independent roll each: made if FTP_value × fatigue ≥ rand_int(1,1000). Free
// throws use current-energy fatigue (player[+0x24]) — distinct from FG make,
// which uses base stamina — though in PR3a energy == base stamina so both are
// ≈1.0. Returns the number made (always ≤ n).
func shootFreeThrows(shooter onCourt, n int, r *rng.RNG) int {
	value := shotValueFT(shooter.FTP)
	fatigue := fatigueFactor(shooter.Stamina) // energy proxy (constant in PR3a)
	made := 0
	for i := 0; i < n; i++ {
		if rollMake(value, fatigue, r) {
			made++
		}
	}
	return made
}
