package jsbtrace

import (
	"os"
	"testing"
)

// TestValidateExternalCapture loads one capture named by JSB_TRACE_VALIDATE
// with the real loader. The jsb-native parity loop runs it to validate a fresh
// capture before committing it. Unset, it skips, so CI shows a visible SKIP.
func TestValidateExternalCapture(t *testing.T) {
	name := os.Getenv("JSB_TRACE_VALIDATE")
	if name == "" {
		t.Skip("JSB_TRACE_VALIDATE unset: names a capture for the parity loop to validate")
	}
	c := MustLoad(t, name)
	if len(c.Records) < 1 {
		t.Fatalf("capture %q has no records", name)
	}
}
