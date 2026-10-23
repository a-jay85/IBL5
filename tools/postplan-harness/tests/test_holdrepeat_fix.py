"""Tests for the fix-eligibility, fingerprint and attempt-ledger surface of harness/holdrepeat.py."""
from __future__ import annotations

import json
import os
import sys
from types import SimpleNamespace

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import holdrepeat as hr

SLUG = "feature-branch"
MISSING_A = "MISSING-FILE: bin/foo"
MISSING_B = "MISSING: tests/BarTest.php"
ROW_7 = "UNREALISED-ASSERTION: row 7 -- widget renders"


def _cond(number, reason=""):
    return SimpleNamespace(number=number, name=f"cond-{number}", blocked=True, reason=reason)


def _seed(tmp_path, spec, slug=SLUG):
    """Seed the hold record through the production writer. spec: {number: reason}."""
    hr.observe(str(tmp_path), slug, armed=False,
               conditions=[_cond(n, r) for n, r in spec.items()],
               fingerprint="fp", pr=12, now="2026-10-23T12:00:00Z")


def _fix_eligible(tmp_path, capsys, *extra, slug=SLUG):
    rc = hr.main(["fix-eligible", "--state-dir", str(tmp_path), "--slug", slug, *extra])
    return rc, capsys.readouterr().out


def _fix_record(tmp_path, fp, outcome, slug=SLUG):
    return hr.main(["fix-record", "--state-dir", str(tmp_path), "--slug", slug,
                    "--fingerprint", fp, "--pr", "12", "--outcome", outcome])


def _fp_of(out):
    return out.split("fingerprint=")[1].split()[0]


def test_fix_eligible_condition3_missing_only(tmp_path, capsys):
    _seed(tmp_path, {3: f"{MISSING_A}; {ROW_7}"})
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == hr.FIX_ELIGIBLE_EXIT
    assert out.startswith("holdrepeat: FIX eligible fingerprint=")
    fp = _fp_of(out)
    assert len(fp) == 16 and int(fp, 16) >= 0
    assert "parts=2" in out
    assert f"part: {MISSING_A}" in out and f"part: {ROW_7}" in out


def test_fix_ineligible_other_conditions(tmp_path, capsys):
    _seed(tmp_path, {3: MISSING_A, 14: "review finding"})
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == 0
    assert "FIX ineligible reason=other-conditions" in out


def test_fix_ineligible_without_condition3(tmp_path, capsys):
    _seed(tmp_path, {7: "plan auto_merge: false"})
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == 0
    assert "reason=other-conditions" in out


def test_fix_ineligible_foreign_part(tmp_path, capsys):
    _seed(tmp_path, {3: f"{MISSING_A}; UNREALISED-ASSERTION: matrix-assertion check unavailable (rc 2)"})
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == 0
    assert "FIX ineligible reason=foreign-part" in out


def test_fix_ineligible_no_parts_and_no_record(tmp_path, capsys):
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == 0 and "reason=no-record" in out
    _seed(tmp_path, {3: "something else entirely"})
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == 0 and "reason=no-parts" in out


def test_hold_fingerprint_order_independent_and_shrinks():
    ab = hr.hold_fingerprint(f"{MISSING_A}; {MISSING_B}")
    ba = hr.hold_fingerprint(f"{MISSING_B}; {MISSING_A}")
    assert ab == ba and len(ab) == 16
    # plan-only fix or diff-only fix: the set shrinks, so the fingerprint moves
    assert hr.hold_fingerprint(MISSING_A) != ab
    assert hr.hold_fingerprint(MISSING_B) != hr.hold_fingerprint(MISSING_A)
    assert hr.hold_fingerprint("") == ""
    assert hr.hold_fingerprint("no parts here") == ""


def test_fix_same_fingerprint_gives_up(tmp_path, capsys):
    _seed(tmp_path, {3: MISSING_A})
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == hr.FIX_ELIGIBLE_EXIT
    fp = _fp_of(out)
    assert _fix_record(tmp_path, fp, "started") == 0
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == hr.FIX_GIVEUP_EXIT
    assert f"FIX give-up reason=repeat fingerprint={fp}" in out


def test_fix_per_pr_ceiling_blocks_new_fingerprint(tmp_path, capsys):
    _seed(tmp_path, {3: MISSING_A})
    _, out = _fix_eligible(tmp_path, capsys)
    _fix_record(tmp_path, _fp_of(out), "started")
    _seed(tmp_path, {3: MISSING_B})
    _, out = _fix_eligible(tmp_path, capsys)
    assert _fp_of(out) != ""
    _fix_record(tmp_path, _fp_of(out), "started")
    _seed(tmp_path, {3: ROW_7})
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == hr.FIX_GIVEUP_EXIT
    assert "FIX give-up reason=ceiling" in out


def test_fix_giveup_dm_once_per_fingerprint(tmp_path, capsys):
    _seed(tmp_path, {3: MISSING_A})
    _, out = _fix_eligible(tmp_path, capsys)
    _fix_record(tmp_path, _fp_of(out), "started")
    _, first = _fix_eligible(tmp_path, capsys)
    _, second = _fix_eligible(tmp_path, capsys)
    assert "dm=yes" in first
    assert "dm=no" in second


def test_fix_eligible_no_mark_keeps_dm_available(tmp_path, capsys):
    _seed(tmp_path, {3: MISSING_A})
    _, out = _fix_eligible(tmp_path, capsys)
    _fix_record(tmp_path, _fp_of(out), "started")
    _, peek = _fix_eligible(tmp_path, capsys, "--no-mark")
    assert "dm=no" in peek
    with open(hr.fix_attempts_path(str(tmp_path), SLUG)) as fh:
        assert json.load(fh)["giveup_dm"] == []
    _, real = _fix_eligible(tmp_path, capsys)
    assert "dm=yes" in real


def test_fix_eligible_error_is_ineligible(tmp_path, capsys):
    _seed(tmp_path, {3: MISSING_A})
    path = hr.record_path(str(tmp_path), SLUG)
    with open(path) as fh:
        doc = json.load(fh)
    doc["structural"] = 5  # valid schema, unreadable body: iterating it raises
    with open(path, "w") as fh:
        json.dump(doc, fh)
    rc, out = _fix_eligible(tmp_path, capsys)
    assert rc == 0
    assert "FIX ineligible reason=error" in out


def test_fix_outcome_updates_last_matching_attempt_only(tmp_path, capsys):
    _seed(tmp_path, {3: MISSING_A})
    _, out = _fix_eligible(tmp_path, capsys)
    fp = _fp_of(out)
    _fix_record(tmp_path, fp, "started")
    _fix_record(tmp_path, fp, "fixed-refire-rc=0")
    with open(hr.fix_attempts_path(str(tmp_path), SLUG)) as fh:
        attempts = json.load(fh)["attempts"]
    assert [a["outcome"] for a in attempts] == ["fixed-refire-rc=0"]
    assert attempts[0]["parts"] == [MISSING_A]
    before = open(hr.fix_attempts_path(str(tmp_path), SLUG)).read()
    _fix_record(tmp_path, "0" * 16, "gave-up")
    assert open(hr.fix_attempts_path(str(tmp_path), SLUG)).read() == before


def test_fix_ledger_survives_cleared_hold(tmp_path, capsys):
    _seed(tmp_path, {3: MISSING_A})
    _, out = _fix_eligible(tmp_path, capsys)
    _fix_record(tmp_path, _fp_of(out), "started")
    hr.clear_record(str(tmp_path), SLUG)
    assert os.path.exists(hr.fix_attempts_path(str(tmp_path), SLUG))


def test_fix_recheck_reports_remaining_missing(tmp_path, capsys, monkeypatch):
    _seed(tmp_path, {3: f"{MISSING_A}; {MISSING_B}"})
    monkeypatch.setattr(hr, "_recheck_items",
                        lambda slug, wt, plan: [MISSING_B, "MISSING: unrelated"])
    rc = hr.main(["fix-recheck", "--state-dir", str(tmp_path), "--slug", SLUG,
                  "--worktree", str(tmp_path), "--plan", ""])
    out = capsys.readouterr().out
    assert rc == hr.FIX_RECHECK_FAIL_EXIT
    assert f"part: {MISSING_B}" in out
    assert MISSING_A not in out and "unrelated" not in out


def test_fix_recheck_clean_when_recorded_parts_gone(tmp_path, capsys, monkeypatch):
    _seed(tmp_path, {3: MISSING_A})
    monkeypatch.setattr(hr, "_recheck_items", lambda slug, wt, plan: ["MISSING: unrelated"])
    rc = hr.main(["fix-recheck", "--state-dir", str(tmp_path), "--slug", SLUG,
                  "--worktree", str(tmp_path)])
    assert rc == 0
    assert capsys.readouterr().out == ""


def test_fix_recheck_error_fails_closed(tmp_path, capsys, monkeypatch):
    _seed(tmp_path, {3: MISSING_A})

    def boom(slug, wt, plan):
        raise RuntimeError("git exploded")

    monkeypatch.setattr(hr, "_recheck_items", boom)
    rc = hr.main(["fix-recheck", "--state-dir", str(tmp_path), "--slug", SLUG,
                  "--worktree", str(tmp_path)])
    assert rc == 1
    assert "fix-recheck error (git exploded)" in capsys.readouterr().out
