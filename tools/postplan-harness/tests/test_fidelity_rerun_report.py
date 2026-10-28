"""The Phase 5.5 rerun report (scripts/fidelity_rerun_report.py).

Every fixture tree is built under pytest's tmp_path and the script runs as a child
process, the way an operator runs it, with TZ=UTC so the local-time dir stamps parse the
same everywhere.
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
from pathlib import Path

import pytest

SCRIPT = Path(__file__).resolve().parent.parent / "scripts" / "fidelity_rerun_report.py"
FAR_END = "2099-01-01T00:00:00Z"
POST_WINDOW = f"2026-10-27T18:33:33Z..{FAR_END}"
H40 = "a" * 40
O40 = "b" * 40

DECLINE_OLD = "[10:00:00] phase5.5 fidelity: review: full (carry-forward declined: {r})"
DECLINE_NEW = DECLINE_OLD + " diff-id=" + H40 + " own-id=" + O40 + " own-arm={arm}"
CARRIED = "[10:00:00] phase5.5 fidelity: review: carried forward (patch-id aaaaaaaaaaaa)"
CARRIED_OWN = CARRIED + " (own-change match, reviewed bbbbbbbbbbbb)"


def make_run(root: Path, name: str, lines: list[str], pr="__none__", **extra) -> Path:
    d = root / name
    d.mkdir()
    (d / "audit.log").write_text("\n".join(lines) + "\n")
    result = {"terminal": "shipped-armed", **extra}
    if pr != "__none__":
        result["pr_number"] = pr
    (d / "result.json").write_text(json.dumps(result))
    return d


def run_report(root: Path, *flags: str):
    env = dict(os.environ, TZ="UTC")
    return subprocess.run([sys.executable, "-I", str(SCRIPT), "--out-dir", str(root), *flags],
                          capture_output=True, text=True, env=env, timeout=60)


def report(root: Path, *flags: str) -> dict:
    p = run_report(root, "--json", *flags)
    assert p.returncode == 0, p.stderr
    return json.loads(p.stdout)


def test_report_on_fixture_tree(tmp_path):
    make_run(tmp_path, "live-a-20261015-100000-1",
             [DECLINE_OLD.format(r="no-prior-sticky")], pr=101)
    make_run(tmp_path, "live-a-20261016-100000-2",
             [DECLINE_OLD.format(r="diff-changed")], pr=101)
    make_run(tmp_path, "live-a-20261017-100000-3", [CARRIED], pr=101)
    make_run(tmp_path, "live-b-20261018-100000-4",
             [DECLINE_OLD.format(r="no-prior-sticky")], pr=102)
    make_run(tmp_path, "live-a-20261028-100000-5",
             [DECLINE_NEW.format(r="diff-changed", arm="own-change-changed")],
             pr=101, fired_by="pr-cycle-tick")
    make_run(tmp_path, "live-a-20261028-110000-6", [CARRIED_OWN], pr=101, fired_by="automouse")

    out = report(tmp_path, "--post-window", POST_WINDOW)
    pre, post = out["pre"], out["post"]
    assert pre["runs"] == 4
    assert pre["prs"] == 2
    assert pre["full_reviews"] == 3
    assert pre["reviewer_calls"] == 3
    assert pre["full_per_pr"] == 1.5
    assert pre["histogram"] == {"1": 1, "3": 1}
    assert pre["own_arm"] == {"not-logged": 1}
    assert pre["kinds"]["full/no-prior-sticky"] == 2
    assert pre["kinds"]["carried"] == 1
    assert pre["fired_by"] == {"unknown": 4}
    assert post["runs"] == 2
    assert post["prs"] == 1
    assert post["kinds"]["carried-own-change"] == 1
    assert post["kinds"]["full/diff-changed"] == 1
    assert post["own_arm"] == {"own-change-changed": 1}
    assert post["fired_by"] == {"pr-cycle-tick": 1, "automouse": 1}
    assert post["histogram"] == {"2": 1}
    assert out["skipped"] == {}


def test_report_reads_fired_by_from_audit_line_and_delta_kind(tmp_path):
    make_run(tmp_path, "live-a-20261028-100000-1",
             ["[09:59:59] postplan: fired-by=post-plan-fleet",
              DECLINE_OLD.format(r="diff-changed"),
              "[10:05:00] phase5.5 fidelity: verdict=READY reviewed_tree=abcdef123456 review_kind=delta"],
             pr=7)
    post = report(tmp_path, "--post-window", POST_WINDOW)["post"]
    assert post["fired_by"] == {"post-plan-fleet": 1}
    assert post["kinds"] == {"delta/diff-changed": 1}
    assert post["reviewer_calls"] == 1
    assert post["full_reviews"] == 0
    assert post["own_arm"] == {}


def test_report_skips_unreadable_runs(tmp_path):
    good = [DECLINE_OLD.format(r="no-prior-sticky")]
    make_run(tmp_path, "live-ok-20261015-100000-1", good, pr=1)
    nores = make_run(tmp_path, "live-nores-20261015-100001-2", good, pr=2)
    (nores / "result.json").unlink()
    broken = make_run(tmp_path, "live-broken-20261015-100002-3", good, pr=3)
    (broken / "result.json").write_text("{")
    make_run(tmp_path, "live-nullpr-20261015-100003-4", good, pr=None)
    make_run(tmp_path, "live-strpr-20261015-100004-5", good, pr="12")
    make_run(tmp_path, "live-nostamp", good, pr=9)

    p = run_report(tmp_path, "--json")
    assert p.returncode == 0, p.stderr
    out = json.loads(p.stdout)
    assert out["pre"]["runs"] == 1
    assert out["pre"]["prs"] == 1
    assert out["skipped"] == {"no-result-json": 1, "unreadable-result-json": 1,
                              "no-integer-pr-number": 2, "unstamped-dir-name": 1}
    text = run_report(tmp_path)
    assert text.returncode == 0
    assert "unstamped-dir-name: 1" in text.stdout


def test_report_ignores_near_miss_lines(tmp_path):
    make_run(tmp_path, "live-a-20261015-100000-1",
             ["[10:00:00] phase5 fidelity: review: full (carry-forward declined: diff-changed)",
              "phase5.5 fidelity: review: full (carry-forward declined: diff-changed)",
              "[10:00:00] phase5.5 fidelity: review: full (carry-forward declined: diff-changed)"
              " own-id=zzz",
              "[10:00:00] phase5.5 fidelity: verdict=READY review_kind=deltaX",
              "[10:00:00] phase5.5 fidelity: review: carried forward (patch-id zzz)"],
             pr=5)
    pre = report(tmp_path)["pre"]
    assert pre["runs"] == 1
    assert pre["kinds"] == {"no-fidelity-line": 1}
    assert not [k for k in pre["kinds"] if k.startswith("full")]
    assert pre["full_reviews"] == 0
    assert pre["own_arm"] == {}


def test_report_window_boundary(tmp_path):
    line = [DECLINE_OLD.format(r="no-prior-sticky")]
    make_run(tmp_path, "live-a-20261027-183333-1", line, pr=1)
    make_run(tmp_path, "live-b-20261027-183332-2", line, pr=2)
    make_run(tmp_path, "live-c-20261012-235959-3", line, pr=3)   # before the pre window
    out = report(tmp_path)
    assert (out["pre"]["runs"], out["pre"]["prs"]) == (1, 1)
    assert (out["post"]["runs"], out["post"]["prs"]) == (1, 1)


@pytest.mark.parametrize("flags", [
    ["--bogus"],
    ["--pre-window", "2026-10-13"],
    ["--pre-window", "2026-10-27T00:00:00Z..2026-10-13T00:00:00Z"],
    ["--pre-window", "2026-10-13T00:00:00Z..2026-10-20T00:00:00Z",
     "--post-window", "2026-10-15T00:00:00Z.."],
    ["--post-window", "yesterday.."],
], ids=["unknown-flag", "no-dotdot", "reversed", "overlap", "malformed-bound"])
def test_report_rejects_bad_flags(tmp_path, flags):
    p = run_report(tmp_path, *flags)
    assert p.returncode == 2
    assert p.stderr.strip()
    assert p.stdout == ""


def test_report_empty_out_dir(tmp_path):
    out = report(tmp_path)
    for name in ("pre", "post"):
        assert out[name]["runs"] == 0
        assert out[name]["prs"] == 0
        assert out[name]["full_per_pr"] == 0.0
        assert out[name]["histogram"] == {}
    assert out["skipped"] == {}
    proc = run_report(tmp_path)
    assert proc.returncode == 0
    # Without the zero-PR guard the division raises ZeroDivisionError and the script exits 1.
    assert "ZeroDivisionError" not in proc.stderr
