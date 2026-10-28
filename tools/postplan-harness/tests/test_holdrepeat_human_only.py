"""Human-only hold classifier: `holdrepeat.human_only_names` and the `human-only-eligible` CLI.

Records are seeded through `holdrepeat.observe`, the production writer, so the record shape is
never hand-built.
"""
import json
import os
import subprocess
import sys

import pytest

HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, HARNESS_ROOT)

from harness import holdrepeat

SLUG = "human-only-slug"


def _cond(n, reason="r"):
    return {"number": n, "name": f"cond-{n}", "blocked": True, "reason": reason}


def _seed(state_dir, nums, slug=SLUG, fp="fp"):
    holdrepeat.observe(state_dir, slug, armed=False,
                       conditions=[_cond(n) for n in nums],
                       fingerprint=fp, pr=1, now="t")


def _set_hold_set(state_dir, hold_set):
    rec = holdrepeat.load_record(state_dir, SLUG)
    rec["hold_set"] = hold_set
    holdrepeat._save_record(state_dir, SLUG, rec)


def test_subset_is_eligible(tmp_path):
    d = str(tmp_path)
    _seed(d, [1, 7, 8])
    assert holdrepeat.human_only_names(d, SLUG) == (
        "1 manual-testing-clearance, 7 plan-auto-merge-hold, 8 feat-commit-type-floor")


def test_single_condition_each(tmp_path):
    d = str(tmp_path)
    expected = {
        1: "1 manual-testing-clearance",
        3: "3 unresolved-MISSING-items",
        7: "7 plan-auto-merge-hold",
        8: "8 feat-commit-type-floor",
    }
    for n, text in expected.items():
        # `observe` writes no record for a hold with no structural condition (1 alone),
        # so seed a structural record and overwrite its hold_set.
        _seed(d, [7])
        _set_hold_set(d, [[n, f"cond-{n}", "r"]])
        assert holdrepeat.human_only_names(d, SLUG) == text


def test_foreign_condition_not_eligible(tmp_path):
    d = str(tmp_path)
    _seed(d, [1, 7, 2])
    assert holdrepeat.human_only_names(d, SLUG) is None


def test_empty_hold_set_not_eligible(tmp_path):
    d = str(tmp_path)
    _seed(d, [7])
    _set_hold_set(d, [])
    assert holdrepeat.human_only_names(d, SLUG) is None


@pytest.mark.parametrize("bad", [[7], [[]], [["7"]], [[True]]])
def test_malformed_entries_not_eligible(tmp_path, bad):
    d = str(tmp_path)
    _seed(d, [7])
    _set_hold_set(d, bad)
    assert holdrepeat.human_only_names(d, SLUG) is None


def test_missing_and_unreadable_record(tmp_path):
    d = str(tmp_path)
    assert holdrepeat.human_only_names(d, "no-such-slug") is None
    _seed(d, [7])
    with open(holdrepeat.record_path(d, SLUG), "w") as fh:
        fh.write("{not json")
    assert holdrepeat.human_only_names(d, SLUG) is None
    _seed(d, [7], slug="wrong-schema")
    rec = holdrepeat.load_record(d, "wrong-schema")
    rec["schema_version"] = -1
    holdrepeat._save_record(d, "wrong-schema", rec)
    assert holdrepeat.human_only_names(d, "wrong-schema") is None


def test_fingerprint_match_and_mismatch(tmp_path):
    d = str(tmp_path)
    _seed(d, [1, 7], fp="fp-a")
    names = "1 manual-testing-clearance, 7 plan-auto-merge-hold"
    assert holdrepeat.human_only_names(d, SLUG, current_fp="fp-a") == names
    assert holdrepeat.human_only_names(d, SLUG, current_fp="fp-b") is None
    assert holdrepeat.human_only_names(d, SLUG, current_fp=None) == names


def _run_cli(d, slug):
    env = dict(os.environ, PYTHONPATH=HARNESS_ROOT)
    return subprocess.run(
        [sys.executable, "-m", "harness.holdrepeat", "human-only-eligible",
         "--state-dir", d, "--slug", slug],
        capture_output=True, text=True, env=env, cwd=HARNESS_ROOT, timeout=60)


def test_cli_exit_codes(tmp_path):
    d = str(tmp_path)
    _seed(d, [1, 7], slug="ok")
    _seed(d, [1, 7, 2], slug="bad")
    ok = _run_cli(d, "ok")
    assert ok.returncode == 10
    assert ok.stdout.strip() == "1 manual-testing-clearance, 7 plan-auto-merge-hold"
    bad = _run_cli(d, "bad")
    assert bad.returncode == 0
    assert bad.stdout == ""
    none = _run_cli(d, "none")
    assert none.returncode == 0
    assert none.stdout == ""
    with open(holdrepeat.record_path(d, "broken"), "w") as fh:
        json.dump({"schema_version": -1}, fh)
    broken = _run_cli(d, "broken")
    assert broken.returncode == 0
    assert broken.stdout == ""
