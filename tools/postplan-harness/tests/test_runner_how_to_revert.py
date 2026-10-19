"""Runner wiring for the advisory How to revert field: `_inject_how_to_revert` (Phase 2
injection after Merge danger) and the `pr_copy_prompt` request."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import how_to_revert
from harness.llm_calls import pr_copy_prompt
from harness.state import Classification, PlanInfo


def _diff(path, status="M"):
    head = f"diff --git a/{path} b/{path}\n"
    if status == "A":
        head += "new file mode 100644\n"
    return head + f"--- a/{path}\n+++ b/{path}\n@@ -1 +1 @@\n-old\n+new\n"


def _worktree(tmp_path, files):
    (tmp_path / "bin").mkdir(exist_ok=True)
    (tmp_path / "bin" / "check-x").write_text("#!/bin/sh\nexit 0\n")
    for rel, text in files.items():
        p = tmp_path / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text)
    return str(tmp_path)


def _revert_lines(body):
    return [ln for ln in body.splitlines() if ln.startswith(how_to_revert.FIELD_PREFIX)]


def test_phase2_line_lands_after_merge_danger(tmp_path):
    wt = _worktree(tmp_path, {"ibl5/classes/Trade/A.php": "<?php\n"})
    diff = _diff("ibl5/classes/Trade/A.php")
    copy = {"summary_md": "## Summary\n- change\n\n## Test plan\n- ran it"}
    logs = []
    runner._inject_merge_danger(copy, wt, diff, logs.append)
    runner._inject_how_to_revert(copy, diff, logs.append)
    lines = copy["summary_md"].splitlines()
    md_idx = [i for i, ln in enumerate(lines) if ln.startswith("**Merge danger:**")]
    assert len(md_idx) == 1
    assert len(_revert_lines(copy["summary_md"])) == 1
    assert lines[md_idx[0] + 1].startswith(how_to_revert.FIELD_PREFIX)


def test_inject_how_to_revert_returns_no_finding():
    copy = {"summary_md": "## Summary\n- change"}
    logs = []
    result = runner._inject_how_to_revert(copy, _diff("docs/a.md"), logs.append)
    assert result is None
    assert len(logs) == 1
    assert logs[0].startswith("phase2: how-to-revert source=")


def test_inject_how_to_revert_skips_empty_summary():
    copy = {"summary_md": ""}
    logs = []
    runner._inject_how_to_revert(copy, _diff("docs/a.md"), logs.append)
    assert copy["summary_md"] == ""
    assert logs == []


def test_inject_how_to_revert_swallows_module_error(monkeypatch):
    def _boom(body, diff_text):
        raise ValueError("boom")

    monkeypatch.setattr(runner.how_to_revert, "apply_to_body", _boom)
    body = "## Summary\n- change"
    copy = {"summary_md": body}
    logs = []
    runner._inject_how_to_revert(copy, _diff("docs/a.md"), logs.append)
    assert copy["summary_md"] == body
    assert "phase2: how-to-revert skipped (boom)" in logs


def test_migration_diff_gets_mechanical_extra_step():
    copy = {"summary_md": "## Summary\n- change\n\n"
                          f"{how_to_revert.FIELD_PREFIX} just revert the merge commit please"}
    logs = []
    runner._inject_how_to_revert(copy, _diff("ibl5/migrations/NNN_slug.sql", status="A"), logs.append)
    [line] = _revert_lines(copy["summary_md"])
    assert line.rstrip(".").endswith(how_to_revert.STEP_MIGRATION)
    assert "phase2: how-to-revert source=mechanical" in logs


def test_pr_copy_prompt_requests_how_to_revert_line():
    assert "**How to revert:**" in pr_copy_prompt("some-slug", Classification(), PlanInfo(), "")
