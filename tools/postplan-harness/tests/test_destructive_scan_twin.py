"""Pin the CI destructive-migration twin: it keeps its own tokenizer and its comment cases.

`bin/check-destructive-migrations` delegates to `bin/lib/destructive-migration-scan.py`, whose
`Splitter` understands strings and comments on its own. Merge-danger trigger 1 blanks comments
with `harness/sql_comments.py`. The two are independent; these tests fail if the twin drops a
comment case or starts sharing the harness regex.
"""
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "bin" / "check-destructive-migrations"
ENGINE = ROOT / "bin" / "lib" / "destructive-migration-scan.py"
COMMENT_ROWS = ("block comment", "hash comment", "dash in block", "string literal", "php comment")

pytestmark = pytest.mark.skipif(not SCRIPT.is_file(), reason="repo bin/ not checked out")


def test_twin_self_test_passes():
    r = subprocess.run([str(SCRIPT), "--self-test"], cwd=ROOT, capture_output=True, text=True,
                       timeout=120)
    tail = "\n".join((r.stdout + r.stderr).splitlines()[-20:])
    assert r.returncode == 0, tail


def test_twin_self_test_keeps_its_comment_rows():
    text = ENGINE.read_text(encoding="utf-8")
    for label in COMMENT_ROWS:
        assert f'("{label}",' in text, label


def test_twin_does_not_share_the_harness_regex():
    assert "DESTRUCTIVE_SQL" not in ENGINE.read_text(encoding="utf-8")
