---
impl_model: opus
---

# Plan: canned overlay probe

## Phase 1: Probe the gate

**Tier:** self

Run `bin/check-plan` on this file.

## Approach

This plan exists only so the plan-eval validate step can prove the overlaid plan gates run inside a replay clone.

## Critical Files

- `bin/check-plan` (reference) the gate under probe.

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | The gate accepts this plan | CLI-executable | post-impl | `bin/check-plan --help # (one-shot)` |
