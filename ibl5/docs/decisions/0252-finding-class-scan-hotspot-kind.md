---
description: The weekly finding-class scan files a bug issue for a none class whose review threads cluster on one file.
last_verified: 2026-11-13
---

# ADR-0252: Finding-class scan files single-file none classes as hotspot bugs

**Status:** Accepted
**Date:** 2026-11-13

## Context

The weekly finding-class scan (ADR-0205) sends most classified threads to `check_kind: none`. A `none` class is counted in the run summary and DM, and it is never filed. Several `none` classes are one file flagged again and again. On the 2026-11-10 corpus, `check-plan-cw-gate-regex-gaps` has 13 of 13 threads on one file, `body-numbers-regex-rewrite-corrupts-text` has 11 of 11, and `conflict-report-false-all-clear-or-loss-hidden` has 7 of 7. Nobody could build a static check for these, so the findings stayed in the none count while the file kept getting the same defect.

## Decision

`count_classes` in `bin/finding-class-scan` derives a `hotspot` kind from the per-thread `path` of each `none` class. A class becomes a hotspot when at least 3 of its distinct threads, and at least 80% of them, sit on one file. The two thresholds are the constants `HOTSPOT_MIN_THREADS` and `HOTSPOT_MIN_SHARE_PCT`. A hotspot files under the `bug` label with a fix-plus-regression-test recipe that names the file (`render_hotspot_body`).

The classifier enum stays `phpstan`, `bin-check` and `none`. A classifier that emits `hotspot` is rejected by `VALIDATE_JQ`. The derivation runs on every scan and reads the thread rows at count time, so no registry rewrite happens and old threads count like new ones. `plan_actions` files a hotspot only when this run derived `hotspot_path`, so a registry entry that says `hotspot` stays skipped.

This amends the ADR-0205 sentence saying a `none` class is never filed. A `none` class clustered on one file is now filed. Every other `none` class keeps the old treatment.

## Alternatives Considered

- Classifier-assigned fourth kind: add `hotspot` to the classifier enum and re-classify the registry once. Rejected because: it costs tokens on every run, needs a reversible backfill step, and asks a model to count files that the thread rows already carry.
- Cap on distinct files: file a class that spans at most N files. Rejected because: it misjudges large classes. `keyword-regex-incomplete-negation-or-word-forms` has 12 threads with 10 on one file, and a 2-file cap would reject it.
- Environment variables for the thresholds: let a caller tune them per run. Rejected because: no caller needs it, and each variable adds an unset-and-empty contract to test.

## Consequences

- Positive: the replay of a copy of the real state dir flipped 29 classes from `skipped-none` to filed bugs. The none count went from 98 to 69.
- Positive: a cross-file class still files nothing. `bin-test-false-green-assertion` sits at 3 of 20 threads on one file (15%) and `claim-before-fallible-step` at 5 of 9 (56%). Both stay `none`.
- Negative: the first real run files every currently qualifying class at once, 29 on the 2026-11-10 corpus. Later runs file only newly qualifying classes.
- Tuning lives in the two constants in `bin/finding-class-scan`.

## References

- `bin/finding-class-scan`
- `bin/test-finding-class-scan`
- `ibl5/docs/decisions/0205-weekly-finding-class-scan.md`
