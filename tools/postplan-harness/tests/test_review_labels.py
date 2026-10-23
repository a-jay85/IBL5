"""Label classification through ReviewPhase.run: reviewer hints, verifier agreement, order.

Each test names the mutation it exists to kill. Canned replies cover agents A, B, D, and
security per .claude/rules/post-plan-concurrency-docs.md.
"""
from __future__ import annotations

import json
import os
import sys
import threading

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import review
from harness.review import ReviewPhase
from harness.state import Classification, HarnessError, LlmCallRecord, PlanInfo, UsageLedger

PATH = "ibl5/x.php"

# Right-side lines: 1 ctx, 2 added, 3 ctx, 4 ctx | 21 ctx, 22 added, 23 ctx.
DIFF = (
    "diff --git a/ibl5/x.php b/ibl5/x.php\n"
    "--- a/ibl5/x.php\n"
    "+++ b/ibl5/x.php\n"
    "@@ -1,3 +1,4 @@\n"
    " line1\n"
    "+added2\n"
    " line3\n"
    " line4\n"
    "@@ -20,2 +21,3 @@\n"
    " ctx21\n"
    "+added22\n"
    " ctx23\n"
)
CONTEXT_LINE, ADDED_LINE = 3, 2


class _Llm:
    """No-sleep canned LLM adapter that records every prompt by purpose."""

    def __init__(self, canned: dict):
        self.canned = canned
        self.prompts: dict = {}
        self.ledger = UsageLedger()
        self._lock = threading.Lock()

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        """Verifier seat k votes real for finding n when k <= canned real_votes (0 = all reject)."""
        seat = int(purpose.rsplit("-", 1)[1])
        out = []
        for e in self.canned["verdicts"]:
            real = seat <= e["real_votes"]
            out.append({"n": e["n"], "verdict": "real" if real else "not-real",
                        "preexisting": bool(e.get("preexisting")) and real, "reason": "stub"})
        return json.dumps(out)

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        with self._lock:
            self.prompts[purpose] = prompt
        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        data = self.canned[purpose]
        if normalizer is not None:
            data = normalizer(data)
        validate(data)
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"stub:{model}"))
        return data


class _Gh:
    def __init__(self):
        self.findings: dict = {}
        self.summaries: list = []

    def post_review_findings(self, pr, sha, title, findings):
        self.findings[title] = list(findings)

    def post_review_summary(self, pr, title, body):
        self.summaries.append((title, body))


def _cls() -> Classification:
    c = Classification()
    c.has_php = True
    c.has_modified = True
    c.lines_php_changed = 100
    c.has_comments_in_diff = True
    c.non_code_only = False
    c.engine_only = False
    c.has_e2e_specs = False
    c.filtered_diff = DIFF
    c.files = [PATH]
    return c


def _finding(line=CONTEXT_LINE, **extra):
    return {"path": PATH, "line": line, "body": f"defect at {line}", **extra}


def _canned(a=None, security=None, verdicts=None, b=None, d=None):
    return {"review-agent-a": a or [], "review-agent-b": b or [],
            "review-agent-d": d or [], "security-audit": security or [],
            "verdicts": verdicts or []}


def _run(canned):
    llm, gh = _Llm(canned), _Gh()
    ReviewPhase(llm, gh, worktree="/tmp/wt").run({}, _cls(), PlanInfo())
    return llm, gh


def _posted(gh, title="Code review"):
    return gh.findings.get(title, [])


def _v(n, real_votes=3, preexisting=False):
    return {"n": n, "real_votes": real_votes, "preexisting": preexisting}


def test_preexisting_finding_posts_with_label_when_reviewer_and_verifiers_agree():
    """Mutation: drop the f.scorer_preexisting assignment in run => label becomes important."""
    _, gh = _run(_canned(a=[_finding(preexisting=True)], verdicts=[_v(1, 3, True)]))
    assert [f.label for f in _posted(gh)] == ["pre-existing"]


def test_reviewer_preexisting_without_verifier_agreement_stays_important():
    """Mutation: make classify ignore scorer_preexisting => fails."""
    _, gh = _run(_canned(a=[_finding(preexisting=True)], verdicts=[_v(1, 3, False)]))
    assert [f.label for f in _posted(gh)] == ["important"]


def test_preexisting_needs_every_real_voter_to_agree():
    """Mutation: use any() instead of all real voters => a dissenting seat is ignored."""
    llm, gh = _Llm(_canned(a=[_finding(preexisting=True)])), _Gh()
    seats = iter([True, False, True])

    def tooled(purpose, model, prompt, *, cwd, **kw):
        return json.dumps([{"n": 1, "verdict": "real", "preexisting": next(seats), "reason": "x"}])

    llm.call_tooled = tooled
    ReviewPhase(llm, gh, worktree="/tmp/wt").run({}, _cls(), PlanInfo())
    assert [f.label for f in _posted(gh)] == ["important"]


def test_preexisting_hint_on_added_line_stays_important():
    """Mutation: pass {} as `changed` into classify => fails."""
    _, gh = _run(_canned(a=[_finding(line=ADDED_LINE, preexisting=True)],
                         verdicts=[_v(1, 3, True)]))
    assert [f.label for f in _posted(gh)] == ["important"]


def test_nit_hint_cannot_demote_threshold_finding():
    """Mutation: check the nit branch before the threshold branch => fails."""
    _, gh = _run(_canned(a=[_finding(severity="nit")], verdicts=[_v(1)]))
    assert [f.label for f in _posted(gh)] == ["important"]


def _run_with_scores(canned, scores):
    """Run with the real verifier records, then overwrite each score.

    Verifier scoring keeps every finding at or above its threshold, so the nit band
    (NIT_FLOOR <= score < threshold) is unreachable on post-plan today. Injecting the
    scores tests what happens after scoring: classify, the nit cap, sort and posting."""
    llm, gh = _Llm(canned), _Gh()
    phase = ReviewPhase(llm, gh, worktree="/tmp/wt")
    real_verify = phase.verify_findings

    def verify(findings, filtered_diff):
        records = real_verify(findings, filtered_diff)
        for rec, score in zip(records, scores):
            rec["score"] = score
        return records

    phase.verify_findings = verify
    phase.run({}, _cls(), PlanInfo())
    return gh


def test_nit_band_posts_as_nit_and_cap_is_five():
    """Mutation: remove apply_nit_cap in run => all 7 nits post."""
    a = [_finding(line=1, severity="nit") for _ in range(7)]
    gh = _run_with_scores(_canned(a=a, verdicts=[_v(n) for n in range(1, 8)]),
                          [61, 62, 63, 64, 65, 66, 67])
    posted = _posted(gh)
    assert [f.label for f in posted] == ["nit"] * 5
    assert [f.score for f in posted] == [67, 66, 65, 64, 63]


def test_security_nit_hint_is_dropped_and_security_preexisting_is_labeled():
    """Mutation: delete the source == "code-review" guard in classify => the security nit posts.

    The score is 70, below SEC_THRESHOLD (75), so the nit branch is the only way it posts."""
    sec = [_finding(line=1, severity="nit"), _finding(line=CONTEXT_LINE, preexisting=True)]
    gh = _run_with_scores(_canned(security=sec, verdicts=[_v(1), _v(2, 3, True)]), [70, 76])
    posted = _posted(gh, "Security audit")
    assert [(f.line, f.label) for f in posted] == [(CONTEXT_LINE, "pre-existing")]


def test_security_preexisting_is_labeled():
    """Mutation: skip the security source in the verifier preexisting assignment => fails."""
    sec = [_finding(line=CONTEXT_LINE, preexisting=True)]
    _, gh = _run(_canned(security=sec, verdicts=[_v(1, 3, True)]))
    posted = _posted(gh, "Security audit")
    assert [(f.line, f.label) for f in posted] == [(CONTEXT_LINE, "pre-existing")]


def test_posted_order_is_important_then_preexisting():
    """Mutation: remove sort_by_severity => submission order (pre-existing first) is posted."""
    a = [_finding(line=CONTEXT_LINE, preexisting=True), _finding(line=ADDED_LINE)]
    _, gh = _run(_canned(a=a, verdicts=[_v(1, 3, True), _v(2)]))
    assert [f.label for f in _posted(gh)] == ["important", "pre-existing"]


def test_verifier_prompt_asks_for_preexisting_and_does_not_reject_old_defects():
    """Mutation: revert the verifier contract/false-positive text => fails."""
    assert '"preexisting"' in review._VERDICT_CONTRACT
    assert "do not reject it for being old" in review.VERIFIER_FALSE_POSITIVES


def test_contract_text_lists_optional_keys_and_bug_class():
    """Mutation: revert the 2a contract text => fails."""
    contract = review.OUTPUT_CONTRACT
    for token in ('"bug_class"', '"other_instances"', '"preexisting"', '"severity"'):
        assert token in contract
    assert 'no "severity"' not in contract
    assert '"bug_class" (a short kebab-case name' in contract
    assert '"other_instances" (a JSON array of "path:line" strings' in contract
    assert "IS reportable" in review.AUTOMATIC_ZERO_NOTE


def test_unvoted_finding_is_kept_not_preexisting():
    """Mutation: treat an abstaining panel as preexisting-agreeing => the label flips."""
    a = [_finding(line=1), _finding(line=CONTEXT_LINE, preexisting=True)]
    _, gh = _run(_canned(a=a, verdicts=[_v(1)]))
    posted = _posted(gh)
    assert [(f.line, f.label) for f in posted] == [(1, "important"), (CONTEXT_LINE, "important")]


def test_unanimous_not_real_drops_finding():
    """Mutation: keep a finding every verifier rejected => it posts."""
    a = [_finding(line=1), _finding(line=CONTEXT_LINE)]
    _, gh = _run(_canned(a=a, verdicts=[_v(1), _v(2, 0)]))
    assert [f.line for f in _posted(gh)] == [1]
