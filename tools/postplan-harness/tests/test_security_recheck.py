"""The fix round's security recheck: a blind Opus re-review of the post-fix tree."""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import ReviewPhase
from harness.state import Classification, Finding, PlanInfo
from harness.thread_ingestion import fixed_body

FIXER_SENTINEL = "FIXER-TRANSCRIPT-SENTINEL"
FINDING_SENTINEL = "ORIGINAL-FINDING-SENTINEL"


class _CapturingLlm:
    """Records (purpose, model, prompt) for every agent call; every agent finds nothing."""

    def __init__(self):
        self.calls: list[tuple[str, str, str]] = []

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.calls.append((purpose, model, prompt))
        data = []
        if normalizer is not None:
            data = normalizer(data)
        validate(data)
        return data

    def call_tooled(self, purpose, model, prompt, **kw):
        self.calls.append((purpose, model, prompt))
        return "[]"


class _Gh:
    def __init__(self):
        self.summaries: list[tuple[str, str]] = []

    def post_review_findings(self, pr, sha, title, findings):
        pass

    def post_review_summary(self, pr, title, body):
        self.summaries.append((title, body))


def _cls(**kw) -> Classification:
    c = Classification()
    c.has_php = True
    c.has_modified = True
    c.non_code_only = False
    c.engine_only = False
    c.has_e2e_specs = False
    c.filtered_diff = "+<?php echo htmlspecialchars($name);\n"
    c.files = ["ibl5/s.php"]
    for k, v in kw.items():
        setattr(c, k, v)
    return c


def _reused_security_finding() -> Finding:
    return Finding(source="security-audit", agent="security", path="ibl5/s.php", line=3,
                   body=f"Escape the name. {FINDING_SENTINEL}", score=90)


def _security_calls(llm):
    return [c for c in llm.calls if c[0] == "security-audit"]


def test_security_recheck_prompt_is_blind_and_opus(tmp_path):
    llm = _CapturingLlm()
    reply = fixed_body("abc1234", f"Escaped the name. {FIXER_SENTINEL}")
    assert FIXER_SENTINEL in reply
    ReviewPhase(llm, _Gh(), worktree=str(tmp_path)).run(
        {}, _cls(), PlanInfo(), reused=[_reused_security_finding()], security_model="opus")
    sec = _security_calls(llm)
    assert len(sec) == 1
    _purpose, model, prompt = sec[0]
    assert model == "opus"
    for banned in (FIXER_SENTINEL, FINDING_SENTINEL, "addressed", "remediation", "fix round"):
        assert banned not in prompt, banned


def test_security_recheck_runs_when_gate_is_off(tmp_path):
    llm = _CapturingLlm()
    phase = ReviewPhase(llm, _Gh(), worktree=str(tmp_path))
    cls = _cls(has_php=False)
    assert not phase.gates(cls)["security"]
    _f, gates, _s, _d = phase.run({}, cls, PlanInfo(), security_model="opus")
    assert [c[1] for c in _security_calls(llm)] == ["opus"]
    assert gates["security"] is False          # the cached gate record is unchanged


def test_security_recheck_drops_reused_security_findings(tmp_path):
    llm = _CapturingLlm()
    gh = _Gh()
    ReviewPhase(llm, gh, worktree=str(tmp_path)).run(
        {}, _cls(), PlanInfo(), reused=[_reused_security_finding()], security_model="opus")
    assert len(_security_calls(llm)) == 1
    sec = [b for t, b in gh.summaries if t == "Security audit"]
    assert sec and not sec[0].startswith("No security issues found.")
    assert "1 finding(s) on unchanged files" in sec[0]
    assert "`ibl5/s.php:3`" in sec[0]


def test_plain_review_keeps_reused_security_note(tmp_path):
    llm = _CapturingLlm()
    gh = _Gh()
    ReviewPhase(llm, gh, worktree=str(tmp_path)).run(
        {}, _cls(), PlanInfo(), reused=[_reused_security_finding()])
    assert [c[1] for c in _security_calls(llm)] == ["sonnet"]
    sec = [b for t, b in gh.summaries if t == "Security audit"]
    assert sec and "reused" in sec[0]


def test_accepted_round_summary_names_kept_security_finding(tmp_path):
    """An accepted round's Opus recheck keeps a non-holding security finding on an
    unchanged file: the posted summary must name it, never say "No security issues found."."""
    import dataclasses
    import runner
    from harness import armable
    from harness.labels import LABEL_NIT

    kept = _reused_security_finding()
    kept.label = LABEL_NIT              # non-holding, so the round's recheck accepts
    assert armable.holding_findings([kept]) == []
    llm = _CapturingLlm()
    gh = _Gh()
    phase = ReviewPhase(llm, gh, worktree=str(tmp_path))
    cls = dataclasses.replace(_cls(), filtered_diff=(
        "diff --git a/ibl5/t.php b/ibl5/t.php\n--- a/ibl5/t.php\n+++ b/ibl5/t.php\n"
        "@@ -1 +1 @@\n+<?php echo 1;\n"))
    findings, _g, _s, _d = runner._interdiff_review(
        phase, {}, cls, PlanInfo(), ["ibl5/t.php"], [kept], [], lambda m: None,
        security_model="opus")
    assert kept in findings
    sec = [b for t, b in gh.summaries if t == "Security audit"]
    assert len(sec) == 1
    assert "No security issues found." not in sec[0]
    assert "1 finding(s) on unchanged files" in sec[0]
    assert "`ibl5/s.php:3`" in sec[0]
