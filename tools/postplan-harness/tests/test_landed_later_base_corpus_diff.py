"""bench/landed_later_base_corpus_diff.py: stacked-base replay of the planned-landed filter.

Each test writes a tiny corpus under tmp_path (live-<n>/result.json plus audit.log).
"""
import importlib.util
import json
import os
from pathlib import Path

from harness import fidelity

HARNESS = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

_spec = importlib.util.spec_from_file_location(
    "landed_later_base_corpus_diff_t", HARNESS / "bench" / "landed_later_base_corpus_diff.py")
llb = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(llb)

SHA = "a" * 40


def _shadow(missing):
    return {"status": "present", "fields": {
        "schema": "fidelity-shadow/1",
        "planned_landed": {"hold": bool(missing), "missing": missing},
        "merge_danger": {"door": "two-way", "hold": False, "why": "additive change"},
        "body_numbers": {"consistent": True, "mismatches": []}}}


_FILE_A = [{"kind": "file", "token": "a.py", "why": "planned"}]


def _run(corpus, n, missing, audit=None):
    d = corpus / f"live-{n}"
    d.mkdir()
    (d / "result.json").write_text(json.dumps(
        {"pr_number": n, "fidelity": {"shadow": _shadow(missing)}}))
    if audit is not None:
        (d / "audit.log").write_text(audit)


def _main(corpus, capsys):
    rc = llb.main(["--harness-root", str(HARNESS), "--out-dir", str(corpus)])
    return rc, capsys.readouterr().out


def test_stacked_missing_run_counts_as_newly_held(tmp_path, capsys):
    _run(tmp_path, 1, _FILE_A, audit=f"phase5.5: review base: parent {SHA}\n")
    rc, out = _main(tmp_path, capsys)
    assert rc == 0
    assert "newly-held (upper bound): 1" in out
    assert "stacked-base: 1" in out


def test_master_base_and_stacked_without_missing_unchanged(tmp_path, capsys):
    _run(tmp_path, 1, _FILE_A, audit="phase5.5: review base: master\n")
    _run(tmp_path, 2, [], audit=f"phase5.5: review base: parent {SHA}\n")
    rc, out = _main(tmp_path, capsys)
    assert rc == 0
    assert "newly-held (upper bound): 0" in out
    assert "new-clear: 0" in out
    assert "parity-break: 0" in out


def test_base_regex_near_misses_read_as_master(tmp_path, capsys):
    _run(tmp_path, 1, _FILE_A, audit=f"delta-review: review base: parent {SHA}\n")
    _run(tmp_path, 2, _FILE_A, audit="phase5.5: review base: parentless\n")
    _run(tmp_path, 3, _FILE_A)
    rc, out = _main(tmp_path, capsys)
    assert rc == 0
    assert "stacked-base: 0" in out
    assert "newly-held (upper bound): 0" in out


def test_new_clear_exits_nonzero(tmp_path, capsys, monkeypatch):
    _run(tmp_path, 1, _FILE_A, audit=f"phase5.5: review base: parent {SHA}\n")

    def fake(shadow, files, diff, log, *, review_inputs=None):
        return ["planned-landed: a.py"] if review_inputs is None else []

    monkeypatch.setattr(fidelity, "reviewer_arming_holds", fake)
    rc, out = _main(tmp_path, capsys)
    assert rc == 1
    assert "new-clear: 1" in out
