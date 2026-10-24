"""Arming condition (20): the run wires the owner-approval state into ArmInputs, and an
owner-approval hold posts a marker-free notice on the PR."""
import glob
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import owner_approvals as oa
from harness.state import UsageLedger
from test_fixer_off_limits import _RecordingLlm, _owner_comment
from test_runner_replay import CANNED, _actions, _fixture, _verdict_doc

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")


@pytest.fixture
def verdict_cleanup():
    used = []
    yield used.append
    for n in used:
        for path in glob.glob(f"/tmp/post-plan-fidelity-*-{n}*"):
            try:
                os.unlink(path)
            except OSError:
                pass


def _fx(pr, **over):
    fx = _fixture(pr_number=pr, **over)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    return fx


def test_run_passes_clear_state_to_arm_inputs_when_no_records(tmp_path, verdict_cleanup):
    pr = 7401
    verdict_cleanup(pr)
    llm = _RecordingLlm(UsageLedger(), dict(CANNED), {})
    res = runner.run(_fx(pr), str(tmp_path / "out"), llm, mode="replay")
    assert res.owner_approval_state == "CLEAR"
    assert 20 not in {c.number for c in res.arm.holds}
    assert 20 in {c.number for c in res.arm.conditions}


def test_run_revert_hold_posts_marker_free_notice(tmp_path, verdict_cleanup, monkeypatch):
    pr = 7402
    verdict_cleanup(pr)
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""
    llm = _RecordingLlm(UsageLedger(), canned, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("READY")],
    })
    hit = oa.RevertHit("oad-0123abcd", "ibl5/x.php", "keep-lost", "if ($teamId === 0) { return; }")
    monkeypatch.setattr(oa, "check_commit", lambda git, snap, sha: [hit])
    out = str(tmp_path / "out")
    res = runner.run(_fx(pr, pr_issue_comments=[_owner_comment()]), out, llm, mode="replay")
    assert res.error_kind == "owner-approval-revert", (res.error_kind, res.error)
    assert res.owner_approval_state == "REVERT"
    assert runner.exit_code_for(res) == 3
    posted = [a for a in _actions(out) if a["action"] == "pr_comment"]
    assert posted, [a["action"] for a in _actions(out)]
    body = posted[-1]["body"]
    assert "owner-approved deviation oad-0123abcd (ibl5/x.php, keep-lost)" in body
    assert oa.APPROVAL_MARKER not in body
    assert not [a for a in _actions(out) if a["action"] in ("push", "pr_push")]
