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

A missing capture fails the test. No test skips for an absent file.

## Older copies

Four files end in a jsb-native commit id: `pl-0010-d322a86.jsonl`, `pl-0025-90b5298.jsonl`, `pl-0027-3c70714.jsonl` and `pl-0028-2ba5afc.jsonl`. Each holds the bytes that an older evidence file under `engine/trace-evidence/` pins. The tests load the plain names, such as `pl-0010.jsonl`. `trace_pinned` stages each older copy under its plain name, so the normal run and the perturbed run both read the bytes the entry pins.

All seven evidence files under `engine/trace-evidence/` are records of merged port branches. Each one names a `test_blob` from an earlier version of its test file, so `trace_pinned` holds every one of them at the test-blob step.

## Review

A capture committed to this directory lands in its own PR. Phase 6.5 always holds that PR for the owner (Phase 4 condition 5 detection).

The parity loop writes each capture into an IBL5 worktree named `jsb-capture-<row>`, under this directory. Each capture PR holds for the owner (ADR-0266). The port PR records its evidence only after the capture is on `origin/master`.

A file named `pl-NNNN-<7-hex commit>.jsonl` keeps the exact bytes an older evidence entry pinned, while its test loads `pl-NNNN.jsonl`. `trace_pinned` copies that file's base-branch bytes into a temp dir under the name the test loads. It then runs the test and the perturbed run against that copy.

Each file is at most 8 MiB (8388608 bytes). `tools/postplan-harness/harness/trace_pinned.py` holds a larger one as `capture-too-large`. `.gitattributes` marks `*.jsonl` here as `binary`, so git stores the bytes as written and keeps them out of review diffs. ADR-0286 records the decision.
