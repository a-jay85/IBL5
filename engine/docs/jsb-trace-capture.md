---
description: Runbook for capturing per-call (input, output) pairs from the original JSB binary into a jsb-trace/1 JSONL file.
last_verified: 2026-11-27
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

The jsb-native parity loop writes its capture into an IBL5 worktree named `jsb-capture-<row>`, under `engine/testdata/jsb-captures/`. That worktree ships as an owner-held capture PR (ADR-0266). `port` refuses until the file is on IBL5 `origin/master` with the same sha256. A PR that adds a capture and its evidence together holds as `capture-in-diff`. ADR-0286 (`ibl5/docs/decisions/0286-in-repo-jsb-trace-captures.md`) records this path.

## Using a capture

Write a test named `TestTracePinned...`. Load the file with `jsbtrace.MustLoad`. A missing capture fails the test. Feed each `in` to the ported function. Assert the result equals that record's `out`. The expected values live only in the capture.

## Trace-pinned evidence

A port PR that changes the golden snapshot can clear the owner hold with a `TestTracePinned*` test that loads a capture already on the base branch. Record the evidence with one `--ported` list per `--test`:

    cd tools/postplan-harness && python3 -m harness.trace_pinned record --branch <branch> \
        --test ./internal/sim:TestTracePinnedX --capture x.jsonl \
        --ported engine/internal/sim/x.go,engine/internal/result/result.go

The capture must sit under `engine/testdata/jsb-captures/` on the base branch, with the recorded sha256 and a size of at most 8 MiB. A capture over the cap holds as `capture-too-large`. An entry that names another capture host holds as `capture-bad-host`.

List every non-test engine `.go` file the PR changes for that routine. The check holds the PR when a changed non-test engine `.go` file is in no list, or when the pinned test executes no added line of a listed file. A comment-only edit, a pure deletion, a rename, and any file under `engine/cmd/` therefore hold for the owner. The reason strings live in `tools/postplan-harness/harness/trace_pinned.py`.

### Replay progress for port branches

A `jsb-port-<row>-rN` branch needs one more check before its trace-pinned evidence lifts the hold. At arming, `tools/postplan-harness/harness/replay_progress.py` replays the recorded week on the PR head and on its base. The base replay runs days 1 to 7 and stops at its first mismatching day. The head replay runs days 1 to that day. The hold lifts only when the head matches through that day, or when its first mismatch comes strictly later than the base's. Positions are ordered by day, then game, then draw, then matched draws, and a box-score-only mismatch ranks after every draw mismatch on its day. An equal or earlier position keeps the hold with `replay-no-progress:<row>:<position>`. The check keeps the hold with `replay-unavailable:<why>` when jsb-native or its current recording is missing, the recording changes during the check, `go run ./cmd/jsbreplay` fails, or the 40-minute budget runs out. Each replay writes only to its own temp copy of `parity/state`, so it never touches the loop's replay directory. Results are cached under `~/.cache/ibl5-postplan/replay-progress/`, keyed by the engine tree hash, the recording id and the baseline hash. Reason strings carry the row id and positions only.

## Whole-day replay

The whole-day recorder in jsb-native captures every `rand()` draw of a sim day together with the calling function, game enter and exit markers, and a league snapshot before each day. The recordings stay in jsb-native, pinned by commit and sha256 (ADR-0279). IBL5 holds none of them.

Run the replay tool by hand from `engine/`:

    go run ./cmd/jsbreplay -plr <ibl5.plr> [-sch <ibl5.sch>] -sco-pre <before.sco> \
        -sco-post <after.sco> -replay <replay.json> -out <dir>

`-sch` defaults to the `.sch` beside `-plr`. The day's games are the records `-sco-post` appends to `-sco-pre`. `-replay` holds `{"format":"jsb-replay/1","games":[[v,...],...]}`, one list of raw values per game in play order. The tool writes `engine_draws.jsonl` (one line per draw with its `caller` and the `fn` from `sim.DrawSites`, plus a final `exhausted` line when the engine wanted more draws than JSB made), `jsb_box.json` and `engine_box.json`. `-count-games` with `-sco-pre` and `-sco-post` prints `{"games":N}`. Exit codes are 0 for success, 2 for usage and 3 for an input error.

The divergence finder compares the two draw logs game by game and names the first index where they differ. It reports one of four kinds. `value-use` means the engine used the draw through `Float64` or `IntN`, so that call site is not ported yet and the next function is the JSB function at that index. `missing-draw` means the engine made fewer draws, so the next function is the JSB function that drew at that index. `extra-draw` means the engine drew where JSB did not, so the next function is the engine caller's JSB function, or the previous JSB function when the caller is unmapped. `box-score` means every draw matched and a box score field differs, so the finder names the game, player and field and no function.

A port reads `Raw()` and copies JSB's arithmetic on the raw value. It never wraps that value in `Float64` or `IntN`, because the replay source logs those as `value-use`.

`sim.DrawSites` takes its address for a function from that function's own doc comment, and only when the comment holds exactly one `FUN_` token. A function that cites two tokens, or none, maps to an empty string. `TestDrawSitesCoverEveryCallSite` fails until the table lists every function that draws.

The jsb-native loop reads this tool only through the flags above, and it waits or stops in three cases:

- `begin` idles with `ibl5-replay-unmerged` while the IBL5 checkout has no `engine/cmd/jsbreplay`.
- The recorder raises `game-boundary-unknown` when no candidate game probe enters as many times per day as `-count-games` reports. It never falls back to the first probe.
- `run_day` in `diverge.py` replays one recorded day through this tool and raises when the tool exits nonzero.

On the recorder side, `record_hook.js` flushes every second, well inside the 16 s idle window that ends a Sim Day in `simdays.py`. `tracehost.py` keeps only payloads that carry an `in`, `d` or `mark` key and drops the rest.

## Rows blocked on a richer capture

A capture can be too thin to pin a port. Some records hold only the call's raw `args` and its `rand` draws in `in`, and only `ret` in `out`. Those records carry none of the record values the routine reads or writes. No Go port can be checked against such a capture, so the row stays at `captured` and gets no trace-pinned evidence.

The pl-0027 recapture added the record block that the routine copies out and writes back, and `TestTracePinnedPl0027DayStartPass` in `engine/internal/sim/tracepinned_pl0027_test.go` now pins the Go port in `engine/internal/sim/daystartpass.go`. The Go engine still has no sim-day loop, so no game code calls that port.

Row pl-0009 is blocked on a richer capture. Its shot-type pick is ported in `engine/internal/sim/shotselect.go`, and `TestTracePinnedPl0009Pick` in `engine/internal/sim/tracepinned_pl0009_test.go` pins it against every pick record. The whole-day replay of recording rec-20261123-0904 still first diverges on day 1, game 0, at draw 64, where the engine reads a double in `selectDefender`. The pl-0009 capture records only the shot-type re-pick and pick sites, so it cannot show what the original game draws there. Switching `selectDefender` to an integer draw would move the replay past draw 64 on the draw label alone. The row gets no further port until a recording that names the caller of each draw, or a capture of more draw sites, shows the order.
