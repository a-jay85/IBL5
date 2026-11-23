package sim

import (
	"fmt"
	"go/ast"
	"go/parser"
	"go/token"
	"io/fs"
	"regexp"
	"sort"
	"strings"
	"testing"
)

var funTokenRE = regexp.MustCompile(`FUN_[0-9a-f]{8}`)

// docAddress returns the single FUN_xxxxxxxx token in a doc comment, or "" when
// the comment holds none or more than one.
func docAddress(doc string) string {
	toks := funTokenRE.FindAllString(doc, -1)
	if len(toks) != 1 {
		return ""
	}
	return toks[0]
}

// drawFunc is one function that makes an RNG draw.
type drawFunc struct {
	key string
	doc string
}

// scanDrawFuncs parses every non-test file in the package and returns the
// functions that call .Float64(), .IntN(n) or .Raw().
func scanDrawFuncs(t *testing.T) map[string]drawFunc {
	t.Helper()
	fset := token.NewFileSet()
	pkgs, err := parser.ParseDir(fset, ".", func(fi fs.FileInfo) bool {
		return !strings.HasSuffix(fi.Name(), "_test.go")
	}, parser.ParseComments)
	if err != nil {
		t.Fatalf("parse: %v", err)
	}
	out := map[string]drawFunc{}
	for _, pkg := range pkgs {
		for _, f := range pkg.Files {
			for _, d := range f.Decls {
				fd, ok := d.(*ast.FuncDecl)
				if !ok || fd.Body == nil || !makesDraw(fd) {
					continue
				}
				key := funcKey(fd)
				out[key] = drawFunc{key: key, doc: fd.Doc.Text()}
			}
		}
	}
	return out
}

func makesDraw(fd *ast.FuncDecl) bool {
	found := false
	ast.Inspect(fd.Body, func(n ast.Node) bool {
		call, ok := n.(*ast.CallExpr)
		if !ok {
			return true
		}
		sel, ok := call.Fun.(*ast.SelectorExpr)
		if !ok {
			return true
		}
		switch {
		case sel.Sel.Name == "Float64" && len(call.Args) == 0,
			sel.Sel.Name == "Raw" && len(call.Args) == 0,
			sel.Sel.Name == "IntN" && len(call.Args) == 1:
			found = true
		}
		return true
	})
	return found
}

func funcKey(fd *ast.FuncDecl) string {
	if fd.Recv == nil || len(fd.Recv.List) == 0 {
		return "sim." + fd.Name.Name
	}
	if star, ok := fd.Recv.List[0].Type.(*ast.StarExpr); ok {
		return fmt.Sprintf("sim.(*%s).%s", recvName(star.X), fd.Name.Name)
	}
	return fmt.Sprintf("sim.%s.%s", recvName(fd.Recv.List[0].Type), fd.Name.Name)
}

func recvName(e ast.Expr) string {
	switch x := e.(type) {
	case *ast.Ident:
		return x.Name
	case *ast.IndexExpr:
		return recvName(x.X)
	case *ast.IndexListExpr:
		return recvName(x.X)
	}
	return "?"
}

func TestDrawSitesCoverEveryCallSite(t *testing.T) {
	funcs := scanDrawFuncs(t)
	var missing, extra []string
	for k := range funcs {
		if _, ok := DrawSites[k]; !ok {
			missing = append(missing, k)
		}
	}
	for k := range DrawSites {
		if _, ok := funcs[k]; !ok {
			extra = append(extra, k)
		}
	}
	if len(missing) == 0 && len(extra) == 0 {
		return
	}
	sort.Strings(missing)
	sort.Strings(extra)
	keys := make([]string, 0, len(funcs))
	for k := range funcs {
		keys = append(keys, k)
	}
	sort.Strings(keys)
	var sb strings.Builder
	sb.WriteString("var DrawSites = map[string]string{\n")
	for _, k := range keys {
		fmt.Fprintf(&sb, "\t%q: %q,\n", k, docAddress(funcs[k].doc))
	}
	sb.WriteString("}")
	t.Errorf("DrawSites key set differs from AST draw sites\nmissing from table: %v\nstale in table: %v\nexpected literal:\n%s", missing, extra, sb.String())
}

func TestDrawSitesAddressesMatchDocComments(t *testing.T) {
	funcs := scanDrawFuncs(t)
	for k, want := range DrawSites {
		fn, ok := funcs[k]
		if !ok {
			t.Errorf("%s: no draw function found", k)
			continue
		}
		if got := docAddress(fn.doc); got != want {
			t.Errorf("%s: DrawSites has %q, doc comment gives %q", k, want, got)
		}
	}
}

func TestDocAddress(t *testing.T) {
	cases := []struct{ name, doc, want string }{
		{"single token", "ports FUN_004e42e0", "FUN_004e42e0"},
		{"two tokens are ambiguous", "FUN_004e42e0 and FUN_004e1ba0", ""},
		{"uppercase near-miss", "FUN_004E42E0", ""},
		{"none", "no address here", ""},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if got := docAddress(c.doc); got != c.want {
				t.Errorf("docAddress(%q) = %q, want %q", c.doc, got, c.want)
			}
		})
	}
}
