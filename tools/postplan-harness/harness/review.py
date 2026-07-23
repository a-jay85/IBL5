"""Phase 4 — code review + security audit as bounded LLM calls.

Launch gates are the deterministic Phase-3 flags (identical to
_phase-4-review-audit.md 4B/4C). Each retained call is toolless and receives a
bounded packet: PR metadata + file list + the filtered diff (already <100KB).
Scoring (4D) is one bounded Haiku call against the shared rubric scale;
thresholds and posting are deterministic.

Prompt text is condensed from .claude/review-shared/_review-agents.md,
_security-agents.md, and _review-rubric.md (the flag-gated sections mirror the
originals; agents here cannot read repo files, so the checklists are inlined).
"""
from __future__ import annotations

import re

from .state import Classification, Finding, PlanInfo
from . import schemas
from .classify import slice_spec_diffs

CODE_THRESHOLD = 80      # drop findings scored below (rubric Thresholds table)
SEC_THRESHOLD = 75

AUTOMATIC_ZERO_NOTE = (
    "Assume PHPStan level max + strict-rules + IBL5 custom rules are satisfied "
    "(strict_types, escaped output, raw superglobals, inline CSS, deprecated tags, "
    "require_once in classes/, meaningless assertions, number_format outside "
    "StatsFormatter, direct nuke globals, begin_transaction in repositories, cookie-"
    "before-header). Any finding those rules would catch is OUT OF SCOPE — a merged "
    "PR cannot violate them. Do not report pre-existing issues on lines the PR did "
    "not modify."
)

OUTPUT_CONTRACT = (
    "\n\nReturn ONLY a JSON array of findings: "
    '[{"path": "repo/relative/file.php", "line": 123, "body": "what and why"}]. '
    "line is a single anchor line on the new-file side of the diff. Return [] if "
    "no issues survive scrutiny. No prose outside the JSON."
)


def agent_a_prompt(meta: dict, cls: Classification, plan: PlanInfo) -> str:
    sections = ["Section 1 — Architectural fitness: Repository/Service/View split, "
                "SQL literals consistent with the baseline schema, native-type "
                "comparisons (=== 0 vs === '0') correct for column types, refactors "
                "preserve tested behavior, clean PR scope (no drive-by changes)."]
    if not cls.migration_only:
        sections.append(
            "Section 2 — Bug detection (production-impact only): bind_param type-char "
            "swaps; native-type mismatch (=== '0' on INT columns like tid/retired/hasMLE); "
            "contract-year cy1-vs-cy2 confusion; COUNT(*) on ibl_box_scores without "
            "gameMIN > 0; related-row writes without transactional(); free-agent tid "
            "compared as string. Skip stylistic issues and linter-catchable problems.")
    if cls.has_php:
        sections.append(
            "Section 3 — DB performance (measurable only): unbounded fetchAll() on "
            "growing tables (ibl_box_scores, ibl_players, ibl_transactions); N+1 "
            "query loops (fetch inside foreach/while); ORDER BY/WHERE on unindexed "
            "columns; redundant repeat queries; unindexed JOINs.")
    reuse = ""
    if plan.found and plan.has_reuse and plan.reuse_section:
        reuse = ("\n\nPLANNED REUSE (flag any step that hand-rolled logic the plan "
                 "directed to reuse):\n" + plan.reuse_section)
    return (
        "You are a Senior PHP Architect and Staff Engineer reviewing a PR for "
        "architectural fitness, correctness bugs, and database performance.\n\n"
        + AUTOMATIC_ZERO_NOTE + "\n\n" + "\n\n".join(sections) + reuse
        + f"\n\nPR: #{meta.get('number')} {meta.get('title', '')}\n"
        + f"Files changed:\n" + "\n".join(cls.files)
        + "\n\nDIFF:\n" + cls.filtered_diff + OUTPUT_CONTRACT
    )


def agent_b_prompt(meta: dict, cls: Classification, run_history: bool, run_comments: bool) -> str:
    parts = []
    if run_history:
        parts.append("Section 1 — Regression risk: for the PHP files with the most "
                     "changed lines, does the change risk re-introducing a bug the "
                     "file's structure suggests was fixed (guard clauses, boundary "
                     "checks, type casts being removed)?")
    if run_comments:
        parts.append("Section 2 — Code comments: does the change comply with guidance "
                     "in code comments visible in the diff's @@ context windows? Flag "
                     "changes that contradict an adjacent comment's stated constraint.")
    return (
        "You are a Senior Software Engineer reviewing regression risk and in-code "
        "guidance compliance.\n\n" + AUTOMATIC_ZERO_NOTE + "\n\n" + "\n\n".join(parts)
        + f"\n\nPR: #{meta.get('number')} {meta.get('title', '')}\n"
        + "\n\nDIFF:\n" + cls.filtered_diff + OUTPUT_CONTRACT
    )


def agent_d_prompt(meta: dict, cls: Classification) -> str:
    spec_diff, prod_diff = slice_spec_diffs(cls.filtered_diff, cls.e2e_spec_modules)
    return (
        "You are a Senior QA Engineer reviewing Playwright E2E specs for assertion "
        "quality. Lint-enforced rules (missing await, force:true, waitForTimeout, "
        "networkidle) are out of scope.\n\nFlag these named anti-patterns only:\n"
        "1. Same-page-success-only: a happy-path submission test asserting a success "
        "banner without cross-page navigation, API read-back, or submitFormAndAssertEffect.\n"
        "2. Generic-fallback assertion: toBeVisible() on body/.ibl-content/#main or "
        "selectors present on every error template; header-text-only assertions in "
        "non-smoke tests; uncounted .first() where the name implies multiple items.\n"
        "3. New UI branch without a spec: production diff adds a phase-gated state / "
        "admin-only module / HTMX-swapped tab / conditional modal with no matching "
        "test block (prod overlap present: " + str(cls.has_e2e_prod_overlap) + ").\n"
        "Smoke tests (tests/e2e/smoke/) legitimately assert page-load via generic "
        "selectors — exempt. Error-path tests intentionally verify absence of effect — exempt.\n"
        + f"\nPR: #{meta.get('number')} {meta.get('title', '')}\n"
        + "\nSPEC DIFF:\n" + spec_diff + "\n\nPRODUCTION DIFF (overlapping modules):\n"
        + (prod_diff or "(none)") + OUTPUT_CONTRACT
    )


def security_prompt(meta: dict, cls: Classification, plan: PlanInfo) -> str:
    php_added = []
    in_php = False
    for line in cls.filtered_diff.splitlines():
        if line.startswith("diff --git"):
            in_php = bool(re.search(r"\.php\b", line))
        elif in_php and line.startswith("+") and not line.startswith("++"):
            php_added.append(line)
    blob = "\n".join(php_added)
    sql_count = len(re.findall(r"sql_query|prepare|fetchOne|fetchAll|query\(", blob))
    forms_count = len(re.findall(r"POST|PUT|DELETE|<form|action=", blob))
    cats = ["Auth/Authz"]
    if sql_count:
        cats.insert(0, "SQL Injection")
    if forms_count:
        cats.insert(-1, "CSRF Protection")
    plan_block = ""
    if plan.found and plan.has_security and plan.security_section:
        plan_block = ("\n\nEXPECTED DEFENSES (from the plan — confirm each is present in "
                      "the diff, and flag any state-changing surface the plan did not "
                      "anticipate):\n" + plan.security_section)
    return (
        "You are a Senior Application Security Engineer auditing a PHP diff. Focus on "
        "exploitable vulnerabilities, not theoretical risks; consider strict_types, the "
        "prepared-statement repository pattern (BaseMysqliRepository fetchOne/fetchAll/"
        "execute are parameterized), CsrfGuard, ApiKeyAuthenticator, is_user/is_admin "
        "guards. XSS and raw-superglobal input validation are deterministically "
        "lint-enforced — out of scope.\n\nCATEGORIES: " + ", ".join(cats) + "\n"
        "SQL Injection: flag sql_query() with interpolated/concatenated variables, "
        "dynamic ORDER BY/LIMIT/columns from user input without whitelist. Do NOT flag "
        "prepared statements, hardcoded strings, or (int)-cast values.\n"
        "CSRF: flag new POST/PUT/DELETE or form-processing state changes without "
        "CsrfGuard::validateSubmittedToken()/validateToken(); forms missing "
        "generateToken(). GET-only reads and ApiKeyAuthenticator endpoints are exempt.\n"
        "Auth/Authz: flag state-changing endpoints without is_user()/isAuthenticated(); "
        "admin operations without is_admin(); open redirects from user input; missing "
        "session_regenerate_id() after auth changes. Read-only public pages are exempt."
        + plan_block
        + f"\n\nPR: #{meta.get('number')} {meta.get('title', '')}\n"
        + "\nPHP DIFF (filtered):\n" + cls.filtered_diff + OUTPUT_CONTRACT
    )


def scoring_prompt(findings: list[Finding]) -> str:
    numbered = "\n".join(
        f"{i+1}. [{f.source}/{f.agent}] {f.path}:{f.line} — {f.body}"
        for i, f in enumerate(findings))
    return (
        "Score each review finding 0-100 for confidence it is a real, "
        "production-impacting issue:\n"
        "0 = false positive / pre-existing / linter-caught. 25 = suspicious but likely "
        "mitigated. 50 = pattern present, exploitation needs conditions that may not "
        "apply; stylistic. 75 = highly confident, verified real. 100 = certain: direct "
        "user input to SQL/HTML/file/state-change with zero sanitization.\n"
        "IBL5 false positives (score 0-25): BaseMysqliRepository variables (already "
        "parameterized); test files; echo in CLI scripts; hardcoded sql_query strings; "
        "ApiKeyAuthenticator handlers (CSRF-exempt); GET-only readers; pre-existing "
        "issues on unmodified lines; intentional changes related to the PR's purpose.\n\n"
        "FINDINGS:\n" + numbered +
        '\n\nReturn ONLY valid JSON: [{"n": 1, "score": 75}, ...] — one entry per finding.'
    )


class ReviewPhase:
    def __init__(self, llm, gh):
        self.llm = llm
        self.gh = gh

    def gates(self, cls: Classification) -> dict:
        b_hist = cls.has_php and cls.lines_php_changed > 50
        b_comm = (not cls.non_code_only) and cls.has_comments_in_diff
        return {
            "A": not (cls.non_code_only or cls.engine_only),
            "B_history": b_hist, "B_comments": b_comm,
            "B": b_hist or b_comm,
            "C": not (cls.non_code_only or not cls.has_modified or cls.lines_php_changed <= 50),
            "D": cls.has_e2e_specs,
            "security": cls.has_php,
        }

    def run(self, meta: dict, cls: Classification, plan: PlanInfo) -> tuple[list[Finding], dict]:
        gates = self.gates(cls)
        findings: list[Finding] = []

        def collect(agent: str, source: str, purpose: str, model: str, prompt: str):
            data = self.llm.call(purpose, model, prompt, schemas.validate_findings)
            for f in data:
                findings.append(Finding(source=source, agent=agent, path=f["path"],
                                        line=f["line"], body=f["body"]))

        if gates["A"]:
            collect("A", "code-review", "review-agent-a", "sonnet", agent_a_prompt(meta, cls, plan))
        if gates["B"]:
            collect("B", "code-review", "review-agent-b", "sonnet",
                    agent_b_prompt(meta, cls, gates["B_history"], gates["B_comments"]))
        # Agent C (previous-PR feedback) requires live gh search — replay serves
        # recorded "no prior feedback"; a live variant would add a gh-read adapter.
        if gates["D"]:
            collect("D", "code-review", "review-agent-d", "sonnet", agent_d_prompt(meta, cls))
        if gates["security"]:
            collect("security", "security-audit", "security-audit", "haiku",
                    security_prompt(meta, cls, plan))

        if findings:
            scores = self.llm.call("score-findings", "haiku", scoring_prompt(findings),
                                   schemas.validate_scores)
            by_n = {s["n"]: s["score"] for s in scores}
            for i, f in enumerate(findings):
                f.score = by_n.get(i + 1, 0)

        surviving = [f for f in findings
                     if (f.score or 0) >= (SEC_THRESHOLD if f.source == "security-audit"
                                           else CODE_THRESHOLD)]

        pr = meta.get("number") or 0
        sha = meta.get("headRefOid") or meta.get("sha") or ""
        code = [f for f in surviving if f.source == "code-review"]
        sec = [f for f in surviving if f.source == "security-audit"]
        if code:
            self.gh.post_review_findings(pr, sha, "Code review", code)
        else:
            self.gh.post_review_summary(pr, "Code review", "No issues found.")
        if gates["security"]:
            if sec:
                self.gh.post_review_findings(pr, sha, "Security audit", sec)
            else:
                self.gh.post_review_summary(
                    pr, "Security audit",
                    "No security issues found. (XSS and input validation are enforced "
                    "by PHPStan custom rules.)")
        return surviving, gates
