package main

import (
	"io"
	"path/filepath"
	"strings"
	"testing"
)

// TestRun_RejectsBadArgs: a missing -plr, an unknown flag, a nonexistent -plr
// path, and a run with neither -calibrate nor -out all return an error.
func TestRun_RejectsBadArgs(t *testing.T) {
	missing := filepath.Join(t.TempDir(), "nope.plr")
	cases := []struct {
		name string
		args []string
		want string
	}{
		{"missing -plr", []string{"-calibrate"}, "-plr"},
		{"unknown flag", []string{"-bogus"}, ""},
		{"nonexistent path", []string{"-calibrate", "-plr", missing}, ""},
		{"neither mode", []string{"-plr", missing}, "-calibrate"},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			err := run(tc.args, io.Discard)
			if err == nil {
				t.Fatalf("run(%v) = nil, want an error", tc.args)
			}
			if tc.want != "" && !strings.Contains(err.Error(), tc.want) {
				t.Errorf("run(%v) error %q does not name %q", tc.args, err, tc.want)
			}
		})
	}
}
