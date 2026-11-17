---
description: Diff-scoped gate bin/check-exit-code-handling with three rules (git-procsub, git-capture-unchecked, teardown-then-rm) and a CI split between the harness and the PR-diff run.
last_verified: 2026-11-16
---

# ADR-0258: Exit-code handling gate

**Status:** Accepted
**Date:** 2026-11-16
**Deciders:** Repo owner (backlog#1644)

## Context

Eight review threads across PRs 3267, 3382, 3383 and 3395 reduce to four shapes. Shape A hides or skips the exit status of a git capture. Shape B writes `cmd "$(...)" || fallback`. Shape C runs a teardown and then removes a marker with `;`, so a failed teardown still drops the marker. Shape D lumps exit codes in `bin/lib/plan-exec-mutations`. Shape D and the cited A and C instances are fixed on master. `.claude/rules/shell-fail-open-probe.md` already describes the class, and it recurred anyway.

## Decision

Add `bin/check-exit-code-handling`, a gate that reads only the lines a branch adds (`--since=<ref>`). It has three rules: `git-procsub`, `git-capture-unchecked` and `teardown-then-rm`. A line can opt out with `# exit-ok: <reason of 15 or more characters>`. The `--all` flag scans every tracked eligible line and exists for corpus runs.

The harness `bin/test-check-exit-code-handling` runs in the `static-guards` job of `.github/workflows/tests.yml`. The PR-diff run lives in `.github/workflows/pr-meta-checks.yml`, next to `bin/check-pipefail-grep-q`. The `static-guards` checkout is shallow and the job also runs on push, so a merge-base gate there would exit 2. `pr-meta-checks.yml` checks out with `fetch-depth: 0` and exports `BASE_SHA`.

I added a new gate instead of extending `bin/check-pipefail-grep-q`, and all four add-new conditions in `.claude/rules/meta-tooling-bar.md` hold. The sibling arms on a file that sets pipefail, scans `bin/` only and has no escape marker or lookahead. This gate needs errexit eligibility, skill markdown fences, a 3-line lookahead and `# exit-ok:`, so a bolted-on branch would split the sibling's single trigger in two. The trigger here is exit-status handling, and the surface recurs. A rule doc alone already failed to stop the class.

## Alternatives Considered

- Extend `bin/check-pipefail-grep-q`. Rejected because its eligibility keys on pipefail and a bolted-on branch would split its single trigger.
- Whole-tree gate. Rejected because about 1014 status-save sites exist and the corpus holds 15 live hits, so it would block every PR.
- A rule for shape B. Rejected because the `||` tests the outer command, and a line regex cannot tell it from a deliberate fallback. A scan found that a plain `x=$(cmd) || die` capture would be flagged too.
- A rule for timeout exit-code lumping. Rejected because the real bug, a non-zero rc counted as positive evidence, cannot be matched by grep. The 5 files with no 124 or 125-127 mention are all false positives.
- A rule for a status save after echo, printf, log, test, cd or local. Rejected because the scan found 0 hits.
- A doc-only fix. Rejected because the rule doc already exists and the bug class recurred.

## Consequences

- Positive: new instances of the three shapes fail the PR with the file and line.
- Positive: the escape marker keeps the reason on the flagged line, so it moves with the code.
- Negative: the matcher reads text and does not parse quotes. A quoted `;`, `|`, `&` or `>` can end a match early, so such a line is missed and never falsely flagged.
- Negative: the capture rule trusts any errexit `set` line in the file. Errexit is off inside a function called from `if`, `||` or `&&`, so that hole stays open. A skill markdown fence never counts as errexit.
- Negative: `|| true` counts as a check, so a git diff capture followed by `|| true` passes.

The `--all` run lists 15 live hits. This PR leaves them unedited, and each is a known follow-up the gate does not block:

- `bin/docfix-check-veronly` lines 200, 279, 344 and 367 (process-substitution loop inputs).
- `bin/postplan-fix` lines 413 (inside a heredoc prompt that a model runs), 603, 668, 672, 1032 and 1037 (process-substitution loop inputs).
- `bin/wt-status` lines 101 and 109 (process-substitution loop inputs).
- `bin/test-wt-gc-tick` lines 524, 529 and 534 (git show into a file in a harness that sets only `set -u`).

Two sites the issue scan listed are not hits. `bin/automouse/prompt-impl:374` holds an unchecked capture, but the file is prompt text with no shell shebang, so it is not an eligible surface. `bin/test-check-infection-ran:138` matched the capture regex because an echoed message contains the words git, diff and a redirect. It is not a git call, so this PR marks it `# exit-ok:`.

## References

- `bin/check-exit-code-handling`
- `bin/test-check-exit-code-handling`
- `bin/check-pipefail-grep-q`
- `.github/workflows/tests.yml`
- `.github/workflows/pr-meta-checks.yml`
- `.claude/rules/meta-tooling-bar.md`
- `.claude/rules/shell-fail-open-probe.md`
