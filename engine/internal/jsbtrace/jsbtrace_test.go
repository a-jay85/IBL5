package jsbtrace

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

const goodHeader = `{"format":"jsb-trace/1","source":"frida","jsb_build":"b1","function":"f","address":"0x401000"}`
const goodRecord = `{"in":{"a":1},"out":{"b":2}}`

func writeCapture(t *testing.T, dir, name string, lines ...string) {
	t.Helper()
	body := strings.Join(lines, "\n") + "\n"
	if err := os.WriteFile(filepath.Join(dir, name), []byte(body), 0o600); err != nil {
		t.Fatal(err)
	}
}

func assertDefaultDir(t *testing.T) {
	t.Helper()
	root, err := moduleRoot()
	if err != nil {
		t.Fatal(err)
	}
	want := filepath.Join(root, "testdata", "jsb-captures")
	got, err := captureDir()
	if err != nil {
		t.Fatal(err)
	}
	if got != want {
		t.Fatalf("captureDir() = %q, want %q", got, want)
	}
	if _, err := os.Stat(filepath.Join(got, "README.md")); err != nil {
		t.Fatalf("default dir lacks README.md: %v", err)
	}
}

func TestLoadDefaultDirWhenOverrideUnset(t *testing.T) {
	// Setenv first so the original value is restored on cleanup.
	t.Setenv("JSB_TRACE_CAPTURE_DIR", "x")
	if err := os.Unsetenv("JSB_TRACE_CAPTURE_DIR"); err != nil {
		t.Fatal(err)
	}
	assertDefaultDir(t)
}

func TestLoadDefaultDirWhenOverrideEmpty(t *testing.T) {
	t.Setenv("JSB_TRACE_CAPTURE_DIR", "")
	assertDefaultDir(t)
}

func TestLoadOverrideDirWins(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("JSB_TRACE_CAPTURE_DIR", dir)
	writeCapture(t, dir, "ok.jsonl", goodHeader, goodRecord, "", goodRecord)
	c, err := Load("ok.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	if len(c.Records) != 2 || c.Header.Function != "f" || c.Header.Address != "0x401000" {
		t.Fatalf("unexpected capture: %+v", c)
	}
	if string(c.Records[0].Out) != `{"b":2}` {
		t.Fatalf("out = %s", c.Records[0].Out)
	}
	MustLoad(t, "ok.jsonl")
}

func TestLoadRejectsPathEscape(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("JSB_TRACE_CAPTURE_DIR", dir)
	for _, name := range []string{"../golden.json", "a/b.jsonl", `a\b.jsonl`} {
		_, err := Load(name)
		if err == nil || !strings.Contains(err.Error(), "escapes") {
			t.Fatalf("Load(%q) err = %v, want escape error", name, err)
		}
	}
}

func TestLoadRejectsBadHeader(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("JSB_TRACE_CAPTURE_DIR", dir)
	bad := map[string]string{
		"format.jsonl":  `{"format":"jsb-trace/2","source":"frida","jsb_build":"b","function":"f","address":"0x1"}`,
		"source.jsonl":  `{"format":"jsb-trace/1","source":"gdb","jsb_build":"b","function":"f","address":"0x1"}`,
		"build.jsonl":   `{"format":"jsb-trace/1","source":"frida","jsb_build":"","function":"f","address":"0x1"}`,
		"func.jsonl":    `{"format":"jsb-trace/1","source":"frida","jsb_build":"b","function":"","address":"0x1"}`,
		"address.jsonl": `{"format":"jsb-trace/1","source":"frida","jsb_build":"b","function":"f","address":"0X1G"}`,
	}
	for name, hdr := range bad {
		writeCapture(t, dir, name, hdr, goodRecord)
		_, err := Load(name)
		if err == nil || !strings.Contains(err.Error(), "bad header") {
			t.Fatalf("Load(%q) err = %v, want bad header", name, err)
		}
	}
}

func TestLoadRejectsZeroRecords(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("JSB_TRACE_CAPTURE_DIR", dir)
	writeCapture(t, dir, "empty.jsonl", goodHeader, "")
	_, err := Load("empty.jsonl")
	if err == nil || !strings.Contains(err.Error(), "no records") {
		t.Fatalf("err = %v, want no records", err)
	}
}

func TestLoadRejectsRecordMissingOut(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("JSB_TRACE_CAPTURE_DIR", dir)
	writeCapture(t, dir, "noout.jsonl", goodHeader, `{"in":{"a":1}}`)
	_, err := Load("noout.jsonl")
	if err == nil || !strings.Contains(err.Error(), `missing "out"`) {
		t.Fatalf("err = %v, want missing out", err)
	}
}
