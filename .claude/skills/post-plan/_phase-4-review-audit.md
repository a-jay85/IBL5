# Phase 4 reference — Code Review + Security Audit

Purpose: the full Phase 4 code-review (4A–4D) and conditional security-audit procedure.

Agent definitions and scoring rubric live in shared include files under `.claude/review-shared/` so this skill, `/pr-review`, and `/security-audit` all share one source of truth. Read them as instructed below — do NOT inline the definitions or duplicate them.

### 4A: Fetch PR data (shared by both)

Run these commands yourself (not via agents):

```bash
gh pr view --json number,headRefOid,headRefName,baseRefName,title,body,author
cat /tmp/post-plan-diff-$PPID   # filtered diff written by Phase 3 (already < 100KB after the fallback)
```

Capture the `cat` output — that is `$DIFF` for every sub-agent prompt below. No sub-agent calls `gh pr diff`.

**Do not forward CLAUDE.md content in agent prompts** — sub-agents auto-load CLAUDE.md on init, so forwarding it doubles the token cost (~5K × N agents). If directory-specific `CLAUDE.md` files exist for modified directories, read them and forward only those (they are not auto-loaded).

### 4B: Code Review — up to 3 parallel agents (merged by tier)

**Read** `.claude/review-shared/_review-agents.md` (Agents A/B/C) and `.claude/review-shared/_test-spec-agent.md` (Agent D — E2E specs). The canonical agent definitions.

Pass each agent: PR metadata, file list, and filtered `$DIFF`. **No agent calls `gh pr diff`.** Do not forward CLAUDE.md content (auto-loaded).

**Reuse conformance (Agent A only, when `PLAN_FOUND` and `$HAS_PLAN_REUSE`):** extract the plan's Reuse notes from `$PLAN_FILE` and append them to Agent A's prompt under a `PLANNED REUSE:` heading, instructing it to flag any step that hand-rolled logic the plan directed it to reuse (e.g. plan named `SalaryCapRepository::getTeamTotalSalary()`, impl wrote a raw query). This turns Section 1's open-ended architectural judgment into a concrete conformance check.

**Model tiers:**

- Agent A (Architecture + Bug detection + DB performance): **Sonnet**
- Agent B (Git history + Code comments): **Sonnet**
- Agent C (Previous PRs): **Haiku**
- Agent D (E2E specs — POST-effect + assertion discrimination + coverage-branch): **Sonnet**

**Launch gates** (consult Phase 3 variables — skip the launch entirely, don't let the agent exit early):

- Agent A: skip if `$NON_CODE_ONLY` or `$ENGINE_ONLY`. (Agent A is a "Senior PHP Architect"; a pure-Go engine diff has no PHP architecture to review — skipping avoids low-signal PHP-rubric review of Go code. A **mixed** PR — `HAS_PHP=true`, `ENGINE_ONLY=false` — still launches Agent A to review the PHP portion.) If `$MIGRATION_ONLY`, instruct agent to skip Section 2 (bug detection). If `! $HAS_PHP`, instruct agent to skip Section 3 (DB performance).
- Agent B: skip if BOTH sub-gates fail: (`! $HAS_PHP` or `$LINES_PHP_CHANGED <= 50`) AND (`$NON_CODE_ONLY` or `! $HAS_COMMENTS_IN_DIFF`). If only one sub-gate passes, instruct agent to run only that section.
- Agent C: skip if `$NON_CODE_ONLY` or `! $HAS_MODIFIED` or `$LINES_PHP_CHANGED <= 50`
- Agent D: skip if `! $HAS_E2E_SPECS`. When launched, pre-slice the diff into two temp files before forwarding to the agent:
  ```bash
  # Spec portion of the diff (only .ts under ibl5/tests/e2e/)
  awk '
    /^diff --git.*ibl5\/tests\/e2e\/.*\.ts/ {keep=1; print; next}
    /^diff --git/ {keep=0}
    keep==1 {print}
  ' "$DIFF_FILE" > /tmp/post-plan-spec-diff-$PPID

  # Production portion: only files under ibl5/modules/<M>/ for M in E2E_SPEC_MODULES
  MODULES_REGEX=$(echo "$E2E_SPEC_MODULES" | tr '\n' '|' | sed 's/|$//')
  if [ -n "$MODULES_REGEX" ]; then
      awk -v re="ibl5/modules/($MODULES_REGEX)/" '
        $0 ~ "^diff --git.*"re {keep=1; print; next}
        /^diff --git/ {keep=0}
        keep==1 {print}
      ' "$DIFF_FILE" > /tmp/post-plan-spec-prod-diff-$PPID
  else
      : > /tmp/post-plan-spec-prod-diff-$PPID
  fi
  ```
  Pass Agent D: PR metadata, the spec file list, `/tmp/post-plan-spec-diff-$PPID`, `/tmp/post-plan-spec-prod-diff-$PPID`, and `$HAS_E2E_PROD_OVERLAP`. The agent does **not** call `gh pr diff`.

### 4C: Security Audit — single conditional Haiku agent

**Skip entire 4C if** `! $HAS_PHP`. CSS, markdown, migrations, and lockfile bumps cannot introduce SQLi/CSRF/auth vulnerabilities.

**Read** `.claude/review-shared/_security-agents.md` — the canonical security agent definition and pattern-detection bash block.

Run the pattern-detection block from that file to get SQL and Forms category counts. Build the `CATEGORIES:` line (always include Auth/Authz; add SQL Injection if SQL > 0; add CSRF Protection if Forms > 0). Launch a **single Haiku agent** with the categories line and the PHP-only subset of `$DIFF`. Do not forward CLAUDE.md content (auto-loaded).

**Plan-backed mode (when `PLAN_FOUND` and `$HAS_PLAN_SECURITY`):** the plan already declares each touched surface and its intended defense. Pass the plan's Security section to the agent as an `EXPECTED DEFENSES:` checklist and instruct it to (a) confirm each planned defense is present in the diff and (b) flag any state-changing surface the plan did *not* anticipate. You may build `CATEGORIES:` directly from the plan's declared surfaces instead of running the pattern-detection grep. This shifts the audit from discovery to verification — it catches "CSRF was planned but the impl omitted it" and cuts the false positives blind pattern-matching produces.

**XSS and Input Validation are NOT audited here** — they're deterministically enforced by `RequireEscapedOutputRule` and `BanRawSuperglobalsRule` (run in PostToolUse and CI).

### 4D: Score, filter, and post

**Read** `.claude/review-shared/_review-rubric.md` — the canonical rubric, thresholds (`< 80` for code review, `< 75` for security), Automatic-Zero rule list, and IBL5 false-positive list.

Combine ALL issues from 4B and 4C into one numbered list.

**Skip the scoring agent if the combined list is empty** — jump straight to the `post_review_summary` no-issues path below.

Otherwise launch a **single Haiku agent**, pass it the issues list plus the **Scoring scale and Thresholds sections** from `_review-rubric.md` (not the full Automatic Zero or false-positive lists — review agents have already filtered those). Instruct it to return JSON scores per that rubric. Parse the response and assign scores back to each issue.

**Filter** per the thresholds in `_review-rubric.md`.

**Re-check PR state:** `gh pr view --json state --jq '.state'` — skip posting if not `OPEN`.

**Post results for code review and security audit** using the shared posting helper. Source it mirroring the pr-armable.sh idiom used by the Phase 6.5 condition blocks:

```bash
source "$(git rev-parse --show-toplevel)/bin/lib/post-review-findings.sh"
```

The helper provides two public functions:

- `post_review_findings PR_NUMBER HEAD_SHA REVIEW_TITLE FINDINGS_FILE` — splits findings into on-diff (→ batch resolvable inline review threads) and out-of-diff (→ single fallback `gh pr comment`). Nothing is dropped. The `<!-- score: N -->` marker is appended automatically; the heading envelope and footer are emitted by the helper — do NOT re-add them.
- `post_review_summary PR_NUMBER REVIEW_TITLE BODY` — for the no-issues path; posts a single `gh pr comment` with the heading, evidence body, and footer.

**For code review:**
- Issues survived filter → build a JSON findings array (one object per issue: `path` = repo-relative file, `line` = single anchor line on new-file side, `body` = `<description> (CLAUDE.md says "<rule>")` + full-SHA range link, `score` = Haiku score) to a temp file; call `post_review_findings "$PR" "$FULL_SHA" "Code review" <file>`.
- No issues → call `post_review_summary "$PR" "Code review" "No issues found. <1-2 sentence evidence summary>"`.

**For security audit:**
- Issues survived filter → build findings JSON (`body` = `**[SEVERITY]** Type in \`Class::method()\` — description` + full-SHA range link, `score`); call `post_review_findings "$PR" "$FULL_SHA" "Security audit" <file>`. Severity: CRITICAL (SQLi/CMDi), HIGH (missing auth/open redirect), MEDIUM (CSRF/missing auth on non-critical endpoints), LOW (best practice).
- No issues → call `post_review_summary "$PR" "Security audit" "No security issues found. <brief evidence per category> (XSS and input validation are enforced by PHPStan custom rules.)"`.

**Link format (in `body` field):** `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}` — expand SHA from 4A beforehand, never use bash interpolation in the body string. Include 1 line of context before/after the anchor line.
