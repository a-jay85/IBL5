"""Phase 4 review depth wiring: `runner._review_phase_for` takes the deeper of the Phase-2
body and the live PR body, and the cache key follows the depth."""
import ast
import json
import os
import sys

import pytest

HARNESS_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, HARNESS_DIR)
sys.path.insert(0, os.path.join(HARNESS_DIR, "tests"))

import runner
from harness import reviewcache
from harness.classify import classify, files_from_diff
from harness.review import ReviewPhase
from test_review_cache_hit import DIFF, GATES, _arm

with open(os.path.join(HARNESS_DIR, "tests", "fixtures", "review_depth_cases.json")) as _fh:
    B = {row["id"]: row["body"] for row in json.load(_fh)}


def _build(phase2, meta):
    logs: list[str] = []
    phase = runner._review_phase_for(None, None, {"summary_md": phase2}, meta, logs.append)
    return phase, logs


def test_both_standard_is_standard():
    phase, _ = _build(B["two-way-localized"], {"body": B["two-way-module"]})
    assert phase.depth == "standard"


def test_live_one_way_deepens_standard_phase2():
    phase, _ = _build(B["two-way-localized"], {"body": B["one-way-localized"]})
    assert phase.depth == "deep"


def test_phase2_one_way_beats_stale_live():
    phase, _ = _build(B["one-way-external"], {"body": B["two-way-localized"]})
    assert phase.depth == "deep"


def test_live_missing_line_is_deep():
    phase, _ = _build(B["two-way-localized"], {"body": B["missing-line"]})
    assert phase.depth == "deep"


@pytest.mark.parametrize("meta", [None, {}, {"body": None}, {"body": 7}])
def test_live_unreadable_is_deep(meta):
    phase, _ = _build(B["two-way-localized"], meta)
    assert phase.depth == "deep"


def test_clean_rerun_uses_live_only():
    standard, _ = _build("", {"body": B["two-way-localized"]})
    deep, _ = _build("", {"body": B["missing-line"]})
    assert standard.depth == "standard"
    assert deep.depth == "deep"


def test_depth_log_line():
    _, logs = _build(B["two-way-localized"], {"body": B["one-way-localized"]})
    assert logs == ["review-depth: deep (phase2=two-way/localized, live=one-way)"]


def test_deep_gates_miss_standard_cache():
    cls = classify(files_from_diff(DIFF), DIFF)
    deep_gates = ReviewPhase(None, None, depth="deep").gates(cls)
    assert reviewcache.restore_review(_arm(), GATES) is not None
    assert reviewcache.restore_review(_arm(), deep_gates) is None


def test_runner_builds_review_phase_only_via_helper():
    with open(os.path.join(HARNESS_DIR, "runner.py")) as fh:
        tree = ast.parse(fh.read())
    helper = next(n for n in ast.walk(tree)
                  if isinstance(n, ast.FunctionDef) and n.name == "_review_phase_for")
    inside = {id(n) for n in ast.walk(helper)}
    constructors = [n for n in ast.walk(tree) if isinstance(n, ast.Call)
                    and isinstance(n.func, ast.Name) and n.func.id == "ReviewPhase"]
    assert constructors, "helper must construct ReviewPhase"
    for call in constructors:
        assert id(call) in inside, f"ReviewPhase built outside _review_phase_for at line {call.lineno}"
        assert any(kw.arg == "depth" for kw in call.keywords)
    callers = [n for n in ast.walk(tree) if isinstance(n, ast.Call)
               and isinstance(n.func, ast.Name) and n.func.id == "_review_phase_for"
               and id(n) not in inside]
    assert callers, "runner must call _review_phase_for"
