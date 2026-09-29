"""scope_conformance.scope_items: the harness side of bin/lib/plan-scope-conformance.

Every test runs the real helper script. Plans are files under tmp_path, because the
helper reads the plan from disk.
"""
import json
import os
import re
from pathlib import Path

from harness import conformance, fidelity
from harness.scope_conformance import _added_paths, scope_items
from harness.state import PlanInfo

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


def _unplanned(items):
    return [i for i in items if i.startswith("UNPLANNED-FILE:")]


def _gaps(items):
    return [i for i in items if i.startswith("UNEXPLAINED-GAP:")]


def test_scope_items_flags_unplanned_path(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    items = scope_items(plan, ["ibl5/a.php", "ibl5/rogue.php"], "", "")
    unplanned = _unplanned(items)
    assert len(unplanned) == 1
    assert unplanned[0].startswith("UNPLANNED-FILE: ibl5/rogue.php (")


def test_scope_items_empty_plan_path_returns_empty(tmp_path):
    plan = PlanInfo(found=True, path="")
    assert scope_items(plan, ["ibl5/rogue.php"], "", "") == []


def test_scope_items_fails_closed_on_usage_exit(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    script = tmp_path / "usage-exit"
    script.write_text("#!/bin/sh\necho 'Usage: nope' >&2\nexit 2\n")
    os.chmod(script, 0o755)
    items = scope_items(plan, ["ibl5/a.php"], "", "", script=str(script))
    assert len(items) == 1
    assert "scope check unavailable (exit 2" in items[0]
    assert items[0].startswith("UNPLANNED-FILE:")


def test_scope_items_fails_closed_on_missing_script(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    items = scope_items(plan, ["ibl5/a.php"], "", "", script=str(tmp_path / "absent"))
    assert items == ["UNPLANNED-FILE: scope check unavailable (FileNotFoundError)"]


def test_added_paths_parses_new_file_mode():
    body = _diff(modified=["ibl5/old.php"], added=["ibl5/new.php"])
    assert _added_paths(body) == ["ibl5/new.php"]


def test_scope_items_exempts_added_test_via_diff_body(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    test_path = "ibl5/tests/X/NewTest.php"
    changed = ["ibl5/a.php", test_path]
    added = scope_items(plan, changed, _diff(["ibl5/a.php"], [test_path]), "")
    assert _unplanned(added) == []
    modified = scope_items(plan, changed, _diff(["ibl5/a.php", test_path], []), "")
    assert len(_unplanned(modified)) == 1
    assert test_path in _unplanned(modified)[0]


def test_gap_dropped_when_resolver_matches(tmp_path):
    plan = _plan(tmp_path, ["ibl5/migrations/100_foo.sql"])
    changed = ["ibl5/migrations/101_foo.sql"]
    resolved = scope_items(plan, changed, "", "", resolve=conformance._resolve)
    assert _gaps(resolved) == []
    raw = scope_items(plan, changed, "", "", resolve=None)
    assert len(_gaps(raw)) == 1
    assert raw and "ibl5/migrations/100_foo.sql" in _gaps(raw)[0]


def test_check_includes_scope_items_without_matrix(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    assert plan.has_matrix is False
    items = conformance.check(plan, ["ibl5/a.php", "ibl5/rogue.php"], diff_body="", pr_body="")
    assert any(i.startswith("UNPLANNED-FILE: ibl5/rogue.php (") for i in items)


def _replay_inputs():
    changed = [p for p in (SCOPE_1996 / "changed.txt").read_text().splitlines() if p.strip()]
    added = [p for p in (SCOPE_1996 / "added.txt").read_text().splitlines() if p.strip()]
    modified = [p for p in changed if p not in set(added)]
    plan = PlanInfo(found=True, path=str(SCOPE_1996 / "plan.md"))
    return plan, changed, _diff(modified, added)


def test_replay_1996_flags_schedule_updater():
    plan, changed, diff_body = _replay_inputs()
    unplanned = _unplanned(scope_items(plan, changed, diff_body, ""))
    assert any(u.startswith("UNPLANNED-FILE: ibl5/classes/Updater/ScheduleUpdater.php (")
               for u in unplanned)


def test_replay_1996_declared_body_clears():
    plan, changed, diff_body = _replay_inputs()
    body = (SCOPE_1996 / "body-declared.md").read_text()
    items = scope_items(plan, changed, diff_body, body)
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
        flagged = {u.split(" ", 2)[1] for u in _unplanned(scope_items(plan, paths, "", body))}
        assert flagged == set(paths), f"PR #{entry['number']}: {set(paths) - flagged}"
        checked += 1
    assert checked > 0


def test_work_list_forwards_scope_labels(tmp_path):
    verdict = tmp_path / "verdict.md"
    verdict.write_text("")
    items = fidelity.build_work_list(
        verdict_path=str(verdict),
        unresolved_conformance=["UNPLANNED-FILE: a", "UNEXPLAINED-GAP: b"])
    assert {"hold": "3", "text": "UNPLANNED-FILE: a"} in items
    assert {"hold": "3", "text": "UNEXPLAINED-GAP: b"} in items


def test_work_list_still_excludes_missing_phase_and_contract(tmp_path):
    verdict = tmp_path / "verdict.md"
    verdict.write_text("")
    items = fidelity.build_work_list(
        verdict_path=str(verdict),
        unresolved_conformance=["MISSING-PHASE: Phase 2", "UNMET-CONTRACT: evidence",
                                "UNPLANNED-FILE: a"])
    texts = [i["text"] for i in items]
    assert "MISSING-PHASE: Phase 2" not in texts
    assert "UNMET-CONTRACT: evidence" not in texts
    assert "UNPLANNED-FILE: a" in texts
