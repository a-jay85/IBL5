"""The rebuildable-table registry stays in step with the Refresh steps and the door rule."""
import os
import shutil
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import rebuildable as rb

REPO_ROOT = Path(__file__).resolve().parents[3]
STEPS_DIR = Path(rb.STEPS_GLOB).parent
DOOR_RULE = REPO_ROOT / ".claude/skills/post-plan/_pr-body-claims.md"
BEGIN, END = "<!-- rebuildable-tables:begin -->", "<!-- rebuildable-tables:end -->"


def _copied_root(tmp_path: Path) -> Path:
    shutil.copytree(REPO_ROOT / STEPS_DIR, tmp_path / STEPS_DIR)
    return tmp_path


def test_registry_matches_refresh_steps():
    assert rb.registry_drift(REPO_ROOT) == []
    assert rb.scan_refresh_steps(REPO_ROOT) == rb.REBUILDABLE_TABLES


def test_drift_flags_added_refresh_step_target(tmp_path):
    root = _copied_root(tmp_path)
    (root / STEPS_DIR / "RefreshFooStep.php").write_text(
        "<?php\n$this->dbExec('DELETE FROM `ibl_foo`');\n$this->dbExec('INSERT INTO `ibl_foo` SELECT 1');\n")
    drift = rb.registry_drift(root)
    assert "unregistered rebuild target ibl_foo in RefreshFooStep" in drift


def test_drift_flags_unclassified_refresh_step(tmp_path):
    root = _copied_root(tmp_path)
    (root / STEPS_DIR / "RefreshBarStep.php").write_text("<?php\nclass RefreshBarStep {}\n")
    assert rb.registry_drift(root) == ["unclassified refresh step RefreshBarStep"]


def test_drift_ignores_delete_without_insert(tmp_path):
    root = _copied_root(tmp_path)
    (root / STEPS_DIR / "RefreshBazStep.php").write_text("<?php\n$this->dbExec('DELETE FROM `ibl_baz`');\n")
    assert "ibl_baz" not in rb.scan_refresh_steps(root)
    assert rb.registry_drift(root) == ["unclassified refresh step RefreshBazStep"]


def test_scan_missing_steps_dir_raises(tmp_path):
    with pytest.raises(FileNotFoundError):
        rb.scan_refresh_steps(tmp_path)


def test_door_rule_lists_registry():
    text = DOOR_RULE.read_text(encoding="utf-8")
    assert text.count(BEGIN) == 1 and text.count(END) == 1
    block = text.split(BEGIN, 1)[1].split(END, 1)[0]
    assert block.strip() == rb.render_rule_block().strip()
    assert block.strip().splitlines()[0].startswith("- `ibl_hist`")
