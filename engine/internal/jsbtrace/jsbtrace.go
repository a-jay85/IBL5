// Package jsbtrace loads raw per-call captures taken from the original JSB
// binary. A capture pairs the inputs and outputs of one JSB routine so a Go
// port can be pinned against recorded values. The package only reads; it has
// no write API, because captures come from a hook on the original binary.
package jsbtrace

import (
	"bufio"
	"bytes"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

// Header is line 1 of a capture file.
type Header struct {
	Format   string `json:"format"`
	Source   string `json:"source"`
	JSBBuild string `json:"jsb_build"`
	Function string `json:"function"`
	Address  string `json:"address"`
}

// Record is one captured call: the inputs and the outputs of the routine.
type Record struct {
	In  json.RawMessage
	Out json.RawMessage
}

// Capture is a parsed capture file.
type Capture struct {
	Header  Header
	Records []Record
}

var addressRE = regexp.MustCompile(`^0x[0-9a-f]+$`)

// moduleRoot walks up from the working directory to the first directory
// holding go.mod.
func moduleRoot() (string, error) {
	dir, err := os.Getwd()
	if err != nil {
		return "", err
	}
	for {
		if _, err := os.Stat(filepath.Join(dir, "go.mod")); err == nil {
			return dir, nil
		}
		parent := filepath.Dir(dir)
		if parent == dir {
			return "", fmt.Errorf("jsbtrace: no go.mod found above working directory")
		}
		dir = parent
	}
}

// captureDir returns the capture directory. A non-empty JSB_TRACE_CAPTURE_DIR
// replaces the default; an empty value means the default.
func captureDir() (string, error) {
	if dir := os.Getenv("JSB_TRACE_CAPTURE_DIR"); dir != "" {
		return dir, nil
	}
	root, err := moduleRoot()
	if err != nil {
		return "", err
	}
	return filepath.Join(root, "testdata", "jsb-captures"), nil
}

// Load reads and validates the capture file called name. The name must be a
// bare file name ending in .jsonl.
func Load(name string) (Capture, error) {
	var c Capture
	if strings.Contains(name, "..") || strings.ContainsAny(name, "/\\") {
		return c, fmt.Errorf("jsbtrace: capture name %q escapes the capture directory", name)
	}
	if !strings.HasSuffix(name, ".jsonl") {
		return c, fmt.Errorf("jsbtrace: capture name %q must end in .jsonl", name)
	}
	dir, err := captureDir()
	if err != nil {
		return c, err
	}
	data, err := os.ReadFile(filepath.Join(dir, name))
	if err != nil {
		return c, fmt.Errorf("jsbtrace: read capture: %w", err)
	}
	return parse(name, data)
}

func parse(name string, data []byte) (Capture, error) {
	var c Capture
	sc := bufio.NewScanner(bytes.NewReader(data))
	sc.Buffer(make([]byte, 0, 1<<20), 64<<20)
	lineNo := 0
	headerSeen := false
	for sc.Scan() {
		lineNo++
		line := bytes.TrimSpace(sc.Bytes())
		if len(line) == 0 {
			continue
		}
		if !headerSeen {
			if lineNo != 1 {
				return c, fmt.Errorf("jsbtrace: %s: header must be line 1", name)
			}
			h, err := parseHeader(line)
			if err != nil {
				return c, fmt.Errorf("jsbtrace: %s: %w", name, err)
			}
			c.Header = h
			headerSeen = true
			continue
		}
		rec, err := parseRecord(line)
		if err != nil {
			return c, fmt.Errorf("jsbtrace: %s line %d: %w", name, lineNo, err)
		}
		c.Records = append(c.Records, rec)
	}
	if err := sc.Err(); err != nil {
		return c, fmt.Errorf("jsbtrace: %s: %w", name, err)
	}
	if !headerSeen {
		return c, fmt.Errorf("jsbtrace: %s: bad header: file is empty", name)
	}
	if len(c.Records) == 0 {
		return c, fmt.Errorf("jsbtrace: %s: no records", name)
	}
	return c, nil
}

func parseHeader(line []byte) (Header, error) {
	var h Header
	if err := json.Unmarshal(line, &h); err != nil {
		return h, fmt.Errorf("bad header: %w", err)
	}
	if h.Format != "jsb-trace/1" {
		return h, fmt.Errorf("bad header: format %q, want jsb-trace/1", h.Format)
	}
	if h.Source != "frida" && h.Source != "x32dbg" {
		return h, fmt.Errorf("bad header: source %q, want frida or x32dbg", h.Source)
	}
	if h.JSBBuild == "" {
		return h, fmt.Errorf("bad header: jsb_build is empty")
	}
	if h.Function == "" {
		return h, fmt.Errorf("bad header: function is empty")
	}
	if !addressRE.MatchString(h.Address) {
		return h, fmt.Errorf("bad header: address %q does not match %s", h.Address, addressRE)
	}
	return h, nil
}

func parseRecord(line []byte) (Record, error) {
	var raw map[string]json.RawMessage
	if err := json.Unmarshal(line, &raw); err != nil {
		return Record{}, fmt.Errorf("bad record: %w", err)
	}
	var r Record
	for _, k := range []string{"in", "out"} {
		v, ok := raw[k]
		if !ok {
			return Record{}, fmt.Errorf("record missing %q", k)
		}
		if t := bytes.TrimSpace(v); len(t) == 0 || t[0] != '{' {
			return Record{}, fmt.Errorf("record key %q must be a JSON object", k)
		}
		if k == "in" {
			r.In = v
		} else {
			r.Out = v
		}
	}
	return r, nil
}

// SkipIfAbsent skips tb when JSB_TRACE_CAPTURE_DIR is unset or empty and the
// default capture directory has no file called name. A capture hosted in the
// private jsb-native repo has no copy in CI, so the test skips and says why.
// A set JSB_TRACE_CAPTURE_DIR never skips: a missing file there is a failure.
func SkipIfAbsent(tb testing.TB, name string) {
	tb.Helper()
	if os.Getenv("JSB_TRACE_CAPTURE_DIR") != "" {
		return
	}
	dir, err := captureDir()
	if err != nil {
		return // MustLoad reports the error
	}
	if _, err := os.Stat(filepath.Join(dir, name)); err == nil {
		return
	}
	tb.Skipf("jsbtrace: capture %q is not in %s and JSB_TRACE_CAPTURE_DIR is unset; it lives in the private jsb-native repo", name, dir)
}

// MustLoad is Load that fails the test on error.
func MustLoad(tb testing.TB, name string) Capture {
	tb.Helper()
	c, err := Load(name)
	if err != nil {
		tb.Fatalf("jsbtrace.MustLoad(%q): %v", name, err)
	}
	return c
}
