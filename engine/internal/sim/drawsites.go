package sim

// DrawSites maps each function that makes an RNG draw to the JSB address it
// ports. Keys are rng.NormalizeCaller forms (sim.selectRebounder or
// sim.(*gameState).maybeInjure, in quotes). A value is set only when the function's own
// doc comment holds exactly one FUN_xxxxxxxx token, and is "" otherwise.
// TestDrawSitesCoverEveryCallSite regenerates the key set from the AST and
// TestDrawSitesAddressesMatchDocComments pins each value to the doc comment.
var DrawSites = map[string]string{
	"sim.(*gameState).creditBlock":             "",
	"sim.(*gameState).maybeInjure":             "",
	"sim.(*gameState).rebound":                 "",
	"sim.(*gameState).resolveFoulAt":           "",
	"sim.(*gameState).resolveTurnoverPath":     "",
	"sim.(*gameState).runTransitionPossession": "",
	"sim.(*gameState).threePtShot":             "",
	"sim.(*gameState).transitionStealSucceeds": "",
	"sim.bucket3Weight":                        "",
	"sim.dayStartScaledPass":                   "FUN_005c6100",
	"sim.foulBucketWeight":                     "FUN_004e3860",
	"sim.possession":                           "",
	"sim.possessionTime":                       "",
	"sim.randInt":                              "FUN_0047f5a0",
	"sim.rollMake":                             "",
	"sim.selectBallHandler":                    "",
	"sim.selectDefender":                       "",
	"sim.selectOutcome":                        "",
	"sim.selectRebounder":                      "",
	"sim.selectShotType":                       "",
	"sim.simGameWith":                          "",
	"sim.transitionTriggers":                   "FUN_004e42e0",
}
