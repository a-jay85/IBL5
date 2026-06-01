//go:build validate

// This corpus suite is gated behind the `validate` build tag, so the normal
// `go test ./...` (and the engine.yml CI) NEVER compiles or runs it. Invoke it
// explicitly against a developer-local corpus:
//
//	cd engine && JSB_CORPUS_DIR=/path/to/triples \
//	  go test -tags validate ./internal/validate/ -run ValidateCorpusReal
//
// The corpus files are large 5.60 backup triples (.plr/.sch/.sco) that are NOT
// committed to the repo (see the PR9b plan, Fixtures & corpus provisioning). The
// tolerance bands are STARTING values pending calibration (OPEN #1), so this
// suite is a DIAGNOSTIC a developer/nightly runs, not a per-PR merge gate.
package validate

import (
	"os"
	"strconv"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/bundle"
)

// Master 5.60 corpus candidates (developer-local; point JSB_CORPUS_DIR at one):
//   - ~/Downloads/IBL52007ConfFinalsGm4-7/   (PLAYOFF — run with JSB_CORPUS_GAMETYPE=4)
//   - ~/Downloads/2007IBLFinalsEnd/           (PLAYOFF — game type 4)
//   - ~/Downloads/Olympics2003/              (ALL-STAR scale — game type 5 or 6)
//   - ibl5/IBL5.{plr,sch,sco}                 (regular season — game type 2)
//
// The 5.99 default_*.plr files are a DIFFERENT engine version (PR9a's reader
// layout targets 5.60); they are unsupported here.
//
// IMPORTANT: neither the .sch nor the .sco stores a game type, so every game is
// stamped with JSB_CORPUS_GAMETYPE (default 2, regular). A playoff/all-star
// corpus run under the default regular type is a systematic basis mismatch —
// set JSB_CORPUS_GAMETYPE to match the corpus.
func TestValidateCorpusReal(t *testing.T) {
	if testing.Short() {
		t.Skip("skipping real-corpus suite in -short mode")
	}
	dir := os.Getenv("JSB_CORPUS_DIR")
	if dir == "" {
		t.Skip("JSB_CORPUS_DIR unset; set it to a dir of 5.60 .plr/.sch/.sco triples (corpus is not in the repo)")
	}

	runs := 200
	if v := os.Getenv("JSB_CORPUS_RUNS"); v != "" {
		n, err := strconv.Atoi(v)
		if err != nil || n < 1 {
			t.Fatalf("JSB_CORPUS_RUNS=%q is not a positive integer", v)
		}
		runs = n
	}

	gameType := bundle.GameTypeRegular
	if v := os.Getenv("JSB_CORPUS_GAMETYPE"); v != "" {
		n, err := strconv.Atoi(v)
		if err != nil {
			t.Fatalf("JSB_CORPUS_GAMETYPE=%q is not an integer", v)
		}
		gameType = bundle.GameType(n)
	}

	rep, err := ValidateCorpus(dir, runs, 0, gameType)
	if err != nil {
		t.Fatalf("ValidateCorpus(%q): %v", dir, err)
	}

	t.Logf("validated %d games, %d unmatched (runs=%d, game_type=%d)",
		len(rep.Games), len(rep.Unmatched), runs, int(gameType))
	if !rep.Pass {
		for _, u := range rep.Unmatched {
			t.Logf("UNMATCHED stem=%s %d@%d scores=%d-%d: %s",
				u.Stem, u.VisitorTeamID, u.HomeTeamID, u.VisitorScore, u.HomeScore, u.Reason)
		}
		for _, g := range rep.Games {
			for _, r := range g.Rows {
				if !r.Pass {
					t.Logf("OUT-OF-BAND game(%d@%d) team=%d %s", g.VisitorTeamID, g.HomeTeamID, r.TeamID, r.Detail)
				}
			}
		}
		t.Fatal("corpus has out-of-band stats or unmatched games (diagnostic — bands are uncalibrated, see OPEN #1)")
	}
}
