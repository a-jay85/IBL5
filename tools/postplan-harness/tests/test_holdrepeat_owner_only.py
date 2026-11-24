#!/usr/bin/env python3
"""Owner-only hold kinds in holdrepeat: 18 with SELF-GOVERNING, and 12 with kept scope only.

Records are seeded through `holdrepeat.observe`, the production writer.
"""
import json
import os
import subprocess
import sys

import pytest

HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, HARNESS_ROOT)

from harness import holdrepeat as hr
from harness import unplanned_scope

SLUG = "owner-only-slug"
SG = "state=SELF-GOVERNING"
SCOPE_A = "[unplanned-scope] Adds bin/foo helper. Next action: keep."
SCOPE_B = "[GATE-EDIT] [unplanned-scope] Edits the gate. Next action: keep."
BODY_CLAIM = "[MISSING] The PR body claims a test that is absent. Next action: add it."


def _title(item):
    return unplanned_scope.title_of("- [BLOCKING] " + item)


def _cond(n, reason="r"):
    return {"number": n, "name": f"cond-{n}", "blocked": True, "reason": reason}


def _fid(blocking, kept=None, owner=None, verdict="NOT READY"):
    return {
        "review_items": [{"verdict": verdict, "blocking": list(blocking)}],
        "kept_unplanned": [_title(b) for b in blocking] if kept is None else kept,
        "kept_owner_scope": [{"title": t} for t in (owner or [])],
    }


def _seed(d, conds, fid=None, fp="fp", slug=SLUG):
    return hr.observe(d, slug, armed=False, conditions=conds, fingerprint=fp,
                      pr=1, now="t", fidelity=fid)


def _check_argv(d, slug=SLUG, plan="p.md", worktree="wt"):
    return ["check", "--state-dir", d, "--slug", slug, "--plan", plan, "--worktree", worktree]


def test_kind_a_alone_declines_same_fingerprint(tmp_path, monkeypatch, capsys):
    d = str(tmp_path)
    _seed(d, [_cond(18, SG)])
    assert hr.load_record(d, SLUG)["hold_kinds"] == [hr.KIND_SELF_GOVERNING]
    assert "owner approval" in hr.owner_only_decline(d, SLUG, "fp")
    monkeypatch.setattr(hr, "fingerprint", lambda *a, **k: "fp")
    assert hr.main(_check_argv(d)) == 10
    assert "owner approval" in capsys.readouterr().out


def test_kind_b_alone_declines_same_fingerprint(tmp_path):
    d = str(tmp_path)
    _seed(d, [_cond(12)], _fid([SCOPE_A]))
    assert hr.load_record(d, SLUG)["hold_kinds"] == [hr.KIND_KEPT_SCOPE]
    assert hr.owner_only_decline(d, SLUG, "fp") == "owner approval"


def test_kind_a_and_b_declines(tmp_path):
    d = str(tmp_path)
    _seed(d, [_cond(12), _cond(18, SG)], _fid([SCOPE_A]))
    assert hr.load_record(d, SLUG)["hold_kinds"] == sorted([hr.KIND_SELF_GOVERNING, hr.KIND_KEPT_SCOPE])
    assert hr.owner_only_decline(d, SLUG, "fp") == "owner approval"


def test_kind_b_with_condition_7_declines(tmp_path):
    d = str(tmp_path)
    _seed(d, [_cond(7, "plan declares auto_merge: false"), _cond(12)], _fid([SCOPE_A]))
    assert hr.owner_only_decline(d, SLUG, "fp") == "7 plan-auto-merge-hold, owner approval"
    assert hr.load_record(d, SLUG)["hold_kinds"] == [hr.KIND_KEPT_SCOPE]


def test_kind_b_with_non_scope_blocker_not_declined(tmp_path):
    d = str(tmp_path)
    _seed(d, [_cond(12)], _fid([SCOPE_A, BODY_CLAIM]))
    assert hr.load_record(d, SLUG) is None
    assert hr.owner_only_decline(d, SLUG, "fp") is None


def test_plain_condition_12_not_declined(tmp_path):
    d = str(tmp_path)
    _seed(d, [_cond(12)], _fid([BODY_CLAIM], kept=[]))
    assert hr.owner_only_decline(d, SLUG, "fp") is None
    assert hr.human_only_names(d, SLUG) is None
    _seed(d, [_cond(12)])
    assert hr.human_only_names(d, SLUG) is None


def test_owner_quote_accepted_scope_not_kind_b():
    assert hr.owner_only_kinds([_cond(12)], _fid([SCOPE_A], owner=[_title(SCOPE_A)])) == []


def test_kind_b_unkept_scope_not_declined():
    assert hr.owner_only_kinds([_cond(12)], _fid([SCOPE_A], kept=[])) == []


def test_changed_fingerprint_not_declined(tmp_path, monkeypatch):
    d = str(tmp_path)
    _seed(d, [_cond(18, SG)], fp="fp-old")
    assert hr.owner_only_decline(d, SLUG, "fp-new") is None
    assert hr.owner_only_decline(d, SLUG, "") is None
    monkeypatch.setattr(hr, "fingerprint", lambda *a, **k: "fp-new")
    assert hr.main(_check_argv(d)) == 0


def _git(cwd, *args):
    subprocess.run(["git", "-C", str(cwd), *args], check=True, capture_output=True,
                   env={**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"})


def test_body_only_change_same_fingerprint_declines(tmp_path, capsys):
    wt = tmp_path / "wt"
    wt.mkdir()
    _git(wt, "init", "-q")
    (wt / "a.txt").write_text("one\n")
    _git(wt, "add", "-A")
    _git(wt, "commit", "-q", "-m", "init")
    _git(wt, "update-ref", "refs/remotes/origin/master", "HEAD")
    plan = tmp_path / "plan.md"
    plan.write_text("plan\n")
    d = str(tmp_path / "state")
    fp = hr.fingerprint(str(plan), str(wt))
    assert fp
    _seed(d, [_cond(12)], _fid([SCOPE_A]), fp=fp)
    # second run: the PR body gained a note, so the fidelity text differs; plan and diff do not
    note = "[unplanned-scope] Adds bin/foo helper. Next action: keep. Body note reworded."
    _seed(d, [_cond(12)], _fid([note], kept=[_title(SCOPE_A)]), fp=fp)
    argv = _check_argv(d, plan=str(plan), worktree=str(wt))
    assert hr.main(argv) == 10
    assert "owner approval" in capsys.readouterr().out


def test_old_format_record_loads(tmp_path):
    d = str(tmp_path)
    with open(hr.record_path(d, SLUG), "w") as fh:
        json.dump({"schema_version": 1, "slug": SLUG, "structural_key": "7:m",
                   "hold_set": [[7, "plan-auto-merge-hold", "m"]], "structural": [],
                   "repeat_count": 2, "dm_sent_key": "7:m", "fingerprint": "fp",
                   "pr": 1, "updated_at": "t"}, fh)
    assert hr.should_decline(d, SLUG, "fp")[0] is True
    assert hr.owner_only_decline(d, SLUG, "fp") is None
    assert hr.human_only_names(d, SLUG) == "7 plan-auto-merge-hold"


@pytest.mark.parametrize("bad", ["12:kept-unplanned-scope", [12], ["12:kept-unplanned-scope-x"],
                                 {"a": 1}, [["12:kept-unplanned-scope"]]])
def test_malformed_hold_kinds_fail_open(tmp_path, bad):
    d = str(tmp_path)
    _seed(d, [_cond(12)], _fid([SCOPE_A]))
    rec = hr.load_record(d, SLUG)
    rec["hold_kinds"] = bad
    hr._save_record(d, SLUG, rec)
    assert hr.owner_only_decline(d, SLUG, "fp") is None
    assert hr.human_only_names(d, SLUG) is None


@pytest.mark.parametrize("reason", ["state=ONE-WAY", "state=MISSING after 1 retry",
                                    "state=SELF-GOVERNING-X", ""])
def test_kind_a_requires_exact_state(reason):
    assert hr.owner_only_kinds([_cond(18, reason)]) == []


def test_kind_b_tag_must_lead():
    assert hr.owner_only_kinds([_cond(12)], _fid(["Mentions [unplanned-scope] in prose"])) == []
    assert hr.owner_only_kinds([_cond(12)], _fid([SCOPE_B])) == [hr.KIND_KEPT_SCOPE]


def test_kind_b_needs_not_ready_final_verdict():
    assert hr.owner_only_kinds([_cond(12)], _fid([SCOPE_A], verdict="READY")) == []
    assert hr.owner_only_kinds([_cond(12)], {"review_items": []}) == []
    assert hr.owner_only_kinds([_cond(12)], "not a dict") == []


def test_human_only_names_owner_approval_plain_words(tmp_path):
    d = str(tmp_path)
    _seed(d, [_cond(12), _cond(18, SG)], _fid([SCOPE_A]))
    assert hr.human_only_names(d, SLUG) == "owner approval"
    _seed(d, [_cond(1), _cond(18, SG)])
    assert hr.human_only_names(d, SLUG) == "1 manual-testing-clearance, owner approval"
    _seed(d, [_cond(12)])
    assert hr.human_only_names(d, SLUG) is None


def test_foreign_condition_with_kind_not_eligible(tmp_path):
    d = str(tmp_path)
    _seed(d, [_cond(14), _cond(18, SG)])
    assert hr.human_only_names(d, SLUG) is None
    assert hr.owner_only_decline(d, SLUG, "fp") is None


def test_check_message_plan_vs_blind(tmp_path, monkeypatch, capsys):
    d = str(tmp_path)
    _seed(d, [_cond(18, SG)])
    monkeypatch.setattr(hr, "fingerprint", lambda *a, **k: "fp")
    plan = tmp_path / "plan.md"
    plan.write_text("plan\n")
    assert hr.main(_check_argv(d, plan=str(plan))) == 10
    assert capsys.readouterr().out.strip().endswith("add a plan phase for it.")
    assert hr.main(_check_argv(d, slug=SLUG, plan="")) == 10
    assert capsys.readouterr().out.strip().endswith("or run bin/owner-approve.")


def test_kinds_never_enter_structural_key():
    assert hr.structural_key([_cond(12), _cond(18, SG)]) == ""


if __name__ == "__main__":
    # The plan matrix cell is this file path alone, so the file runs itself.
    import pytest
    sys.exit(pytest.main(["-q", __file__]))
