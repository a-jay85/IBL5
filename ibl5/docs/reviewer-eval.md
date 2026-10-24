---
description: How the reviewer eval corpus scores the post-plan code reviewer, and the before/after rule for review-surface PRs.
last_verified: 2026-10-24
---

# Reviewer eval

The reviewer eval runs the post-plan code reviewer over past PRs whose outcome is known. It reports how many real bugs the reviewer found and how many false alarms it raised. Decision record: `ibl5/docs/decisions/0201-reviewer-eval-corpus.md`.

## What it measures

The corpus lives in `tools/postplan-harness/fixtures/reviewer-eval/corpus.json`. It holds two kinds of case.

- **Bug cases.** Each names the commit that introduced a bug, the later commit that fixed it, and the line ranges where the bug sits. Only cases with `status: "confirmed"` are scored. Unconfirmed cases are counted and skipped.
- **Clean cases.** Each names a commit that no fix or revert blamed for at least `window_days` days.

`bin/reviewer-eval run` feeds each case's `git diff <commit>^ <commit>` to the unchanged `ReviewPhase` with no plan and a no-op GitHub adapter. Its verifiers read a detached checkout of the case commit, which is removed after the case. It scores the findings that survive the verifier vote. A verifier that errors abstains, and an abstain keeps a finding unverified, so the case counts as degraded.

The run prints a `  raw <agent> <path>:<line> score=<n>` line for every finding before the vote, and the cache keeps them under `scored`. Read those lines to judge a miss by hand.

A bug case counts as a hit when one surviving finding names an accepted file and a line within `LINE_WINDOW` (5) lines of an accepted range, inclusive. The accepted ranges are the case's `expected` ranges plus its optional `also_accept` ranges. A finding at line 0 never counts as a hit. Every surviving finding on a clean case is a false alarm.

`expected` holds the lines the fix changed. `also_accept` holds other lines the introducing PR added where the same defect shows, for example the loop that an unbounded parameter drives. The rule still matches by position only. When two defects share one statement, a range cannot tell a finding about one from a finding about the other.

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
3. When the defect also shows on added lines more than 5 lines from the fix, add an `also_accept` list with those ranges, in the same `{path, start, end}` shape as `expected`. Each range must lie inside lines the introducing commit added. Blame corroboration runs on `expected` only. Leave the key out when there is no second site, since validate rejects an empty list.
4. Strip every `_*` key, write a one-sentence description with no names or emails, and run `bin/reviewer-eval validate`. Validate checks that blame on the fix's removed lines lands inside an expected range, and that every expected and also_accept range lies inside lines the introducing commit added. CI runs it on every PR.

## The review surface

The surface hash `v` covers the files in `REVIEW_SURFACE_PATHS` in `tools/postplan-harness/harness/reviewer_eval.py`, plus the `MODEL_MAP` block of `tools/postplan-harness/harness/adapters/llm.py`. `classify.py` and `schemas.py` are outside it, so an edit there needs no score.

## PR flow for a surface edit

`bin/check-reviewer-eval` runs on every PR in `.github/workflows/pr-meta-checks.yml`. When the surface hash is the same at base and head, it prints `review surface unchanged` and passes.

When the hash changed, run this from the worktree:

```bash
bin/reviewer-eval compare
```

It scores the base tree and the head tree with one shared cache and prints two lines. Paste both into the PR body. The check requires that the before line matches the base hash, the after line matches the head hash, both lines match the head corpus digest, and both show `degraded=0`. It fails when the after score is worse than the before score.

The check is warn-only while the baseline recall in this doc at the base ref is 0 or absent. A finding then prints a `WARNING: not blocking` line and exits 0. The check blocks once that baseline recall is above 0. It reads the base ref so a PR cannot loosen its own check.

Two waivers go in the PR body as HTML comments. Each needs a reason of 15 or more characters.

- `<!-- reviewer-eval-skip: <reason> -->` skips the check.
- `<!-- reviewer-eval-regression-ok: <reason> -->` accepts a worse after score.

## Limits

The check binds a claimed score to the exact surface and corpus content. It cannot prove the run happened.

One run is a sample. LLM output varies between runs. Pass `--runs N` to `run` or `compare` to review each case N times. A bug case then counts as a hit only when every run hits, and a clean case keeps the run with the most findings. Each case line gains `runs_hit=<h>/<N>` or `runs_flagged=<f>/<N>`, plus `FLAKY` when the runs disagree. A `runs=<N> flaky=<k>` line prints before `llm_calls`. The score line keeps its shape, so `bin/check-reviewer-eval` reads it unchanged. Run 1 uses the same cache entry as a plain run, and runs 2 to N get their own `-r<k>` entries. `compare --runs` needs a base that already has the flag.

## Baseline

Recorded 2026-10-21 on review surface `v=2f6c48d10056`, after the Phase 4D verifier replaced the Haiku scorer. The recall is above 0, so `bin/check-reviewer-eval` blocks. Rescored offline on 2026-10-23 after case `trade-preview-unbounded-cash-years` gained an `also_accept` range at line 63. The reviewer did not run again, so every findings count below is the 2026-10-21 count.

```
case depth-chart-log-after-commit bug MISS findings=0 file_only=0
case playoff-series-invalid-group-by bug MISS findings=3 file_only=1
case saved-dc-sum-is-int bug HIT findings=2 file_only=0
case table-dropdown-unencoded-param bug HIT findings=12 file_only=0
case team-page-missing-pick-team bug MISS findings=2 file_only=0
case trade-preview-unbounded-cash-years bug HIT findings=1 file_only=0
case clean-navigation-voting-link clean flagged=1
case clean-perf-logger-injectable clean flagged=4
case clean-quad-double-cte clean flagged=0
case clean-rookie-option-rename clean flagged=1
case clean-season-archive-collector clean flagged=2
case clean-sim-recap-linebreaks clean flagged=0
case clean-standings-pythagorean clean flagged=3
case clean-standings-updater-constant clean flagged=0
case clean-topics-service clean flagged=0
excluded_unconfirmed=1
reviewer-eval v=2f6c48d10056 c=98210c961f08 recall=3/6 false_alarms=11 clean_flagged=5/9 degraded=0
```

A hand check of the raw findings puts the true recall at 2 of 6. The scored 3/6 is two true hits, `saved-dc-sum-is-int` and `trade-preview-unbounded-cash-years`, plus the dropdown case credited by position alone.

- `saved-dc-sum-is-int` is a true hit. Agents A and B both name the `is_int` defect at line 312.
- `table-dropdown-unencoded-param` hits on position alone. The finding at line 66 is a nearby injection in the HTML-escaped base URL. It does not name the missing `encodeURIComponent` on the selected value at line 68. An accepted range cannot correct this over-credit. Both readings point at the same statement, lines 66 to 69, and telling them apart needs a judged text match.
- `trade-preview-unbounded-cash-years` is a true hit. Its one finding names the real defect at line 63, the loop. The fix bounded the GET parameters at lines 39 to 40, outside the window, so the case scored a miss until its `also_accept` range at line 63 was added.
- `depth-chart-log-after-commit` is a real miss. The live post-plan run on PR #3288 also got no finding from agents A, B and security.

The first baseline, on surface `v=16345a91e105` with the Haiku scorer, read a recall of 0 of 6 with no surviving finding on any case. The matching rule did not cause that zero, because no finding reached it. The scorer dropped the agents' findings, including the two line-312 findings that the verifier now keeps.
