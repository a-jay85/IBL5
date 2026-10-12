"""Runner wiring for the Merge danger field: `_inject_merge_danger` (Phase 2 injection plus the
body-check validator) and the `pr_copy_prompt` request."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import merge_danger
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


def _run(copy, worktree, diff):
    logs = []
    findings = runner._inject_merge_danger(copy, worktree, diff, logs.append)
    return findings, logs


def _field_lines(body):
    return [ln for ln in body.splitlines() if ln.startswith("**Merge danger:**")]


def test_inject_adds_field_when_llm_omitted_it(tmp_path):
    wt = _worktree(tmp_path, {"ibl5/classes/Trade/A.php": "<?php\n"})
    copy = {"summary_md": "## Summary\n- change"}
    findings, logs = _run(copy, wt, _diff("ibl5/classes/Trade/A.php"))
    lines = _field_lines(copy["summary_md"])
    assert len(lines) == 1
    assert copy["summary_md"].rstrip().endswith(lines[0])
    assert merge_danger.FIELD_RE.match(lines[0])
    assert "Door: two-way" in lines[0]
    assert findings == []
    assert any("Door=" in ln for ln in logs)


def test_inject_never_lowers_mechanical_one_way(tmp_path):
    wt = _worktree(tmp_path, {"bin/lib/pr-armable.sh": "#!/bin/bash\n"})
    llm_why = "only a tidy comment in a shell helper"
    copy = {"summary_md": "## Summary\n- tidy\n\n**Merge danger:** Door: two-way · Blast radius: "
                          f"localized · Why: {llm_why}"}
    _run(copy, wt, _diff("bin/lib/pr-armable.sh"))
    [line] = _field_lines(copy["summary_md"])
    assert "Door: one-way" in line
    assert "Blast radius: site-wide" in line
    assert "arming-path:bin/lib/pr-armable.sh" in line
    assert line.index("arming-path:") < line.index(llm_why)


def test_inject_honours_llm_raise(tmp_path):
    wt = _worktree(tmp_path, {"docs/a.md": "x\n"})
    copy = {"summary_md": "## Summary\n\n**Merge danger:** Door: one-way · Blast radius: external · "
                          "Why: posts to a channel nobody can unsend from"}
    _run(copy, wt, _diff("docs/a.md"))
    [line] = _field_lines(copy["summary_md"])
    assert "Door: one-way" in line and "Blast radius: external" in line


def test_inject_replaces_malformed_llm_line(tmp_path):
    wt = _worktree(tmp_path, {"docs/a.md": "x\n"})
    copy = {"summary_md": "## Summary\n\n**Merge danger:** Door: maybe · Blast radius: module · "
                          "Why: unsure what this could break here"}
    findings, logs = _run(copy, wt, _diff("docs/a.md"))
    lines = _field_lines(copy["summary_md"])
    assert len(lines) == 1 and merge_danger.FIELD_RE.match(lines[0])
    assert "Door: two-way" in lines[0]
    assert findings == []
    assert any("malformed" in ln for ln in logs)


def test_pr_copy_prompt_requests_merge_danger_line():
    prompt = pr_copy_prompt("some-slug", Classification(), PlanInfo(), "")
    assert "**Merge danger:**" in prompt
    assert "exactly these four keys" in prompt


def test_derive_from_worktree_reads_candidate_text(tmp_path):
    wf = ".github/workflows/x.yml"
    wt = _worktree(tmp_path, {wf: "env:\n  DISCORD_WEBHOOK: ${{ secrets.X }}\n"})
    d = merge_danger.derive_from_worktree(wt, _diff(wf))
    assert (d.door, d.blast) == ("one-way", "external")
    assert f"notify-workflow:{wf}" in d.triggers


def test_validator_returns_no_findings_after_injection(tmp_path):
    wt = _worktree(tmp_path, {"docs/a.md": "x\n"})
    copy = {"summary_md": "## Summary\n- docs"}
    findings, _ = _run(copy, wt, _diff("docs/a.md"))
    assert findings == []


def test_validator_flags_missing_line_when_injection_is_bypassed(tmp_path, monkeypatch):
    wt = _worktree(tmp_path, {"docs/a.md": "x\n"})
    monkeypatch.setattr(merge_danger, "inject_merge_danger", lambda body, md: body)
    copy = {"summary_md": "## Summary\n- docs"}
    findings, logs = _run(copy, wt, _diff("docs/a.md"))
    assert len(findings) == 1 and findings[0].startswith("merge-danger: MISSING")
    assert any(ln.startswith("phase2 body-check finding: merge-danger: MISSING") for ln in logs)

    line = "**Merge danger:** Door: two-way · Blast radius: module · Why: twenty plus chars of reason"
    copy = {"summary_md": f"## Summary\n\n{line}\n{line}"}
    findings, _ = _run(copy, wt, _diff("docs/a.md"))
    assert len(findings) == 1 and findings[0].startswith("merge-danger: MALFORMED")


def test_empty_summary_is_left_alone_on_clean_tree_rerun(tmp_path):
    wt = _worktree(tmp_path, {"bin/lib/pr-armable.sh": "#!/bin/bash\n"})
    copy = {"summary_md": ""}
    findings, _ = _run(copy, wt, _diff("bin/lib/pr-armable.sh"))
    assert copy["summary_md"] == ""
    assert findings == []


def test_later_phase_leaves_missing_or_malformed_line_alone(tmp_path):
    for body in ("## Summary\n\nno field here\n",
                 "**Merge danger:** Door: maybe · Blast radius: module · Why: twenty characters at least\n"):
        assert runner._merge_danger_body(body, str(tmp_path), "", lambda *_: None, "phase6") == body
