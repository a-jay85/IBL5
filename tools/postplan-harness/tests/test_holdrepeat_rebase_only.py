"""Rebase-only eligibility: `holdrepeat.rebase_only_eligible`, its CLI, and `refresh_fingerprint`.

Records are seeded through `holdrepeat.observe`, the production writer, so the record shape is
never hand-built.
"""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import holdrepeat

SLUG = "rebase-only-slug"


def _cond(n, reason="r"):
    return {"number": n, "name": f"cond-{n}", "blocked": True, "reason": reason}


def _seed(state_dir, nums, slug=SLUG):
    holdrepeat.observe(state_dir, slug, armed=False,
                       conditions=[_cond(n) for n in nums],
                       fingerprint="fp", pr=1, now="t")


def test_eligible_for_7_and_8(tmp_path):
    d = str(tmp_path)
    _seed(d, [7, 8])
    assert holdrepeat.rebase_only_eligible(d, SLUG) is True


def test_eligible_for_condition_3_only(tmp_path):
    d = str(tmp_path)
    holdrepeat.observe(d, SLUG, armed=False,
                       conditions=[_cond(3, "MISSING-FILE: ibl5/classes/Foo.php")],
                       fingerprint="fp", pr=1, now="t")
    assert holdrepeat.rebase_only_eligible(d, SLUG) is True


def test_not_eligible_when_machine_fixable_condition_present(tmp_path):
    d = str(tmp_path)
    _seed(d, [7, 15], slug="a")
    _seed(d, [3, 12], slug="b")
    assert holdrepeat.rebase_only_eligible(d, "a") is False
    assert holdrepeat.rebase_only_eligible(d, "b") is False


def test_not_eligible_for_condition_13(tmp_path):
    d = str(tmp_path)
    _seed(d, [13])
    assert holdrepeat.rebase_only_eligible(d, SLUG) is False


def test_not_eligible_without_record(tmp_path):
    d = str(tmp_path)
    assert holdrepeat.rebase_only_eligible(d, "no-such-slug") is False
    _seed(d, [7])
    rec = holdrepeat.load_record(d, SLUG)
    rec["schema_version"] = -1
    holdrepeat._save_record(d, SLUG, rec)
    assert holdrepeat.rebase_only_eligible(d, SLUG) is False


def test_not_eligible_for_empty_hold_set(tmp_path):
    d = str(tmp_path)
    _seed(d, [7])
    rec = holdrepeat.load_record(d, SLUG)
    rec["hold_set"] = []
    holdrepeat._save_record(d, SLUG, rec)
    assert holdrepeat.rebase_only_eligible(d, SLUG) is False


def test_cli_exit_codes(tmp_path):
    d = str(tmp_path)
    _seed(d, [7, 8], slug="ok")
    _seed(d, [7, 15], slug="bad")
    argv = ["rebase-eligible", "--state-dir", d, "--slug"]
    assert holdrepeat.main(argv + ["ok"]) == 10
    assert holdrepeat.main(argv + ["bad"]) == 0
    assert holdrepeat.main(argv + ["none"]) == 0


def test_cli_fails_open_to_not_eligible(tmp_path, monkeypatch):
    def boom(*_a, **_k):
        raise RuntimeError("boom")
    monkeypatch.setattr(holdrepeat, "rebase_only_eligible", boom)
    assert holdrepeat.main(["rebase-eligible", "--state-dir", str(tmp_path),
                            "--slug", SLUG]) == 0


def test_cli_rejects_missing_slug(tmp_path):
    with pytest.raises(SystemExit) as exc:
        holdrepeat.main(["rebase-eligible", "--state-dir", str(tmp_path)])
    assert exc.value.code == 2


# --- Phase 4: refresh_fingerprint ------------------------------------------------------

def test_refresh_fingerprint_updates_only_fingerprint(tmp_path):
    d = str(tmp_path)
    conds = [_cond(7), _cond(8)]
    holdrepeat.observe(d, SLUG, armed=False, conditions=conds, fingerprint="fp", pr=1, now="t")
    holdrepeat.mark_dm_sent(d, SLUG, holdrepeat.structural_key(conds))
    before = holdrepeat.load_record(d, SLUG)
    assert holdrepeat.refresh_fingerprint(d, SLUG, "new", "t2") is True
    after = holdrepeat.load_record(d, SLUG)
    assert after["fingerprint"] == "new"
    assert after["updated_at"] == "t2"
    for key in ("hold_set", "structural", "structural_key", "repeat_count", "dm_sent_key"):
        assert after[key] == before[key], key


def test_refresh_fingerprint_unknown_slug_writes_nothing(tmp_path):
    d = str(tmp_path)
    assert holdrepeat.refresh_fingerprint(d, "no-such-slug", "new", "t2") is False
    assert os.listdir(d) == []


def test_refresh_fingerprint_empty_fp_is_noop(tmp_path):
    d = str(tmp_path)
    _seed(d, [7])
    path = holdrepeat.record_path(d, SLUG)
    before = open(path, "rb").read()
    assert holdrepeat.refresh_fingerprint(d, SLUG, "", "t2") is False
    assert open(path, "rb").read() == before
