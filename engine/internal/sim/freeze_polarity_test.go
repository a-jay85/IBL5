package sim

import (
	"fmt"
	"os"
	"reflect"
	"strings"
	"testing"
)

// abFlagFields returns the bool FreezeConfig fields whose names start with one of
// the given prefixes, in declaration order.
func abFlagFields(t *testing.T, prefixes ...string) []string {
	t.Helper()
	typ := reflect.TypeOf(FreezeConfig{})
	var names []string
	for i := 0; i < typ.NumField(); i++ {
		f := typ.Field(i)
		if f.Type.Kind() != reflect.Bool {
			continue
		}
		for _, p := range prefixes {
			if strings.HasPrefix(f.Name, p) {
				names = append(names, f.Name)
				break
			}
		}
	}
	return names
}

// TestFreezeConfig_ABFlagsZeroValueFaithful pins the inverted-polarity convention for
// A/B arms: the zero value is faithful (false), and validate() accepts a config with
// only that arm set, because an arm consumes no frozen Means.
func TestFreezeConfig_ABFlagsZeroValueFaithful(t *testing.T) {
	fields := abFlagFields(t, "Suppress", "Unfaithful")
	if len(fields) == 0 {
		t.Fatal("no Suppress*/Unfaithful* bool fields found on FreezeConfig")
	}
	var zero FreezeConfig
	for _, name := range fields {
		t.Run(name, func(t *testing.T) {
			if reflect.ValueOf(zero).FieldByName(name).Bool() {
				t.Fatalf("FreezeConfig{}.%s is true; the zero value must be faithful", name)
			}
			var fc FreezeConfig
			reflect.ValueOf(&fc).Elem().FieldByName(name).SetBool(true)
			if err := (Options{Freeze: fc}).validate(); err != nil {
				t.Fatalf("validate() rejected FreezeConfig{%s: true}: %v", name, err)
			}
		})
	}
}

// TestFreezeConfig_SuppressArmsWiredInAbFreeze asserts every Suppress* arm reaches the
// archive A/B harness: abFreeze() in threept_undershoot_archive_test.go must return
// sim.FreezeConfig{<Field>: true} for it, so a new arm cannot ship unmeasured.
func TestFreezeConfig_SuppressArmsWiredInAbFreeze(t *testing.T) {
	src, err := os.ReadFile("../calibrate/threept_undershoot_archive_test.go")
	if err != nil {
		t.Fatalf("read abFreeze source: %v", err)
	}
	fields := abFlagFields(t, "Suppress")
	if len(fields) == 0 {
		t.Fatal("no Suppress* bool fields found on FreezeConfig")
	}
	for _, name := range fields {
		want := fmt.Sprintf("sim.FreezeConfig{%s: true}", name)
		if !strings.Contains(string(src), want) {
			t.Errorf("abFreeze() has no %q case; wire the arm into the A/B harness", want)
		}
	}
}
