"""runner._run_fidelity end to end for READY WITH NOTES runs and the body-notes step."""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.ghad import RecordingGh
from harness.state import UsageLedger

import runner
from test_fidelity_rounds import (TREE_1, _counting_git, _cleanup, _plan, _Res,  # noqa: F401
                                   _ScriptedLlm, git_shim)

RWN_NO_BODY_NOTE = (
    "6d checks\n\n## FINDINGS\n\n"
    "- [NOTE] `runner.py` could cache the parsed plan; follow up later.\n\n"
    "READY WITH NOTES\n")


class _GhSpy(RecordingGh):
    def __init__(self, *a, **kw):
        super().__init__(*a, **kw)
        self.edits: list = []

    def pr_edit_body(self, pr, body):
        self.edits.append((pr, body))
        return super().pr_edit_body(pr, body)


def test_rwn_without_body_notes_makes_no_fix_call(tmp_path, git_shim):
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [RWN_NO_BODY_NOTE],
        "fidelity-notes": [[]],
    })
    gh = _GhSpy(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), _counting_git(), gh,
                             _plan(), "diff", "body", 9960, "dead" * 10, TREE_1, False,
                             lambda _m: None, res)
        assert not [p for p, _m in llm.calls if p.startswith("plan-fidelity-body-notes")]
        assert gh.edits == []
        assert res.fidelity["remediation_sha"] is None
    finally:
        _cleanup(9960)
