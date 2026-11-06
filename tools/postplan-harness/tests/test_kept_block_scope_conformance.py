"""The kept block declares nothing to bin/lib/plan-scope-conformance, so Phase 5.0 still sees it.

Every test runs the real script.
"""
from __future__ import annotations

import os
import subprocess

import pytest

from harness import unplanned_scope as us

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
SCRIPT = os.path.join(ROOT, "bin", "lib", "plan-scope-conformance")
PLANNED = "ibl5/planned.php"
KEPT = "ibl5/kept.css"
DECLARED = "docs/other.md"


def _run(tmp_path, body: str) -> str:
    plan = tmp_path / "plan.md"
    plan.write_text(f"# Plan\n\n## Critical Files\n\n- `{PLANNED}` (modified)\n", encoding="utf-8")
    changed = tmp_path / "changed.txt"
    changed.write_text(f"{PLANNED}\n{KEPT}\n{DECLARED}\n", encoding="utf-8")
    body_file = tmp_path / "body.md"
    body_file.write_text(body, encoding="utf-8")
    proc = subprocess.run(["bash", SCRIPT, str(plan), str(changed), str(body_file)],
                          capture_output=True, text=True, timeout=60)
    assert proc.returncode == 0, proc.stderr
    return proc.stdout


def _kept_block(path: str) -> str:
    item = {"hold": "12", "text": f"- [BLOCKING] [unplanned-scope] Remove the grid in {path}."}
    block = us.render_kept_block([item], {"changed_paths": [path]})
    assert block.startswith(us.KEPT_BEGIN)
    return block


def _lines_for(out: str, path: str) -> list[str]:
    return [line for line in out.splitlines() if path in line]


@pytest.mark.parametrize("wrapper", ["{block}\n", "intro\n\n{block}\n\ntail\n"])
def test_kept_paths_stay_unplanned_for_phase50(tmp_path, wrapper):
    declared = f"## Declared scope\n- `{DECLARED}`\n\n"
    without = _run(tmp_path, declared)
    with_block = _run(tmp_path, declared + wrapper.format(block=_kept_block(KEPT)))
    assert _lines_for(without, KEPT), without
    assert _lines_for(with_block, KEPT) == _lines_for(without, KEPT)
    # With the markers lost, the block's own `## ` heading must still end `## Declared scope`.
    bare = "\n".join(line for line in _kept_block(KEPT).splitlines()
                     if line not in (us.KEPT_BEGIN, us.KEPT_END))
    assert _lines_for(_run(tmp_path, declared + wrapper.format(block=bare)), KEPT) == _lines_for(without, KEPT)


def test_declared_scope_path_still_declared(tmp_path):
    body = f"## Declared scope\n- `{KEPT}`\n- `{DECLARED}`\n\n{_kept_block(KEPT)}\n"
    assert _lines_for(_run(tmp_path, body), KEPT) == []
