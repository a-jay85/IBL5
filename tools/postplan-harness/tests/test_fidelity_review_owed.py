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


def test_skill_sticky_current_marker_and_same_tree_is_current(tmp_path, monkeypatch):
    fired = _stub_pr_review_now(tmp_path, monkeypatch)
    result = fidelity.fire_review_owed(
        None, "master_sha", 123, SKILL_STICKY, T1, str(tmp_path), live=True)
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
