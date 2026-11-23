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
