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
    assert d.why.startswith("mechanical: replay cleared arming-path (53 inputs: 0 loosened")


def test_worktree_add_only_hold_clears_self_governing(tmp_path):
    tree = _tree(tmp_path)
    diff = _edit(tree, ARMABLE, _POST_PASS, _ADD_HOLD + _POST_PASS)
    d = merge_danger.derive_from_worktree(tree, diff)
    assert d.self_governing is False, d.why
    assert "0 loosened" in d.replay_note
    assert "53 tightened" in d.replay_note


def test_worktree_loosening_edit_keeps_self_governing(tmp_path):
    tree = _tree(tmp_path)
    diff = _edit(tree, ARMABLE, _MD_OLD, _MD_NEW)
    d = merge_danger.derive_from_worktree(tree, diff)
    assert d.self_governing is True
    assert f"arming-path:{ARMABLE}" in d.triggers
    assert d.replay_note.startswith("replay kept arming-path (53 inputs: ")
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


# --- fidelity replay stage (harness/fidelity_replay.py) -------------------------------------------

import ast  # noqa: E402
import textwrap  # noqa: E402
import threading  # noqa: E402
import types  # noqa: E402

from harness import fidelity_replay  # noqa: E402
from harness import fidelity_replay_driver as drv  # noqa: E402
from harness.fidelity_replay import (ORDERING, REPLAYED_ROOTS, Unresolvable,  # noqa: E402
                                     check_resolvable, line_owner, looser_output,
                                     production_roots, top_defs)

PKG = REPO / "tools" / "postplan-harness"
FIDELITY = fidelity_replay.FIDELITY_PATH


def _fid_records():
    return json.loads(fidelity_replay.CORPUS_PATH.read_text(encoding="utf-8"))


def _ready_text():
    for rec in _fid_records():
        if rec["fid"].get("verdict_1") == "READY" and not rec["id"].startswith("fixture:"):
            return rec["verdict_1"]
    raise AssertionError("corpus has no live READY record")


def _fake_run(out_dir, name, stored, raw, pr):
    run = out_dir / name
    (run / "fidelity-packet").mkdir(parents=True)
    (run / "fidelity-packet" / "diff.patch").write_text(
        "diff --git a/x.py b/x.py\n--- a/x.py\n+++ b/x.py\n@@ -1 +1 @@\n-a\n+b\n")
    (run / "raw-plan-fidelity-review-attempt0.txt").write_text(raw)
    (run / "result.json").write_text(json.dumps({
        "pr_number": pr, "plan": "p.md",
        "fidelity": {"verdict_1": stored, "verdict_2": None, "reviewed_tree": None}}))


def _live_ids(dest):
    return [r["id"] for r in json.loads(dest.read_text()) if r["id"].startswith("live-")]


def test_harvest_rejects_mismatched_verdict(tmp_path):
    out, dest = tmp_path / "out", tmp_path / "corpus.json"
    _fake_run(out, "live-mismatch-1", "NOT READY", _ready_text(), 990001)
    fidelity_replay.harvest(out, dest)
    assert _live_ids(dest) == []
    _fake_run(out, "live-match-1", "READY", _ready_text(), 990002)
    fidelity_replay.harvest(out, dest)
    assert _live_ids(dest) == ["live-match-1"]


def test_committed_corpus_has_no_problems():
    assert fidelity_replay.corpus_problems(_fid_records()) == []


def test_corpus_problems_flags_small_corpus():
    small = _fid_records()[:fidelity_replay.MIN_FIDELITY_RECORDS - 1]
    assert any("fewer than" in p for p in fidelity_replay.corpus_problems(small))


def test_cli_rejects_unknown_subcommand():
    assert fidelity_replay.main(["harvst"]) == 2
    assert fidelity_replay.main(["harvest", "only-one"]) == 2
    assert fidelity_replay.main([]) == 2


def _run_fid_driver(corpus, side_root):
    pkg = side_root / "tools" / "postplan-harness"
    return subprocess.run([sys.executable, str(fidelity_replay.DRIVER_PATH), str(corpus), str(pkg),
                           str(side_root)], capture_output=True, text=True, timeout=300,
                          cwd=str(pkg))


def test_driver_runs_committed_corpus():
    proc = _run_fid_driver(fidelity_replay.CORPUS_PATH, REPO)
    assert proc.returncode == 0, proc.stderr
    payload = json.loads(proc.stdout)
    assert [r["id"] for r in payload["results"]] == [r["id"] for r in _fid_records()]
    assert all("ok" in r["out"]["v1.parse_verdict"] for r in payload["results"])
    fn = top_defs(ast.parse((REPO / FIDELITY).read_text(encoding="utf-8")))["reviewer_arming_holds"]
    body = set(range(fn.body[0].lineno, fn.end_lineno + 1))
    assert body & set(payload["executed"][FIDELITY])


def test_driver_exits_nonzero_on_empty_verdict_text(tmp_path):
    corpus = tmp_path / "c.json"
    corpus.write_text(json.dumps([dict(_fid_records()[0], verdict_1="")]))
    proc = _run_fid_driver(corpus, REPO)
    assert proc.returncode == 4
    assert proc.stdout == ""


def _delete_def(source, name):
    node = top_defs(ast.parse(source))[name]
    start = min([node.lineno] + [d.lineno for d in node.decorator_list])
    lines = source.splitlines(keepends=True)
    del lines[start - 1:node.end_lineno]
    return "".join(lines)


def test_driver_marks_missing_root_absent(tmp_path):
    side = tmp_path / "side"
    shutil.copytree(PKG / "harness", side / arming_replay.HARNESS_DIR,
                    ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
    fid = side / FIDELITY
    fid.write_text(_delete_def(fid.read_text(encoding="utf-8"), "sticky_terminal"),
                   encoding="utf-8")
    corpus = tmp_path / "c.json"
    corpus.write_text(json.dumps(_fid_records()[:3]))
    proc = _run_fid_driver(corpus, side)
    assert proc.returncode == 0, proc.stderr
    outs = [r["out"] for r in json.loads(proc.stdout)["results"]]
    assert all(o["sticky_terminal"] == {"absent": True} for o in outs)
    assert all("ok" in o["v1.parse_verdict"] for o in outs)


def test_looser_output_verdict_ordering():
    def looser(old, new):
        return looser_output("verdict", {"ok": old}, {"ok": new})
    assert looser("NOT READY", "READY") is True
    assert looser(None, "NOT READY") is True
    assert looser("NOT READY", "READY WITH NOTES") is True
    assert looser("READY", "NOT READY") is False
    assert looser("READY", "READY") is False
    assert looser("READY", "READY (sticky)") is True


def test_looser_output_sets_flags_errors():
    def s(old, new):
        return looser_output("set", {"ok": old}, {"ok": new})
    assert s(["a", "b"], ["a"]) is True
    assert s(["a"], ["a reworded"]) is True
    assert s(["a"], ["a", "b"]) is False
    assert looser_output("flag", {"ok": False}, {"ok": True}) is True
    assert looser_output("flag", {"ok": True}, {"ok": False}) is False
    assert looser_output("set", {"ok": []}, {"raise": "KeyError"}) is True
    assert looser_output("set", {"raise": "KeyError"}, {"ok": []}) is True
    assert looser_output("set", {"raise": "KeyError"}, {"raise": "KeyError"}) is False


def test_ordering_and_roots_match_driver():
    tree = ast.parse(fidelity_replay.DRIVER_PATH.read_text(encoding="utf-8"))
    keys = [ast.literal_eval(n.value) for n in tree.body if isinstance(n, ast.Assign)
            and isinstance(n.targets[0], ast.Name) and n.targets[0].id == "OUTPUT_KEYS"]
    assert keys == [tuple(ORDERING)]
    attrs = {n.attr for n in ast.walk(tree) if isinstance(n, ast.Attribute)
             and isinstance(n.value, ast.Name) and n.value.id == "fidelity"}
    assert attrs - {"verdict_path", "STICKY_MARKER"} == set(REPLAYED_ROOTS)


def test_static_analyzer_near_misses():
    check_resolvable(ast.parse("def f(x):\n    return getattr(x, 'a')\n"))
    for src in ("def f(x, a):\n    return getattr(x, a)\n",
                "def f():\n    return globals()\n",
                "import sys\ndef f():\n    return sys.modules\n"):
        with pytest.raises(Unresolvable):
            check_resolvable(ast.parse(src))
    owner = line_owner(top_defs(ast.parse("def f(a=None,\n      b=-1):\n    return a\n")))
    assert owner[1] == ("f", "header") and owner[2] == ("f", "header")
    assert 2 not in line_owner(top_defs(ast.parse("def f(a=None,\n      b=g()):\n    return a\n")))
    with pytest.raises(Unresolvable):
        production_roots({"tools/x.py": "from harness import fidelity\nx = fidelity\n"})
    assert production_roots({"tools/x.py": "from harness import fidelity\nfidelity.y()\n"}) == {
        "y": {"tools/x.py"}}
    lazy = "def f():\n    from harness import fidelity\n    return fidelity.y()\n"
    shadowed = lazy + "def g(fidelity=None):\n    return {'k': fidelity}\n"
    assert production_roots({"tools/x.py": shadowed}) == {"y": {"tools/x.py"}}
    with pytest.raises(Unresolvable):
        production_roots({"tools/x.py": lazy + "def h():\n    from harness import fidelity\n"
                                               "    return [fidelity]\n"})
    for src in ("from ..fidelity import f\nf()\n", "from .. import fidelity\nfidelity.f()\n"):
        with pytest.raises(Unresolvable):
            production_roots({"harness/adapters/x.py": src})
    assert production_roots({"tools/x.py": "import harness as h\nh.fidelity.f()\n"}) == {
        "f": {"tools/x.py"}}
    assert production_roots({"tools/x.py": "import harness\nharness.fidelity.f()\n"}) == {
        "f": {"tools/x.py"}}
    # A non-Python file holds only on a real module reference outside a comment line.
    for prose in ("#!/bin/sh\n# fidelity.record_reviewed_tree writes it; see harness/x.py\n",
                  "#!/bin/sh\necho 'harness ran the Phase 5.5 fidelity reviewer'\n",
                  "#!/bin/sh\nls post-plan-fidelity-verdict-1.md harness/fidelity_replay.py\n"):
        assert production_roots({"bin/x": prose}) == {}
    for ref in ("#!/bin/sh\npython3 -m harness.fidelity check\n",
                "#!/bin/sh\npython3 tools/postplan-harness/harness/fidelity.py\n",
                "#!/bin/sh\npython3 - <<'EOF'\nimport fidelity\nEOF\n",
                "#!/bin/sh\npython3 - <<'EOF'\nfrom fidelity import f\nEOF\n"):
        with pytest.raises(Unresolvable):
            production_roots({"bin/x": ref})
    master = ast.parse((REPO / FIDELITY).read_text(encoding="utf-8"))
    check_resolvable(master)
    top_defs(master)
    files = {p.relative_to(REPO).as_posix(): p.read_text(encoding="utf-8")
             for p in sorted(PKG.glob("*.py")) + sorted((PKG / "harness").glob("*.py"))}
    assert "tools/postplan-harness/runner.py" in production_roots(files)["reviewer_arming_holds"]


def _insert_first_line(source, func, stmt):
    first = top_defs(ast.parse(source))[func].body[0]
    lines = source.splitlines(keepends=True)
    block = textwrap.indent(textwrap.dedent(stmt).strip("\n") + "\n", " " * first.col_offset)
    lines.insert(first.lineno - 1, block)
    return "".join(lines)


def _fid_append(source, text):
    return source.rstrip("\n") + "\n\n\n" + text.strip("\n") + "\n"


def _fid_tree(tmp_path):
    tree = tmp_path / "fidtree"
    for d in arming_replay.COPY_DIRS:
        shutil.copytree(REPO / d, tree / d, ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
    for py in sorted(PKG.glob("*.py")):
        shutil.copy2(py, tree / "tools" / "postplan-harness" / py.name)
    _git(tree, "init", "-q")
    _git(tree, "add", "-A")
    _git(tree, "commit", "-q", "-m", "base")
    return tree


def _fid_edit(tree, edit):
    p = tree / FIDELITY
    p.write_text(edit(p.read_text(encoding="utf-8")), encoding="utf-8")
    return _git(tree, "diff")


def _fid_replay(tmp_path, edit):
    tree = _fid_tree(tmp_path)
    v = _replay(tree, _fid_edit(tree, edit))
    return v, f"{v.note} {v.reason}"


def test_dropping_a_reviewer_hold_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(s, "_landed_later", "return True"))
    assert v.cleared is False
    assert "fidelity-loosened" in text and "holds." in text


def _landed_corpus(tmp_path):
    """The committed corpus plus, per record with a missing file item, a copy whose diff lands
    that file, so the base `_landed_later` returns True and drops the hold."""
    records = _fid_records()
    extra = []
    for rec in records:
        items = [i for i in fidelity_replay._missing_items(rec.get("shadow"))
                 if isinstance(i, dict) and i.get("kind") in ("file", "test") and i.get("token")]
        if items:
            landed = json.loads(json.dumps(rec))
            landed["id"] = rec["id"] + "~landed"
            landed["changed_files"] = list(rec["changed_files"]) + [i["token"].strip("`") for i in items]
            landed["review_inputs"] = None
            extra.append(landed)
    assert extra, "corpus has no record with a missing file item"
    path = tmp_path / "landed-corpus.json"
    path.write_text(json.dumps(records + extra), encoding="utf-8")
    return path


def _driver_side(corpus, tree):
    proc = _run_fid_driver(corpus, tree)
    assert proc.returncode == 0, proc.stderr
    return fidelity_replay._fid_side(json.loads(proc.stdout))


def test_adding_a_reviewer_hold_clears(tmp_path, monkeypatch):
    corpus = _landed_corpus(tmp_path)
    monkeypatch.setattr(fidelity_replay, "CORPUS_PATH", corpus)
    tree = _fid_tree(tmp_path)
    base = _driver_side(corpus, tree)
    probe = "tools/postplan-harness/tests/test_probe.py"  # never copied into the replay sides
    (tree / probe).parent.mkdir(parents=True, exist_ok=True)
    (tree / probe).write_text("X = 1\n")
    _git(tree, "add", "-A")
    _git(tree, "commit", "-q", "-m", "probe")
    _append(tree, probe, "Y = 2\n")
    diff = _fid_edit(tree, lambda s: _insert_first_line(s, "_landed_later", "return False"))
    cand = _driver_side(corpus, tree)
    # The candidate keeps holds the base dropped: a strict superset on at least one record.
    grew = [rid for (rid, o), (_, n) in zip(base.results, cand.results)
            if o["holds.stored_shadow"] != n["holds.stored_shadow"]]
    assert grew, "the landed records did not change any hold"
    v = _replay(tree, diff)
    text = f"{v.note} {v.reason}"
    assert v.cleared is True, text
    assert "fidelity replay cleared" in text


def test_verdict_promotion_stays_held(tmp_path):
    tree = _fid_tree(tmp_path)
    base = _driver_side(fidelity_replay.CORPUS_PATH, tree)
    diff = _fid_edit(tree, lambda s: _insert_first_line(s, "parse_verdict", 'return "READY"'))
    cand = _driver_side(fidelity_replay.CORPUS_PATH, tree)
    loosened, _ = fidelity_replay.compare_sides(base, cand, set())
    # The verdict ordering itself must flag the NOT READY -> READY promotion.
    assert any(k.endswith(":v1.parse_verdict") for k in loosened)
    v = _replay(tree, diff)
    assert v.cleared is False
    assert "fidelity-loosened" in f"{v.note} {v.reason}"


def test_unreplayed_channel_change_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(s, "re_review", "_probe = 0"))
    assert v.cleared is False
    assert "fidelity-unreplayed-channel: re_review" in text


def test_module_level_change_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _fid_append(s, "_PROBE = 1"))
    assert v.cleared is False
    assert "fidelity-module-level-change" in text


def test_duplicate_def_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _fid_append(
        s, "def parse_verdict(path):\n    return None"))
    assert v.cleared is False
    assert "fidelity-duplicate-def" in text


def test_computed_getattr_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", "getattr(path, str(path)[:0] or 'strip')"))
    assert v.cleared is False
    assert "fidelity-unresolvable-dispatch" in text


def test_dead_function_change_clears(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _fid_append(s, "def _probe_dead():\n    return 1"))
    assert v.cleared is True, text
    assert "fidelity replay cleared" in text
    assert any("dead _probe_dead" in line for line in fidelity_replay.LAST_DETAIL)


def test_unexercised_change_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", 'if path == "/never/":\n    return "READY"'))
    assert v.cleared is False
    assert "fidelity-unexercised-change" in text


def test_read_only_open_clears(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", "open(path).close()"))
    assert v.cleared is True, text


def test_parameter_write_in_sticky_terminal_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "sticky_terminal", 'fid["k"] = 1'))
    assert v.cleared is False
    assert "argument-mutated" in text


def test_augmented_assign_on_parameter_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "sticky_terminal", 'fid |= {"zz": 1}'))
    assert v.cleared is False, text
    assert "argument-mutated" in text


def test_aliased_parameter_write_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "sticky_terminal", '_a = fid; _a["zz"] = 1'))
    assert v.cleared is False, text
    assert "argument-mutated" in text


def test_lambda_parameter_write_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "sticky_terminal", '(lambda d: d.__setitem__("zz", 1))(fid)'))
    assert v.cleared is False, text
    assert "argument-mutated" in text


def test_call_through_bound_method_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "sticky_terminal", '_set = fid.__setitem__; _set("zz", 1)'))
    assert v.cleared is False, text
    assert "argument-mutated" in text


def test_aliased_environ_write_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", 'import os as _os; _e = _os.environ; _e["FID_PROBE"] = "1"'))
    assert v.cleared is False, text
    assert "audit:os.putenv" in text


def test_environ_data_write_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", 'import os as _os; _d = _os.environ._data; _d[b"FID_PROBE"] = b"1"'))
    assert v.cleared is False, text
    assert "environ:FID_PROBE" in text


def test_aliased_open_for_append_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", '_o = open; _fh = _o(path, "a"); _fh.write(""); _fh.close()'))
    assert v.cleared is False, text
    assert "audit:open-write" in text


def test_pathlib_open_for_append_stays_held(tmp_path):
    """`pathlib.Path(path).open("a")` raises a write-mode `open` audit event through an alias."""
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", 'import pathlib as _pl; _pl.Path(path).open("a").close()'))
    assert v.cleared is False, text
    assert "audit:open-write" in text


def test_module_global_write_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", 'import harness.second_review as _sr; _m = _sr; _m._FID_PROBE = 1'))
    assert v.cleared is False, text
    assert "global:harness.second_review._FID_PROBE" in text


def test_changed_line_compile_call_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(
        s, "parse_verdict", 'compile("1", "<p>", "eval")'))
    assert v.cleared is False, text
    assert "fidelity-changed-line-hazard: compile" in text


def test_static_side_effect_walker_removed():
    for name in ("side_effect", "_stmt_effect", "_def_effect", "FILE_WRITE_ATTRS",
                 "MUTATING_METHODS", "SIDE_EFFECT_NAMES", "SIDE_EFFECT_MODULES"):
        assert not hasattr(fidelity_replay, name)


@pytest.fixture
def watch():
    drv._WATCH.update(phase=None, record="-", where="t", hits=[])


@pytest.mark.parametrize("args,expected", [
    (("p", "r", os.O_RDONLY | os.O_CLOEXEC), False),
    (("p", "rb", 0), False),
    (("p", "w", 0), True),
    (("p", "a", 0), True),
    (("p", "r+", 0), True),
    (("p", "x", 0), True),
    (("p", None, os.O_RDONLY), False),
    (("p", None, os.O_WRONLY | os.O_CREAT), True),
    (("p", None, os.O_RDWR), True),
    (("p", b"r", 0), True),
    (("p", None, "0"), True),
    (("p", "r"), True),
])
def test_write_mode_open_detection(args, expected):
    assert drv._write_open(args) is expected


@pytest.mark.parametrize("event,expected", [
    ("os.remove", True), ("os.putenv", True), ("subprocess.Popen", True),
    ("socket.connect", True), ("tempfile.mkstemp", True), ("ctypes.dlopen", True),
    ("os.some_future_event", True),
    ("os.listdir", False), ("os.scandir", False), ("import", False), ("exec", False),
    ("compile", False), ("open", False), ("sys._getframe", False), ("os_x.thing", False),
])
def test_audit_event_classification(event, expected):
    assert drv._denied(event, "call") is expected


def test_call_only_events_ignored_at_import():
    for event in ("object.__setattr__", "sys.settrace"):
        assert drv._denied(event, "import") is False
        assert drv._denied(event, "call") is True


def test_uncopyable_argument_fails_closed(watch):
    assert drv._arg_copy((threading.Lock(),), {}) is drv._UNCOPYABLE
    assert drv._WATCH["hits"][-1].endswith("argument-uncopyable")


def test_uncomparable_argument_fails_closed(watch):
    assert drv._arg_copy((object(),), {}) is drv._UNCOPYABLE
    assert drv._WATCH["hits"][-1].endswith("argument-uncomparable")


def test_uncopyable_module_global_fails_closed(watch):
    m = types.ModuleType("m")
    m.lock = threading.Lock()
    drv._freeze_globals({"m": m})
    assert drv._WATCH["hits"][-1].endswith("global-uncopyable:m.lock")


def _run_driver_in(tree):
    pkg = tree / "tools" / "postplan-harness"
    env = {k: v for k, v in os.environ.items() if k != "PYTHONDONTWRITEBYTECODE"}
    proc = subprocess.run(
        [sys.executable, str(fidelity_replay.DRIVER_PATH), str(fidelity_replay.CORPUS_PATH),
         str(pkg), str(tree)], capture_output=True, text=True, timeout=300, cwd=str(pkg), env=env)
    return pkg, proc


def test_driver_import_writes_no_bytecode(tmp_path):
    tree = _fid_tree(tmp_path)
    pkg, proc = _run_driver_in(tree)
    assert proc.returncode == 0, proc.stderr
    assert not list((pkg / "harness").glob("__pycache__"))
    payload = json.loads(proc.stdout)
    assert not any(" import " in e for e in payload["side_effects"])


def test_import_time_side_effect_is_recorded(tmp_path):
    tree = _fid_tree(tmp_path)
    armable = tree / "tools" / "postplan-harness" / "harness" / "armable.py"
    armable.write_text(armable.read_text(encoding="utf-8")
                       + '\nopen(__file__ + ".probe", "a").close()\n', encoding="utf-8")
    _, proc = _run_driver_in(tree)
    assert proc.returncode == 0, proc.stderr
    assert any("import audit:open-write" in e for e in json.loads(proc.stdout)["side_effects"])


_DEFERRED_WRITES = {
    # a gc finalizer: it runs whenever the collector next runs, often between replayed calls
    "finalizer": ("class _Probe:\n"
                  "    def __del__(self):\n"
                  "        open(__file__ + '.probe', 'a').close()\n",
                  "    _p = _Probe(); _p.me = _p\n"),
    # a thread left running after the call returns
    "thread": ("import threading as _thr\n",
               "    _thr.Thread(target=lambda: (_thr.Event().wait(0.2),"
               " open(__file__ + '.probe', 'a').close())).start()\n"),
    # an exit callback: it runs after the last replayed call
    "atexit": ("import atexit as _ax\n",
               "    _ax.register(lambda: open(__file__ + '.probe', 'a').close())\n"),
}


@pytest.mark.parametrize("kind", sorted(_DEFERRED_WRITES))
def test_deferred_write_after_a_replayed_call_is_recorded(tmp_path, kind):
    tree = _fid_tree(tmp_path)
    fid = tree / FIDELITY
    module_code, call_code = _DEFERRED_WRITES[kind]
    fid.write_text(fid.read_text(encoding="utf-8") + "\n" + module_code
                   + "_orig_parse_verdict = parse_verdict\n\n\ndef parse_verdict(path):\n"
                   + call_code + "    return _orig_parse_verdict(path)\n", encoding="utf-8")
    _, proc = _run_driver_in(tree)
    assert proc.returncode == 0, proc.stderr
    effects = json.loads(proc.stdout)["side_effects"]
    assert any(" import " not in e for e in effects), effects


_DEFERRED_SETUPS = {
    # no write at all: leaving a thread running or an exit callback behind is itself the hit
    "thread-started": ("import threading as _thr\n",
                       "    _thr.Thread(target=_thr.Event().wait, args=(0.5,), daemon=True).start()\n"),
    "atexit-registered": ("import atexit as _ax\n", "    _ax.register(int)\n"),
}


@pytest.mark.parametrize("kind", sorted(_DEFERRED_SETUPS))
def test_call_leaving_a_thread_or_exit_callback_is_recorded(tmp_path, kind):
    tree = _fid_tree(tmp_path)
    fid = tree / FIDELITY
    module_code, call_code = _DEFERRED_SETUPS[kind]
    fid.write_text(fid.read_text(encoding="utf-8") + "\n" + module_code
                   + "_orig_parse_verdict = parse_verdict\n\n\ndef parse_verdict(path):\n"
                   + call_code + "    return _orig_parse_verdict(path)\n", encoding="utf-8")
    _, proc = _run_driver_in(tree)
    assert proc.returncode == 0, proc.stderr
    effects = json.loads(proc.stdout)["side_effects"]
    assert any(e.endswith(" " + kind) for e in effects), effects


def test_driver_output_without_side_effects_is_an_error():
    # Every other key is present, so only the missing side_effects key can raise.
    with pytest.raises(KeyError, match="side_effects"):
        fidelity_replay._fid_side({"schema": "fidelity-replay/1", "results": [], "executed": {},
                                   "branches": None, "members": []})


@pytest.mark.parametrize("key", ["branches", "members"])
def test_driver_output_without_branch_keys_is_an_error(key):
    payload = {"schema": "fidelity-replay/1", "results": [], "executed": {}, "side_effects": [],
               "branches": None, "members": []}
    del payload[key]
    with pytest.raises(KeyError, match=key):
        fidelity_replay._fid_side(payload)


@pytest.mark.parametrize("src,line,token", [
    ("def f():\n    global _X\n", 2, "global"),
    ("def f():\n    n = 0\n    def g():\n        nonlocal n\n", 4, "nonlocal"),
    ("x = compile('1', 's', 'eval')", 1, "compile"),
    ("m = __import__('os')", 1, "__import__"),
    ("importlib.reload(m)", 1, "importlib"),
    ("from importlib import reload", 1, "importlib"),
    ("import importlib.util", 1, "importlib"),
    ("y = eval('1')", 1, "eval"),
])
def test_changed_line_hazard_flags_declarations_and_dynamic_code(src, line, token):
    assert fidelity_replay.changed_line_hazard(ast.parse(src), line) == token


@pytest.mark.parametrize("src", [
    "p = re.compile('x')", "s = 'exec'", "_global = 1", "self.importlib_cache = 1",
    "x.eval()", "# global _X",
])
def test_changed_line_hazard_near_misses(src):
    assert fidelity_replay.changed_line_hazard(ast.parse(src), 1) is None


def test_corpus_carries_no_decompile_tokens():
    text = fidelity_replay.CORPUS_PATH.read_text(encoding="utf-8")
    assert fidelity_replay._DECOMPILE_TOKEN_RE.search(text) is None
    assert fidelity_replay.redact_decompile_tokens(
        "FUN" "_004e3860 DAT" "_00669f58 jsb560" "_decompiled" ".c +" "0x6BA8") == (
        "FUN-004e3860 DAT-00669f58 jsb560-decompiled-c +0X6BA8")
    assert fidelity_replay._GATED_LITERAL_RE.search(text) is None
    assert fidelity_replay.redact_decompile_tokens(
        "--" "depth=50; owner-approve " "mode=x") == "--Depth=50; owner-approve Mode=x"
    assert fidelity_replay.redact_decompile_tokens("_phase" "-6.5-arm-auto-merge.md") == (
        "_Phase-6.5-arm-auto-merge.md")


def test_missing_fidelity_corpus_stays_held(tmp_path, monkeypatch):
    monkeypatch.setattr(fidelity_replay, "CORPUS_PATH", tmp_path / "absent.json")
    v, text = _fid_replay(tmp_path, lambda s: _insert_first_line(s, "_landed_later", "return False"))
    assert v.cleared is False
    assert "fidelity-corpus-missing" in text


def test_self_edit_stays_held(tmp_path):
    tree = _fid_tree(tmp_path)
    _fid_edit(tree, lambda s: _insert_first_line(s, "_landed_later", "return False"))
    diff = _append(tree, arming_replay.HARNESS_DIR + "/fidelity_replay.py", "# probe\n")
    # Only fidelity.py's section goes in, so the base judge has no fidelity_replay.py line to
    # hold on and the stage's own self-edit check is what keeps the flag.
    section = merge_danger._section_for(diff, FIDELITY)
    v = arming_replay.replay_worktree(tree, diff, {FIDELITY: section}, frozenset({FIDELITY}))
    assert v.cleared is False
    assert "fidelity-replay-self-edit" in f"{v.note} {v.reason}"


def test_non_fidelity_diff_skips_stage(tmp_path, monkeypatch):
    calls = []
    monkeypatch.setattr(fidelity_replay, "replay_sides",
                        lambda *a, **k: calls.append(a) or (True, "x", []))
    tree = _fid_tree(tmp_path)
    diff = _append(tree, arming_replay.HARNESS_DIR + "/second_review.py", "\n_PROBE = 1\n")
    assert FIDELITY not in _sections(diff)
    _replay(tree, diff)
    assert calls == []


# --- branch outcomes (ADR-0283 residual gap) ------------------------------------------------------

def _fid_swap(old, new, func=None):
    """Replace `old` once: in the whole file, or only inside top-level def `func`."""
    def edit(source):
        lo, hi = 0, len(source)
        if func is not None:
            node = top_defs(ast.parse(source))[func]
            starts = [0]
            for ln in source.splitlines(keepends=True):
                starts.append(starts[-1] + len(ln))
            lo, hi = starts[node.lineno - 1], starts[node.end_lineno]
        body = source[lo:hi]
        assert body.count(old) == 1, old
        return source[:lo] + body.replace(old, new) + source[hi:]
    return edit


def test_weakened_branch_no_record_takes_stays_held(tmp_path):
    """Review thread 4691330476: the line runs on every record, but no record has the hold set,
    so the weakened condition is never true and the outputs match."""
    v, text = _fid_replay(tmp_path, _fid_swap(
        'if md["hold"] is True:', 'if md["hold"] is True and md.get("door") == "never":'))
    assert v.cleared is False, text
    assert "fidelity-unexercised-branch: reviewer_arming_holds:" in text
    assert 'md["hold"] is True' in text and "never True" in text


def test_dropped_membership_member_stays_held(tmp_path):
    v, text = _fid_replay(tmp_path, _fid_swap(
        'if status not in ("present", "carried"):', 'if status not in ("present",):'))
    assert v.cleared is False, text
    assert "fidelity-unexercised-branch: reviewer_arming_holds:" in text
    assert "never matched 'carried'" in text


def test_rewrite_of_a_branch_the_corpus_takes_both_ways_clears(tmp_path):
    v, text = _fid_replay(tmp_path, _fid_swap(
        "if line.strip() == DIGEST_CUT:", "if DIGEST_CUT == line.strip():", "parse_verdict"))
    assert v.cleared is True, text
    assert "fidelity replay cleared 2 changed lines" in text


@pytest.mark.parametrize("src", [
    # the probe fires at the line's first event, before `status` is rebound
    'status = "carried"; ok = status not in ("present",)\n',
    'ok = status not in ("present",); status = "carried"\n',
    # an earlier statement that only ends on the site's line
    'status = str(\n    1); ok = status not in ("present",)\n',
    'try:\n    pass\nexcept ValueError as status: ok = status not in ("present",)\n',
])
def test_membership_site_sharing_its_line_is_dropped(src):
    assert drv._member_sites(src) == {}


def test_membership_site_alone_on_its_line_is_kept():
    src = ('def f(status):\n'
           '    status = "carried"\n'
           '    return status not in ("present",)\n')
    assert list(drv._member_sites(src)) == [3]


_GAP_SRC = ("def f(x, s):\n"
            "    if x in ('a', 'b'):\n"
            "        return 1\n"
            "    return 2 if s else 3\n")


def _gap(line, branches=frozenset(), members=None):
    side = fidelity_replay.FidSide("fidelity-replay/1", (), {}, (), branches, members)
    code = compile(_GAP_SRC, FIDELITY, "exec", dont_inherit=True)
    return fidelity_replay.branch_gap(ast.parse(_GAP_SRC), code, _GAP_SRC, line, side)


def _both_ways(line):
    """Fake branch events that take the jump on `line` of `f` both ways."""
    import dis
    f_code = next(c for c in compile(_GAP_SRC, FIDELITY, "exec").co_consts
                  if isinstance(c, types.CodeType))
    instrs = list(dis.get_instructions(f_code))
    lefts = dict((s, left) for s, left, _r in f_code.co_branches()) if hasattr(
        f_code, "co_branches") else {}
    for i, ins in enumerate(instrs):
        if ins.opname.startswith("POP_JUMP") and ins.positions.lineno == line:
            left = lefts.get(ins.offset, instrs[i + 1].offset)
            return frozenset({("f", 1, ins.offset, left), ("f", 1, ins.offset, -1)})
    raise AssertionError(f"no jump on line {line}")


def test_branch_gap_fails_closed():
    assert "never True or False" in _gap(4)  # no record took the jump either way
    assert "cannot record" in _gap(4, branches=None)  # this interpreter recorded no branches
    assert _gap(4, branches=_both_ways(4)) is None
    full = _both_ways(2)
    key = (2, 7, 2, 22)
    assert "membership test the replay cannot read" in _gap(2, full, {})
    assert "operand the replay cannot read" in _gap(2, full, {key: (frozenset({0, 1}), True, True)})
    assert "never matched 'b'" in _gap(2, full, {key: (frozenset({0}), True, False)})
    assert "outside the list" in _gap(2, full, {key: (frozenset({0, 1}), False, False)})
    assert _gap(2, full, {key: (frozenset({0, 1}), True, False)}) is None
