"""PR copy typing on tooling-only diffs.

PRs #2554, #2543, #2505 and #2485 were each made only of bin/, .claude/, tools/ and docs
files, yet opened as `feat:` and were held by the human-signoff floor (condition 8). A diff
with no GM-visible runtime file is retyped feat -> chore; one runtime file keeps `feat:`;
an unknown root keeps the model's type (fail-safe denylist).
"""
import copy
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pytest

from harness.classify import classify
from harness.schemas import coerce_copy_type, coerce_pr_copy

PR_2554 = [".claude/rules/workflow-continuity-detail.md", "bin/automouse/run", "bin/post-plan-now",
           "bin/test-automouse-postplan-engine", "tools/postplan-harness/runner.py",
           "tools/postplan-harness/tests/test_human_block.py",
           "tools/postplan-harness/tests/test_post_plan_now_fallback.py",
           "tools/postplan-harness/tests/test_runner_exit_codes.py",
           "tools/postplan-harness/tests/test_verdict_line.py"]
PR_2543 = [".claude/skills/burndown/SKILL.md", "bin/backlog", "bin/lib/burndown.sh", "bin/test-burndown"]
PR_2505 = ["bin/fleet-status", "bin/test-fleet-status"]
PR_2485 = ["bin/test-wt-sync-tick", "bin/wt-sync-tick",
           "ibl5/docs/decisions/0106-worktree-sync-tick.md", "ibl5/docs/decisions/0133-sync-tick-hold.md"]
PR_2551 = ["bin/sim-recap-cron-setup", "bin/sim-recap-tick", "ibl5/classes/SimRecap/README.md",
           "ibl5/classes/SimRecap/RecapPhasePolicy.php", "ibl5/docs/OPERATIONS_RUNBOOK.md",
           "ibl5/tests/SimRecap/RecapPhasePolicyTest.php", "ibl5/tests/SimRecap/RecapTickTest.php",
           "ibl5/tests/SimRecap/RecapCronSetupTest.php"]


def _feat_copy() -> dict:
    return {"type": "feat", "title": "feat: t", "commit_subject": "feat: t", "summary_md": ""}


@pytest.mark.parametrize("files", [PR_2554, PR_2543, PR_2505, PR_2485],
                         ids=["2554", "2543", "2505", "2485"])
def test_held_tooling_prs_retype_feat_to_chore(files):
    cls = classify(files, "")
    assert cls.has_gm_visible is False
    assert coerce_copy_type("feat: harness exit codes", cls) == "chore: harness exit codes"
    assert coerce_pr_copy(_feat_copy(), cls)["title"] == "chore: t"


def test_sim_recap_pr_2551_stays_feat():
    cls = classify(PR_2551, "")
    assert cls.has_gm_visible is True
    assert coerce_copy_type("feat: sim recap phase policy", cls) == "feat: sim recap phase policy"


def test_feat_title_on_mixed_diff_is_untouched():
    cls = classify(PR_2554 + ["ibl5/classes/Harness/Foo.php"], "")
    assert cls.has_gm_visible is True
    assert coerce_copy_type("feat: x", cls) == "feat: x"
    before = copy.deepcopy(_feat_copy())
    assert coerce_pr_copy(_feat_copy(), cls) == before


def test_unknown_root_keeps_model_type():
    cls = classify(["newtool/whatever.py"], "")
    assert cls.has_gm_visible is True
    assert coerce_copy_type("feat: x", cls) == "feat: x"


def test_non_feat_types_on_tooling_diff_pass_through():
    cls = classify(PR_2505, "")
    for subject in ("fix: x", "refactor: x", "docs: x", "ci: x", "chore: x"):
        assert coerce_copy_type(subject, cls) == subject
