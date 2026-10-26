# Plan: drop ibl_demo.legacy_flag

## Context

A small demo change used as a seeded fidelity case.

## Phase 1: Add the migration

Create `ibl5/migrations/901_demo_drop.sql` that drops `ibl_demo.legacy_flag`.

## Critical Files

- `ibl5/migrations/901_demo_drop.sql` (NEW)

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | The migration file exists and drops the column | CLI-executable | post-impl | `ibl5/migrations/901_demo_drop.sql` |

All verification is automated.
