"""Phase 4 — code review + security audit as bounded LLM calls.

Launch gates are the deterministic Phase-3 flags (identical to
_phase-4-review-audit.md 4B/4C). Each retained call is toolless and receives a
bounded packet: PR metadata + file list + the filtered diff (already <100KB).
Scoring (4D) is VERIFIER_COUNT tool-enabled Sonnet verifiers voting per finding (drop needs every valid vote to be not-real);
thresholds and posting are deterministic.

Prompt text is condensed from .claude/review-shared/_review-agents.md,
_security-agents.md, and _review-rubric.md (the flag-gated sections mirror the
originals; agents here cannot read repo files, so the checklists are inlined).
"""
from __future__ import annotations

import concurrent.futures
import json
import os
import re

from .state import Classification, Finding, HarnessError, PlanInfo
from . import schemas
from .adapters.llm import extract_json
from .classify import slice_spec_diffs
from .review_depth import DEEP, STANDARD, max_depth

CODE_THRESHOLD = 80      # drop findings scored below (rubric Thresholds table)
SEC_THRESHOLD = 75

VERIFIER_COUNT = 3                     # odd, smallest N > 1 that makes "all reject" meaningful
VERIFIER_PURPOSES = tuple(f"verify-findings-{i}" for i in range(1, VERIFIER_COUNT + 1))
VERIFIER_MODEL = "sonnet"              # call_tooled allowlist is {"opus","sonnet"}; Haiku cannot read code here
VERIFIER_ALLOWED_TOOLS = ("Read", "Grep", "Glob")
VERIFIER_DENIED_TOOLS = ("Bash", "Write", "Edit", "NotebookEdit", "Agent")
VERIFIER_MAX_TURNS = 25
VERIFIER_TIMEOUT = 900                 # seconds per call; a few file reads, never a repo crawl
VERIFIER_MAX_BUDGET_USD = 2.0
VERIFIER_PACKET_BUDGET = 90_000        # bytes; headroom under adapters.llm.MAX_PROMPT_BYTES (120_000)
VERIFIER_HUNK_CAP = 20_000             # bytes of diff hunk inlined per cited file
VERIFIER_TERMINAL_KINDS = frozenset({"llm-fixture-missing", "llm-model-not-allowed",
                                     "llm-tooled-no-tools"})   # programming/test errors stay loud

VERIFIER_FALSE_POSITIVES = (
    "Reject (not-real) when the finding is one of the IBL5 false positives: BaseMysqliRepository "
    "variables (already parameterized); test files; echo in CLI scripts; hardcoded sql_query "
    "strings; ApiKeyAuthenticator handlers (CSRF-exempt); GET-only readers; pre-existing issues "
    "on lines the PR did not modify; intentional changes that are the PR's stated purpose."
)

AUTOMATIC_ZERO_NOTE = (
    "Assume PHPStan level max + strict-rules + IBL5 custom rules are satisfied "
    "(strict_types, escaped output, raw superglobals, inline CSS, deprecated tags, "
    "require_once in classes/, meaningless assertions, number_format outside "
    "StatsFormatter, direct nuke globals, begin_transaction in repositories, cookie-"
    "before-header). Any finding those rules would catch is OUT OF SCOPE — a merged "
    "PR cannot violate them. Do not report pre-existing issues on lines the PR did "
    "not modify."
)

OUTPUT_CONTRACT_EXAMPLE = (
    '[{"path": "ibl5/classes/TeamRepository.php", "line": 88, '
    '"body": "fetchAll() on ibl_box_scores is unbounded and this table grows every sim; '
    'add a LIMIT or filter by season."}]'
)

_CONTRACT_BODY = (
    "Return ONLY a JSON array of findings: "
    '[{"path": "repo/relative/file.php", "line": 123, "body": "what and why"}]. '
    "Every finding carries exactly these three lowercase keys. Do NOT emit any other "
    'key: no "category", no "severity", no "symbol", no "file", no "detail". '
    "Do NOT wrap the array in an object and do NOT group findings under per-topic keys; "
    "one flat array covers the whole review. "
    "line is a single anchor line on the new-file side of the diff; use 0 when the "
    "finding is about the file as a whole and has no single anchor line. "
    "Return [] if no issues survive scrutiny. No prose outside the JSON, no markdown "
    "headings, no explanation before or after.\n"
    "A complete valid reply with one finding looks exactly like this:\n"
    + OUTPUT_CONTRACT_EXAMPLE
)

OUTPUT_CONTRACT_HEAD = "OUTPUT CONTRACT (restated at the end of this prompt):\n" + _CONTRACT_BODY + "\n\n"

OUTPUT_CONTRACT = "\n\nOUTPUT CONTRACT (as stated at the top):\n" + _CONTRACT_BODY


def agent_a_prompt(meta: dict, cls: Classification, plan: PlanInfo) -> str:
    sections = ["Look for architectural-fitness problems: Repository/Service/View split, "
                "SQL literals consistent with the baseline schema, native-type "
                "comparisons (=== 0 vs === '0') correct for column types, refactors "
                "preserve tested behavior, clean PR scope (no drive-by changes)."]
    if not cls.migration_only:
        sections.append(
            "Look for production-impact bugs: bind_param type-char "
            "swaps; native-type mismatch (=== '0' on INT columns like tid/retired/hasMLE); "
            "contract-year cy1-vs-cy2 confusion; COUNT(*) on ibl_box_scores without "
            "gameMIN > 0; related-row writes without transactional(); free-agent tid "
            "compared as string. Skip stylistic issues and linter-catchable problems.")
    if cls.has_php:
        sections.append(
            "Look for measurable DB-performance problems: unbounded fetchAll() on "
            "growing tables (ibl_box_scores, ibl_players, ibl_transactions); N+1 "
            "query loops (fetch inside foreach/while); ORDER BY/WHERE on unindexed "
            "columns; redundant repeat queries; unindexed JOINs.")
    reuse = ""
    if plan.found and plan.has_reuse and plan.reuse_section:
        reuse = ("\n\nPLANNED REUSE (flag any step that hand-rolled logic the plan "
                 "directed to reuse):\n" + plan.reuse_section)
    return (
        OUTPUT_CONTRACT_HEAD
        + "You are a Senior PHP Architect and Staff Engineer reviewing a PR for "
        "architectural fitness, correctness bugs, and database performance.\n\n"
        + AUTOMATIC_ZERO_NOTE + "\n\n" + "\n\n".join(sections) + reuse
        + f"\n\nPR: #{meta.get('number')} {meta.get('title', '')}\n"
        + f"Files changed:\n" + "\n".join(cls.files)
        + "\n\nDIFF:\n" + cls.filtered_diff + OUTPUT_CONTRACT
    )


def agent_b_prompt(meta: dict, cls: Classification, run_history: bool, run_comments: bool,
                   *, any_files: bool = False) -> str:
    parts = []
    if run_history:
        parts.append("Look for regression risk: for the "
                     + ("" if any_files else "PHP ") + "files with the most "
                     "changed lines, does the change risk re-introducing a bug the "
                     "file's structure suggests was fixed (guard clauses, boundary "
                     "checks, type casts being removed)?")
    if run_comments:
        parts.append("Look for code-comment compliance problems: does the change comply with guidance "
                     "in code comments visible in the diff's @@ context windows? Flag "
                     "changes that contradict an adjacent comment's stated constraint.")
    return (
        OUTPUT_CONTRACT_HEAD
        + "You are a Senior Software Engineer reviewing regression risk and in-code "
        "guidance compliance.\n\n" + AUTOMATIC_ZERO_NOTE + "\n\n" + "\n\n".join(parts)
        + f"\n\nPR: #{meta.get('number')} {meta.get('title', '')}\n"
        + "\n\nDIFF:\n" + cls.filtered_diff + OUTPUT_CONTRACT
    )


def agent_d_prompt(meta: dict, cls: Classification) -> str:
    spec_diff, prod_diff = slice_spec_diffs(cls.filtered_diff, cls.e2e_spec_modules)
    return (
        OUTPUT_CONTRACT_HEAD
        + "You are a Senior QA Engineer reviewing Playwright E2E specs for assertion "
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
        OUTPUT_CONTRACT_HEAD
        + "You are a Senior Application Security Engineer auditing a PHP diff. Focus on "
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


_VERDICT_CONTRACT = (
    'Return ONLY a JSON array with one entry per finding number you were given: '
    '[{"n": 1, "verdict": "real", "reason": "one sentence citing what you read"}]. '
    '"verdict" is exactly "real" or "not-real". Do NOT add entries for issues you noticed '
    "yourself; you are verifying, not reviewing. No prose outside the JSON."
)


def _diff_by_path(filtered_diff: str) -> dict[str, str]:
    """Split a unified diff into per-file blocks keyed by the new-side path, each capped."""
    blocks: dict[str, list[str]] = {}
    cur = None
    for line in filtered_diff.splitlines():
        m = re.match(r"^diff --git a/(\S+) b/(\S+)", line)
        if m:
            cur = m.group(2)
            blocks[cur] = []
        if cur is not None:
            blocks[cur].append(line)
    out = {}
    for path, lines in blocks.items():
        text = "\n".join(lines)
        if len(text.encode()) > VERIFIER_HUNK_CAP:
            text = text.encode()[:VERIFIER_HUNK_CAP].decode(errors="ignore") + "\n[TRUNCATED]"
        out[path] = text
    return out


def verifier_prompt(chunk: list[Finding], hunks: dict[str, str]) -> str:
    numbered = "\n".join(
        f"{i+1}. [{f.source}/{f.agent}] {f.path}:{f.line} — {f.body}" for i, f in enumerate(chunk))
    paths = []
    for f in chunk:
        if f.path not in paths:
            paths.append(f.path)
    diff_text = "\n\n".join(f"### {p}\n{hunks.get(p, '(no hunk for this path in the PR diff)')}"
                            for p in paths)
    return (
        "OUTPUT CONTRACT (restated at the end):\n" + _VERDICT_CONTRACT + "\n\n"
        "You are one of several independent verifiers. Other reviewers produced the numbered "
        "findings below against this PR. For EACH finding, open the cited file at the cited "
        "line in the working tree (your cwd is the PR checkout), read enough surrounding code "
        "to judge the claim, compare with the diff hunk, and vote.\n"
        "Vote not-real ONLY when you verified in the code that the claim is wrong, already "
        "mitigated, out of the PR's changed lines, or matches a rejection rule below. "
        "When you cannot open the file, cannot find the line, or cannot decide, vote real: "
        "the pipeline keeps a finding unless every verifier rejects it.\n\n"
        "REJECTION RULES:\n" + AUTOMATIC_ZERO_NOTE + "\n" + VERIFIER_FALSE_POSITIVES + "\n\n"
        "FINDINGS:\n" + numbered + "\n\nDIFF HUNKS FOR THE CITED FILES:\n" + diff_text
        + "\n\nOUTPUT CONTRACT (as stated at the top):\n" + _VERDICT_CONTRACT
    )


def chunk_findings(findings: list[Finding], hunks: dict[str, str],
                   budget: int = VERIFIER_PACKET_BUDGET) -> list[list[int]]:
    """Greedy, order-preserving index chunks whose rendered packet stays under `budget` bytes.
    A single finding whose packet alone exceeds the budget still gets its own chunk (its hunk
    is already capped by VERIFIER_HUNK_CAP, and the adapter truncates as a last resort)."""
    chunks: list[list[int]] = []
    cur: list[int] = []
    for i in range(len(findings)):
        trial = cur + [i]
        size = len(verifier_prompt([findings[j] for j in trial], hunks).encode())
        if cur and size > budget:
            chunks.append(cur)
            cur = [i]
        else:
            cur = trial
    if cur:
        chunks.append(cur)
    return chunks


def aggregate_votes(findings: list[Finding], seat_votes: list[dict]) -> list[dict]:
    """seat_votes[k] = {"votes": {idx: (verdict, reason)}, "errors": {idx: kind}} for seat k.
    An index absent from a seat's votes is an abstention. Drop ONLY when every seat returned a
    valid not-real; any abstention keeps. Score: 0 when dropped, else
    base + round((100 - base) * valid_real / VERIFIER_COUNT), base = the source's threshold."""
    records = []
    for idx, f in enumerate(findings):
        votes, real, not_real = [], 0, 0
        for seat, sv in enumerate(seat_votes, start=1):
            v = (sv.get("votes") or {}).get(idx)
            if v is None:
                votes.append({"seat": seat, "verdict": "abstain", "reason": "",
                              "error": (sv.get("errors") or {}).get(idx, "missing-vote")})
                continue
            verdict, reason = v
            votes.append({"seat": seat, "verdict": verdict, "reason": reason})
            if verdict == "real":
                real += 1
            else:
                not_real += 1
        kept = not_real < VERIFIER_COUNT
        base = SEC_THRESHOLD if f.source == "security-audit" else CODE_THRESHOLD
        score = 0 if not kept else base + round((100 - base) * real / VERIFIER_COUNT)
        records.append({"n": idx + 1, "source": f.source, "agent": f.agent, "path": f.path,
                        "line": f.line, "body_head": (f.body or "")[:160], "votes": votes,
                        "valid_real": real, "valid_not_real": not_real,
                        "abstain": VERIFIER_COUNT - real - not_real, "kept": kept,
                        "score": score, "fallback": kept and real == 0})
    return records


def _reused_note(reused: list[Finding]) -> str:
    if not reused:
        return ""
    return (f"\n\n{len(reused)} finding(s) on unchanged files reused from a prior run "
            "(not re-posted).")


class ReviewPhase:
    def __init__(self, llm, gh, depth: str = STANDARD, *, log=None, worktree: str | None = None):
        self.llm = llm
        self.gh = gh
        self.depth = max_depth(depth)      # anything but exactly "standard" is deep
        self._log = log or (lambda _msg: None)
        self.worktree = worktree
        self.last_votes: list[dict] = []     # vote records of the most recent run()

    def gates(self, cls: Classification) -> dict:
        b_hist = cls.has_php and cls.lines_php_changed > 50
        if self.depth == DEEP:
            b_hist = not cls.non_code_only
        b_comm = (not cls.non_code_only) and cls.has_comments_in_diff
        g = {
            "A": not (cls.non_code_only or cls.engine_only),
            "B_history": b_hist, "B_comments": b_comm,
            "B": b_hist or b_comm,
            "C": not (cls.non_code_only or not cls.has_modified or cls.lines_php_changed <= 50),
            "D": cls.has_e2e_specs,
            "security": cls.has_php,
        }
        if self.depth == DEEP:
            g["depth"] = DEEP              # deep-only key: a standard<->deep switch misses the cache
        return g

    def verify_findings(self, findings: list[Finding], filtered_diff: str) -> list[dict]:
        """VERIFIER_COUNT tool-enabled Sonnet verifiers vote per finding; returns aggregate records."""
        seat_votes = [{"votes": {}, "errors": {}} for _ in range(VERIFIER_COUNT)]
        if not findings:
            return []
        if not self.worktree:
            for sv in seat_votes:
                sv["errors"] = {i: "no-worktree" for i in range(len(findings))}
            self._log("phase4d verifier: no worktree path; all findings kept unverified")
            return aggregate_votes(findings, seat_votes)
        hunks = _diff_by_path(filtered_diff or "")
        chunks = chunk_findings(findings, hunks)
        jobs = [(seat, ci) for ci in range(len(chunks)) for seat in range(VERIFIER_COUNT)]

        def _one(job):
            seat, ci = job
            idxs = chunks[ci]
            prompt = verifier_prompt([findings[i] for i in idxs], hunks)
            try:
                raw = self.llm.call_tooled(
                    VERIFIER_PURPOSES[seat], VERIFIER_MODEL, prompt, cwd=self.worktree,
                    allowed_tools=VERIFIER_ALLOWED_TOOLS, denied_tools=VERIFIER_DENIED_TOOLS,
                    max_turns=VERIFIER_MAX_TURNS, timeout=VERIFIER_TIMEOUT,
                    max_budget_usd=VERIFIER_MAX_BUDGET_USD)
                data = schemas.unwrap_verdicts_envelope(extract_json(raw))
                schemas.validate_verdicts(data)
            except HarnessError as e:
                if e.kind in VERIFIER_TERMINAL_KINDS:
                    raise
                return seat, idxs, {}, e.kind
            except ValueError as e:          # extract_json found no JSON
                return seat, idxs, {}, f"unparseable: {str(e)[:80]}"
            votes = {}
            for v in data:
                n = v["n"]
                if 1 <= n <= len(idxs) and idxs[n - 1] not in votes:   # first vote per n wins
                    votes[idxs[n - 1]] = (v["verdict"], v.get("reason", ""))
            return seat, idxs, votes, None

        with concurrent.futures.ThreadPoolExecutor(max_workers=len(jobs)) as ex:
            results = [fut.result() for fut in [ex.submit(_one, j) for j in jobs]]
        for seat, idxs, votes, err in results:
            seat_votes[seat]["votes"].update(votes)
            for i in idxs:
                if i not in votes:
                    seat_votes[seat]["errors"][i] = err or "missing-vote"
        return aggregate_votes(findings, seat_votes)

    def _audit_votes(self, records: list[dict]) -> None:
        """Write <out_dir>/verifier-votes.json and one audit line per finding. Never raises."""
        out_dir = getattr(self.llm, "out_dir", None)
        if out_dir and records:
            try:
                os.makedirs(out_dir, exist_ok=True)
                with open(os.path.join(out_dir, "verifier-votes.json"), "w") as fh:
                    json.dump({"schema_version": 1, "verifier_count": VERIFIER_COUNT,
                               "records": records}, fh, indent=2, sort_keys=True)
            except OSError as e:
                self._log(f"phase4d verifier: could not write verifier-votes.json: {e}")
        for r in records:
            tally = f"real={r['valid_real']} not-real={r['valid_not_real']} abstain={r['abstain']}"
            word = "kept" if r["kept"] else "DROPPED"
            if r["source"] == "security-audit" and not r["kept"]:
                reasons = " | ".join(v["reason"][:120] for v in r["votes"] if v["verdict"] == "not-real")
                self._log(f"phase4d verifier: DROPPED security finding {r['path']}:{r['line']} "
                          f"({tally}) reasons: {reasons}")
            else:
                self._log(f"phase4d verifier: {word} [{r['source']}/{r['agent']}] "
                          f"{r['path']}:{r['line']} score={r['score']} {tally}"
                          + (" fallback" if r.get("fallback") else ""))

    def run(self, meta: dict, cls: Classification, plan: PlanInfo,
            reused: list[Finding] | None = None) -> tuple[list[Finding], dict, list[dict], list[str]]:
        gates = self.gates(cls)
        findings: list[Finding] = []
        degraded_agents: list[str] = []

        # Build the list of independent agent tasks (preserved submission order so that
        # finding indices are deterministic for the scorer's n=1..N mapping).
        # The "purpose", "sonnet" literals below are pinned by reviewcache.MODEL_PINS (greped by
        # test_model_pins_match_source), so the deep switch stays inline with them.
        tasks: list[tuple[str, str, str, str, str]] = []
        if gates["A"]:
            tasks.append(("A", "code-review", "review-agent-a", "sonnet" if self.depth != DEEP else "opus",
                          agent_a_prompt(meta, cls, plan)))
        if gates["B"]:
            tasks.append(("B", "code-review", "review-agent-b", "sonnet",
                          agent_b_prompt(meta, cls, gates["B_history"], gates["B_comments"],
                                         any_files=(self.depth == DEEP))))
        # Agent C (previous-PR feedback) requires live gh search — replay serves
        # recorded "no prior feedback"; a live variant would add a gh-read adapter.
        if gates["D"]:
            tasks.append(("D", "code-review", "review-agent-d", "sonnet",
                          agent_d_prompt(meta, cls)))
        if gates["security"]:
            tasks.append(("security", "security-audit", "security-audit", "sonnet" if self.depth != DEEP else "opus",
                          security_prompt(meta, cls, plan)))

        def _call_agent(task: tuple[str, str, str, str, str]):
            agent, source, purpose, model, prompt = task
            for attempt in range(2):
                try:
                    data = self.llm.call(purpose, model, prompt, schemas.validate_findings,
                                         normalizer=schemas.unwrap_findings_envelope)
                    return (agent, source, purpose, data, None)
                except HarnessError as e:
                    if e.kind != "llm-invalid-output":
                        raise                      # llm-fixture-missing, gh, etc. stay terminal
                    if attempt == 0:
                        continue  # retry once
            return (agent, source, purpose, [], purpose)  # degraded after both attempts

        # Run all independent agents concurrently; futures list preserves submission order.
        max_workers = max(len(tasks), 1)
        with concurrent.futures.ThreadPoolExecutor(max_workers=max_workers) as executor:
            futures = [executor.submit(_call_agent, task) for task in tasks]
            # f.result() re-raises any terminal exception from _call_agent
            results = [f.result() for f in futures]

        # Aggregate in stable submission order (A → B → D → security).
        for agent, source, purpose, data, degraded_purpose in results:
            if degraded_purpose is not None:
                degraded_agents.append(degraded_purpose)
            else:
                for f in data:
                    findings.append(Finding(source=source, agent=agent, path=f["path"],
                                            line=f["line"], body=f["body"]))

        self.last_votes = self.verify_findings(findings, cls.filtered_diff)
        for f, rec in zip(findings, self.last_votes):
            f.score = rec["score"]
        self._audit_votes(self.last_votes)

        surviving = [f for f in findings
                     if (f.score or 0) >= (SEC_THRESHOLD if f.source == "security-audit"
                                           else CODE_THRESHOLD)]
        scored = [{"source": f.source, "agent": f.agent, "path": f.path,
                   "line": f.line, "score": f.score,
                   "body_head": (f.body or "")[:160]}
                  for f in findings]

        pr = meta.get("number") or 0
        sha = meta.get("headRefOid") or meta.get("sha") or ""
        code = [f for f in surviving if f.source == "code-review"]
        sec = [f for f in surviving if f.source == "security-audit"]
        code_degraded = [p for p in degraded_agents if p.startswith("review-agent-")]
        # Interdiff mode: findings on unchanged files were posted by the run that produced
        # them; they are never re-posted, and their presence rules out "No issues found.".
        reused_code = [f for f in (reused or []) if f.source == "code-review"]
        reused_sec = [f for f in (reused or []) if f.source == "security-audit"]
        if code:
            self.gh.post_review_findings(pr, sha, "Code review", code)
        elif code_degraded:
            self.gh.post_review_summary(pr, "Code review",
                                        "Review unavailable - unparseable output from: "
                                        + ", ".join(code_degraded) + _reused_note(reused_code))
        elif reused_code:
            self.gh.post_review_summary(pr, "Code review", _reused_note(reused_code).strip())
        else:
            self.gh.post_review_summary(pr, "Code review", "No issues found.")
        if gates["security"]:
            if sec:
                self.gh.post_review_findings(pr, sha, "Security audit", sec)
            elif "security-audit" in degraded_agents:
                self.gh.post_review_summary(pr, "Security audit",
                                            "Review unavailable - unparseable output from: "
                                            "security-audit" + _reused_note(reused_sec))
            elif reused_sec:
                self.gh.post_review_summary(pr, "Security audit", _reused_note(reused_sec).strip())
            else:
                self.gh.post_review_summary(
                    pr, "Security audit",
                    "No security issues found. (XSS and input validation are enforced "
                    "by PHPStan custom rules.)")
        return surviving, gates, scored, degraded_agents
