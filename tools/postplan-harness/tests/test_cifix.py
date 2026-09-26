"""Unit tests for harness.cifix — pure CI-fix helpers."""
from __future__ import annotations

import pathlib
import pytest

from harness import cifix
from harness import fidelity


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
            "rollup-only",
            ["Tests and Analysis"],
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
                "name": "ExternalCheck",
                "state": "FAILURE",
                "link": "https://example.com/status",
            }
        ]
        result = cifix.failed_job_refs(checks, ["ExternalCheck"])
        assert "ExternalCheck" not in result


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
            diff_path="/tmp/patch.diff",
            prior=[],
        )
        defaults.update(kwargs)
        return cifix.ci_fix_prompt(**defaults)

    def test_prompt_forbids_blind_expectation_edits(self):
        prompt = self._make_prompt()
        assert "Do NOT change a test's expected value" in prompt
        assert "name the diff line" in prompt

    def test_prompt_passes_log_paths_not_contents(self):
        # A name present in log_paths → path in prompt.
        prompt = self._make_prompt(names=["X", "Y"], log_paths={"X": "/tmp/x.log"})
        assert "/tmp/x.log" in prompt
        # A name absent from log_paths → third-party check notice in prompt.
        assert "third-party check" in prompt

    def test_prompt_carries_gate_edit_deny_text(self):
        prompt = self._make_prompt()
        assert fidelity.GATE_EDIT_DENY_TEXT in prompt

    def test_prompt_lists_prior_attempts(self):
        # Non-empty prior → prior string appears and "Previous attempts:" header appears.
        prompt_with = self._make_prompt(prior=["attempt 1: still-red"])
        assert "attempt 1: still-red" in prompt_with
        assert "Previous attempts:" in prompt_with

        # Empty prior → "Previous attempts:" must NOT appear.
        prompt_without = self._make_prompt(prior=[])
        assert "Previous attempts:" not in prompt_without


class TestSurvivorComment:
    def test_survivor_comment_lists_every_survivor(self):
        body = cifix.survivor_comment(["JobA", "JobB"], ["audit line 1"], False)
        assert "JobA" in body
        assert "JobB" in body
        assert "A human needs to look at these before merging." in body

    def test_survivor_comment_raises_on_empty(self):
        with pytest.raises(ValueError):
            cifix.survivor_comment([], [], False)


class TestWorkflowValidation:
    def test_rollup_names_exist_in_workflows(self):
        """Each name in ROLLUP_CHECKS must appear as 'name: <name>' in some workflow file.

        A rename of the gate job fails this test, preventing the hardcoded set from
        silently rotting.
        """
        repo_root = pathlib.Path(__file__).parent.parent.parent.parent
        workflows_dir = repo_root / ".github" / "workflows"
        assert workflows_dir.is_dir(), f"workflows dir not found at {workflows_dir}"

        workflow_files = list(workflows_dir.glob("*.yml")) + list(workflows_dir.glob("*.yaml"))
        assert workflow_files, "no workflow files found"

        for rollup_name in cifix.ROLLUP_CHECKS:
            needle = f"name: {rollup_name}"
            found = any(
                needle in wf.read_text(encoding="utf-8", errors="replace")
                for wf in workflow_files
            )
            assert found, (
                f"ROLLUP_CHECKS contains {rollup_name!r} but no workflow file under "
                f"{workflows_dir} contains the line 'name: {rollup_name}'. "
                "Update ROLLUP_CHECKS to match the current job name."
            )
