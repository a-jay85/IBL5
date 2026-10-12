"""Phase 5: body-check reuse keyed on a hash of the exact rendered prompt.

Drives `runner._body_check` directly (same stub git/gh/llm shape as tests/test_body_check.py)
with hand-seeded `cache_rec` dicts, so each key-rule clause has its own miss test.
"""
import copy as copy_mod
import hashlib
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import body_numbers, llm_calls, reviewcache, schemas
from harness.adapters.llm import FixtureLlm
from harness.classify import name_status_text
from harness.state import UsageLedger

VERSION = "v" * 64
BODY = "## Summary\n- tidy the widget wiring\n"
DIFF = ("diff --git a/ibl5/x.php b/ibl5/x.php\n"
        "--- a/ibl5/x.php\n+++ b/ibl5/x.php\n@@ -1 +1 @@\n-<?php echo 0;\n+<?php echo 1;\n")
EXTRA_FILE_DIFF = DIFF + ("diff --git a/ibl5/z.py b/ibl5/z.py\n"
                          "--- a/ibl5/z.py\n+++ b/ibl5/z.py\n@@ -1 +1 @@\n-a\n+b\n")
STORED = {"corrected_body": BODY, "findings": ["stored finding"]}
LIVE = {"corrected_body": BODY, "findings": ["live finding"]}


class _Git:
    def __init__(self, diff):
        self._diff = diff

    def diff_vs_base(self):
        return self._diff


class _Gh:
    def pr_body_fresh(self):
        return ""


def _prompt(body, diff):
    """The prompt `_body_check` renders for this body and diff (mechanical pass included)."""
    ns = name_status_text(diff)
    corrected = body_numbers.correct_body_numbers(body, ns, runner.numstat_text(diff))
    return llm_calls.pr_body_check_prompt(body_numbers.body_prose_for_check(corrected), ns)


def _hash(prompt):
    return hashlib.sha256(prompt.encode()).hexdigest()


def _record(input_hash, *, version=VERSION, result=STORED, run_id="live-prior"):
    return {
        "schema_version": reviewcache.SCHEMA_VERSION, "slug": "s", "pr_number": 9999,
        "run_id": "record-last-writer",
        "key": {"diff_id": "d" * 40, "plan_hash": "p" * 64, "version": version,
                "pr_title": "t", "file_list": ["ibl5/x.php"]},
        "per_file_ids": {"ibl5/x.php": "d" * 40},
        "review": None, "fidelity": None,
        "body_check": {"run_id": run_id, "input_hash": input_hash,
                       "result": copy_mod.deepcopy(result)},
    }


def _call(*, body=BODY, diff=DIFF, cache_rec=None, cache_version=VERSION):
    ledger = UsageLedger()
    llm = FixtureLlm(ledger, {"body-check": copy_mod.deepcopy(LIVE)})
    cache_out: dict = {}
    logs: list = []
    result, degraded = runner._body_check(
        llm, _Git(diff), _Gh(), {"summary_md": body}, False, None, logs.append,
        cache_rec=cache_rec, cache_version=cache_version, cache_out=cache_out)
    return result, degraded, [c.purpose for c in ledger.calls], cache_out, logs


def _hit_rec(**kw):
    return _record(_hash(_prompt(BODY, DIFF)), **kw)


def test_body_check_hit_skips_llm_and_keeps_mechanical_passes(monkeypatch):
    rec = _hit_rec()   # built before the spy: the test's own _prompt() also calls the corrector
    calls = []
    real = body_numbers.correct_body_numbers

    def spy(*a, **k):
        calls.append(a)
        return real(*a, **k)

    monkeypatch.setattr(body_numbers, "correct_body_numbers", spy)
    result, degraded, purposes, cache_out, logs = _call(cache_rec=rec)
    assert "body-check" not in purposes
    assert cache_out["reused_from"] == "live-prior"
    assert len(calls) == 1
    assert result["findings"] == STORED["findings"]
    assert degraded is False
    assert any("body-check reused from live-prior" in m for m in logs)
    # The raw reused result is what Phase 7 re-writes.
    assert cache_out["result"]["findings"] == STORED["findings"]


def test_body_check_hit_runs_test_count_pass(monkeypatch):
    """The test-count annotation runs after a hit, as it does after a live call."""
    seen = []
    real = body_numbers.annotate_test_count_mismatches

    def spy(*a, **k):
        seen.append(a[0])
        return real(*a, **k)

    monkeypatch.setattr(body_numbers, "annotate_test_count_mismatches", spy)
    _, _, purposes, _, _ = _call(cache_rec=_hit_rec())
    assert "body-check" not in purposes
    assert seen == [STORED["corrected_body"]]


def test_body_check_hit_not_degraded():
    result, degraded, purposes, _, _ = _call(cache_rec=_hit_rec())
    assert degraded is False
    assert "body-check" not in purposes
    assert result["findings"] == STORED["findings"]


def test_body_check_miss_on_one_char_body_change():
    rec = _hit_rec()
    _, _, purposes, cache_out, _ = _call(body=BODY.rstrip("\n") + ".\n", cache_rec=rec)
    assert purposes == ["body-check"]
    assert "reused_from" not in cache_out
    assert cache_out["input_hash"] != rec["body_check"]["input_hash"]


def test_body_check_miss_on_name_status_change():
    rec = _hit_rec()
    result, _, purposes, _, _ = _call(diff=EXTRA_FILE_DIFF, cache_rec=rec)
    assert purposes == ["body-check"]
    assert result["findings"] == LIVE["findings"]
    assert "M\tibl5/z.py" in name_status_text(EXTRA_FILE_DIFF)


def test_body_check_miss_on_version_change():
    _, _, purposes, cache_out, _ = _call(cache_rec=_hit_rec(), cache_version="w" * 64)
    assert purposes == ["body-check"]
    assert "reused_from" not in cache_out


def test_body_check_miss_on_empty_version():
    _, _, purposes, cache_out, _ = _call(cache_rec=_hit_rec(version=""), cache_version="")
    assert purposes == ["body-check"]
    assert "reused_from" not in cache_out


def test_body_check_miss_when_stored_result_fails_validator():
    result, degraded, purposes, _, _ = _call(
        cache_rec=_hit_rec(result={"findings": "nope"}))
    assert purposes == ["body-check"]
    assert degraded is False
    assert result["findings"] == LIVE["findings"]


@pytest.mark.parametrize("make_rec", [
    lambda: None,
    lambda: dict(_hit_rec(), body_check=None),
], ids=["record-none", "arm-null"])
def test_body_check_miss_when_arm_null_or_record_none(make_rec):
    result, _, purposes, cache_out, _ = _call(cache_rec=make_rec())
    assert purposes == ["body-check"]
    assert result["findings"] == LIVE["findings"]
    assert "reused_from" not in cache_out
    assert cache_out["input_hash"] == _hash(_prompt(BODY, DIFF))


def test_body_check_prompt_hash_is_exact_prompt():
    prompt = _prompt(BODY, DIFF)
    rec = _record(_hash(prompt))
    validator = schemas.validate_body_check
    hit = reviewcache.restore_body_check(rec, VERSION, _hash(prompt), validator)
    assert hit is not None
    result, run_id = hit
    assert run_id == "live-prior"
    assert result["findings"] == STORED["findings"]
    assert reviewcache.restore_body_check(rec, VERSION, _hash(prompt + "\n"), validator) is None
    # The runner hashes this same string.
    _, _, _, cache_out, _ = _call(cache_rec=None)
    assert cache_out["input_hash"] == _hash(prompt)
