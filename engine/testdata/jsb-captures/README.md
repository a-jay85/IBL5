# JSB trace captures

This directory holds raw per-call captures taken from the original JSB binary. A trace-pinned test loads one through `jsbtrace.MustLoad` and checks a ported engine function against it.

## Format

A capture is a file named `<name>.jsonl`. The loader reads only `*.jsonl` files.

Line 1 is a JSON header object with these keys:

- `format`: exactly `"jsb-trace/1"`.
- `source`: `"frida"` or `"x32dbg"`.
- `jsb_build`: a non-empty string naming the JSB build.
- `function`: a non-empty string naming the JSB routine.
- `address`: a string matching `^0x[0-9a-f]+$`.

Every later non-empty line is one record object with the keys `in` and `out`. Each is a JSON object. A capture holds at least one record.

A hook on the original JSB binary produces the file. The Go engine never writes one. See `engine/docs/jsb-trace-capture.md` for the runbook.

## Override

The env var `JSB_TRACE_CAPTURE_DIR` replaces this directory when it is non-empty. Empty means this directory.

A test whose capture is hosted in jsb-native calls `jsbtrace.SkipIfAbsent` first. With the env var unset or empty and no local copy, the test skips and names the reason.

## Review

A capture committed to this directory lands in its own PR. Phase 6.5 always holds that PR for the owner (Phase 4 condition 5 detection).

The parity loop keeps its captures in the private jsb-native repo under `captures/`. No IBL5 PR adds one, so no capture PR exists to hold. The port PR's evidence pins the jsb-native commit and the file's sha256, and `tools/postplan-harness/harness/trace_pinned.py` checks both against that repo's `main` before it clears the hold. The owner chose on 2026-11-22 to skip trace review for this path. The loop messages the owner only when it is blocked.
