# Plan: demo empty-value validation

## Context

A small demo change used as a seeded fidelity case.

## Phase 1: Add the validator

Create `tools/demo/validate.py` with `reject_empty(value)`.

## Phase 2: Cover it with a test

Create `tools/demo/tests/test_helper.py` with `test_rejects_empty`.

## Critical Files

- `tools/demo/validate.py` (NEW)
- `tools/demo/tests/test_helper.py` (NEW)

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | `reject_empty` raises on empty and blank input; assertion `test_rejects_empty` | CLI-executable | post-impl | `python3 -m pytest tools/demo/tests/test_helper.py -k test_rejects_empty` |

All verification is automated.
