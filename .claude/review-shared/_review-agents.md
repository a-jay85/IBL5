---
description: Shared code-review agent definitions used by /pr-review and /post-plan.
last_verified: 2026-10-25
---

# Code Review Agents (shared definitions)

Source of truth for code-review agent prompts. Used by `/pr-review` Step 3, and `/post-plan` Phase 4B. Do not edit without updating both callers.

## Token-efficiency design

Agents are merged by model tier to minimize context overhead (~5K tokens per agent spawn). Two agents instead of six:

- **Agent A (Sonnet 5.5):** Architecture, bug detection, DB performance, git history and code comments. All require semantic judgment over the same diff.
- **Agent C (Haiku):** Previous PRs — mechanical lookup, no judgment

## Common preamble (all agents)

Each agent receives: filtered PR diff, file list, and PR metadata from the parent command. **No agent should call `gh pr diff`** — the diff is already fetched by the parent. **Do not forward CLAUDE.md content in the prompt** — agents auto-load CLAUDE.md on init.

**Assume PHPStan `level: max` + `phpstan-strict-rules` + the custom rules listed in `_review-rubric.md` are satisfied.** Any finding those rules would catch is out of scope — they run in PostToolUse and CI, so a merged PR cannot violate them.

**Playwright `.spec.ts` files are lint-enforced by `bun run lint:e2e` (CI job `ESLint (e2e specs)`).** Reviewers should not duplicate `playwright/*` or `@typescript-eslint/*` rule checks — the preset already covers missing `await`, `{ force: true }`, `waitForTimeout`, `networkidle`, web-first assertions, etc. Semantic E2E spec review (POST-effect verification, assertion discrimination, coverage-branch check) is handled by **Agent D**, defined in `.claude/review-shared/_test-spec-agent.md` and launched from `/post-plan` Phase 4B. Do not duplicate its checks here.

---

## Per-finding fields (every review agent)

End every finding with one metadata line, exactly this shape:

`severity: important|nit · pre-existing: yes|no · bug class: <kebab-case> · other instances: <path:line, path:line> | none`

- `severity`: `nit` only for a real but low-impact issue (naming, a comment, a micro-clarity fix). The scorer decides the final label; a `nit` hint can never demote a finding the scorer rates at or above threshold.
- `pre-existing: yes` when the defect was already on the line before this PR (the line is unchanged context in the diff and the diff did not introduce the bug). Report such defects; do not drop them. They post as a non-holding `Pre-existing` note so the author is not blamed and the bug is not lost.
- `bug class`: a short name for the defect class (`unbounded-fetch`, `missing-null-guard`, `unescaped-output`). Reuse a class name from `ibl5/docs/retrospective-class-registry.md` when one fits.
- `other instances`: every other place in the diff with the same bug class, plus any you can see in the diff's context windows of the touched files. One review round should fix all copies. Write `none` when there are none.

## Agent A: Architecture + Bug Detection + DB Performance + History + Code Comments (Sonnet 5.5, `subagent_type: "sonnet-5-5"`, omit `model`)

You are a **Senior PHP Architect and Staff Engineer** reviewing for architectural fitness, correctness bugs, database performance, regression risk from git history, and compliance with in-code guidance. Complete every section the caller enables. Sections 1 to 3 run unless the caller skips them, and Sections 4 and 5 run only when the caller's launch gate turns them on. Return a numbered evidence summary for each enabled section, even if it finds no issues.

### Section 1: Architectural fitness

**Domain vocabulary:** Repository/Service/View separation, Contracts interfaces, dependency direction, aggregate boundaries, single-responsibility modules, controller-as-thin-adapter, prepared-statement repository pattern, `BaseMysqliRepository` query isolation.

**Focus (judgment calls a linter cannot make):**

1. Does new code respect the Repository / Service / View architectural split?
2. Are SQL string literals consistent with `ibl5/migrations/000_baseline_schema.sql` (column/table names, types)?
3. Are native-type comparisons (`=== 0` vs `=== '0'`) correct for the actual column type?
4. Does refactoring preserve behavior documented in tests?
5. Is the PR scope clean (no unrelated files, no drive-by changes)?

All items in the Automatic Zero list from `_review-rubric.md` are enforced by PHPStan — do not re-check them.

### Section 2: Bug detection

Only flag bugs that would cause incorrect behavior in production: wrong results, data corruption, crashes, or silent failures. Skip stylistic issues, unlikely edge cases, and anything a linter or type checker would catch.

**Domain vocabulary:** `strict_types` coercion boundaries, `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` type mapping, prepared-statement `bind_param` type characters (`s`/`i`/`d`/`b`), `transactional()` isolation, `fetchOne`/`fetchAll` result shapes, `BaseMysqliRepository` query patterns.

**Named anti-patterns (IBL5-specific):**

| Anti-pattern | Detection signal |
|---|---|
| `bind_param` type-char swap | Type string length ≠ parameter count, or `'s'` used for an INT column / `'i'` for a VARCHAR column |
| Native-type mismatch | `=== '0'` on an INT column (`tid`, `retired`, `hasMLE`) or `=== 0` on a VARCHAR column |
| Contract-year field confusion | Reading `cy1` when the `cy` value could be `2` (should read `cy2`) |
| DNP row miscounting | `COUNT(*)` on `ibl_box_scores` without a `gameMIN > 0` filter |
| Transaction isolation gap | Multiple `sql_query()` / repository calls that modify related rows without `transactional()` wrapper |
| Free-agent guard | `tid` compared as string (`=== '0'`) instead of `=== 0` (int) |

### Section 3: Database performance

**Domain vocabulary:** query execution plans, index selectivity, full table scans, N+1 query patterns, unbounded result sets, covering indexes, composite index column order, `EXPLAIN` analysis, connection overhead, `ORDER BY` on non-indexed columns.

**Named anti-patterns:**

| Anti-pattern | Detection signal |
|---|---|
| Unbounded `fetchAll()` | `fetchAll()` on a table known to grow (`ibl_box_scores`, `ibl_players`, `ibl_transactions`) without `LIMIT` or `WHERE` constraining result size |
| N+1 query loop | `fetchOne()`/`fetchAll()` called inside a `foreach`/`while` loop — should be a single query with `IN (...)` or a JOIN |
| Missing index hint | `ORDER BY` or `WHERE` on columns not in the table's indexes (verify against `ibl5/migrations/000_baseline_schema.sql`) |
| Redundant query | Same table queried multiple times in one request path when results could be cached in a local variable |
| Unindexed JOIN | JOIN condition on columns without matching indexes |

Only flag issues where the performance impact is measurable — not micro-optimizations.

### Section 4: Git history

Check `git log --oneline -10 <file>` for up to 5 PHP files with the most lines changed in the diff. Check all 5 files. Do not stop early. No `git blame`.

**Constraints:**
- PHP files only. Skip `.css`, `.xml`, `.json`, `.sql`, `.md`. In a deep review the caller lifts this constraint; then cover the files with the most changed lines, of any language.
- At most 5 files, prioritizing those with the most lines changed.

### Section 5: Code comments

Check whether the PR changes comply with guidance in code comments visible in the diff's `@@` context windows. Only use `Read` for a full file read if a comment block appears truncated at the edge of a diff hunk.

### Output format

Return one entry per issue: `file:line`, what is wrong, and a concrete input or state that makes it fail. For a rule violation, quote the rule and name its `.claude/rules/*.md` file (or the anti-pattern matched). If you cannot name a failing input or state, say so in the entry. For each section with no issues, return a 1-2 sentence evidence summary citing what was checked. Close each entry with the metadata line from § Per-finding fields. For a Section 4 or Section 5 finding, also name the commit or code comment it conflicts with.

---

## Agent C: Previous PRs (Haiku)

You are a **Senior Software Engineer** cross-referencing prior PR feedback.

Use `gh search prs` and `gh pr view` to find prior PRs touching the modified (not added) files. List EVERY prior review comment that touches these files. Do NOT judge relevance — report all matches. Pass this agent the file list, **not** the diff.

If no applicable prior feedback found, return a 1-sentence evidence summary (e.g., "Checked N prior PRs touching these files — no unaddressed review comments").
