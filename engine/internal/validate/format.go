package validate

import (
	"fmt"
	"io"
)

// WriteReport renders a Report as a deterministic, human-readable PASS/FAIL
// listing: a header, one GAME block per validated game with one line per stat
// per team, an UNMATCHED line per unpairable .sco game, and a final RESULT
// line. The output depends only on the Report contents (no timestamps, no
// map-iteration order), so two runs over the same corpus are byte-identical.
//
// Write errors are intentionally ignored (via the p closure), as the report is
// a diagnostic printed to stdout — mirroring the engine's other CLI output
// paths, where the os.Stderr/os.Stdout write error is not actionable.
func WriteReport(w io.Writer, rep Report) {
	p := func(format string, a ...any) { _, _ = fmt.Fprintf(w, format, a...) }

	p("JSB validation report — runs=%d base_seed=%d game_type=%d\n",
		rep.Runs, rep.BaseSeed, int(rep.GameType))
	for _, g := range rep.Games {
		verdict := "PASS"
		if !g.Pass {
			verdict = "FAIL"
		}
		p("GAME visitor=%d home=%d date=%s %s\n",
			g.VisitorTeamID, g.HomeTeamID, g.Date, verdict)
		for _, r := range g.Rows {
			p("  team=%d %s\n", r.TeamID, r.Detail)
		}
	}
	for _, u := range rep.Unmatched {
		p("UNMATCHED stem=%s visitor=%d home=%d scores=%d-%d: %s\n",
			u.Stem, u.VisitorTeamID, u.HomeTeamID, u.VisitorScore, u.HomeScore, u.Reason)
	}
	result := "PASS"
	if !rep.Pass {
		result = "FAIL"
	}
	p("RESULT: %s (%d games, %d unmatched)\n",
		result, len(rep.Games), len(rep.Unmatched))
}
