# Code Review Agents (shared definitions)

Source of truth for code-review agent prompts. Used by `/code-review` Step 3, and `/post-plan` Phase 5B. Do not edit without updating both callers.

## Common preamble (all agents)

Each agent receives: filtered PR diff, file list, PR metadata, and root CLAUDE.md content from the parent command. **No agent should call `gh pr diff`** — the diff is already fetched by the parent.

**Assume PHPStan `level: max` + `phpstan-strict-rules` + the custom rules listed in `_review-rubric.md` are satisfied.** Any finding those rules would catch is out of scope — they run in PostToolUse and CI, so a merged PR cannot violate them.

**Playwright `.spec.ts` files are lint-enforced by `bun run lint:e2e` (CI job `ESLint (e2e specs)`).** Reviewers should not duplicate `playwright/*` or `@typescript-eslint/*` rule checks — the preset already covers missing `await`, `{ force: true }`, `waitForTimeout`, `networkidle`, web-first assertions, etc.

---

## Agent 1: CLAUDE.md judgment review

Mandatory CLAUDE.md rules are enforced by PHPStan and **must not be re-checked**. Specifically do not report:

- Missing `declare(strict_types=1)`
- Untyped properties or method signatures
- Loose equality (`==` / `!=`)
- `number_format()` calls outside `StatsFormatter`
- Unescaped output in Views (use of `echo`/`<?=` without `HtmlSanitizer::e()`)
- Inline CSS in PHP (`<style>` blocks or `style="..."` attributes in string literals)
- Deprecated HTML tags (`<b>`, `<i>`, `<center>`, `<font>`, `<u>`)
- Direct calls to banned Nuke globals (`is_user`, `cookiedecode`, `is_admin`, etc.)
- `require_once` / `require` / `include_once` / `include` in `classes/**`
- Raw superglobal access (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`) outside sanctioned input-layer files
- Direct `begin_transaction()` in `BaseMysqliRepository` subclasses
- Empty test method bodies, `assertTrue(true)`, or mocks without `->expects()`
- `$cookie[...]` reads before `PageLayout::header()`

**Focus exclusively on judgment calls a linter cannot make:**

1. Does new code respect the Repository / Service / View architectural split documented in CLAUDE.md?
2. Are SQL string literals consistent with `ibl5/migrations/000_baseline_schema.sql` (column/table names, types)?
3. Are native-type comparisons (`=== 0` vs `=== '0'`) correct for the actual underlying column type?
4. Does refactoring preserve behavior documented in tests?
5. Is the PR scope clean (no unrelated files, no drive-by changes)?

Return a list of issues with the specific CLAUDE.md subsection violated.

---

## Agent 2: Bug detection

You are a **Staff Software Engineer** reviewing a PR for correctness.

Only flag bugs that would cause incorrect behavior in production: wrong results, data corruption, crashes, or silent failures. Skip stylistic issues, unlikely edge cases, and anything a linter or type checker would catch.

Assume PHPStan `level: max` + strict-rules + all custom rules from `_review-rubric.md` are satisfied — do not report anything those would catch.

---

## Agent 3: Git history

Check `git log --oneline -10 <file>` for up to 5 PHP files with the most lines changed in the diff. Stop early on the first relevant historical concern. No `git blame`.

**Constraints:**
- PHP files only — skip `.css`, `.xml`, `.json`, `.sql`, `.md`
- At most 5 files, prioritizing those with the most lines changed
- Return empty list if no relevant concerns after checking 5 files

---

## Agent 4: Previous PRs

Use `gh search prs` and `gh pr view` to find prior PRs touching the modified (not added) files. Check for comments that also apply to the current PR. Pass this agent the file list, **not** the diff.

---

## Agent 5: Code comments

Check whether the PR changes comply with guidance in code comments visible in the diff's `@@` context windows. Only use `Read` for a full file read if a comment block appears truncated at the edge of a diff hunk.
