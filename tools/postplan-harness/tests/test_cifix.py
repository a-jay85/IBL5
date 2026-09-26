import os
import pathlib
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import cifix, fidelity
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError


class TestTriage:
    def test_triage_drops_human_signoff(self):
        assert cifix.triage(["human-signoff", "PHPUnit"]) == ("actionable", ["PHPUnit"])

    def test_triage_signoff_only_is_green(self):
        assert cifix.triage(["human-signoff"]) == ("green", [])
        assert cifix.triage([]) == ("green", [])

    def test_triage_drops_rollup_from_actionable(self):
        result = cifix.triage(["Shell harness regression tests", "Tests and Analysis"])
        assert result == ("actionable", ["Shell harness regression tests"])

    def test_triage_rollup_only(self):
        assert cifix.triage(["Tests and Analysis", "human-signoff"]) == (
            "rollup-only", ["Tests and Analysis"]
        )

    def test_triage_dedupes_preserving_order(self):
        assert cifix.triage(["B", "A", "B"]) == ("actionable", ["B", "A"])


class TestFailedJobRefs:
    def test_failed_job_refs_extracts_run_and_job(self):
        checks = [
            {
                "name": "MyJob",
                "state": "FAILURE",
                "link": "https://github.com/o/r/actions/runs/111/job/222",
            }
        ]
        result = cifix.failed_job_refs(checks, ["MyJob"])
        assert result == {"MyJob": ("111", "222")}

    def test_failed_job_refs_skips_non_actions_link(self):
        checks = [
            {
                "name": "ThirdParty",
                "state": "FAILURE",
                "link": "https://example.com/status",
            }
        ]
        result = cifix.failed_job_refs(checks, ["ThirdParty"])
        assert "ThirdParty" not in result


class TestConstants:
    def test_ci_fix_model_is_opus_5_5(self):
        assert cifix.CI_FIX_MODEL_ID == "claude-opus-5-5"
        assert cifix.CI_FIX_MODEL != "sonnet"


class TestCiFixPrompt:
    def _make_prompt(self, **kwargs):
        defaults = dict(
            pr_number=42,
            attempt=1,
            names=["X"],
            log_paths={"X": "/tmp/x.log"},
            diff_path="/tmp/diff.patch",
            prior=[],
        )
        defaults.update(kwargs)
        return cifix.ci_fix_prompt(**defaults)

    def test_prompt_forbids_blind_expectation_edits(self):
        prompt = self._make_prompt()
        assert "Do NOT change a test's expected value" in prompt
        assert "name the diff line" in prompt

    def test_prompt_passes_log_paths_not_contents(self):
        prompt = cifix.ci_fix_prompt(
            pr_number=1,
            attempt=1,
            names=["X", "NoLog"],
            log_paths={"X": "/tmp/x.log"},
            diff_path="/tmp/diff.patch",
            prior=[],
        )
        assert "/tmp/x.log" in prompt
        assert "third-party check" in prompt

    def test_prompt_carries_gate_edit_deny_text(self):
        prompt = self._make_prompt()
        assert fidelity.GATE_EDIT_DENY_TEXT in prompt

    def test_prompt_lists_prior_attempts(self):
        prompt_with = self._make_prompt(prior=["attempt 1: still-red"])
        assert "attempt 1: still-red" in prompt_with

        prompt_without = self._make_prompt(prior=[])
        assert "Previous attempts:" not in prompt_without


class TestSurvivorComment:
    def test_survivor_comment_lists_every_survivor(self):
        body = cifix.survivor_comment(["JobA", "JobB"], ["audit-1"], False)
        assert "JobA" in body
        assert "JobB" in body

    def test_survivor_comment_raises_on_empty(self):
        with pytest.raises(ValueError):
            cifix.survivor_comment([], [], False)


class TestRollupNamesInWorkflows:
    def test_rollup_names_exist_in_workflows(self):
        # Resolve the .github/workflows directory relative to this test file.
        test_dir = pathlib.Path(__file__).resolve().parent
        # Walk up to repo root (the directory containing .github).
        repo_root = test_dir
        for _ in range(10):
            if (repo_root / ".github" / "workflows").is_dir():
                break
            repo_root = repo_root.parent
        else:
            pytest.fail("Could not locate .github/workflows from test file location")

        workflows_dir = repo_root / ".github" / "workflows"

        for name in cifix.ROLLUP_CHECKS:
            found = False
            for wf_path in workflows_dir.glob("*.yml"):
                try:
                    content = wf_path.read_text(errors="replace")
                except OSError:
                    continue
                if f"name: {name}" in content:
                    found = True
                    break
            assert found, (
                f"ROLLUP_CHECKS name {name!r} not found as 'name: {name}' "
                f"in any file under {workflows_dir}"
            )


# ---- Phase 3a: adapter seam tests ----

class TestPushFf:
    def test_push_ff_never_forces(self, monkeypatch, tmp_path):
        captured_argv = []

        def fake_run_out(self, *args):
            captured_argv.extend(args)
            return (0, "")

        git = LiveGit.__new__(LiveGit)
        git.push_remote = "origin"
        git.worktree = str(tmp_path)
        monkeypatch.setattr(LiveGit, "branch", lambda self: "test-branch")
        monkeypatch.setattr(LiveGit, "head", lambda self: "abc123")
        monkeypatch.setattr(LiveGit, "_run_out", fake_run_out)

        git.push_ff()

        assert not any(arg.startswith("--force") for arg in captured_argv)

    def test_push_ff_maps_hook_denial_to_local_gate(self, monkeypatch, tmp_path):
        from harness.adapters.gitad import _LOCAL_GATE_MARKERS

        marker = next(iter(_LOCAL_GATE_MARKERS))

        def fake_run_out(self, *args):
            return (1, f"pre-push hook denied: {marker}")

        git = LiveGit.__new__(LiveGit)
        git.push_remote = "origin"
        git.worktree = str(tmp_path)
        monkeypatch.setattr(LiveGit, "branch", lambda self: "test-branch")
        monkeypatch.setattr(LiveGit, "head", lambda self: "abc123")
        monkeypatch.setattr(LiveGit, "_run_out", fake_run_out)

        with pytest.raises(HarnessError) as exc_info:
            git.push_ff()
        assert exc_info.value.kind == "local-gate"


class TestRecordingGhRerun:
    def test_recording_gh_rerun_records_action(self, tmp_path):
        gh = RecordingGh(str(tmp_path))
        gh.run_rerun_failed("111")

        actions = gh.actions()
        assert any(
            a.get("action") == "run_rerun_failed" and a.get("run_id") == "111"
            for a in actions
        )
