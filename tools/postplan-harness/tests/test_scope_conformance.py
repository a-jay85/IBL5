"""scope_conformance.scope_notes: the harness side of bin/lib/plan-scope-conformance.

Every test runs the real helper script. Plans are files under tmp_path, because the
helper reads the plan from disk.
"""
import json
import os
import re
import time
from pathlib import Path

import pytest

from harness import conformance, scope_conformance
from harness.classify import (SCOPE_NOTES_BEGIN, SCOPE_NOTES_END, render_scope_notes,
                              upsert_scope_notes)
from harness.scope_conformance import _added_paths, scope_notes
from harness.state import HarnessError, PlanInfo

FIXTURES = Path(__file__).parent / "fixtures"
SCOPE_1996 = FIXTURES / "scope-1996"


def _plan(tmp_path, critical: list[str], name: str = "plan.md") -> PlanInfo:
    lines = ["# Plan", "", "## Critical Files", ""]
    lines += [f"- `{p}` (modified)" for p in critical]
    path = tmp_path / name
    path.write_text("\n".join(lines) + "\n")
    return PlanInfo(found=True, path=str(path))


def _diff(modified: list[str], added: list[str]) -> str:
    out = []
    for p in added:
        out += [f"diff --git a/{p} b/{p}", "new file mode 100644", "index 0000000..1111111",
                "--- /dev/null", f"+++ b/{p}", "@@ -0,0 +1 @@", "+x"]
    for p in modified:
        out += [f"diff --git a/{p} b/{p}", "index 1111111..2222222 100644",
                f"--- a/{p}", f"+++ b/{p}", "@@ -1 +1 @@", "-x", "+y"]
    return "\n".join(out) + "\n"


def _unplanned(notes):
    return [n for n in notes if n.startswith("unplanned ")]


def _gaps(notes):
    return [n for n in notes if n.startswith("gap ")]


def test_scope_notes_flags_unplanned_path(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    notes = scope_notes(plan, ["ibl5/a.php", "ibl5/rogue.php"], "", "")
    unplanned = _unplanned(notes)
    assert len(unplanned) == 1
    assert unplanned[0].startswith("unplanned ibl5/rogue.php (")


def test_scope_notes_renders_claude_path_as_unplanned(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    notes = scope_notes(plan, ["ibl5/a.php", ".claude/rules/new.md"], "", "")
    assert len(notes) == 1
    assert notes[0].startswith("unplanned .claude/rules/new.md (")


def test_scope_notes_reports_gap(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php", "ibl5/b.php"])
    notes = scope_notes(plan, ["ibl5/a.php"], "", "")
    assert len(_gaps(notes)) == 1
    assert _gaps(notes)[0].startswith("gap ibl5/b.php (")


def test_scope_notes_empty_plan_path_returns_empty(tmp_path):
    plan = PlanInfo(found=True, path="")
    assert scope_notes(plan, ["ibl5/rogue.php"], "", "") == []


def test_scope_notes_unavailable_on_usage_exit(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    script = tmp_path / "usage-exit"
    script.write_text("#!/bin/sh\necho 'Usage: nope' >&2\nexit 2\n")
    os.chmod(script, 0o755)
    notes = scope_notes(plan, ["ibl5/a.php"], "", "", script=str(script))
    assert len(notes) == 1
    assert notes[0].startswith("scope check unavailable (exit 2")


def test_scope_notes_timeout_fails_closed_and_reaps(tmp_path, monkeypatch):
    """A hung scope helper is an anomaly: it raises subprocess-timeout (not the advisory
    note) and the process group, grandchild included, is reaped.

    Mutation caught: bare subprocess.run blocks 300s (elapsed assertion); folding the
    timeout into the "scope check unavailable" note makes pytest.raises fail.
    """
    plan = _plan(tmp_path, ["ibl5/a.php"])
    script = tmp_path / "hang"
    script.write_text(f"#!/bin/bash\nsleep 300 &\necho $! > {tmp_path}/grandchild.pid\nsleep 300\n")
    os.chmod(script, 0o755)
    monkeypatch.setattr(scope_conformance, "SCOPE_CHECK_TIMEOUT", 1)
    start = time.monotonic()
    with pytest.raises(HarnessError) as ei:
        scope_notes(plan, ["ibl5/a.php"], "", "", script=str(script))
    assert time.monotonic() - start < 15
    assert ei.value.kind == "subprocess-timeout"
    assert "scope-check" in ei.value.detail
    grandchild = int((tmp_path / "grandchild.pid").read_text().strip())
    deadline = time.monotonic() + 5
    while time.monotonic() < deadline:
        try:
            os.kill(grandchild, 0)
        except ProcessLookupError:
            return
        time.sleep(0.1)
    pytest.fail("grandchild survived the process-group reap")


def test_scope_notes_unavailable_on_missing_script(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    notes = scope_notes(plan, ["ibl5/a.php"], "", "", script=str(tmp_path / "absent"))
    assert notes == ["scope check unavailable (FileNotFoundError)"]


def test_added_paths_parses_new_file_mode():
    body = _diff(modified=["ibl5/old.php"], added=["ibl5/new.php"])
    assert _added_paths(body) == ["ibl5/new.php"]


def test_scope_notes_exempts_added_test_via_diff_body(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    test_path = "ibl5/tests/X/NewTest.php"
    changed = ["ibl5/a.php", test_path]
    added = scope_notes(plan, changed, _diff(["ibl5/a.php"], [test_path]), "")
    assert _unplanned(added) == []
    modified = scope_notes(plan, changed, _diff(["ibl5/a.php", test_path], []), "")
    assert len(_unplanned(modified)) == 1
    assert test_path in _unplanned(modified)[0]


def test_check_emits_no_scope_items(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    assert plan.has_matrix is False
    items = conformance.check(plan, ["ibl5/a.php", "ibl5/rogue.php"], diff_body="", pr_body="")
    assert not any(i.startswith(("UNPLANNED-FILE:", "SCOPE-NOTE")) for i in items)


def _replay_inputs():
    changed = [p for p in (SCOPE_1996 / "changed.txt").read_text().splitlines() if p.strip()]
    added = [p for p in (SCOPE_1996 / "added.txt").read_text().splitlines() if p.strip()]
    modified = [p for p in changed if p not in set(added)]
    plan = PlanInfo(found=True, path=str(SCOPE_1996 / "plan.md"))
    return plan, changed, _diff(modified, added)


def test_replay_1996_flags_schedule_updater():
    plan, changed, diff_body = _replay_inputs()
    unplanned = _unplanned(scope_notes(plan, changed, diff_body, ""))
    assert any(u.startswith("unplanned ibl5/classes/Updater/ScheduleUpdater.php (")
               for u in unplanned)


def test_replay_1996_declared_body_clears():
    plan, changed, diff_body = _replay_inputs()
    body = (SCOPE_1996 / "body-declared.md").read_text()
    items = scope_notes(plan, changed, diff_body, body)
    assert _unplanned(items) == []
    assert _gaps(items) == []


_SPAN = re.compile(r"<!-- files-changed:begin -->(.*?)<!-- files-changed:end -->", re.S)
_ROW = re.compile(r"^- `[A-Z][0-9]*` (?P<rest>.+)$", re.M)


def test_corpus_generated_block_never_declares(tmp_path):
    corpus = json.loads((FIXTURES / "pr-bodies-corpus.json").read_text())
    assert len(corpus) == 50
    heading = re.compile(r"^##[ \t]+Declared scope[ \t]*$", re.M)
    assert sum(1 for e in corpus if heading.search(e["body"] or "")) == 0
    plan = _plan(tmp_path, [])
    checked = 0
    for entry in corpus:
        body = entry["body"] or ""
        span = _SPAN.search(body)
        if span is None:
            continue
        # A rename row carries `old → new` in one code span; both sides are changed paths.
        paths = sorted({p.strip() for m in _ROW.finditer(span.group(1))
                        for tok in re.findall(r"`([^`]+)`", m.group("rest"))
                        for p in tok.split(" → ")})
        if not paths:
            continue
        flagged = {u.split(" ", 2)[1] for u in _unplanned(scope_notes(plan, paths, "", body))}
        assert flagged == set(paths), f"PR #{entry['number']}: {set(paths) - flagged}"
        checked += 1
    assert checked > 0


def test_render_scope_notes_empty_and_nonempty():
    assert render_scope_notes([]) == ""
    out = render_scope_notes(["unplanned ibl5/a.php (changed path)", "gap ibl5/b.php (absent)"])
    assert out.startswith(SCOPE_NOTES_BEGIN)
    assert out.endswith(SCOPE_NOTES_END)
    assert "## Unplanned changes" in out
    assert "Auto-merge is not held on them." in out
    assert "- unplanned `ibl5/a.php` (changed path)" in out
    assert "- gap `ibl5/b.php` (absent)" in out


def test_upsert_scope_notes_insert_replace_remove():
    body = "## Summary\n- x"
    v1 = render_scope_notes(["unplanned ibl5/a.php (...)"])
    v2 = render_scope_notes(["gap ibl5/b.php (...)"])
    appended = upsert_scope_notes(body, v1)
    assert body in appended
    assert SCOPE_NOTES_BEGIN in appended
    replaced = upsert_scope_notes(appended, v2)
    assert body in replaced
    assert "ibl5/b.php" in replaced
    assert "ibl5/a.php" not in replaced
    removed = upsert_scope_notes(replaced, "")
    assert removed.strip() == body.strip()
    assert upsert_scope_notes(body, "") == body
