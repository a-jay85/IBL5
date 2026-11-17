"""Column 4 of ``list_open_review_findings`` skips marker and blank lines.

Since #3352 every posted body opens with ``<!-- label: X -->`` and
``<!-- score: N -->``. Column 4 must show the first prose line, not a marker.
The tests source the shell lib with ``prf_review_threads`` stubbed to cat a
JSONL file.
"""

from __future__ import annotations

import json
import subprocess
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[3]
LIB = REPO_ROOT / "bin" / "lib" / "post-review-findings.sh"
CORPUS = Path(__file__).resolve().parent / "fixtures" / "pr3458_open_threads.jsonl"

_STUB = (
    'source "$1"; f="$2"; prf_review_threads() { cat "$f"; }; '
    "list_open_review_findings 3458"
)


def _run_lib(jsonl: Path, lib: Path = LIB) -> list[list[str]]:
    proc = subprocess.run(
        ["bash", "-c", _STUB, "_", str(lib), str(jsonl)],
        cwd=REPO_ROOT,
        capture_output=True,
        text=True,
        check=True,
    )
    return [line.split("\t") for line in proc.stdout.splitlines() if line]


def _column4(tmp_path: Path, body: str) -> str:
    base = json.loads(CORPUS.read_text().splitlines()[0])
    base["body"] = body
    jsonl = tmp_path / "rows.jsonl"
    jsonl.write_text(json.dumps(base) + "\n")
    rows = _run_lib(jsonl)
    assert len(rows) == 1
    row = rows[0]
    return row[3] if len(row) > 3 else ""


def test_marker_prefixed_body_yields_first_prose_line(tmp_path: Path) -> None:
    body = "<!-- label: nit -->\n<!-- score: 60 -->\nReal first line\nsecond"
    assert _column4(tmp_path, body) == "Real first line"
    with_blank = "<!-- label: nit -->\n<!-- score: 60 -->\n\nReal first line\nsecond"
    assert _column4(tmp_path, with_blank) == "Real first line"


def test_near_miss_inline_marker_line_is_kept(tmp_path: Path) -> None:
    body = "<!-- label: nit --> trailing text\nnext"
    assert _column4(tmp_path, body) == "<!-- label: nit --> trailing text"


def test_all_marker_body_yields_empty_column(tmp_path: Path) -> None:
    body = "<!-- label: nit -->\n<!-- score: 60 -->"
    assert _column4(tmp_path, body) == ""


def test_first_line_truncated_to_100_chars(tmp_path: Path) -> None:
    line = "x" * 101
    body = f"<!-- label: nit -->\n<!-- score: 60 -->\n{line}"
    assert _column4(tmp_path, body) == "x" * 100


def test_unmarked_corpus_runs_nonempty() -> None:
    assert len(_run_lib(CORPUS)) >= 1
