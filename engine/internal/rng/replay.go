package rng

import (
	"fmt"
	"regexp"
	"runtime"
	"strings"
)

// RawMax is the largest value a raw draw returns, matching the MSVC rand() range.
const RawMax = 32767

// Draw is one logged draw from a replay source.
type Draw struct {
	API    string `json:"api"` // "raw", "float64" or "intn"
	N      int    `json:"n,omitempty"`
	Value  int    `json:"v"`
	Caller string `json:"caller"`
}

// ExhaustedError is the panic value when a replay source runs out of draws.
type ExhaustedError struct {
	Index  int
	API    string
	Caller string
}

func (e *ExhaustedError) Error() string {
	return fmt.Sprintf("rng: replay exhausted at draw %d (%s) wanted by %s", e.Index, e.API, e.Caller)
}

type replay struct {
	values []int
	pos    int
	log    []Draw
}

// NewReplay returns an RNG that hands back values in order. It returns an error
// naming the first index whose value is outside 0..RawMax.
func NewReplay(values []int) (*RNG, error) {
	for i, v := range values {
		if v < 0 || v > RawMax {
			return nil, fmt.Errorf("rng: replay value %d at index %d is outside 0..%d", v, i, RawMax)
		}
	}
	cp := make([]int, len(values))
	copy(cp, values)
	return &RNG{rep: &replay{values: cp}}, nil
}

// Draws returns the log of draws consumed so far. It is nil on a PCG RNG.
func (r *RNG) Draws() []Draw {
	if r.rep == nil {
		return nil
	}
	return r.rep.log
}

func (p *replay) next(api string, n int) int {
	caller := callerName()
	if p.pos == len(p.values) {
		panic(&ExhaustedError{Index: p.pos, API: api, Caller: caller})
	}
	v := p.values[p.pos]
	p.pos++
	p.log = append(p.log, Draw{API: api, N: n, Value: v, Caller: caller})
	return v
}

func (p *replay) raw() int { return p.next("raw", 0) }

func (p *replay) float64() float64 { return float64(p.next("float64", 0)) / 32768 }

func (p *replay) intN(n int) int {
	if n <= 0 {
		panic("invalid argument to IntN")
	}
	return p.next("intn", n) % n
}

var (
	gowrapSuffix = regexp.MustCompile(`\.gowrap[0-9]+$`)
	funcSuffix   = regexp.MustCompile(`(\.func[0-9]+)+(\.[0-9]+)*$`)
)

// NormalizeCaller trims the import path and strips closure and gowrap suffixes
// from a runtime function name.
func NormalizeCaller(fn string) string {
	if i := strings.LastIndex(fn, "/"); i >= 0 {
		fn = fn[i+1:]
	}
	fn = gowrapSuffix.ReplaceAllString(fn, "")
	fn = funcSuffix.ReplaceAllString(fn, "")
	return fn
}

// callerName returns the normalized name of the first frame outside this
// package. Only the replay path calls it.
func callerName() string {
	var pcs [16]uintptr
	n := runtime.Callers(1, pcs[:])
	frames := runtime.CallersFrames(pcs[:n])
	for {
		f, more := frames.Next()
		if f.Function != "" && !strings.Contains(f.Function, "/internal/rng.") {
			return NormalizeCaller(f.Function)
		}
		if !more {
			break
		}
	}
	return "unknown"
}
