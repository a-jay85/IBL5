# Plan: demo normalize helper

## Context

A small demo change used as a seeded fidelity case.

## Phase 1: Add the helper module

Create `tools/demo/helper.py` with a `normalize(name)` function.

## Phase 2: Wire it into the CLI

Edit `tools/demo/main.py` so `run` calls `helper.normalize` on the first argument.

## Critical Files

- `tools/demo/helper.py` (NEW)
- `tools/demo/main.py` (modify)

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | The CLI lowercases and strips its first argument | CLI-executable | post-impl | `python3 tools/demo/main.py ' Bob '` prints `hello bob` |

All verification is automated.
