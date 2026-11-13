"""Runner wiring for the Merge danger field: `_inject_merge_danger` (Phase 2 injection plus the
merge-danger validator) and the `pr_copy_prompt` request."""
import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import merge_danger, savedline
from harness.llm_calls import pr_copy_prompt
from harness.state import Classification, PlanInfo


def _diff(path, status="M"):
    head = f"diff --git a/{path} b/{path}\n"
    if status == "A":
        head += "new file mode 100644\n"
    return head + f"--- a/{path}\n+++ b/{path}\n@@ -1 +1 @@\n-old\n+new\n"


def _worktree(tmp_path, files):
    (tmp_path / "bin").mkdir(parents=True, exist_ok=True)
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


def test_inject_never_drops_mechanical_self_governing(tmp_path):
    wt = _worktree(tmp_path, {"bin/lib/pr-armable.sh": "#!/bin/bash\n"})
    llm_why = "only a tidy comment in a shell helper"
    copy = {"summary_md": "## Summary\n- tidy\n\n**Merge danger:** Door: two-way · Blast radius: "
                          f"localized · Why: {llm_why}"}
    _run(copy, wt, _diff("bin/lib/pr-armable.sh"))
    [line] = _field_lines(copy["summary_md"])
    assert "Door: two-way" in line
    assert " · Self-governing: yes · Why: " in line
    assert merge_danger.classify_body(copy["summary_md"]) == "SELF-GOVERNING"
    assert "Blast radius: site-wide" in line
    assert "arming-path:bin/lib/pr-armable.sh" in line
    assert line.index("arming-path:") < line.index(llm_why)


def test_inject_honours_llm_raise(tmp_path):
    wt = _worktree(tmp_path, {"docs/a.md": "x\n"})
    copy = {"summary_md": "## Summary\n\n**Merge danger:** Door: one-way · Blast radius: external · "
                          "Why: sends a DM that cannot be recalled later"}
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


def test_derive_from_worktree_workflow_send_edit_is_two_way(tmp_path):
    # Workflow alerts reach only the owner's channels, so a send-line edit is two-way.
    wf = ".github/workflows/x.yml"
    text = ("env:\n  DISCORD_WEBHOOK: ${{ secrets.X }}\njobs:\n  a:\n"
            "    runs-on: ubuntu-26.04\n    steps:\n      - run: echo hi\n")
    wt = _worktree(tmp_path, {wf: text})
    diff = (f"diff --git a/{wf} b/{wf}\n--- a/{wf}\n+++ b/{wf}\n@@ -1,2 +1,2 @@\n env:\n"
            "-  DISCORD_WEBHOOK: ${{ secrets.OLD }}\n+  DISCORD_WEBHOOK: ${{ secrets.X }}\n")
    d = merge_danger.derive_from_worktree(wt, diff)
    assert (d.door, d.blast) == ("two-way", "site-wide")
    assert not any(t.startswith("notify-workflow") for t in d.triggers)


def test_worktree_armable_edit_holds_when_tree_has_no_harness(tmp_path):
    wt = _worktree(tmp_path, {})
    path = "tools/postplan-harness/harness/armable.py"
    diff = (f"diff --git a/{path} b/{path}\n--- a/{path}\n+++ b/{path}\n@@ -1,1 +1,1 @@\n"
            "-x = 1\n+x = 2\n")
    assert merge_danger.derive_from_worktree(wt, diff).self_governing is True


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
    assert any(ln.startswith("phase2 finding: merge-danger: MISSING") for ln in logs)

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


def test_phase6_and_phase7_reapplication_leave_the_phase2_line_unchanged(tmp_path):
    path = "tools/postplan-harness/harness/armable.py"
    wt = _worktree(tmp_path, {path: "x = 1\n"})
    diff = _diff(path)
    body = ("## Summary\n\n**Merge danger:** Door: one-way · Blast radius: site-wide · "
            "Why: edits the arming predicate library itself")
    log = lambda *_: None
    b2 = runner._merge_danger_body(body, wt, diff, log, "phase2")
    b6 = runner._merge_danger_body(b2, wt, diff, log, "phase6")
    b7 = runner._merge_danger_body(b6, wt, diff, log, "phase7")
    assert _field_lines(b2) == _field_lines(b6) == _field_lines(b7)
    [line] = _field_lines(b2)
    assert line.count("mechanical:") == 1
    assert line.endswith("library itself")


def _docs_body(door, why):
    return f"## Summary\n\n**Merge danger:** Door: {door} · Blast radius: localized · Why: {why}"


def test_phase2_keeps_llm_one_way_at_the_floor_when_why_names_no_effect(tmp_path):
    wt = _worktree(tmp_path, {"ibl5/docs/x.md": "x\n"})
    logs = []
    body = _docs_body("one-way", "the change widens an ENUM and adds a helper table")
    out = runner._merge_danger_body(body, wt, _diff("ibl5/docs/x.md"), logs.append, "phase2")
    [line] = _field_lines(out)
    assert "Door: two-way" in line
    assert any("kept at the mechanical two-way floor" in ln for ln in logs)


@pytest.mark.parametrize("why", [
    "drops the trade_mode column data for every team",
    "dropping the trade_mode column data for every team",
])
def test_phase2_honours_llm_one_way_naming_an_effect(tmp_path, why):
    wt = _worktree(tmp_path, {"ibl5/docs/x.md": "x\n"})
    body = _docs_body("one-way", why)
    out = runner._merge_danger_body(body, wt, _diff("ibl5/docs/x.md"), lambda *_: None, "phase2")
    [line] = _field_lines(out)
    assert "Door: one-way" in line


def test_phase6_keeps_a_one_way_raise_regardless_of_why(tmp_path):
    wt = _worktree(tmp_path, {"ibl5/docs/x.md": "x\n"})
    body = _docs_body("one-way", "the change widens an ENUM and adds a helper table")
    out = runner._merge_danger_body(body, wt, _diff("ibl5/docs/x.md"), lambda *_: None, "phase6")
    [line] = _field_lines(out)
    assert "Door: one-way" in line


def test_pr_copy_prompt_states_the_revert_bar():
    prompt = pr_copy_prompt("some-slug", Classification(), PlanInfo(), "")
    assert "git revert <merge-sha>" in prompt
    assert "Add-only migrations" in prompt


# Per-round re-judge: the harness-owned line and the sticky human tombstone.

_FIXROUND = (Path(__file__).parent / "fixtures" / "merge_danger" / "pr3840_fixround_line.txt").read_text().strip()
_DOC = "ibl5/docs/x.md"
_NO_EFFECT_ONE_WAY = "the change widens an ENUM and adds a helper table"
_EFFECT_ONE_WAY = "drops the trade_mode column data for every team"
_FIXER_TWO_WAY = "a read-path swap with no data change at all"


def _swap_line(body, line):
    return "\n".join(line if ln.startswith("**Merge danger:**") else ln for ln in body.splitlines())


def _door(body):
    return merge_danger.parse_merge_danger(body).door


def _phase2(tmp_path, wt, diff, door, why):
    return runner._merge_danger_body(_docs_body(door, why), wt, diff, lambda *_: None, "phase2",
                                     state_dir=str(tmp_path / "state"), slug="t")


def _later(tmp_path, wt, diff, body, start, phase="phase5.5 round 1", log=None):
    return runner._merge_danger_body(body, wt, diff, log or (lambda *_: None), phase,
                                     state_dir=str(tmp_path / "state"), slug="t",
                                     round_start_body=start)


def _fixer(start, door, why):
    return _swap_line(start, f"**Merge danger:** Door: {door} · Blast radius: localized · Why: {why}")


def _docs_wt(tmp_path):
    return _worktree(tmp_path / "wt", {_DOC: "x\n"}), _diff(_DOC)


def test_owned_line_lowered_when_fresh_verdict_two_way(tmp_path):
    wt, diff = _docs_wt(tmp_path)
    start = _phase2(tmp_path, wt, diff, "one-way", _EFFECT_ONE_WAY)
    assert _door(start) == "one-way"
    assert savedline.load_line(str(tmp_path / "state"), "t") == _field_lines(start)[0]
    # The fresh LLM line names no irreversible effect, so the Phase 2 bar judges it two-way.
    out = _later(tmp_path, wt, diff, _fixer(start, "one-way", _NO_EFFECT_ONE_WAY), start)
    assert _door(out) == "two-way"
    assert savedline.load_line(str(tmp_path / "state"), "t") == _field_lines(out)[0]


def test_phase55_fixer_rebuildable_raise_refused(tmp_path):
    wt, diff = _docs_wt(tmp_path)
    start = _phase2(tmp_path, wt, diff, "two-way", _FIXER_TWO_WAY)
    assert savedline.load_line(str(tmp_path / "state"), "t") == _field_lines(start)[0]
    out = _later(tmp_path, wt, diff, _swap_line(start, _FIXROUND), start)
    assert _door(out) == "two-way"
    assert not savedline.is_human_owned(str(tmp_path / "state"), "t")


def _human_round(tmp_path):
    wt, diff = _docs_wt(tmp_path)
    harness_start = _phase2(tmp_path, wt, diff, "two-way", _FIXER_TWO_WAY)
    human = _swap_line(harness_start, "**Merge danger:** Door: one-way · Blast radius: localized · "
                                      f"Why: {_EFFECT_ONE_WAY}")
    logs = []
    out = _later(tmp_path, wt, diff, _fixer(human, "two-way", _FIXER_TWO_WAY), human, log=logs.append)
    return wt, diff, out, logs


def test_human_edited_line_stays_raise_only(tmp_path):
    _, _, out, logs = _human_round(tmp_path)
    assert _door(out) == "one-way"
    assert savedline.is_human_owned(str(tmp_path / "state"), "t")
    assert any("edited outside the harness" in ln for ln in logs)


def test_human_raise_survives_next_round(tmp_path):
    wt, diff, _, _ = _human_round(tmp_path)
    # A later post-plan run's Phase 2 rewrites the line; the tombstone refuses its save.
    rerun = _phase2(tmp_path, wt, diff, "one-way", _EFFECT_ONE_WAY)
    out = _later(tmp_path, wt, diff, _fixer(rerun, "two-way", _FIXER_TWO_WAY), rerun)
    assert _door(out) == "one-way"
    state = Path(savedline.path_for(str(tmp_path / "state"), "t"))
    assert state.read_text() == '{"owner": "human"}'


def test_missing_saved_state_stays_raise_only(tmp_path):
    wt, diff = _docs_wt(tmp_path)
    body = _swap_line(_docs_body("two-way", _FIXER_TWO_WAY), _FIXROUND)
    out = runner._merge_danger_body(body, wt, diff, lambda *_: None, "phase6",
                                    state_dir=str(tmp_path / "state"), slug="t")
    assert _door(out) == "one-way"
    assert not (tmp_path / "state").exists()


def test_unreadable_saved_state_stays_raise_only(tmp_path):
    wt, diff = _docs_wt(tmp_path)
    (tmp_path / "state").mkdir()
    state = Path(savedline.path_for(str(tmp_path / "state"), "t"))
    state.write_text("{not json")
    body = _swap_line(_docs_body("two-way", _FIXER_TWO_WAY), _FIXROUND)
    out = runner._merge_danger_body(body, wt, diff, lambda *_: None, "phase6",
                                    state_dir=str(tmp_path / "state"), slug="t")
    assert _door(out) == "one-way"
    assert state.read_text() == "{not json"


def test_floor_one_way_never_lowered(tmp_path):
    mig = "ibl5/migrations/200_drop_col.sql"
    wt = _worktree(tmp_path / "wt", {mig: "ALTER TABLE ibl_plr DROP COLUMN foo;\n"})
    diff = (f"diff --git a/{mig} b/{mig}\nnew file mode 100644\n--- /dev/null\n+++ b/{mig}\n"
            "@@ -0,0 +1 @@\n+ALTER TABLE ibl_plr DROP COLUMN foo;\n")
    assert merge_danger.derive_from_worktree(wt, diff).door == "one-way"
    start = _phase2(tmp_path, wt, diff, "two-way", _FIXER_TWO_WAY)
    assert _door(start) == "one-way"
    out = _later(tmp_path, wt, diff, _fixer(start, "two-way", _FIXER_TWO_WAY), start)
    assert _door(out) == "one-way"
    assert not savedline.is_human_owned(str(tmp_path / "state"), "t")


def test_self_governing_never_dropped_on_owned_rejudge(tmp_path):
    wt, diff = _docs_wt(tmp_path)
    saved = ("**Merge danger:** Door: two-way · Blast radius: localized · Self-governing: yes · "
             f"Why: {_FIXER_TWO_WAY}")
    assert savedline.save_line(str(tmp_path / "state"), "t", saved)
    start = _swap_line(_docs_body("two-way", _FIXER_TWO_WAY), saved)
    out = _later(tmp_path, wt, diff, _fixer(start, "two-way", "the fixer rewrote this Why text"), start)
    assert merge_danger.parse_merge_danger(out).self_governing is True
    assert merge_danger.classify_body(out) == "SELF-GOVERNING"


def test_owned_no_fresh_verdict_keeps_saved_door(tmp_path):
    wt, diff = _docs_wt(tmp_path)
    saved = f"**Merge danger:** Door: one-way · Blast radius: localized · Why: {_NO_EFFECT_ONE_WAY}"
    assert savedline.save_line(str(tmp_path / "state"), "t", saved)
    start = _swap_line(_docs_body("two-way", _FIXER_TWO_WAY), saved)
    out = _later(tmp_path, wt, diff, start, start, phase="phase6")
    assert _door(out) == "one-way"


def test_default_kwargs_match_todays_raise_only(tmp_path):
    wt, diff = _docs_wt(tmp_path)
    body = _docs_body("one-way", _NO_EFFECT_ONE_WAY)
    for phase in ("phase6", "phase5.5 round 1", "phase7"):
        out = runner._merge_danger_body(body, wt, diff, lambda *_: None, phase)
        assert _door(out) == "one-way", phase
    assert list(tmp_path.glob("**/*" + savedline.SUFFIX)) == []
