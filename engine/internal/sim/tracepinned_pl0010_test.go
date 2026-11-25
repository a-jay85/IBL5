package sim

import (
	"encoding/json"
	"errors"
	"fmt"
	"testing"

	"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
	"github.com/a-jay85/IBL5/engine/internal/rng"
)

// flagUnwritten seeds the flag table before each call. The routine writes only
// 0 or 4, so a slot still holding it after the call is one the routine left
// alone.
const flagUnwritten = -1

// pl0010Player is one on-court player in a capture input.
type pl0010Player struct {
	Idx  int     `json:"idx"`
	OO   int     `json:"oo"`
	DO   int     `json:"do_"`
	PO   int     `json:"po"`
	I54c int     `json:"i54c"`
	D78  float64 `json:"d78"`
	D90  float64 `json:"d90"`
	Db0  float64 `json:"db0"`
	De0  float64 `json:"de0"`
}

// pl0010Team is the offensive team block in a capture input.
type pl0010Team struct {
	D70 float64 `json:"d70"`
	D88 float64 `json:"d88"`
	Da0 float64 `json:"da0"`
	Da8 float64 `json:"da8"`
	Dd8 float64 `json:"dd8"`
}

// pl0010In is one capture input: the possession context, the lineup's players
// and team block, and the raw draws.
type pl0010In struct {
	PrevHandler int            `json:"prev_handler"`
	OffTeam     int            `json:"off_team"`
	HomeCourt   int            `json:"home_court"`
	PlayType    int            `json:"play_type"`
	P8In        int            `json:"p8_in"`
	Rand        []int          `json:"rand"`
	Lineup      []int          `json:"lineup"`
	Players     []pl0010Player `json:"players"`
	Team        pl0010Team     `json:"team"`
	PosBase     []float64      `json:"pos_base"`
	E16ea8      int            `json:"e16ea8"`
	Hca         float64        `json:"hca"`
}

// pl0010Out is one capture output: the picked player, the picked slot, the two
// caller bytes and the flag table after the call.
type pl0010Out struct {
	Ret       int   `json:"ret"`
	P6        int   `json:"p6"`
	P8        int   `json:"p8"`
	Slot      int   `json:"slot"`
	Flags6334 []int `json:"flags6334"`
}

// pl0010Inputs converts one decoded capture input into the ported selector's
// inputs. It is the only place a capture key meets a production field name.
// The flag table starts at flagUnwritten in every slot, because the capture
// does not hold the table's state before the call.
func pl0010Inputs(in pl0010In) ballHandlerInputs {
	var b ballHandlerInputs
	for k, p := range in.Players {
		b.Players[k] = ballHandlerPlayer{
			ID:             p.Idx,
			OutsideOff:     p.OO,
			DriveOff:       p.DO,
			PostOff:        p.PO,
			TwoPtComposite: p.D90,
			Aux1:           p.I54c,
			Aux2:           p.D78,
			Aux3:           p.Db0,
			Aux4:           p.De0,
		}
	}
	b.OffTeam = in.OffTeam
	b.HomeCourt = in.HomeCourt
	b.HomeCourtAdv = in.Hca
	copy(b.PosTwoPtPct[:], in.PosBase)
	b.WeightGate = in.E16ea8
	b.PlayType = in.PlayType
	b.PrevHandler = in.PrevHandler
	b.P8In = in.P8In
	b.TeamRollTerms = [4]float64{in.Team.D70, in.Team.Dd8, in.Team.Da8, in.Team.D88}
	b.TeamRollLimit = in.Team.Da0
	for k := range b.FlagsBefore {
		b.FlagsBefore[k] = flagUnwritten
	}
	return b
}

// decodePl0010 decodes one capture record and checks the shape the port
// relies on.
func decodePl0010(t *testing.T, i int, rec jsbtrace.Record) (pl0010In, pl0010Out) {
	t.Helper()
	var in pl0010In
	var out pl0010Out
	if err := json.Unmarshal(rec.In, &in); err != nil {
		t.Fatalf("record %d: decode in: %v", i, err)
	}
	if err := json.Unmarshal(rec.Out, &out); err != nil {
		t.Fatalf("record %d: decode out: %v", i, err)
	}
	if len(in.Players) != 5 || len(in.Lineup) != 5 || len(in.PosBase) != 5 {
		t.Fatalf("record %d: %d players, %d lineup slots, %d position bases, want 5 each", i, len(in.Players), len(in.Lineup), len(in.PosBase))
	}
	for k, p := range in.Players {
		if p.Idx != in.Lineup[k] {
			t.Fatalf("record %d: player %d is %d, lineup slot holds %d", i, k, p.Idx, in.Lineup[k])
		}
	}
	if len(out.Flags6334) != ballHandlerFlagCount {
		t.Fatalf("record %d: flag table has %d entries, want %d", i, len(out.Flags6334), ballHandlerFlagCount)
	}
	if len(in.Rand) == 0 {
		t.Fatalf("record %d: no raw draws", i)
	}
	return in, out
}

// TestTracePinnedPl0010BallHandler replays every capture record through
// selectBallHandlerJSB. It requires the exact picked player, slot, p6 and p8,
// and the exact raw draws in order. The capture does not hold the flag table
// from before the call, so the flag check covers the slots the routine wrote:
// each must equal the capture's table, and the run must write at least one.
// The p8_passthrough subtest pins that p8 passes through off play type 5 and
// that p6 stays 0, which no capture record can show because both are 0 in all
// of them.
func TestTracePinnedPl0010BallHandler(t *testing.T) {
	const name = "pl-0010.jsonl"
	jsbtrace.SkipIfAbsent(t, name)
	c := jsbtrace.MustLoad(t, name)
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	written := 0
	var passthrough *pl0010In
	for i, rec := range c.Records {
		in, out := decodePl0010(t, i, rec)
		if passthrough == nil && in.PlayType != 5 {
			cp := in
			passthrough = &cp
		}
		t.Run(fmt.Sprintf("rec%04d_play%d", i, in.PlayType), func(t *testing.T) {
			rp, err := rng.NewReplay(in.Rand)
			if err != nil {
				t.Fatalf("record %d: replay: %v", i, err)
			}
			got := selectBallHandlerJSB(pl0010Inputs(in), rp)

			if got.Ret != out.Ret {
				t.Errorf("record %d play %d: ret %d, capture %d", i, in.PlayType, got.Ret, out.Ret)
			}
			if got.Slot != out.Slot {
				t.Errorf("record %d play %d: slot %d, capture %d", i, in.PlayType, got.Slot, out.Slot)
			}
			if got.P6 != out.P6 {
				t.Errorf("record %d play %d: p6 %d, capture %d", i, in.PlayType, got.P6, out.P6)
			}
			if got.P8 != out.P8 {
				t.Errorf("record %d play %d: p8 %d, capture %d", i, in.PlayType, got.P8, out.P8)
			}
			for k, v := range got.Flags {
				if v == flagUnwritten {
					continue
				}
				written++
				if v != out.Flags6334[k] {
					t.Errorf("record %d play %d: flag %d written %d, capture %d", i, in.PlayType, k, v, out.Flags6334[k])
				}
			}

			d := rp.Draws()
			if len(d) != len(in.Rand) {
				t.Fatalf("record %d play %d: took %d draws, capture holds %d", i, in.PlayType, len(d), len(in.Rand))
			}
			for j := range d {
				if d[j].Value != in.Rand[j] {
					t.Errorf("record %d play %d: draw %d = %d, capture raw draw = %d", i, in.PlayType, j, d[j].Value, in.Rand[j])
				}
			}
		})
	}
	if written == 0 {
		t.Error("no record wrote a usage-dominance flag")
	}

	t.Run("p8_passthrough", func(t *testing.T) {
		if passthrough == nil {
			t.Fatal("capture holds no record off play type 5")
		}
		b := pl0010Inputs(*passthrough)
		b.P8In = 1
		draws := make([]int, 64)
		for k := range draws {
			draws[k] = (rng.RawMax + 1) / 2
		}
		rp, err := rng.NewReplay(draws)
		if err != nil {
			t.Fatalf("replay: %v", err)
		}
		got := selectBallHandlerJSB(b, rp)
		if got.P8 != 1 {
			t.Errorf("p8 %d off play type 5, want the caller's 1", got.P8)
		}
		if got.P6 != 0 {
			t.Errorf("p6 %d with no prior handler, want 0", got.P6)
		}
	})
	t.Logf("pinned %d capture records, %d written flags", len(c.Records), written)
}

// TestTracePinnedPl0010BallHandlerShortStream replays every capture record with
// its last raw draw removed. selectBallHandlerJSB must run out of draws on every
// record, which shows it consumes every captured draw.
func TestTracePinnedPl0010BallHandlerShortStream(t *testing.T) {
	const name = "pl-0010.jsonl"
	jsbtrace.SkipIfAbsent(t, name)
	c := jsbtrace.MustLoad(t, name)
	if len(c.Records) == 0 {
		t.Fatal("capture holds no records")
	}
	for i, rec := range c.Records {
		in, _ := decodePl0010(t, i, rec)
		rp, err := rng.NewReplay(in.Rand[:len(in.Rand)-1])
		if err != nil {
			t.Fatalf("record %d: replay: %v", i, err)
		}
		func() {
			defer func() {
				v := recover()
				if v == nil {
					t.Errorf("record %d: finished on %d of %d draws, want an exhausted stream", i, len(in.Rand)-1, len(in.Rand))
					return
				}
				err, ok := v.(error)
				var ex *rng.ExhaustedError
				if !ok || !errors.As(err, &ex) {
					t.Errorf("record %d: panic %v, want *rng.ExhaustedError", i, v)
				}
			}()
			selectBallHandlerJSB(pl0010Inputs(in), rp)
		}()
	}
}
