# Plan: demo shout helper

## Context

A small demo change used as a seeded fidelity case.

## Phase 1: Add the helper

Create `tools/demo/shout.py` with `shout(text)`.

## Phase 2: Update the old test only if needed

If `shout` changes the CLI output, update `tools/demo/tests/test_cli_output.py`; the spec leaves this open.

## Critical Files

- `tools/demo/shout.py` (NEW)
- `tools/demo/tests/test_cli_output.py` (conditional)

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | `shout` upper-cases its input | CLI-executable | post-impl | `python3 tools/demo/shout.py abc` prints `ABC` |

All verification is automated.
