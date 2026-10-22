"""The shell twin bin/lib/review-depth.sh must print exactly what classify_depth returns."""
from __future__ import annotations

import json
import subprocess
from pathlib import Path

import pytest

from harness.review_depth import classify_depth

HERE = Path(__file__).resolve().parent
SCRIPT = HERE.parent.parent.parent / "bin" / "lib" / "review-depth.sh"
CASES = json.loads((HERE / "fixtures" / "review_depth_cases.json").read_text(encoding="utf-8"))
BY_ID = {row["id"]: row for row in CASES}


def _run(args: list[str], body: str, env: dict[str, str]) -> subprocess.CompletedProcess:
    return subprocess.run(
        ["/bin/bash", str(SCRIPT), *args],
        input=body.encode("utf-8"),
        capture_output=True,
        env=env,
    )


@pytest.mark.parametrize("row", CASES, ids=[r["id"] for r in CASES])
def test_shell_matches_python(row):
    """The shell prints exactly `<depth> <reason>` from classify_depth for every case row.
    Changing the line-count test to `-ge 1` in review-depth.sh turns [two-lines] red."""
    depth, reason = classify_depth(row["body"])
    if row["body"] is None:
        proc = _run(["--unreadable"], "", {"PATH": "/usr/bin:/bin"})
    else:
        proc = _run([], row["body"], {"PATH": "/usr/bin:/bin"})
    assert proc.returncode == 0
    assert proc.stdout.decode() == f"{depth} {reason}\n"
    assert depth == row["depth"]


def test_runs_under_empty_env():
    proc = _run([], BY_ID["two-way-localized"]["body"], {})
    assert proc.stdout.decode() == "standard two-way/localized\n"
    proc = _run([], BY_ID["missing-line"]["body"], {})
    assert proc.stdout.decode() == "deep MISSING\n"


@pytest.mark.parametrize("args", [["--bogus"], ["--unreadable", "x"]])
def test_usage_errors_print_no_verdict(args):
    proc = _run(args, "", {"PATH": "/usr/bin:/bin"})
    assert proc.returncode == 2
    assert proc.stdout == b""
