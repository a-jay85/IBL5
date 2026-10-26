# Plan: demo greet helper

## Context

A small demo change used as a seeded fidelity case.

## Phase 1: Add the helper

Create `tools/demo/greet.py` with `greet(name)`.

## Phase 2: Test it

Create `tools/demo/tests/test_greet.py` covering the default and a named greeting.

## Critical Files

- `tools/demo/greet.py` (NEW)
- `tools/demo/tests/test_greet.py` (NEW)

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | `greet` returns the default and the named greeting | CLI-executable | post-impl | `python3 -m pytest tools/demo/tests/test_greet.py` |

All verification is automated.
