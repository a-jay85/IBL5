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


class TestSkillAndCompanionPhase7AreOpusOnly:
    def test_skill_and_companion_phase7_are_opus_only(self):
        test_dir = pathlib.Path(__file__).resolve().parent
        repo_root = test_dir
        for _ in range(10):
            if (repo_root / ".claude" / "skills" / "post-plan" / "SKILL.md").exists():
                break
            repo_root = repo_root.parent
        else:
            pytest.fail("Could not locate .claude/skills/post-plan/SKILL.md")

        skill_path = repo_root / ".claude" / "skills" / "post-plan" / "SKILL.md"
        companion_path = repo_root / ".claude" / "skills" / "post-plan" / "_phase-7-ci-monitoring.md"

        skill_full = skill_path.read_text()
        # Slice Phase 7 section only
        start = skill_full.find("## Phase 7: CI Monitoring")
        end = skill_full.find("## Phase 8", start)
        skill_slice = skill_full[start:end] if start >= 0 and end >= 0 else skill_full[start:]

        companion_text = companion_path.read_text()

        assert "Sonnet" not in skill_slice, "skill Phase 7 section still mentions Sonnet"
        assert "Sonnet" not in companion_text, "_phase-7-ci-monitoring.md still mentions Sonnet"
        assert "mutation|MSI" not in skill_slice
        assert "mutation|MSI" not in companion_text
        assert "Opus 5.5" in skill_slice, "skill Phase 7 section missing Opus 5.5"
        assert "Opus 5.5" in companion_text, "_phase-7-ci-monitoring.md missing Opus 5.5"
        assert "gh run rerun" in skill_slice
        assert "gh run rerun" in companion_text
        assert f"{cifix.MAX_CI_FIX_ATTEMPTS} attempts" in skill_slice
        assert "Tests and Analysis" in skill_slice


# ---- Phase 7 gate-path deny arm (backlog #890) ----

class TestCiFixGatePathDeny:
    def test_gate_path_edit_in_fix_commit_stops_loop_without_push(self, monkeypatch, tmp_path):
        from test_runner_replay import (_TwoShaLiveGit, _audit, _red_fixture,
                                        _run_live_shaped_with)

        events = []

        class _GateEditFixGit(_TwoShaLiveGit):
            def changed_files(self, base="origin/master"):
                events.append(("changed_files", base))
                if base.endswith("^"):
                    return [".github/workflows/ci.yml", "ibl5/classes/Foo.php"]
                return super().changed_files(base)

            def push_ff(self):
                events.append(("push_ff", None))
                return super().push_ff()

        out = str(tmp_path / "out")
        # A rewatch entry is scripted so a (wrongly) continuing loop would consume it.
        fx = _red_fixture(ci_fix_rewatch=[{"exit": 0, "failed": []}])
        res, _ = _run_live_shaped_with(
            monkeypatch, out,
            git_cls=_GateEditFixGit,
            canned_extra={"ci-fix": ["edits made"]},
            fixture=fx,
        )

        audit = _audit(out)
        assert "phase7 ci-fix: gate-path edit detected in fix commit replay-sha-" in audit
        assert "phase7 ci-fix attempt 1: model=claude-opus-5-5 outcome=error:gate-path-edit" in audit
        # Loop stopped: no second attempt, no rewatch-driven "fixed".
        assert "ci-fix attempt 2:" not in audit
        assert "outcome=fixed" not in audit
        assert res.ci_outcome == "failed"

        git = _GateEditFixGit.instances[-1]
        fix_msgs = [m for m in git.commit_messages
                    if m.startswith("fix: address Phase 7 CI failures")]
        assert len(fix_msgs) == 1

        # changed_files was asked about the fix commit's parent, and nothing pushed after.
        caret_calls = [i for i, e in enumerate(events)
                       if e[0] == "changed_files" and e[1].endswith("^")]
        assert len(caret_calls) == 1
        new_sha = f"replay-sha-{len(git.commit_messages)}"
        assert events[caret_calls[0]] == ("changed_files", f"{new_sha}^")
        assert not any(e[0] == "push_ff" for e in events[caret_calls[0]:])
