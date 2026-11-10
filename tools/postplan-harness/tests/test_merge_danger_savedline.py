"""harness/savedline.py: the saved Merge danger line and its human tombstone."""
import json
import os
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import savedline
from harness.statefile import safe_slug

REPO_ROOT = Path(__file__).resolve().parents[3]
LINE = "**Merge danger:** Door: two-way · Blast radius: module · Why: a read-path swap with no data change"


def test_save_then_load_round_trips(tmp_path):
    assert savedline.save_line(str(tmp_path), "t", LINE) is True
    assert savedline.load_line(str(tmp_path), "t") == LINE
    assert savedline.is_human_owned(str(tmp_path), "t") is False


def test_load_missing_returns_none(tmp_path):
    assert savedline.load_line(str(tmp_path), "t") is None
    assert savedline.load_line(str(tmp_path / "absent"), "t") is None


def test_load_corrupt_json_returns_none(tmp_path):
    Path(savedline.path_for(str(tmp_path), "t")).write_text("{not json")
    assert savedline.load_line(str(tmp_path), "t") is None
    assert savedline.is_human_owned(str(tmp_path), "t") is False


def test_load_wrong_owner_returns_none(tmp_path):
    Path(savedline.path_for(str(tmp_path), "t")).write_text(json.dumps({"owner": "bot", "line": "x"}))
    assert savedline.load_line(str(tmp_path), "t") is None
    Path(savedline.path_for(str(tmp_path), "t")).write_text(json.dumps(["owner", "harness"]))
    assert savedline.load_line(str(tmp_path), "t") is None
    Path(savedline.path_for(str(tmp_path), "t")).write_text(json.dumps({"owner": "harness", "line": ""}))
    assert savedline.load_line(str(tmp_path), "t") is None


def test_save_refuses_over_tombstone(tmp_path):
    savedline.save_line(str(tmp_path), "t", LINE)
    savedline.mark_human(str(tmp_path), "t")
    assert savedline.is_human_owned(str(tmp_path), "t") is True
    assert savedline.save_line(str(tmp_path), "t", LINE) is False
    assert savedline.load_line(str(tmp_path), "t") is None
    assert json.loads(Path(savedline.path_for(str(tmp_path), "t")).read_text()) == {"owner": "human"}


def test_path_uses_safe_slug(tmp_path):
    slug = "feat/x y"
    path = savedline.path_for(str(tmp_path), slug)
    assert safe_slug(slug) != slug
    assert path == os.path.join(str(tmp_path), safe_slug(slug) + savedline.SUFFIX)
    assert savedline.save_line(str(tmp_path), slug, LINE)
    assert os.listdir(tmp_path) == [safe_slug(slug) + savedline.SUFFIX]


def test_state_dir_is_gitignored():
    rel = "tools/postplan-harness/out/state/x" + savedline.SUFFIX
    proc = subprocess.run(["git", "check-ignore", "-q", rel], cwd=REPO_ROOT, check=False)
    assert proc.returncode == 0
