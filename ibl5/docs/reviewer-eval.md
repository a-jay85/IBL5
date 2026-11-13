---
description: How the reviewer eval corpus scores the post-plan code reviewer, how /fix-and-prevent grows the corpus, the before/after rule for review-surface PRs, and the reviewer bar that gates security fixes.
last_verified: 2026-11-12
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

New bug cases come from `/fix-and-prevent`. Its review-layer step, step 2a of `.claude/skills/fix-and-prevent/_remediation.md`, names the layer that should have caught the bug. When that layer is `phase-4-review` and the introducing PR carries a `## Code review` comment, the prevention backlog item carries a pending case with the introducing PR, its commit, the expected range, and a description. Append it after the fix PR merges, following steps 2 to 4 above. Validate needs the fix's squash commit on master, so the fix PR itself cannot carry the case. The miner in step 1 stays the way to backfill older pairs.

Each new confirmed case also changes `test_real_corpus_expected_ranges_pinned` in `tools/postplan-harness/tests/test_reviewer_eval.py`. Update its confirmed-case count and its `PINNED_EXPECTED` map in the same change.

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

## The bar

The post-plan fix round lets the fixer touch a security-audit finding only while the reviewer meets the owner's bar. Below the bar the finding stays out of the round and holds arming condition (2), so the owner fixes it. Decision record: `ibl5/docs/decisions/0232-reviewer-bar-security-fixes.md`.

The bar passes when all four hold:

- the corpus has at least 30 confirmed cases, and the score covers every one of them
- recall is at least 60%
- at most 20% of clean cases are flagged, and the corpus has at least one clean case
- no agent degraded

The checks use integer arithmetic, so 18/30 recall and 2/10 clean flagged both pass.

`bin/reviewer-eval run --write-record` records a score. It writes `tools/postplan-harness/out/state/reviewer-bar.json` in the main checkout, a gitignored directory every worktree reads. The record holds `v`, `c`, the score line, and the run-1 cache entry for each case. `--record-path FILE` writes elsewhere. The command refuses `--canned`, `--cases`, `--kinds`, `--no-cache` and `--dry-run`. With fewer than 30 confirmed cases it prints `reviewer-bar: record NOT written (too-few-cases: <n> < 30)` and exits 1 before any LLM call, so it costs nothing until the corpus is big enough. A degraded run writes nothing and keeps exit 3.

`bin/reviewer-eval bar` prints `reviewer-bar: PASS <reason>` and exits 0, or `reviewer-bar: FAIL <reason>` and exits 1. It never calls an LLM. It fails on too few cases, a missing or unreadable record, a stale `v` (the reviewer surface changed) or `c` (the corpus changed), a score line the stored entries do not reproduce, or any error.

Nothing runs the eval on a schedule. The owner or a Claude session runs `bin/reviewer-eval run --write-record` from the main checkout after a reviewer or corpus change lands on `master`.

## Limits

The check binds a claimed score to the exact surface and corpus content. It cannot prove the run happened.

One run is a sample. LLM output varies between runs. Pass `--runs N` to `run` or `compare` to review each case N times. A bug case then counts as a hit only when every run hits, and a clean case keeps the run with the most findings. Each case line gains `runs_hit=<h>/<N>` or `runs_flagged=<f>/<N>`, plus `FLAKY` when the runs disagree. A `runs=<N> flaky=<k>` line prints before `llm_calls`. The score line keeps its shape, so `bin/check-reviewer-eval` reads it unchanged. Run 1 uses the same cache entry as a plain run, and runs 2 to N get their own `-r<k>` entries. `compare --runs` needs a base that already has the flag.

## Opus verifier (2026-10-27)

At both review depths, the Phase 4D verifier now runs on Opus. It ran on Sonnet before. The seat count stayed at 3. Each side was scored once.

```
Reviewer eval before: reviewer-eval v=247f8fcb9b0f c=98210c961f08 recall=2/6 false_alarms=0 clean_flagged=0/9 degraded=0
Reviewer eval after: reviewer-eval v=05b22ea13c35 c=98210c961f08 recall=2/6 false_alarms=0 clean_flagged=0/9 degraded=0
```

## Fold B into A trial (2026-10-25)

Agent B (git history and code comments) was folded into Agent A as Sections 4 and 5, each behind B's old sub-gate. Each side was scored three times. A bug case counted as a hit only when all three runs hit it, and `false_alarms` took the worst run. The fold scored no worse under the `is_worse` rule, so it shipped.

aggregate before: hits=2/6 false_alarms=0 clean_flagged=0/9
aggregate after: hits=2/6 false_alarms=0 clean_flagged=0/9

Flipped cases: none. `saved-dc-sum-is-int` stayed a hit (HIT -> HIT). Unstable cases: `team-page-missing-pick-team` before (2 of 3 runs hit), `depth-chart-log-after-commit` after (1 of 3 runs hit).

```
run 1
Reviewer eval before: reviewer-eval v=c2c7dbf3da13 c=98210c961f08 recall=2/6 false_alarms=0 clean_flagged=0/9 degraded=0
Reviewer eval after: reviewer-eval v=f44dd1a41403 c=98210c961f08 recall=2/6 false_alarms=0 clean_flagged=0/9 degraded=0
```

## Baseline

Recorded 2026-10-21 on review surface `v=2f6c48d10056`, after the Phase 4D verifier replaced the Haiku scorer. The recall is above 0, so `bin/check-reviewer-eval` blocks. Rescored offline on 2026-10-23 after case `trade-preview-unbounded-cash-years` gained an `also_accept` range at line 63. The reviewer did not run again, so every findings count below is the 2026-10-21 count.

Cases added to the corpus after 2026-10-21 are not in this baseline. The reviewer is not rerun when a case lands, so the `c=` digest and the `/6` denominator below describe the corpus as it was on that date. `bin/check-reviewer-eval` reads only the recall hits from the score line, so a new case does not change what it enforces.

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

## Second-reviewer eval

The second-reviewer eval replays the `pr-second-reviewer` seat on past held PRs whose owner outcome is known. It reports how often the seat cleared a PR the owner held and how often it held a PR the owner merged as it was. Decision record: `ibl5/docs/decisions/0247-second-reviewer-eval-corpus.md`. The code lives in `tools/postplan-harness/harness/second_review_eval.py`.

### Subcommands

Each one runs as `bin/reviewer-eval second <name>`.

| Name | What it does | Exit codes |
|------|--------------|------------|
| `mine` | Reads merged PRs since `--since` (default 2026-10-13) through read-only `gh` calls and git, and writes one row per PR to `tools/postplan-harness/out/second-review-eval/candidates.jsonl`. | 0 written, 1 on a `gh` failure |
| `validate` | Checks the corpus schema and prints `ok cases=<n> confirmed=<k> c=<digest>`. | 0 valid, 1 schema errors, 2 unreadable corpus |
| `run` | Runs the seat on each confirmed case, or only the `--case` ids given, and prints the score line. `--score-out` also writes the line to a file. | 0 scored, 2 no confirmed case, 3 a case was degraded |
| `compare` | Compares two score files given with `--before` and `--after`. | 0 no worse, 1 worse, 2 the corpus digest or a denominator differs |
| `smoke` | Runs the real seat path with a fake model on a synthetic case. It spends no tokens. | 0 pass, 1 fail |

The miner only reads. A guard in the live `gh` adapter raises on any argv outside `pr list`, `pr view`, a plain `api repos/a-jay85/IBL5/...` read, or a `graphql` query. The candidates file and the verdict cache sit under the gitignored `tools/postplan-harness/out/`.

### Corpus

`tools/postplan-harness/fixtures/second-review-eval/corpus.json` holds `{"version": 1, "cases": [...]}`. Each case carries `id`, `pr`, `status`, `expected`, `label_basis`, `base_sha`, `replay_sha`, `holds`, `reason`, `reason_source`, and `evidence`. The `holds` map uses only the clearable conditions 7, 10, 12 and 17. An id starts with a letter, so it can never equal a bare PR number.

The miner picks a case's holds from the `Auto-merge held:` block of the post-plan sticky comment. It reads every saved version of the comment and takes the newest one whose holds are all clearable. The replay commit is the PR commit whose tree matches that version's `**Reviewed tree:**` line. A PR whose sticky also held a non-clearable condition, whose reviewed tree left the branch history, or whose diff is over the inline cap gets `seat_eligible: false` and stays out of the corpus.

A person copies each eligible row into the corpus and sets `status`. A case is `confirmed` when its `evidence` names the label or the blamed fix. A `changed-after-hold` case also needs a commit after the replay commit that touches a file in the diff, named in `evidence`. Every other case is `unconfirmed`, and only confirmed cases enter the score and the corpus digest.

### Labels

The miner labels each PR from what the owner did. The first matching rule wins.

1. A fix within 14 days whose removed lines blame the PR's merge commit gives `hold` with basis `reverted`.
2. A `needs-a-human` label gives `hold` with basis `needs-a-human`.
3. A `human-approved` label with a changes-requested review, or with a patch id that changed after the hold, gives `hold` with basis `changed-after-hold`.
4. A `human-approved` label with an unchanged patch id gives `clear` with basis `approved-as-is`. Patch ids ignore a rebase-only push.
5. Any other kept PR was cleared by the seat and auto-merged, and gives `clear` with basis `seat-cleared-clean`.

No merged sticky carries a `Second reviewer cleared` digest line yet, so the miner finds no rule-5 case today.

### Score

`run` prints one line:

```
second-review-eval v=<surface> c=<corpus> missed_holds=a/b extra_holds=c/d degraded=n
```

`v=` hashes the seat code, the agent definition, and the `REMEDIATION_DIFF_INLINE_CAP` line of `fidelity.py`. `c=` hashes the confirmed cases. `missed_holds=a/b` counts `hold` cases the seat cleared. `extra_holds=c/d` counts `clear` cases the seat held. The two counts are never summed, because a missed hold lets a bad PR merge and an extra hold only costs the owner a look. A `CLEAN` verdict that clears only some of a case's holds counts as held, since arming would still wait. A degraded case (a seat error or no verdict) drops out of both denominators and adds 1 to `degraded=`.

The seat writes each verdict under the key `eval-<id>`, at `/tmp/postplan-second-review-eval-<id>.md`, so a run never touches the verdict file arming reads for a real PR. The run deletes that file afterward. Verdicts are cached in `tools/postplan-harness/out/second-review-eval/cache/`, keyed on the case and the surface hash, so a rerun on an unchanged surface makes no seat call.

Nothing gates on this score. It is a measurement for PRs that change the seat. The first run, on 2026-11-12 over cases `pr3899-hold7` and `pr3820-clear7`, printed `second-review-eval v=7e835ad672d7 c=bb8984c77d24 missed_holds=0/1 extra_holds=1/1 degraded=0`.
