"""Tests for harness/arming_replay.py: codec, driver, pure judge, replay runner, and the
worktree-level wiring through merge_danger.derive_from_worktree."""
import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import arming_replay, merge_danger
from harness.armable import ArmInputs, evaluate
from harness.arming_replay import (MIN_RECORDS, ReplayVerdict, SideResult, changed_lines,
                                   encode_inputs, inert_lines, judge)
from harness.arming_replay_driver import decode
from harness.gate_backtest import _ARMING_PATHS
from harness.state import Classification, Finding
from test_armable import BODY_CLEARED, inputs

REPO = Path(__file__).resolve().parents[3]
ARMABLE = "tools/postplan-harness/harness/armable.py"


@pytest.fixture(autouse=True)
def _clear_cache():
    arming_replay._CACHE.clear()
    yield
    arming_replay._CACHE.clear()


def _blocked(decision):
    return sorted(c.number for c in decision.conditions if c.blocked)


def _load_corpus():
    return json.loads(arming_replay.CORPUS_PATH.read_text(encoding="utf-8"))


def _decode(record):
    return decode(record, ArmInputs, Classification, Finding)


# --- codec and driver ---------------------------------------------------------------------------

def test_codec_round_trip_preserves_decision():
    cases = [
        inputs(dep_state_lookup=lambda n: "OPEN", pr_body=BODY_CLEARED + "\nDepends-on: #1400\n"),
        inputs(findings=[Finding("code-review", "A", "f.php", 1, "bug", 85)]),
        inputs(exec_mutations_holding=("m",), plan_hold_triggers=("gate-weakening",)),
        inputs(plan_auto_merge_false=True, current_tree="t", second_review_verdict="CLEAN",
               second_review_tree="t", plan_hold_triggers=("gate-weakening",),
               second_review_clears={7: "safe, the diff keeps the gate's rejections"}),
        inputs(),
        inputs(classification=Classification(files=["ibl5/design/input.css"])),
    ]
    for x in cases:
        back = _decode({"id": "x", "inputs": json.loads(json.dumps(encode_inputs(x)))})
        want, got = evaluate(x), evaluate(back)
        assert (got.armed, _blocked(got)) == (want.armed, _blocked(want))


def _run_driver(corpus_path, cwd=None):
    pkg = REPO / "tools" / "postplan-harness"
    return subprocess.run(
        [sys.executable, str(arming_replay.DRIVER_PATH), str(corpus_path), str(pkg), str(REPO)],
        cwd=str(cwd or pkg), capture_output=True, text=True, timeout=120)


def test_driver_results_match_in_process_evaluate():
    proc = _run_driver(arming_replay.CORPUS_PATH)
    assert proc.returncode == 0, proc.stderr
    out = json.loads(proc.stdout)
    want = []
    for r in _load_corpus():
        d = evaluate(_decode(r))
        want.append({"id": r["id"], "armed": d.armed, "blocked": _blocked(d)})
    assert out["results"] == want
    assert ARMABLE in out["executed"]
    assert not any(k.startswith("<") or "/<" in k for k in out["executed"])


def test_driver_unknown_record_key_exits_nonzero(tmp_path):
    record = _load_corpus()[0]
    record["inputs"]["no_such_field"] = 1
    corpus = tmp_path / "corpus.json"
    corpus.write_text(json.dumps([record]))
    proc = _run_driver(corpus)
    assert proc.returncode != 0
    assert proc.stdout == ""


def test_seed_corpus_blocks_every_condition_and_arms_some():
    records = _load_corpus()
    ids = [r["id"] for r in records]
    assert len(ids) == len(set(ids))
    assert len(records) >= MIN_RECORDS
    decisions = [evaluate(_decode(r)) for r in records]
    assert any(d.armed for d in decisions)
    blocked_somewhere = {n for d in decisions for n in _blocked(d)}
    for c in evaluate(inputs()).conditions:
        assert c.number in blocked_somewhere, f"no corpus record blocks condition {c.number}"


# --- pure judge ---------------------------------------------------------------------------------

_P = ARMABLE
_SRC = "".join(f"x{i} = {i}\n" for i in range(1, 11))
_SRC_COMMENT = _SRC.replace("x5 = 5\n", "x5 = 5\n# a note\n")
_SECTION = f"diff --git a/{_P} b/{_P}\n--- a/{_P}\n+++ b/{_P}\n@@ -5,1 +5,1 @@\n-x5 = 5\n+x5 = 5\n"
_SECTION_COMMENT = (f"diff --git a/{_P} b/{_P}\n--- a/{_P}\n+++ b/{_P}\n@@ -5,1 +5,2 @@\n x5 = 5\n"
                    "+# a note\n")


def _side(results=None, executed=None, fields=("a", "b")):
    """A SideResult. results: list of (armed, blocked numbers); default 10 armed + 10 held."""
    if results is None:
        results = [(True, ())] * 10 + [(False, (1,))] * 10
    rows = tuple((f"r{i}", armed, frozenset(blocked)) for i, (armed, blocked) in enumerate(results))
    if executed is None:
        executed = {_P: frozenset(range(1, 11))}
    return SideResult(tuple(fields), rows, executed)


def _base():
    return [(True, ())] * 10 + [(False, (1,))] * 10


def _judge(old, new, section=_SECTION, sources=(_SRC, _SRC)):
    return judge(old, new, {_P: section}, {_P: sources})


def test_judge_clean_replay_clears():
    v = _judge(_side(), _side())
    assert v.cleared is True
    assert v.reason == "clean"
    assert v.note.startswith("replay cleared arming-path (20 inputs: 0 loosened")


def test_judge_add_only_clears():
    new = _base()
    new[0] = (False, (99,))
    new[10] = (False, (1, 99))
    v = _judge(_side(), _side(new))
    assert v.cleared is True
    assert "0 loosened" in v.note
    assert "2 tightened (1 armed->held)" in v.note


def test_judge_held_to_armed_keeps():
    new = _base()
    new[10] = (True, ())
    v = _judge(_side(), _side(new))
    assert v.cleared is False
    assert "1 loosened (1 held->armed)" in v.note


def test_judge_dropped_hold_on_still_held_input_keeps():
    old = _base()
    old[10] = (False, (1, 7))
    new = _base()
    v = _judge(_side(old), _side(new))
    assert v.cleared is False
    assert "1 loosened (0 held->armed)" in v.note


def test_judge_small_corpus_keeps():
    nineteen = _base()[:19]
    assert _judge(_side(nineteen), _side(nineteen)).reason == "corpus-too-small"
    assert _judge(_side(_base()), _side(_base())).reason != "corpus-too-small"


def test_judge_shape_change_keeps():
    v = _judge(_side(), _side(fields=("a", "b", "c")))
    assert (v.cleared, v.reason) == (False, "inputs-shape-changed")


def test_judge_degenerate_corpus_keeps():
    armed = [(True, ())] * 20
    held = [(False, (1,))] * 20
    assert _judge(_side(armed), _side(armed)).reason == "corpus-degenerate"
    assert _judge(_side(held), _side(held)).reason == "corpus-degenerate"


def test_judge_unexecuted_changed_line_keeps():
    executed = {_P: frozenset({1, 2, 3, 4, 6, 7})}
    v = _judge(_side(executed=executed), _side(executed=executed))
    assert (v.cleared, v.reason) == (False, "unexercised-change")


def test_judge_change_in_unreached_file_keeps():
    v = _judge(_side(executed={}), _side(executed={}))
    assert (v.cleared, v.reason) == (False, "unexercised-change")


def test_judge_comment_only_change_in_reached_file_clears():
    executed = {_P: frozenset({1, 2, 3, 4, 5})}
    v = _judge(_side(executed=executed), _side(executed=executed), section=_SECTION_COMMENT,
               sources=(_SRC, _SRC_COMMENT))
    assert v.cleared is True


def test_judge_unreadable_section_and_source_keep():
    assert judge(_side(), _side(), {_P: None}, {_P: (_SRC, _SRC)}).reason == "unreadable-section"
    assert judge(_side(), _side(), {_P: "@@ bad @@\n"}, {_P: (_SRC, _SRC)}).reason == \
        "unreadable-section"
    assert judge(_side(), _side(), {_P: _SECTION}, {_P: (None, _SRC)}).reason == "unreadable-source"
    assert judge(_side(), _side(), {_P: _SECTION}, {_P: (_SRC, "x = (\n")}).reason == \
        "unreadable-source"


def test_judge_result_mismatch_keeps():
    new = _side()
    renamed = SideResult(new.fields, (("other",) + new.results[0][1:],) + new.results[1:],
                         new.executed)
    assert _judge(_side(), renamed).reason == "result-mismatch"


def test_changed_lines_counts_dash_dash_content_as_removed():
    section = "--- a/x.sql\n+++ b/x.sql\n@@ -1,2 +1,1 @@\n--- x\n keep\n"
    assert changed_lines(section) == ({1}, set())


def test_changed_lines_header_without_counts():
    assert changed_lines("@@ -3 +3 @@\n-a\n+b\n") == ({3}, {3})


def test_changed_lines_rejects_truncated_hunk():
    with pytest.raises(ValueError):
        changed_lines("@@ -1,3 +1,3 @@\n-a\n")


def test_inert_lines_excludes_string_continuation_and_first_two_lines():
    source = "# c1\n# c2\nx = '''a\n\nb'''\n# c6\n\ny = 1\n"
    assert inert_lines(source) == {6, 7}


def test_judge_note_has_no_sentence_break():
    new = _base()
    new[10] = (True, ())
    for v in (_judge(_side(), _side()), _judge(_side(), _side(new)),
              _judge(_side(), _side(fields=("z",)))):
        for bad in (". ", "|", " · "):
            assert bad not in v.note


# --- replay runner ------------------------------------------------------------------------------

def _git(repo, *args):
    env = {k: v for k, v in os.environ.items()
           if k not in ("GIT_DIR", "GIT_WORK_TREE", "GIT_INDEX_FILE")}
    return subprocess.run(
        ["git", "-C", str(repo), "-c", "user.name=t", "-c", "user.email=t@example.invalid",
         "-c", "core.hooksPath=/dev/null", "-c", "commit.gpgsign=false", *args],
        env=env, capture_output=True, text=True, check=True).stdout


def _tree(tmp_path):
    tree = tmp_path / "tree"
    for d in arming_replay.COPY_DIRS:
        shutil.copytree(REPO / d, tree / d, ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
    _git(tree, "init", "-q")
    _git(tree, "add", "-A")
    _git(tree, "commit", "-q", "-m", "base")
    return tree


def _edit(tree, path, old, new):
    p = tree / path
    text = p.read_text(encoding="utf-8")
    assert text.count(old) == 1, f"anchor not unique in {path}: {old!r}"
    p.write_text(text.replace(old, new), encoding="utf-8")
    return _git(tree, "diff")


def _append(tree, path, extra):
    p = tree / path
    p.write_text(p.read_text(encoding="utf-8") + extra, encoding="utf-8")
    return _git(tree, "diff")


def _sections(diff):
    out = {}
    chunks = diff.split("\ndiff --git ")
    for i, chunk in enumerate(chunks):
        text = chunk if i == 0 else "diff --git " + chunk
        if not text.startswith("diff --git "):
            continue
        path = text.splitlines()[0].rsplit(" b/", 1)[1]
        if path.startswith(arming_replay.HARNESS_DIR + "/") and path.endswith(".py"):
            out[path] = text if text.endswith("\n") else text + "\n"
    return out


def _replay(tree, diff):
    sections = _sections(diff)
    arming = frozenset(p for p in sections if p in _ARMING_PATHS)
    return arming_replay.replay_worktree(tree, diff, sections, arming)


def test_replay_comment_only_edit_clears(tmp_path):
    tree = _tree(tmp_path)
    v = _replay(tree, _append(tree, ARMABLE, "# fixture comment\n"))
    assert v.cleared is True, v


def test_replay_unused_function_keeps(tmp_path):
    tree = _tree(tmp_path)
    v = _replay(tree, _append(tree, ARMABLE, "\n\ndef _fixture_unused():\n    return 1\n"))
    assert (v.cleared, v.reason) == (False, "unexercised-change")


def test_replay_new_side_exception_keeps(tmp_path):
    tree = _tree(tmp_path)
    anchor = "def evaluate(inp: ArmInputs) -> ArmDecision:\n"
    diff = _edit(tree, ARMABLE, anchor, anchor + '    raise RuntimeError("fixture")\n')
    v = _replay(tree, diff)
    assert (v.cleared, v.reason) == (False, "error")


def test_replay_timeout_keeps(tmp_path, monkeypatch):
    tree = _tree(tmp_path)
    diff = _append(tree, ARMABLE, "# fixture comment\n")
    monkeypatch.setattr(arming_replay, "SIDE_TIMEOUT", 0.01)
    v = _replay(tree, diff)
    assert (v.cleared, v.reason) == (False, "timeout")


def test_replay_empty_corpus_keeps(tmp_path, monkeypatch):
    tree = _tree(tmp_path)
    diff = _append(tree, ARMABLE, "# fixture comment\n")
    empty = tmp_path / "empty.json"
    empty.write_text("[]")
    monkeypatch.setattr(arming_replay, "CORPUS_PATH", empty)
    v = _replay(tree, diff)
    assert (v.cleared, v.reason) == (False, "corpus-too-small")


def test_replay_missing_corpus_keeps(tmp_path, monkeypatch):
    tree = _tree(tmp_path)
    diff = _append(tree, ARMABLE, "# fixture comment\n")
    monkeypatch.setattr(arming_replay, "CORPUS_PATH", tmp_path / "absent.json")
    v = _replay(tree, diff)
    assert (v.cleared, v.reason) == (False, "corpus-missing")


def test_replay_apply_failure_keeps(tmp_path):
    tree = _tree(tmp_path)
    diff = (f"diff --git a/{ARMABLE} b/{ARMABLE}\n--- a/{ARMABLE}\n+++ b/{ARMABLE}\n"
            "@@ -1,2 +1,2 @@\n no such context line\n-also missing\n+replacement\n")
    v = _replay(tree, diff)
    assert (v.cleared, v.reason) == (False, "apply-failed")


def test_replay_missing_harness_tree_keeps(tmp_path):
    empty = tmp_path / "empty"
    empty.mkdir()
    v = arming_replay.replay_worktree(empty, "", {ARMABLE: None}, frozenset({ARMABLE}))
    assert (v.cleared, v.reason) == (False, "tree-missing")


def test_replay_cache_returns_same_verdict_without_second_spawn(tmp_path, monkeypatch):
    tree = _tree(tmp_path)
    diff = _append(tree, ARMABLE, "# fixture comment\n")
    first = _replay(tree, diff)
    assert first.cleared is True

    def boom(*_a, **_k):
        raise AssertionError("second spawn")

    monkeypatch.setattr(arming_replay.subprocess, "run", boom)
    assert _replay(tree, diff) == first


def test_replay_never_raises_on_bad_inputs(tmp_path):
    v = arming_replay.replay_worktree(tmp_path / "nope", "diff", {ARMABLE: None},
                                      frozenset({ARMABLE}))
    assert v.cleared is False
    tree = _tree(tmp_path)
    v = arming_replay.replay_worktree(tree, "not a diff at all\n", {ARMABLE: None},
                                      frozenset({ARMABLE}))
    assert v.cleared is False
    v = arming_replay.replay_worktree(tree, None, None, None)
    assert v.cleared is False


# --- worktree-level wiring ----------------------------------------------------------------------

_LOG_OLD = 'p5_reason = "Phase 5 deterministic failure"'
_LOG_NEW = 'p5_reason = "Phase 5 deterministic failure (see log)"'
_POST_PASS = "    # Post-pass: the only code here that releases a hold. Fail-closed on every path.\n"
_ADD_HOLD = '    cs.append(ConditionResult(99, "fixture hold", True, "fixture"))\n'
_MD_OLD = '    md_blocked = md != "TWO-WAY"'
_MD_NEW = "    md_blocked = False"


def test_worktree_log_message_edit_clears_self_governing(tmp_path):
    tree = _tree(tmp_path)
    diff = _edit(tree, ARMABLE, _LOG_OLD, _LOG_NEW)
    d = merge_danger.derive_from_worktree(tree, diff)
    assert d.self_governing is False, d.why
    assert d.door == "two-way"
    assert d.why.startswith("mechanical: replay cleared arming-path (48 inputs: 0 loosened")


def test_worktree_add_only_hold_clears_self_governing(tmp_path):
    tree = _tree(tmp_path)
    diff = _edit(tree, ARMABLE, _POST_PASS, _ADD_HOLD + _POST_PASS)
    d = merge_danger.derive_from_worktree(tree, diff)
    assert d.self_governing is False, d.why
    assert "0 loosened" in d.replay_note
    assert "48 tightened" in d.replay_note


def test_worktree_loosening_edit_keeps_self_governing(tmp_path):
    tree = _tree(tmp_path)
    diff = _edit(tree, ARMABLE, _MD_OLD, _MD_NEW)
    d = merge_danger.derive_from_worktree(tree, diff)
    assert d.self_governing is True
    assert f"arming-path:{ARMABLE}" in d.triggers
    assert d.replay_note.startswith("replay kept arming-path (48 inputs: ")
    assert "0 loosened" not in d.replay_note
    assert "held->armed" in d.replay_note


def test_worktree_phase2_and_arm_time_agree(tmp_path):
    cleared_tree = _tree(tmp_path / "a")
    diff = _edit(cleared_tree, ARMABLE, _LOG_OLD, _LOG_NEW)
    first = merge_danger.derive_from_worktree(cleared_tree, diff)
    assert merge_danger.derive_from_worktree(cleared_tree, diff) == first
    body = merge_danger.render_merge_danger(first)
    assert merge_danger.classify_with_floor(body, first) == "TWO-WAY"

    kept_tree = _tree(tmp_path / "b")
    diff = _edit(kept_tree, ARMABLE, _MD_OLD, _MD_NEW)
    kept = merge_danger.derive_from_worktree(kept_tree, diff)
    assert merge_danger.derive_from_worktree(kept_tree, diff) == kept
    stripped = merge_danger.render_merge_danger(kept).replace(" · Self-governing: yes", "")
    assert merge_danger.classify_with_floor(stripped, kept) != "TWO-WAY"
