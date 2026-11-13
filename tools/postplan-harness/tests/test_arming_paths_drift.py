"""Drift guard: every harness module the arming code imports is an arming path or a named helper."""
import ast
import os
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.gate_backtest import _ARMING_PATHS

HARNESS = Path(__file__).resolve().parents[1] / "harness"
PREFIX = "tools/postplan-harness/harness/"
ENTRIES = ("armable", "merge_danger")

# Harness modules the arming code imports that decide nothing. Each value says why. A module
# goes here only when it holds data types, enums or constants and no function the arming
# code calls for a verdict. When in doubt, add it to _ARMING_PATHS: that only adds a hold.
_NON_DECISION_HELPERS = {
    "state": "dataclasses and enums (ArmDecision, Classification, ConditionResult, Finding)",
    "manual_rows": "reached only through state.py's TYPE_CHECKING import; type-only",
    "classify": "reached only through planfile.py, which is listed; planfile reads one constant (BACKLOG_REPO)",
    "outofscope": "reached only through planfile.py's local import; extracts deferral text for backlog filing, no hold reads it",
}


def _harness_imports(source):
    out = set()
    for node in ast.walk(ast.parse(source)):
        if isinstance(node, ast.ImportFrom):
            if node.level == 1:
                if node.module:
                    out.add(node.module.split(".")[0])
                else:
                    out.update(a.name.split(".")[0] for a in node.names)
            elif node.level == 0 and node.module:
                parts = node.module.split(".")
                if parts[0] == "harness":
                    if len(parts) > 1:
                        out.add(parts[1])
                    else:
                        out.update(a.name.split(".")[0] for a in node.names)
        elif isinstance(node, ast.Import):
            for a in node.names:
                parts = a.name.split(".")
                if parts[0] == "harness" and len(parts) > 1:
                    out.add(parts[1])
    return out


def _reachable(root, entries=ENTRIES):
    seen, todo = set(), list(entries)
    while todo:
        name = todo.pop()
        if name in seen:
            continue
        f = root / f"{name}.py"
        if not f.is_file():
            continue  # an attribute of harness/__init__.py, not a module file
        seen.add(name)
        todo.extend(_harness_imports(f.read_text(encoding="utf-8")))
    return seen


def _unlisted(root):
    return sorted(m for m in _reachable(root)
                  if f"{PREFIX}{m}.py" not in _ARMING_PATHS and m not in _NON_DECISION_HELPERS)


def test_arming_imports_are_listed_or_allowlisted():
    assert _unlisted(HARNESS) == []


def test_drift_walk_flags_unlisted_fixture_module(tmp_path):
    (tmp_path / "armable.py").write_text("from .rogue_rel import x\nfrom .mid import y\n")
    (tmp_path / "merge_danger.py").write_text("from harness.rogue_abs import z\n")
    (tmp_path / "mid.py").write_text("import harness.deep\n")
    for name in ("rogue_rel", "rogue_abs", "deep"):
        (tmp_path / f"{name}.py").write_text("")
    assert _unlisted(tmp_path) == ["deep", "mid", "rogue_abs", "rogue_rel"]


def test_drift_walk_reads_type_checking_imports(tmp_path):
    (tmp_path / "armable.py").write_text(
        "from typing import TYPE_CHECKING\nif TYPE_CHECKING:\n    from .typed_only import T\n"
    )
    (tmp_path / "typed_only.py").write_text("")
    (tmp_path / "merge_danger.py").write_text("")
    assert _unlisted(tmp_path) == ["typed_only"]


def test_drift_walk_ignores_lookalike_imports(tmp_path):
    (tmp_path / "armable.py").write_text(
        "import harness_extra\n"
        "from harnessx import y\n"
        "from ..outside import z\n"
        "import json\n"
        "from __future__ import annotations\n"
    )
    for name in ("harness_extra", "harnessx", "outside"):
        (tmp_path / f"{name}.py").write_text("")
    (tmp_path / "merge_danger.py").write_text("")
    assert _unlisted(tmp_path) == []


def test_allowlist_and_tuple_do_not_overlap():
    for key in _NON_DECISION_HELPERS:
        assert (HARNESS / f"{key}.py").is_file()
        assert f"{PREFIX}{key}.py" not in _ARMING_PATHS
