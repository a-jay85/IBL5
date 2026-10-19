"""Phase 4D verifier votes: scored-row contract, verdict schema, vote aggregation,
verifier fan-out, and the vote record.

The first four tests are characterization tests: they pin the properties of 4D that
reviewcache, statefile and fidelity consume, and they held before the text-only scorer
was replaced.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import CODE_THRESHOLD, SEC_THRESHOLD, ReviewPhase
from harness.state import Classification, Finding, HarnessError, LlmCallRecord, PlanInfo, UsageLedger

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


class _DualLlm:
    """Toolless `call` serves the review agents; `call_tooled` serves the verifiers.
    Canned replies are keyed by purpose."""

    def __init__(self, canned: dict):
        self.canned = canned
        self.ledger = UsageLedger()
        self.tooled_calls: list[dict] = []          # kwargs of every call_tooled, in order
        self.out_dir = None
        self.workdir = None

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        data = self.canned[purpose]
        if normalizer is not None:
            data = normalizer(data)
        validate(data)
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"fake:{model}"))
        return data

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools=(), **kw):
        self.tooled_calls.append({"purpose": purpose, "model": model, "cwd": cwd,
                                  "allowed_tools": tuple(allowed_tools), "prompt": prompt, **kw})
        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        val = self.canned[purpose]
        if isinstance(val, dict) and "raise" in val:
            raise HarnessError(val["raise"]["kind"], val["raise"].get("detail", ""))
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"fake:{model}"))
        return val


class _RecordingGh:
    def __init__(self):
        self.findings_posts: list[tuple] = []      # (pr, sha, title, findings)
        self.summary_posts: list[tuple] = []       # (pr, title, text)

    def post_review_findings(self, pr, sha, title, findings):
        self.findings_posts.append((pr, sha, title, list(findings)))

    def post_review_summary(self, pr, title, text):
        self.summary_posts.append((pr, title, text))


def _cls(**kwargs) -> Classification:
    """Classification enabling agent A plus security (B, C, D off)."""
    c = Classification()
    c.has_php = True
    c.has_modified = True
    c.lines_php_changed = 10
    c.has_comments_in_diff = False
    c.non_code_only = False
    c.engine_only = False
    c.has_e2e_specs = False
    c.filtered_diff = ("diff --git a/ibl5/classes/TeamRepository.php b/ibl5/classes/TeamRepository.php\n"
                       "--- a/ibl5/classes/TeamRepository.php\n"
                       "+++ b/ibl5/classes/TeamRepository.php\n"
                       "@@ -85,3 +85,4 @@\n"
                       "+        $rows = $this->fetchAll('SELECT * FROM ibl_box_scores');\n")
    c.files = ["ibl5/classes/TeamRepository.php"]
    for k, v in kwargs.items():
        setattr(c, k, v)
    return c


META = {"number": 42, "title": "t", "headRefOid": "abc123"}

ONE_REAL = '[{"n": 1, "verdict": "real", "reason": "unbounded fetchAll confirmed at line 88"}]'
CANNED = {
    "review-agent-a": [{"path": "ibl5/classes/TeamRepository.php", "line": 88, "body": "unbounded fetchAll"}],
    "security-audit": [],
    "score-findings": [{"n": 1, "score": 90}],       # legacy scorer (ignored once verifiers land)
    "verify-findings-1": ONE_REAL, "verify-findings-2": ONE_REAL, "verify-findings-3": ONE_REAL,
}


def _run(canned=None, **phase_kw):
    llm = _DualLlm(dict(canned or CANNED))
    gh = _RecordingGh()
    result = ReviewPhase(llm, gh, **phase_kw).run(META, _cls(), PlanInfo())
    return result, llm, gh


def _key(f: Finding) -> tuple:
    return (f.source, f.agent, f.path, f.line, f.body)


# ---------------------------------------------------------------------------
# Characterization (pre-impl rows 1-4)
# ---------------------------------------------------------------------------


def test_scored_rows_keep_legacy_key_set():
    (findings, _gates, scored, _degraded), _llm, _gh = _run()
    assert scored, "expected at least one scored row"
    for row in scored:
        assert set(row) == {"source", "agent", "path", "line", "score", "body_head"}
    assert len(scored) == len(findings)


def test_thresholds_are_80_code_and_75_security():
    assert CODE_THRESHOLD == 80
    assert SEC_THRESHOLD == 75


def test_run_posts_only_a_subset_of_agent_findings():
    (findings, _gates, _scored, _degraded), _llm, gh = _run()
    agent_keys = {(s, a, p, ln, b) for (s, a, p, ln, b) in
                  (("code-review", "A", f["path"], f["line"], f["body"])
                   for f in CANNED["review-agent-a"])}
    posted = [f for (_pr, _sha, _title, fs) in gh.findings_posts for f in fs]
    assert posted, "expected the confirmed finding to be posted"
    posted_keys = [_key(f) for f in posted]
    assert len(posted_keys) == len(set(posted_keys)), "a finding was posted twice"
    for k in posted_keys:
        assert k in agent_keys, f"posted finding {k} was not returned by any agent"
    assert {_key(f) for f in findings} <= agent_keys


def test_run_returns_four_tuple():
    result, _llm, _gh = _run()
    assert len(result) == 4
    assert isinstance(result[1], dict)
    assert isinstance(result[2], list)
    assert isinstance(result[3], list)
