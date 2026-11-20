---
description: Runbook for capturing per-call (input, output) pairs from the original JSB binary into a jsb-trace/1 JSONL file.
last_verified: 2026-11-17
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

## Using a capture

Write a test named `TestTracePinned...`. Load the file with `jsbtrace.MustLoad`. Feed each `in` to the ported function. Assert the result equals that record's `out`. The expected values live only in the capture.
