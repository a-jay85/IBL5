"""Tests for the reason-keyed early hold-repeat match (Phase 1) and verdict_line (Phase 2)."""
from __future__ import annotations

import os
import stat
import subprocess
import sys
from types import SimpleNamespace

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness import holdrepeat as hr
from harness.state import PlanInfo

SLUG = "slug"
ITEM_FILE = "MISSING-FILE: tools/a.py (plan Critical File never appeared in the diff)"
ITEM_TEST = "MISSING: tests/t.py (matrix planned a test the diff never wrote)"
ITEM_NEW = "MISSING-FILE: tools/new.py (plan Critical File never appeared in the diff)"


def _cond(number, reason):
    return SimpleNamespace(number=number, name=f"cond-{number}", blocked=True, reason=reason)


def _record(tmp_path, spec):
    """spec: {condition number: reason}. Writes the real on-disk record shape."""
    hr.observe(str(tmp_path), SLUG, armed=False,
               conditions=[_cond(n, r) for n, r in spec.items()],
               fingerprint="fp", pr=1, now="2026-10-02T12:00:00Z")


def test_early_repeat_equal_sets_returns_reason(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}; {ITEM_TEST}"})
    out = hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_TEST, ITEM_FILE])
    assert out
    assert "tools/a.py" in out and "tests/t.py" in out


def test_early_repeat_ignores_non_missing_parts(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}; UNREALISED-ASSERTION: row 4 -- x; {ITEM_TEST}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE, ITEM_TEST])


def test_early_repeat_strict_subset_proceeds(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}; {ITEM_TEST}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE]) is None


def test_early_repeat_superset_proceeds(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}; {ITEM_TEST}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE, ITEM_TEST, ITEM_NEW]) is None


def test_early_repeat_no_record_proceeds(tmp_path):
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE]) is None


def test_early_repeat_prior_cond7_only_proceeds(tmp_path):
    _record(tmp_path, {7: f"{ITEM_FILE}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE]) is None


def test_early_repeat_empty_current_proceeds(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, []) is None


def test_early_missing_items_filters_kinds():
    plan = PlanInfo(found=True, has_matrix=True,
                    planned_test_paths=["tests/test_zzz_absent.py"],
                    critical_files=[("tools/zzz_absent.py", "", False)],
                    required_test_methods=["test_zzz_method"])
    items = conformance.early_missing_items(plan, [])
    assert items
    assert all(i.startswith(("MISSING:", "MISSING-FILE:")) for i in items)
    assert any(i.startswith("MISSING:") for i in items)
    assert any(i.startswith("MISSING-FILE:") for i in items)


def test_verdict_line_hold_repeat_declined():
    import runner
    from harness.state import RunResult, TerminalState
    res = RunResult(terminal=TerminalState.HOLD_REPEAT_DECLINED)
    res.hold_repeat = {"early_decline": True, "reason": "MISSING-FILE: a.py (x)",
                       "structural_key": "3:m", "repeat_count": 2, "dm_sent": True}
    line = runner.verdict_line(res, 0, "https://github.com/o/r/pull")
    assert line.startswith("RESULT: post-plan DECLINED")
    assert "declined: same hold as last run (MISSING-FILE: a.py (x)), no tokens spent" in line
    assert "PR #" not in line


def test_verdict_line_hold_repeat_missing_reason():
    import runner
    from harness.state import RunResult, TerminalState
    res = RunResult(terminal=TerminalState.HOLD_REPEAT_DECLINED)
    res.hold_repeat = {}
    line = runner.verdict_line(res, 0)
    assert "(unknown)" in line


# --- UNREALISED-ASSERTION row holds ---------------------------------------------------------

ROW_A = "UNREALISED-ASSERTION: row 4 — first cell"
ROW_B = "UNREALISED-ASSERTION: row 7 — second cell"
ROW_NEW = "UNREALISED-ASSERTION: row 9 — third cell"
UNAVAILABLE = "UNREALISED-ASSERTION: matrix-assertion check unavailable (exit 2: boom)"


def _fake_script(tmp_path, stdout_lines=(), stderr_lines=(), code=1, sentinel=None):
    path = tmp_path / "fake-matrix-script"
    body = ["#!/bin/sh"]
    if sentinel:
        body.append(f"touch '{sentinel}'")
    for ln in stdout_lines:
        body.append(f"printf '%s\\n' '{ln}'")
    for ln in stderr_lines:
        body.append(f"printf '%s\\n' '{ln}' >&2")
    body.append(f"exit {code}")
    path.write_text("\n".join(body) + "\n")
    path.chmod(path.stat().st_mode | stat.S_IEXEC)
    return str(path)


def _g(cwd, *args):
    subprocess.run(["git", "-C", str(cwd), *args], check=True, capture_output=True)


def _repo(tmp_path, files):
    """Repo with one commit; `refs/remotes/origin/master` points at it."""
    repo = tmp_path / "repo"
    repo.mkdir()
    _g(repo, "init", "-q", "-b", "master")
    _g(repo, "config", "user.email", "t@t")
    _g(repo, "config", "user.name", "t")
    for rel, text in files.items():
        p = repo / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text)
    _g(repo, "add", "-A")
    _g(repo, "commit", "-qm", "base")
    sha = subprocess.run(["git", "-C", str(repo), "rev-parse", "HEAD"],
                         check=True, capture_output=True, text=True).stdout.strip()
    _g(repo, "update-ref", "refs/remotes/origin/master", sha)
    return repo


def test_unrealised_parts_keeps_rows_and_drops_unavailable():
    reason = f"{ROW_A}; {UNAVAILABLE}; {ITEM_FILE}"
    assert hr.unrealised_parts(reason) == frozenset({ROW_A})


def test_missing_parts_still_ignores_unrealised_rows():
    assert hr.missing_parts(f"{ROW_A}; {ITEM_FILE}") == frozenset({ITEM_FILE})


def test_hold_parts_unions_missing_and_unrealised():
    assert hr.hold_parts(f"{ROW_A}; {ITEM_FILE}") == frozenset({ROW_A, ITEM_FILE})


def test_evaluate_unrealised_parses_items_and_tokens(tmp_path):
    script = _fake_script(tmp_path, [ROW_A, ROW_B],
                          ["  missing: tok_one tok_two", "  missing: tok_three"])
    ev = hr.evaluate_unrealised("plan.md", "diff", str(tmp_path), script=script)
    assert ev is not None
    assert ev.items == (ROW_A, ROW_B)
    assert ev.tokens == frozenset({"tok_one", "tok_two", "tok_three"})


def test_evaluate_unrealised_returns_none_when_unavailable(tmp_path):
    exit2 = _fake_script(tmp_path, [], ["boom"], code=2)
    assert hr.evaluate_unrealised("plan.md", "d", str(tmp_path), script=exit2) is None
    shaped = _fake_script(tmp_path, [UNAVAILABLE], ["  missing: x"], code=1)
    assert hr.evaluate_unrealised("plan.md", "d", str(tmp_path), script=shaped) is None
    assert hr.evaluate_unrealised("", "d", str(tmp_path), script=shaped) is None


def test_tokens_on_ref_true_when_master_has_token(tmp_path):
    repo = _repo(tmp_path, {"src/widget_helper.txt": "hello needle_in_content\n"})
    assert hr.tokens_on_ref(str(repo), "origin/master", {"needle_in_content"}) is True
    assert hr.tokens_on_ref(str(repo), "origin/master", {"widget_helper"}) is True
    assert hr.tokens_on_ref(str(repo), "origin/master", {"absent_zzz"}) is False
    assert hr.tokens_on_ref(str(repo), "origin/nope", {"needle_in_content"}) is None
    assert hr.tokens_on_ref(str(repo), "origin/master", set()) is None


def _unrealised_state(tmp_path, rows=(ROW_A, ROW_B)):
    state = tmp_path / "state"
    _record(state, {3: "; ".join(rows)})
    return str(state)


def _run_early(state, repo, script):
    return hr.early_unrealised_repeat(state, SLUG, [], plan_path="plan.md",
                                      diff_body="", worktree=str(repo), script=script)


def test_early_unrealised_repeat_detects_identical_set(tmp_path):
    repo = _repo(tmp_path, {"a.txt": "base\n"})
    script = _fake_script(tmp_path, [ROW_B, ROW_A], ["  missing: absent_tok"])
    reason, note, tokens = _run_early(_unrealised_state(tmp_path), repo, script)
    assert note == "repeat"
    assert ROW_A in reason and ROW_B in reason
    assert tokens == frozenset({"absent_tok"})


def test_early_unrealised_repeat_proceeds_on_changed_set(tmp_path):
    repo = _repo(tmp_path, {"a.txt": "base\n"})
    script = _fake_script(tmp_path, [ROW_A, ROW_NEW], ["  missing: absent_tok"])
    reason, note, _ = _run_early(_unrealised_state(tmp_path), repo, script)
    assert reason is None and note == "unrealised set changed"


def test_early_unrealised_repeat_proceeds_when_master_has_token(tmp_path):
    repo = _repo(tmp_path, {"a.txt": "base has present_tok\n"})
    script = _fake_script(tmp_path, [ROW_A, ROW_B], ["  missing: present_tok"])
    reason, note, _ = _run_early(_unrealised_state(tmp_path), repo, script)
    assert reason is None and "master" in note


def test_early_unrealised_repeat_proceeds_on_dirty_worktree(tmp_path):
    repo = _repo(tmp_path, {"a.txt": "base\n"})
    (repo / "a.txt").write_text("edited\n")
    script = _fake_script(tmp_path, [ROW_A, ROW_B], ["  missing: absent_tok"])
    reason, note, _ = _run_early(_unrealised_state(tmp_path), repo, script)
    assert reason is None and note == "worktree dirty or unreadable"


def test_early_unrealised_repeat_never_spawns_for_missing_only_record(tmp_path):
    repo = _repo(tmp_path, {"a.txt": "base\n"})
    sentinel = tmp_path / "spawned"
    script = _fake_script(tmp_path, [ROW_A], ["  missing: x"], sentinel=str(sentinel))
    state = tmp_path / "state"
    _record(state, {3: f"{ITEM_FILE}; {ITEM_TEST}"})
    reason, note, _ = _run_early(str(state), repo, script)
    assert reason is None and note == "no unrealised hold"
    assert not sentinel.exists()
