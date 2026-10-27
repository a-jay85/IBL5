"""Runner wiring for condition (20): `_inject_removed_guards` (Phase 2 body block) and
`_removed_guards_holds` (the arm-time detector rerun that feeds `ArmInputs`)."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import removed_guards

SEASON_DIFF = (
    "diff --git a/ibl5/classes/Season/Season.php b/ibl5/classes/Season/Season.php\n"
    "--- a/ibl5/classes/Season/Season.php\n"
    "+++ b/ibl5/classes/Season/Season.php\n"
    "@@ -1,4 +1,1 @@\n"
    "-        try {\n"
    "-        } catch (\\RuntimeException) {\n"
    " }\n"
)


def _stub_detector(tmp_path, rc):
    lib = tmp_path / "bin" / "lib"
    lib.mkdir(parents=True)
    script = lib / "deleted-guards"
    script.write_text(f"#!/bin/sh\ncat >/dev/null\nexit {rc}\n")
    script.chmod(0o755)
    return str(tmp_path)


def test_runner_injects_block_for_flagged_removal():
    copy = {"summary_md": "## Summary\n- change"}
    logs = []
    assert runner._inject_removed_guards(copy, None, SEASON_DIFF, None, logs.append) is True
    body = copy["summary_md"]
    assert removed_guards.BEGIN in body and removed_guards.END in body
    assert "`try@ibl5/classes/Season/Season.php#" in body
    assert removed_guards.PLACEHOLDER in body
    assert any(ln.startswith("phase2 removed-guard: try@") for ln in logs)
    # The rendered placeholder holds until a human writes a reason.
    assert any(h.startswith("removed-guards:UNJUSTIFIED:")
               for h in removed_guards.classify_body(body, []))


def test_runner_arm_inputs_carry_detector_holds():
    # No block in the body: the rerun's flagged keys hold as UNLISTED.
    holds = runner._removed_guards_holds(None, SEASON_DIFF, None, "## Summary\n- change")
    assert any(h.startswith("removed-guards:UNLISTED:try@ibl5/classes/Season/Season.php#")
               for h in holds)
    # The same keys listed with substantive reasons arm.
    ok, rows = removed_guards.run_detector(runner._detector_root(None), SEASON_DIFF, None)
    assert ok and rows
    body = removed_guards.render_block(
        rows, {r[0]: "moved into SeasonRepository::load" for r in rows})
    assert runner._removed_guards_holds(None, SEASON_DIFF, None, body) == []
    # Wiring: the ArmInputs construction passes the computed holds.
    src = open(runner.__file__).read()
    arm_at = src.index("inputs = ArmInputs(")
    assert src.index("_rg_holds = _removed_guards_holds(") < arm_at
    assert src.index("removed_guards_holds=_rg_holds,") > arm_at
    assert src.index("_inject_removed_guards(copy, plan, diff, worktree, log)") > \
        src.index('_inject_scope_notes(copy, plan, files, diff, copy["summary_md"], log)')


def test_detector_root_ignores_worktree_copy(tmp_path):
    wt = _stub_detector(tmp_path, 2)
    assert runner._detector_root(wt) == runner.conformance._HARNESS_REPO_ROOT


def test_runner_detector_error_holds(tmp_path, monkeypatch):
    wt = _stub_detector(tmp_path, 2)
    monkeypatch.setattr(runner, "_detector_root", lambda worktree: wt)
    assert runner._removed_guards_holds(wt, SEASON_DIFF, None, "") == \
        ["removed-guards:DETECTOR-ERROR"]
    copy = {"summary_md": "## Summary"}
    logs = []
    assert runner._inject_removed_guards(copy, None, SEASON_DIFF, wt, logs.append) is False
    assert copy["summary_md"] == "## Summary"
    assert "phase2 removed-guards: detector error" in logs
