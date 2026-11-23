import os, stat, sys
import pytest
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from harness import fidelity

T1, T2 = "a" * 40, "b" * 40
SKILL_STICKY = (f"**Reviewed tree:** {T1}\n\nREVIEW-COVERAGE: CURRENT\n"
                "<!-- pr-ready-verdict -->\n")
HARNESS_STICKY = ("Plan-fidelity verdict: READY\n\n"
                  f"**Reviewed tree:** {T1}\n<!-- pr-ready-verdict -->\n")


def _stub_pr_review_now(tmp_path, monkeypatch, rc=0):
    """A pr-review-now that appends its argv to fired.log and exits rc."""
    log = tmp_path / "fired.log"
    stub = tmp_path / "pr-review-now"
    stub.write_text("#!/usr/bin/env bash\n"
                    f"printf '%s\\n' \"$*\" >> '{log}'\nexit {rc}\n")
    stub.chmod(stub.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("REVIEW_OWED_PR_REVIEW_NOW", str(stub))
    return log


def _serve(monkeypatch, serve: dict):
    """The conftest pattern: `_git_show` answers from `serve`, None otherwise."""
    monkeypatch.setattr(fidelity, "_git_show",
                        lambda wt, ref: serve.get(ref.split(":", 1)[-1]))


def test_harness_sticky_always_owes_and_fires(tmp_path, monkeypatch):
    fired = _stub_pr_review_now(tmp_path, monkeypatch)
    result = fidelity.fire_review_owed(
        None, "master_sha", 123, HARNESS_STICKY, T1, str(tmp_path), live=True)
    assert result["verdict"] == "REVIEW-OWED"
    assert result["fired"] is True
    assert fired.exists() and fired.read_text().strip() == "123"
    assert result["command"].startswith("FIRED: ")


def test_harness_sticky_with_banner_line_one_still_owes(tmp_path, monkeypatch):
    fired = _stub_pr_review_now(tmp_path, monkeypatch)
    body = (f"**READY** \u2014 2026-09-30 10:00:00 PDT \u00b7 auto-merge armed\n\n"
            "Findings: none.\n\n---\n\n<details><summary>Audit trail</summary>\n\n"
            f"REVIEW-COVERAGE: CURRENT\n**Reviewed tree:** {T1}\n\n</details>\n\n"
            "READY\n<!-- pr-ready-verdict -->\n")
    result = fidelity.fire_review_owed(
        None, "master_sha", 123, body, T1, str(tmp_path), live=True)
    assert result["verdict"] == "REVIEW-OWED"
    assert result["fired"] is True


def test_skill_sticky_current_marker_and_same_tree_is_current(tmp_path, monkeypatch):
    fired = _stub_pr_review_now(tmp_path, monkeypatch)
    result = fidelity.fire_review_owed(
        None, "master_sha", 123, SKILL_STICKY, T1, str(tmp_path), live=True)
    assert result["verdict"] == "REVIEW-CURRENT"
    assert result["reason"] == T1
    assert result["fired"] is False
    assert not fired.exists()


def test_skill_sticky_current_marker_inside_details_is_current(tmp_path, monkeypatch):
    fired = _stub_pr_review_now(tmp_path, monkeypatch)
    sticky = (f"**Reviewed tree:** {T1}\n\n<details><summary>Audit trail</summary>\n\n"
              "REVIEW-COVERAGE: CURRENT\n\n</details>\n<!-- pr-ready-verdict -->\n")
    result = fidelity.fire_review_owed(
        None, "master_sha", 123, sticky, T1, str(tmp_path), live=True)
    assert result["verdict"] == "REVIEW-CURRENT"
    assert result["reason"] == T1
    assert result["fired"] is False
    assert not fired.exists()


def test_tree_changed_after_remediation_fires(tmp_path, monkeypatch):
    fired = _stub_pr_review_now(tmp_path, monkeypatch)
    result = fidelity.fire_review_owed(
        None, "master_sha", 123, SKILL_STICKY, T2, str(tmp_path), live=True)
    assert result["verdict"] == "REVIEW-OWED"
    assert result["reason"] == "tree-changed"
    assert result["fired"] is True


def test_replay_dry_runs_and_never_launches(tmp_path, monkeypatch):
    fired = _stub_pr_review_now(tmp_path, monkeypatch)
    result = fidelity.fire_review_owed(
        None, "master_sha", 123, HARNESS_STICKY, T1, str(tmp_path), live=False)
    assert result["verdict"] == "REVIEW-OWED"
    assert result["fired"] is False
    assert result["command"].startswith("DRY-RUN: ")
    assert not fired.exists()


def test_fire_failure_still_reports_owed(tmp_path, monkeypatch):
    fired = _stub_pr_review_now(tmp_path, monkeypatch, rc=3)
    result = fidelity.fire_review_owed(
        None, "master_sha", 123, HARNESS_STICKY, T1, str(tmp_path), live=True)
    assert result["verdict"] == "REVIEW-OWED"
    assert result["fired"] is False
    assert result["command"] == "FIRE-FAILED: rc=3"


def test_no_pr_skips_without_writing_anything(tmp_path):
    result = fidelity.fire_review_owed(
        None, "master_sha", None, HARNESS_STICKY, T1, str(tmp_path))
    assert result["verdict"] == "skipped"
    assert result["reason"] == "no-pr"
    assert not (tmp_path / "review-owed.sh").exists()


def test_script_missing_at_master_degrades(tmp_path, monkeypatch):
    _serve(monkeypatch, {})
    result = fidelity.fire_review_owed(
        str(tmp_path), "master_sha", 123, HARNESS_STICKY, T1, str(tmp_path))
    assert result["verdict"] == "unavailable"
    assert result["reason"] == "script-unavailable"
    assert result["fired"] is False


def test_script_is_sourced_from_master_sha(tmp_path, monkeypatch):
    canned_script = f"#!/usr/bin/env bash\necho 'REVIEW-CURRENT {T2}'\n"
    _serve(monkeypatch, {".claude/review-shared/scripts/review-owed.sh": canned_script})
    result = fidelity.fire_review_owed(
        str(tmp_path), "master_sha", 123, HARNESS_STICKY, T1, str(tmp_path))
    assert result["verdict"] == "REVIEW-CURRENT"
    assert result["reason"] == T2


def test_usage_error_and_timeout_degrade(tmp_path, monkeypatch):
    # usage-error: a script that exits 2
    exit2_script = "#!/usr/bin/env bash\nexit 2\n"
    _serve(monkeypatch, {".claude/review-shared/scripts/review-owed.sh": exit2_script})
    result = fidelity.fire_review_owed(
        str(tmp_path), "master_sha", 123, HARNESS_STICKY, T1, str(tmp_path))
    assert result["reason"] == "usage-error"
    assert result["fired"] is False

    # timeout: a script that sleeps longer than REVIEW_OWED_TIMEOUT
    sleep_script = "#!/usr/bin/env bash\nsleep 5\n"
    _serve(monkeypatch, {".claude/review-shared/scripts/review-owed.sh": sleep_script})
    monkeypatch.setattr(fidelity, "REVIEW_OWED_TIMEOUT", 1)
    result2 = fidelity.fire_review_owed(
        str(tmp_path), "master_sha", 123, HARNESS_STICKY, T1, str(tmp_path))
    assert result2["reason"] == "timeout"
    assert result2["fired"] is False


def test_review_owed_fires_when_fix_round_rejudge_indeterminate(tmp_path, monkeypatch):
    """An indeterminate fix-round re-judge writes nothing: the sticky names no new tree."""
    import types
    import runner
    from tests.test_fidelity_sticky import _sticky

    fired = _stub_pr_review_now(tmp_path, monkeypatch)

    class Git:
        def head_tree(self):
            return T2

    class Gh:
        def pr_body_fresh(self):
            return "body"

    monkeypatch.setattr(runner.fidelity, "re_review", lambda *a, **k: (None, None, None))
    fid = {"verdict_1": "READY WITH NOTES", "error_kind": None, "reviewed_tree": T1,
           "remediation_sha": None, "verdict_2": None, "reviewed_tree_2": None,
           "verdict_path": "/tmp/post-plan-fidelity-verdict-1.md"}
    res = types.SimpleNamespace(fidelity=fid, slug="")
    runner._rejudge_fidelity_after_fix_round(None, Git(), Gh(), "out", "wt", None, "m", "body", 1,
                                             "abc1234", res, lambda *_: None)
    body = _sticky(fid=fid, terminal=fidelity.sticky_terminal(fid),
                   excerpt="REVIEW-COVERAGE: CURRENT\nfinding one")
    assert "**Re-reviewed tree:**" not in body
    result = fidelity.fire_review_owed(None, "master_sha", 123, body, T2, str(tmp_path), live=True)
    assert result["verdict"] == "REVIEW-OWED"
    assert result["reason"] == "no-tree-line"
    assert result["fired"] is True
    assert fired.read_text().strip() == "123"
