# Plan: demo calc helpers

## Context

A small demo change used as a seeded fidelity case.

## Phase 1: Add the helpers

Create `tools/demo/calc.py` with `add` and `double`.

## Phase 2: Test them

Create `tools/demo/tests/test_calc.py` covering both.

## Critical Files

- `tools/demo/calc.py` (NEW)
- `tools/demo/tests/test_calc.py` (NEW)

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | `add` and `double` return the expected values | CLI-executable | post-impl | `python3 -m pytest tools/demo/tests/test_calc.py` |

All verification is automated.
