# Plan: add ibl_demo.note

## Context

A small demo change used as a seeded fidelity case.

## Phase 1: Add the migration

Create `ibl5/migrations/902_demo_add.sql` that adds a nullable `note` column to `ibl_demo`.

## Critical Files

- `ibl5/migrations/902_demo_add.sql` (NEW)

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | The migration file exists and adds the column | CLI-executable | post-impl | `ibl5/migrations/902_demo_add.sql` |

All verification is automated.
