"""The owner's reviewer bar: thresholds, the record check, and the bin/reviewer-eval CLI."""
from __future__ import annotations

import json
from pathlib import Path

import pytest

from harness import reviewer_bar as rb
from harness import reviewer_eval as re_

V = "aaaaaaaaaaaa"
C = "bbbbbbbbbbbb"


def _line(hits, scored, cf, ct, deg=0):
    return (f"reviewer-eval v={V} c={C} recall={hits}/{scored} false_alarms={cf} "
            f"clean_flagged={cf}/{ct} degraded={deg}")


def _eval(line, confirmed):
    return rb.evaluate(**rb.counts(re_.parse_score_line(line)), confirmed_total=confirmed)


# --- thresholds ------------------------------------------------------------------------

def test_bar_fails_29_cases_full_recall():
    v = _eval(_line(29, 29, 0, 10), 29)
    assert not v.passed and v.reason.startswith("too-few-cases")


def test_bar_passes_30_cases_60pct_recall_20pct_clean():
    v = _eval(_line(18, 30, 2, 10), 30)
    assert v.passed, v.reason


def test_bar_fails_30_cases_below_60pct():
    v = _eval(_line(17, 30, 0, 10), 30)
    assert not v.passed and v.reason.startswith("recall")


def test_bar_fails_59pct_recall():
    v = _eval(_line(59, 100, 0, 10), 100)
    assert not v.passed and v.reason.startswith("recall")
    assert _eval(_line(60, 100, 0, 10), 100).passed


def test_bar_fails_21pct_clean_flagged():
    v = _eval(_line(30, 30, 21, 100), 30)
    assert not v.passed and v.reason.startswith("clean-flagged")
    assert _eval(_line(30, 30, 20, 100), 30).passed


def test_bar_fails_degraded():
    v = _eval(_line(30, 30, 0, 10, deg=1), 30)
    assert not v.passed and v.reason.startswith("degraded")


def test_bar_fails_no_clean_cases():
    v = _eval(_line(30, 30, 0, 0), 30)
    assert not v.passed and v.reason == "no-clean-cases"


def test_bar_fails_scored_differs_from_confirmed():
    v = _eval(_line(31, 31, 0, 10), 30)
    assert not v.passed and v.reason.startswith("scored-mismatch")
    v = _eval(_line(30, 30, 0, 10), 31)
    assert not v.passed and v.reason.startswith("scored-mismatch")


# --- record check ----------------------------------------------------------------------

def _corpus(n_bug=30, n_clean=10):
    cases = [{"id": f"bug-{i:02d}", "kind": "bug", "status": "confirmed", "pr": i,
              "commit": f"{i:040x}", "fix_pr": 1000 + i, "fix_commit": f"{1000 + i:040x}",
              "expected": [{"path": f"src/f{i}.php", "start": 10, "end": 12}],
              "description": "d"} for i in range(n_bug)]
    clean = [{"id": f"clean-{i:02d}", "kind": "clean", "pr": 500 + i,
              "commit": f"{500 + i:040x}"} for i in range(n_clean)]
    return {"cases": cases, "clean": clean}


def _entry(findings):
    return {"findings": findings, "scored": [], "degraded": []}


def _hit(case):
    e = case["expected"][0]
    return [{"source": "agent-b", "agent": "b", "path": e["path"], "line": e["start"],
             "score": 90}]


def _entries(corpus, n_hits, n_flagged=0):
    cases = {c["id"]: _entry(_hit(c) if i < n_hits else [])
             for i, c in enumerate(corpus["cases"])}
    flag = [{"source": "agent-b", "agent": "b", "path": "x.php", "line": 3, "score": 90}]
    clean = {c["id"]: _entry(flag if i < n_flagged else [])
             for i, c in enumerate(corpus["clean"])}
    return {"cases": cases, "clean": clean}


def _record(corpus, entries, *, v=V, c=None, line=None):
    c = re_.corpus_digest(corpus) if c is None else c
    if line is None:
        line = re_.format_score_line(re_.score_from_entries(corpus, entries, v=v))
    return {"schema": rb.RECORD_SCHEMA, "v": v, "c": c, "line": line,
            "written_at": "2026-11-07T00:00:00+00:00", "entries": entries}


def _write(tmp_path, record):
    p = tmp_path / "reviewer-bar.json"
    p.write_text(json.dumps(record) if not isinstance(record, str) else record)
    return str(p)


def _check(tmp_path, path, corpus, **kw):
    return rb.check(tmp_path, path, surface_hash=lambda r: V,
                    load_corpus=lambda r: corpus, **kw)


def test_check_short_circuits_below_30_confirmed(tmp_path):
    corpus = _corpus(n_bug=29)
    v = _check(tmp_path, str(tmp_path), corpus)   # a directory: reading it would error
    assert not v.passed and v.reason.startswith("too-few-cases")


def test_check_missing_record_fails(tmp_path):
    v = _check(tmp_path, str(tmp_path / "nope.json"), _corpus())
    assert not v.passed and v.reason.startswith("missing")


def test_check_unreadable_record_fails(tmp_path):
    corpus = _corpus()
    v = _check(tmp_path, _write(tmp_path, "{not json"), corpus)
    assert not v.passed and v.reason.startswith("unreadable")
    rec = _record(corpus, _entries(corpus, 30))
    rec["schema"] = 0
    v = _check(tmp_path, _write(tmp_path, rec), corpus)
    assert not v.passed and v.reason.startswith("unreadable")


def test_check_stale_v_fails(tmp_path):
    corpus = _corpus()
    rec = _record(corpus, _entries(corpus, 30), v="cccccccccccc")
    v = _check(tmp_path, _write(tmp_path, rec), corpus)
    assert not v.passed and v.reason.startswith("stale-v")


def test_check_stale_c_fails(tmp_path):
    corpus = _corpus()
    rec = _record(corpus, _entries(corpus, 30), c="dddddddddddd")
    v = _check(tmp_path, _write(tmp_path, rec), corpus)
    assert not v.passed and v.reason.startswith("stale-c")


def test_check_inconsistent_line_fails(tmp_path):
    corpus = _corpus()
    passing = re_.format_score_line(re_.score_from_entries(corpus, _entries(corpus, 30), v=V))
    rec = _record(corpus, _entries(corpus, 5), line=passing)
    v = _check(tmp_path, _write(tmp_path, rec), corpus)
    assert not v.passed and v.reason.startswith("record-inconsistent")


def test_check_rescore_exception_fails_closed(tmp_path):
    corpus = _corpus()
    rec = _record(corpus, _entries(corpus, 30))

    def boom(corp, entries):
        raise RuntimeError("rescore blew up")

    v = _check(tmp_path, _write(tmp_path, rec), corpus, rescore=boom)
    assert not v.passed and v.reason.startswith("error:")


def test_check_valid_record_passes(tmp_path):
    corpus = _corpus()
    rec = _record(corpus, _entries(corpus, 18, n_flagged=2))
    v = _check(tmp_path, _write(tmp_path, rec), corpus)
    assert v.passed, v.reason
    assert v.reason == "pass: recall 18/30, clean flagged 2/10"


# --- CLI -------------------------------------------------------------------------------

class _NoLlm:
    def __init__(self, *a, **kw):
        raise AssertionError("LLM used")


def test_bar_cli_fail_exits_one(tmp_path, capsys, monkeypatch):
    monkeypatch.setattr(re_, "ClaudeCli", _NoLlm)
    monkeypatch.setattr(rb, "check", lambda root, path: rb.Verdict(False, f"missing: {path}"))
    rc = re_.main(["bar", "--record-path", str(tmp_path / "nope.json")])
    assert rc == 1
    assert capsys.readouterr().out.startswith("reviewer-bar: FAIL missing")


def test_bar_cli_fail_exits_one_real_check(tmp_path, capsys, monkeypatch):
    monkeypatch.setattr(re_, "ClaudeCli", _NoLlm)
    monkeypatch.setattr(rb, "MIN_CASES", 1)
    rc = re_.main(["bar", "--record-path", str(tmp_path / "nope.json")])
    assert rc == 1
    assert capsys.readouterr().out.startswith("reviewer-bar: FAIL missing")


def test_bar_cli_pass_exits_zero(tmp_path, capsys, monkeypatch):
    monkeypatch.setattr(re_, "ClaudeCli", _NoLlm)
    monkeypatch.setattr(rb, "check", lambda root, path: rb.Verdict(True, "pass: ok"))
    rc = re_.main(["bar", "--record-path", str(tmp_path / "r.json")])
    assert rc == 0
    assert capsys.readouterr().out.startswith("reviewer-bar: PASS")


def _stub_corpus(monkeypatch, corpus):
    monkeypatch.setattr(re_, "load_corpus", lambda p: corpus)
    monkeypatch.setattr(re_, "validate_schema", lambda c: [])


def test_write_record_below_min_cases_makes_no_llm_call(tmp_path, capsys, monkeypatch):
    monkeypatch.setattr(re_, "ClaudeCli", _NoLlm)
    _stub_corpus(monkeypatch, _corpus(n_bug=9))

    def no_run(*a, **kw):
        raise AssertionError("run_eval called")

    monkeypatch.setattr(re_, "run_eval", no_run)
    dest = tmp_path / "r.json"
    rc = re_.main(["run", "--write-record", "--record-path", str(dest)])
    assert rc == 1
    assert "reviewer-bar: record NOT written (too-few-cases: 9 < 30)" in capsys.readouterr().out
    assert not dest.exists()


def test_write_record_rejects_canned(tmp_path, monkeypatch):
    monkeypatch.setattr(re_, "ClaudeCli", _NoLlm)
    _stub_corpus(monkeypatch, _corpus())
    canned = tmp_path / "canned.json"
    canned.write_text("{}")
    dest = tmp_path / "r.json"
    rc = re_.main(["run", "--write-record", "--canned", str(canned), "--record-path", str(dest)])
    assert rc == 2
    assert not dest.exists()


def test_write_record_skipped_when_degraded(tmp_path, capsys, monkeypatch):
    class _QuietLlm:
        def __init__(self, *a, **kw):
            pass

    monkeypatch.setattr(re_, "ClaudeCli", _QuietLlm)
    _stub_corpus(monkeypatch, _corpus())
    monkeypatch.setattr(re_, "run_eval", lambda *a, **kw: 3)
    dest = tmp_path / "r.json"
    rc = re_.main(["run", "--write-record", "--record-path", str(dest),
                   "--cache-dir", str(tmp_path / "cache")])
    assert rc == 3
    assert "reviewer-bar: record NOT written (degraded)" in capsys.readouterr().out
    assert not dest.exists()


def test_write_record_writes_consistent_record(tmp_path, capsys, monkeypatch):
    class _QuietLlm:
        def __init__(self, *a, **kw):
            pass

    corpus = _corpus()
    monkeypatch.setattr(re_, "ClaudeCli", _QuietLlm)
    _stub_corpus(monkeypatch, corpus)
    monkeypatch.setattr(re_, "surface_hash_worktree", lambda root: V)
    cache = tmp_path / "cache"
    entries = _entries(corpus, 18, n_flagged=2)
    for group, kind in (("cases", "bug"), ("clean", "clean")):
        for case in corpus[group]:
            p = cache / V / f"{case['id']}-{re_.case_digest(case, kind)}.json"
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(json.dumps(entries[group][case["id"]]))
    monkeypatch.setattr(re_, "run_eval", lambda *a, **kw: 0)
    dest = tmp_path / "state" / "r.json"
    rc = re_.main(["run", "--write-record", "--record-path", str(dest),
                   "--cache-dir", str(cache)])
    assert rc == 0
    assert f"reviewer-bar: record written {dest}" in capsys.readouterr().out
    v = rb.check(tmp_path, str(dest), surface_hash=lambda r: V, load_corpus=lambda r: corpus)
    assert v.passed, v.reason


def test_write_record_missing_entry_writes_nothing(tmp_path, capsys, monkeypatch):
    class _QuietLlm:
        def __init__(self, *a, **kw):
            pass

    monkeypatch.setattr(re_, "ClaudeCli", _QuietLlm)
    _stub_corpus(monkeypatch, _corpus())
    monkeypatch.setattr(re_, "surface_hash_worktree", lambda root: V)
    monkeypatch.setattr(re_, "run_eval", lambda *a, **kw: 0)
    dest = tmp_path / "r.json"
    rc = re_.main(["run", "--write-record", "--record-path", str(dest),
                   "--cache-dir", str(tmp_path / "cache")])
    assert rc == 1
    assert "record NOT written (missing cache entry bug-00)" in capsys.readouterr().out
    assert not dest.exists()


def test_bar_rejects_unknown_flag():
    with pytest.raises(SystemExit) as e:
        re_.main(["bar", "--bogus"])
    assert e.value.code == 2


def test_default_record_path_is_harness_state_dir():
    assert Path(rb.default_record_path()).name == "reviewer-bar.json"
    assert Path(rb.default_record_path()).parent.name == "state"
