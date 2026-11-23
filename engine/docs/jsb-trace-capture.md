---
description: Runbook for capturing per-call (input, output) pairs from the original JSB binary into a jsb-trace/1 JSONL file.
last_verified: 2026-11-22
---

# JSB per-call trace capture

A trace-pinned test needs the inputs and outputs of one JSB routine, call by call. This runbook produces that file.

## Why a new format

The existing traces count events per team-game. See `engine/docs/j27-foul-volume-port.md` and the other `engine/docs/j27-*-port.md` files. They give no (input, output) pair for a single engine function. A port cannot be pinned against a count. It can be pinned against a pair.

## Steps

1. Pick the routine. Note its entry address and its build.
2. Attach a Frida hook, or an x32dbg breakpoint pair. For x32dbg, follow `engine/docs/x32dbg-foul-divisor-pin-runbook.md`.
3. At the entry, dump the argument registers and the struct fields the routine reads. This is `in`.
4. At the return, dump the return value and the fields the routine wrote. This is `out`.
5. Write one JSONL record per call: `{"in": {...}, "out": {...}}`. Both values are JSON objects.
6. Prepend the header line with `format`, `source`, `jsb_build`, `function`, and `address`. The format is in `engine/testdata/jsb-captures/README.md`.
7. Save the file as `engine/testdata/jsb-captures/<name>.jsonl`.

## Landing a capture

A capture lands in its own PR, before the port PR that uses it. The owner reviews that PR.

The jsb-native parity loop takes a second path. It commits `captures/pl-NNNN.jsonl` to the private jsb-native `main`, and the port PR records that file with `--capture-host jsb-native`. No IBL5 capture PR exists for it. ADR-0278 (`ibl5/docs/decisions/0278-jsb-native-hosted-trace-captures.md`) covers this path.

## Using a capture

Write a test named `TestTracePinned...`. Load the file with `jsbtrace.MustLoad`. For a jsb-native capture, call `jsbtrace.SkipIfAbsent(t, name)` before `jsbtrace.MustLoad`. Feed each `in` to the ported function. Assert the result equals that record's `out`. The expected values live only in the capture.

## Trace-pinned evidence

A port PR that changes the golden snapshot can clear the owner hold with a `TestTracePinned*` test that loads a capture already on the base branch. Record the evidence with one `--ported` list per `--test`:

    cd tools/postplan-harness && python3 -m harness.trace_pinned record --branch <branch> \
        --test ./internal/sim:TestTracePinnedX --capture x.jsonl \
        --ported engine/internal/sim/x.go,engine/internal/result/result.go

For a capture hosted in jsb-native, name the file under its `captures/` directory and add `--capture-host jsb-native`. The check pins the latest jsb-native `main` commit that touched the file:

    cd tools/postplan-harness && python3 -m harness.trace_pinned record --branch <branch> \
        --test ./internal/sim:TestTracePinnedPl0017 --capture pl-0017.jsonl \
        --capture-host jsb-native --ported engine/internal/sim/x.go

A hosted entry adds these hold reasons: `capture-bad-host`, `capture-commit-bad`, `capture-host-unavailable`, `capture-commit-off-main`, and `capture-not-at-commit`.

List every non-test engine `.go` file the PR changes for that routine. The check holds the PR when a changed non-test engine `.go` file is in no list, or when the pinned test executes no added line of a listed file. A comment-only edit, a pure deletion, a rename, and any file under `engine/cmd/` therefore hold for the owner. The reason strings live in `tools/postplan-harness/harness/trace_pinned.py`.

## Whole-day replay

The whole-day recorder in jsb-native captures every `rand()` draw of a sim day together with the calling function, game enter and exit markers, and a league snapshot before each day. The recordings stay in jsb-native, pinned by commit and sha256 (ADR-0279). IBL5 holds none of them.

Run the replay tool by hand from `engine/`:

    go run ./cmd/jsbreplay -plr <ibl5.plr> [-sch <ibl5.sch>] -sco-pre <before.sco> \
        -sco-post <after.sco> -replay <replay.json> -out <dir>

`-sch` defaults to the `.sch` beside `-plr`. The day's games are the records `-sco-post` appends to `-sco-pre`. `-replay` holds `{"format":"jsb-replay/1","games":[[v,...],...]}`, one list of raw values per game in play order. The tool writes `engine_draws.jsonl` (one line per draw with its `caller` and the `fn` from `sim.DrawSites`, plus a final `exhausted` line when the engine wanted more draws than JSB made), `jsb_box.json` and `engine_box.json`. `-count-games` with `-sco-pre` and `-sco-post` prints `{"games":N}`. Exit codes are 0 for success, 2 for usage and 3 for an input error.

The divergence finder compares the two draw logs game by game and names the first index where they differ. It reports one of four kinds. `value-use` means the engine used the draw through `Float64` or `IntN`, so that call site is not ported yet and the next function is the JSB function at that index. `missing-draw` means the engine made fewer draws, so the next function is the JSB function that drew at that index. `extra-draw` means the engine drew where JSB did not, so the next function is the engine caller's JSB function, or the previous JSB function when the caller is unmapped. `box-score` means every draw matched and a box score field differs, so the finder names the game, player and field and no function.

A port reads `Raw()` and copies JSB's arithmetic on the raw value. It never wraps that value in `Float64` or `IntN`, because the replay source logs those as `value-use`.

`sim.DrawSites` takes its address for a function from that function's own doc comment, and only when the comment holds exactly one `FUN_` token. A function that cites two tokens, or none, maps to an empty string. `TestDrawSitesCoverEveryCallSite` fails until the table lists every function that draws.
