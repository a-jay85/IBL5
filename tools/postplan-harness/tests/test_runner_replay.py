import glob
import json
import os
import re
import subprocess
import sys
import tempfile
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.llm import FixtureLlm, extract_json
from harness.state import HarnessError, TerminalState, UsageLedger
from harness import ciwatch, schemas

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Replay runs reach fidelity's procedure lookup; see tests/conftest.py.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")


def load(slug):
    path = os.path.join(ROOT, "fixtures/scenarios", slug, "fixture.json")
    if not os.path.exists(path):
        pytest.skip(f"replay fixture absent (gitignored; regenerate via ./run replay): {slug}")
    with open(path) as fh:
        return json.load(fh)


CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "commit_subject": "chore: replay commit", "summary_md": "## Summary\n- x\n"},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}


def run_slug(slug, canned=CANNED):
    out = tempfile.mkdtemp(prefix=f"postplan-test-{slug}-")
    llm = FixtureLlm(UsageLedger(), canned)
    res = runner.run(load(slug), out, llm, mode="replay", headless=True)
    return res, out


def test_replay_mode_skips_manual_testing_and_leaves_empty_dict():
    """Row 19: replay run has skipped_reason 'replay-mode'; manual_testing stays absent from JSON."""
    res, out = _run_inline()
    assert res.manual_testing == {}
    with open(os.path.join(out, "result.json")) as fh:
        blob = json.load(fh)
    assert "manual_testing" not in blob


def test_docs_only_arms_no_review_calls():
    res, out = run_slug("backlog-l6-done")
    assert res.terminal == TerminalState.SHIPPED_ARMED
    assert res.classification.docs_only and res.classification.non_code_only
    purposes = [c.purpose for c in res.ledger.calls]
    assert "review-agent-a" not in purposes and "security-audit" not in purposes
    actions = [a["action"] for a in _actions(out)]
    assert "pr_merge_auto" in actions


def test_plan_auto_merge_false_holds():
    res, out = run_slug("request-event-logging")
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert any(c.number == 7 for c in res.arm.holds)
    assert "pr_merge_auto" not in [a["action"] for a in _actions(out)]
    # php diff -> review + security agents fired
    purposes = [c.purpose for c in res.ledger.calls]
    assert "review-agent-a" in purposes and "security-audit" in purposes
    assert res.phase5 == "pass"    # recorded phpunit+phpstan outputs judged


def test_manual_held_visual_pr():
    res, _ = run_slug("mobile-target-size-a11y-sitewide")
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert {c.number for c in res.arm.holds} >= {1, 7}


def test_finding_at_80_blocks_arming():
    canned = dict(CANNED)
    canned["review-agent-a"] = [{"path": "ibl5/x.php", "line": 3, "body": "real bug"}]
    canned["score-findings"] = [{"n": 1, "score": 85}]
    res, out = run_slug("request-event-logging", canned)
    assert any(c.number == 2 for c in res.arm.holds)
    acts = _actions(out)
    posted = [a for a in acts if a["action"] == "pr_review_findings"]
    assert posted and posted[0]["findings"][0]["score"] == 85


def test_llm_safety_verdict_can_add_hold():
    canned = dict(CANNED)
    canned["safety-verdict"] = {"holds": ["new UI needs visual judgment"]}
    res, _ = run_slug("backlog-l6-done", canned)
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert any(c.number == 9 for c in res.arm.holds)


def test_no_mutating_gh_commands_ever_executed():
    """The whole point: every side effect is a recorded intent."""
    _, out = run_slug("backlog-l6-done")
    for a in _actions(out):
        assert set(a) >= {"ts", "action"}   # typed records, not shell strings



def _fixture(**over):
    fx = {
        "slug": "synthetic-degrade",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 9999,
        "pr_meta": {"number": 9999, "title": "fix: synthetic",
                    "body": "## Manual Testing\n\nNo manual testing needed\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Synthetic plan\n\nBody with no matrix and no frontmatter.\n",
    }
    fx.update(over)
    return fx


class DegradingLlm(FixtureLlm):
    """FixtureLlm that raises llm-invalid-output for one purpose, exactly as the live bug does."""
    def __init__(self, ledger, canned, bad_purposes, kind="llm-invalid-output"):
        super().__init__(ledger, canned)
        self.bad, self.kind = set(bad_purposes), kind

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        if purpose in self.bad:
            raise HarnessError(self.kind, f"{purpose}: schema: findings must be a JSON array")
        return super().call(purpose, model, prompt, validate, max_retries, normalizer)


def _capture_git(monkeypatch):
    captured = []

    class _CapturingReplayGit(runner.ReplayGit):
        def __init__(self, fixture):
            super().__init__(fixture)
            captured.append(self)

    monkeypatch.setattr(runner, "ReplayGit", _CapturingReplayGit)
    return captured


def test_clean_rerun_with_open_pr_skips_pr_copy():
    """No pr-copy canned: calling it would raise llm-fixture-missing and fail the run."""
    out = tempfile.mkdtemp()
    canned = {k: v for k, v in CANNED.items() if k != "pr-copy"}
    fx = _fixture(clean_tree=True, head_subject="feat: add widget")
    fx["pr_meta"] = dict(fx["pr_meta"], title="feat: synthetic")
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), canned), mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    assert "pr-copy" not in [c.purpose for c in res.ledger.calls]
    assert "pr-copy skipped" in "\n".join(res.audit)
    assert "stripped model-authored" not in "\n".join(res.audit)
    # the live PR's feat: title still trips the human-signoff hold
    assert 8 in {c.number for c in res.arm.holds}


def test_clean_rerun_copy_carries_the_live_pr_title():
    class _Gh:
        def pr_exists(self): return True
        def pr_title(self): return "feat: x"

    class _Git:
        def has_changes_to_commit(self): return False
        def branch_head_subject(self): return "chore: last commit"

    copy, degraded = runner._pr_copy(None, _Git(), _Gh(), None, "slug", None, None,
                                     lambda m: None)
    assert copy == {"title": "feat: x", "commit_subject": "chore: last commit",
                    "summary_md": ""}
    assert degraded is False      # a skip is not a degradation; it must not hold arming


def test_dirty_rerun_with_open_pr_still_calls_pr_copy():
    out = tempfile.mkdtemp()
    res = runner.run(_fixture(), out, FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert "pr-copy" in [c.purpose for c in res.ledger.calls]


def test_bad_pr_copy_json_falls_back_to_commit_subject(monkeypatch):
    captured = _capture_git(monkeypatch)
    out = tempfile.mkdtemp()
    llm = DegradingLlm(UsageLedger(), CANNED, {"pr-copy"})
    res = runner.run(_fixture(head_subject="fix: real subject"), out, llm, mode="replay")
    assert captured[0].commit_messages[0].split("\n", 1)[0] == "fix: real subject"
    assert "pr-copy DEGRADED" in "\n".join(res.audit)
    # the run ships, but an unreviewed title never auto-merges
    assert res.terminal == TerminalState.DEGRADED
    assert "pr-copy" in res.degraded_agents
    assert not res.arm.armed
    assert "pr_merge_auto" not in [a["action"] for a in _actions(out)]


def test_bad_pr_copy_json_without_subject_opens_feat_pr():
    """No branch commit to borrow: the fallback title is feat:, so condition (8) holds."""
    out = tempfile.mkdtemp()
    llm = DegradingLlm(UsageLedger(), CANNED, {"pr-copy"})
    res = runner.run(_fixture(pr_number=None, pr_meta=None), out, llm, mode="replay")
    creates = [a for a in _actions(out) if a.get("action") == "pr_create"]
    assert creates and creates[-1]["title"] == "feat: synthetic-degrade"
    assert not res.arm.armed


def test_other_pr_copy_errors_still_fail():
    out = tempfile.mkdtemp()
    llm = DegradingLlm(UsageLedger(), CANNED, {"pr-copy"}, kind="llm-timeout")
    res = runner.run(_fixture(), out, llm, mode="replay")
    assert res.terminal == TerminalState.FAILED


def test_envelope_unwrap_reaches_findings():
    out = tempfile.mkdtemp()
    canned = dict(CANNED)
    canned["review-agent-a"] = {"findings": [{"path": "a.php", "line": 1, "body": "x"}]}
    canned["score-findings"] = [{"n": 1, "score": 85}]   # finding survives (≥80 threshold)
    llm = FixtureLlm(UsageLedger(), canned)
    res = runner.run(_fixture(), out, llm, mode="replay")
    assert res.terminal == TerminalState.SHIPPED_HELD   # surviving finding blocks arming
    assert any(f.path == "a.php" for f in res.findings)


def test_fenced_array_still_parses():
    fenced = "```json\n[{\"path\": \"a.php\", \"line\": 1, \"body\": \"x\"}]\n```"
    assert isinstance(extract_json(fenced), list)


def test_prose_then_array_still_parses():
    prose = "Findings:\n[{\"path\": \"a.php\", \"line\": 1, \"body\": \"x\"}]"
    assert isinstance(extract_json(prose), list)


def test_empty_reply_still_fails():
    import pytest
    with pytest.raises(ValueError, match="no parseable JSON in model reply"):
        extract_json("")


def test_prose_only_reply_still_fails():
    import pytest
    with pytest.raises(ValueError, match="no parseable JSON in model reply"):
        extract_json("I found no issues.")


def test_wrong_envelope_key_still_fails():
    data = schemas.unwrap_findings_envelope({"not_findings": []})
    try:
        schemas.validate_findings(data)
        assert False, "expected HarnessError"
    except HarnessError as e:
        assert e.kind == "schema" and "findings must be a JSON array" in str(e)


def test_string_findings_value_still_fails():
    data = schemas.unwrap_findings_envelope({"findings": "none"})
    try:
        schemas.validate_findings(data)
        assert False, "expected HarnessError"
    except HarnessError as e:
        assert e.kind == "schema" and "findings must be a JSON array" in str(e)


def test_degraded_run_holds_and_reports(tmp_path):
    out = str(tmp_path / "out")
    llm = DegradingLlm(UsageLedger(), CANNED, ["review-agent-a"])
    res = runner.run(_fixture(), out, llm, mode="replay")
    assert res.terminal == TerminalState.DEGRADED
    assert res.degraded_agents == ["review-agent-a"]
    assert res.arm is not None and not res.arm.armed
    c9 = next(c for c in res.arm.conditions if c.number == 9)
    assert c9.blocked and "review-agent-a" in c9.reason
    assert runner.exit_code_for(res) == 0
    acts = _actions(out)
    assert not any(a["action"] == "pr_merge_auto" for a in acts)
    assert any(a["action"] == "pr_edit_body" for a in acts)
    assert any("Review Unavailable" in json.dumps(a) for a in acts)
    assert any("review-agent-a" in json.dumps(a) for a in acts)
    assert any(a["action"] in ("pr_create", "pr_comment") for a in acts)
    from harness.armable import manual_testing_clearance
    assert manual_testing_clearance(_fixture()["pr_meta"]["body"]
                                    + "\n\n## Review Unavailable\n\nnote\n") == "CLEARED"


def test_degraded_rerun_skips_duplicate_section(tmp_path):
    """Re-run on a PR whose body already has ## Review Unavailable must not append a second one.

    This test does NOT rely on RecordingGh._body_override resetting between
    instantiations — which is exactly what masked the bug. Instead, the section
    is pre-loaded into the fixture's pr_meta.body so that a fresh RecordingGh
    (with _body_override=None) returns it from pr_body(), exactly as LiveGh
    would on a genuine re-run.
    """
    out = str(tmp_path / "out")
    pre_tagged_body = (
        "## Manual Testing\n\nNo manual testing needed\n"
        "\n\n## Review Unavailable\n\n"
        "The compiled post-plan harness could not parse the reply from: "
        "review-agent-a. Those checks did not run; auto-merge was not armed. "
        "Re-run the review or review this PR by hand before merging.\n"
    )
    fx = _fixture(pr_meta={"number": 9999, "title": "fix: synthetic",
                           "body": pre_tagged_body, "headRefOid": "deadbeef"})
    llm = DegradingLlm(UsageLedger(), CANNED, ["review-agent-a"])
    res = runner.run(fx, out, llm, mode="replay")
    assert res.terminal == TerminalState.DEGRADED
    acts = _actions(out)
    edit_acts = [a for a in acts if a["action"] == "pr_edit_body"]
    assert edit_acts, "expected at least one pr_edit_body action"
    final_body = edit_acts[-1]["body"]
    count = final_body.count("## Review Unavailable")
    assert count == 1, (
        f"## Review Unavailable duplicated on re-run: found {count} occurrences "
        f"(idempotency guard missing or ineffective)"
    )


def test_non_invalid_output_error_still_fails_run(tmp_path):
    out = str(tmp_path / "out")
    llm = DegradingLlm(UsageLedger(), CANNED, ["review-agent-a"], kind="llm-fixture-missing")
    res = runner.run(_fixture(), out, llm, mode="replay")
    assert res.terminal == TerminalState.FAILED


def test_clean_run_still_arms(tmp_path):
    out = str(tmp_path / "out")
    llm = FixtureLlm(UsageLedger(), CANNED)
    res = runner.run(_fixture(), out, llm, mode="replay")
    assert res.terminal == TerminalState.SHIPPED_ARMED
    acts = _actions(out)
    assert any(a["action"] == "pr_merge_auto" for a in acts)


def test_conflict_resolved_fixture_holds_condition_14(tmp_path):
    """4f replay hold — a branch carrying an auto-resolved rebase conflict never arms.

    Every other input is the clean fixture that arms in test_clean_run_still_arms, so the
    only thing separating the two runs is the conflict-resolved flag.
    """
    out = str(tmp_path / "out")
    res = runner.run(_fixture(conflict_resolved=True), out,
                     FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert 14 in {c.number for c in res.arm.holds}
    assert not any(a["action"] == "pr_merge_auto" for a in _actions(out))
    with open(os.path.join(out, "result.json")) as fh:
        blob = json.load(fh)
    c14 = [c for c in blob["arm"]["conditions"] if c["number"] == 14][0]
    assert c14["blocked"] is True
    assert "auto-resolved rebase conflict" in c14["reason"]


def test_conflict_verdict_fixture(tmp_path):
    """A CONFLICT-REVIEW=CLEAN verdict alongside the conflict flag clears condition 14.

    Pairs with test_conflict_resolved_fixture_holds_condition_14: same inputs except
    conflict_verdict is set, so condition (14) clears and the run arms.
    """
    out = str(tmp_path / "out")
    res = runner.run(
        _fixture(conflict_resolved=True, conflict_verdict="CONFLICT-REVIEW=CLEAN"),
        out, FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert res.terminal == TerminalState.SHIPPED_ARMED
    assert 14 not in {c.number for c in res.arm.holds}
    assert any(a["action"] == "pr_merge_auto" for a in _actions(out))
    with open(os.path.join(out, "result.json")) as fh:
        blob = json.load(fh)
    c14 = [c for c in blob["arm"]["conditions"] if c["number"] == 14][0]
    assert c14["blocked"] is False


def test_red_ci_checks_fixture_holds_condition_15(tmp_path):
    """4g replay hold — already-red CI checks at arm time hold auto-merge.

    Every other input is the clean fixture that arms in the baseline, so the only
    thing separating the two runs is the red_ci_checks seam value.
    """
    out = str(tmp_path / "out")
    res = runner.run(
        _fixture(red_ci_checks=["pytest (stdlib harness)"]), out,
        FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert 15 in {c.number for c in res.arm.holds}
    assert not any(a["action"] == "pr_merge_auto" for a in _actions(out))
    with open(os.path.join(out, "result.json")) as fh:
        blob = json.load(fh)
    c15 = [c for c in blob["arm"]["conditions"] if c["number"] == 15][0]
    assert c15["blocked"] is True
    assert "pytest (stdlib harness)" in c15["reason"]


def test_conformance_handoff_clean_run_writes_empty_bridge(tmp_path):
    out = str(tmp_path / "out")
    res = runner.run(_fixture(), out, FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert res.unresolved_conformance == []
    assert os.path.exists(os.path.join(out, runner.CONFORMANCE_DONE_NAME))
    bridge = os.path.join(out, runner.CONFORMANCE_BRIDGE_NAME)
    assert os.path.exists(bridge)
    # Phase 6.5 condition (3) tests the bridge with `[ -s "$BRIDGE" ]`. A
    # newline-only file is 1 byte and reads as "unresolved items exist".
    assert os.path.getsize(bridge) == 0


def test_conformance_handoff_lists_unresolved_items(tmp_path, monkeypatch):
    out = str(tmp_path / "out")
    items = [
        "MISSING: ibl5/tests/Http/FooTest.php (matrix planned a test the diff never wrote)",
        "MISSING-FILE: bin/check-plan (plan Critical File never appeared in the diff)",
    ]
    monkeypatch.setattr(runner.conformance, "check", lambda *a, **k: list(items))
    res = runner.run(_fixture(), out, FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert os.path.exists(os.path.join(out, runner.CONFORMANCE_DONE_NAME))
    with open(os.path.join(out, runner.CONFORMANCE_BRIDGE_NAME)) as fh:
        lines = fh.read().splitlines()
    assert lines == items
    assert lines == res.unresolved_conformance


def test_conformance_handoff_absent_when_phase5_never_ran(tmp_path):
    out = str(tmp_path / "out")
    res = runner.run(_fixture(diff=""), out, FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert res.terminal == TerminalState.NOTHING_TO_SHIP
    assert os.path.exists(os.path.join(out, "result.json"))
    assert not os.path.exists(os.path.join(out, runner.CONFORMANCE_DONE_NAME))
    assert not os.path.exists(os.path.join(out, runner.CONFORMANCE_BRIDGE_NAME))


def test_conformance_handoff_absent_when_conformance_raises(tmp_path, monkeypatch):
    out = str(tmp_path / "out")
    def boom(*a, **k):
        raise HarnessError("conformance-failed", "synthetic")
    monkeypatch.setattr(runner.conformance, "check", boom)
    res = runner.run(_fixture(), out, FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert res.terminal == TerminalState.FAILED
    assert not os.path.exists(os.path.join(out, runner.CONFORMANCE_DONE_NAME))


def _actions(out):
    p = os.path.join(out, "actions.jsonl")
    if not os.path.exists(p):
        return []
    with open(p) as fh:
        return [json.loads(l) for l in fh if l.strip()]


# ---------------------------------------------------------------------------
# Phase 6 inline tests — no load() / no gitignored fixture files
# ---------------------------------------------------------------------------

_INLINE_DIFF = """\
diff --git a/ibl5/classes/SomeService.php b/ibl5/classes/SomeService.php
new file mode 100644
--- /dev/null
+++ b/ibl5/classes/SomeService.php
@@ -0,0 +1,3 @@
+<?php
+class SomeService {}
"""

_INLINE_PLAN = """\
## Verification Matrix

| # | Test | Test type | Timing | Planned tests |
|---|------|-----------|--------|---------------|
| 1 | Eyeball the widget | Truly-manual | post-impl | none |
| 2 | Confirm the page loads | Truly-manual | post-impl | none |
"""

_INLINE_FIXTURE = {
    "slug": "inline-phase6-test",
    "diff": _INLINE_DIFF,
    "plan_content": _INLINE_PLAN,
    "pr_number": 999,
    "pr_meta": {"number": 999, "title": "chore: inline test", "body": "",
                "headRefOid": "abc123"},
    "verify": {"phpunit": None, "phpstan": None, "go": None},
    "checks_outcome": {"exit": 0, "failed": []},
    "probes": {},
}


def test_replay_commit_subject_is_not_the_pr_title(monkeypatch):
    """The commit subject comes from copy['commit_subject']; the PR title from copy['title'].

    CANNED deliberately sets the two to different strings, so this fails if runner.py is
    reverted to committing copy['title'], or if the two schema fields are collapsed into
    one. _INLINE_FIXTURE's diff is a production .php file, so no *_only flag is set and
    coerce_commit_subject returns the subject unchanged — the value asserted here is the
    fixture's, not a coercion artifact.
    """
    captured = []

    class _CapturingReplayGit(runner.ReplayGit):
        def __init__(self, fixture):
            super().__init__(fixture)
            captured.append(self)

    monkeypatch.setattr(runner, "ReplayGit", _CapturingReplayGit)
    res, out = _run_inline()

    assert len(captured) == 1, f"expected one ReplayGit instance, got {len(captured)}"
    messages = captured[0].commit_messages
    assert messages, "commit_all was never called — the commit path did not run"
    subject = messages[0].split("\n", 1)[0]

    assert subject == CANNED["pr-copy"]["commit_subject"] == "chore: replay commit"
    assert subject != CANNED["pr-copy"]["title"]
    assert not messages[0].startswith("chore: replay\n"), \
        "commit subject is the PR title — the two fields have been collapsed"

    # The PR-creation site deliberately keeps using copy['title'].
    creates = [a for a in _actions(out) if a.get("action") == "pr_create"]
    if creates:
        assert creates[-1].get("title") == CANNED["pr-copy"]["title"]


def _run_inline(canned_extra=None, probes=None):
    fixture = dict(_INLINE_FIXTURE)
    if probes is not None:
        fixture = dict(fixture, probes=probes)
    canned = dict(CANNED)
    if canned_extra:
        canned.update(canned_extra)
    out = tempfile.mkdtemp(prefix="postplan-test-inline-")
    from harness.adapters.probe import FixtureProbe
    probe = FixtureProbe(fixture)
    llm = FixtureLlm(UsageLedger(), canned)
    res = runner.run(fixture, out, llm, mode="replay", headless=True, probe=probe)
    return res, out


def test_phase6_all_rows_demoted_clears():
    """Test A: both rows probed and pass -> clearance CLEARED, body has sentinel,
    manual_demotions has two entries."""
    probes = {
        "bin/test-widget": True,
        "bin/test-page-load": True,
    }
    canned_recheck = [
        {"n": 1, "probe": ["bin/test-widget"]},
        {"n": 2, "probe": ["bin/test-page-load"]},
    ]
    res, out = _run_inline(
        canned_extra={"manual-recheck": canned_recheck},
        probes=probes,
    )
    # Both rows demoted -> sentinel
    assert "No manual testing needed" in (res.arm.conditions[0].name if res.arm else "")  \
        or res.terminal != TerminalState.FAILED  # ran without crash
    assert len(res.manual_demotions) == 2
    for dem in res.manual_demotions:
        assert dem["exit_ok"] is True
        assert "argv" in dem
    # PR body should contain sentinel, no checkbox line
    actions = _actions(out)
    body_edits = [a for a in actions if a.get("action") == "pr_edit_body"]
    if body_edits:
        body = body_edits[-1].get("body", "")
        assert "No manual testing needed" in body
        assert "- [ ]" not in body


def test_phase6_one_hold_stays_held():
    """Test B: one row holds, one demoted -> HELD, exactly one checkbox line,
    manual_demotions has exactly one entry."""
    probes = {
        "bin/test-page-load": True,
    }
    canned_recheck = [
        {"n": 1, "hold": True},
        {"n": 2, "probe": ["bin/test-page-load"]},
    ]
    res, out = _run_inline(
        canned_extra={"manual-recheck": canned_recheck},
        probes=probes,
    )
    assert len(res.manual_demotions) == 1
    assert res.manual_demotions[0]["exit_ok"] is True
    # PR body should have exactly one checkbox
    actions = _actions(out)
    body_edits = [a for a in actions if a.get("action") == "pr_edit_body"]
    if body_edits:
        body = body_edits[-1].get("body", "")
        assert "No manual testing needed" not in body
        checkboxes = [ln for ln in body.splitlines() if ln.strip().startswith("- [ ]")]
        assert len(checkboxes) == 1


# --- Phase 5: the sticky verdict comment, end to end -------------------------

class ScriptedToolLlm(FixtureLlm):
    """FixtureLlm whose tooled calls follow a script: one reply per purpose, in order.

    `review()` and `re_review()` both go through `call_tooled`, so a per-purpose list is
    what lets one run hand back verdict 1 and then a different verdict 2.
    """
    def __init__(self, ledger, canned, scripts):
        super().__init__(ledger, dict(canned))
        self.scripts = {k: list(v) for k, v in scripts.items()}

    def call_tooled(self, purpose, model, prompt, **kw):
        queue = self.scripts.get(purpose)
        if queue:
            self.canned[purpose] = queue.pop(0)
        return super().call_tooled(purpose, model, prompt, **kw)


def _verdict_doc(word, tree_line=None, notes="Check 3: the diff matches the plan."):
    doc = f"## 6d checks\n\n{notes}\n\n{word}\n"
    if tree_line:
        doc += tree_line + "\n"
    return doc + "\n## DIGEST\n\n**What changed:** a synthetic replay change\n"


REPLAY_TREE_2 = format(2, "040x")   # ReplayGit.head_tree after the phase-2 + remediation commits


@pytest.fixture
def sticky_tmp():
    """Yields a fresh pr number and removes the /tmp verdict files it leaves behind."""
    used = []

    def _next(n):
        used.append(n)
        return n

    yield _next
    for n in used:
        for path in glob.glob(f"/tmp/post-plan-fidelity-*-{n}*"):
            try:
                os.unlink(path)
            except OSError:
                pass


def _sticky_run(tmp_path, pr, scripts, **over):
    out = str(tmp_path / f"out{pr}")
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""       # opts the fixture into the real Phase 5.5 path
    llm = ScriptedToolLlm(UsageLedger(), canned, scripts)
    fx = _fixture(pr_number=pr, **over)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, out, llm, mode="replay")
    return res, out


def _sticky_body(out):
    posts = [a for a in _actions(out) if a["action"] == "pr_sticky_verdict"]
    assert len(posts) == 1, posts
    return posts[0]["body"]


def test_replay_a_clean_run_posts_the_sticky_then_arms(tmp_path, sticky_tmp):
    """(a) The comment is posted BEFORE `--auto`, because `--auto` can merge instantly."""
    pr = sticky_tmp(7101)
    res, out = _sticky_run(tmp_path, pr,
                           {"plan-fidelity-review": [_verdict_doc("READY")]})
    assert res.terminal == TerminalState.SHIPPED_ARMED
    acts = [a["action"] for a in _actions(out)]
    assert acts.count("pr_merge_auto") == 1
    assert acts.index("pr_sticky_verdict") < acts.index("pr_merge_auto")
    assert _sticky_body(out).splitlines()[-1] == "<!-- pr-ready-verdict -->"


def test_replay_b_remediated_then_clean_says_so_and_arms(tmp_path, sticky_tmp):
    pr = sticky_tmp(7102)
    res, out = _sticky_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("READY")],
    })
    assert res.terminal == TerminalState.SHIPPED_ARMED
    body = _sticky_body(out)
    assert body.splitlines()[-2].startswith("READY (re-review)")
    with open(os.path.join(out, "result.json")) as fh:
        blob = json.load(fh)
    assert blob["fidelity"]["selected_source"] == "verdict-2"


def test_replay_c_failing_re_review_holds_and_never_arms(tmp_path, sticky_tmp):
    pr = sticky_tmp(7103)
    res, out = _sticky_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("NOT READY")],
        "plan-fidelity-re-review-3": [_verdict_doc("NOT READY")],
        "plan-fidelity-re-review-4": [_verdict_doc("NOT READY")],
    })
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert not any(a["action"] == "pr_merge_auto" for a in _actions(out))
    body = _sticky_body(out)
    assert "NOT READY (re-review)" in body
    assert "- (12) plan-fidelity-verdict" in body


def test_replay_d_prose_after_the_tree_falls_back_to_verdict_1(tmp_path, sticky_tmp):
    """The parser is anchored, so a trailing word makes the tree unreadable — and a
    verdict 2 with no readable tree is stale, never a pass."""
    pr = sticky_tmp(7104)
    res, out = _sticky_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [
            _verdict_doc("READY", f"REVIEWED_TREE={REPLAY_TREE_2} after the rebase")],
    })
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert 12 in {c.number for c in res.arm.holds}
    with open(os.path.join(out, "result.json")) as fh:
        blob = json.load(fh)
    assert blob["fidelity"]["selected_source"] == "verdict-1"


def test_replay_e_a_moved_head_makes_verdict_2_stale(tmp_path, sticky_tmp):
    pr = sticky_tmp(7105)
    res, out = _sticky_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("READY")],
    }, current_tree="f" * 40)
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert 12 in {c.number for c in res.arm.holds}


class _TwoShaGit(runner.ReplayGit):
    """commit_all hands back a distinct sha per commit, so the remediation commit can
    be told apart from the Phase-2 one (ReplayGit returns the same sha for both)."""

    def commit_all(self, message):
        super().commit_all(message)
        return f"replay-sha-{len(self.commit_messages)}"


def test_replay_f_phase7_keys_ci_on_the_remediation_commit(tmp_path, sticky_tmp, monkeypatch):
    """After a Phase 5.5 remediation commit, Phase 7's CI head is that commit — the
    Phase-2 sha is no longer the PR head, so a watch keyed on it reports the wrong tree."""
    monkeypatch.setattr(runner, "ReplayGit", _TwoShaGit)
    pr = sticky_tmp(7106)
    res, _ = _sticky_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("READY")],
    })
    assert res.fidelity["remediation_sha"] == "replay-sha-2"
    assert res.ci_head == "replay-sha-2"


def test_replay_g_phase7_keeps_the_phase2_commit_without_remediation(tmp_path, sticky_tmp, monkeypatch):
    monkeypatch.setattr(runner, "ReplayGit", _TwoShaGit)
    pr = sticky_tmp(7107)
    res, _ = _sticky_run(tmp_path, pr, {"plan-fidelity-review": [_verdict_doc("READY")]})
    assert res.fidelity.get("remediation_sha") is None
    assert res.ci_head == "replay-sha-1"


def test_replay_a_failed_sticky_post_does_not_change_arming(tmp_path, sticky_tmp):
    """The skill's own post is `|| true`. A new hold here would change what arming means."""
    pr = sticky_tmp(7106)
    res, out = _sticky_run(tmp_path, pr,
                           {"plan-fidelity-review": [_verdict_doc("READY")]},
                           sticky_comment_id="")
    assert res.sticky_error == "sticky-post-failed"
    assert res.sticky_comment_id is None
    assert res.terminal == TerminalState.SHIPPED_ARMED
    assert [a["action"] for a in _actions(out)].count("pr_merge_auto") == 1


def test_fidelity_push_failure_exits_1_for_full_fallback(tmp_path, sticky_tmp, monkeypatch):
    """A push-failed HarnessError from the remediation commit propagates as rc=1.

    The harness now re-raises push-failed out of _run_fidelity so the top-level handler
    sets terminal=FAILED; the launcher has no resume arm, so rc=4 is gone and any
    non-0/3 exit goes to the full /post-plan skill fallback.
    """
    pr = sticky_tmp(7110)
    _orig_push = runner.ReplayGit.push

    def _push(self):
        if (self.commit_messages
                and self.commit_messages[-1].startswith("chore: address Phase 5.5")):
            raise HarnessError("push-failed", "remote rejected")
        return _orig_push(self)

    monkeypatch.setattr(runner.ReplayGit, "push", _push)

    res, out = _sticky_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
    })

    assert res.terminal is TerminalState.FAILED
    assert res.error_kind == "push-failed"
    assert runner.exit_code_for(res) == 1
    assert not any(a["action"] == "pr_merge_auto" for a in _actions(out))
    assert "RESULT: post-plan FAILED" in runner.verdict_line(res, 1)


def test_dead_remediation_round_log_names_subtype(tmp_path, sticky_tmp):
    """Phase 6a — when call_tooled raises on fidelity-remediation, the runner logs the subtype.

    The detail string Phase 4 now produces is fed through the runner's 300-char why-collapse
    and must survive intact (it is 83 chars). Mutation: shrink [:300] to [:40] and the subtype
    falls off the logged line; or revert Phase 4 and the canned detail is the only place the
    subtype appears, which the adapter test already catches.
    """
    pr = sticky_tmp(7120)
    detail = ("fidelity-remediation: envelope is_error=True subtype=error_max_turns "
              "result='Reached max turns (60)'")
    res, out = _sticky_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": [{"raise": {"kind": "llm-tooled-error", "detail": detail}}],
    })
    with open(os.path.join(out, "audit.log")) as fh:
        log_lines = fh.read().splitlines()
    assert any(
        "phase5.5 round 1: remediation unavailable (llm-tooled-error)" in l
        and "subtype=error_max_turns" in l
        for l in log_lines
    ), f"expected dead-round log line with subtype; log:\n" + "\n".join(log_lines)


def test_sticky_bodies_are_gitignored():
    """LiveGh writes the body under the run dir; it must never show up as a repo change."""
    proc = subprocess.run(
        ["git", "check-ignore", "-q", "tools/postplan-harness/out/run/sticky-verdict-1.md"],
        cwd=os.path.join(ROOT, "..", ".."),
    )
    assert proc.returncode == 0


# ---------------------------------------------------------------------------
# Phase 9 / 10 / 11 tests
# ---------------------------------------------------------------------------

def test_retrospective_failure_keeps_armed_terminal(tmp_path, sticky_tmp):
    """Phase 9 HarnessError is non-fatal: terminal stays shipped-armed."""
    pr = sticky_tmp(7107)
    out = str(tmp_path / f"out{pr}")
    canned = {k: v for k, v in CANNED.items() if k != "retrospective"}
    canned["plan-fidelity-review"] = ""
    llm = ScriptedToolLlm(UsageLedger(), canned,
                          {"plan-fidelity-review": [_verdict_doc("READY")]})
    fx = _fixture(pr_number=pr)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, out, llm, mode="replay")

    assert res.terminal == TerminalState.SHIPPED_ARMED
    acts = _actions(out)
    assert [a["action"] for a in acts].count("pr_merge_auto") == 1
    assert runner.exit_code_for(res) == 0
    with open(os.path.join(out, "result.json")) as fh:
        blob = json.load(fh)
    assert blob["retrospective"] == {"save": False, "error": "llm-fixture-missing"}


def test_phase10_and_11_logged(tmp_path, sticky_tmp):
    """Phase 10 log line follows Phase 9; Phase 11 logs even on a failed run."""
    # (a) clean run
    pr = sticky_tmp(7108)
    res, out = _sticky_run(tmp_path, pr,
                           {"plan-fidelity-review": [_verdict_doc("READY")]})
    with open(os.path.join(out, "audit.log")) as fh:
        log_lines = fh.read().splitlines()

    phase9_idx = next(i for i, l in enumerate(log_lines) if "phase9:" in l)
    phase10_idx = next(i for i, l in enumerate(log_lines)
                       if "phase10: preview environment skipped" in l)
    phase11_idx = next(i for i, l in enumerate(log_lines)
                       if "phase11: no adapter scratch to remove" in l)
    assert phase9_idx < phase10_idx < phase11_idx

    # Failed run: pr-copy missing causes HarnessError -> FAILED terminal
    pr2 = sticky_tmp(7109)
    out2 = str(tmp_path / f"out{pr2}")
    canned_no_copy = {k: v for k, v in CANNED.items() if k != "pr-copy"}
    canned_no_copy["plan-fidelity-review"] = ""
    llm2 = ScriptedToolLlm(UsageLedger(), canned_no_copy, {})
    fx2 = _fixture(pr_number=pr2)
    fx2["pr_meta"] = dict(fx2["pr_meta"], number=pr2)
    res2 = runner.run(fx2, out2, llm2, mode="replay")
    assert res2.terminal == TerminalState.FAILED
    with open(os.path.join(out2, "audit.log")) as fh:
        log2 = fh.read()
    assert "phase11: no adapter scratch to remove" in log2


def test_phase11_cleanup_error_ignored(tmp_path, sticky_tmp):
    """close() raising must not change the terminal state."""
    pr = sticky_tmp(7110)
    out = str(tmp_path / f"out{pr}")

    class BoomLlm(ScriptedToolLlm):
        def close(self):
            raise RuntimeError("boom")

    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""
    llm = BoomLlm(UsageLedger(), canned,
                  {"plan-fidelity-review": [_verdict_doc("READY")]})
    fx = _fixture(pr_number=pr)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, out, llm, mode="replay")

    assert res.terminal == TerminalState.SHIPPED_ARMED
    with open(os.path.join(out, "audit.log")) as fh:
        log = fh.read()
    assert "phase11: cleanup error ignored" in log
# ---------------------------------------------------------------------------
# Phase 2 background CI watch — replay cases (a)–(e). See plan phase 6.3.
# ---------------------------------------------------------------------------


class _LiveShapedGit(runner.ReplayGit):
    """ReplayGit plus the two methods runner.run only calls when live=True.

    head() deliberately returns a different value than commit_all(), so case (b)
    proves the background watch is keyed on the POST-rebase head SHA.
    """

    POST_REBASE_SHA = "postrebase0000000000000000000000000000ab"

    def rebase_onto(self, base="origin/master"):
        return

    def head(self):
        return self.POST_REBASE_SHA


class _FakePopen:
    """gh pr checks stand-in for the live-shaped run: always green, never spawns."""

    def __init__(self, *a, **k):
        self.returncode = 0
        self.args = a[0] if a else []

    # Phase 5 posts the sticky verdict through subprocess.run, which uses Popen as a
    # context manager. Without these the fake stands in only for the direct Popen calls.
    def __enter__(self):
        return self

    def __exit__(self, *exc):
        return False

    def communicate(self, input=None, timeout=None):
        return "", ""

    def poll(self):
        return self.returncode

    def terminate(self):
        return

    def kill(self):
        return

    def wait(self, timeout=None):
        return self.returncode


def _run_live_shaped(monkeypatch, out, canned_extra=None):
    """Replay fixtures driven down the live-shaped arm of runner.run.

    mode="replay" keeps git/gh fake (no network, no pushes) while live=True turns
    on exactly the branches this plan touches: the Phase 2 watch start, the
    Phase 7 reuse, and the reap in the finally.
    """
    from harness.adapters.probe import FixtureProbe

    monkeypatch.setattr(runner, "ReplayGit", _LiveShapedGit)
    canned = dict(CANNED)
    if canned_extra:
        canned.update(canned_extra)
    llm = FixtureLlm(UsageLedger(), canned)
    return runner.run(_INLINE_FIXTURE, out, llm, mode="replay", headless=True,
                      live=True, probe=FixtureProbe(_INLINE_FIXTURE))


def _ci_files(out):
    return [f for f in os.listdir(out) if f.startswith("ci-") and f.endswith(".json")]


def test_replay_starts_no_background_watch(monkeypatch, tmp_path):
    """(a) A plain replay run never touches CI: no watch, no outcome file."""
    started = []
    monkeypatch.setattr(ciwatch, "start_background_watch",
                        lambda *a, **k: started.append(a))

    out = str(tmp_path / "out")
    from harness.adapters.probe import FixtureProbe
    llm = FixtureLlm(UsageLedger(), CANNED)
    runner.run(_INLINE_FIXTURE, out, llm, mode="replay", headless=True,
               probe=FixtureProbe(_INLINE_FIXTURE))

    assert started == [], "a replay run started a background CI watch"
    assert _ci_files(out) == []


def test_live_push_starts_background_watch_with_head_sha(monkeypatch, tmp_path):
    """(b) The watch is keyed on the post-rebase head, not the pre-rebase commit."""
    seen = []
    monkeypatch.setattr(ciwatch, "start_background_watch",
                        lambda w, pr, sha, out_dir, **k: seen.append((pr, sha, out_dir)))
    monkeypatch.setattr(ciwatch, "watch_live",
                        lambda *a, **k: ciwatch.CiOutcome(0, [], "stub watch_live"))

    out = str(tmp_path / "out")
    _run_live_shaped(monkeypatch, out)

    assert len(seen) == 1, f"expected exactly one background watch, got {len(seen)}"
    pr, sha, out_dir = seen[0]
    assert pr == _INLINE_FIXTURE["pr_number"]
    assert sha == _LiveShapedGit.POST_REBASE_SHA
    assert not sha.startswith("replay-sha-"), "keyed on the pre-rebase commit SHA"
    assert out_dir == out


def _live_arm_c15(monkeypatch, tmp_path, probe_result, bg=None):
    """Run the live-shaped arm and return condition (15)'s ConditionResult."""
    monkeypatch.setattr(ciwatch, "start_background_watch", lambda *a, **k: bg)
    monkeypatch.setattr(ciwatch, "watch_live",
                        lambda *a, **k: ciwatch.CiOutcome(0, [], "stub watch_live"))
    monkeypatch.setattr(ciwatch, "probe_failed_checks", lambda w, pr: list(probe_result))
    monkeypatch.setattr(ciwatch, "reap_background_watch", lambda *a, **k: None)
    res = _run_live_shaped(monkeypatch, str(tmp_path / "out"))
    return [c for c in res.arm.conditions if c.number == 15][0]


def test_live_probe_failure_holds_condition_15(monkeypatch, tmp_path):
    """The live arm actually calls probe_failed_checks.

    runner.py's condition-(15) block is inside `if live and pr:` and is wrapped in a
    broad try/except that falls back to []. Every other test in this file runs
    live=False, so without this case a typo in an attribute name would degrade the
    condition to a silent no-op in production while the suite stayed green.
    """
    c15 = _live_arm_c15(monkeypatch, tmp_path, ["pytest (stdlib harness)"])
    assert c15.blocked is True
    assert "pytest (stdlib harness)" in c15.reason


def test_live_probe_clean_clears_condition_15(monkeypatch, tmp_path):
    """Negative control: an empty probe must not manufacture a hold."""
    c15 = _live_arm_c15(monkeypatch, tmp_path, [])
    assert c15.blocked is False


def test_live_finished_background_watch_feeds_condition_15(monkeypatch, tmp_path):
    """A background watch that already resolved to `failure` contributes its names.

    Exercises the bg_ci.done / bg_ci.status / bg_ci.failed attribute reads, which the
    bg=None cases above short-circuit past. A rename on any of the three raises inside
    the try/except and silently clears the hold; this case fails instead.
    """
    bg = ciwatch.BackgroundWatch(sha="deadbeef", pr=_INLINE_FIXTURE["pr_number"],
                                 worktree=".", path=str(tmp_path / "ci.json"),
                                 started=0.0)
    bg.status, bg.failed = "failure", ["E2E Tests"]
    bg.done.set()
    c15 = _live_arm_c15(monkeypatch, tmp_path, [], bg=bg)
    assert c15.blocked is True
    assert "E2E Tests" in c15.reason


def test_phase7_reuses_background_outcome(monkeypatch, tmp_path):
    """(c) A terminal outcome for this head makes Phase 7 a lookup, not a watch."""
    out = tmp_path / "out"
    out.mkdir()
    sha = _LiveShapedGit.POST_REBASE_SHA
    (out / f"ci-{sha}.json").write_text(json.dumps({
        "sha": sha, "pr": _INLINE_FIXTURE["pr_number"], "status": "success",
        "exit_code": 0, "failed_checks": [], "probe": [],
        "evidence": "seeded", "started": 0.0, "finished": 1.0,
    }, indent=2, sort_keys=True))

    monkeypatch.setattr(ciwatch, "start_background_watch", lambda *a, **k: None)

    def boom(*a, **k):
        raise AssertionError("watch_live ran despite a reusable outcome on disk")

    monkeypatch.setattr(ciwatch, "watch_live", boom)

    res = _run_live_shaped(monkeypatch, str(out))
    assert res.ci_outcome == "green"


def test_background_watch_is_reaped_on_harness_error(monkeypatch, tmp_path):
    """(d) A failure after the push still reaps the watcher and still reports."""
    handle = types.SimpleNamespace(
        path="/tmp/ci-sentinel.json", sha=_LiveShapedGit.POST_REBASE_SHA)
    monkeypatch.setattr(ciwatch, "start_background_watch", lambda *a, **k: handle)
    reaped = []
    monkeypatch.setattr(ciwatch, "reap_background_watch",
                        lambda bg, **k: reaped.append(bg))

    class _ExplodingReviewPhase:
        def __init__(self, llm, gh):
            pass

        def run(self, *a, **k):
            raise HarnessError("forced-failure", "raised after the phase 2 push")

    monkeypatch.setattr(runner, "ReviewPhase", _ExplodingReviewPhase)

    out = str(tmp_path / "out")
    res = _run_live_shaped(monkeypatch, out)

    assert reaped == [handle], "the watcher was not reaped on the error path"
    assert res.terminal == TerminalState.FAILED
    assert res.error_kind == "forced-failure"
    assert os.path.exists(os.path.join(out, "result.json"))


def test_background_watch_is_reaped_on_normal_completion(monkeypatch, tmp_path):
    """(e) The happy path reaps exactly once and leaves a settled outcome file.

    Paired with (d): (e) alone still passes if the reap sits inside the try body,
    so the pair is what pins it to the finally.
    """
    monkeypatch.setattr(ciwatch.subprocess, "Popen", _FakePopen)
    reaped = []
    real_reap = ciwatch.reap_background_watch

    def counting_reap(bg, **k):
        reaped.append(bg)
        return real_reap(bg, **k)

    monkeypatch.setattr(ciwatch, "reap_background_watch", counting_reap)

    out = str(tmp_path / "out")
    res = _run_live_shaped(monkeypatch, out)

    assert len(reaped) == 1 and reaped[0] is not None
    path = os.path.join(out, f"ci-{_LiveShapedGit.POST_REBASE_SHA}.json")
    data = json.loads(open(path).read())
    assert data["status"] in ("success", "failure", "timeout")
    assert res.ci_outcome == "green"


# ---------------------------------------------------------------------------
# audit.log as a LIVE progress signal. bin/fleet-status reads this file to render
# a harness row mid-run; before it was written incrementally, every harness row
# showed "(no output yet — headless runs flush at exit)" for the whole run,
# because the other two sources fleet-status tries are both empty on this engine
# (the harness prints nothing until exit, and its bounded `claude -p` calls carry
# no --session-id for the transcript glob to key on).
# ---------------------------------------------------------------------------


class _AuditSnoopLlm(ScriptedToolLlm):
    """Snapshots audit.log ON DISK at the first LLM call, i.e. while run() is mid-flight.

    Reading the file after run() returns would pass even with the old exit-time-only
    write, so the assertion has to happen from inside the run. An LLM call is the
    hook because it is the only harness seam a test can occupy mid-phase.
    """

    def __init__(self, ledger, canned, scripts, audit_path):
        super().__init__(ledger, canned, scripts)
        self._audit_path = audit_path
        self.snapshot = None

    def _snap(self):
        if self.snapshot is not None:
            return
        try:
            with open(self._audit_path) as fh:
                self.snapshot = fh.read()
        except OSError:
            self.snapshot = ""

    def call(self, purpose, model, prompt, *a, **kw):
        self._snap()
        return super().call(purpose, model, prompt, *a, **kw)

    def call_tooled(self, purpose, model, prompt, **kw):
        self._snap()
        return super().call_tooled(purpose, model, prompt, **kw)


def _snoop_run(tmp_path, pr, scripts, **over):
    """_sticky_run with an LLM that captures the on-disk audit.log mid-run."""
    out = str(tmp_path / f"out{pr}")
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""
    llm = _AuditSnoopLlm(UsageLedger(), canned, scripts,
                         os.path.join(out, "audit.log"))
    fx = _fixture(pr_number=pr, **over)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, out, llm, mode="replay")
    return res, out, llm


def test_audit_log_is_populated_mid_run(tmp_path, sticky_tmp):
    """audit.log carries phase lines BEFORE the run ends — the whole point of the mirror."""
    pr = sticky_tmp(7130)
    res, out, llm = _snoop_run(tmp_path, pr,
                               {"plan-fidelity-review": [_verdict_doc("READY")]})
    assert llm.snapshot is not None, "the run made no LLM call, so nothing was sampled"
    assert "phase1 plan:" in llm.snapshot, llm.snapshot
    # The sampled text must match the format bin/fleet-status greps for: `[HH:MM:SS] phase…`
    first = llm.snapshot.splitlines()[0]
    assert re.match(r"^\[\d{2}:\d{2}:\d{2}\] phase", first), first


def test_audit_log_final_bytes_unchanged_by_the_mirror(tmp_path, sticky_tmp):
    """_finish still rewrites the file whole; the live append must not double or reorder it."""
    pr = sticky_tmp(7131)
    res, out, _ = _snoop_run(tmp_path, pr,
                             {"plan-fidelity-review": [_verdict_doc("READY")]})
    with open(os.path.join(out, "audit.log")) as fh:
        on_disk = fh.read()
    assert on_disk == "\n".join(res.audit) + "\n"


def test_audit_log_truncated_for_a_reused_out_dir(tmp_path, sticky_tmp):
    """A stale tail read as live progress is worse than the placeholder — so truncate."""
    pr = sticky_tmp(7132)
    out = str(tmp_path / f"out{pr}")
    os.makedirs(out, exist_ok=True)
    with open(os.path.join(out, "audit.log"), "w") as fh:
        fh.write("[00:00:00] phase9: LEFTOVER FROM A PREVIOUS RUN\n")

    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""
    llm = _AuditSnoopLlm(UsageLedger(), canned,
                         {"plan-fidelity-review": [_verdict_doc("READY")]},
                         os.path.join(out, "audit.log"))
    fx = _fixture(pr_number=pr)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    runner.run(fx, out, llm, mode="replay")

    assert "LEFTOVER" not in (llm.snapshot or ""), llm.snapshot
    with open(os.path.join(out, "audit.log")) as fh:
        assert "LEFTOVER" not in fh.read()


# ---------------------------------------------------------------------------
# Phase 2c: Post-remediation conformance re-run
# ---------------------------------------------------------------------------

_PLAN_WITH_TEST_FOO = (
    "# Synthetic conformance re-run test plan\n\n"
    "## Verification Matrix\n\n"
    "| # | What | Test type | Timing | Test file |\n"
    "|---|------|-----------|--------|----------|\n"
    "| 1 | foo passes | PHPUnit | post-impl | `tests/test_foo.py` |\n"
)


def _patch_fidelity_with_remediation(monkeypatch, remediation_sha=None):
    """Monkeypatch _run_fidelity to set a READY verdict and optionally a remediation_sha."""
    def _fake(llm, out_dir, worktree, git, gh, plan, diff, body, pr, master_sha,
              reviewed_tree, live, log, res, before_remediation=None):
        reviewed = reviewed_tree or "a" * 40
        verdict_file = os.path.join(out_dir, "verdict.md")
        with open(verdict_file, "w") as fh:
            fh.write("READY\n")
        rounds = ([] if not remediation_sha else
                  [{"remediation_sha": remediation_sha, "verdict": "READY",
                    "reviewed_tree": reviewed, "verdict_path": verdict_file}])
        res.fidelity = {
            "verdict_1": "READY",
            "error_kind": None,
            "reviewed_tree": reviewed,
            "verdict_path": verdict_file,
            "remediation_sha": remediation_sha,
            "verdict_2": None,
            "reviewed_tree_2": None,
            "rounds": rounds,
            "rounds_completed": len(rounds),
            "backlog_issue_numbers": [],
        }
        return "READY", ""

    monkeypatch.setattr(runner, "_run_fidelity", _fake)


def _patch_two_call_git(monkeypatch, first_files, second_files):
    """Make ReplayGit.changed_files() return first_files on call 1, second_files on call 2+.

    Returns a list whose first element is the total call count so tests can assert on it.
    """
    call_count = [0]

    class _TwoCallGit(runner.ReplayGit):
        def changed_files(self, base="origin/master"):
            call_count[0] += 1
            return first_files if call_count[0] == 1 else second_files

    monkeypatch.setattr(runner, "ReplayGit", _TwoCallGit)
    return call_count


def test_conformance_rerun_clears_hold_when_remediation_adds_planned_file(tmp_path, monkeypatch):
    """Remediation adds planned file → second changed_files sees it → conformance clean.

    Mutation caught: delete the re-run block and unresolved keeps the first-pass
    MISSING item, so the empty-list assertion fails.
    """
    _patch_fidelity_with_remediation(monkeypatch, remediation_sha="fake-remediation-sha")
    _patch_two_call_git(
        monkeypatch,
        first_files=["runner.py"],
        second_files=["runner.py", "tools/postplan-harness/tests/test_foo.py"],
    )
    out = str(tmp_path / "out")
    os.makedirs(out)
    fx = _fixture(plan_content=_PLAN_WITH_TEST_FOO)
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), CANNED), mode="replay")

    assert res.unresolved_conformance == []
    bridge = os.path.join(out, runner.CONFORMANCE_BRIDGE_NAME)
    assert os.path.exists(bridge) and os.path.getsize(bridge) == 0
    assert "phase5.0 conformance (post-remediation): clean" in "\n".join(res.audit)


def test_conformance_hold_stays_when_remediation_does_not_add_file(tmp_path, monkeypatch):
    """Remediation commits an unrelated edit → MISSING survives, bridge non-empty.

    Mutation caught: replace the re-run with `unresolved = []`.
    """
    _patch_fidelity_with_remediation(monkeypatch, remediation_sha="fake-remediation-sha")
    _patch_two_call_git(
        monkeypatch,
        first_files=["runner.py"],
        second_files=["runner.py"],
    )
    out = str(tmp_path / "out")
    os.makedirs(out)
    fx = _fixture(plan_content=_PLAN_WITH_TEST_FOO)
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), CANNED), mode="replay")

    assert any("MISSING" in item and "test_foo.py" in item
               for item in (res.unresolved_conformance or []))
    bridge = os.path.join(out, runner.CONFORMANCE_BRIDGE_NAME)
    assert os.path.exists(bridge) and os.path.getsize(bridge) > 0


def test_conformance_not_rerun_without_remediation(tmp_path, monkeypatch):
    """READY with no remediation_sha → changed_files() called exactly once.

    Mutation caught: drop the `if res.fidelity.get('remediation_sha')` gate
    and the call count becomes 2.
    """
    _patch_fidelity_with_remediation(monkeypatch, remediation_sha=None)
    call_count = _patch_two_call_git(
        monkeypatch,
        first_files=["runner.py"],
        second_files=["runner.py", "tools/postplan-harness/tests/test_foo.py"],
    )
    out = str(tmp_path / "out")
    os.makedirs(out)
    fx = _fixture(plan_content=_PLAN_WITH_TEST_FOO)
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), CANNED), mode="replay")

    assert call_count[0] == 1, f"expected 1 changed_files call, got {call_count[0]}"
    assert not any("post-remediation" in line for line in res.audit)


def test_arm_inputs_see_post_remediation_conformance(tmp_path, monkeypatch):
    """Condition (3) uses post-remediation result; hold not fired when remediation adds file.

    Mutation caught: re-run conformance into a fresh local name without assigning back
    to `unresolved`, so condition (3) still holds despite the planned file landing.
    """
    _patch_fidelity_with_remediation(monkeypatch, remediation_sha="fake-remediation-sha")
    _patch_two_call_git(
        monkeypatch,
        first_files=["runner.py"],
        second_files=["runner.py", "tools/postplan-harness/tests/test_foo.py"],
    )
    out = str(tmp_path / "out")
    os.makedirs(out)
    fx = _fixture(plan_content=_PLAN_WITH_TEST_FOO)
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), CANNED), mode="replay")

    assert res.arm is not None
    assert 3 not in {c.number for c in res.arm.holds}, \
        "condition (3) must not hold when remediation added the planned file"
