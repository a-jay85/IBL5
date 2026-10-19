---
description: How the reviewer eval corpus scores the post-plan code reviewer, and the before/after rule for review-surface PRs.
last_verified: 2026-10-19
---

# Reviewer eval

The reviewer eval runs the post-plan code reviewer over past PRs whose outcome is known. It reports how many real bugs the reviewer found and how many false alarms it raised. Decision record: `ibl5/docs/decisions/0201-reviewer-eval-corpus.md`.

## What it measures

The corpus lives in `tools/postplan-harness/fixtures/reviewer-eval/corpus.json`. It holds two kinds of case.

- **Bug cases.** Each names the commit that introduced a bug, the later commit that fixed it, and the line ranges where the bug sits. Only cases with `status: "confirmed"` are scored. Unconfirmed cases are counted and skipped.
- **Clean cases.** Each names a commit that no fix or revert blamed for at least `window_days` days.

`bin/reviewer-eval run` feeds each case's `git diff <commit>^ <commit>` to the unchanged `ReviewPhase` with no plan and a no-op GitHub adapter. It scores the findings that survive the reviewer's own threshold.

A bug case counts as a hit when one surviving finding names an expected file and a line within `LINE_WINDOW` (5) lines of an expected range, inclusive. A finding at line 0 never counts as a hit. Every surviving finding on a clean case is a false alarm.

## Score line

Each run ends with one line of this shape: `reviewer-eval v=<12 hex> c=<12 hex> recall=<hits>/<scored> false_alarms=<n> clean_flagged=<a>/<b> degraded=<d>`.

- `v` is the review-surface hash.
- `c` is the corpus digest.
- `recall` is confirmed bug cases hit over confirmed bug cases scored.
- `false_alarms` is the total surviving findings on clean cases.
- `clean_flagged` is clean cases with at least one finding over clean cases scored.
- `degraded` is the count of cases where a review agent failed. A run with `degraded` above 0 exits 3. Rerun the same command and the cache serves the cases that already finished.

## Adding a case

1. Mine candidates into the gitignored out dir:
   ```bash
   bin/reviewer-eval mine bugs --since 2026-05-01 --limit 40 > tools/postplan-harness/out/reviewer-eval/cand-bugs.jsonl
   bin/reviewer-eval mine clean --window-days 60 --limit 25 > tools/postplan-harness/out/reviewer-eval/cand-clean.jsonl
   ```
2. Read the fix and the introducing diff for each bug candidate. Mark it `confirmed` only when all three tests hold:
   - The fix corrects a defect such as a wrong value, a crash, a missing guard, or a wrong query or branch. A refactor, rename, or doc change does not count.
   - The lines the fix removed or changed are lines the introducing PR added, and they are the defect.
   - The defect is visible in the introducing diff alone.
   A pair that fails one test goes in as `unconfirmed`, with the failed test named in its description.
3. Strip every `_*` key, write a one-sentence description with no names or emails, and run `bin/reviewer-eval validate`. Validate checks that blame on the fix's removed lines lands inside the expected range. CI runs it on every PR.

## The review surface

The surface hash `v` covers the files in `REVIEW_SURFACE_PATHS` in `tools/postplan-harness/harness/reviewer_eval.py`, plus the `MODEL_MAP` block of `tools/postplan-harness/harness/adapters/llm.py`. `classify.py` and `schemas.py` are outside it, so an edit there needs no score.

## PR flow for a surface edit

`bin/check-reviewer-eval` runs on every PR in `.github/workflows/pr-meta-checks.yml`. When the surface hash is the same at base and head, it prints `review surface unchanged` and passes.

When the hash changed, run this from the worktree:

```bash
bin/reviewer-eval compare
```

It scores the base tree and the head tree with one shared cache and prints two lines. Paste both into the PR body. The check requires that the before line matches the base hash, the after line matches the head hash and the head corpus digest, and the after line shows `degraded=0`. It fails when the after score is worse than the before score.

Two waivers go in the PR body as HTML comments. Each needs a reason of 15 or more characters.

- `<!-- reviewer-eval-skip: <reason> -->` skips the check.
- `<!-- reviewer-eval-regression-ok: <reason> -->` accepts a worse after score.

## Limits

The check binds a claimed score to the exact surface and corpus content. It cannot prove the run happened.

One run is a sample. LLM output varies between runs. Rerun a borderline comparison with `--no-cache` before you trust a one-case difference.

## Baseline

Recorded 2026-10-19 on review surface `v=16345a91e105`. The dry run estimated 62 model calls, and the live run made 45.

No finding survived the reviewer's threshold on any case. A spot check of `saved-dc-sum-is-int` shows agents A and B both naming the defect at line 312, and the scorer gave each finding 75. `CODE_THRESHOLD` in `tools/postplan-harness/harness/review.py` is 80, so both were dropped. The zero recall measures the scorer and the threshold together.

```
case depth-chart-log-after-commit bug MISS findings=0 file_only=0
case playoff-series-invalid-group-by bug MISS findings=0 file_only=0
case saved-dc-sum-is-int bug MISS findings=0 file_only=0
case table-dropdown-unencoded-param bug MISS findings=0 file_only=0
case team-page-missing-pick-team bug MISS findings=0 file_only=0
case trade-preview-unbounded-cash-years bug MISS findings=0 file_only=0
case clean-navigation-voting-link clean flagged=0
case clean-perf-logger-injectable clean flagged=0
case clean-quad-double-cte clean flagged=0
case clean-rookie-option-rename clean flagged=0
case clean-season-archive-collector clean flagged=0
case clean-sim-recap-linebreaks clean flagged=0
case clean-standings-pythagorean clean flagged=0
case clean-standings-updater-constant clean flagged=0
case clean-topics-service clean flagged=0
excluded_unconfirmed=1
llm_calls=45
reviewer-eval v=16345a91e105 c=89cdd47be2df recall=0/6 false_alarms=0 clean_flagged=0/9 degraded=0
```
