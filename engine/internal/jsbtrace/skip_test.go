package jsbtrace

import (
	"fmt"
	"os"
	"strings"
	"testing"
)

// skipRecorder records a Skipf message instead of stopping the test.
type skipRecorder struct {
	testing.TB
	skipped bool
	msg     string
}

func (r *skipRecorder) Helper() {}

func (r *skipRecorder) Skipf(format string, args ...any) {
	r.skipped = true
	r.msg = fmt.Sprintf(format, args...)
}

func unsetCaptureDir(t *testing.T) {
	t.Helper()
	old, had := os.LookupEnv("JSB_TRACE_CAPTURE_DIR")
	if err := os.Unsetenv("JSB_TRACE_CAPTURE_DIR"); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() {
		if had {
			if err := os.Setenv("JSB_TRACE_CAPTURE_DIR", old); err != nil {
				t.Error(err)
			}
		}
	})
}

const absentCapture = "pl-9999.jsonl"

func TestSkipIfAbsentSkipsWhenEnvUnset(t *testing.T) {
	unsetCaptureDir(t)
	r := &skipRecorder{TB: t}
	SkipIfAbsent(r, absentCapture)
	if !r.skipped {
		t.Fatal("want a skip with JSB_TRACE_CAPTURE_DIR unset and no local capture")
	}
}

func TestSkipIfAbsentSkipsWhenEnvEmpty(t *testing.T) {
	t.Setenv("JSB_TRACE_CAPTURE_DIR", "")
	r := &skipRecorder{TB: t}
	SkipIfAbsent(r, absentCapture)
	if !r.skipped {
		t.Fatal("want a skip with JSB_TRACE_CAPTURE_DIR empty and no local capture")
	}
}

func TestSkipIfAbsentNoSkipWhenEnvSet(t *testing.T) {
	t.Setenv("JSB_TRACE_CAPTURE_DIR", t.TempDir())
	r := &skipRecorder{TB: t}
	SkipIfAbsent(r, absentCapture)
	if r.skipped {
		t.Fatalf("a set JSB_TRACE_CAPTURE_DIR must never skip, got %q", r.msg)
	}
}

func TestSkipIfAbsentNoSkipWhenDefaultHasFile(t *testing.T) {
	unsetCaptureDir(t)
	r := &skipRecorder{TB: t}
	SkipIfAbsent(r, "README.md")
	if r.skipped {
		t.Fatalf("README.md is in the default dir, got skip %q", r.msg)
	}
}

func TestSkipIfAbsentReasonNamesCapture(t *testing.T) {
	unsetCaptureDir(t)
	r := &skipRecorder{TB: t}
	SkipIfAbsent(r, absentCapture)
	for _, want := range []string{absentCapture, "JSB_TRACE_CAPTURE_DIR is unset"} {
		if !strings.Contains(r.msg, want) {
			t.Errorf("skip message %q lacks %q", r.msg, want)
		}
	}
}
