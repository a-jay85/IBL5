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

**Diff bounds — print before launching agents (informational, never blocking).** Report both directions of plan-vs-diff scope so the human reading 4B sees what the diff actually ships. Run yourself, not via an agent:

```bash
source "$(git rev-parse --show-toplevel)/bin/lib/critical-files.sh"
git diff --name-only origin/master...HEAD | sort -u > /tmp/post-plan-diff-paths-$PPID
if [ -n "${PLAN_FILE:-}" ] && [ -f "$PLAN_FILE" ]; then
  cf_parse_section "$PLAN_FILE" | sed 's/^[A-Z_]*://' | sort -u > /tmp/post-plan-plan-paths-$PPID
else
  : > /tmp/post-plan-plan-paths-$PPID
fi
echo "Diff bounds — in diff, not named by plan ($(comm -23 /tmp/post-plan-diff-paths-$PPID /tmp/post-plan-plan-paths-$PPID | wc -l | tr -d ' ')):"
comm -23 /tmp/post-plan-diff-paths-$PPID /tmp/post-plan-plan-paths-$PPID | sed 's/^/  /'
echo "Diff bounds — named by plan, not in diff ($(comm -13 /tmp/post-plan-diff-paths-$PPID /tmp/post-plan-plan-paths-$PPID | wc -l | tr -d ' ')):"
comm -13 /tmp/post-plan-diff-paths-$PPID /tmp/post-plan-plan-paths-$PPID | sed 's/^/  /'
```

Both counts and both path lists are printed even when zero — a `0` is the informative answer, and a silent section is indistinguishable from a skipped one. **Neither list gates anything**: it does not skip an agent, does not change a launch gate, and does not feed Phase 4D scoring or Phase 6.5 arming. Plan-blind runs (`PLAN_FILE` unset) print an empty plan side and the full diff on the first list; that is correct, not an error. Phase 5.0's conformance check and condition (3) are unaffected — this reads the same section and blocks on nothing.

**Reuse conformance (Agent A only, when `PLAN_FOUND` and `$HAS_PLAN_REUSE`):** extract the plan's Reuse notes from `$PLAN_FILE` and append them to Agent A's prompt under a `PLANNED REUSE:` heading, instructing it to flag any step that hand-rolled logic the plan directed it to reuse (e.g. plan named `SalaryCapRepository::getTeamTotalSalary()`, impl wrote a raw query). This turns Section 1's open-ended architectural judgment into a concrete conformance check.

**Model tiers:**

- Agent A (Architecture + Bug detection + DB performance): **Sonnet 4.6** (`subagent_type: "sonnet-4-6"`, omit `model`)
- Agent B (Git history + Code comments): **Sonnet 4.6** (`subagent_type: "sonnet-4-6"`, omit `model`)
- Agent C (Previous PRs): **Haiku**
- Agent D (E2E specs — POST-effect + assertion discrimination + coverage-branch): **Sonnet 4.6** (`subagent_type: "sonnet-4-6"`, omit `model`)

**Launch gates** (consult Phase 3 variables — skip the launch entirely, don't let the agent exit early):

- Agent A: skip if `$NON_CODE_ONLY` or `$ENGINE_ONLY`. (Agent A is a "Senior PHP Architect"; a pure-Go engine diff has no PHP architecture to review — skipping avoids low-signal PHP-rubric review of Go code. A **mixed** PR — `HAS_PHP=true`, `ENGINE_ONLY=false` — still launches Agent A to review the PHP portion.) If `$MIGRATION_ONLY`, instruct agent to skip Section 2 (bug detection). If `! $HAS_PHP`, instruct agent to skip Section 3 (DB performance).
- Agent B: skip if BOTH sub-gates fail: (`! $HAS_PHP` or `$LINES_PHP_CHANGED <= 50`) AND (`$NON_CODE_ONLY` or `! $HAS_COMMENTS_IN_DIFF`). If only one sub-gate passes, instruct agent to run only that section.
- Agent C: skip if `$NON_CODE_ONLY` or `! $HAS_MODIFIED` or `$LINES_PHP_CHANGED <= 50`
- Agent E (Shell / Workflow / Agent-prose): **Sonnet 4.6** (`subagent_type: "sonnet-4-6"`, omit `model`) — launch when `$HAS_SHELL || $HAS_WORKFLOW || $HAS_SKILL_PROSE`; skip when all three are false. No line-count threshold.
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

```bash
# Agent E pre-slice: shell + workflow + .claude prose sections only.
# Superset of LINES_SHELL_CHANGED — adds .github/workflows/ and .claude/ arms to cover HAS_WORKFLOW and HAS_SKILL_PROSE.
awk '
  /^diff --git/ { p=$NF; sub(/^b\//,"",p)
    keep = (p ~ /^\.github\/workflows\/.*\.ya?ml$/ || p ~ /^\.claude\/.*\.md$/ || \
            ((p ~ /(^|\/)bin\// || p ~ /\.sh$/) && p !~ /\.(php|md|json|py|ts|tsx|css|sql|ya?ml|lock|txt|neon)$/)) ? 1 : 0 }
  keep==1 {print}
' "$DIFF_FILE" > /tmp/post-plan-shell-diff-$PPID
```

Read `.claude/review-shared/_shell-workflow-agent.md` for the canonical Agent E definition and pass it the contents of `/tmp/post-plan-shell-diff-$PPID` plus the Phase 3 file list. If that file is empty (possible when the flags fire on a rename-only change), skip the spawn — do not send an agent an empty diff.

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

**Post results for code review and security audit** using the shared posting helper.

> **Never compose these comments by hand.** `gh pr comment` with a freehand body is not an
> alternative spelling of the calls below — the envelope the helper emits (`### Code review` /
> `### Security audit` heading, `<details>` wrapper, `<!-- score: N -->` markers, `PRF_FOOTER`)
> is **machine-parsed downstream** by three consumers:
> `.claude/skills/pr-ready/scripts/4b-probe.sh` (matches `^#{1,6} +Code review` to set
> `PHASE_4B_RAN`), `list_open_review_findings` / `resolve_review_finding` (read the score marker
> and the inline threads to disposition findings), and the `unresolved-findings-hold` gate.
> A hand-written comment performs a real review whose artifact is invisible to all three:
> `/pr-ready` reports "structured code review never ran" and recommends a redundant `/pr-review`,
> and the findings can never be dispositioned. This failed silently on PRs #1956 and #2001.
> If a call below does not fit the situation, stop and say so — do not improvise a comment.

Source the helper, mirroring the pr-armable.sh idiom used by the Phase 6.5 condition blocks:

```bash
source "$(git rev-parse --show-toplevel)/bin/lib/post-review-findings.sh"
```

**Code review — run exactly one of these two blocks.** Issues survived the filter:

```bash
# One object per surviving issue. path = repo-relative file (matches `+++ b/<path>`);
# line = single anchor line on the new-file (RIGHT) side; body = <description>
# (CLAUDE.md says "<rule>") followed by the full-SHA range link; score = Haiku score.
# Do NOT add the heading or footer — the helper emits both.
cat > /tmp/post-plan-cr-findings-$PPID.json <<'JSON'
[ { "path": "ibl5/classes/Example.php", "line": 17,
    "body": "<description> (CLAUDE.md says \"<rule>\")\n\n<full-SHA range link>",
    "score": 85 } ]
JSON
post_review_findings "$PR" "$FULL_SHA" "Code review" "/tmp/post-plan-cr-findings-$PPID.json"
```

No issues survived the filter:

```bash
post_review_summary "$PR" "Code review" "No issues found. <1-2 sentence evidence summary>"
```

**Security audit — run exactly one of these two blocks.** Issues survived the filter (severity:
CRITICAL = SQLi/CMDi, HIGH = missing auth/open redirect, MEDIUM = CSRF/missing auth on
non-critical endpoints, LOW = best practice):

```bash
cat > /tmp/post-plan-sa-findings-$PPID.json <<'JSON'
[ { "path": "ibl5/classes/Example.php", "line": 17,
    "body": "**[SEVERITY]** Type in `Class::method()` — description\n\n<full-SHA range link>",
    "score": 85 } ]
JSON
post_review_findings "$PR" "$FULL_SHA" "Security audit" "/tmp/post-plan-sa-findings-$PPID.json"
```

No issues survived the filter:

```bash
post_review_summary "$PR" "Security audit" "No security issues found. <brief evidence per category> (XSS and input validation are enforced by PHPStan custom rules.)"
```

`post_review_findings` splits findings into on-diff (→ batch resolvable inline review threads) and
out-of-diff (→ single fallback `gh pr comment`); nothing is dropped, and an empty array is a no-op.

**Confirm the envelope landed.** A freehand comment fails silently in both directions, so measure
the count for that title **before and after** each call and require a strict increase. An absolute
count is not enough — a PR that already carries a `### Code review` comment (a prior `/pr-review`,
a Phase-5 strict-loop re-entry into Phase 4) reads as passing even when this run posted nothing.

```bash
# prf_envelope_count TITLE — comments + reviews whose body carries the helper's heading.
prf_envelope_count() {
    local t="$1" ep n=0 x
    for ep in "issues/$PR/comments" "pulls/$PR/reviews"; do
        x=$("$GH_CMD" api "repos/{owner}/{repo}/$ep" --paginate \
            --jq "[.[] | select((.body // \"\") | test(\"(?m)^#{1,6} +${t}\\\\b\"))] | length" \
            | awk '{s+=$1} END {print s+0}')   # --paginate emits one count per page
        n=$((n + x))
    done
    printf '%s\n' "$n"
}

before=$(prf_envelope_count "Code review")
# ... run exactly one of the two Code review blocks above ...
[ "$(prf_envelope_count "Code review")" -gt "$before" ] \
    || echo "ENVELOPE MISSING: Code review — re-post through the helper"
```

Repeat with `"Security audit"` around the security-audit call. Skip the check for a
`post_review_findings` call whose findings array was empty — that is a documented no-op and posts
nothing. On `ENVELOPE MISSING`, re-post through the helper rather than leaving the artifact
undetectable; do not paper over it with a freehand comment.

> Canonical source for the two blocks below: `.claude/review-shared/_posting-procedure.md` (§ Dispositioning open threads, § Link format rules). They are restated here under this phase's own variable names (`$PR`, `$FULL_SHA` from 4A) so the commands are runnable as written — change one and change the other.

**Dispositioning open threads:** a finding posted as an inline thread stays open until something replies *in-thread* and resolves it. Never announce a fixed finding with `gh pr comment` to close a thread — a top-level comment cannot associate with a review thread. Use `list_open_review_findings` to find the COMMENT_ID, then `resolve_review_finding` to reply in-thread and mark the thread resolved. The same call applies when declining a finding — the body says why, and the thread still closes. A finding is dispositioned when it is fixed *or* explicitly declined; silence is not a disposition.

```bash
source "$(git rev-parse --show-toplevel)/bin/lib/post-review-findings.sh"
list_open_review_findings "$PR"                              # TSV: COMMENT_ID, score, path:line, excerpt
resolve_review_finding "$PR" <COMMENT_ID> "Fixed in $FULL_SHA — <what changed>"
```

**Link format (in `body` field):** `https://github.com/a-jay85/IBL5/blob/{FULL_SHA}/path/to/file#L{start}-L{end}` — expand SHA from 4A beforehand, never use bash interpolation in the body string. Include 1 line of context before/after the anchor line.
