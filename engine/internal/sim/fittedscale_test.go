package sim

import (
	"go/ast"
	"go/parser"
	"go/token"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// fittedScaleNames are the fitted multipliers the live outcome-map port
// removed (engine/docs/j27-live-outcome-map-port.md). None may come back in
// the sim package.
var fittedScaleNames = map[string]bool{"foulBucketScale": true, "foulBucketVolumeScale": true,
	"andOneHalfCourtScale": true, "stealTurnoverScale": true, "nonStealTurnoverScale": true}

// fittedScaleIdents returns every identifier in src that names a fitted
// scale. It parses with mode 0, which drops comments, so a comment that names
// a removed scale is no hit.
func fittedScaleIdents(name string, src []byte) ([]string, error) {
	f, err := parser.ParseFile(token.NewFileSet(), name, src, 0) // mode 0 drops comments
	if err != nil {
		return nil, err
	}
	var hits []string
	ast.Inspect(f, func(n ast.Node) bool {
		if id, ok := n.(*ast.Ident); ok && fittedScaleNames[id.Name] {
			hits = append(hits, id.Name)
		}
		return true
	})
	return hits, nil
}

func TestNoFittedFoulScales(t *testing.T) {
	paths, err := filepath.Glob("*.go")
	if err != nil {
		t.Fatalf("glob: %v", err)
	}
	scanned := 0
	for _, p := range paths {
		if strings.HasSuffix(p, "_test.go") {
			continue
		}
		src, err := os.ReadFile(p)
		if err != nil {
			t.Fatalf("read %s: %v", p, err)
		}
		hits, err := fittedScaleIdents(p, src)
		if err != nil {
			t.Fatalf("parse %s: %v", p, err)
		}
		for _, h := range hits {
			t.Errorf("%s declares or uses fitted scale %s", p, h)
		}
		scanned++
	}
	if scanned == 0 {
		t.Fatal("scanned zero non-test Go files in internal/sim")
	}
}

func TestFittedScaleIdents_IgnoresComments(t *testing.T) {
	clean := []byte("package sim\n\n// foulBucketScale was removed\nvar foulBucketScaleOld = 1.0\n")
	hits, err := fittedScaleIdents("clean.go", clean)
	if err != nil {
		t.Fatalf("parse clean: %v", err)
	}
	if len(hits) != 0 {
		t.Errorf("clean source hits = %v, want none", hits)
	}

	dirty := []byte("package sim\n\nconst foulBucketScale = 1.0\n")
	hits, err = fittedScaleIdents("dirty.go", dirty)
	if err != nil {
		t.Fatalf("parse dirty: %v", err)
	}
	if len(hits) != 1 || hits[0] != "foulBucketScale" {
		t.Errorf("dirty source hits = %v, want [foulBucketScale]", hits)
	}
}
